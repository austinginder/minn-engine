<?php

use Minn\Theme\FontFaces;

/** The fonts the theme's merged theme.json and its style variations declare, as WP_Font_Face prints them (probe fonts). */
class WP_Font_Face_Resolver
{
    public static function get_fonts_from_theme_json()
    {
        return FontFaces::fromSettings((array) WP_Theme_JSON_Resolver::get_merged_data()->get_settings(), static fn (string $path): string => get_theme_file_uri($path));
    }

    public static function get_fonts_from_style_variations()
    {
        $fonts = [];
        foreach (WP_Theme_JSON_Resolver::get_style_variations() as $variation) {
            $fonts = [...$fonts, ...FontFaces::fromSettings((array) ($variation['settings'] ?? []), static fn (string $path): string => get_theme_file_uri($path))];
        }
        return $fonts;
    }
}
