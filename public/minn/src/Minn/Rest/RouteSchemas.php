<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\PluginCall;

/**
 * The item schema an OPTIONS answer describes a route by: the one its core
 * controller registers on the runtime's server, or, where the engine keeps
 * that controller as a dead end or answers the route itself, the schema the
 * reference describes it by (data/rest-schemas.json), with the fields
 * plugin code registers for the type after its own properties.
 */
final class RouteSchemas
{
    /** Each route, as the index lists it: the captured schema it is described by, and the type whose registered fields join it. */
    private const OWN = [
        '/wp/v2/font-families' => ['wp_font_family', 'wp_font_family'],
        '/wp/v2/font-families/(?P<id>[\d]+)' => ['wp_font_family', 'wp_font_family'],
        '/wp/v2/font-families/(?P<font_family_id>[\d]+)/font-faces' => ['wp_font_face', 'wp_font_face'],
        '/wp/v2/font-families/(?P<font_family_id>[\d]+)/font-faces/(?P<id>[\d]+)' => ['wp_font_face', 'wp_font_face'],
        '/wp/v2/global-styles/(?P<id>[\/\d+]+)' => ['wp_global_styles', 'wp_global_styles'],
        '/wp/v2/global-styles/(?P<parent>[\d]+)/revisions' => ['wp_global_styles-revision', 'wp_global_styles'],
        '/wp/v2/global-styles/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)' => ['wp_global_styles-revision', 'wp_global_styles'],
        '/wp/v2/statuses' => ['status', null],
        '/wp/v2/statuses/(?P<status>[\w-]+)' => ['status', null],
        '/wp/v2/font-collections' => ['font-collection', null],
        '/wp/v2/font-collections/(?P<slug>[\/\w-]+)' => ['font-collection', null],
        '/wp/v2/block-directory/search' => ['block-directory-item', null],
        '/wp/v2/pattern-directory/patterns' => ['pattern-directory-item', null],
        '/wp-site-health/v1/tests/background-updates' => ['wp-site-health-test', null],
        '/wp-site-health/v1/tests/loopback-requests' => ['wp-site-health-test', null],
        '/wp-site-health/v1/tests/https-status' => ['wp-site-health-test', null],
        '/wp-site-health/v1/tests/dotorg-communication' => ['wp-site-health-test', null],
        '/wp-site-health/v1/tests/authorization-header' => ['wp-site-health-test', null],
        '/wp-block-editor/v1/navigation-fallback' => ['navigation-fallback', null],
    ];

    /** The schema the route is described by, or null for none. @return array<string, mixed>|null */
    public static function for(string $route): ?array
    {
        if (isset(self::OWN[$route])) {
            [$name, $type] = self::OWN[$route];
            $schema = SchemaData::of($name);
            return $type === null ? $schema : RegisteredFields::withSchemas($schema, $type);
        }
        // The server holds its routes' options once it has listed them.
        $server = RuntimeRoutes::server();
        $server->get_routes();
        $options = (array) $server->get_route_options($route);
        return isset($options['schema']) && is_callable($options['schema']) ? (array) PluginCall::run($options['schema']) : null;
    }
}
