<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;

/**
 * An item a REST response carries, through the filter the reference runs
 * as it prepares one (rest_prepare_{type}, rest_prepare_attachment,
 * rest_prepare_user, rest_prepare_comment, rest_prepare_{taxonomy}): the
 * item as a response object, its links on the response rather than in its
 * data, handed with the object it describes and the request; what the
 * filter leaves is the item. With nothing hooked, or a response handed back
 * untouched, the item is Minn's own, byte for byte. Fields plugin code
 * registers for the type are added first, as the reference adds them
 * before the filter runs.
 */
final class RuntimePrepare
{
    /** Remembers the request a REST call is answering, for the filters its items pass through (on the request's own runtime). */
    public static function answering(\WP_REST_Request $request): void
    {
        Runtime::current()->set('rest_prepare_request', $request);
    }

    /**
     * Runs an in-process REST call answering its own request (the filters
     * hand plugins that one), then puts back the request it interrupted.
     *
     * @template T
     * @param \Closure(): T $run
     * @return T
     */
    public static function during(\WP_REST_Request $request, \Closure $run): mixed
    {
        $outer = Runtime::current()->get('rest_prepare_request');
        self::answering($request);
        try {
            return $run();
        } finally {
            Runtime::current()->set('rest_prepare_request', $outer);
        }
    }

    /** The post types whose REST answers and saves get their hooked blocks, before plugins say. */
    public const BLOCK_HOOKS_TYPES = ['post', 'page', 'wp_block', 'wp_navigation'];

    /**
     * A post-shaped item (a post, a page, a pattern, a navigation, an
     * attachment, any other type) through rest_prepare_{type}, given its
     * hooked blocks first; the post it describes is looked up only when a
     * hook needs it.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function postItem(string $filter, array $item, int $postId, string $type): array
    {
        return self::item($filter, $item, static fn () => \get_post($postId), $type);
    }

    /**
     * A post item as rest_block_hooks_post_types has it (asked of every item
     * the posts controller prepares, with the post it describes): a type it
     * lists has its raw content given its hooked blocks and rendered again
     * from that, a navigation's root list put in its meta, as the
     * reference's insert_hooked_blocks_into_rest_response does (probe
     * hook-inputs-2).
     *
     * @param array<string, mixed> $item
     * @param \Closure(): mixed $described
     * @return array<string, mixed>
     */
    private static function hookedBlocks(array $item, string $type, \Closure $described): array
    {
        $post = $described();
        if (!in_array($type, (array) Runtime::hooks()->filter('rest_block_hooks_post_types', [self::BLOCK_HOOKS_TYPES, $type, $post]), true) || empty($item['content']['raw'])) {
            return $item;
        }
        $atRoot = [];
        $item['content']['raw'] = self::withHookedBlocks((string) $item['content']['raw'], $post, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata', $atRoot);
        if ($atRoot !== []) {
            $item['meta']['_wp_ignored_hooked_blocks'] = \Minn\Json::encode($atRoot, \Minn\Json::ESCAPED);
        }
        // Rendered again only where the item shows its content rendered (a pattern carries it raw alone).
        if (array_key_exists('rendered', (array) $item['content'])) {
            $item['content']['rendered'] = Runtime::hooks()->filter('the_content', [$item['content']['raw']]);
        }
        return $item;
    }

    /**
     * A prepared post about to be saved, as rest_block_hooks_post_types has
     * it: a type it lists has its content's ignored hooked blocks marked, a
     * navigation's root list put in meta_input, as the reference's
     * update_ignored_hooked_blocks_postmeta does; asked of every save.
     */
    public static function savedWithHookedBlocks(\stdClass $prepared, string $type): void
    {
        if (!in_array($type, (array) Runtime::hooks()->filter('rest_block_hooks_post_types', [self::BLOCK_HOOKS_TYPES, $type, $prepared]), true) || !isset($prepared->post_content)) {
            return;
        }
        $atRoot = [];
        $prepared->post_content = self::withHookedBlocks((string) $prepared->post_content, $prepared->ID ?? null, 'set_ignored_hooked_blocks_metadata', $atRoot);
        if ($atRoot !== []) {
            $prepared->meta_input = (array) ($prepared->meta_input ?? []) + ['_wp_ignored_hooked_blocks' => \Minn\Json::encode($atRoot, \Minn\Json::ESCAPED)];
        }
    }

    /** @param list<string> $atRoot a navigation's root ignored list, filled in */
    private static function withHookedBlocks(string $content, mixed $post, string $callback, array &$atRoot): string
    {
        return (string) \apply_block_hooks_to_content_from_post_object($content, $post, $callback, $atRoot);
    }

    /**
     * One item through a rest_prepare filter.
     *
     * @param array<string, mixed> $item
     * @param \Closure(): mixed $described the object the item describes, fetched only when a plugin listens
     * @param ?string $postType a post item's type, given its hooked blocks first (hookedBlocks())
     * @return array<string, mixed>
     */
    public static function item(string $filter, array $item, \Closure $described, ?string $postType = null): array
    {
        if (!Runtime::booted()) {
            return $item;
        }
        if ($postType !== null) {
            $item = self::hookedBlocks($item, $postType, $described);
        }
        $request = Runtime::current()->get('rest_prepare_request');
        $request = $request instanceof \WP_REST_Request ? $request : new \WP_REST_Request('GET', '/');
        $item = RegisteredFields::add($item, self::objectType($filter), $request);
        if (!Runtime::hooks()->has($filter)) {
            return $item;
        }
        $response = RuntimeRoutes::itemResponse($item);
        $before = [$response->get_data(), $response->get_links()];
        $filtered = \rest_ensure_response(Runtime::hooks()->filter((string) $filter, [$response, $described(), $request]));
        if ($filtered instanceof \WP_Error) {
            return $item;
        }
        if ($filtered === $response && [$response->get_data(), $response->get_links()] === $before) {
            return $item;
        }
        $data = \rest_get_server()->response_to_data($filtered, false);
        return is_array($data) ? $data : $item;
    }

    /** The object type register_rest_field names for the items a prepare filter sees: the post type, taxonomy, or kind of item. */
    private static function objectType(string $filter): string
    {
        $type = substr($filter, strlen('rest_prepare_'));
        return match ($type) {
            'block_type' => 'block-type',
            'widget_type' => 'widget-type',
            'menu_location' => 'menu-location',
            default => $type,
        };
    }
}
