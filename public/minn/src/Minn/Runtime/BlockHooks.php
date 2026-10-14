<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
     * A template part's markup with its hooked blocks inserted. The context
     * is the part itself: a plugin reads its area to tell a header from a
     * footer, and its content to see whether its block is already there.
     */
    public static function forPart(string $markup, string $slug, string $area): string
    {
        if (!self::active()) {
            return $markup;
        }
        $template = Handoff::newBlockTemplate();
        $template->id = Themes::stylesheet() . '//' . $slug;
        $template->theme = Themes::stylesheet();
        $template->slug = $slug;
        $template->type = 'wp_template_part';
        $template->area = $area;
        $template->content = $markup;
        return _minn_apply_hooks_to_template_part($markup, $template);
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
}
