<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Which posts count as a post's neighbours (get_adjacent_post, probe
 * link-template-tags): published ones, and private ones the reader may see
 * (all of them, or only their own); kept to posts sharing one of $sameTerms
 * when given, and away from any post in $excludedTerms, both in $taxonomy.
 */
final readonly class AdjacentScope
{
    /**
     * @param 'all'|'own'|'none' $private
     * @param list<int> $sameTerms
     * @param list<int> $excludedTerms
     */
    public function __construct(
        public string $private = 'none',
        public int $reader = 0,
        public array $sameTerms = [],
        public array $excludedTerms = [],
        public string $taxonomy = 'category',
    ) {
    }
}
