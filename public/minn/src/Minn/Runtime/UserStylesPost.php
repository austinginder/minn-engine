<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Theme;

/**
 * A theme's user styles post as WordPress's theme.json resolver finds and
 * makes it (probe global-styles-lookup): the newest wp_global_styles post
 * tied to the theme by its wp_theme term, through WP_Query (so plugins hear
 * the query and may steer it), made when asked and missing.
 */
final class UserStylesPost
{
    /**
     * A theme's user styles post as an array
     * (WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles): the
     * newest of the statuses given, by the theme's name. When asked to make
     * one and there is none, the post is made for that theme, any theme,
     * its term given as tax_input (so set only for a user who may assign
     * it). [] when there is none.
     *
     * @return array<string, mixed>
     */
    public static function data(mixed $theme, mixed $createPost = false, mixed $statuses = ['publish']): array
    {
        $stylesheet = $theme instanceof Theme ? $theme->get_stylesheet() : (string) $theme;
        $found = Handoff::newQuery()->query([
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'desc',
            'post_type' => 'wp_global_styles',
            'post_status' => $statuses,
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $stylesheet]],
        ]);
        if (is_array($found) && $found !== []) {
            return get_object_vars($found[0]);
        }
        if (!$createPost) {
            return [];
        }
        $id = PostWrites::insert([
            'post_content' => '{"version": 3, "isGlobalStylesUserThemeJSON": true }',
            'post_status' => 'publish',
            'post_title' => 'Custom Styles',
            'post_type' => 'wp_global_styles',
            'post_name' => 'wp-global-styles-' . urlencode($stylesheet),
            'tax_input' => ['wp_theme' => [$stylesheet]],
        ], true);
        $post = is_int($id) && $id > 0 ? Records::post($id) : null;
        return $post === null ? [] : get_object_vars($post);
    }

    /** The active theme's user styles post id (WP_Theme_JSON_Resolver::get_user_global_styles_post_id), the post made when it is missing. */
    public static function id(): int
    {
        return (int) (self::data(Themes::stylesheet(), true)['ID'] ?? 0);
    }
}
