<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Post addresses as the reference's link functions build them (probe
 * permalinks): a post through pre_post_link (the structure) and post_link;
 * a page from get_page_uri through _get_page_link and page_link; an
 * attachment under its parent's address, or its own; a plugin's type by
 * its address pattern (the permastruct its rewrite registered) through
 * post_type_link, by its query var without one. Plain (?p=, ?page_id=,
 * ?post_type=) unless its status is public, or private and readable by
 * whoever asks, or it is a sample: a draft, a scheduled or trashed post, a
 * revision, a private one a visitor asks for. "Leaving the name" keeps the slug as its placeholder
 * (%postname%, %pagename%, %{type}%) for an editor to fill in, and
 * get_sample_permalink shows a draft as it would be published.
 */
final class PostLinks
{
    /** The slug kept as its placeholder. */
    public const LEAVE_NAME = 1;

    /** The address an editor previews: a draft as if published. */
    public const SAMPLE = 2;

    private const UNPUBLISHED = ['draft', 'pending', 'auto-draft', 'future'];

    /** A post's address (not a page, attachment or plugin type). */
    public static function post(\WP_Post $post, int $flags): string
    {
        $leavename = (bool) ($flags & self::LEAVE_NAME);
        $structure = (string) \apply_filters('pre_post_link', (string) Runtime::options()->filtered('permalink_structure'), $post, $leavename);
        if ($structure !== '' && !self::plain($post, $flags)) {
            $link = \user_trailingslashit(\home_url(strtr($structure, self::tokens($post, $flags))), 'single');
        } else {
            $link = \home_url('?p=' . $post->ID);
        }
        return (string) \apply_filters('post_link', $link, $post, $leavename);
    }

    /** What each structure tag of a post stands for; the name stays a tag when it is left. */
    private static function tokens(\WP_Post $post, int $flags): array
    {
        $time = strtotime((string) $post->post_date) ?: time();
        $tokens = [
            '%year%' => date('Y', $time), '%monthnum%' => date('m', $time), '%day%' => date('d', $time),
            '%hour%' => date('H', $time), '%minute%' => date('i', $time), '%second%' => date('s', $time),
            '%post_id%' => (string) $post->ID,
        ];
        if (!($flags & self::LEAVE_NAME)) {
            $tokens += ['%postname%' => (string) $post->post_name, '%pagename%' => (string) $post->post_name];
        }
        $author = \get_userdata((int) $post->post_author);
        $tokens['%author%'] = $author ? (string) $author->user_nicename : '';
        $tokens['%category%'] = str_contains((string) Runtime::options()->filtered('permalink_structure'), '%category%') ? self::category($post) : '';
        return $tokens;
    }

    /** The %category% part: the post's first category by id (post_link_category may choose another), under its parents. */
    private static function category(\WP_Post $post): string
    {
        $categories = \get_the_category($post->ID);
        usort($categories, static fn ($a, $b) => (int) $a->term_id <=> (int) $b->term_id);
        $chosen = $categories === [] ? null : \apply_filters('post_link_category', $categories[0], $categories, $post);
        $chosen = $chosen instanceof \WP_Term ? $chosen : \get_term((int) Runtime::options()->filtered('default_category'), 'category');
        if (!$chosen instanceof \WP_Term) {
            return '';
        }
        $parents = $chosen->parent ? (string) \get_category_parents((int) $chosen->parent, false, '/', true) : '';
        return $parents . $chosen->slug;
    }

    /** A page's own address, before page_link: ?page_id= while unpublished, its tag when the name is left, else its path. */
    public static function page(\WP_Post $post, int $flags): string
    {
        $leavename = (bool) ($flags & self::LEAVE_NAME);
        $pretty = (string) Runtime::options()->filtered('permalink_structure') !== '';
        if (!$pretty || self::plain($post, $flags)) {
            $link = \home_url('?page_id=' . $post->ID);
        } else {
            $link = \home_url(\user_trailingslashit($leavename ? '%pagename%' : (string) \get_page_uri($post), 'page'));
        }
        return (string) \apply_filters('_get_page_link', $link, $post->ID);
    }

    /** An attachment's address: under a parent it belongs to, else its own slug, else ?attachment_id=. */
    public static function attachment(\WP_Post $post, int $flags): string
    {
        $leavename = (bool) ($flags & self::LEAVE_NAME);
        $parent = (int) $post->post_parent > 0 && (int) $post->post_parent !== (int) $post->ID ? \get_post((int) $post->post_parent) : null;
        $pretty = (string) Runtime::options()->filtered('permalink_structure') !== '';
        $link = '';
        if ($pretty && $parent instanceof \WP_Post && in_array($parent->post_type, \get_post_types(), true)) {
            $parentLink = $parent->post_type === 'page' ? self::page($parent, 0) : (string) \get_permalink($parent);
            $name = is_numeric($post->post_name) || str_contains((string) Runtime::options()->filtered('permalink_structure'), '%category%') ? 'attachment/' . $post->post_name : (string) $post->post_name;
            if (!str_contains($parentLink, '?')) {
                $link = \user_trailingslashit(\trailingslashit($parentLink) . '%postname%');
                $link = $leavename ? $link : str_replace('%postname%', $name, $link);
            }
        } elseif ($pretty && !$leavename) {
            $link = \home_url(\user_trailingslashit((string) $post->post_name));
        }
        return (string) \apply_filters('attachment_link', $link !== '' ? $link : \home_url('/?attachment_id=' . $post->ID), $post->ID);
    }

    /** A plugin type's address by its pattern, by its query var, or by the type and id plainly. */
    public static function custom(\WP_Post $post, int $flags): string
    {
        $leavename = (bool) ($flags & self::LEAVE_NAME);
        $sample = (bool) ($flags & self::SAMPLE);
        $type = \get_post_type_object($post->post_type);
        $struct = (string) \_minn_rewrite()->get_extra_permastruct($post->post_type);
        // A plugin type's sample is pretty whatever its status, a trashed post's too (probe rest-plugin-types).
        $plain = !$sample && self::plain($post, $flags);
        $slug = $type?->hierarchical ? (string) \get_page_uri($post) : (string) $post->post_name;
        if ($struct !== '' && !$plain) {
            $link = \home_url(\user_trailingslashit($leavename ? $struct : str_replace("%{$post->post_type}%", $slug, $struct)));
        } elseif (!empty($type?->query_var) && !$plain) {
            $link = \home_url(\add_query_arg((string) $type->query_var, $slug, ''));
        } else {
            $link = \home_url(\add_query_arg(['post_type' => $post->post_type, 'p' => $post->ID], ''));
        }
        return (string) \apply_filters('post_type_link', $link, $post, $leavename, $sample);
    }

    /**
     * Whether a post's address is the plain one: its status neither public
     * nor private and readable, unless it is a sample of a status that is
     * not internal (a trashed post and a revision stay plain as samples).
     */
    private static function plain(\WP_Post $post, int $flags): bool
    {
        $status = \get_post_status_object((string) $post->post_status);
        if ((($flags & self::SAMPLE) || ($post->filter ?? '') === 'sample') && !($status->internal ?? false)) {
            return false;
        }
        return !($status->public ?? false) && !(($status->private ?? false) && \current_user_can('read_post', $post->ID));
    }

    /**
     * The address an editor shows with the slug to edit: the post as if
     * published, its name made unique, the address with the name left,
     * a page's parents written in.
     *
     * @return array{0: string, 1: string}
     */
    public static function sample(\WP_Post $post, ?string $title, ?string $name): array
    {
        $sample = clone $post;
        if (in_array($sample->post_status, self::UNPUBLISHED, true)) {
            $sample->post_status = 'publish';
            $sample->post_name = (string) \sanitize_title($sample->post_name !== '' ? $sample->post_name : $sample->post_title, (string) $sample->ID);
        }
        if ($name !== null) {
            $sample->post_name = (string) \sanitize_title($name !== '' ? $name : (string) $title, (string) $sample->ID);
        }
        $sample->post_name = (string) \wp_unique_post_slug($sample->post_name, $sample->ID, $sample->post_status, $sample->post_type, $sample->post_parent);
        $sample->filter = 'sample';
        $link = str_replace("%{$sample->post_type}%", '%pagename%', (string) \get_permalink($sample, true));
        if (\is_post_type_hierarchical($sample->post_type)) {
            $uri = (string) \get_page_uri($sample);
            $uri = str_contains($uri, '/') ? substr($uri, 0, (int) strrpos($uri, '/')) : '';
            $uri = (string) \apply_filters('editable_slug', $uri, $sample);
            $link = str_replace('%pagename%', ($uri !== '' ? $uri . '/' : '') . '%pagename%', $link);
        }
        return (array) \apply_filters('get_sample_permalink', [$link, \apply_filters('editable_slug', $sample->post_name, $sample)], $post->ID, $title, $name, $sample);
    }
}
