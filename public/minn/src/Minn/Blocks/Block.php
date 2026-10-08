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

    /** A block-less run of HTML, as the parser reads it. */
    public static function freeform(string $html): self
    {
        return new self(null, [], [], $html, [$html]);
    }

    /** A block as parse_blocks hands it out, as the value object. @param array<string, mixed> $block */
    public static function fromArray(array $block): self
    {
        return new self(
            $block['blockName'] ?? null,
            (array) ($block['attrs'] ?? []),
            array_map(static fn (array $inner): self => self::fromArray($inner), (array) ($block['innerBlocks'] ?? [])),
            (string) ($block['innerHTML'] ?? ''),
            (array) ($block['innerContent'] ?? []),
        );
    }

    /** The same block with other attributes. @param array<string, mixed> $attrs */
    public function withAttrs(array $attrs): self
    {
        return new self($this->name, $attrs, $this->innerBlocks, $this->innerHtml, $this->innerContent);
    }

    /**
     * The block as parse_blocks hands it to plugin code.
     *
     * @return array{blockName: ?string, attrs: array<string, mixed>, innerBlocks: list<array<string, mixed>>, innerHTML: string, innerContent: list<string|null>}
     */
    public function toArray(): array
    {
        return [
            'blockName' => $this->name,
            'attrs' => $this->attrs,
            'innerBlocks' => array_map(static fn (self $inner): array => $inner->toArray(), $this->innerBlocks),
            'innerHTML' => $this->innerHtml,
            'innerContent' => $this->innerContent,
        ];
    }

    /** One attribute, or the default. */
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
