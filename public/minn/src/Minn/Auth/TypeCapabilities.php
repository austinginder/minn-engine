<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * The capability names a post type's permissions are built from. Posts and
 * pages own their families (edit_posts, edit_others_pages, ...). Navigation
 * menus fold every one of them onto edit_theme_options, which is why an
 * editor may read a menu and only an administrator may change one; the
 * reference's own registration says the same thing. A plugin's type
 * answers with the names it registered.
 */
final readonly class TypeCapabilities
{
    /** Types whose whole cap family collapses to a single name. */
    private const FOLDED = ['wp_navigation' => 'edit_theme_options'];

    /** A cap name built from the post/page family, folded when the type folds, or the plugin type's own name for it (probe rest-plugin-caps). */
    public static function of(string $type, string $capability): string
    {
        if (isset(self::FOLDED[$type])) {
            return self::FOLDED[$type];
        }
        $registered = RegisteredCaps::ofType($type);
        return $registered === null ? $capability : (string) ($registered->cap->{$capability} ?? $capability);
    }

    /** The capability creating a post of the type needs: the edit capability, or a plugin type's create_posts. */
    public static function create(string $type): string
    {
        return RegisteredCaps::ofType($type) === null ? self::edit($type) : self::of($type, 'create_posts');
    }

    /** The capability stem of a type: pages for page, posts for everything else. */
    public static function plural(string $type): string
    {
        return ($type === 'page' ? 'page' : 'post') . 's';
    }

    /** The edit capability of a type. */
    public static function edit(string $type): string
    {
        return self::of($type, 'edit_' . self::plural($type));
    }

    /** The edit-others capability of a type. */
    public static function editOthers(string $type): string
    {
        return self::of($type, 'edit_others_' . self::plural($type));
    }

    /** The publish capability of a type. */
    public static function publish(string $type): string
    {
        return self::of($type, 'publish_' . self::plural($type));
    }

    /** The read-private capability of a type. */
    public static function readPrivate(string $type): string
    {
        return self::of($type, 'read_private_' . self::plural($type));
    }
}
