<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * A patch to a view configuration (WP_View_Config_Data; probe
 * rest-view-config), applied as the reference applies it. Only schema
 * version 1 is taken, and only the documented keys: each is set (its value
 * replaced), merged (maps merged key by key, lists joined with their
 * members matched by identity), replaced (merged, but lists replaced
 * whole) or removed (keys from a map, members from a list by identity).
 * A null in a patch removes what it names. What a caller got wrong comes
 * back as problems: the function to name and the words.
 */
final class ViewConfigPatch
{
    public const KEYS = ['default_view', 'default_layouts', 'view_list', 'form'];

    private const SHAPE = 'A view configuration patch value must match the shape of the value it patches: a list merges into a list, and an associative array into an associative array.';

    /** @var list<array{0: ?string, 1: string}> */
    private array $problems = [];

    private function __construct(private readonly bool $replaceLists)
    {
    }

    /**
     * A configuration with a patch applied in a mode (set, merge, replace,
     * remove), and the problems met: [function or null for the mode's own
     * method, words].
     *
     * @param array<string, mixed> $config
     * @param array<mixed> $patch
     * @return array{0: array<string, mixed>, 1: list<array{0: ?string, 1: string}>}
     */
    public static function apply(array $config, array $patch, mixed $version, string $mode): array
    {
        if (!is_numeric($version) || (int) $version !== 1) {
            return [$config, [[null, 'A view configuration patch must declare a supported schema version.']]];
        }
        $patcher = new self($mode === 'replace');
        foreach ($patch as $key => $value) {
            // A remove spec may list keys at the top; the documented keys are not removable that way.
            if (!is_string($key)) {
                continue;
            }
            if (!in_array($key, self::KEYS, true)) {
                $patcher->problems[] = [null, sprintf('"%s" is not a documented view configuration key.', $key)];
                continue;
            }
            $patched = match ($mode) {
                'set' => self::stripNulls($value),
                'remove' => array_key_exists($key, $config) ? self::remove($config[$key], $value) : null,
                default => array_key_exists($key, $config) ? $patcher->merge($config[$key], $value) : self::stripNulls($value),
            };
            if ($mode === 'remove' && !array_key_exists($key, $config)) {
                continue;
            }
            if ($patched === null) {
                unset($config[$key]);
            } else {
                $config[$key] = $patched;
            }
        }
        return [$config, $patcher->problems];
    }

    /** A value with every null in it removed, lists closed up. */
    public static function stripNulls(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $list = array_is_list($value);
        $out = [];
        foreach ($value as $key => $item) {
            if ($item !== null) {
                $out[$key] = self::stripNulls($item);
            }
        }
        return $list ? array_values($out) : $out;
    }

    /** The identity a list member is matched by: a word itself, a map's id or slug; null for none. */
    public static function identity(mixed $item): ?string
    {
        if (is_string($item) || is_int($item)) {
            return (string) $item;
        }
        if (is_array($item)) {
            foreach (['id', 'slug'] as $key) {
                if (isset($item[$key]) && (is_string($item[$key]) || is_int($item[$key]))) {
                    return (string) $item[$key];
                }
            }
        }
        return null;
    }

    /** Incoming merged over current: maps key by key, lists by identity (or replaced), anything else replaced; a mismatched shape is a problem and leaves current. */
    private function merge(mixed $current, mixed $incoming): mixed
    {
        if ($incoming === null) {
            return null;
        }
        if (!is_array($current) || !is_array($incoming) || $current === [] || $incoming === []) {
            return self::stripNulls($incoming);
        }
        if (array_is_list($current) !== array_is_list($incoming)) {
            $this->problems[] = ['merge_properties', self::SHAPE];
            return $current;
        }
        if (array_is_list($current)) {
            return $this->replaceLists ? self::stripNulls($incoming) : $this->mergeList($current, $incoming);
        }
        foreach ($incoming as $key => $value) {
            if ($value === null) {
                unset($current[$key]);
                continue;
            }
            $current[$key] = array_key_exists($key, $current) ? $this->merge($current[$key], $value) : self::stripNulls($value);
        }
        return $current;
    }

    /**
     * @param list<mixed> $current
     * @param list<mixed> $incoming
     * @return list<mixed>
     */
    private function mergeList(array $current, array $incoming): array
    {
        foreach ($incoming as $item) {
            $identity = self::identity($item);
            $at = $identity === null ? false : array_search($identity, array_map(self::identity(...), $current), true);
            if ($at === false) {
                $current[] = self::stripNulls($item);
            } elseif (is_array($current[$at]) && is_array($item)) {
                $current[$at] = $this->merge($current[$at], $item);
            }
        }
        return $current;
    }

    /** Keys removed from a map (a list of keys, or a key mapped to what to remove inside it); members removed from a list by identity. */
    private static function remove(mixed $current, mixed $spec): mixed
    {
        if (!is_array($current) || !is_array($spec)) {
            return $current;
        }
        if (array_is_list($current) && $current !== []) {
            $gone = array_map(static fn ($item) => self::identity($item), $spec);
            return array_values(array_filter($current, static fn ($item) => !in_array(self::identity($item), $gone, true)));
        }
        foreach ($spec as $key => $inner) {
            if (is_int($key) && (is_string($inner) || is_int($inner))) {
                unset($current[$inner]);
            } elseif (is_string($key) && array_key_exists($key, $current)) {
                $current[$key] = self::remove($current[$key], $inner);
            }
        }
        return $current;
    }
}
