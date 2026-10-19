<?php
/**
 * The latest-posts block across its attributes, as the reference renders it
 * (probe latest-posts): the list and the grid with its columns, the author,
 * the date, the excerpt (cut, with its more link) and the whole content,
 * the featured image (its size, alignment, dimensions and link), and the
 * block's own classes, over two posts it makes in a category of their own
 * (one with a hand excerpt and a featured image); they and the category go
 * at the end. Ids, dates and the host
 * are masked. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$category = 0;
register_shutdown_function(static function () use (&$made, &$category): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    if (is_int($category) && $category > 1) {
        wp_delete_term($category, 'category');
    }
});
$term = wp_insert_term('Zz latest posts', 'category');
$category = is_array($term) ? (int) $term['term_id'] : 0;
$now = current_time('timestamp');
$a = wp_insert_post(['post_title' => 'Zz latest A', 'post_status' => 'publish', 'post_author' => 1, 'post_date' => date('Y-m-d H:i:s', $now - 120), 'post_category' => [$category], 'post_content' => "<!-- wp:paragraph -->\n<p>Zz the first paragraph of A, with words enough that an excerpt of ten words is cut short.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Its second paragraph.</p>\n<!-- /wp:paragraph -->"]);
$b = wp_insert_post(['post_title' => 'Zz latest B', 'post_status' => 'publish', 'post_author' => 1, 'post_date' => date('Y-m-d H:i:s', $now - 60), 'post_category' => [$category], 'post_excerpt' => 'A hand excerpt for B.', 'post_content' => "<!-- wp:paragraph -->\n<p>B's content.</p>\n<!-- /wp:paragraph -->"]);
foreach ([$a, $b] as $id) {
    if ($category <= 1 || !is_int($id) || $id <= 1) {
        fwrite(STDERR, "latest-posts probe: could not make its posts\n");
        exit(1);
    }
    $made[] = $id;
}
$image = (int) (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'fields' => 'ids'])[0] ?? 0);
if ($image > 1) {
    set_post_thumbnail($b, $image);
}
$mask = static function (string $html) use ($a, $b, $image): string {
    $html = str_replace(['-' . $a . '"', '-' . $b . '"', 'p=' . $a, 'p=' . $b, 'wp-image-' . $image], ['-{a}"', '-{b}"', 'p={a}', 'p={b}', 'wp-image-{image}'], $html);
    $html = (string) preg_replace('#https?://[^/"\']+#', '{host}', $html);
    $html = (string) preg_replace('/datetime="[^"]*"/', 'datetime="{date}"', $html);
    return (string) preg_replace('#(<time[^>]*>)[^<]*(</time>)#', '$1{date}$2', $html);
};
$cases = [
    'the list' => [],
    'with the author' => ['displayAuthor' => true],
    'with the date' => ['displayPostDate' => true],
    'excerpts, cut at ten words' => ['displayPostContent' => true, 'displayPostContentRadio' => 'excerpt', 'excerptLength' => 10],
    'the whole content' => ['displayPostContent' => true, 'displayPostContentRadio' => 'full_post'],
    'a grid of three columns' => ['postLayout' => 'grid', 'columns' => 3],
    'a grid, the column count left out' => ['postLayout' => 'grid'],
    'the featured image, as it is set' => ['displayFeaturedImage' => true],
    'the featured image, thumbnail, left, linked' => ['displayFeaturedImage' => true, 'featuredImageSizeSlug' => 'thumbnail', 'featuredImageAlign' => 'left', 'addLinkToFeaturedImage' => true],
    'the featured image, sized' => ['displayFeaturedImage' => true, 'featuredImageSizeWidth' => 120, 'featuredImageSizeHeight' => 80],
    'excerpts, cut at three words (a hand excerpt too)' => ['displayPostContent' => true, 'excerptLength' => 3],
    'excerpts at the default length' => ['displayPostContent' => true],
    'the featured image, its width only, aligned right' => ['displayFeaturedImage' => true, 'featuredImageSizeWidth' => 90, 'featuredImageAlign' => 'right', 'featuredImageSizeSlug' => 'full'],
    'everything at once' => ['displayAuthor' => true, 'displayPostDate' => true, 'displayPostContent' => true, 'excerptLength' => 5, 'postLayout' => 'grid', 'columns' => 2, 'displayFeaturedImage' => true, 'addLinkToFeaturedImage' => true],
    'its own class and alignment' => ['className' => 'zz-latest', 'align' => 'wide'],
    'colours and type size' => ['textColor' => 'contrast', 'backgroundColor' => 'base', 'fontSize' => 'small'],
];
$rendered = [];
foreach ($cases as $label => $attrs) {
    $rendered[$label] = $mask(do_blocks('<!-- wp:latest-posts ' . wp_json_encode(['postsToShow' => 2, 'categories' => [['id' => $category]]] + $attrs) . ' /-->'));
}
$say('latest-posts', $rendered);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
