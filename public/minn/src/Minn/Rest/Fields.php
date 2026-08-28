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
    /** @param list<string> $paths */
    private function __construct(public array $paths)
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
        return $paths === [] ? null : new self($paths);
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
}
