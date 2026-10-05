<?php

declare(strict_types=1);

namespace Minn\Html;

/**
 * A tag processor's next_tag() query: a tag name (any case), a class, the
 * nth match to stop at, and whether closers count.
 */
final readonly class TagQuery
{
    private function __construct(
        public ?string $tagName,
        public ?string $className,
        public int $offset,
        public string $closers,
    ) {
    }

    /**
     * The query read from the array a caller passes.
     *
     * @param array{tag_name?: ?string, class_name?: ?string, match_offset?: int, tag_closers?: string} $query
     */
    public static function from(array $query): self
    {
        return new self(
            isset($query['tag_name']) ? strtoupper((string) $query['tag_name']) : null,
            isset($query['class_name']) ? (string) $query['class_name'] : null,
            max(0, (int) ($query['match_offset'] ?? 1)),
            ($query['tag_closers'] ?? 'skip') === 'visit' ? 'visit' : 'skip',
        );
    }

    /** Whether a tag (by name, closer or not, and a class test) matches, before the offset is counted. */
    public function matches(string $name, int $closer, callable $hasClass): bool
    {
        if ($closer === 1 && $this->closers !== 'visit') {
            return false;
        }
        if ($this->tagName !== null && $this->tagName !== $name) {
            return false;
        }
        return $this->className === null || $hasClass($this->className);
    }
}
