<?php

declare(strict_types=1);

namespace Minn\Cron;

use Minn\Auth\LoginThrottle;

use Closure;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Db;
use Minn\Ops\Asking;
use Minn\Ops\Releases;
use Minn\Ops\Updates;
use Minn\Runtime\CronTable;
use Minn\Runtime\PostWrites;
use Minn\Runtime\Runtime;
use Throwable;

/**
 * The engine's scheduled work: scheduled posts go live when their time
 * comes, the cron option's due events fire, expired throttle rows and
 * transients are swept, and once every 24 hours the update service is
 * asked about Minn, plugins and themes before automatic updates run. Triggered
 * by wp-cron.php, `wp minn cron`, `minn cron`, or a front request that
 * finds something due (after its response is sent). One run at a time,
 * through a short-lived lock option.
 *
 * Firing the cron option's due hooks needs the booted runtime and the
 * facade, so it arrives as a closure the caller supplies (from the front
 * pipeline, or the CLI once it has booted the runtime); with none, only
 * the engine's own scheduled posts and sweeps run.
 */
final readonly class Cron
{
    private const LOCK = 'minn_cron_lock';
    private const LOCK_TTL = 60;

    /** When the next daily update pass is due (a timestamp); the slots stay 24 hours apart however late a run is. */
    private const DAILY = 'minn_daily_next';
    private const DAY = 86400;

    /** @param ?Closure(): int $fireDueEvents fires the cron option's due hooks and returns how many ran */
    public function __construct(private Db $db, private Site $site, private PostWriter $writer, private ?Updates $updates = null, private ?Closure $fireDueEvents = null)
    {
    }

    /**
     * The one recipe every trigger builds from: the post writer, the
     * updater over the site's wp-content, and the runtime's firing closure.
     *
     * @param ?Closure(): int $fireDueEvents
     */
    public static function create(Db $db, Site $site, string $contentDir, ?Closure $fireDueEvents): self
    {
        $updates = Updates::forSite($site, $contentDir);
        return new self($db, $site, new PostWriter($db, new Posts($db), $site), $updates, $fireDueEvents);
    }

    /** Runs every due job; the report lists what happened. @return list<string> */
    public function run(): array
    {
        if (!$this->lock()) {
            return ['another run holds the lock'];
        }
        try {
            // Posts go live first: a plugin's failing callback never holds one back.
            $published = $this->publishDue();
            $report = ["published {$published} scheduled post" . ($published === 1 ? '' : 's')];
            if ($this->fireDueEvents !== null) {
                $report[] = $this->fireEvents();
            }
            $report[] = 'swept ' . $this->sweepTransients() . ' expired transients';
            $report[] = 'swept ' . $this->sweepThrottle() . ' expired throttle rows';
            $slot = $this->dailySlot();
            if ($this->updates !== null && $slot <= time()) {
                array_push($report, ...$this->daily($slot));
            }
            $this->site->setOption('minn_cron_last', (string) time());
            return $report;
        } finally {
            $this->unlock();
        }
    }

    /**
     * True when a hit has nothing to do: no job is due and the housekeeping
     * (expired transients and throttle rows) ran within the last minute, so
     * a request repeated as fast as a visitor can send it costs two reads.
     */
    public function idle(): bool
    {
        return !$this->due() && (int) (Runtime::options()->get('minn_cron_last') ?? 0) > time() - 60;
    }

    /** True when a scheduled post's time has come, the cron option holds a due event, or the daily update pass is due. */
    public function due(): bool
    {
        return ($this->updates !== null && $this->dailySlot() <= time()) || $this->postDue() || CronTable::due(CronTable::fromBlob($this->site->option('cron')), time()) !== [];
    }

    /**
     * Fires the due events through the runtime. A callback that throws
     * ends the events step, as a fatal ends the reference's cron request;
     * the event was already rescheduled or removed, so it does not repeat
     * on the next trigger, and the sweeps still run.
     */
    private function fireEvents(): string
    {
        try {
            $fired = ($this->fireDueEvents)();
            return "fired {$fired} scheduled event" . ($fired === 1 ? '' : 's');
        } catch (Throwable $failure) {
            error_log('minn cron: a scheduled event failed: ' . $failure->getMessage());
            return 'scheduled events stopped: ' . $failure->getMessage();
        }
    }

    /**
     * The slot after a daily pass that ran at `$now` for `$slot`: 24 hours on
     * from the first run, then whole days on from the slot, the first one
     * still ahead (a site that slept a week checks once, not seven times).
     */
    public static function nextSlot(int $slot, int $now): int
    {
        return $slot <= 0 ? $now + self::DAY : $slot + self::DAY * (intdiv(max(0, $now - $slot), self::DAY) + 1);
    }

    /** When the daily update pass is next due; 0 on a site that has never run one. */
    private function dailySlot(): int
    {
        return (int) ($this->site->option(self::DAILY) ?? 0);
    }

    /**
     * The daily update pass, once every 24 hours: the update service asked
     * about Minn's releases and about the site's plugins and themes, each
     * marked as the day's scheduled check (the service counts those, one a
     * site, and keeps nothing else), then the automatic updates. The next
     * slot is written first, 24 hours after this one and never in the past,
     * so a failure waits for tomorrow instead of repeating on every trigger,
     * and a late run does not push the next one later. What was refused is
     * reported by name.
     *
     * @return list<string>
     */
    private function daily(int $slot): array
    {
        $this->site->setOption(self::DAILY, (string) self::nextSlot($slot, time()));
        $lines = [];
        try {
            Releases::forSite($this->site, defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : '0.0.0')->refresh(Asking::Daily);
            $lines[] = 'asked the update service about Minn';
        } catch (Throwable $failure) {
            $lines[] = 'the release check stopped: ' . $failure->getMessage();
        }
        try {
            $this->updates->refresh(Asking::Daily);
            $lines[] = 'checked plugins and themes';
        } catch (Throwable $failure) {
            $lines[] = 'the plugin and theme check stopped: ' . $failure->getMessage();
        }
        $result = $this->updates->runAuto(\Minn\Ops\AutoUpdates::forSite());
        $lines[] = 'applied ' . count($result['done']) . ' automatic update' . (count($result['done']) === 1 ? '' : 's');
        foreach ($result['failed'] as $item => $reason) {
            $lines[] = "automatic update of {$item} refused: {$reason}";
        }
        return $lines;
    }

    private function postDue(): bool
    {
        return $this->db->value(
            "SELECT 1 FROM {$this->db->table('posts')} WHERE post_status = 'future' AND post_date_gmt <= ? LIMIT 1",
            [gmdate('Y-m-d H:i:s')],
        ) !== null;
    }

    /**
     * A scheduled post goes live as the reference publishes it: status only,
     * dates and modified untouched, term counts refreshed. With plugins
     * loaded it goes through check_and_publish_future_post, the reference's
     * own publish_future_post work, so they hear publish_post and the
     * transition; a plugin that throws on one post holds back no other.
     */
    private function publishDue(): int
    {
        $rows = $this->db->rows(
            "SELECT ID FROM {$this->db->table('posts')} WHERE post_status = 'future' AND post_date_gmt <= ? ORDER BY post_date_gmt ASC LIMIT 200",
            [gmdate('Y-m-d H:i:s')],
        );
        foreach ($rows as $row) {
            $id = (int) $row['ID'];
            if (Runtime::booted()) {
                try {
                    PostWrites::publishFuture($id);
                } catch (Throwable $failure) {
                    error_log("minn cron: publishing scheduled post {$id} stopped in a plugin: " . $failure->getMessage());
                }
                continue;
            }
            $this->db->execute("UPDATE {$this->db->table('posts')} SET post_status = 'publish' WHERE ID = ? AND post_status = 'future'", [$id]);
            $this->writer->recountTaxonomiesOf($id);
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
        return LoginThrottle::sweep($this->db, time());
    }

    private function lock(): bool
    {
        $table = $this->db->table('options');
        $now = time();
        // The row itself: another process may hold the lock this instant.
        $held = $this->db->storedOption(self::LOCK);
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
        Runtime::optionWritten(self::LOCK);
    }
}
