<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The update offers the site's own plugins publish. A plugin that hosts
 * itself, or sells itself, is not in the wordpress.org directory and never
 * appears in the directory's answer: it publishes its offer by filtering
 * the update transient WordPress reads, which is the only place anyone
 * learns of it. The engine asks the same question of the runtime, in the
 * shape the reference asks it, so a self-hosted plugin is offered its
 * update here exactly as it would be on WordPress.
 *
 * The transient carries `checked` (every installed plugin and its version),
 * and a plugin's updater returns early when that is empty, so the installed
 * versions are what make the question answerable.
 */
final class PluginUpdates
{
    private const HOOK = 'site_transient_update_plugins';

    /**
     * What the site's plugins offer for themselves, empty when no runtime
     * is booted or nothing filters the transient.
     *
     * @param array<string, string> $installed plugin file => installed version
     * @return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}
     */
    public static function supplied(array $installed): array
    {
        if ($installed === [] || !Runtime::booted() || Runtime::hooks()->has(self::HOOK) === false) {
            return ['plugins' => [], 'no_update' => []];
        }
        $transient = (object) [
            'last_checked' => time(),
            'checked' => $installed,
            'response' => [],
            'no_update' => [],
            'translations' => [],
        ];
        return self::fromFiltered(Runtime::hooks()->filter(self::HOOK, [$transient]), $installed);
    }

    /**
     * The filtered transient as the state's two buckets: offers under
     * `plugins`, plugins that answered "current" under `no_update`, both
     * as arrays, and only for files this site actually has.
     *
     * @param array<string, string> $installed
     * @return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}
     */
    public static function fromFiltered(mixed $filtered, array $installed): array
    {
        return [
            'plugins' => self::bucket($filtered, 'response', $installed),
            'no_update' => self::bucket($filtered, 'no_update', $installed),
        ];
    }

    /**
     * One bucket of the transient, normalised.
     *
     * @param array<string, string> $installed
     * @return array<string, array<string, mixed>>
     */
    private static function bucket(mixed $filtered, string $name, array $installed): array
    {
        $bucket = is_object($filtered) ? ($filtered->{$name} ?? null) : (is_array($filtered) ? ($filtered[$name] ?? null) : null);
        if (!is_array($bucket) && !is_object($bucket)) {
            return [];
        }
        $out = [];
        foreach ((array) $bucket as $file => $offer) {
            $file = (string) $file;
            if (!isset($installed[$file]) || (!is_array($offer) && !is_object($offer))) {
                continue;
            }
            $out[$file] = array_filter((array) $offer, static fn (mixed $value): bool => is_scalar($value) || is_array($value) || $value === null);
        }
        return $out;
    }
}
