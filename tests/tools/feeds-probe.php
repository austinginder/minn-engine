<?php
/**
 * fetch_feed and the parsed feed plugins read: the channel, each item's
 * title, link, id, dates, authors, categories, enclosures, description and
 * content, the raw tag arrays and the whole parsed tree, for RSS 2.0, Atom
 * (with relative links), RSS 1.0, Latin-1 and Windows-1252 documents, an
 * HTML page that names its feed, and what a broken document, a non-feed
 * and a missing file give back; then the RSS widget and block that print a
 * feed. The feeds are the files in tests/fixtures/feeds, served from the
 * shared uploads folder by the reference's PHP server (MINN_FEED_BASE);
 * the feed cache is cleared before and after so neither stack reads the
 * other's. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$base = rtrim((string) (getenv('MINN_FEED_BASE') ?: 'https://ref.minn.localhost/wp-content/uploads/minn-feed-probe'), '/');
add_filter('http_request_host_is_external', '__return_true');
$port = (int) parse_url($base, PHP_URL_PORT);
add_filter('http_allowed_safe_ports', static fn ($ports) => $port > 0 ? array_merge((array) $ports, [$port]) : $ports);
// The staged feeds come over a Cove site's local HTTPS, whose certificate authority WordPress's own bundle does not know.
add_filter('http_request_args', static fn (array $args): array => ['sslverify' => false] + $args);
$files = ['rss2.xml', 'atom.xml', 'rdf.xml', 'edge.xml', 'atom-edge.xml', 'latin1.xml', 'tags.xml', 'sort.xml', 'cp1252.xml', 'empty.xml', 'page.html'];
$errors = ['broken.xml', 'notfeed.txt', 'no-such-feed.xml'];
$clear = static function () use ($base, $files, $errors): void {
    foreach ([...$files, ...$errors] as $file) {
        delete_site_transient('feed_' . md5("{$base}/{$file}"));
        delete_site_transient('feed_mod_' . md5("{$base}/{$file}"));
    }
};
$clear();
$plain = static fn ($v) => is_string($v) ? str_replace($base, '{base}', $v) : $v;
$deep = static function ($v) use (&$deep, $plain) {
    return is_array($v) ? array_map($deep, $v) : $plain($v);
};

$describeItem = static function ($item) use ($plain): array {
    $author = $item->get_author();
    $categories = array_map(static fn ($c) => [$c->get_term(), $c->get_label()], (array) $item->get_categories());
    $enclosure = $item->get_enclosure();
    return [
        'title' => $item->get_title(),
        'permalink' => $plain($item->get_permalink()),
        'link' => $plain($item->get_link()),
        'id' => $item->get_id(),
        'date U' => $item->get_date('U'),
        'date Y-m-d H:i:s' => $item->get_date('Y-m-d H:i:s'),
        'gmdate' => $item->get_gmdate('Y-m-d H:i:s'),
        'updated' => $item->get_updated_date('U'),
        'author' => $author ? [$author->get_name(), $author->get_email()] : null,
        'categories' => $categories,
        'enclosure' => $enclosure ? [$plain($enclosure->get_link()), $enclosure->get_type(), $enclosure->get_length()] : null,
        'description' => $item->get_description(),
        'content' => $item->get_content(),
    ];
};
// What plugins read beyond the basics: media thumbnails, every link and
// enclosure, the default date format, the raw tag arrays.
$describeMore = static function ($item) use ($plain, $deep): array {
    $enclosure = $item->get_enclosure();
    $authors = array_map(static fn ($a) => [$a->get_name(), $a->get_email(), $a->get_link()], (array) $item->get_authors());
    $contributors = array_map(static fn ($a) => [$a->get_name(), $a->get_email()], (array) $item->get_contributors());
    $category = $item->get_category();
    return [
        'date' => $item->get_date(),
        'updated gmdate' => $item->get_updated_gmdate('Y-m-d H:i:s'),
        'thumbnail' => $item->get_thumbnail(),
        'links' => $deep((array) $item->get_links()),
        'related links' => $item->get_links('related'),
        'authors' => $authors,
        'contributors' => $contributors,
        'category' => $category ? [$category->get_term(), $category->get_scheme(), $category->get_label(), $category->get_type()] : null,
        'category types' => array_map(static fn ($c) => [$c->get_type(), $c->get_label(true)], (array) $item->get_categories()),
        'enclosures' => count((array) $item->get_enclosures()),
        'enclosure detail' => $enclosure ? [$enclosure->get_medium(), $enclosure->get_width(), $enclosure->get_height(), $enclosure->get_thumbnail(), $enclosure->get_extension(), $enclosure->get_real_type(), $enclosure->get_size(), $enclosure->get_title(), $enclosure->get_description(), $enclosure->get_duration()] : null,
        'media thumbnail tag' => $item->get_item_tags(SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail'),
        'title tag' => $item->get_item_tags('', 'title'),
        'atom title tag' => $item->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_10, 'title'),
        'id hashed' => $item->get_id(true),
        'description only' => $item->get_description(true),
        'content only' => $item->get_content(true),
        'copyright' => $item->get_copyright(),
        'base' => $plain($item->get_base()),
        'source' => $item->get_source() ? get_class($item->get_source()) : null,
    ];
};
// The parsed tree, one row per element: path, text, attributes, xml:base, xml:lang.
$tree = static function (array $child, string $path = '') use (&$tree, $plain, $deep): array {
    $rows = [];
    foreach ($child as $ns => $tags) {
        foreach ($tags as $tag => $nodes) {
            foreach ($nodes as $i => $node) {
                $here = $path . '/' . ($ns === '' ? '' : '{' . $ns . '}') . $tag . "[{$i}]";
                $rows[] = [$here, $node['data'] ?? null, $deep($node['attribs'] ?? null), $plain($node['xml_base'] ?? null), $node['xml_base_explicit'] ?? null, $node['xml_lang'] ?? null];
                if (isset($node['child'])) {
                    array_push($rows, ...$tree($node['child'], $here));
                }
            }
        }
    }
    return $rows;
};

$options = [];
add_action('wp_feed_options', static function ($feed, $url) use (&$options, $plain): void {
    $options[] = [get_class($feed), $plain(is_array($url) ? implode(' ', $url) : $url)];
}, 10, 2);
$lifetimes = [];
add_filter('wp_feed_cache_transient_lifetime', static function ($lifetime, $url = null) use (&$lifetimes, $plain, $base) {
    $named = null;
    foreach (['rss2.xml', 'atom.xml', 'rdf.xml', 'edge.xml', 'atom-edge.xml', 'latin1.xml', 'tags.xml', 'sort.xml', 'cp1252.xml', 'empty.xml', 'page.html'] as $file) {
        if ($url === md5("{$base}/{$file}")) {
            $named = "md5({base}/{$file})";
        }
    }
    $lifetimes[] = [$lifetime, $named ?? $plain($url)];
    return $lifetime;
}, 10, 2);

foreach ($files as $file) {
    $feed = fetch_feed("{$base}/{$file}");
    if (is_wp_error($feed)) {
        $say("feed {$file}", ['error', $feed->get_error_code(), $plain($feed->get_error_message())]);
        continue;
    }
    $say("feed {$file} channel", [
        get_class($feed),
        $feed->get_title(),
        $plain($feed->get_permalink()),
        $plain($feed->get_link()),
        $feed->get_description(),
        $feed->get_language(),
        $feed->get_item_quantity(),
        $feed->get_item_quantity(2),
        $feed->error(),
    ]);
    $items = $feed->get_items();
    $say("feed {$file} items", array_map($describeItem, $items));
    $say("feed {$file} slice", array_map(static fn ($i) => $i->get_title(), $feed->get_items(1, 1)));
    $first = $feed->get_item(0);
    $say("feed {$file} get_item", $first ? [get_class($first), $first->get_title(), $first->get_feed() === $feed] : null);
    $say("feed {$file} more", array_map($describeMore, $items));
    $author = $feed->get_author();
    $say("feed {$file} channel more", [
        $feed->get_copyright(),
        $plain($feed->get_image_url()),
        $feed->get_image_title(),
        $plain($feed->get_image_link()),
        $feed->get_image_width(),
        $feed->get_image_height(),
        $feed->get_type(),
        $feed->get_encoding(),
        $plain($feed->subscribe_url()),
        $author ? [$author->get_name(), $author->get_email()] : null,
        $deep((array) $feed->get_links()),
        $feed->get_channel_tags('', 'title'),
        $deep($feed->get_feed_tags(SIMPLEPIE_NAMESPACE_ATOM_10, 'title')),
        $feed->get_item(99),
        count((array) $feed->get_authors()),
        count((array) $feed->get_categories()),
        $feed->status_code(),
        $plain($feed->get_base()),
        $deep($feed->get_all_discovered_feeds() ? array_map(static fn ($f) => $f->url ?? null, $feed->get_all_discovered_feeds()) : []),
    ]);
    $say("feed {$file} tree", $tree((array) ($feed->data['child'] ?? [])));
    $say("feed {$file} cached", [get_site_transient('feed_' . md5("{$base}/{$file}")) !== false, get_site_transient('feed_mod_' . md5("{$base}/{$file}")) !== false]);
}
foreach ($errors as $file) {
    $feed = fetch_feed("{$base}/{$file}");
    $say("feed {$file}", is_wp_error($feed) ? ['error', $feed->get_error_code(), $plain($feed->get_error_message())] : ['feed', get_class($feed), $feed->get_item_quantity()]);
}
$say('wp_feed_options', $options);
$say('wp_feed_cache_transient_lifetime', $lifetimes);

// A second fetch reads the cache: no request leaves.
$requests = 0;
add_filter('pre_http_request', static function ($pre) use (&$requests) {
    $requests++;
    return $pre;
});
$again = fetch_feed("{$base}/rss2.xml");
$say('cached fetch', [is_wp_error($again) ? 'error' : $again->get_item_quantity(), $requests]);

$multi = fetch_feed(["{$base}/rss2.xml", "{$base}/rdf.xml"]);
$say('multiple feeds', is_wp_error($multi) ? ['error', $multi->get_error_message()] : [get_class($multi), $multi->get_title(), $multi->get_item_quantity(), array_map(static fn ($i) => [$i->get_title(), $i->get_feed()->get_title()], $multi->get_items())]);

ob_start();
wp_widget_rss_output("{$base}/rss2.xml", ['items' => 2, 'show_summary' => 1, 'show_author' => 1, 'show_date' => 1]);
$say('wp_widget_rss_output all', $plain(ob_get_clean()));
ob_start();
wp_widget_rss_output("{$base}/atom.xml");
$say('wp_widget_rss_output defaults', $plain(ob_get_clean()));
ob_start();
wp_widget_rss_output(['url' => "{$base}/edge.xml", 'items' => 20, 'show_summary' => 1, 'show_author' => 1, 'show_date' => 1]);
$say('wp_widget_rss_output edge', $plain(ob_get_clean()));
ob_start();
wp_widget_rss_output("{$base}/empty.xml");
$say('wp_widget_rss_output empty', $plain(ob_get_clean()));
$say('wp_widget_rss_process', $deep(wp_widget_rss_process(['url' => "{$base}/rss2.xml", 'items' => 3, 'title' => '<b>T</b>', 'show_date' => 1])));
ob_start();
the_widget('WP_Widget_RSS', ['url' => "{$base}/rss2.xml", 'items' => 2, 'title' => ''], ['before_widget' => '<section>', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>']);
$say('the RSS widget', $plain(ob_get_clean()));
$say('core/rss block', $plain(do_blocks('<!-- wp:rss {"feedURL":"' . $base . '/rss2.xml","itemsToShow":2,"displayExcerpt":true,"displayAuthor":true,"displayDate":true} /-->')));
ob_start();
wp_widget_rss_output(['url' => "{$base}/rss2.xml", 'items' => 3, 'show_author' => 1]);
$say('wp_widget_rss_output email-only author', $plain(ob_get_clean()));
ob_start();
wp_widget_rss_output("{$base}/broken.xml");
$say('wp_widget_rss_output error', $plain(ob_get_clean()));
foreach ([
    'defaults' => '{"feedURL":"' . $base . '/rss2.xml"}',
    'short excerpts' => '{"feedURL":"' . $base . '/edge.xml","itemsToShow":20,"displayExcerpt":true,"excerptLength":5,"displayAuthor":true,"displayDate":true}',
    'rel and new tab' => '{"feedURL":"' . $base . '/rss2.xml","itemsToShow":1,"openInNewTab":true,"rel":"nofollow noopener"}',
    'rel only' => '{"feedURL":"' . $base . '/rss2.xml","itemsToShow":1,"rel":"nofollow"}',
    'classes' => '{"feedURL":"' . $base . '/rss2.xml","itemsToShow":1,"className":"is-style-x extra","align":"wide","blockLayout":"grid"}',
    'broken' => '{"feedURL":"' . $base . '/broken.xml"}',
    'empty' => '{"feedURL":"' . $base . '/empty.xml"}',
    'no url' => '{}',
    'authors' => '{"feedURL":"' . $base . '/atom-edge.xml","displayAuthor":true,"displayDate":true,"displayExcerpt":true}',
    'author without date' => '{"feedURL":"' . $base . '/rss2.xml","displayAuthor":true}',
] as $name => $attrs) {
    $say("core/rss block {$name}", $plain(do_blocks('<!-- wp:rss ' . $attrs . ' /-->')));
}
$empty = fetch_feed('');
$say('fetch_feed empty url', is_wp_error($empty) ? ['error', $empty->get_error_message()] : [get_class($empty), $empty->get_item_quantity(), $empty->error()]);
// Dates in another site timezone.
$zone = static fn () => 'Asia/Tokyo';
add_filter('pre_option_timezone_string', $zone);
ob_start();
wp_widget_rss_output(['url' => "{$base}/atom.xml", 'items' => 2, 'show_date' => 1]);
$say('wp_widget_rss_output tokyo', $plain(ob_get_clean()));
$say('core/rss block tokyo', $plain(do_blocks('<!-- wp:rss {"feedURL":"' . $base . '/atom.xml","itemsToShow":2,"displayDate":true} /-->')));
remove_filter('pre_option_timezone_string', $zone);
$say('core/rss block grid', $plain(do_blocks('<!-- wp:rss {"feedURL":"' . $base . '/atom.xml","blockLayout":"grid","columns":3,"openInNewTab":true} /-->')));

$classes = [];
foreach (['SimplePie', 'SimplePie_Item', 'SimplePie_Author', 'SimplePie_Category', 'SimplePie_Enclosure', 'SimplePie_Source', 'SimplePie\\SimplePie', 'SimplePie\\Item', 'SimplePie\\Author', 'SimplePie\\Category', 'SimplePie\\Enclosure', 'WP_Feed_Cache_Transient', 'WP_SimplePie_File', 'WP_SimplePie_Sanitize_KSES'] as $class) {
    $classes[$class] = class_exists($class) ? (new ReflectionClass($class))->getName() : false;
}
$say('classes', $classes);
$say('item is a SimplePie_Item', isset($first) && $first instanceof SimplePie_Item);

$clear();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
