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
    /** Whether any block filter is registered. */
    public static function active(): bool
    {
        if (!Runtime::booted()) {
            return false;
        }
        $hooks = Runtime::hooks();
        return $hooks->has('pre_render_block') || $hooks->has('render_block_data') || $hooks->has('render_block');
    }

    /**
     * A block as the parsed array plugins receive.
     *
     * @return array<string, mixed> the parsed-array shape plugin code reads
     */
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

    /** A block from the parsed array plugins hand back. */
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

    /** The data filters the engine's renderer does itself for a block it renders natively: element styles, style variations, a gallery's image ids. */
    public const NATIVE_DATA_DONE = ['wp_render_elements_support_styles' => 10, 'wp_render_block_style_variation_support_styles' => 10, 'block_core_gallery_data_id_backcompatibility' => 10];

    /**
     * The render filters it does itself: the element and style variation
     * classes, the layout's container classes (a child's own layout left to a
     * stand-in), the paragraph's class.
     */
    public const NATIVE_RENDER_DONE = ['wp_render_elements_class_name' => 10, 'wp_render_block_style_variation_class_name' => 10, 'wp_render_layout_support_flag' => [10, '_minn_render_child_layout_support'], 'block_core_paragraph_add_class' => 10];

    /**
     * A parsed block through render_block_data, without the callbacks done
     * already (NATIVE_DATA_DONE for a block the engine's renderer renders).
     *
     * @param array<string, mixed> $parsed
     * @param object|null $parent the WP_Block rendering it, when there is one
     * @param array<string, int|array{0: int, 1: string}> $done
     */
    public static function data(array $parsed, ?object $parent, array $done = []): mixed
    {
        return Runtime::hooks()->filterWithout('render_block_data', [$parsed, $parsed, $parent], $done);
    }

    /**
     * A rendered block through render_block and its per-name filter, without
     * the callbacks done already (NATIVE_RENDER_DONE, with its stand-ins, for
     * a block the engine's renderer renders).
     *
     * @param array<string, mixed> $parsed
     * @param object|null $instance the WP_Block the filters receive
     * @param array<string, int|array{0: int, 1: string}> $done
     */
    public static function rendered(string $html, array $parsed, ?object $instance, array $done = []): string
    {
        $html = (string) Runtime::hooks()->filterWithout('render_block', [$html, $parsed, $instance], $done);
        return (string) Runtime::hooks()->filterWithout('render_block_' . ($parsed['blockName'] ?? ''), [$html, $parsed, $instance], $done);
    }

    /**
     * A short-circuit from pre_render_block, or the block as render_block_data left it.
     *
     * @param array<string, int|array{0: int, 1: string}> $done the data filters done already
     */
    public static function before(Block $block, array $done = []): string|Block
    {
        $parsed = self::toArray($block);
        $pre = Runtime::hooks()->filter('pre_render_block', [null, $parsed, null]);
        if ($pre !== null) {
            return (string) $pre;
        }
        $filtered = self::data($parsed, null, $done);
        if (!is_array($filtered)) {
            return $block;
        }
        // Keys a filter added ride along to render_block, as they do on the reference.
        $block = self::fromArray($filtered);
        self::$parsedFor[spl_object_id($block)] = $filtered;
        return $block;
    }

    /**
     * A rendered block through render_block and its per-name filter.
     *
     * @param array<string, int|array{0: int, 1: string}> $done the render filters done already
     */
    public static function after(Block $block, string $html, array $done = []): string
    {
        $id = spl_object_id($block);
        $parsed = self::$parsedFor[$id] ?? self::toArray($block);
        unset(self::$parsedFor[$id]);
        $instance = class_exists('\WP_Block', false) ? new \WP_Block($parsed, self::context()) : null;
        return self::rendered($html, $parsed, $instance, $done);
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
