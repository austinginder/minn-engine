<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * What one kses pass allows: the tags, each tag's attributes (allowed
 * plainly or with value rules), whether a tag takes data- attributes,
 * which attributes hold URIs, and the schemes those URIs may use.
 */
final readonly class KsesPolicy
{
    /**
     * @param array<string, array<string, mixed>> $tags tag => attribute => true, or a list of value rules
     * @param array<string, list<string>> $prefixes tag => attribute-name prefixes it accepts (only "data-")
     * @param list<string> $uriAttributes
     * @param list<string> $schemes
     */
    private function __construct(
        private array $tags,
        private array $prefixes,
        private array $uriAttributes,
        public array $schemes,
    ) {
    }

    /** Post content from an author without unfiltered_html: the reference's post allowlist as captured. */
    public static function post(): self
    {
        return self::fromAllowlist(KsesEntities::allowlist('post'), Kses::SCHEMES, Kses::URI_ATTRIBUTES);
    }

    /** Comments, profiles and term descriptions: only the attributes each tag lists, nothing global. */
    public static function comment(): self
    {
        $tags = [];
        foreach (Kses::COMMENT as $tag => $attributes) {
            $tags[$tag] = array_fill_keys($attributes, true);
        }
        return new self($tags, [], Kses::URI_ATTRIBUTES, Kses::SCHEMES);
    }

    /**
     * A caller's own allowlist taken literally: a listed attribute is allowed
     * whatever it maps to (false included, as the reference only asks whether
     * the name is there), an array carries value rules, and "data-*" lets the
     * tag take any data- name. Nothing else is implied.
     *
     * @param array<array-key, mixed> $allowedHtml
     * @param list<string> $schemes
     * @param list<string> $uriAttributes
     */
    public static function fromAllowlist(array $allowedHtml, array $schemes, array $uriAttributes): self
    {
        $tags = [];
        $prefixes = [];
        foreach ($allowedHtml as $tag => $attributes) {
            if (!is_string($tag)) {
                continue; // a list of values, not tags: nothing is allowed
            }
            $tag = strtolower($tag);
            $tags[$tag] ??= [];
            foreach (is_array($attributes) ? $attributes : [] as $name => $rules) {
                if (!is_string($name)) {
                    continue;
                }
                $name = strtolower($name);
                if ($name === 'data-*') {
                    $prefixes[$tag] = ['data-'];
                    continue;
                }
                $tags[$tag][$name] = $rules;
            }
        }
        $lower = static fn (array $names): array => array_values(array_map(static fn (mixed $name): string => strtolower((string) $name), $names));
        return new self($tags, $prefixes, $lower($uriAttributes), $lower($schemes));
    }

    /** Whether the tag may appear at all. */
    public function allowsTag(string $tag): bool
    {
        return isset($this->tags[$tag]);
    }

    /**
     * The rules an attribute carries on a tag: an empty list when it is
     * allowed plainly, null when it is not allowed.
     *
     * @return array<array-key, mixed>|null
     */
    public function rules(string $tag, string $name): ?array
    {
        if (array_key_exists($name, $this->tags[$tag] ?? [])) {
            $rules = $this->tags[$tag][$name];
            return is_array($rules) ? $rules : [];
        }
        // "data-*" admits a data- name with at least one letter, digit, "_" or "-" after it.
        if (in_array('data-', $this->prefixes[$tag] ?? [], true) && preg_match('/^data-[a-z0-9_-]+$/', $name)) {
            return [];
        }
        return null;
    }

    /**
     * The attributes a tag must keep: lose one and the tag keeps none.
     *
     * @return list<string>
     */
    public function required(string $tag): array
    {
        $required = [];
        foreach ($this->tags[$tag] ?? [] as $name => $rules) {
            if (is_array($rules) && !empty($rules['required'])) {
                $required[] = (string) $name;
            }
        }
        return $required;
    }

    /** Whether the attribute holds a URI whose scheme must be judged. */
    public function holdsUri(string $name): bool
    {
        return in_array($name, $this->uriAttributes, true);
    }
}
