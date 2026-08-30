<?php

declare(strict_types=1);

namespace Minn\Front;

/** One block of the parsed lexicon markdown. */
final readonly class LexiconBlock
{
    /**
     * @param list<string> $items
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function __construct(
        public LexiconKind $kind,
        public int $level = 0,
        public string $text = '',
        public string $id = '',
        public ?string $status = null,
        public array $items = [],
        public array $headers = [],
        public array $rows = [],
        public bool $filterable = false,
        public bool $ordered = false,
    ) {
    }
}
