<?php

use Minn\Widgets\WidgetForms;
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

    /** The settings form (Widgets\WidgetForms::block). */
    public function form($instance)
    {
        echo WidgetForms::block($this, (array) $instance);
    }

    public function update($new_instance, $old_instance)
    {
        // A user who may not post unfiltered HTML has the content kses'd (probe widget-factory).
        $instance = (array) $old_instance;
        $content = (string) ($new_instance['content'] ?? '');
        $instance['content'] = current_user_can('unfiltered_html') ? $content : wp_kses_post($content);
        return $instance;
    }

    /** A block widget is never wide in the Customizer; any other widget keeps its answer (probe widget-methods). */
    public function set_is_wide_widget_in_customizer($is_wide, $widget_id)
    {
        return str_starts_with((string) $widget_id, 'block-') ? false : $is_wide;
    }

    private function dynamic_classname(string $content): string
    {
        $blocks = parse_blocks($content);
        return (string) apply_filters('widget_block_dynamic_classname', BlockWidget::classNameFor($blocks), $blocks[0]['blockName'] ?? null);
    }
}
