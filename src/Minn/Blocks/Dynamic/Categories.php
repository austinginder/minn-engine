<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Support\Html;

/** core/categories: the non-empty categories as a list, by name. */
final readonly class Categories
{
    public function __construct(
        private Db $db,
        private Permalinks $permalinks,
    ) {
    }

    public function render(Block $block): string
    {
        $terms = $this->db->rows(
            "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'category' AND tt.count > 0 ORDER BY t.name ASC",
        );
        $items = '';
        foreach ($terms as $term) {
            $items .= "\t" . '<li class="cat-item cat-item-' . (int) $term['term_id'] . '"><a href="' . Html::attr($this->permalinks->forTerm($term)) . '">' . $term['name'] . "</a>\n</li>\n";
        }
        return '<ul class="wp-block-categories-list wp-block-categories-taxonomy-category wp-block-categories">' . $items . '</ul>';
    }
}
