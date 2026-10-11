<?php

/**
 * Block patterns as core and the theme register them (probe
 * block-patterns), both on init. Core's are data (data/core-block-patterns.json,
 * captured): its pattern categories always, its patterns while the theme
 * supports core-block-patterns (every theme does unless it turns them off).
 * The theme's come from its patterns folder (and its parent's), by their
 * headers (WP_Theme::get_block_patterns), with the file to render, the
 * title and description translated in the theme's domain; a slug already
 * registered is left alone.
 */

/** @internal core's pattern categories and patterns */
function _minn_core_block_patterns(): array
{
    static $data = null;
    return $data ??= (array) Minn\Json::file(MINN_ENGINE_DIR . '/data/core-block-patterns.json');
}

function _register_core_block_patterns_and_categories()
{
    $core = _minn_core_block_patterns();
    if (get_theme_support('core-block-patterns')) {
        foreach ((array) ($core['patterns'] ?? []) as $pattern) {
            register_block_pattern($pattern['name'], array_diff_key($pattern, ['name' => true]));
        }
    }
    foreach ((array) ($core['categories'] ?? []) as $category) {
        register_block_pattern_category($category['name'], array_diff_key($category, ['name' => true]));
    }
}

function _register_theme_block_patterns()
{
    $registry = WP_Block_Patterns_Registry::get_instance();
    $theme = wp_get_theme();
    foreach (array_filter([$theme, $theme->parent()]) as $source) {
        $folder = $source->get_stylesheet_directory() . '/patterns/';
        $domain = (string) $source->get('TextDomain');
        foreach ($source->get_block_patterns() as $file => $pattern) {
            if ($registry->is_registered($pattern['slug']) || !file_exists($folder . $file)) {
                continue;
            }
            $pattern['filePath'] = $folder . $file;
            $pattern['title'] = translate_with_gettext_context($pattern['title'], 'Pattern title', $domain);
            if (!empty($pattern['description'])) {
                $pattern['description'] = translate_with_gettext_context($pattern['description'], 'Pattern description', $domain);
            }
            register_block_pattern($pattern['slug'], $pattern);
        }
    }
}

/** A pattern from the pattern directory with the local names for its block types and viewport width. */
function wp_normalize_remote_block_pattern($pattern)
{
    foreach (['block_types' => 'blockTypes', 'viewport_width' => 'viewportWidth'] as $remote => $local) {
        if (isset($pattern[$remote])) {
            $pattern[$local] = $pattern[$remote];
            unset($pattern[$remote]);
        }
    }
    return (array) $pattern;
}

/** The pattern directory slugs the theme.json lists (its patterns). */
function wp_get_theme_directory_pattern_slugs()
{
    return WP_Theme_JSON_Resolver::get_theme_data()->get_patterns();
}
