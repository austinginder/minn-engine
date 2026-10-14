<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Theme;

/**
 * A theme's user styles post as WordPress's theme.json resolver finds and
 * makes it: the newest wp_global_styles post tied to the theme by its
 * wp_theme term, made for the active theme when it is missing.
 */
final class UserStylesPost
{
    /**
     * A theme's user styles post as an array
     * (WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles), made
     * when asked and missing for the active theme; [] when there is none.
     *
     * @return array<string, mixed>
     */
    public static function data(mixed $theme, mixed $createPost = false, mixed $statuses = ['publish']): array
    {
        $stylesheet = $theme instanceof Theme ? $theme->get_stylesheet() : (string) $theme;
        $found = PostReads::query([
            'post_type' => 'wp_global_styles',
            'post_status' => $statuses,
            'numberposts' => 1,
            'orderby' => 'ID',
            'order' => 'DESC',
            'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => $stylesheet]],
        ]);
        if ($found !== []) {
            return get_object_vars($found[0]);
        }
        if (!$createPost || $stylesheet !== Themes::stylesheet()) {
            return [];
        }
        $id = self::id();
        return $id > 0 ? get_object_vars(Records::post($id)) : [];
    }

    /** The active theme's user styles post id (WP_Theme_JSON_Resolver::get_user_global_styles_post_id), the post made when it is missing. */
    public static function id(): int
    {
        $stylesheet = Themes::stylesheet();
        $found = self::data($stylesheet);
        if ($found !== []) {
            return (int) $found['ID'];
        }
        $id = (int) PostWrites::insert([
            'post_type' => 'wp_global_styles',
            'post_status' => 'publish',
            'post_title' => 'Custom Styles',
            'post_name' => 'wp-global-styles-' . $stylesheet,
            'post_content' => '{"version": 3, "isGlobalStylesUserThemeJSON": true }',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);
        if ($id > 0) {
            PostWrites::setTerms($id, [$stylesheet], 'wp_theme');
        }
        return $id;
    }
}
