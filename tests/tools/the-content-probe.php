<?php
/**
 * get_the_content as the loop and the excerpt use it: a plain post, a
 * more tag with the teaser shown and not, a custom more text and a
 * stripped teaser, the <!--noteaser--> marker, pages split by <!--nextpage-->
 * read on page 1 and 2, a protected post, and a plugin re-paging through
 * content_pagination; the hooks each asks. Same protocol as api-probe.php;
 * the posts are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$content = static function (string $label, string $body, array $globals = [], array $args = [], array $filters = [], string $password = '') use (&$made, $say): void {
    $id = (int) wp_insert_post(['post_title' => $label, 'post_content' => $body, 'post_status' => 'publish', 'post_password' => $password]);
    $made[] = $id;
    $post = get_post($id);
    $GLOBALS['post'] = $post;
    setup_postdata($post);
    foreach ($globals as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    foreach ($filters as $hook => $callback) {
        add_filter($hook, $callback, 10, 2);
    }
    $seen = [];
    $recorder = static function (string $hook) use (&$seen): void {
        if (in_array($hook, ['content_pagination', 'the_content_more_link', 'post_password_required', 'the_password_form'], true)) {
            $a = array_slice(func_get_args(), 1);
            $seen[] = $hook . ' ' . json_encode(array_map(static fn ($x) => is_object($x) ? get_class($x) : $x, $a), JSON_UNESCAPED_SLASHES);
        }
    };
    add_action('all', $recorder);
    $out = get_the_content(...$args);
    remove_action('all', $recorder);
    foreach ($filters as $hook => $callback) {
        remove_filter($hook, $callback, 10);
    }
    $mask = static fn (string $s): string => (string) preg_replace(['/\b' . $id . '\b/', '#https?://[^/"]+#'], ['{id}', '{home}'], $s);
    $say($label, ['content' => $mask($out), 'hooks' => array_map($mask, $seen)]);
};
$more = "<!-- wp:paragraph --><p>Teaser.</p><!-- /wp:paragraph -->\n\n<!-- wp:more --><!--more--><!-- /wp:more -->\n\n<!-- wp:paragraph --><p>The rest.</p><!-- /wp:paragraph -->";
$content('zz content plain', '<p>Plain.</p>');
$content('zz content more, teaser only', $more, ['more' => 0]);
$content('zz content more, all', $more, ['more' => 1]);
$content('zz content more text', $more, ['more' => 0], ['Keep reading']);
$content('zz content strip teaser', $more, ['more' => 1], [null, true]);
$content('zz content noteaser', "<p>Teaser.</p><!--more--><!--noteaser--><p>The rest.</p>", ['more' => 1]);
$content('zz content custom more', "<p>Teaser.</p><!--more Go on--><p>The rest.</p>", ['more' => 0]);
$paged = "<p>Page one.</p><!--nextpage--><p>Page two.</p><!--nextpage--><p>Page three.</p>";
$content('zz content page one', $paged);
$content('zz content page two', $paged, ['page' => 2]);
$content('zz content protected', '<p>Secret.</p>', [], [], [], 'pw');
$content('zz content re-paged', $paged, [], [], ['content_pagination' => static fn ($pages) => [implode(' | ', $pages)]]);
unset($GLOBALS['page'], $GLOBALS['more']);
foreach ($made as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
