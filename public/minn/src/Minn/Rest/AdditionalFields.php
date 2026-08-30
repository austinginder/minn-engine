<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Registry;

/** Which object type a wp/v2 route serves, so fields registered for that type can ride on the engine's own responses. */
final class AdditionalFields
{
    /** @return array{0: string, 1: bool}|null the object type and whether the route is a single item */
    public static function typeForRoute(string $route, Registry $registry): ?array
    {
        if (!preg_match('#^/wp/v2/([a-z_-]+)(/\d+)?$#', $route, $m)) {
            return null;
        }
        $base = $m[1];
        foreach ([$registry->postTypes(), $registry->taxonomies()] as $table) {
            foreach ($table as $name => $row) {
                if (($row['rest_base'] ?: $name) === $base) {
                    return [(string) $name, isset($m[2])];
                }
            }
        }
        $type = match ($base) { 'users' => 'user', 'comments' => 'comment', 'media' => 'attachment', default => null };
        return $type === null ? null : [$type, isset($m[2])];
    }

    /**
     * @param array<string, array<string, mixed>> $fields the type's registered fields
     * @param callable(callable, array, string, string): mixed $call runs one get_callback
     */
    public static function apply(array $fields, string $type, bool $single, mixed $data, callable $call): mixed
    {
        $decorate = static function (array $item) use ($fields, $type, $call): array {
            foreach ($fields as $name => $options) {
                if (!empty($options['get_callback'])) {
                    $item[$name] = $call($options['get_callback'], $item, $name, $type);
                }
            }
            return $item;
        };
        if ($single || isset($data['id'])) {
            return $decorate($data);
        }
        return array_map(static fn ($item) => is_array($item) ? $decorate($item) : $item, $data);
    }
}
