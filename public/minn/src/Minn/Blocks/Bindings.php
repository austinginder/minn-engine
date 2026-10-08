<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Runtime\Runtime;

/**
 * Block bindings (probe block-bindings): a block attribute bound to a
 * source registered with register_block_bindings_source takes the source's
 * value as the block renders. Only the attributes a block supports bind
 * (SUPPORTED, widened by block_bindings_supported_attributes and its
 * per-block form); a "__default" pattern-overrides binding stands for every
 * one of them. A static block's saved HTML takes the values (rich text,
 * kses'd, inside the element its definition selects; plain values set as
 * the selected element's attribute); a dynamic block renders with them.
 */
final class Bindings
{
    public const SUPPORTED = [
        'core/paragraph' => ['content'],
        'core/heading' => ['content'],
        'core/image' => ['id', 'url', 'title', 'alt', 'caption'],
        'core/button' => ['url', 'text', 'linkTarget', 'rel'],
        'core/post-date' => ['datetime'],
        'core/navigation-link' => ['url'],
        'core/navigation-submenu' => ['url'],
    ];

    /** The attributes a block may bind (get_block_bindings_supported_attributes). @return list<string> */
    public static function supported(string $name): array
    {
        $hooks = Runtime::hooks();
        $attributes = (array) $hooks->filter('block_bindings_supported_attributes', [self::SUPPORTED[$name] ?? [], $name]);
        return array_values((array) $hooks->filter("block_bindings_supported_attributes_{$name}", [$attributes]));
    }

    /**
     * The bound attributes' values for a block about to render, as the
     * sources answer them for a WP_Block made with the context given (each
     * source also seeing the context it uses); the expanded bindings ride
     * along under metadata for a pattern-overrides default. Empty when
     * nothing is bound or nothing answered.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function values(Block $block, array $context): array
    {
        $supported = $block->name === null ? [] : self::supported($block->name);
        $bindings = $block->attrs['metadata']['bindings'] ?? null;
        if ($supported === [] || !is_array($bindings) || $bindings === []) {
            return [];
        }
        $computed = [];
        if (($bindings['__default']['source'] ?? null) === 'core/pattern-overrides') {
            $bindings = array_combine($supported, array_map(static fn (string $name): mixed => $bindings[$name] ?? ['source' => 'core/pattern-overrides'], $supported));
            $computed['metadata'] = array_merge((array) $block->attrs['metadata'], ['bindings' => $bindings]);
        }
        $instance = null;
        foreach ($bindings as $attribute => $binding) {
            $name = is_array($binding) && is_string($binding['source'] ?? null) ? $binding['source'] : null;
            $source = in_array($attribute, $supported, true) && $name !== null ? \WP_Block_Bindings_Registry::get_instance()->get_registered($name) : null;
            if ($source === null) {
                continue;
            }
            $instance ??= new \WP_Block($block->toArray(), $context);
            foreach ((array) $source->uses_context as $key) {
                if (array_key_exists($key, $context)) {
                    $instance->context[$key] = $context[$key];
                }
            }
            $value = $source->get_value(is_array($binding['args'] ?? null) ? $binding['args'] : [], $instance, (string) $attribute);
            if ($value !== null) {
                $computed[$attribute] = $value;
            }
        }
        return $computed;
    }

    /**
     * A static block's HTML with the bound values in place, by its type's
     * attribute definitions: rich text and html replace what the selected
     * element holds; an attribute source sets the selected element's
     * attribute; anything else is left alone.
     *
     * @param array<string, mixed> $values
     */
    public static function html(string $html, string $blockName, array $values): string
    {
        $definitions = (array) (\WP_Block_Type_Registry::get_instance()->get_registered($blockName)?->attributes ?? []);
        foreach ($values as $attribute => $value) {
            $definition = (array) ($definitions[$attribute] ?? []);
            $selector = (string) ($definition['selector'] ?? '');
            $html = match ($definition['source'] ?? '') {
                'html', 'rich-text' => self::inner($html, explode(',', $selector), (string) \wp_kses_post((string) $value)),
                'attribute' => self::attribute($html, $selector, (string) ($definition['attribute'] ?? ''), $value),
                default => $html,
            };
        }
        return $html;
    }

    /** What the first element matching a selector holds (the block's first element counts), replaced. @param list<string> $selectors */
    private static function inner(string $html, array $selectors, string $value): string
    {
        if (!preg_match(self::opening('[a-zA-Z][a-zA-Z0-9-]*'), $html, $first, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        foreach ($selectors as $selector) {
            $tag = strtolower(trim($selector));
            if ($tag === '') {
                continue;
            }
            $found = strtolower($first[1][0]) === $tag ? $first[0] : (preg_match(self::opening(preg_quote($tag, '/')), $html, $m, PREG_OFFSET_CAPTURE, $first[0][1] + strlen($first[0][0])) ? $m[0] : null);
            $start = $found === null ? null : $found[1] + strlen($found[0]);
            $end = $start === null ? null : self::closing($html, $tag, $start);
            if ($end !== null) {
                return substr($html, 0, $start) . $value . substr($html, $end);
            }
        }
        return $html;
    }

    /** Where the element opened before $from closes: the offset of its "</tag", nested ones of the same name skipped. */
    private static function closing(string $html, string $tag, int $from): ?int
    {
        preg_match_all('/<(\/?)' . preg_quote($tag, '/') . '\b/i', $html, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER, $from);
        $depth = 0;
        foreach ($tags as $m) {
            if ($m[1][0] === '') {
                $depth++;
            } elseif ($depth-- === 0) {
                return $m[0][1];
            }
        }
        return null;
    }

    private static function opening(string $name): string
    {
        return '/<(' . $name . ')\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i';
    }

    /** The first element matching the selector, with an attribute set to the value. */
    private static function attribute(string $html, string $selector, string $name, mixed $value): string
    {
        $processor = new \WP_HTML_Tag_Processor($html);
        if ($name === '' || !$processor->next_tag(['tag_name' => $selector])) {
            return $html;
        }
        $processor->set_attribute($name, is_bool($value) ? $value : (string) $value);
        return $processor->get_updated_html();
    }
}
