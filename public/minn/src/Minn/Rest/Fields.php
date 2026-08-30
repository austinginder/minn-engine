<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The _fields response filter. Dot paths descend ("title.rendered"); the
 * object's own key order is kept. Applied per item on list responses and to
 * the whole payload otherwise, which is why _fields on the associative
 * types response strips every key and yields [] over HTTP, a reference
 * quirk the engine reproduces by construction.
 */
final readonly class Fields
{
    /**
     * @param list<string> $paths
     * @param bool $deferred true when _embed rides along: a single object is
     *                       embedded first and filtered by Embed afterwards
     */
    private function __construct(public array $paths, public bool $deferred = false)
    {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): ?self
    {
        $raw = $query['_fields'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        $paths = array_values(array_filter(array_map(trim(...), explode(',', (string) $raw)), static fn (string $s) => $s !== ''));
        return $paths === [] ? null : new self($paths, array_key_exists('_embed', $query));
    }

    /** The same paths plus _links whenever _embedded is among them: the reference's single-object rule under _embed. */
    public function withLinksForEmbedded(): self
    {
        if (!in_array('_embedded', $this->paths, true) || in_array('_links', $this->paths, true)) {
            return $this;
        }
        return new self([...$this->paths, '_links'], $this->deferred);
    }

    public function apply(array $object): array
    {
        return self::filter($object, $this->paths);
    }

    private static function filter(array $object, array $paths): array
    {
        $out = [];
        foreach ($object as $key => $value) {
            $keepWhole = false;
            $sub = [];
            foreach ($paths as $path) {
                if ($path === $key) {
                    $keepWhole = true;
                    break;
                }
                if (str_starts_with($path, $key . '.')) {
                    $sub[] = substr($path, strlen((string) $key) + 1);
                }
            }
            if ($keepWhole) {
                $out[$key] = $value;
            } elseif ($sub !== [] && is_array($value)) {
                $out[$key] = self::filter($value, $sub);
            }
        }
        return $out;
    }

    /**
     * The fields a response keeps when a caller names some: known fields as
     * given, dotted paths whose root is known, and `id` whenever it exists.
     *
     * @param list<string> $available
     * @param list<string> $requested the parsed `_fields` list
     * @return list<string>
     */
    public static function select(array $available, array $requested): array
    {
        $requested = array_map('trim', $requested);
        if (in_array('id', $available, true)) {
            $requested[] = 'id';
        }
        $kept = [];
        foreach ($requested as $field) {
            $root = str_contains($field, '.') ? strtok($field, '.') : $field;
            if (in_array($root, $available, true)) {
                $kept[] = $field;
            }
        }
        return $kept;
    }
}
