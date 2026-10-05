<?php
// Feed helpers plugin code calls; the engine renders its own feeds. Reading
// other sites' feeds: fetch_feed() returns the SimplePie-shaped object in
// wp-api/simplepie/, over the engine's own reader in Minn\Feed.

// The SimplePie classes load the first time a plugin or fetch_feed() names
// one, as the reference loads them only when a feed is fetched.
spl_autoload_register(static function (string $class): void {
    if (preg_match('/^(SimplePie(\\\\|_|$)|WP_SimplePie_|WP_Feed_Cache_Transient$)/', ltrim($class, '\\'))) {
        _minn_load_simplepie();
    }
});

/** @internal defines the SimplePie classes, their pre-namespace names and the WordPress classes around them, once */
function _minn_load_simplepie(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    foreach (['Fields', 'Interfaces', 'SimplePie', 'Item', 'Parts', 'Sniffer', 'Wp'] as $file) {
        require_once __DIR__ . '/simplepie/' . $file . '.php';
    }
}

/**
 * A feed (or several, merged) fetched and parsed: the cache lifetime
 * filter sees the URL, wp_feed_options sees the object before it reads,
 * and a failure comes back as a simplepie-error.
 */
function fetch_feed($url)
{
    _minn_load_simplepie();
    $feed = new SimplePie\SimplePie();
    $feed->set_sanitize_class('WP_SimplePie_Sanitize_KSES');
    $feed->set_cache_class('WP_Feed_Cache_Transient');
    $feed->set_file_class('WP_SimplePie_File');
    $feed->set_feed_url($url);
    $feed->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 12 * HOUR_IN_SECONDS, $url));
    do_action_ref_array('wp_feed_options', [&$feed, $url]);
    $feed->init();
    $feed->set_output_encoding(get_bloginfo('charset'));
    if ($feed->error()) {
        return new WP_Error('simplepie-error', $feed->error());
    }
    return $feed;
}

/** The RSS block: a feed's items as a list or grid, with dates, authors and excerpts when asked for. */
function render_block_core_rss($attributes)
{
    $a = (array) $attributes + ['columns' => 2, 'blockLayout' => 'list', 'feedURL' => '', 'itemsToShow' => 5, 'displayExcerpt' => false, 'displayAuthor' => false, 'displayDate' => false, 'excerptLength' => 55, 'openInNewTab' => false, 'rel' => ''];
    $rss = fetch_feed($a['feedURL']);
    if (is_wp_error($rss)) {
        return '<div class="components-placeholder"><div class="notice notice-error"><strong>' . __('RSS Error:') . '</strong> ' . esc_html($rss->get_error_message()) . '</div></div>';
    }
    if (!$rss->get_item_quantity()) {
        return '<div class="components-placeholder"><div class="notice notice-error">' . __('An error has occurred, which probably means the feed is down. Try again later.') . '</div></div>';
    }
    $list = '';
    foreach ($rss->get_items(0, (int) $a['itemsToShow']) as $item) {
        $list .= _minn_rss_block_item($item, $a);
    }
    $classes = $a['blockLayout'] === 'grid' ? ['is-grid', 'columns-' . (int) $a['columns']] : [];
    foreach (['displayDate' => 'has-dates', 'displayAuthor' => 'has-authors', 'displayExcerpt' => 'has-excerpts'] as $flag => $class) {
        if (!empty($a[$flag])) {
            $classes[] = $class;
        }
    }
    return sprintf('<ul %s>%s</ul>', get_block_wrapper_attributes(['class' => implode(' ', $classes)]), $list);
}

/** @internal one item of the RSS block: title (linked when it has a link), then date, author and excerpt */
function _minn_rss_block_item($item, array $a): string
{
    $title = esc_html(trim(strip_tags(html_entity_decode((string) $item->get_title(), ENT_QUOTES, get_option('blog_charset')))));
    $title = $title === '' ? __('(no title)') : $title;
    $link = esc_url((string) $item->get_link());
    if ($link !== '') {
        $rel = trim((string) $a['rel']);
        $title = "<a href='{$link}'" . (!empty($a['openInNewTab']) ? ' target="_blank"' : '') . ($rel !== '' ? ' rel="' . esc_attr($rel) . '"' : '') . ">{$title}</a>";
    }
    $out = "<div class='wp-block-rss__item-title'>{$title}</div>";
    $stamp = $item->get_date('U');
    if (!empty($a['displayDate']) && $stamp) {
        $out .= sprintf('<time datetime="%1$s" class="wp-block-rss__item-publish-date">%2$s</time> ', wp_date('c', $stamp), wp_date(get_option('date_format'), $stamp));
    }
    $name = !empty($a['displayAuthor']) && is_object($item->get_author()) ? (string) $item->get_author()->get_name() : '';
    if ($name !== '') {
        $out .= '<span class="wp-block-rss__item-author">' . sprintf(__('by %s'), esc_html(strip_tags($name))) . '</span>';
    }
    $description = (string) $item->get_description();
    if (!empty($a['displayExcerpt']) && $description !== '') {
        $excerpt = esc_attr(wp_trim_words(html_entity_decode($description, ENT_QUOTES, get_option('blog_charset')), (int) $a['excerptLength'], ' [&hellip;]'));
        $out .= '<div class="wp-block-rss__item-excerpt">' . $excerpt . '</div>';
    }
    return "<li class='wp-block-rss__item'>{$out}</li>";
}

/** @internal core/rss renders through its own function, which fetches with the WordPress HTTP API */
function _minn_register_core_rss_block(): void
{
    $type = WP_Block_Type_Registry::get_instance()->get_registered('core/rss');
    if ($type !== null) {
        $type->render_callback = 'render_block_core_rss';
        _minn_bridge_dynamic_block('core/rss');
    }
}

function feed_content_type($type = '')
{
    if ($type === '') {
        $type = get_default_feed();
    }
    $types = ['rss' => 'application/rss+xml', 'rss2' => 'application/rss+xml', 'rss-http' => 'text/xml', 'atom' => 'application/atom+xml', 'rdf' => 'application/rdf+xml'];
    return apply_filters('feed_content_type', $types[$type] ?? 'application/octet-stream', $type);
}

function get_bloginfo_rss($show = '')
{
    // Observed: the value is HTML-escaped whole, tags included, and typographic quotes stay.
    return apply_filters('get_bloginfo_rss', esc_html(get_bloginfo($show)), $show);
}

function bloginfo_rss($show = '')
{
    echo apply_filters('bloginfo_rss', get_bloginfo_rss($show), $show);
}

function the_permalink_rss()
{
    echo esc_url(apply_filters('the_permalink_rss', get_permalink()));
}

function self_link()
{
    $host = wp_parse_url(home_url());
    echo esc_url(apply_filters('self_link', set_url_scheme('http://' . ($_SERVER['HTTP_HOST'] ?? ($host['host'] ?? '')) . wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))));
}

/** The post title for a feed item, filtered but otherwise plain (probed). */
function get_the_title_rss($post = 0)
{
    return apply_filters('the_title_rss', get_the_title($post), $post);
}

/** The rendered content for a feed item: the full pipeline, CDATA close escaped, then the feed filter. */
function get_the_content_feed($feed_type = null)
{
    if ($feed_type === null) {
        $feed_type = 'rss2';
    }
    $post = get_post();
    if ($post === null) {
        return '';
    }
    $content = \Minn\Theme\ClassicContent::render(Minn\Content\PostRecord::fromRow($post->to_array()), null);
    $content = str_replace(']]>', ']]&gt;', $content);
    return apply_filters('the_content_feed', $content, $feed_type);
}
