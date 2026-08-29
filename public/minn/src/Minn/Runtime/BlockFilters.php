<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Blocks\Block;

/**
 * The block-level filters plugin code hooks (pre_render_block,
 * render_block_data, render_block, render_block_{name}), applied around the
 * engine's own renderer so a plugin sees every block the page renders, not
 * only the ones it registered.
 */
final class BlockFilters
{
    public static function active(): bool
    {
        if (!Runtime::booted()) {
            return false;
        }
        $hooks = Runtime::hooks();
        return $hooks->has('pre_render_block') || $hooks->has('render_block_data') || $hooks->has('render_block');
    }

    /** @return array<string, mixed> the parsed-array shape plugin code reads */
    public static function toArray(Block $block): array
    {
        return [
            'blockName' => $block->name,
            'attrs' => $block->attrs,
            'innerBlocks' => array_map([self::class, 'toArray'], $block->innerBlocks),
            'innerHTML' => $block->innerHtml,
            'innerContent' => $block->innerContent,
        ];
    }

    public static function fromArray(array $parsed): Block
    {
        return new Block(
            $parsed['blockName'] ?? null,
            (array) ($parsed['attrs'] ?? []),
            array_map([self::class, 'fromArray'], (array) ($parsed['innerBlocks'] ?? [])),
            (string) ($parsed['innerHTML'] ?? ''),
            (array) ($parsed['innerContent'] ?? []),
        );
    }

    /** @var array<int, array<string, mixed>> the parsed array render_block_data produced, by block object id */
    private static array $parsedFor = [];

    /** A short-circuit from pre_render_block, or the block as render_block_data left it. */
    public static function before(Block $block): string|Block
    {
        $parsed = self::toArray($block);
        $pre = Runtime::hooks()->filter('pre_render_block', [null, $parsed, null]);
        if ($pre !== null) {
            return (string) $pre;
        }
        $filtered = Runtime::hooks()->filter('render_block_data', [$parsed, $parsed, null]);
        if (!is_array($filtered)) {
            return $block;
        }
        // Keys a filter added ride along to render_block, as they do on the reference.
        $block = self::fromArray($filtered);
        self::$parsedFor[spl_object_id($block)] = $filtered;
        return $block;
    }

    public static function after(Block $block, string $html): string
    {
        $id = spl_object_id($block);
        $parsed = self::$parsedFor[$id] ?? self::toArray($block);
        unset(self::$parsedFor[$id]);
        $instance = class_exists('\WP_Block', false) ? new \WP_Block($parsed, self::context()) : null;
        $html = (string) Runtime::hooks()->filter('render_block', [$html, $parsed, $instance]);
        return (string) Runtime::hooks()->filter('render_block_' . $block->name, [$html, $parsed, $instance]);
    }

    /** @return array<string, mixed> */
    private static function context(): array
    {
        $post = $GLOBALS['post'] ?? Runtime::current()->get('post');
        if (is_object($post) && isset($post->ID)) {
            return ['postId' => (int) $post->ID, 'postType' => (string) $post->post_type];
        }
        return [];
    }
}
