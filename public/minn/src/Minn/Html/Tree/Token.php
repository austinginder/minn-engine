<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * A token as the tree builder reads it: a tag (name upper-cased), a run of
 * text (one kind at a time: NUL bytes, white space, or anything else), a
 * comment-like leaf, or the end of the input.
 */
final readonly class Token
{
    public const CLOSER = 1;
    public const SELF_CLOSING = 2;

    public function __construct(
        public string $type,
        public string $name,
        public int $flags,
        public int $offset,
        public string $text = '',
        public string $kind = '',
    ) {
    }

    /** Whether this is a start tag with one of the names (any name when none are given). */
    public function isStart(string ...$names): bool
    {
        return $this->type === '#tag' && ($this->flags & self::CLOSER) === 0 && ($names === [] || in_array($this->name, $names, true));
    }

    /** Whether this is an end tag with one of the names (any name when none are given). */
    public function isEnd(string ...$names): bool
    {
        return $this->type === '#tag' && ($this->flags & self::CLOSER) !== 0 && ($names === [] || in_array($this->name, $names, true));
    }

    /** Whether the tag was written with "/>". */
    public function selfClosing(): bool
    {
        return ($this->flags & self::SELF_CLOSING) !== 0;
    }

    /** Whether this is a run of text of the kind ("null", "whitespace", "generic"). */
    public function isText(string $kind): bool
    {
        return $this->type === '#text' && $this->kind === $kind;
    }

    /** The same token under another name (an "image" start tag read as "img", "</br>" as "<br>"). */
    public function renamed(string $name, int $flags): self
    {
        return new self($this->type, $name, $flags, $this->offset, $this->text, $this->kind);
    }
}
