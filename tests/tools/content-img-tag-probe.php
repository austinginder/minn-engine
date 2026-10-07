<?php
/**
 * The filter plugins use to change each image in content, as the
 * reference applies it (probe content-img-tag): wp_content_img_tag handed
 * every <img> wp_filter_content_tags fits out (an attachment's and any
 * other), with the context it runs in and the attachment's id; what a
 * plugin's change to the tag does to the content; and the same through
 * the_content and widget_text_content. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$attachment = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$id = $attachment ? (int) $attachment[0]->ID : 0;
$url = $id ? (string) wp_get_attachment_url($id) : '';
$mask = static fn ($value) => is_string($value) ? str_replace([$url, (string) $id], ['{url}', '{id}'], $value) : $value;
$heard = [];
add_filter('wp_content_img_tag', static function ($image, $context, $attachmentId) use (&$heard, $mask, $id) {
    $heard[] = [$mask($image), $context, $attachmentId === $id && $id ? '{id}' : $attachmentId];
    return str_replace('<img ', '<img data-zz="1" ', $image);
}, 10, 3);
$content = '<p><img class="wp-image-' . $id . '" src="' . $url . '" alt="a" /></p>'
    . '<p><img src="https://zz.example/x.jpg" alt="b"></p>'
    . '<p><img src="https://zz.example/y.jpg" width="10" height="10" alt="c"></p>'
    . '<iframe src="https://zz.example/f" width="1" height="1"></iframe>';
foreach (['wp_filter_content_tags with no context' => static fn () => wp_filter_content_tags($content), 'wp_filter_content_tags in a context of its own' => static fn () => wp_filter_content_tags($content, 'zz_context'), 'widget_text_content' => static fn () => apply_filters('widget_text_content', $content)] as $label => $run) {
    $heard = [];
    $out = $run();
    $say($label, ['heard' => array_map(static fn ($h) => [preg_replace('/\s(loading|decoding|fetchpriority|sizes|srcset|width|height)="[^"]*"/', '', $h[0]), $h[1], $h[2]], $heard), 'marked' => substr_count((string) $out, 'data-zz="1"')]);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
