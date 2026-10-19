<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Blocks\Block;
use Minn\Blocks\Serializer;

/**
 * The Block Hooks API on the engine's own front end: a plugin asks for its
 * block to be inserted next to an anchor block in a template part or a
 * pattern (WooCommerce puts the mini-cart after the navigation in a
 * header), and the markup the renderer reads carries those insertions.
 *
 * The facade owns the traversal (wp-api/blocks.php); this is the seam the
 * theme blocks call, and it stays inert until a plugin actually hooks
 * something, so a site without such a plugin parses nothing extra.
 */
final class BlockHooks
{
    /**
     * The blocks hooked to each anchor, by position (get_hooked_blocks), as
     * the registered block types' block_hooks name them.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function hooked(): array
    {
        $hooked = [];
        foreach (Handoff::blockTypes()->get_all_registered() as $type) {
            foreach ((array) ($type->block_hooks ?? []) as $anchor => $position) {
                $hooked[$anchor][$position][] = $type->name;
            }
        }
        return $hooked;
    }

    /** Whether block hooks are registered (the runtime's functions are there only once it is up). */
    public static function active(): bool
    {
        if (!Handoff::bound('blockTypes')) {
            return false;
        }
        return self::hooked() !== [] || Runtime::hooks()->has('hooked_block_types');
    }

    /**
     * The blocks hooked to an anchor at a position (insert_hooked_blocks),
     * each as the filters leave it, serialized; a type a filter suppresses,
     * or one the anchor lists as ignored, goes in nothing (probe
     * post-block-hooks).
     *
     * @param array<string, mixed> $anchor
     * @param array<string, array<string, list<string>>> $hooked
     */
    public static function insert(array $anchor, string $position, array $hooked, mixed $context): string
    {
        $ignored = (array) ($anchor['attrs']['metadata']['ignoredHookedBlocks'] ?? []);
        $markup = '';
        foreach (self::filtered($anchor, $position, $hooked, $context) as [$type, $parsed]) {
            $markup .= $parsed === null || in_array($type, $ignored, true) ? '' : Serializer::block(Block::fromArray((array) $parsed));
        }
        return $markup;
    }

    /**
     * The anchor's ignoredHookedBlocks with the types hooked to it at a
     * position added (set_ignored_hooked_blocks_metadata): those a filter
     * leaves standing, the list keeping the keys it had (probe
     * post-block-hooks). Nothing is inserted: the markup is empty.
     *
     * @param array<string, mixed> $anchor
     * @param array<string, array<string, list<string>>> $hooked
     */
    public static function markIgnored(array &$anchor, string $position, array $hooked, mixed $context): string
    {
        $types = [];
        foreach (self::filtered($anchor, $position, $hooked, $context) as [$type, $parsed]) {
            if ($parsed !== null) {
                $types[] = $type;
            }
        }
        if ($types !== []) {
            $anchor['attrs']['metadata']['ignoredHookedBlocks'] = array_unique(array_merge((array) ($anchor['attrs']['metadata']['ignoredHookedBlocks'] ?? []), $types));
        }
        return '';
    }

    /**
     * The types hooked_block_types names for an anchor at a position, each
     * with the block hooked_block and hooked_block_{type} make of it (null
     * when one suppresses it).
     *
     * @param array<string, mixed> $anchor
     * @param array<string, array<string, list<string>>> $hooked
     * @return list<array{0: string, 1: mixed}>
     */
    private static function filtered(array $anchor, string $position, array $hooked, mixed $context): array
    {
        $name = (string) ($anchor['blockName'] ?? '');
        $hooks = Runtime::hooks();
        $out = [];
        foreach ((array) $hooks->filter('hooked_block_types', [$hooked[$name][$position] ?? [], $position, $name, $context]) as $type) {
            $type = (string) $type;
            $parsed = $hooks->filter('hooked_block', [['blockName' => $type, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], $type, $position, $anchor, $context]);
            $out[] = [$type, $hooks->filter("hooked_block_{$type}", [$parsed, $type, $position, $anchor, $context])];
        }
        return $out;
    }

    /**
     * A plugin-registered pattern's content, hooks already applied by the
     * registry, for a slug the theme does not carry. Null when the runtime
     * is not up or nothing registered that name.
     */
    public static function registeredPattern(string $slug): ?string
    {
        if (!Handoff::bound('blockPatterns')) {
            return null;
        }
        $pattern = Handoff::blockPatterns()->get_registered($slug);
        $content = is_array($pattern) ? (string) ($pattern['content'] ?? '') : '';
        return $content === '' ? null : $content;
    }

    /**
     * A theme pattern's markup with its hooked blocks inserted, with the
     * pattern array as the context (blockTypes and categories are how a
     * plugin recognises a header pattern).
     *
     * @param list<string> $blockTypes
     * @param list<string> $categories
     */
    public static function forPattern(string $markup, string $slug, array $blockTypes, array $categories): string
    {
        if (!self::active()) {
            return $markup;
        }
        $context = [
            'name' => $slug,
            'slug' => $slug,
            'content' => $markup,
            'blockTypes' => $blockTypes,
            'categories' => $categories,
        ];
        return (string) Handoff::blockHooks($markup, $context, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
    }

    /**
     * A post's blocks with the blocks hooked to them, as the reference reads
     * a navigation's or a synced pattern's
     * (apply_block_hooks_to_content_from_post_object): the hooked blocks the
     * post says to leave out left out; as stored with no WordPress layer to
     * ask.
     */
    public static function forPost(string $content, int $postId): string
    {
        if (!Handoff::bound('blockHooksForPost')) {
            return $content;
        }
        $ignored = null;
        return (string) Handoff::blockHooksForPost($content, $postId, 'insert_hooked_blocks', $ignored);
    }
}
