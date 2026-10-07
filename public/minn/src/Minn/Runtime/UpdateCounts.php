<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
    /**
     * The counts and their title (before the wp_get_update_data filter),
     * and the titles one by one.
     *
     * @return array{0: array{counts: array<string, int>, title: string}, 1: array<string, string>}
     */
    public static function forCurrentUser(): array
    {
        $counts = ['plugins' => 0, 'themes' => 0, 'wordpress' => 0, 'translations' => 0];
        $plugins = \current_user_can('update_plugins');
        $themes = \current_user_can('update_themes');
        $core = \current_user_can('update_core');
        foreach (['plugins' => $plugins, 'themes' => $themes] as $kind => $allowed) {
            $waiting = $allowed ? \get_site_transient("update_{$kind}") : null;
            $counts[$kind] = is_object($waiting) && !empty($waiting->response) ? count((array) $waiting->response) : 0;
        }
        $release = $core ? \get_core_updates(['dismissed' => false]) : [];
        $counts['wordpress'] = !empty($release) && !in_array($release[0]->response ?? '', ['development', 'latest'], true) ? 1 : 0;
        $counts['translations'] = ($core || $plugins || $themes) && \wp_get_translation_updates() ? 1 : 0;
        $counts['total'] = $counts['plugins'] + $counts['themes'] + $counts['wordpress'] + $counts['translations'];
        $titles = array_filter([
            'wordpress' => $counts['wordpress'] ? sprintf(\__('%d WordPress Update'), $counts['wordpress']) : '',
            'plugins' => $counts['plugins'] ? sprintf(\_n('%d Plugin Update', '%d Plugin Updates', $counts['plugins']), $counts['plugins']) : '',
            'themes' => $counts['themes'] ? sprintf(\_n('%d Theme Update', '%d Theme Updates', $counts['themes']), $counts['themes']) : '',
            'translations' => $counts['translations'] ? \__('Translation Updates') : '',
        ]);
        return [['counts' => $counts, 'title' => $titles ? \esc_attr(implode(', ', $titles)) : ''], $titles];
    }
}
