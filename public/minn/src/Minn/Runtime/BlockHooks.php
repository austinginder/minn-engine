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
    public static function active(): bool
    {
        if (!Runtime::booted() || !function_exists('get_hooked_blocks')) {
            return false;
        }
        return get_hooked_blocks() !== [] || Runtime::hooks()->has('hooked_block_types');
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
        $template = new \WP_Block_Template();
        $template->id = get_stylesheet() . '//' . $slug;
        $template->theme = get_stylesheet();
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
        if (!Runtime::booted() || !class_exists(\WP_Block_Patterns_Registry::class, false)) {
            return null;
        }
        $pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered($slug);
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
        return (string) apply_block_hooks_to_content($markup, $context, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
    }
}
