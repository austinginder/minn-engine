<?php
/**
 * wp_omit_loading_attr_threshold: how many images in the main loop's
 * content load eagerly (the first one large enough with high priority),
 * for thresholds of 0, 1, 3 and 5 read afresh with $force, through
 * wp_filter_content_tags and through image blocks in the_content. Same
 * protocol as api-probe.php.
 */
$log = [];
$saved = [$GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null, $GLOBALS['post'] ?? null];
$img = '';
$blocks = '';
for ($i = 1; $i <= 6; $i++) {
    $img .= '<p><img src="https://zzlazy.example/' . $i . '.jpg" width="800" height="600" alt=""></p>';
    $blocks .= '<!-- wp:image {"width":800,"height":600} --><figure class="wp-block-image is-resized"><img src="https://zzlazy.example/b' . $i . '.jpg" alt="" width="800" height="600"/></figure><!-- /wp:image -->';
}
$marks = static function (string $html): array {
    preg_match_all('/<img[^>]*>/', $html, $m);
    return array_map(static fn ($t) => (str_contains($t, 'loading="lazy"') ? 'lazy' : 'eager') . (str_contains($t, 'fetchpriority="high"') ? '+high' : ''), $m[0]);
};
foreach ([0, 1, 3, 5] as $threshold) {
    $give = static fn () => $threshold;
    add_filter('wp_omit_loading_attr_threshold', $give);
    foreach (['tags' => static fn () => wp_filter_content_tags($img, 'the_content'), 'blocks' => static fn () => apply_filters('the_content', $blocks)] as $how => $render) {
        $GLOBALS['wp_query'] = new WP_Query(['p' => 1]);
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $GLOBALS['wp_query']->in_the_loop = true;
        $GLOBALS['post'] = get_post(1);
        // Each render starts with no content image counted, as a new page does.
        wp_increase_content_media_count(-wp_increase_content_media_count(0));
        $log[] = ["threshold $threshold, $how", [wp_omit_loading_attr_threshold(true), $marks($render())]];
    }
    remove_filter('wp_omit_loading_attr_threshold', $give);
}
$log[] = ['back to the default', wp_omit_loading_attr_threshold(true)];
[$GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $GLOBALS['post']] = $saved;
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
