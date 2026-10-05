<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * A node the tree builder places: an element (named as the HTML API
 * reports it, upper-cased, with its namespace) or a text, comment, doctype
 * or other leaf. Real when a token in the document made it (its offset is
 * where that token starts), virtual when the parser implied it.
 */
final class Node
{
    public const VOID = ['AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR'];
    /** Elements whose body the tokenizer reads whole, so they never take a closer. */
    public const ATOMIC = ['SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'XMP', 'IFRAME', 'NOEMBED', 'NOFRAMES'];

    private bool $selfClosing = false;
    /** @var array<string, string|true> */
    private array $attributes = [];
    private string $text = '';

    private function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $namespace,
        public readonly ?int $offset,
    ) {
    }

    /**
     * An element; the attributes kept are the ones the builder compares.
     *
     * @param array<string, string|true> $attributes
     */
    public static function element(string $name, string $namespace, ?int $offset, array $attributes = []): self
    {
        $node = new self('#tag', $name, $namespace, $offset);
        $node->attributes = $attributes;
        return $node;
    }

    /** A leaf: text, a comment, a doctype, a CDATA section, a processing instruction. */
    public static function leaf(string $type, string $name, string $namespace, ?int $offset, string $text): self
    {
        $node = new self($type, $name, $namespace, $offset);
        $node->text = $text;
        return $node;
    }

    /** The same element, marked as written with "/>". */
    public function selfClosed(): self
    {
        $copy = clone $this;
        $copy->selfClosing = true;
        return $copy;
    }

    /** Whether the element was written with "/>". */
    public function isSelfClosing(): bool
    {
        return $this->selfClosing;
    }

    /** A leaf's text. */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * The attributes kept for comparison.
     *
     * @return array<string, string|true>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /** Whether this is an HTML element with one of the names. */
    public function isHtml(string ...$names): bool
    {
        return $this->type === '#tag' && $this->namespace === 'html' && in_array($this->name, $names, true);
    }

    /** Whether this is an element of the namespace with one of the names. */
    public function isIn(string $namespace, string ...$names): bool
    {
        return $this->type === '#tag' && $this->namespace === $namespace && in_array($this->name, $names, true);
    }

    /** Whether the element is closed by a closer of its own: not a leaf, not void, not read whole, not self-closed foreign content. */
    public function expectsCloser(): bool
    {
        if ($this->type !== '#tag') {
            return false;
        }
        if ($this->namespace === 'html') {
            return !in_array($this->name, self::VOID, true) && !in_array($this->name, self::ATOMIC, true);
        }
        return !$this->selfClosing;
    }

    /** Whether this element is an HTML integration point (SVG foreignObject, desc, title; MathML annotation-xml for HTML). */
    public function isHtmlIntegrationPoint(): bool
    {
        if ($this->isIn('svg', 'FOREIGNOBJECT', 'DESC', 'TITLE')) {
            return true;
        }
        $encoding = strtolower((string) ($this->attributes['encoding'] ?? ''));
        return $this->isIn('math', 'ANNOTATION-XML') && in_array($encoding, ['text/html', 'application/xhtml+xml'], true);
    }

    /** Whether this element is a MathML text integration point. */
    public function isMathTextIntegrationPoint(): bool
    {
        return $this->isIn('math', 'MI', 'MO', 'MN', 'MS', 'MTEXT');
    }
}
