<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Content\TermRecord;
use Minn\Blocks\Block;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Support\Html;

/**
 * core/tag-cloud: non-empty tags by name, sized from 8pt to 22pt in
 * proportion to their counts (every tag at 8pt when the counts are equal).
 */
final readonly class TagCloud
{
    public function __construct(
        private Db $db,
        private Permalinks $permalinks,
    ) {
    }

    /** The block's HTML. */
    public function render(Block $block): string
    {
        $tags = TermRecord::fromRows($this->db->rows(
            "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'post_tag' AND tt.count > 0 ORDER BY t.name ASC",
        ));
        if ($tags === []) {
            return '';
        }
        $counts = array_map(static fn (TermRecord $t) => $t->count, $tags);
        $min = min($counts);
        $max = max($counts);
        $spread = max($max - $min, 1);
        $links = [];
        foreach ($tags as $position => $tag) {
            $count = (int) $tag['count'];
            $size = 8 + ($count - $min) * (22 - 8) / $spread;
            $size = $max === $min ? 8 : $size;
            $links[] = '<a href="' . Html::attr($this->permalinks->forTerm($tag)) . '" class="tag-cloud-link tag-link-' . (int) $tag['term_id']
                . ' tag-link-position-' . ($position + 1) . '" style="font-size: ' . rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.') . 'pt;"'
                . ' aria-label="' . Html::attr($tag['name'] . ' (' . $count . ($count === 1 ? ' item' : ' items') . ')') . '">' . Html::esc((string) $tag['name']) . '</a>';
        }
        return '<p class="wp-block-tag-cloud">' . implode("\n", $links) . '</p>';
    }
}
