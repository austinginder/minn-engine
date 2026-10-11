<?php

/**
 * The navigation block's renderer class. Its render() is the block's own
 * rendering, the engine's (render_block_core_navigation): called directly,
 * outside a block render, the wrapper gets no block support classes
 * (probe loose-classes).
 */
class WP_Navigation_Block_Renderer
{
    public static function render($attributes, $content, $block)
    {
        return render_block_core_navigation($attributes, $content, $block);
    }
}
