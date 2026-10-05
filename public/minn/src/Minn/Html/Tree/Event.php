<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * One step of the walk the HTML processor reports: a node opened (pushed)
 * or closed (popped), the breadcrumbs at that moment, and the offset of
 * the token behind it when the document wrote it (null when implied).
 */
final readonly class Event
{
    /** @param list<string> $breadcrumbs */
    public function __construct(
        public string $op,
        public Node $node,
        public array $breadcrumbs,
        public ?int $offset,
    ) {
    }

    /** Whether this event closes its node. */
    public function isCloser(): bool
    {
        return $this->op === 'pop';
    }
}
