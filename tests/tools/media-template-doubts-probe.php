<?php
/**
 * Template and media doubts measured on both stacks (probe
 * media-template-doubts): date archive links on the rewrite object's date
 * structure, esc_xml (CDATA left alone, character references normalised),
 * the archive template of a type, the calendar's month and a type with no
 * posts, and the template and media tags the facade review doubted. Rows
 * the probe makes (zzmed) are removed after; files it writes are deleted.
 */

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image-edit.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($v) => json_decode(str_replace(['https://' . $host, 'http://' . $host], '{home}', (string) json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);
$made = ['posts' => [], 'attachments' => [], 'files' => [], 'users' => []];
try {
$links = [];
foreach (['/%postname%/', '/blog/%postname%/', '/%post_id%/', '/archives/%post_id%', ''] as $structure) {
    $f = static fn () => $structure;
    add_filter('pre_option_permalink_structure', $f);
    $GLOBALS['wp_rewrite']->init();
    $links[$structure] = [get_year_link(2024), get_month_link(2024, 3), get_day_link(2024, 3, 5), get_year_link(0) === get_year_link(current_time('Y')), get_year_link('') === get_year_link(current_time('Y'))];
    remove_filter('pre_option_permalink_structure', $f);
}
$GLOBALS['wp_rewrite']->init();
$say('date links by structure', $mask($links));
$say('esc_xml', [esc_xml('a & b <c>'), esc_xml('<![CDATA[ a & <b> ]]> & x'), esc_xml('x <![CDATA[1&2]]> y <![CDATA[3]]>'), esc_xml('<![CDATA[ open & <x>'), esc_xml('&nbsp;&copy;&zz; &#38; &#x26;'), esc_xml("a'b\"c")]
    + ['refs' => array_map('esc_xml', ['&#38;', '&#0038;', '&#9;', '&#x0026;', '&#X26;', '&#0;', '&#99999999;', '&#xZZ;', '&#00065;'])]);
$say('get_blogs_of_user', [get_blogs_of_user(0), (static function () { $n = 0; $f = function ($v) use (&$n) { $n++; return $v; }; add_filter('get_blogs_of_user', $f); get_blogs_of_user(0); remove_filter('get_blogs_of_user', $f); return $n; })()]);
register_post_type('zzmed_arch', ['public' => true, 'has_archive' => true]);
$archive = [get_post_type_archive_template()];
foreach (['zzmed_arch', 'post', 'zzmed_none'] as $t) { set_query_var('post_type', $t); $archive[] = basename(get_post_type_archive_template()); }
set_query_var('post_type', '');
unregister_post_type('zzmed_arch');
$say('get_post_type_archive_template', $archive);

$out = [];
register_post_type('zzmed_empty', ['public' => true]);
$out['empty type'] = [get_calendar(['display' => false, 'post_type' => 'zzmed_empty']), (static function () { ob_start(); $r = get_calendar(['post_type' => 'zzmed_empty']); return [$r, ob_get_clean()]; })()];
$out['page type'] = is_string(get_calendar(['display' => false, 'post_type' => 'page']));
unregister_post_type('zzmed_empty');
$cap = static function (array $vars) {
    foreach (['m', 'year', 'monthnum', 'w'] as $k) { unset($GLOBALS[$k]); $GLOBALS['wp_query']->set($k, ''); }
    foreach ($vars as $k => $v) { $GLOBALS[$k] = $v; $GLOBALS['wp_query']->set($k, $v); }
    $html = (string) get_calendar(['display' => false]);
    foreach (['m', 'year', 'monthnum', 'w'] as $k) { unset($GLOBALS[$k]); $GLOBALS['wp_query']->set($k, ''); }
    return preg_match('#<caption>(.*?)</caption>#', $html, $c) ? $c[1] : substr($html, 0, 80);
};
foreach (['m 2024' => ['m' => '2024'], 'm 20243' => ['m' => '20243'], 'm 202403' => ['m' => '202403'], 'm 20240315' => ['m' => '20240315'], 'year' => ['year' => 2022], 'year month' => ['year' => 2022, 'monthnum' => 5], 'm and year month' => ['m' => '202403', 'year' => 2022, 'monthnum' => 5], 'w year' => ['w' => 10, 'year' => 2024], 'w' => ['w' => 10], 'month only' => ['monthnum' => 7]] as $k => $v) {
    $out['caption ' . $k] = $cap($v) === $cap([]) ? 'current' : $cap($v);
}
$say('the calendar: its month and a type with no posts', $out);

// Template tags: translated texts, search form arguments, the upload frame's post, weekday dates, star ratings, heartbeat settings.
$mark = static fn ($translation, $text) => in_array($text, ['Search for:', 'Search &hellip;', 'Search', 'Log in', 'Log out', 'Register', 'Site Admin', 'Username or Email Address', 'Password', 'Remember Me', 'Log In', '%s rating', '%s rating based on %s rating', '%1$s rating based on %2$s ratings', '%1$s rating based on %2$s rating'], true) ? '[T]' . $translation : $translation;
add_filter('gettext', $mark, 10, 2);
$markContext = static fn ($translation, $text) => $mark($translation, $text);
add_filter('gettext_with_context', $markContext, 10, 2);
$plural = static fn ($translation, $single, $plural, $number) => str_contains($single, 'rating') ? '[N' . $number . ']' . $translation : $translation;
add_filter('ngettext', $plural, 10, 4);
$search = [get_search_form(['echo' => false]), (static function () { ob_start(); $r = get_search_form(false); return [$r, ob_get_clean() === '']; })()];
$nullForm = static fn () => null;
add_filter('get_search_form', $nullForm);
$search[] = get_search_form(['echo' => false]);
remove_filter('get_search_form', $nullForm);
$say('get_search_form: labels, false, a null filter', $mask($search));
$say('wp_loginout and wp_register for a visitor', $mask([wp_loginout('', false), wp_register('<li>', '</li>', false)]));
$subscriber = wp_insert_user(['user_login' => 'zzmed_sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'user_email' => 'zzmed_sub@example.test']);
if (is_int($subscriber)) {
    $made['users'][] = $subscriber;
}
$before = get_current_user_id();
wp_set_current_user(is_int($subscriber) ? $subscriber : 0);
$noRead = static fn ($caps, $cap) => $cap === 'read' ? ['do_not_allow'] : $caps;
$registered = [wp_register('<li>', '</li>', false)];
add_filter('map_meta_cap', $noRead, 10, 2);
$registered[] = wp_register('<li>', '</li>', false);
remove_filter('map_meta_cap', $noRead, 10);
$registered[] = preg_replace('/_wpnonce=[a-f0-9]+/', '_wpnonce=N', wp_loginout('', false));
wp_set_current_user($before);
$say('wp_register signed in, without read', $mask($registered));
$say('wp_login_form labels', substr_count(wp_login_form(['echo' => false]), '[T]'));
$stars = [];
foreach ([['rating' => 3.04], ['rating' => 3.25], ['rating' => '3,5'], ['rating' => 3.5, 'number' => 1], ['rating' => 3.5, 'number' => 7], ['rating' => 70, 'type' => 'percent'], ['rating' => 4.96]] as $a) {
    $stars[] = wp_star_rating($a + ['echo' => false]);
}
$say('wp_star_rating', $stars);
remove_filter('gettext', $mark, 10);
remove_filter('gettext_with_context', $markContext, 10);
remove_filter('ngettext', $plural, 10);
$GLOBALS['post'] = get_post(1);
$frames = [get_upload_iframe_src('image')];
$GLOBALS['post_ID'] = 77;
$frames = array_merge($frames, [get_upload_iframe_src('image'), get_upload_iframe_src('image', 5), get_upload_iframe_src()]);
unset($GLOBALS['post_ID'], $GLOBALS['post']);
$say('get_upload_iframe_src: the global post_ID, never the global post', $mask($frames));
$days = [];
$weekday = wp_insert_post(['post_title' => 'zzmed day one', 'post_status' => 'publish', 'post_date' => '2024-03-04 10:00:00']);
$weekday2 = wp_insert_post(['post_title' => 'zzmed day one b', 'post_status' => 'publish', 'post_date' => '2024-03-04 12:00:00']);
$weekday3 = wp_insert_post(['post_title' => 'zzmed day two', 'post_status' => 'publish', 'post_date' => '2024-03-05 10:00:00']);
foreach ([$weekday, $weekday2, $weekday3] as $id) {
    if (is_int($id) && $id > 0) {
        $made['posts'][] = $id;
        $GLOBALS['post'] = get_post($id);
        ob_start();
        the_weekday_date('<b>', '</b>');
        $plain = ob_get_clean();
        setup_postdata($GLOBALS['post']);
        ob_start();
        the_weekday_date('<b>', '</b>');
        $days[] = [$plain, ob_get_clean()];
    }
}
unset($GLOBALS['post'], $GLOBALS['previousweekday'], $GLOBALS['currentday'], $GLOBALS['previousday']);
$say('the_weekday_date across posts', $days);
$say('wp_heartbeat_settings', $mask([wp_heartbeat_settings([]), wp_heartbeat_settings(['x' => 1, 'ajaxurl' => 'kept'])]));

// Head output: resource hints (every relation asked, preconnect as scheme and host, unknown attributes dropped), oEmbed discovery links.
$asked = [];
$hint = static function ($urls, $relation) use (&$asked) {
    $asked[] = $relation;
    return match ($relation) {
        'dns-prefetch' => array_merge($urls, ['https://zzmed-dns.example/path/x.js', '//zzmed-dns2.example']),
        'preconnect' => array_merge($urls, ['https://zzmed-pre.example/some/path?q=1', ['href' => 'https://zzmed-pre2.example/p', 'crossorigin' => 'anonymous', 'zzbogus' => 'x', 'as' => 'font', 'type' => 'font/woff2', 'pr' => '0.5', 'media' => 'all'], ['href' => 'https://zzmed-pre3.example', 'crossorigin']]),
        'prefetch' => array_merge($urls, ['https://zzmed-fetch.example/next.html']),
        'prerender' => array_merge($urls, ['https://zzmed-render.example/next.html']),
        default => $urls,
    };
};
add_filter('wp_resource_hints', $hint, 10, 2);
ob_start();
wp_resource_hints();
$hints = ob_get_clean();
remove_filter('wp_resource_hints', $hint, 10);
$say('wp_resource_hints', [$asked, preg_split('/\n/', trim(preg_replace("#<link rel='dns-prefetch' href='//[^z][^']*' />\n#", '', $hints)))]);
$discovery = [];
$heard = [];
$listen = static function ($output) use (&$heard) { $heard[] = $output; return $output; };
add_filter('oembed_discovery_links', $listen);
ob_start();
wp_oembed_add_discovery_links();
$discovery[] = ob_get_clean();
$discovery[] = $heard;
remove_filter('oembed_discovery_links', $listen);
$say('oEmbed discovery off a singular page', $discovery);
$endpoint = static fn ($url, $permalink, $format) => $url . '&zzmed=' . $format;
add_filter('oembed_endpoint_url', $endpoint, 10, 3);
$oembed = [];
foreach (['/%postname%/'] as $structure) {
    $plain = static fn () => $structure;
    add_filter('pre_option_permalink_structure', $plain);
    $GLOBALS['wp_rewrite']->init();
    $saved = $GLOBALS['wp_query'];
    $GLOBALS['wp_query'] = new WP_Query(['p' => 1]);
    $GLOBALS['post'] = get_post(1);
    ob_start();
    wp_oembed_add_discovery_links();
    $oembed[] = $mask(ob_get_clean());
    $GLOBALS['wp_query'] = $saved;
    unset($GLOBALS['post']);
    remove_filter('pre_option_permalink_structure', $plain);
}
$GLOBALS['wp_rewrite']->init();
remove_filter('oembed_endpoint_url', $endpoint, 10);
$say('oEmbed discovery: the endpoint filter', $oembed);

// Preload links: the image-only attributes, an entry without as, bare attributes under numeric keys.
$preload = static fn () => [
    ['href' => 'https://zzmed.example/a.css', 'as' => 'style', 'imagesrcset' => 'x.png 1x', 'imagesizes' => '100vw'],
    ['href' => 'https://zzmed.example/b.png', 'as' => 'image', 'imagesrcset' => 'b.png 1x, b2.png 2x', 'imagesizes' => '50vw', 'fetchpriority' => 'high'],
    ['imagesrcset' => 'c.png 1x', 'imagesizes' => '10vw'],
    ['imagesrcset' => 'd.png 1x', 'as' => 'image'],
    ['href' => 'https://zzmed.example/e.woff2', 'as' => 'font', 'type' => 'font/woff2', 'crossorigin'],
    ['href' => 'https://zzmed.example/f.js', 'as' => 'script', 'zzbogus' => 'x', 'media' => 'all', 0 => 'zzbare'],
    ['href' => 'https://zzmed.example/g.css'],
    ['as' => 'style'],
    ['href' => 'https://zzmed.example/a.css', 'as' => 'style'],
];
add_filter('wp_preload_resources', $preload);
ob_start();
wp_preload_resources();
$say('wp_preload_resources', explode("\n", trim(ob_get_clean())));
remove_filter('wp_preload_resources', $preload);

// The archive list: the count and the after text together, the current period only on an archive page.
register_post_type('zzmed_arc', ['public' => true]);
foreach (['2019-02-03 10:00:00', '2019-02-09 10:00:00', '2018-07-01 10:00:00'] as $date) {
    $arch = wp_insert_post(['post_type' => 'zzmed_arc', 'post_title' => 'zzmed archived', 'post_status' => 'publish', 'post_date' => $date]);
    if (is_int($arch) && $arch > 0) {
        $made['posts'][] = $arch;
    }
}
$list = static fn (array $args) => $mask(preg_replace('/\?p=\d+/', '?p=N', (string) wp_get_archives($args + ['echo' => 0, 'limit' => 3, 'post_type' => 'zzmed_arc'])));
$archives = [
    'count and after' => $list(['show_post_count' => true, 'after' => '<i>a</i>', 'before' => '<b>b</b>', 'format' => 'custom']),
    'count, html' => $list(['show_post_count' => true, 'after' => '<i>a</i>']),
    'yearly count' => $list(['type' => 'yearly', 'show_post_count' => 1]),
    'option' => $list(['format' => 'option', 'show_post_count' => 1, 'type' => 'yearly']),
];
$GLOBALS['wp_query']->set('year', 2019);
$GLOBALS['wp_query']->set('monthnum', 2);
$archives['period set, not an archive'] = $list(['type' => 'monthly']);
$saved = $GLOBALS['wp_query'];
$GLOBALS['wp_query'] = new WP_Query(['year' => 2019, 'monthnum' => 2]);
$archives['on the month archive'] = $list(['type' => 'monthly']) . ' | ' . $list(['type' => 'yearly']);
$GLOBALS['wp_query'] = $saved;
$GLOBALS['wp_query']->set('year', '');
$GLOBALS['wp_query']->set('monthnum', '');
$say('wp_get_archives', $archives);
unregister_post_type('zzmed_arc');

// The comments feed link of a single post: what the show filters are handed and when they run.
$open = wp_insert_post(['post_title' => 'zzmed open', 'post_status' => 'publish', 'comment_status' => 'open']);
$closed = wp_insert_post(['post_title' => 'zzmed closed', 'post_status' => 'publish', 'comment_status' => 'closed', 'ping_status' => 'closed']);
$extraFeed = [];
foreach (['open' => $open, 'closed' => $closed] as $which => $id) {
    if (!is_int($id) || $id <= 0) {
        continue;
    }
    $made['posts'][] = $id;
    foreach (['plain' => null, 'comments feed off' => 'feed_links_show_comments_feed'] as $case => $off) {
        $heard = [];
        $show = static function ($value) use (&$heard) { $heard[] = ['extra', $value]; return $value; };
        $general = static function ($value) use (&$heard, $off) { $heard[] = ['comments', $value]; return $off === null ? $value : false; };
        add_filter('feed_links_extra_show_post_comments_feed', $show);
        add_filter('feed_links_show_comments_feed', $general);
        $saved = $GLOBALS['wp_query'];
        $GLOBALS['wp_query'] = new WP_Query(['p' => $id]);
        $GLOBALS['post'] = get_post($id);
        ob_start();
        feed_links_extra();
        $html = ob_get_clean();
        $GLOBALS['wp_query'] = $saved;
        unset($GLOBALS['post']);
        remove_filter('feed_links_extra_show_post_comments_feed', $show);
        remove_filter('feed_links_show_comments_feed', $general);
        $extraFeed["{$which}, {$case}"] = [$heard, str_replace((string) $id, '{id}', $mask($html))];
    }
}
$say('feed_links_extra on a single post', $extraFeed);

// The block template's markup: shortcodes run before the blocks render.
add_shortcode('zzmed_blocks', static fn () => '<!-- wp:paragraph --><p>zzmed from a shortcode</p><!-- /wp:paragraph -->');
add_shortcode('zzmed_late', static fn () => 'zzmed late ran');
$savedTemplate = $GLOBALS['_wp_current_template_content'] ?? null;
$GLOBALS['_wp_current_template_content'] = "[zzmed_blocks]\n<!-- wp:html -->[zzmed_late]<!-- /wp:html -->";
$templateHtml = get_the_block_template_html();
$GLOBALS['_wp_current_template_content'] = $savedTemplate;
remove_shortcode('zzmed_blocks');
remove_shortcode('zzmed_late');
$say('get_the_block_template_html: shortcodes and blocks', $templateHtml);

// The template enhancement buffer: only an HTML response is filtered.
$enhance = static fn ($output) => $output . '<!-- zzmed enhanced -->';
$finalHeard = [];
$finalised = static function ($output) use (&$finalHeard) { $finalHeard[] = $output; };
add_filter('wp_template_enhancement_output_buffer', $enhance);
add_action('wp_finalized_template_enhancement_output_buffer', $finalised);
$buffers = [];
$mime = ini_get('default_mimetype');
foreach (['text/html', 'application/json', 'text/plain', 'application/xhtml+xml', 'TEXT/HTML', ''] as $type) {
    ini_set('default_mimetype', $type);
    $finalHeard = [];
    $buffers[$type] = [wp_finalize_template_enhancement_output_buffer('<p>zz</p>', PHP_OUTPUT_HANDLER_FINAL), $finalHeard];
}
ini_set('default_mimetype', $mime);
remove_filter('wp_template_enhancement_output_buffer', $enhance);
remove_action('wp_finalized_template_enhancement_output_buffer', $finalised);
$say('wp_finalize_template_enhancement_output_buffer by response type', $buffers);

// locate_template reads the template path globals when they are set.
$tplDir = sys_get_temp_dir() . '/zzmed-tpl-' . getmypid();
@mkdir($tplDir . '/child', 0777, true);
@mkdir($tplDir . '/parent', 0777, true);
file_put_contents($tplDir . '/child/zzmed-child.php', '<?php');
file_put_contents($tplDir . '/parent/zzmed-parent.php', '<?php');
$made['files'] = array_merge($made['files'], [$tplDir . '/child/zzmed-child.php', $tplDir . '/parent/zzmed-parent.php']);
$made['dirs'] = [$tplDir . '/child', $tplDir . '/parent', $tplDir];
$savedPaths = [$GLOBALS['wp_stylesheet_path'] ?? null, $GLOBALS['wp_template_path'] ?? null];
$GLOBALS['wp_stylesheet_path'] = $tplDir . '/child';
$GLOBALS['wp_template_path'] = $tplDir . '/parent';
$located = array_map(static fn ($name) => str_replace($tplDir, '{dir}', locate_template([$name])), ['zzmed-child.php', 'zzmed-parent.php', 'index.php']);
[$GLOBALS['wp_stylesheet_path'], $GLOBALS['wp_template_path']] = $savedPaths;
if ($savedPaths[0] === null) {
    unset($GLOBALS['wp_stylesheet_path'], $GLOBALS['wp_template_path']);
}
$located[] = basename(locate_template(['index.php']));
$say('locate_template with the template path globals', $located);

// The auto-sizes style: first in the queue; nothing once the old print callback is removed.
$autoSizes = [];
wp_enqueue_style('zzmed-first', 'https://zzmed.example/first.css');
remove_action('wp_head', 'wp_print_auto_sizes_contain_css_fix', 1);
wp_enqueue_img_auto_sizes_contain_css_fix();
$autoSizes['removed'] = [in_array('wp-img-auto-sizes-contain', wp_styles()->queue, true), isset(wp_styles()->registered['wp-img-auto-sizes-contain'])];
add_action('wp_head', 'wp_print_auto_sizes_contain_css_fix', 1);
wp_enqueue_img_auto_sizes_contain_css_fix();
$autoSizes['kept'] = [array_values(array_intersect(wp_styles()->queue, ['wp-img-auto-sizes-contain', 'zzmed-first'])), isset(wp_styles()->registered['wp-img-auto-sizes-contain']), has_action('wp_head', 'wp_print_auto_sizes_contain_css_fix')];
remove_action('wp_head', 'wp_print_auto_sizes_contain_css_fix', 1);
wp_dequeue_style('zzmed-first');
wp_deregister_style('zzmed-first');
$say('wp_enqueue_img_auto_sizes_contain_css_fix', $autoSizes);

// Media helpers: a 3x2 PNG attachment of the probe's own.
$png = (static function () { $im = imagecreatetruecolor(3, 2); ob_start(); imagepng($im); return (string) ob_get_clean(); })();
$bits = wp_upload_bits('zzmed-small.png', null, $png);
$small = 0;
if (empty($bits['error'])) {
    $made['files'][] = $bits['file'];
    $small = wp_insert_attachment(['post_title' => 'zzmed small', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'guid' => $bits['url']], $bits['file']);
    if (is_int($small) && $small > 0) {
        $made['attachments'][] = $small;
        wp_update_attachment_metadata($small, ['width' => 3, 'height' => 2, 'file' => _wp_relative_upload_path($bits['file']), 'sizes' => ['zzmed' => ['file' => 'zzmed-small-1x1.png', 'width' => 1, 'height' => 1, 'mime-type' => 'image/png']]]);
    }
}
$heard = [];
$hear = static function ($name) use (&$heard) { return static function (...$args) use (&$heard, $name) { $heard[] = [$name, array_map(static fn ($a) => is_scalar($a) || $a === null ? $a : gettype($a), array_slice($args, 0, 2))]; return $args[0]; }; };
$urlHear = $hear('attachment_url_to_postid');
add_filter('attachment_url_to_postid', $urlHear, 10, 2);
$found = [attachment_url_to_postid('https://zzmed.example/nope.png') === 0, attachment_url_to_postid($bits['url'] ?? '') === $small, $mask(str_replace(json_encode((string) $small), '"{id}"', json_encode($heard)))];
remove_filter('attachment_url_to_postid', $urlHear, 10);
$say('attachment_url_to_postid: what the filter hears', $found);
$heard = [];
$displayHear = $hear('file_is_displayable_image');
add_filter('file_is_displayable_image', $displayHear, 10, 2);
$display = [file_is_displayable_image($bits['file'] ?? ''), file_is_displayable_image(__FILE__)];
if (function_exists('imageavif')) {
    $avif = sys_get_temp_dir() . '/zzmed-' . getmypid() . '.avif';
    $im = imagecreatetruecolor(4, 4);
    @imageavif($im, $avif);
    $made['files'][] = $avif;
    $display[] = is_file($avif) && filesize($avif) > 0 ? file_is_displayable_image($avif) : 'no avif';
}
$display[] = count($heard);
remove_filter('file_is_displayable_image', $displayHear, 10);
$say('file_is_displayable_image', $display);
$gif0 = sys_get_temp_dir() . '/zzmed-' . getmypid() . '-0.gif';
file_put_contents($gif0, "GIF89a\x00\x00\x00\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;");
$made['files'][] = $gif0;
$say('wp_getimagesize of a 0x0 GIF', wp_getimagesize($gif0));
$tMark = static fn ($translation, $text) => $text === 'Missing Attachment' ? '[T]' . $translation : $translation;
add_filter('gettext', $tMark, 10, 2);
$bare = wp_insert_attachment(['post_title' => 'zzmed bare', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'guid' => '']);
if (is_int($bare) && $bare > 0) {
    $made['attachments'][] = $bare;
}
$noUrl = static fn () => false;
add_filter('wp_get_attachment_url', $noUrl);
$links = [wp_get_attachment_link(999999991), wp_get_attachment_link(is_int($bare) ? $bare : 0)];
remove_filter('wp_get_attachment_url', $noUrl);
remove_filter('gettext', $tMark, 10);
$say('wp_get_attachment_link: missing, and no address', $links);
$say('wp_get_loading_attr_default', array_map(static fn ($c) => @wp_get_loading_attr_default($c), ['template', 'template_part_header', 'the_content', 'wp_get_attachment_image', 'zzmed']));
$sizesOnly = static fn ($sizes) => array_merge($sizes, ['zzmed_opt']);
add_filter('intermediate_image_sizes', $sizesOnly);
$opts = ['zzmed_opt_size_w' => 50, 'zzmed_opt_size_h' => 40, 'zzmed_opt_crop' => 1];
foreach ($opts as $name => $value) {
    add_filter("pre_option_{$name}", static fn () => $value);
}
add_image_size('zzmed_zero', 0, 0);
$registered = wp_get_registered_image_subsizes();
remove_image_size('zzmed_zero');
remove_filter('intermediate_image_sizes', $sizesOnly);
foreach (array_keys($opts) as $name) {
    remove_all_filters("pre_option_{$name}");
}
$say('wp_get_registered_image_subsizes: a size from options, a 0x0 size', [$registered['zzmed_opt'] ?? 'missing', isset($registered['zzmed_zero'])]);
$meta = $small ? wp_get_attachment_metadata($small) : [];
$base = $bits['url'] ?? '';
$say('wp_image_src_get_dimensions', [wp_image_src_get_dimensions($base, $meta, $small), wp_image_src_get_dimensions(set_url_scheme($base, 'https'), $meta, $small), wp_image_src_get_dimensions(set_url_scheme($base, 'http'), $meta, $small), wp_image_src_get_dimensions($base . '?v=1', $meta, $small), wp_image_src_get_dimensions(dirname($base) . '/zzmed-small-1x1.png', $meta, $small)]);
$tag = '<img src="' . $base . '?v=2" alt="">';
$yes = static fn () => 'yes';
$addedWh = [preg_replace('#src="[^"]*"#', 'src', wp_img_tag_add_width_and_height_attr($tag, 'the_content', $small))];
add_filter('wp_img_tag_add_width_and_height_attr', $yes);
$addedWh[] = preg_replace('#src="[^"]*"#', 'src', wp_img_tag_add_width_and_height_attr($tag, 'the_content', $small));
remove_filter('wp_img_tag_add_width_and_height_attr', $yes);
$say('wp_img_tag_add_width_and_height_attr: a query on the address, a filter saying yes', $addedWh);
$seven = static fn () => 7;
add_filter('wp_omit_loading_attr_threshold', $seven);
$say('wp_omit_loading_attr_threshold through its filter', wp_omit_loading_attr_threshold(true));
remove_filter('wp_omit_loading_attr_threshold', $seven);
$heard = [];
$srcsetHear = $hear('wp_calculate_image_srcset_meta');
add_filter('wp_calculate_image_srcset_meta', $srcsetHear, 10, 2);
wp_get_attachment_image_srcset($small, 'full');
remove_filter('wp_calculate_image_srcset_meta', $srcsetHear, 10);
$say('wp_get_attachment_image_srcset: the srcset meta filter', array_map(static fn ($h) => $h[0], $heard));

// Media: personal data without an address, compat fields without markup, the image editor's choice, a GD stream, metadata of an unreadable file.
$owner = wp_insert_user(['user_login' => 'zzmed_owner', 'user_pass' => wp_generate_password(), 'role' => 'author', 'user_email' => 'zzmed_owner@example.test']);
$owned = 0;
if (is_int($owner) && $owner > 0) {
    $made['users'][] = $owner;
    $owned = wp_insert_attachment(['post_title' => 'zzmed owned', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_author' => $owner, 'guid' => 'https://zzmed.example/owned.png']);
    if (is_int($owned) && $owned > 0) {
        $made['attachments'][] = $owned;
    }
}
$noAddress = static fn () => false;
$exported = [count(wp_media_personal_data_exporter('zzmed_owner@example.test')['data'])];
add_filter('wp_get_attachment_url', $noAddress);
$exported[] = wp_media_personal_data_exporter('zzmed_owner@example.test');
remove_filter('wp_get_attachment_url', $noAddress);
$say('wp_media_personal_data_exporter: an upload without an address', $exported);
$fields = static fn () => ['zzmed_text' => ['label' => 'ZZ Label', 'input' => 'text', 'value' => 'zz value', 'helps' => 'zz help'], 'zzmed_html' => ['label' => 'ZZ Html', 'input' => 'html', 'html' => '<b>zz html</b>'], 'zzmed_tr' => ['tr' => '<tr><td>zz own row</td></tr>']];
add_filter('attachment_fields_to_edit', $fields);
$compat = get_compat_media_markup($owned);
remove_filter('attachment_fields_to_edit', $fields);
$say('get_compat_media_markup: fields without their own row', str_replace((string) $owned, '{id}', $compat['item']));
$picked = [];
$pickedPng = _wp_image_editor_choose(['mime_type' => 'image/png']);
$picked[] = is_string($pickedPng) ? $pickedPng : $pickedPng;
$picked[] = _wp_image_editor_choose(['mime_type' => 'image/zzmed']);
$picked[] = _wp_image_editor_choose(['mime_type' => 'image/png', 'output_mime_type' => 'image/zzmed']);
$picked[] = _wp_image_editor_choose(['methods' => ['zzmed_nope']]);
$picked[] = is_array(wp_cache_get('wp_image_editor_choose', 'image_editor'));
$say('_wp_image_editor_choose', $picked);
$notImage = sys_get_temp_dir() . '/zzmed-' . getmypid() . '.txt';
file_put_contents($notImage, 'zz not an image');
$made['files'][] = $notImage;
$editorFor = static fn ($path) => ($e = wp_get_image_editor($path)) instanceof WP_Error ? [$e->get_error_code(), str_replace($notImage, '{file}', $e->get_error_message())] : get_class($e);
$say('wp_get_image_editor of a text file and a missing one', [$editorFor($notImage), $editorFor('/zzmed/nope.png')]);
$gd = imagecreatetruecolor(2, 2);
$streamHeard = [];
$streamListen = static function ($image) use (&$streamHeard) { $streamHeard[] = current_filter(); return $image; };
add_filter('image_save_pre', $streamListen);
add_filter('image_editor_save_pre', $streamListen);
ob_start();
$streamed = @wp_stream_image($gd, 'image/png', 0);
$bytes = ob_get_clean();
remove_filter('image_save_pre', $streamListen);
remove_filter('image_editor_save_pre', $streamListen);
$say('wp_stream_image of a GD image', [$streamed, str_starts_with($bytes, "\x89PNG"), $streamHeard]);
$compatCases = [
    'none' => [],
    'textarea required' => ['zz_ta' => ['label' => 'TA', 'input' => 'textarea', 'value' => "a'b\"<c>", 'required' => true]],
    'hidden after a text field' => ['zz_h' => ['input' => 'hidden', 'value' => 'hv'], 'zz_t' => ['label' => 'T', 'value' => 'tv']],
    'extra rows' => ['zz_t' => ['label' => 'T', 'input' => 'text', 'value' => 'v', 'extra_rows' => ['zzrow' => 'zz extra']]],
    'text with quotes, required' => ['zz_q' => ['label' => 'Q<b>', 'input' => 'text', 'value' => "a'b\"<c>&amp;", 'required' => true, 'helps' => 'one help']],
    'only hidden' => ['zz_h' => ['input' => 'hidden', 'value' => "h'v\""]],
    'another input' => ['zz_u' => ['label' => 'U', 'input' => 'select', 'value' => 'v', 'select' => '<select></select>'], 'zz_h2' => ['label' => 'L', 'input' => 'html', 'html' => '<i>h</i>', 'helps' => ['one', 'two']]],
];
$compatOut = [];
foreach ($compatCases as $case => $given) {
    $answer = static fn () => $given;
    add_filter('attachment_fields_to_edit', $answer);
    $compatOut[$case] = str_replace((string) $owned, '{id}', get_compat_media_markup($owned)['item']);
    remove_filter('attachment_fields_to_edit', $answer);
}
$say('get_compat_media_markup: each kind of field', $compatOut);

// The caption and gallery shortcodes: a theme without HTML5 captions, the gallery's filter order and its feed form.
$html5 = get_theme_support('html5');
remove_theme_support('html5');
$captions = [
    img_caption_shortcode(['id' => 'attachment_zzmed1', 'width' => 100, 'caption' => 'zz cap'], '<img src="a.png" alt="">'),
    img_caption_shortcode(['width' => 100, 'caption' => 'zz cap', 'caption_id' => 'zzmedcap'], '<a href="#"><img src="b.png"></a>'),
    img_caption_shortcode(['width' => 100, 'caption' => 'zz cap'], '<img src="c.png">'),
];
if ($html5) {
    add_theme_support('html5', ...$html5);
}
$say('img_caption_shortcode without HTML5 captions', $captions);
$order = [];
$track = static function ($value) use (&$order) { $order[] = current_filter(); return $value; };
add_filter('post_gallery', $track);
add_filter('shortcode_atts_gallery', $track);
$galleryImages = [];
foreach ([1, 2] as $n) {
    $bitsG = wp_upload_bits("zzmed-g{$n}.png", null, (static function () { $im = imagecreatetruecolor(2, 2); ob_start(); imagepng($im); return (string) ob_get_clean(); })());
    if (empty($bitsG['error'])) {
        $made['files'][] = $bitsG['file'];
        $gid = wp_insert_attachment(['post_title' => "zzmed g{$n}", 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'guid' => $bitsG['url']], $bitsG['file']);
        if (is_int($gid) && $gid > 0) {
            $made['attachments'][] = $gid;
            wp_update_attachment_metadata($gid, ['width' => 2, 'height' => 2, 'file' => _wp_relative_upload_path($bitsG['file'])]);
            $galleryImages[] = $gid;
        }
    }
}
gallery_shortcode(['ids' => implode(',', $galleryImages)]);
remove_filter('post_gallery', $track);
remove_filter('shortcode_atts_gallery', $track);
$GLOBALS['wp_query']->is_feed = true;
$feedGallery = gallery_shortcode(['ids' => implode(',', $galleryImages), 'size' => 'full']);
$GLOBALS['wp_query']->is_feed = false;
$ids = array_map('strval', $galleryImages);
$say('gallery_shortcode: filter order, and in a feed', [$order, $mask(str_replace($ids, '{id}', (string) preg_replace('#/uploads/\d{4}/\d{2}/#', '/uploads/{ym}/', $feedGallery)))]);

// Sideloads and uploads: the parent's month, an IPTC title, post data naming an ID, a plugin's $_FILES, image URLs.
$jpeg = (static function () {
    $im = imagecreatetruecolor(4, 3);
    ob_start();
    imagejpeg($im);
    $bytes = (string) ob_get_clean();
    $tag = static fn (int $set, int $field, string $value) => chr(0x1C) . chr($set) . chr($field) . pack('n', strlen($value)) . $value;
    return (string) iptcembed($tag(2, 5, 'ZZ IPTC Title') . $tag(2, 120, 'ZZ IPTC Caption'), 'data://image/jpeg;base64,' . base64_encode($bytes));
})();
$parentPost = wp_insert_post(['post_title' => 'zzmed parent', 'post_status' => 'publish', 'post_date' => '2021-06-15 10:00:00']);
if (is_int($parentPost) && $parentPost > 0) {
    $made['posts'][] = $parentPost;
}
$sideTmp = wp_tempnam('zzmed-side.jpg');
file_put_contents($sideTmp, $jpeg);
$side = media_handle_sideload(['name' => 'zzmed-side.jpg', 'tmp_name' => $sideTmp], (int) $parentPost, null, ['ID' => (int) $parentPost]);
$sideRow = is_wp_error($side) ? $side->get_error_code() : null;
if (is_int($side) && $side > 0) {
    $made['attachments'][] = $side;
    $attached = get_post($side);
    $sideRow = ['folder' => (string) preg_replace('#^.*/uploads/(\d{4}/\d{2})/.*$#', '$1', (string) get_attached_file($side)), 'title' => $attached->post_title, 'excerpt' => $attached->post_excerpt, 'content' => $attached->post_content, 'parent untouched' => get_post_type($parentPost) === 'post', 'is a new post' => $side !== $parentPost];
}
@unlink($sideTmp);
$say('media_handle_sideload: the parent\'s month, an IPTC title, an ID in the post data', $sideRow);
$_FILES['zzmed_file'] = ['name' => 'zzmed-up.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/nonexistent/zzmed', 'error' => 0, 'size' => 10];
$up = media_handle_upload('zzmed_file', 0);
unset($_FILES['zzmed_file']);
$say('media_handle_upload of a $_FILES entry a plugin set', is_wp_error($up) ? [$up->get_error_code(), $up->get_error_message()] : 'attached');
$plainJpeg = (static function () { $im = imagecreatetruecolor(4, 3); ob_start(); imagejpeg($im); return (string) ob_get_clean(); })();
$asked = [];
$fake = static function ($pre, $args, $url) use (&$asked, $plainJpeg) {
    $asked[] = $url;
    if (!empty($args['filename'])) {
        file_put_contents($args['filename'], $plainJpeg);
    }
    return ['headers' => ['content-type' => 'image/jpeg', 'content-length' => strlen($plainJpeg)], 'body' => $plainJpeg, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => $args['filename'] ?? null];
};
add_filter('pre_http_request', $fake, 10, 3);
$extensionsHeard = [];
$listenExtensions = static function ($list) use (&$extensionsHeard) { $extensionsHeard[] = $list; return $list; };
add_filter('image_sideload_extensions', $listenExtensions);
$sideImages = [];
foreach (['https://zzmed.example/a.png', 'https://zzmed.example/f.JPG?x=1', 'https://zzmed.example/g.jpg.php', 'https://zzmed.example/h.avif', 'https://zzmed.example/file.txt', 'https://zzmed.example/noext'] as $address) {
    $sideId = media_sideload_image($address, 0, null, 'id');
    if (is_int($sideId) && $sideId > 0) {
        $made['attachments'][] = $sideId;
        $sideImages[$address] = [get_post_meta($sideId, '_source_url', true), get_post($sideId)->post_title, basename((string) get_attached_file($sideId))];
    } else {
        $sideImages[$address] = is_wp_error($sideId) ? [$sideId->get_error_code(), $sideId->get_error_message()] : $sideId;
    }
}
remove_filter('pre_http_request', $fake, 10);
remove_filter('image_sideload_extensions', $listenExtensions);
$say('media_sideload_image: image addresses only, the file named for the match, the source kept', [$sideImages, $asked, $extensionsHeard[0] ?? null]);
$emptyTmp = wp_tempnam('zzmed-empty');
$uploadFile = ['name' => 'zzmed-e.jpg', 'type' => 'image/jpeg', 'tmp_name' => $emptyTmp, 'error' => 0, 'size' => 0];
$sideFile = ['name' => 'zzmed-e.jpg', 'tmp_name' => $emptyTmp, 'size' => 0];
$say('an empty file uploaded and sideloaded', [wp_handle_upload($uploadFile, ['test_form' => false]), wp_handle_sideload($sideFile, ['test_form' => false])]);
@unlink($emptyTmp);

// Sub-sizes: which a metadata lacks, and an editor without make_subsize.
$subsizeTarget = wp_insert_attachment(['post_title' => 'zzmed subsizes', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'guid' => 'https://zzmed.example/s.png']);
if (is_int($subsizeTarget) && $subsizeTarget > 0) {
    $made['attachments'][] = $subsizeTarget;
}
$missing = [];
$sizeNames = static fn (array $sizes) => array_map(static fn ($s) => [$s['width'], $s['height'], (bool) $s['crop']], $sizes);
foreach (['no metadata' => false, 'empty' => [], 'no width' => ['height' => 500], '3x2' => ['width' => 3, 'height' => 2], '150x150' => ['width' => 150, 'height' => 150], '151x100' => ['width' => 151, 'height' => 100], '300x300' => ['width' => 300, 'height' => 300], '1200x800 with medium' => ['width' => 1200, 'height' => 800, 'sizes' => ['medium' => ['file' => 'm.png', 'width' => 300, 'height' => 200]]]] as $case => $meta) {
    $give = static fn () => $meta;
    add_filter('wp_get_attachment_metadata', $give);
    $missing[$case] = $sizeNames(wp_get_missing_image_subsizes($subsizeTarget));
    remove_filter('wp_get_attachment_metadata', $give);
}
$say('wp_get_missing_image_subsizes', $missing);
if (!class_exists('WP_Image_Editor', false) && is_file(ABSPATH . WPINC . '/class-wp-image-editor.php')) {
    require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
}
if (!class_exists('Zzmed_Recording_Editor')) {
    eval('class Zzmed_Recording_Editor extends WP_Image_Editor {
        public static $calls = [];
        public static function test($args = []) { return true; }
        public static function supports_mime_type($mime_type) { return true; }
        public function load() { self::$calls[] = "load"; $this->size = ["width" => 400, "height" => 300]; return true; }
        public function save($destfilename = null, $mime_type = null) { return []; }
        public function resize($max_w, $max_h, $crop = false) { self::$calls[] = "resize"; return true; }
        public function multi_resize($sizes) { self::$calls[] = "multi_resize:" . implode(",", array_keys($sizes)); $out = []; foreach ($sizes as $n => $s) { $out[$n] = ["file" => "zz-" . $n . ".png", "width" => $s["width"], "height" => $s["height"], "mime-type" => "image/png"]; } return $out; }
        public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false) { return true; }
        public function rotate($angle) { return true; }
        public function flip($horz, $vert) { return true; }
        public function stream($mime_type = null) { return true; }
    }');
}
$onlyRecording = static fn () => ['Zzmed_Recording_Editor'];
add_filter('wp_image_editors', $onlyRecording);
$recordingFile = sys_get_temp_dir() . '/zzmed-' . getmypid() . '-rec.png';
file_put_contents($recordingFile, (static function () { $im = imagecreatetruecolor(4, 3); ob_start(); imagepng($im); return (string) ob_get_clean(); })());
$made['files'][] = $recordingFile;
Zzmed_Recording_Editor::$calls = [];
$madeMeta = _wp_make_subsizes(['thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true], 'zzmed_a' => ['width' => 50, 'height' => 50, 'crop' => false]], $recordingFile, ['width' => 400, 'height' => 300, 'file' => 'x.png', 'sizes' => []], $subsizeTarget);
remove_filter('wp_image_editors', $onlyRecording);
$say('_wp_make_subsizes with an editor that has no make_subsize', [Zzmed_Recording_Editor::$calls, array_keys((array) ($madeMeta['sizes'] ?? []))]);

// PDF previews: the fallback sizes as registered (crop kept), and none when no fallback size is asked for.
$pdfBytes = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 300]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$pdfRows = [];
foreach (['default' => null, 'no fallback sizes' => []] as $case => $fallback) {
    $pdfBits = wp_upload_bits("zzmed-doc-{$case}.pdf", null, $pdfBytes);
    if (!empty($pdfBits['error'])) {
        $pdfRows[$case] = $pdfBits['error'];
        continue;
    }
    $made['files'][] = $pdfBits['file'];
    $pdf = wp_insert_attachment(['post_title' => 'zzmed pdf', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit'], $pdfBits['file']);
    if (!is_int($pdf) || $pdf <= 0) {
        continue;
    }
    $made['attachments'][] = $pdf;
    $sizesFilter = static fn ($sizes) => $fallback ?? $sizes;
    add_filter('fallback_intermediate_image_sizes', $sizesFilter);
    $pdfMeta = wp_generate_attachment_metadata($pdf, $pdfBits['file']);
    remove_filter('fallback_intermediate_image_sizes', $sizesFilter);
    $summary = array_keys($pdfMeta);
    foreach ((array) ($pdfMeta['sizes'] ?? []) as $name => $size) {
        $summary['sizes'][$name] = [$size['width'] ?? null, $size['height'] ?? null, preg_replace('/^zzmed-doc-[a-z ]+-?/', '', (string) ($size['file'] ?? ''))];
        $made['files'][] = dirname($pdfBits['file']) . '/' . ($size['file'] ?? '');
    }
    $pdfRows[$case] = $summary;
}
$say('PDF previews', $pdfRows);

// Heartbeat settings on an admin screen.
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$savedScreen = $GLOBALS['current_screen'] ?? null;
$GLOBALS['current_screen'] = WP_Screen::get('dashboard');
$adminHeartbeat = [is_admin(), $mask(wp_heartbeat_settings(['x' => 1]))];
$GLOBALS['current_screen'] = $savedScreen;
$say('wp_heartbeat_settings on an admin screen', $adminHeartbeat);

} finally {
    foreach ($made['attachments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_attachment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['files'] as $file) {
        @unlink($file);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
    foreach ($made['dirs'] ?? [] as $dir) {
        @rmdir($dir);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
