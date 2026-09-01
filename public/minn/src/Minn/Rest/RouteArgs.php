<?php

declare(strict_types=1);

namespace Minn\Rest;

/** The argument groups a route registers with, filled the way register_rest_route fills them. */
final class RouteArgs
{
    /**
     * Route arguments with the shared args folded into each endpoint.
     *
     * @param array<string, mixed> $args a single handler (with 'callback') or a list of handler groups, plus optional shared 'args'
     * @return array{0: array<int|string, mixed>, 1: bool} the groups, and whether any lacks a permission_callback
     */
    public static function normalise(array $args): array
    {
        $common = is_array($args['args'] ?? null) ? $args['args'] : [];
        unset($args['args']);
        if (isset($args['callback'])) {
            $args = [$args];
        }
        $missingPermission = false;
        foreach ($args as $key => $group) {
            if (!is_numeric($key) || !is_array($group)) {
                continue;
            }
            $group = array_merge(['methods' => 'GET', 'callback' => null, 'args' => []], $group);
            $group['args'] = array_merge($common, (array) $group['args']);
            if (!isset($group['permission_callback'])) {
                $missingPermission = true;
            }
            foreach ($group['args'] as $name => $options) {
                $options = is_array($options) ? $options : [];
                $options['validate_callback'] ??= 'rest_validate_request_arg';
                $options['sanitize_callback'] ??= 'rest_sanitize_request_arg';
                $group['args'][$name] = $options;
            }
            $args[$key] = $group;
        }
        return [$args, $missingPermission];
    }
}
