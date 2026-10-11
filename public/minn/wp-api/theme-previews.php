<?php

/**
 * Block theme previews (probe preview-output-helpers): a request that
 * names a theme in wp_theme_preview, from someone who may switch themes,
 * previews it: the stylesheet and template filters answer that theme (its
 * template through the template filter), the API fetch middleware is told
 * which theme it is, and the admin gets the nonce to activate it.
 * Initialized on plugins_loaded at 1.
 */

/** The theme a preview names (the active one when it names none), or the current one for anyone who may not switch themes or a theme that is not there. */
function wp_get_theme_preview_path($current_stylesheet = null)
{
    if (!current_user_can('switch_themes')) {
        return $current_stylesheet;
    }
    $named = !empty($_GET['wp_theme_preview']) ? sanitize_text_field(wp_unslash($_GET['wp_theme_preview'])) : null;
    $theme = wp_get_theme($named);
    if (is_wp_error($theme->errors())) {
        return $current_stylesheet;
    }
    return sanitize_text_field(current_filter() === 'template' ? $theme->get_template() : $theme->get_stylesheet());
}

function wp_attach_theme_preview_middleware()
{
    wp_add_inline_script('wp-api-fetch', sprintf('wp.apiFetch.use( wp.apiFetch.createThemePreviewMiddleware( %s ) );', wp_json_encode(sanitize_text_field(wp_unslash($_GET['wp_theme_preview'] ?? '')))), 'after');
}

function wp_block_theme_activate_nonce()
{
    $nonce = wp_create_nonce('switch-theme_' . wp_get_theme_preview_path());
    echo "\t<script>\n\t\twindow.WP_BLOCK_THEME_ACTIVATE_NONCE = " . wp_json_encode($nonce) . ";\n\t</script>\n\t";
}

function wp_initialize_theme_preview_hooks()
{
    if (!empty($_GET['wp_theme_preview'])) {
        add_filter('stylesheet', 'wp_get_theme_preview_path');
        add_filter('template', 'wp_get_theme_preview_path');
        add_action('init', 'wp_attach_theme_preview_middleware');
        add_action('admin_head', 'wp_block_theme_activate_nonce');
    }
}

/**
 * The skip link's style, enqueued while a block template renders. Only the
 * first call in a request decides; later ones do nothing. Not hooked on
 * the engine's front, which writes the skip link itself and has no
 * WordPress stylesheet to point at.
 */
function wp_enqueue_block_template_skip_link()
{
    static $asked = false;
    global $_wp_current_template_content;
    if ($asked) {
        return;
    }
    $asked = true;
    if (empty($_wp_current_template_content)) {
        return;
    }
    wp_enqueue_style('wp-block-template-skip-link');
}
