<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * One parsed block. A null name is freeform HTML between blocks. The
 * innerContent list holds the block's own HTML chunks in order, with a
 * null placeholder wherever an inner block sits.
 *
 * @property list<Block> $innerBlocks
 * @property list<string|null> $innerContent
 */
final readonly class Block
{
    public function __construct(
        public ?string $name,
        public array $attrs,
        public array $innerBlocks,
        public string $innerHtml,
        public array $innerContent,
    ) {
    }

    public static function freeform(string $html): self
    {
        return new self(null, [], [], $html, [$html]);
    }

    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }

    /** The class names declared on the block's own attributes (className). */
    public function className(): string
    {
        return (string) ($this->attrs['className'] ?? '');
    }
}
