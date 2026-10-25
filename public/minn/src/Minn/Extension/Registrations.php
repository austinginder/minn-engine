<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;

/** Everything the extensions registered for this request, handed from Seams to the runner once. */
final readonly class Registrations
{
    /**
     * @param list<Closure> $blockGates
     * @param list<Closure> $blockFilters
     * @param array<string, Closure> $shortcodes
     * @param list<Closure> $contentFilters
     * @param list<Closure> $head
     * @param list<Closure> $footer
     * @param list<string> $bodyClasses
     * @param list<Closure> $documentFilters
     * @param list<object> $routes controllers with #[Route] methods
     * @param array<string, array{metadata: array<string, mixed>, render: Closure}> $blocks
     * @param list<string> $editorStyles
     * @param list<object> $rest controllers with #[Route] methods for the REST API
     */
    public function __construct(
        public array $blockGates,
        public array $blockFilters,
        public array $shortcodes,
        public array $contentFilters,
        public array $head,
        public array $footer,
        public array $bodyClasses,
        public array $documentFilters,
        public ?Closure $title,
        public array $routes = [],
        public array $blocks = [],
        public array $editorStyles = [],
        public array $rest = [],
    ) {
    }

    /** Only the blocks and what previews them, for a request that draws content but is no themed page (REST). */
    public function onlyBlocks(): self
    {
        return new self([], [], [], [], [], [], [], [], null, [], $this->blocks, $this->editorStyles);
    }
}
