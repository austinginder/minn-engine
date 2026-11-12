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

    /**
     * What a request that draws content but is no themed page (REST) keeps:
     * the blocks and what previews them, and everything that decides what
     * content shows (block gates and filters, shortcodes, content filters),
     * so content.rendered hides what the page hides. The page's own parts
     * (head, footer, classes, routes) stay with the themed front.
     */
    public function onlyBlocks(): self
    {
        return new self($this->blockGates, $this->blockFilters, $this->shortcodes, $this->contentFilters, [], [], [], [], null, [], $this->blocks, $this->editorStyles);
    }
}
