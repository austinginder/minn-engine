<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;

/**
 * The admin addresses WordPress's link functions give for editing: a
 * post, a term, a user and one's own profile (null or empty for someone
 * who may not), a user's dashboard, and a post's short link; each as its
 * filter leaves it.
 */
final class EditLinks
{
    /** The address a post is edited at (get_edit_post_link), null for a post the user may not edit or of no known type. */
    public static function post(mixed $post = 0, mixed $context = 'display'): mixed
    {
        $post = Records::post($post);
        if ($post === null || !Can::current('edit_post', $post->ID)) {
            return null;
        }
        $action = $post->post_type === 'revision' ? '' : ($context === 'display' ? '&amp;' : '&') . 'action=edit';
        $type = Registered::postType($post->post_type);
        if ($type === null) {
            return null;
        }
        $link = $type->_edit_link ? SiteUrls::admin(sprintf($type->_edit_link . $action, $post->ID)) : '';
        return Runtime::hooks()->filter('get_edit_post_link', [$link, $post->ID, $context]);
    }

    /** The address a term is edited at (get_edit_term_link), null for one the user may not edit. */
    public static function term(mixed $term, mixed $taxonomy = '', mixed $objectType = ''): mixed
    {
        $term = Records::term($term, $taxonomy);
        if (!$term || Records::isError($term)) {
            return null;
        }
        $tax = Registered::taxonomy($term->taxonomy);
        if (!$tax || !Can::current('edit_term', $term->term_id)) {
            return null;
        }
        $args = ['taxonomy' => $tax->name, 'tag_ID' => $term->term_id];
        if ($objectType) {
            $args += ['post_type' => $objectType];
        } elseif (!empty($tax->object_type)) {
            $args += ['post_type' => reset($tax->object_type)];
        }
        $location = QueryArgs::add($args, SiteUrls::admin('term.php'));
        return Runtime::hooks()->filter('get_edit_term_link', [$location, $term->term_id, $tax->name, $objectType]);
    }

    /** The address a user is edited at (get_edit_user_link): one's own profile for oneself; empty for one the user may not edit. */
    public static function user(mixed $userId = null): mixed
    {
        if (!$userId) {
            $userId = self::me();
        }
        if (empty($userId) || !Can::current('edit_user', $userId)) {
            return '';
        }
        $user = Handoff::user($userId);
        if (!$user) {
            return '';
        }
        $link = self::me() === $user->ID ? self::profile($user->ID) : QueryArgs::add('user_id', $user->ID, SiteUrls::selfAdmin('user-edit.php'));
        return Runtime::hooks()->filter('get_edit_user_link', [$link, $user->ID]);
    }

    /** The address a user's profile is edited at (get_edit_profile_url), as edit_profile_url leaves it. */
    public static function profile(mixed $userId = 0, mixed $scheme = 'admin'): mixed
    {
        $userId = $userId ? (int) $userId : self::me();
        $url = self::me() === $userId ? self::dashboard($userId, 'profile.php', $scheme) : QueryArgs::add('user_id', $userId, self::dashboard($userId, 'user-edit.php', $scheme));
        return Runtime::hooks()->filter('edit_profile_url', [$url, $userId, $scheme]);
    }

    /** A user's dashboard address, at a path in it (get_dashboard_url), as user_dashboard_url leaves it. */
    public static function dashboard(mixed $userId = 0, mixed $path = '', mixed $scheme = 'admin'): mixed
    {
        $userId = $userId ? (int) $userId : self::me();
        $url = SiteUrls::admin($path ? $path : '', $scheme);
        return Runtime::hooks()->filter('user_dashboard_url', [$url, $userId, $path, $scheme]);
    }

    /**
     * A post's short address (wp_get_shortlink), unless pre_get_shortlink
     * answers first: the front page's home address, a public post's ?p=,
     * else empty; the query's post when asked for the query's.
     */
    public static function shortlink(mixed $id = 0, mixed $context = 'post', mixed $allowSlugs = true): mixed
    {
        $hooks = Runtime::hooks();
        $shortlink = $hooks->filter('pre_get_shortlink', [false, $id, $context, $allowSlugs]);
        if ($shortlink !== false) {
            return $shortlink;
        }
        $query = MainQuery::current();
        $post = $context === 'query' ? ($query->is_singular() ? Records::post($query->get_queried_object_id()) : null) : ($context === 'post' ? Records::post($id) : null);
        $type = $post === null ? null : Registered::postType($post->post_type);
        $options = Runtime::options();
        $front = $post !== null && $post->post_type === 'page' && $options->filtered('show_on_front') === 'page' && (int) $options->filtered('page_on_front') === (int) $post->ID;
        $shortlink = $front ? SiteUrls::home('/') : ($type !== null && $type->public ? SiteUrls::home('?p=' . $post->ID) : '');
        return $hooks->filter('get_shortlink', [$shortlink, $id, $context, $allowSlugs]);
    }

    /** The signed-in user's id. */
    private static function me(): int
    {
        return (int) CurrentUser::id();
    }
}
