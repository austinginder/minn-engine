<?php

declare(strict_types=1);

namespace Minn\Admin;

/**
 * The arguments Minn Admin's own routes register, as the reference lists
 * them with the plugin running there: declared, the router judges them
 * before the caller, as the reference does, so a bad value is a 400 even
 * for someone signed out. Each set names the handler that reads it.
 */
final class AdminArgs
{
    /** Read by OverviewController::overview(): the window the charts cover, in days. */
    public const OVERVIEW = [
        'days' => ['type' => 'integer', 'default' => 30, 'minimum' => 7, 'maximum' => 90, 'required' => false],
    ];

    /** Read by OverviewController::overviewActivity(): the bounds of one chart bar. */
    public const ACTIVITY = [
        'from' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$', 'required' => true],
        'to' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$', 'required' => true],
    ];

    /** Read by PackagesController::searchThemes(): the words searched for. */
    public const THEME_SEARCH = [
        'q' => ['type' => 'string', 'required' => true],
    ];

    /** Read by PackagesController::searchPlugins(): the words searched for and the page of results. */
    public const PLUGIN_SEARCH = [
        'q' => ['type' => 'string', 'required' => true],
        'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
    ];

    /** Read by PackagesController::pluginInfo(): the directory slug. */
    public const PLUGIN_INFO = [
        'slug' => ['type' => 'string', 'required' => true],
    ];
}
