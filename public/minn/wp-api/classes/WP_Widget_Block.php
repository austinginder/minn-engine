<?php

use Minn\Runtime\BlockWidget;

/**
 * The block widget: a widget whose whole instance is block markup. Every
 * widget the block editor saves is one of these, so a site whose sidebar
 * was filled in a modern WordPress has nothing else in it.
 *
 * The wrapper gains a second class named after the FIRST block in the
 * content (captured: core/search is widget_search, core/paragraph is
 * widget_text, core/latest-posts is widget_recent_entries), which is what
 * classic themes style against; a block with no mapping adds nothing.
 */
class WP_Widget_Block extends WP_Widget
{
    public $default_instance = ['content' => ''];

    public function __construct()
    {
        parent::__construct(
            'block',
            'Block',
            ['classname' => 'widget_block', 'description' => 'A widget containing a block.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true],
            ['width' => 400, 'height' => 350]
        );
    }

    public function widget($args, $instance)
    {
        $instance = wp_parse_args($instance, $this->default_instance);
        $content = (string) $instance['content'];
        echo str_replace(BlockWidget::BASE_CLASS, $this->dynamic_classname($content), (string) ($args['before_widget'] ?? ''));
        echo apply_filters('widget_block_content', $content, $instance, $this);
        echo $args['after_widget'] ?? '';
    }

    public function form($instance)
    {
        return 'noform';
    }

    public function update($new_instance, $old_instance)
    {
        $instance = (array) $old_instance;
        $instance['content'] = (string) ($new_instance['content'] ?? '');
        return $instance;
    }

    private function dynamic_classname(string $content): string
    {
        return BlockWidget::classNameFor(parse_blocks($content));
    }
}
