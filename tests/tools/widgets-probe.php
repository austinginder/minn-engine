<?php
/**
 * The default widgets as the reference renders them through the_widget(),
 * with the template functions behind them. Runs unchanged on the reference
 * (wp eval-file) and on the engine (tests/api.test.php).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$args = ['before_widget' => '<w class="%s">', 'after_widget' => '</w>', 'before_title' => '<t>', 'after_title' => '</t>'];
$render = static function (string $widget, array $instance) use ($args): string {
    ob_start();
    the_widget($widget, $instance, $args);
    return (string) ob_get_clean();
};
global $wp_widget_factory;
$say('factory', array_keys($wp_widget_factory->widgets));
$say('factory options', array_map(static fn ($w) => [$w->name, $w->id_base, $w->widget_options, $w->control_options], $wp_widget_factory->widgets));

$say('pages default', $render('WP_Widget_Pages', ['title' => 'Pages']));
$say('pages by title excluding', $render('WP_Widget_Pages', ['sortby' => 'post_title', 'exclude' => '6']));
$say('pages by id', $render('WP_Widget_Pages', ['sortby' => 'ID']));
$say('wp_list_pages', [wp_list_pages(['echo' => false]), wp_list_pages(['echo' => false, 'title_li' => '', 'depth' => 1]), wp_list_pages(['echo' => false, 'title_li' => '', 'include' => '2,6', 'sort_column' => 'post_title']), wp_list_pages(['echo' => false, 'title_li' => 'Mine', 'child_of' => 2])]);
$say('wp_dropdown_pages', [wp_dropdown_pages(['echo' => false]), wp_dropdown_pages(['echo' => false, 'selected' => 6, 'show_option_none' => 'None', 'name' => 'pg', 'id' => 'pg-id', 'class' => 'c'])]);

$say('archives default', $render('WP_Widget_Archives', ['title' => 'Archives']));
$say('archives dropdown count', $render('WP_Widget_Archives', ['dropdown' => 1, 'count' => 1]));
$say('archives list count', $render('WP_Widget_Archives', ['count' => 1]));
$say('wp_get_archives', [wp_get_archives(['echo' => false]), wp_get_archives(['echo' => false, 'type' => 'yearly', 'show_post_count' => true]), wp_get_archives(['echo' => false, 'type' => 'daily']), wp_get_archives(['echo' => false, 'type' => 'postbypost', 'limit' => 3]), wp_get_archives(['echo' => false, 'type' => 'alpha', 'limit' => 2]), wp_get_archives(['echo' => false, 'format' => 'option']), wp_get_archives(['echo' => false, 'format' => 'link']), wp_get_archives(['echo' => false, 'format' => 'custom', 'before' => '[', 'after' => ']']), wp_get_archives(['echo' => false, 'type' => 'weekly'])]);

$say('categories default', $render('WP_Widget_Categories', ['title' => 'Cats']));
$say('categories dropdown', $render('WP_Widget_Categories', ['dropdown' => 1, 'count' => 1]));
$say('categories count hierarchical', $render('WP_Widget_Categories', ['count' => 1, 'hierarchical' => 1]));

$say('meta', $render('WP_Widget_Meta', ['title' => 'Meta']));
$say('meta untitled', $render('WP_Widget_Meta', []));
$say('search', $render('WP_Widget_Search', ['title' => 'Find']));
$say('text visual', $render('WP_Widget_Text', ['title' => 'T', 'text' => "Hello <b>x</b>\n\nPara two\nline [gallery]", 'filter' => true, 'visual' => true]));
$say('text legacy', $render('WP_Widget_Text', ['title' => 'T', 'text' => "Hello <b>x</b>\n\nPara two", 'filter' => false]));
$say('text legacy filtered', $render('WP_Widget_Text', ['text' => "Hello\n\nPara two", 'filter' => true]));
$say('recent posts', $render('WP_Widget_Recent_Posts', ['title' => 'Recent', 'number' => 3, 'show_date' => 1]));
$say('recent posts default', $render('WP_Widget_Recent_Posts', []));
$say('recent comments', $render('WP_Widget_Recent_Comments', ['title' => 'Comments', 'number' => 3]));
$say('recent comments default', $render('WP_Widget_Recent_Comments', []));
$say('tag cloud', $render('WP_Widget_Tag_Cloud', ['title' => 'Tags']));
$say('tag cloud count categories', $render('WP_Widget_Tag_Cloud', ['taxonomy' => 'category', 'count' => 1]));
$menus = wp_get_nav_menus();
$say('nav menu', $menus === [] ? 'no menu' : $render('WP_Nav_Menu_Widget', ['title' => 'Menu', 'nav_menu' => $menus[0]->term_id]));
$say('nav menu missing', $render('WP_Nav_Menu_Widget', ['nav_menu' => 999999]));
$say('custom html', $render('WP_Widget_Custom_HTML', ['title' => 'H', 'content' => '<p>raw & <script>x</script></p>']));
$say('rss error', $render('WP_Widget_RSS', ['title' => 'Feed', 'url' => 'http://nonexistent.invalid/feed/', 'items' => 3]));
$say('rss no url', $render('WP_Widget_RSS', ['items' => 3]));
$attachments = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$aid = $attachments === [] ? 0 : $attachments[0]->ID;
$say('media image attachment', $render('WP_Widget_Media_Image', ['title' => 'Img', 'attachment_id' => $aid, 'size' => 'thumbnail', 'link_type' => 'file', 'caption' => 'A caption']));
$say('media image url', $render('WP_Widget_Media_Image', ['url' => 'https://minn.localhost/x.png', 'alt' => 'alt"t', 'width' => 10, 'height' => 5, 'link_type' => 'custom', 'link_url' => 'https://minn.localhost/go', 'link_target_blank' => true, 'image_classes' => 'ic', 'image_title' => 'ti']));
$say('media image empty', $render('WP_Widget_Media_Image', []));
$say('media audio url', $render('WP_Widget_Media_Audio', ['title' => 'Au', 'url' => 'https://minn.localhost/a.mp3', 'loop' => true, 'preload' => 'metadata']));
$say('media video url', $render('WP_Widget_Media_Video', ['title' => 'Vid', 'url' => 'https://minn.localhost/v.mp4', 'loop' => true, 'preload' => 'auto']));
$say('media video youtube', $render('WP_Widget_Media_Video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']));
$say('media gallery', $render('WP_Widget_Media_Gallery', ['title' => 'Gal', 'ids' => [$aid], 'columns' => 2, 'size' => 'thumbnail', 'link_type' => 'none']));
$say('media gallery empty', $render('WP_Widget_Media_Gallery', []));
$say('media schema keys', array_map(static fn ($w) => array_keys($w->get_instance_schema()), array_filter($wp_widget_factory->widgets, static fn ($w) => $w instanceof WP_Widget_Media)));
$say('second instances', [$render('WP_Widget_Categories', ['dropdown' => 1]), $render('WP_Widget_Archives', ['dropdown' => 1]), $render('WP_Widget_Recent_Comments', ['number' => 1])]);
$say('untitled', [$render('WP_Widget_Search', []), $render('WP_Widget_Tag_Cloud', []), $render('WP_Widget_Text', ['text' => 'x', 'visual' => true, 'filter' => true]), $render('WP_Widget_Pages', []), $render('WP_Widget_Archives', [])]);
$say('video shortcode direct', [wp_video_shortcode(['src' => 'https://minn.localhost/v.mp4']), wp_video_shortcode(['src' => 'https://minn.localhost/v.mp4', 'loop' => 'on', 'preload' => 'auto'])]);
$say('rss output', (static function () { ob_start(); wp_widget_rss_output('https://minn.localhost/feed/', ['items' => 2, 'show_summary' => 1, 'show_author' => 1, 'show_date' => 1]); $a = ob_get_clean(); ob_start(); wp_widget_rss_output(fetch_feed('https://minn.localhost/feed/'), ['items' => 1]); $b = ob_get_clean(); return [$a, $b]; })());
$say('rss widget live', $render('WP_Widget_RSS', ['title' => 'Live', 'url' => 'https://minn.localhost/feed/', 'items' => 1, 'show_date' => 1]));
$say('text update', [(new WP_Widget_Text())->update(['title' => ' <b>T</b>', 'text' => '<script>x</script><p>ok</p>', 'filter' => 'on', 'visual' => 'on'], []), (new WP_Widget_Text())->update(['title' => 'T', 'text' => "a\nb", 'filter' => false], ['visual' => false])]);
$say('media updates', [(new WP_Widget_Media_Image())->update(['attachment_id' => '607x', 'url' => 'javascript:x', 'size' => 'bogus', 'width' => '-3', 'link_type' => 'nope', 'link_target_blank' => 'yes', 'image_classes' => 'a <b', 'alt' => '<i>alt</i>', 'caption' => '<em>c</em>', 'title' => '<b>t</b>'], ['title' => 'old', 'size' => 'large']), (new WP_Widget_Media_Video())->update(['url' => 'https://minn.localhost/v.mp4', 'loop' => '1', 'preload' => 'bogus', 'content' => '<track src="x">'], []), (new WP_Widget_Media_Gallery())->update(['ids' => '607,abc,0', 'columns' => '12', 'link_type' => 'post', 'orderby_random' => 'yes'], []), (new WP_Widget_Media_Audio())->update(['url' => 'https://minn.localhost/a.mp3', 'loop' => '', 'preload' => 'auto'], [])]);
function minn_probe_inline_script(): string { ob_start(); wp_print_inline_script_tag('b;'); return (string) ob_get_clean(); }
$say('inline script sourceURL', [(static function () { ob_start(); wp_print_inline_script_tag('a;'); return (string) ob_get_clean(); })(), minn_probe_inline_script(), (static function () { ob_start(); (new WP_Widget_Meta())->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], []); ob_get_clean(); ob_start(); wp_print_inline_script_tag('c;'); return (string) ob_get_clean(); })()]);
$say('recent comments style', (static function () { update_option('widget_recent-comments', [2 => ['title' => 'x'], '_multiwidget' => 1]); $GLOBALS['wp_widget_factory']->widgets['WP_Widget_Recent_Comments']->_register(); add_filter('sidebars_widgets', $f = static fn () => ['sidebar-1' => ['recent-comments-2'], 'wp_inactive_widgets' => []]); ob_start(); (new WP_Widget_Recent_Comments())->recent_comments_style(); $a = ob_get_clean(); remove_filter('sidebars_widgets', $f); ob_start(); (new WP_Widget_Recent_Comments())->recent_comments_style(); $b = ob_get_clean(); delete_option('widget_recent-comments'); return [$a, $b]; })());
$say('text video', $render('WP_Widget_Text', ['text' => '<video width="640" height="360" src="v.mp4"></video><p>a</p><iframe src="x" width="10"></iframe>', 'visual' => true, 'filter' => true]));
$say('caption shortcode', [img_caption_shortcode(['width' => 100, 'caption' => 'c <b>b</b>', 'align' => 'alignright', 'id' => 'x'], '<img src="a">'), img_caption_shortcode(['width' => 0, 'caption' => 'c'], '<img src="a">'), img_caption_shortcode(['width' => 40, 'caption' => ''], '<img src="a">')]);
$say('audio shortcode loop', [wp_audio_shortcode(['src' => 'https://minn.localhost/a.mp3', 'loop' => true, 'preload' => 'none']), wp_audio_shortcode(['src' => 'https://minn.localhost/a.mp3', 'loop' => false])]);
$say('list pages current', (static function () { $GLOBALS['wp_query'] = new WP_Query(['page_id' => 6]); $GLOBALS['wp_query']->get_posts(); $out = wp_list_pages(['echo' => false, 'title_li' => '']); $out2 = wp_list_pages(['echo' => false, 'title_li' => '', 'item_spacing' => 'discard', 'link_before' => '<s>', 'link_after' => '</s>']); $out3 = wp_list_pages(['echo' => false, 'title_li' => '', 'depth' => -1]); wp_reset_query(); return [$out, $out2, $out3]; })());
$say('search forms', [get_search_form(['echo' => false, 'aria_label' => 'Site search']), (static function () { $had = current_theme_supports('html5', 'search-form'); remove_theme_support('html5'); $form = get_search_form(['echo' => false]); if ($had) { add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']); } return $form; })()]);
$say('updates', [
    (new WP_Widget_Pages())->update(['title' => ' <b>P</b> ', 'sortby' => 'bogus', 'exclude' => ' 1, 2 '], []),
    (new WP_Widget_Archives())->update(['title' => 'A', 'count' => 'yes', 'dropdown' => ''], ['x' => 1]),
    (new WP_Widget_Categories())->update(['title' => 'C', 'count' => '1', 'hierarchical' => '0', 'dropdown' => 'on'], []),
    (new WP_Widget_Meta())->update(['title' => '<i>M</i>'], []),
    (new WP_Widget_Search())->update(['title' => 'S<'], []),
    (new WP_Widget_Recent_Posts())->update(['title' => 'R', 'number' => '5x', 'show_date' => '1'], []),
    (new WP_Widget_Recent_Comments())->update(['title' => 'RC', 'number' => 'abc'], []),
    (new WP_Widget_Tag_Cloud())->update(['title' => 'T', 'count' => '1', 'taxonomy' => 'category'], []),
    (new WP_Nav_Menu_Widget())->update(['title' => 'N', 'nav_menu' => '3'], []),
    (new WP_Widget_RSS())->update(['title' => 'F', 'url' => 'http://nonexistent.invalid/feed/', 'items' => '30', 'show_summary' => '1'], []),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
