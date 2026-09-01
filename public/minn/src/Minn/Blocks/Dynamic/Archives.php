<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use DateTimeImmutable;
use DateTimeZone;
use Minn\Blocks\Block;
use Minn\Db;
use Minn\Front\Permalinks;

/** core/archives: the months that have published posts, newest first. */
final readonly class Archives
{
    public function __construct(
        private Db $db,
        private Permalinks $permalinks,
    ) {
    }

    /** The block's HTML. */
    public function render(Block $block, \Minn\Blocks\Renderer $renderer): string
    {
        $viewing = $renderer->context()->resolution->date;
        $months = $this->db->rows(
            "SELECT YEAR(post_date) AS year, MONTH(post_date) AS month, COUNT(*) AS posts
             FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'
             GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date DESC",
        );
        $items = '';
        foreach ($months as $row) {
            $year = (int) $row['year'];
            $month = (int) $row['month'];
            $label = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new DateTimeZone('UTC')))->format('F Y');
            $current = $viewing !== null && $viewing[0] === $year && $viewing[1] === $month && $viewing[2] === null ? ' aria-current="page"' : '';
            $items .= "\t<li><a href='" . $this->permalinks->forDate($year, $month) . "'" . $current . '>' . $label . "</a></li>\n";
        }
        return '<ul class="wp-block-archives-list wp-block-archives">' . $items . '</ul>';
    }
}
