<?php
/**
 * The rest of the feed templates (probe feed-templates-more), each printed
 * once a request as probe feed-templates prints the others: RSS 2.0, Atom
 * and RDF in excerpt-only mode over the probe's own posts, the Atom
 * comments feed of the protected post and the RSS 2.0 comments feed of the
 * whole site. The probe's posts, comments and tag are removed at the end.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$author = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID'])[0] ?? 1);
$base = ['post_status' => 'publish', 'post_type' => 'post', 'post_author' => $author, 'post_date' => '2026-09-01 10:00:00', 'post_date_gmt' => '2026-09-01 10:00:00'];
$made['plain'] = (int) wp_insert_post(array_merge($base, ['post_title' => 'Zz Feed "Plain" & Co', 'post_content' => "<!-- wp:paragraph -->\n<p>Zz plain body with \"quotes\" &amp; more.</p>\n<!-- /wp:paragraph -->", 'comment_status' => 'closed', 'tags_input' => ['zz-feed-tag']]));
$made['enclosed'] = (int) wp_insert_post(array_merge($base, ['post_title' => 'Zz Feed Enclosed', 'post_content' => 'Zz enclosed body.', 'post_date' => '2026-09-01 10:01:00', 'post_date_gmt' => '2026-09-01 10:01:00']));
add_post_meta($made['enclosed'], 'enclosure', "https://zz.example/a.mp3\n1234\naudio/mpeg\n");
$made['locked'] = (int) wp_insert_post(array_merge($base, ['post_title' => 'Zz Feed Locked', 'post_content' => 'Zz secret body.', 'post_password' => 'zz', 'post_date' => '2026-09-01 10:02:00', 'post_date_gmt' => '2026-09-01 10:02:00']));
$made['bare'] = (int) wp_insert_post(array_merge($base, ['post_title' => 'Zz Feed Bare', 'post_content' => '', 'post_excerpt' => 'Zz only an excerpt.', 'post_date' => '2026-09-01 10:03:00', 'post_date_gmt' => '2026-09-01 10:03:00']));
$first = (int) wp_insert_comment(['comment_post_ID' => $made['locked'], 'comment_author' => 'Zz Reader', 'comment_author_email' => 'zz-reader@example.com', 'comment_author_url' => 'https://zz.example/reader', 'comment_content' => "Zz first <b>bold</b> line\nand a second.", 'comment_approved' => 1, 'comment_date' => '2026-09-01 11:00:00', 'comment_date_gmt' => '2026-09-01 11:00:00']);
$reply = (int) wp_insert_comment(['comment_post_ID' => $made['locked'], 'comment_parent' => $first, 'comment_author' => 'Zz Answer', 'comment_author_email' => 'zz-answer@example.com', 'comment_content' => 'Zz a reply.', 'comment_approved' => 1, 'comment_date' => '2026-09-01 11:05:00', 'comment_date_gmt' => '2026-09-01 11:05:00']);
$ids = array_values($made);
$mask = static function (string $out) use ($made, $first, $reply): string {
    $out = str_replace(['?p=' . $made['plain'], '?p=' . $made['enclosed'], '?p=' . $made['locked'], '?p=' . $made['bare'], 'comment-' . $first, 'comment-' . $reply], ['?p={plain}', '?p={enclosed}', '?p={locked}', '?p={bare}', 'comment-{first}', 'comment-{reply}'], $out);
    return (string) preg_replace(['/<input name="post_password" id="pwbox-\d+"/', '/for="pwbox-\d+"/', '/<lastBuildDate>[^<]*<\/lastBuildDate>|<updated>[^<]*<\/updated>|<dc:date>[^<]*\t<\/dc:date>/'], ['<input name="post_password" id="pwbox-N"', 'for="pwbox-N"', '{built}'], $out);
};
$render = static function (array $vars) use ($mask): string {
    query_posts($vars);
    // A request stands the main query's post as the global one before the template runs.
    $GLOBALS['post'] = $GLOBALS['wp_query']->post;
    ob_start();
    do_feed();
    $out = ob_get_clean();
    wp_reset_query();
    return $mask($out);
};
add_filter('pre_option_rss_use_excerpt', '__return_true');
foreach (['rss2', 'atom', 'rdf'] as $type) {
    $say("the {$type} feed in excerpt-only mode", $render(['feed' => $type, 'post__in' => $ids, 'orderby' => 'date', 'order' => 'DESC']));
}
remove_filter('pre_option_rss_use_excerpt', '__return_true');
$say('the atom comments feed of the protected post', $render(['feed' => 'atom', 'p' => $made['locked']]));
$say('the rss2 comments feed of the site', $render(['feed' => 'rss2', 'withcomments' => 1]));

if (!function_exists('wp_delete_post')) {
    require_once ABSPATH . 'wp-admin/includes/post.php';
}
wp_delete_comment($reply, true);
wp_delete_comment($first, true);
foreach ($made as $id) {
    wp_delete_post($id, true);
}
$tag = get_term_by('slug', 'zz-feed-tag', 'post_tag');
if ($tag) {
    wp_delete_term($tag->term_id, 'post_tag');
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
