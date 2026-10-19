<?php
/**
 * wp_filter_content_tags as the reference runs it, for the context it is
 * given (probe content-tags): whether iframes may load lazily asked first;
 * each distinct tag fitted out once; an image's loading, decoding and
 * priority from wp_get_loading_optimization_attributes (a header part's in
 * view, a template's none, any other context's lazy), the decoding and
 * loading filters, "auto" sizes, wp_content_img_tag; an iframe without a
 * loading of its own given one. What wp_img_tag_add_loading_attr may answer,
 * the arrays the optimization's filters are handed, iframes when they may
 * not load lazily, how a tag's attributes are read (after a space, in lower
 * case, an image's src and the width and height that ask for a loading in
 * double quotes), and image blocks inside template parts (the part's
 * context deciding). Same protocol as api-probe.php; the probe's parts are
 * removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
$hooks = ['wp_lazy_loading_enabled', 'wp_omit_loading_attr_threshold', 'pre_wp_get_loading_optimization_attributes', 'wp_get_loading_optimization_attributes', 'wp_img_tag_add_loading_attr', 'wp_img_tag_add_srcset_and_sizes_attr', 'wp_img_tag_add_width_and_height_attr', 'wp_img_tag_add_auto_sizes', 'wp_content_img_tag', 'wp_iframe_tag_add_loading_attr', 'wp_min_priority_img_pixels', 'wp_img_tag_add_decoding_attr', 'wp_loading_optimization_force_header_contexts'];
$listen = static function () use ($hooks, &$heard): array {
    $callbacks = [];
    foreach ($hooks as $hook) {
        $callbacks[$hook] = static function (...$args) use ($hook, &$heard) {
            $shown = array_map(static fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : (is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) || $v === null ? (string) $v : gettype($v))), array_slice($args, 0, 3));
            $heard[] = $hook . '(' . implode(', ', $shown) . ')';
            return $args[0];
        };
        add_filter($hook, $callbacks[$hook], 10, 5);
    }
    return $callbacks;
};
$quiet = static function (array $callbacks): void {
    foreach ($callbacks as $hook => $callback) {
        remove_filter($hook, $callback, 10);
    }
};
$tags = static function (string $html): array {
    preg_match_all('/<(img|iframe)([^>]*)>/', $html, $m);
    return array_map(static fn ($a) => trim((string) preg_replace('/\s*(src|width|height)="[^"]*"/', '', $a)), $m[2]);
};

$img = '<img src="https://example.com/a.jpg" width="900" height="900">';
$small = '<img src="https://example.com/s.jpg" width="20" height="20">';
$nodims = '<img src="https://example.com/n.jpg">';
$frame = '<iframe src="https://example.com/" width="10" height="10"></iframe>';
$frame0 = '<iframe src="https://example.com/"></iframe>';
$callbacks = $listen();
foreach ([
    ['a header part', 'template_part_header', $img . $img . $frame],
    ['a footer part', 'template_part_footer', $img . $small . $nodims . $frame . $frame0],
    ['a part with no media', 'template_part_uncategorized', '<p>no media</p>'],
    ['the template', 'template', $img . $frame],
    ['the_content', 'the_content', $img . $small . $img . $img . $frame],
    ['a widget', 'widget_block_content', $img],
    ['a context of its own', 'zz_context', $img . $frame],
    ['a header part again', 'template_part_header', $small . $img],
    ['an image of its own loading', 'template_part_footer', '<img loading="eager" src="https://example.com/c.jpg" width="900" height="900">'],
    ['an image of its own sizes', 'template_part_footer', '<img src="https://example.com/d.jpg" width="900" height="900" sizes="100vw">'],
    ['values the arrays read', 'zz_arrays', '<img src="https://example.com/e.jpg" width="900" height="600" loading="eager" decoding="sync" fetchpriority="low" class="x"><img width="5"><iframe src="https://example.com/" width="10" height="20" loading="eager"></iframe><img src="c.jpg" width="50%" height="abc">'],
    ['an image of high priority in the template', 'template', '<img fetchpriority="high" decoding="async" width="1140" height="760" src="https://example.com/f.jpg"/>'],
] as [$label, $context, $html]) {
    $heard = [];
    $out = wp_filter_content_tags($html, $context);
    $say("tags: {$label}", ['tags' => $tags($out), 'heard' => $heard]);
}
$heard = [];
$say('tags: with no context', ['tags' => $tags(wp_filter_content_tags($img)), 'heard' => $heard]);
$quiet($callbacks);

// What wp_img_tag_add_loading_attr and wp_iframe_tag_add_loading_attr may answer.
foreach ([true, false, 'eager', 'lazy', 'zz', '', 1] as $value) {
    $force = static fn () => $value;
    add_filter('wp_img_tag_add_loading_attr', $force, 5);
    add_filter('wp_iframe_tag_add_loading_attr', $force, 5);
    foreach (['template', 'the_content'] as $context) {
        $say('loading answered ' . json_encode($value) . " in {$context}", $tags(wp_filter_content_tags('<img decoding="async" width="1140" height="760" src="https://example.com/g.jpg"/>' . $frame, $context)));
    }
    remove_filter('wp_img_tag_add_loading_attr', $force, 5);
    remove_filter('wp_iframe_tag_add_loading_attr', $force, 5);
}

// Iframes when they may not load lazily: asked, then left alone.
$off = static fn ($on, $tag) => $tag === 'iframe' ? false : $on;
add_filter('wp_lazy_loading_enabled', $off, 5, 2);
$callbacks = $listen();
$heard = [];
$out = wp_filter_content_tags($frame . $frame0, 'zz_no_lazy_frames');
$quiet($callbacks);
remove_filter('wp_lazy_loading_enabled', $off, 5);
$say('iframes that may not load lazily', ['tags' => $tags($out), 'heard' => $heard]);

// How a tag's attributes are read: after a space, in lower case (src in any case); an image's src, and the width and
// height that ask for a loading, written in double quotes; an iframe's width and height too.
$read = [];
foreach ([
    '<img src="a.jpg" width="50" height="50">', "<img src='a.jpg' width='50' height='50'>", '<img src="a.jpg" width=50 height=50>',
    '<img src="a.jpg" WIDTH="50" HEIGHT="50">', "<img src=\"a.jpg\"\twidth=\"50\"\theight=\"50\">", '<img src="a.jpg" width="" height="">',
    "<img alt='x' src='a.jpg' class='avatar' height='50' width='50' decoding='async'/>", '<img SRC="a.jpg" width="50" height="50">',
    "<img src=\"a.jpg\" width=\"900\" height=\"900\" decoding='sync'>", '<img src="a.jpg" width="900" height="900" DECODING="sync">',
    "<img src=\"a.jpg\" width=\"900\" height=\"900\"\tdecoding=\"sync\">", "<img src=\"a.jpg\" width=\"900\" height=\"900\" loading='eager'>",
    '<img src="a.jpg" width="900" height="900" LOADING="eager">', "<img\tsrc=\"a.jpg\" width=\"900\" height=\"900\">",
    '<img src="" width="900" height="900">', '<img alt="x" src="a.jpg" width=\'900\' height="900">',
    "<iframe src='x' width='10' height='10'></iframe>", '<iframe src="x" WIDTH="10" HEIGHT="10"></iframe>',
    '<iframe src="x" width="" height=""></iframe>', '<iframe src="x" width=10 height=10></iframe>',
    "<iframe src=\"x\" width=\"10\" height=\"10\" loading='eager'></iframe>", '<iframe src="x" width="10" height="10" LOADING="eager"></iframe>',
] as $tag) {
    $callbacks = $listen();
    $heard = [];
    $out = wp_filter_content_tags($tag, 'zz_reading');
    $quiet($callbacks);
    $read[] = [$tag, $out, array_values(array_filter($heard, static fn ($line) => !str_starts_with($line, 'wp_lazy_loading_enabled') && !str_starts_with($line, 'wp_omit_loading_attr_threshold')))];
}
$say('attributes as read', $read);

// Image blocks inside template parts: the part's context decides.
$battery = (array) json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/contracts/fixtures/blocks/manifest.json'), true);
$attachment = (int) ($battery['image'] ?? 0);
$url = $attachment > 0 ? wp_get_attachment_image_url($attachment, 'medium') : false;
if (!$url) {
    fwrite(STDERR, "content-tags: the block battery's image is not there\n");
    exit(1);
}
$parts = [];
register_shutdown_function(static function () use (&$parts): void {
    foreach ($parts as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$block = '<!-- wp:image {"id":' . $attachment . ',"sizeSlug":"medium"} --><figure class="wp-block-image size-medium"><img src="' . $url . '" alt="" class="wp-image-' . $attachment . '"/></figure><!-- /wp:image -->';
foreach (['header' => 'zz-tags-header', 'footer' => 'zz-tags-footer', '' => 'zz-tags-plain'] as $area => $slug) {
    $id = wp_insert_post(['post_type' => 'wp_template_part', 'post_name' => $slug, 'post_title' => $slug, 'post_status' => 'publish', 'post_content' => $block . $block]);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "content-tags: could not save {$slug}\n");
        exit(1);
    }
    $parts[] = $id;
    wp_set_object_terms($id, get_stylesheet(), 'wp_theme');
    if ($area !== '') {
        wp_set_object_terms($id, $area, 'wp_template_part_area');
    }
}
foreach (['header' => 'zz-tags-header', 'footer' => 'zz-tags-footer', 'uncategorized' => 'zz-tags-plain'] as $label => $slug) {
    wp_cache_set_last_changed('posts');
    $html = do_blocks('<!-- wp:template-part {"slug":"' . $slug . '"} /-->');
    preg_match_all('/<img ([^>]*?)src=/', $html, $m);
    $say("an image block in a {$label} part", $m[1]);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
