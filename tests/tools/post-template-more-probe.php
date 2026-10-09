<?php
/**
 * More post template tags (probe post-template-more): the current post's
 * custom fields one key at a time and as the deprecated list, the
 * attachment link tag, and a post's revisions listed with their expanded
 * titles (avatar, author, date, edit link, and what kind of revision).
 * The probe's posts, revisions and attachment are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$heard = [];
foreach (['deprecated_function_run' => 3, 'deprecated_argument_run' => 3] as $hook => $args) {
    add_action($hook, static function (...$a) use ($hook, &$heard): void {
        $heard[] = [$hook, ...array_map(static fn ($v) => is_string($v) ? strip_tags($v) : $v, $a)];
    }, 10, $args);
}
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('deprecated_argument_trigger_error', '__return_false');
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use ($host, &$ids) {
    $json = str_replace($host, '{host}', (string) wp_json_encode($value));
    foreach ($ids as $id => $name) {
        $json = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $json);
    }
    return json_decode($json, true);
};
$printed = static function (Closure $call) use (&$heard): array {
    $heard = [];
    ob_start();
    $out = $call();
    return [(string) ob_get_clean(), $out, $heard];
};
wp_set_current_user(1);

$made[] = $post = (int) wp_insert_post(['post_title' => 'Zz Template More', 'post_status' => 'publish', 'post_content' => 'first', 'post_date' => '2020-02-03 04:05:06']);
$ids[$post] = 'post';
add_post_meta($post, 'zz_one', 'single value');
add_post_meta($post, 'zz_many', 'a');
add_post_meta($post, 'zz_many', 'b');
add_post_meta($post, '_zz_hidden', 'protected');
add_post_meta($post, 'zz_html', '<b>bold</b> & more');
add_post_meta($post, 'zz_array', ['k' => 'v']);
$GLOBALS['post'] = get_post($post);
setup_postdata($GLOBALS['post']);
$say('custom fields one key at a time', [post_custom('zz_one'), post_custom('zz_many'), post_custom('_zz_hidden'), post_custom('zz_array'), post_custom('zz_never'), post_custom(''), post_custom()]);
$say('the deprecated custom field list', [
    $printed(static fn () => the_meta()),
    $printed(static function () {
        $key = static fn ($html, $key, $value) => "[{$key}=" . (is_string($value) ? $value : gettype($value)) . ']';
        add_filter('the_meta_key', $key, 10, 3);
        the_meta();
        remove_filter('the_meta_key', $key, 10);
    }),
]);

$say('the custom field list at its edges', (static function () use (&$made, $printed) {
    $made[] = $bare = (int) wp_insert_post(['post_title' => 'Zz Bare', 'post_status' => 'publish', 'post_content' => 'x']);
    $kept = $GLOBALS['post'];
    $GLOBALS['post'] = get_post($bare);
    $out = [$printed(static fn () => the_meta())[0]];
    add_post_meta($bare, '_zz_only_hidden', 'x');
    $out[] = $printed(static fn () => the_meta())[0];
    add_post_meta($bare, 'zz_spaced', '  padded  ');
    add_post_meta($bare, 'zz <key>', 'v');
    add_post_meta($bare, 'zz_spaced', ' second ');
    $out[] = $printed(static fn () => the_meta())[0];
    $out[] = $printed(static fn () => wp_list_post_revisions($bare))[0];
    $GLOBALS['post'] = $kept;
    return $out;
})());

$made[] = $attachment = (int) wp_insert_attachment(['post_title' => 'Zz Attachment', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'guid' => 'https://zz.example/zz-attachment.png'], false, $post);
$ids[$attachment] = 'attachment';
$say('the attachment link tag', $mask([
    $printed(static fn () => the_attachment_link($attachment)),
    $printed(static fn () => the_attachment_link($attachment, true)),
    $printed(static fn () => the_attachment_link($attachment, false, false, true)),
    $printed(static fn () => the_attachment_link($attachment, false, 'zz-old')),
    $printed(static fn () => the_attachment_link(999999999)),
    $printed(static function () use ($attachment) {
        $GLOBALS['post'] = get_post($attachment);
        the_attachment_link();
        $GLOBALS['post'] = get_post($GLOBALS['post']->post_parent);
    }),
    (static function () use ($attachment, $printed) {
        $args = [];
        $listen = static function (...$a) use (&$args) {
            $args[] = array_slice($a, 2);
            return $a[0];
        };
        add_filter('wp_get_attachment_link', $listen, 10, 7);
        $printed(static function () use ($attachment) {
            the_attachment_link($attachment);
            the_attachment_link($attachment, true);
            the_attachment_link($attachment, 'medium', false, true);
        });
        remove_filter('wp_get_attachment_link', $listen, 10);
        return $args;
    })(),
]));

wp_update_post(['ID' => $post, 'post_content' => 'second']);
wp_update_post(['ID' => $post, 'post_content' => 'third']);
foreach (wp_get_post_revisions($post) as $revision) {
    $made[] = (int) $revision->ID;
}
$made[] = $autosave = (int) _wp_put_post_revision(array_merge(get_post($post, ARRAY_A), ['post_content' => 'autosaved']), true);
$revisions = array_values(wp_get_post_revisions($post));
foreach ($revisions as $i => $revision) {
    $ids[(int) $revision->ID] = wp_is_post_autosave($revision) ? 'autosave' : 'revision' . $i;
}
$dates = static fn ($value) => json_decode((string) preg_replace(['/[A-Z][a-z]+ \d{1,2}, \d{4} @ \d{2}:\d{2}:\d{2}/', '/\d+ (second|min|minute|hour)s? ago/'], ['{date}', '{ago}'], (string) wp_json_encode($value)), true);
$say('revision titles expanded', $dates($mask([
    $printed(static fn () => wp_post_revision_title_expanded($revisions[0])),
    $printed(static fn () => wp_post_revision_title_expanded($revisions[0], false)),
    $printed(static fn () => wp_post_revision_title_expanded(get_post($autosave))),
    $printed(static fn () => wp_post_revision_title_expanded(get_post($post))),
    $printed(static fn () => wp_post_revision_title_expanded((int) $revisions[1]->ID)),
    $printed(static fn () => wp_post_revision_title_expanded(999999999)),
    $printed(static fn () => wp_post_revision_title($revisions[0])),
    $printed(static fn () => wp_post_revision_title_expanded($attachment)),
    (static function () use (&$made, $printed) {
        register_post_type('zzrev', ['public' => true, 'supports' => ['revisions']]);
        $made[] = $custom = (int) wp_insert_post(['post_title' => 'Zz Custom Rev', 'post_status' => 'publish', 'post_type' => 'zzrev']);
        return [$printed(static fn () => wp_post_revision_title_expanded($custom)), wp_post_revision_title($custom)];
    })(),
    (static function () use ($post, $printed) {
        $heard = [];
        $listen = static function (...$a) use (&$heard) {
            $heard[] = [count($a), $a[1] instanceof WP_Post, $a[2]];
            return $a[0] . ' [zz]';
        };
        add_filter('wp_post_revision_title_expanded', $listen, 10, 3);
        $out = [$printed(static fn () => wp_post_revision_title_expanded($post, false))[1], $heard];
        remove_filter('wp_post_revision_title_expanded', $listen, 10);
        return $out;
    })(),
    (static function () use ($post, $printed) {
        wp_set_current_user(0);
        $out = $printed(static fn () => wp_post_revision_title_expanded($post));
        wp_set_current_user(1);
        return $out;
    })(),
])));
$say('revisions listed', $dates($mask([
    $printed(static fn () => wp_list_post_revisions($post)),
    $printed(static fn () => wp_list_post_revisions($post, 'revision')),
    $printed(static fn () => wp_list_post_revisions($post, 'autosave')),
    $printed(static fn () => wp_list_post_revisions($post, 'zz')),
    $printed(static fn () => wp_list_post_revisions(999999999)),
    $printed(static fn () => wp_list_post_revisions()),
    $printed(static function () use ($post) {
        wp_set_current_user(0);
        wp_list_post_revisions($post);
        wp_set_current_user(1);
    }),
])));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
