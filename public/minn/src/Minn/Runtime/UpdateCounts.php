<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\I18n\Gettext;
use Minn\Support\Escape;

/**
 * The updates waiting, as wp_get_update_data counts them for the user
 * (probe admin-bar): plugins and themes from their update transients,
 * WordPress itself unless it is current, translations; each only for a
 * user who may update it. WordPress counts its own update only where
 * wp-admin's update functions are loaded; the engine always has them, so
 * it counts it everywhere.
 */
final class UpdateCounts
{
    /** The updates waiting, counted for what the user may update, with their title (wp_get_update_data), as wp_get_update_data leaves them. */
    public static function data(): mixed
    {
        [$data, $titles] = self::forCurrentUser();
        return Runtime::hooks()->filter('wp_get_update_data', [$data, $titles]);
    }

    /**
     * The translation updates waiting (wp_get_translation_updates): none, as
     * the engine fetches no language packs on its own.
     *
     * @return list<object>
     */
    public static function translations(): array
    {
        return [];
    }

    /** The core releases offered (get_core_updates), each marked not dismissed; false when the offer holds none. */
    public static function core(mixed $options = []): mixed
    {
        $options = array_merge(['available' => true, 'dismissed' => false], $options);
        $fromApi = Transients::getSite('update_core');
        if (!isset($fromApi->updates) || !is_array($fromApi->updates)) {
            return false;
        }
        $result = [];
        foreach ($fromApi->updates as $update) {
            if ($options['available']) {
                $update->dismissed = false;
                $result[] = $update;
            }
        }
        return $result;
    }

    /**
     * The counts and their title (before the wp_get_update_data filter),
     * and the titles one by one.
     *
     * @return array{0: array{counts: array<string, int>, title: string}, 1: array<string, string>}
     */
    public static function forCurrentUser(): array
    {
        $counts = ['plugins' => 0, 'themes' => 0, 'wordpress' => 0, 'translations' => 0];
        $plugins = Can::current('update_plugins');
        $themes = Can::current('update_themes');
        $core = Can::current('update_core');
        foreach (['plugins' => $plugins, 'themes' => $themes] as $kind => $allowed) {
            $waiting = $allowed ? Transients::getSite("update_{$kind}") : null;
            $counts[$kind] = is_object($waiting) && !empty($waiting->response) ? count((array) $waiting->response) : 0;
        }
        $release = $core ? self::core(['dismissed' => false]) : [];
        $counts['wordpress'] = !empty($release) && !in_array($release[0]->response ?? '', ['development', 'latest'], true) ? 1 : 0;
        $counts['translations'] = ($core || $plugins || $themes) && self::translations() ? 1 : 0;
        $counts['total'] = $counts['plugins'] + $counts['themes'] + $counts['wordpress'] + $counts['translations'];
        $titles = array_filter([
            'wordpress' => $counts['wordpress'] ? sprintf(Gettext::text('%d WordPress Update'), $counts['wordpress']) : '',
            'plugins' => $counts['plugins'] ? sprintf(Gettext::plural('%d Plugin Update', '%d Plugin Updates', $counts['plugins']), $counts['plugins']) : '',
            'themes' => $counts['themes'] ? sprintf(Gettext::plural('%d Theme Update', '%d Theme Updates', $counts['themes']), $counts['themes']) : '',
            'translations' => $counts['translations'] ? Gettext::text('Translation Updates') : '',
        ]);
        return [['counts' => $counts, 'title' => $titles ? Escape::attr(implode(', ', $titles)) : ''], $titles];
    }
}
