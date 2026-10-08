<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\Parser;
use Minn\Blocks\Renderer;
use Minn\Db;
use Minn\Blocks\RenderState;

/** core/block: a synced pattern, rendered from the wp_block post it references. */
final readonly class SyncedPattern
{
    public function __construct(private Db $db)
    {
    }

    /** The block's HTML. */
    public function render(Block $block, Renderer $renderer): string
    {
        $ref = (int) $block->attr('ref', 0);
        if ($ref <= 0) {
            return '';
        }
        $row = $this->db->row(
            "SELECT post_content FROM {$this->db->table('posts')} WHERE ID = ? AND post_type = 'wp_block' AND post_status = 'publish' LIMIT 1",
            [$ref],
        );
        if ($row === null || !$renderer->state()->enter('block:' . $ref)) {
            return '';
        }
        // Its overrides reach the blocks inside, for their pattern-overrides bindings.
        $out = $renderer->providing(['pattern/overrides' => $block->attr('content', [])], static fn (): string => $renderer->renderBlocks(Parser::parse((string) $row['post_content'])));
        $renderer->state()->leave('block:' . $ref);
        return $out;
    }
}
