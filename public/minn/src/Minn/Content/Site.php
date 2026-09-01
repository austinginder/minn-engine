<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/** Site-wide options and the site's clock. */
final readonly class Site
{
    public function __construct(private Db $db)
    {
    }

    /** One option's raw value, or null when it is unset. */
    public function option(string $name): ?string
    {
        return $this->db->option($name);
    }

    /** Writes an option, inserting it as autoloaded when it does not exist. */
    public function setOption(string $name, string $value): void
    {
        $table = $this->db->table('options');
        if ($this->db->option($name) === null) {
            $this->db->execute("INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, 'auto')", [$name, $value]);
            return;
        }
        $this->db->execute("UPDATE {$table} SET option_value = ? WHERE option_name = ?", [$value, $name]);
    }

    /** Removes an option row. */
    public function deleteOption(string $name): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [$name]);
    }

    /** The gmt_offset option in seconds. */
    public function gmtOffset(): int
    {
        return (int) round(((float) ($this->db->option('gmt_offset') ?? 0)) * 3600);
    }

    /** Now in the site's local time, MySQL format. */
    public function localNow(): string
    {
        return gmdate('Y-m-d H:i:s', time() + $this->gmtOffset());
    }

    /**
     * A site-local datetime from a write body ("2031-01-01T00:00:00") to
     * the pair of columns it fills, plus its timestamp.
     *
     * @return array{0: string, 1: string, 2: int} local, gmt, timestamp
     */
    public function localDate(string $input): array
    {
        $local = str_replace('T', ' ', $input);
        $stamp = strtotime($local . ' UTC') - $this->gmtOffset();
        return [$local, gmdate('Y-m-d H:i:s', $stamp), $stamp];
    }
}
