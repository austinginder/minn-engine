<?php

declare(strict_types=1);

namespace Minn\Extension;

use InvalidArgumentException;
use Minn\Content\Site;
use Minn\Db;
use Minn\Json;
use Minn\Runtime\DbDelta;
use Minn\Runtime\Runtime;

/**
 * An extension's own tables, from the "tables" its minn.json declares:
 * created under the site's table prefix the first time the extension
 * registers, and brought up to date (missing columns and keys added, as
 * dbDelta adds them) when its version or its declaration changes. What
 * was applied is recorded per extension in one option, so a request
 * after that costs one autoloaded read. Nothing is ever dropped, renamed
 * or changed: a column a later version leaves out stays, and so does
 * every table when the extension goes away.
 */
final readonly class Tables
{
    /** The option recording, per extension slug, the version and declaration last applied. */
    public const OPTION = 'minn_extension_tables';

    private const FALLBACK_COLLATION = 'utf8mb4_unicode_520_ci';

    /** WordPress's own tables, unprefixed: no extension declares one, whatever its slug ("term" would own term_relationships). */
    private const CORE_TABLES = ['posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'users', 'usermeta', 'options', 'links', 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'blog_versions'];

    public function __construct(private Db $db, private Site $site)
    {
    }

    /**
     * Applies an extension's tables when they changed since the last time,
     * and records them. Returns what was done (empty when nothing was due),
     * by table or table.column, in DbDelta's words. A declaration that
     * names a table outside the extension's own throws, which skips it.
     *
     * @return array<string, string>
     */
    public function settle(Manifest $manifest): array
    {
        if ($manifest->tables === []) {
            return [];
        }
        $marker = self::marker($manifest);
        $recorded = $this->recorded();
        if (($recorded[$manifest->slug] ?? null) === $marker) {
            return [];
        }
        $statements = self::statements($manifest, $this->db->prefix(), $this->collation(), array_keys($recorded));
        $done = (new DbDelta($this->db))->apply($statements, true);
        $this->site->setOption(self::OPTION, Json::encode([$manifest->slug => $marker] + $recorded));
        return $done;
    }

    /**
     * The CREATE TABLE statements an extension declares, keyed by the
     * prefixed table name: each name must be the extension's slug (dashes
     * as underscores) followed by "_" and lowercase letters, digits or
     * underscores, and each line one column or key definition. No name is
     * one of WordPress's own tables, or inside the names of another
     * extension (from $others, the slugs with tables recorded) whose slug
     * is longer: acme may not declare acme_x_orders while acme-x has tables
     * (security sweep 2026-11-12, finding 1.5).
     *
     * @param list<string> $others
     * @return array<string, string>
     */
    public static function statements(Manifest $manifest, string $prefix, string $collation = self::FALLBACK_COLLATION, array $others = []): array
    {
        $own = str_replace('-', '_', strtolower($manifest->slug)) . '_';
        $charset = (string) strtok($collation, '_');
        $theirs = array_filter(array_map(static fn (string $slug): string => str_replace('-', '_', strtolower($slug)) . '_', $others), static fn (string $prefix): bool => strlen($prefix) > strlen($own) && str_starts_with($prefix, $own));
        $statements = [];
        foreach ($manifest->tables as $name => $lines) {
            if (!str_starts_with($name, $own) || preg_match('/^[a-z0-9_]+$/', $name) !== 1 || strlen($prefix . $name) > 64 || in_array($name, self::CORE_TABLES, true)) {
                throw new InvalidArgumentException("{$manifest->slug} may declare only tables named {$own}*: {$name} is not one");
            }
            foreach ($theirs as $other) {
                if (str_starts_with($name, $other)) {
                    throw new InvalidArgumentException("{$manifest->slug} may not declare {$name}: its name is another extension's ({$other}*)");
                }
            }
            foreach ($lines as $line) {
                if (trim($line) === '' || preg_match('/[;\r\n]|--|\/\*/', $line) === 1) {
                    throw new InvalidArgumentException("{$manifest->slug}: table {$name} has a line that is not one column or key definition: {$line}");
                }
            }
            $table = $prefix . $name;
            $body = implode(",\n", array_map(static fn (string $line): string => '  ' . trim($line), $lines));
            $statements[$table] = "CREATE TABLE IF NOT EXISTS {$table} (\n{$body}\n) DEFAULT CHARACTER SET {$charset} COLLATE {$collation}";
        }
        return $statements;
    }

    /** What is recorded for an extension once its tables are applied: its version and a digest of the declaration. */
    public static function marker(Manifest $manifest): string
    {
        return $manifest->version . ':' . substr(hash('sha256', Json::encode($manifest->tables)), 0, 16);
    }

    /** @return array<string, string> slug => marker */
    private function recorded(): array
    {
        $recorded = Json::decode((string) (Runtime::options()->raw(self::OPTION) ?? ''), or: null);
        return is_array($recorded) ? array_filter($recorded, is_string(...)) : [];
    }

    /** The collation the site's own options table was made with, so extension tables sort text as the site does. */
    private function collation(): string
    {
        $collation = (string) $this->db->value('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$this->db->table('options')]);
        return preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $collation) === 1 ? $collation : self::FALLBACK_COLLATION;
    }
}
