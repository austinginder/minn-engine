<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\Packages;
use Minn\RestError;
use WP_CLI;
use WP_CLI\Formatter;

/**
 * Shared wording for `wp theme search` and `wp plugin search`. The
 * default table is name/slug/rating; Success prints before the rows and
 * only for table/yaml. json/csv/count print the Formatter output alone.
 */
final class DirectorySearch
{
    private const FIELDS = ['name', 'slug', 'rating'];

    /**
     * Searches wordpress.org for one kind of asset and prints the page.
     *
     * @param array<string, mixed> $assocArgs
     */
    public static function run(string $kind, array $args, array $assocArgs): void
    {
        $page = max(1, (int) ($assocArgs['page'] ?? 1));
        $perPage = (int) ($assocArgs['per-page'] ?? 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        $runtime = Runtime::boot();
        $packages = new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content');
        try {
            $result = $kind === 'theme'
                ? $packages->queryThemes((string) ($args[0] ?? ''), $page, $perPage)
                : $packages->queryPlugins((string) ($args[0] ?? ''), $page, $perPage);
        } catch (RestError $error) {
            WP_CLI::error($error->getMessage());
        }
        $format = (string) ($assocArgs['format'] ?? 'table');
        $noun = $kind === 'theme' ? 'themes' : 'plugins';
        if ($format === 'table' || $format === 'yaml') {
            WP_CLI::success('Showing ' . count($result['items']) . ' of ' . $result['total'] . ' ' . $noun . '.');
        }
        $fields = self::FIELDS;
        if (isset($assocArgs['fields'])) {
            $fields = array_map(trim(...), explode(',', (string) $assocArgs['fields']));
        }
        (new Formatter($assocArgs, $fields))->display_items($result['items']);
    }
}
