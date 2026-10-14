<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Theme;

/**
 * The site's themes as WordPress names and finds them: the active
 * stylesheet and template (as their filters leave them), the themes root on
 * disk and its address, each active theme's folder and its address, a theme
 * by its folder, every installed theme, and the extra roots plugins
 * register.
 */
final class Themes
{
    /** The active theme's folder (get_stylesheet). */
    public static function stylesheet(): mixed
    {
        return Runtime::hooks()->filter('stylesheet', [(string) Runtime::options()->filtered('stylesheet')]);
    }

    /** The active parent theme's folder (get_template). */
    public static function template(): mixed
    {
        return Runtime::hooks()->filter('template', [(string) Runtime::options()->filtered('template')]);
    }

    /** Where themes live on disk (get_theme_root). */
    public static function root(mixed $stylesheetOrTemplate = ''): mixed
    {
        return Runtime::hooks()->filter('theme_root', [WP_CONTENT_DIR . '/themes']);
    }

    /** The themes root's address (get_theme_root_uri). */
    public static function rootUri(mixed $stylesheetOrTemplate = '', mixed $themeRoot = ''): mixed
    {
        return Runtime::hooks()->filter('theme_root_uri', [SiteUrls::content('themes'), self::root(), $stylesheetOrTemplate]);
    }

    /** The active theme's folder on disk (get_stylesheet_directory). */
    public static function stylesheetDirectory(): mixed
    {
        $stylesheet = self::stylesheet();
        return Runtime::hooks()->filter('stylesheet_directory', [self::root($stylesheet) . '/' . $stylesheet, $stylesheet, self::root($stylesheet)]);
    }

    /** The active parent theme's folder on disk (get_template_directory). */
    public static function templateDirectory(): mixed
    {
        $template = self::template();
        return Runtime::hooks()->filter('template_directory', [self::root($template) . '/' . $template, $template, self::root($template)]);
    }

    /** The active theme's folder address (get_stylesheet_directory_uri). */
    public static function stylesheetDirectoryUri(): mixed
    {
        $stylesheet = str_replace('%2F', '/', rawurlencode((string) self::stylesheet()));
        return Runtime::hooks()->filter('stylesheet_directory_uri', [self::rootUri($stylesheet) . '/' . $stylesheet, $stylesheet, self::rootUri($stylesheet)]);
    }

    /** Whether the current theme is a block theme (wp_is_block_theme): the booted one's answer, or, for a stylesheet a filter swapped in, that theme's own. */
    public static function isBlockTheme(): bool
    {
        $runtime = Runtime::current();
        $theme = $runtime->get('theme');
        $stylesheet = self::stylesheet();
        if ($runtime->get('block_theme') === null || $theme instanceof \Minn\Theme\Theme && $stylesheet === $theme->slug) {
            return true;
        }
        $known = (array) $runtime->get('block_theme_by_stylesheet', []);
        $known[$stylesheet] ??= self::theme($stylesheet)->is_block_theme();
        $runtime->set('block_theme_by_stylesheet', $known);
        return $known[$stylesheet];
    }

    /** The active theme's stylesheet address (get_stylesheet_uri), as stylesheet_uri leaves it. */
    public static function stylesheetUri(): mixed
    {
        $directory = self::stylesheetDirectoryUri();
        return Runtime::hooks()->filter('stylesheet_uri', [$directory . '/style.css', $directory]);
    }

    /** The active parent theme's folder address (get_template_directory_uri). */
    public static function templateDirectoryUri(): mixed
    {
        $template = str_replace('%2F', '/', rawurlencode((string) self::template()));
        return Runtime::hooks()->filter('template_directory_uri', [self::rootUri($template) . '/' . $template, $template, self::rootUri($template)]);
    }

    /** A theme by its folder, the active one when none is named (wp_get_theme). */
    public static function theme(mixed $stylesheet = '', mixed $themeRoot = ''): Theme
    {
        $stylesheet = (string) $stylesheet === '' ? self::stylesheet() : (string) $stylesheet;
        return Handoff::newTheme($stylesheet, $themeRoot !== '' ? (string) $themeRoot : self::root($stylesheet));
    }

    /**
     * Every installed theme by its folder (wp_get_themes).
     *
     * @return array<string, Theme>
     */
    public static function all(mixed $args = []): array
    {
        $out = [];
        foreach (glob(self::root() . '/*/style.css') ?: [] as $file) {
            $slug = basename(dirname($file));
            $out[$slug] = Handoff::newTheme($slug, self::root());
        }
        return $out;
    }

    /** A further themes root (register_theme_directory), under wp-content when not found as given; false when it is nowhere. */
    public static function registerDirectory(mixed $directory): bool
    {
        $directory = (string) $directory;
        if (!file_exists($directory)) {
            $directory = WP_CONTENT_DIR . '/' . $directory;
            if (!file_exists($directory)) {
                return false;
            }
        }
        $directory = rtrim($directory, '/\\');
        $GLOBALS['wp_theme_directories'] ??= [];
        if (!in_array($directory, $GLOBALS['wp_theme_directories'], true)) {
            $GLOBALS['wp_theme_directories'][] = $directory;
        }
        return true;
    }
}
