<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\Parser;
use Minn\Blocks\Renderer;
use Minn\Db;
use Minn\Blocks\RenderState;
use Minn\Runtime\BlockFilters;
use Minn\Runtime\BlockHooks;
use Minn\Runtime\Handoff;

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
        // The cycle guard and the render's budget are asked before the pattern is read, so a refused one costs no query.
        if (!$renderer->state()->enter('block:' . $ref)) {
            return '';
        }
        $row = $this->db->row(
            "SELECT post_content FROM {$this->db->table('posts')} WHERE ID = ? AND post_type = 'wp_block' AND post_status = 'publish' LIMIT 1",
            [$ref],
        );
        if ($row === null) {
            $renderer->state()->leave('block:' . $ref);
            return '';
        }
        $blocks = Parser::named(BlockHooks::forPost((string) $row['post_content'], $ref));
        // As the reference's does, the block takes the pattern's blocks as its own inner blocks and renders them
        // through render_block once before its own render ends there too (probe block-instances).
        $instance = $renderer->ownInstance();
        if ($instance !== null) {
            Handoff::adoptBlock($instance, ['innerBlocks' => array_map(static fn (Block $inner): array => $inner->toArray(), $blocks)] + (array) $instance->parsed_block, (array) $instance->context);
        }
        // Its overrides reach the blocks inside, for their pattern-overrides bindings.
        $out = $renderer->providing(['pattern/overrides' => $block->attr('content', [])], static fn (): string => $renderer->renderBlocks($blocks));
        $renderer->state()->leave('block:' . $ref);
        return $instance === null ? $out : BlockFilters::rendered($out, (array) $instance->parsed_block, $instance);
    }
}
