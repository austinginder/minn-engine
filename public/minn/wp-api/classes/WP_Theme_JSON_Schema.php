<?php

/**
 * theme.json brought to the current schema, version 3 (probe
 * schema-and-menus). Data with no version becomes an empty version 3.
 * Version 1 has four settings renamed (in settings and in each block's
 * settings), then goes through version 2's step, which marks the theme's
 * font and spacing sizes as replacing the defaults (for every origin but
 * custom). Any other version passes as given.
 */
#[AllowDynamicProperties]
class WP_Theme_JSON_Schema
{
    /** The settings version 1 called otherwise, renamed in this order. */
    const V1_TO_V2_RENAMED_PATHS = ['border.customRadius' => 'border.radius', 'spacing.customMargin' => 'spacing.margin', 'spacing.customPadding' => 'spacing.padding', 'typography.customLineHeight' => 'typography.lineHeight'];

    public static function migrate($theme_json, $origin = 'theme')
    {
        if (!isset($theme_json['version'])) {
            return ['version' => 3];
        }
        switch ($theme_json['version']) {
            case 1:
                $theme_json = self::migrate_v1_to_v2($theme_json);
                // Version 2 goes on to 3.
            case 2:
                $theme_json = self::migrate_v2_to_v3($theme_json, $origin);
        }
        return $theme_json;
    }

    private static function migrate_v1_to_v2($old)
    {
        if (isset($old['settings'])) {
            $old['settings'] = self::rename_paths($old['settings'], self::V1_TO_V2_RENAMED_PATHS);
        }
        $old['version'] = 2;
        return $old;
    }

    private static function migrate_v2_to_v3($old, $origin)
    {
        $old['version'] = 3;
        if ($origin === 'custom') {
            return $old;
        }
        if (isset($old['settings']['typography']['fontSizes'])) {
            $old['settings']['typography']['defaultFontSizes'] = false;
        }
        if (isset($old['settings']['spacing']['spacingSizes']) || isset($old['settings']['spacing']['spacingScale'])) {
            $old['settings']['spacing']['defaultSpacingSizes'] = false;
        }
        return $old;
    }

    private static function rename_paths($settings, $paths_to_rename)
    {
        $settings = self::rename_settings($settings, $paths_to_rename);
        foreach (array_keys((array) ($settings['blocks'] ?? [])) as $block) {
            $settings['blocks'][$block] = self::rename_settings($settings['blocks'][$block], $paths_to_rename);
        }
        return $settings;
    }

    /** Each old path that holds a value gets it under the new path (added last, or replacing one already there), and loses it. */
    private static function rename_settings($settings, $paths_to_rename)
    {
        foreach ($paths_to_rename as $original => $renamed) {
            $value = _wp_array_get($settings, explode('.', $original), null);
            if ($value !== null) {
                _wp_array_set($settings, explode('.', $renamed), $value);
                $settings = self::unset_setting_by_path($settings, explode('.', $original));
            }
        }
        return $settings;
    }

    private static function unset_setting_by_path($settings, $path)
    {
        $leaf = array_pop($path);
        $node = &$settings;
        foreach ($path as $key) {
            if (!isset($node[$key]) || !is_array($node[$key])) {
                return $settings;
            }
            $node = &$node[$key];
        }
        unset($node[$leaf]);
        return $settings;
    }
}
