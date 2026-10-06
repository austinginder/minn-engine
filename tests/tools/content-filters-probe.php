<?php
/**
 * The content filters' defaults, function by function and as the chains a
 * plugin runs when it renders text itself (apply_filters('the_content'),
 * 'the_title', 'comment_text'): smilies, capital_P_dangit, tag balancing,
 * emoji, insecure home URLs, the image and iframe attributes
 * wp_filter_content_tags adds, the feed's embed clean-up. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$rel = static fn ($v) => is_string($v) ? str_replace(['https://' . $bare, 'http://' . $bare], ['{home}', '{home-http}'], $v) : $v;
$keep = ['use_smilies' => get_option('use_smilies'), 'https_migration_required' => get_option('https_migration_required', 'MISSING')];
register_shutdown_function(static function () use ($keep): void {
    update_option('use_smilies', $keep['use_smilies']);
    $keep['https_migration_required'] === 'MISSING' ? delete_option('https_migration_required') : update_option('https_migration_required', $keep['https_migration_required']);
});
update_option('use_smilies', '1');

// --- Smilies: text becomes emoji images; code, pre and attributes are left alone.
foreach ([
    'Hello :) world', 'one :-) two ;) three :P four 8-) five', ':D :( :o :mrgreen: :?:', 'tight:)no', ':)',
    '<code>:)</code> and :)', '<pre>:-)</pre>', '<a title=":)">:)</a>', 'line one :)' . "\n" . ':( line two', 'no smilies here',
] as $i => $text) {
    $say("convert_smilies #{$i}", str_replace('{home-http}', '{home}', $rel(convert_smilies($text))));
}
update_option('use_smilies', '0');
$say('convert_smilies with use_smilies off', convert_smilies('Hello :) world'));
update_option('use_smilies', '1');

// --- capital_P_dangit: only after a space, a parenthesis, a quote entity, or a tag's closing bracket.
foreach ([
    'I love Wordpress', '(Wordpress)', '&#8216;Wordpress&#8217;', 'Wordpress first', '>Wordpress<', 'wordpress lower', 'WordPress right',
    '<a href="http://wordpress.org/">Wordpress.org</a>', 'MyWordpress', '&#8220;Wordpress&#8221;', 'about Wordpress.',
] as $i => $text) {
    $say("capital_P_dangit #{$i}", capital_P_dangit($text));
}

// --- force_balance_tags: what is left open is closed, a stray closer goes.
foreach ([
    '<p>open', '<div><span>x</div>', '</p>stray', '<b><i>x</b></i>', '<br><img src="x">', '<p>a<p>b', '<ul><li>a<li>b</ul>', 'plain', '<!-- c --><p>x', '<blockquote><p>q',
] as $i => $text) {
    $say("force_balance_tags #{$i}", force_balance_tags($text));
}

// --- wp_staticize_emoji: emoji become the CDN images a feed can carry.
foreach (['Hi 😀', 'thumbs 👍🏽 up', 'no emoji', '&#x1f600; entity', 'flag 🇺🇸'] as $i => $text) {
    $say("wp_staticize_emoji #{$i}", wp_staticize_emoji($text));
}

// --- wp_replace_insecure_home_url: only after a site moved to https asks for it.
$insecure = '<a href="http://' . $bare . '/x">x</a> <img src="http://' . $bare . '/i.png"> http://elsewhere.example/y';
$say('wp_replace_insecure_home_url without migration', $rel(wp_replace_insecure_home_url($insecure)));
update_option('https_migration_required', true);
$say('wp_replace_insecure_home_url with migration', $rel(wp_replace_insecure_home_url($insecure)));
delete_option('https_migration_required');

// --- wp_filter_content_tags over the battery's image, which every stack shares.
$attachments = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$image = $attachments[0] ?? null;
$imageId = $image ? (int) $image->ID : 0;
$url = $imageId ? (string) wp_get_attachment_url($imageId) : '';
$say('probe image', $imageId > 0);
foreach ([
    'plain img' => '<img src="' . $url . '" alt="" class="wp-image-' . $imageId . '" />',
    'img with size class' => '<img src="' . $url . '" alt="a" class="alignnone size-full wp-image-' . $imageId . '" />',
    'img without id' => '<img src="https://elsewhere.example/a.png" alt="" />',
    'img with loading' => '<img loading="eager" src="' . $url . '" class="wp-image-' . $imageId . '" />',
    'iframe' => '<iframe src="https://elsewhere.example/embed" width="560" height="315"></iframe>',
    'two images' => '<p><img src="' . $url . '" class="wp-image-' . $imageId . '" /></p><p><img src="' . $url . '" class="wp-image-' . $imageId . '" /></p>',
] as $name => $html) {
    $say("wp_filter_content_tags {$name}", $rel(wp_filter_content_tags($html)));
}

// --- The feed's embed clean-up.
$embed = '<blockquote class="wp-embedded-content" data-secret="abc"><a href="https://elsewhere.example/p/">P</a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title="P" src="https://elsewhere.example/p/embed/#?secret=abc" data-secret="abc" width="600" height="338" frameborder="0" marginwidth="0" marginheight="0" scrolling="no"></iframe>';
$say('_oembed_filter_feed_content', _oembed_filter_feed_content($embed));

// --- Block hooks over content with nothing hooked.
$say('apply_block_hooks_to_content_from_post_object', apply_block_hooks_to_content_from_post_object('<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'));

// --- The chains, as a plugin runs them on text it renders itself.
foreach ([
    'classic' => "Hello :) from Wordpress.\n\nA second \"paragraph\" -- here.",
    'blocks' => '<!-- wp:paragraph --><p>Hello :) from Wordpress.</p><!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:heading --><h2 class="wp-block-heading">A "heading"</h2><!-- /wp:heading -->',
    'image block' => '<!-- wp:image {"id":' . $imageId . ',"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $url . '" alt="" class="wp-image-' . $imageId . '"/></figure><!-- /wp:image -->',
    'classic image' => 'Look:' . "\n\n" . '<img src="' . $url . '" alt="" class="alignnone wp-image-' . $imageId . '" />',
    'shortcode' => "Before\n\n[caption id=\"\" align=\"alignnone\" width=\"300\"]<img src=\"{$url}\" width=\"300\" height=\"200\" /> A caption[/caption]\n\nAfter",
] as $name => $raw) {
    $say("the_content {$name}", $rel(apply_filters('the_content', $raw)));
}
$say('the_title', apply_filters('the_title', 'Wordpress isn\'t "here" -- now', 0));
$say('comment_text', apply_filters('comment_text', "Hi :) see http://elsewhere.example/page and Wordpress.\n\nSecond <b>bold", null, []));
$say('the_excerpt', apply_filters('the_excerpt', 'An excerpt :) about Wordpress.'));
$say('term_description', apply_filters('term_description', "A term \"description\".\n\nTwo.", 0, 'category', 'display'));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
