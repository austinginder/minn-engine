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
    ) {
    }
}
