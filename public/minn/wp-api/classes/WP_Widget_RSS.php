<?php
/**
 * The RSS widget: a feed's title line with its icon (the feed's own title
 * and link when the instance gives no title), and the entries
 * wp_widget_rss_output prints.
 */
class WP_Widget_RSS extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('rss', 'RSS', ['classname' => 'widget_rss', 'description' => 'Entries from any RSS or Atom feed.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true], ['width' => 400, 'height' => 200]);
    }

    public function widget($args, $instance)
    {
        if (!empty($instance['error'])) {
            return;
        }
        $url = strip_tags((string) ($instance['url'] ?? ''));
        if (str_starts_with($url, 'http://feeds.feedburner.com/')) {
            $url = 'http://feeds.feedburner.com/' . substr($url, 28);
        }
        if ($url === '') {
            return;
        }
        $rss = fetch_feed($url);
        $title = (string) ($instance['title'] ?? '');
        $link = '';
        if (!is_wp_error($rss)) {
            $link = strip_tags((string) $rss->get_permalink());
            if ($title === '') {
                $title = strip_tags((string) $rss->get_title());
            }
        }
        if (empty($title)) {
            $title = __('Unknown Feed');
        }
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        if ($title) {
            $icon = '<img class="rss-widget-icon" style="border:0" width="14" height="14" src="' . esc_url(includes_url('images/rss.png')) . '" alt="' . esc_attr($title === 'Unknown Feed' ? 'RSS feed' : 'RSS feed: ' . $title) . '" loading="lazy" />';
            $title = '<a class="rsswidget rss-widget-feed" href="' . esc_url(strip_tags($url)) . '">' . $icon . '</a> <a class="rsswidget rss-widget-title" href="' . esc_url($link) . '">' . esc_html($title) . '</a>';
        }
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        wp_widget_rss_output($rss, $instance);
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $testurl = isset($new_instance['url']) && (!isset($old_instance['url']) || $new_instance['url'] !== $old_instance['url']);
        return wp_widget_rss_process($new_instance, $testurl);
    }

    public function form($instance)
    {
        wp_widget_rss_form($instance);
    }
}
