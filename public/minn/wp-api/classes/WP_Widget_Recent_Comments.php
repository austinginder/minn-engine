<?php

use Minn\Widgets\WidgetForms;
/** The recent comments widget: the newest approved comments on published posts, and the head style it prints when active. */
class WP_Widget_Recent_Comments extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('recent-comments', 'Recent Comments', ['classname' => 'widget_recent_comments', 'description' => 'Your site&#8217;s most recent comments.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
        if (is_active_widget(false, false, $this->id_base) || is_customize_preview()) {
            add_action('wp_head', [$this, 'recent_comments_style']);
        }
    }

    public function recent_comments_style()
    {
        if (!current_theme_supports('widgets') || !apply_filters('show_recent_comments_widget_style', true, $this->id_base)) {
            return;
        }
        $type = current_theme_supports('html5', 'style') ? '' : ' type="text/css"';
        echo "<style{$type}>.recentcomments a{display:inline !important;padding:0 !important;margin:0 !important;}</style>\n";
    }

    public function widget($args, $instance)
    {
        static $first = true;
        $title = apply_filters('widget_title', empty($instance['title']) ? 'Recent Comments' : $instance['title'], $instance, $this->id_base);
        $number = empty($instance['number']) ? 5 : absint($instance['number']);
        $number = $number > 0 ? $number : 5;
        $comments = get_comments(apply_filters('widget_comments_args', ['number' => $number, 'status' => 'approve', 'post_status' => 'publish'], $instance));
        $id = $first ? 'recentcomments' : "recentcomments-{$this->number}";
        $first = false;
        $output = '<ul id="' . $id . '">';
        foreach (is_array($comments) ? $comments : [] as $comment) {
            $output .= '<li class="recentcomments"><span class="comment-author-link">' . get_comment_author_link($comment) . '</span> on <a href="' . esc_url(get_comment_link($comment)) . '">' . get_the_title($comment->comment_post_ID) . '</a></li>';
        }
        $output .= '</ul>';
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo $output . $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $instance['number'] = absint($new_instance['number'] ?? 5);
        return $instance;
    }

    public function flush_widget_cache()
    {
    }

    /** The settings form (Widgets\WidgetForms::recentComments). */
    public function form($instance)
    {
        echo WidgetForms::recentComments($this, (array) $instance);
    }
}
