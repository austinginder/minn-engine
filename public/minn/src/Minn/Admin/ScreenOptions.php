<?php

declare(strict_types=1);

namespace Minn\Admin;

use Closure;

/**
 * Which screen option a save keeps for itself, as set_screen_options tells
 * them apart on the reference (probe admin-doubts): a core list's page size
 * is saved as a number from 1 to 999 without asking any filter; any other
 * option is the plugin's to accept through its filters.
 */
final class ScreenOptions
{
    /** The core lists' page sizes that are not named after a post type or taxonomy. */
    private const CORE = [
        'edit_per_page', 'users_per_page', 'edit_comments_per_page', 'upload_per_page', 'edit_tags_per_page', 'plugins_per_page',
        'export_personal_data_requests_per_page', 'remove_personal_data_requests_per_page', 'sites_network_per_page',
        'users_network_per_page', 'site_users_network_per_page', 'plugins_network_per_page', 'themes_network_per_page', 'site_themes_network_per_page',
    ];

    /**
     * Whether an option is a core list's page size: one of those above, or
     * a name that is a post type or taxonomy once "edit_" and "_per_page"
     * are taken out of it.
     *
     * @param Closure(string): bool $registered whether a post type or taxonomy has that name
     */
    public static function isCore(string $option, Closure $registered): bool
    {
        return in_array($option, self::CORE, true) || $registered(str_replace(['edit_', '_per_page'], '', $option));
    }

    /** Whether a plugin's option is a page size or the column count, which the general set-screen-option filter is asked about first. */
    public static function pageSized(string $option): bool
    {
        return str_ends_with($option, '_page') || $option === 'layout_columns';
    }
}
