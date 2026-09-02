<?php

declare(strict_types=1);

namespace Minn\Cron;

use Minn\Ops\Updates;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Db;

/**
 * The engine's scheduled work: scheduled posts go live when their time
 * comes, and expired throttle rows and transients are swept. Triggered by
 * wp-cron.php, `wp minn cron`, `minn cron`, or a front request that finds
 * a post due. One run at a time, through a short-lived lock option.
 */
final readonly class Cron
{
    private const LOCK = 'minn_cron_lock';
    private const LOCK_TTL = 60;

    public function __construct(private Db $db, private Site $site, private PostWriter $writer, private ?Updates $updates = null)
    {
    }

    /** Runs every due job; the report lists what happened. @return list<string> */
    public function run(): array
    {
        if (!$this->lock()) {
            return ['another run holds the lock'];
        }
        try {
            $report = [];
            $published = $this->publishDue();
            $report[] = "published {$published} scheduled post" . ($published === 1 ? '' : 's');
            $report[] = 'swept ' . $this->sweepTransients() . ' expired transients';
            $report[] = 'swept ' . $this->sweepThrottle() . ' expired throttle rows';
            if ($this->updates !== null && (int) ($this->site->option('minn_auto_updates_last') ?? 0) < time() - 86400) {
                $this->site->setOption('minn_auto_updates_last', (string) time());
                $done = $this->updates->runAuto();
                $report[] = 'applied ' . count($done) . ' automatic update' . (count($done) === 1 ? '' : 's');
            }
            $this->site->setOption('minn_cron_last', (string) time());
            return $report;
        } finally {
            $this->unlock();
        }
    }

    /** True when a scheduled post's time has come. */
    public function due(): bool
    {
        return $this->db->value(
            "SELECT 1 FROM {$this->db->table('posts')} WHERE post_status = 'future' AND post_date_gmt <= ? LIMIT 1",
            [gmdate('Y-m-d H:i:s')],
        ) !== null;
    }

    /** A scheduled post goes live as the reference publishes it: status only, dates and modified untouched, term counts refreshed. */
    private function publishDue(): int
    {
        $rows = $this->db->rows(
            "SELECT ID FROM {$this->db->table('posts')} WHERE post_status = 'future' AND post_date_gmt <= ? ORDER BY post_date_gmt ASC LIMIT 200",
            [gmdate('Y-m-d H:i:s')],
        );
        foreach ($rows as $row) {
            $this->db->execute("UPDATE {$this->db->table('posts')} SET post_status = 'publish' WHERE ID = ? AND post_status = 'future'", [(int) $row['ID']]);
            $this->writer->recountTaxonomiesOf((int) $row['ID']);
        }
        return count($rows);
    }

    private function sweepTransients(): int
    {
        $table = $this->db->table('options');
        $expired = $this->db->rows(
            "SELECT option_name FROM {$table} WHERE option_name LIKE '\\_transient\\_timeout\\_%' AND option_value < ? LIMIT 500",
            [(string) time()],
        );
        foreach ($expired as $row) {
            $name = substr((string) $row['option_name'], strlen('_transient_timeout_'));
            $this->db->execute("DELETE FROM {$table} WHERE option_name IN (?, ?)", ['_transient_timeout_' . $name, '_transient_' . $name]);
        }
        return count($expired);
    }

    private function sweepThrottle(): int
    {
        $table = $this->db->table('options');
        $rows = $this->db->rows("SELECT option_name, option_value FROM {$table} WHERE option_name LIKE 'minn\\_login\\_throttle\\_%'");
        $swept = 0;
        foreach ($rows as $row) {
            if (preg_match('/^(\d+):/', (string) $row['option_value'], $m) && (int) $m[1] + 900 <= time()) {
                $this->db->execute("DELETE FROM {$table} WHERE option_name = ?", [$row['option_name']]);
                $swept++;
            }
        }
        return $swept;
    }

    private function lock(): bool
    {
        $table = $this->db->table('options');
        $now = time();
        $held = $this->db->option(self::LOCK);
        if ($held !== null && (int) $held + self::LOCK_TTL > $now) {
            return false;
        }
        if ($held === null) {
            return $this->db->execute("INSERT IGNORE INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, 'off')", [self::LOCK, (string) $now]) === 1;
        }
        return $this->db->execute("UPDATE {$table} SET option_value = ? WHERE option_name = ? AND option_value = ?", [(string) $now, self::LOCK, $held]) === 1;
    }

    private function unlock(): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [self::LOCK]);
    }
}
