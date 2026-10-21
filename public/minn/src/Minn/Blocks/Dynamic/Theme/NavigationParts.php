<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Runtime\Records;
use Minn\Runtime\Runtime;

/**
 * The navigation family's small answers for the block_core_navigation_*
 * helpers (probes navigation-blocks, nav-pomo-rest-doubts): colour classes
 * and inline styles from a block's attributes or context, the post ids its
 * links point to, whether an item renders, and the typography presets older
 * content stored.
 */
final class NavigationParts
{
    /**
     * Colour classes and inline styles: for the text and for the background,
     * the first candidate set (a [kind, value] pair, kind named or custom)
     * wins: has-text-color and the named colour's class, or the custom one
     * inline; the same for the background.
     *
     * @param list<array{0: string, 1: mixed}> $text
     * @param list<array{0: string, 1: mixed}> $background
     * @return array{css_classes: list<string>, inline_styles: string}
     */
    public static function colors(array $text, array $background): array
    {
        $classes = [];
        $styles = '';
        foreach ([[$text, 'has-text-color', 'has-%s-color', 'color'], [$background, 'has-background', 'has-%s-background-color', 'background-color']] as [$candidates, $flag, $class, $property]) {
            foreach ($candidates as [$kind, $value]) {
                if (empty($value)) {
                    continue;
                }
                $classes[] = $flag;
                if ($kind === 'named') {
                    $classes[] = sprintf($class, $value);
                } else {
                    $styles .= "{$property}: {$value};";
                }
                break;
            }
        }
        return ['css_classes' => $classes, 'inline_styles' => $styles];
    }

    /**
     * A source's colours with the named one first, the custom one next and,
     * when a style key is given, the style's colour last.
     *
     * @return array{css_classes: list<string>, inline_styles: string}
     */
    public static function namedFirst(mixed $source, string $prefix = ''): array
    {
        $source = (array) $source;
        $style = $prefix === '' ? (array) ($source['style']['color'] ?? []) : [];
        [$text, $background] = $prefix === '' ? ['textColor', 'backgroundColor'] : ["{$prefix}TextColor", "{$prefix}BackgroundColor"];
        $custom = static fn (string $key): string => 'custom' . ucfirst($key);
        return self::colors(
            [['named', $source[$text] ?? null], ['custom', $source[$custom($text)] ?? null], ['custom', $style['text'] ?? null]],
            [['named', $source[$background] ?? null], ['custom', $source[$custom($background)] ?? null], ['custom', $style['background'] ?? null]],
        );
    }

    /**
     * A navigation's colours and its overlay's, from its attributes (no style colours).
     *
     * @return array<string, mixed>
     */
    public static function navigationColors(mixed $attributes): array
    {
        $attributes = (array) $attributes;
        unset($attributes['style']);
        return self::namedFirst($attributes) + self::overlay($attributes);
    }

    /**
     * A page list's colours, the style's own last, and its overlay's, from its navigation's context.
     *
     * @return array<string, mixed>
     */
    public static function pageListColors(mixed $context): array
    {
        return self::namedFirst($context) + self::overlay((array) $context);
    }

    /**
     * A link's colours from its navigation's context: the custom colour first,
     * then the named one, then the style's.
     *
     * @return array{css_classes: list<string>, inline_styles: string}
     */
    public static function linkColors(mixed $context): array
    {
        return self::colors(...self::linkCandidates((array) $context));
    }

    /**
     * A link's colours inside a submenu: the overlay's custom then named
     * colour first, else the link's own.
     *
     * @return array{css_classes: list<string>, inline_styles: string}
     */
    public static function submenuLinkColors(mixed $context): array
    {
        $context = (array) $context;
        [$text, $background] = self::linkCandidates($context);
        return self::colors(
            [['custom', $context['customOverlayTextColor'] ?? null], ['named', $context['overlayTextColor'] ?? null], ...$text],
            [['custom', $context['customOverlayBackgroundColor'] ?? null], ['named', $context['overlayBackgroundColor'] ?? null], ...$background],
        );
    }

    /**
     * The post ids the links in blocks point to, at any depth (a block's
     * inner links before its own), each once at its first key.
     *
     * @return array<int, mixed>
     */
    public static function postIds(mixed $blocks): array
    {
        $ids = [];
        foreach ($blocks ?? [] as $block) {
            $ids = [...$ids, ...self::blockPostIds($block)];
        }
        return array_unique($ids);
    }

    /**
     * The post ids a block's inner links point to, then its own when it links to a post.
     *
     * @return list<mixed>
     */
    public static function blockPostIds(mixed $block): array
    {
        $ids = !empty($block->inner_blocks) ? array_values(self::postIds($block->inner_blocks)) : [];
        if (($block->attributes['kind'] ?? null) === 'post-type' && isset($block->attributes['id'])) {
            $ids[] = $block->attributes['id'];
        }
        return $ids;
    }

    /**
     * Whether a navigation item renders: a link to a post or a page (by its
     * kind or its type) only while that post's status is one
     * render_block_core_navigation_link_allowed_post_status allows (publish).
     */
    public static function itemRenders(mixed $attributes, mixed $block): bool
    {
        $attributes = (array) $attributes;
        $linksPost = ($attributes['kind'] ?? null) === 'post-type' || in_array($attributes['type'] ?? null, ['post', 'page'], true);
        if (!$linksPost || empty($attributes['id'])) {
            return true;
        }
        $allowed = (array) Runtime::hooks()->filter('render_block_core_navigation_link_allowed_post_status', [['publish'], $attributes, $block]);
        $post = Records::post((int) $attributes['id']);
        return is_object($post) && in_array($post->post_status, $allowed, true);
    }

    /**
     * A navigation's typography presets in the forms older content stored
     * (var:preset|...) read as their values, strikethrough as line-through.
     *
     * @return mixed the parsed block
     */
    public static function typographyBackcompat(mixed $parsedBlock): mixed
    {
        if (($parsedBlock['blockName'] ?? '') !== 'core/navigation') {
            return $parsedBlock;
        }
        foreach (['textDecoration', 'fontStyle', 'fontWeight', 'textTransform'] as $property) {
            $value = $parsedBlock['attrs']['style']['typography'][$property] ?? null;
            if (is_string($value) && str_starts_with($value, 'var:preset|')) {
                $value = substr($value, (int) strrpos($value, '|') + 1);
                $parsedBlock['attrs']['style']['typography'][$property] = $value;
            }
            if ($property === 'textDecoration' && $value === 'strikethrough') {
                $parsedBlock['attrs']['style']['typography'][$property] = 'line-through';
            }
        }
        return $parsedBlock;
    }

    /**
     * A link's text and background candidates: custom, named, the style's.
     *
     * @param array<string, mixed> $context
     * @return array{0: list<array{0: string, 1: mixed}>, 1: list<array{0: string, 1: mixed}>}
     */
    private static function linkCandidates(array $context): array
    {
        $style = (array) ($context['style']['color'] ?? []);
        return [
            [['custom', $context['customTextColor'] ?? null], ['named', $context['textColor'] ?? null], ['custom', $style['text'] ?? null]],
            [['custom', $context['customBackgroundColor'] ?? null], ['named', $context['backgroundColor'] ?? null], ['custom', $style['background'] ?? null]],
        ];
    }

    /**
     * The overlay colours alongside the main ones, from a source's overlay* keys.
     *
     * @param array<string, mixed> $source
     * @return array{overlay_css_classes: list<string>, overlay_inline_styles: string}
     */
    private static function overlay(array $source): array
    {
        $overlay = self::namedFirst($source, 'overlay');
        return ['overlay_css_classes' => $overlay['css_classes'], 'overlay_inline_styles' => $overlay['inline_styles']];
    }
}
