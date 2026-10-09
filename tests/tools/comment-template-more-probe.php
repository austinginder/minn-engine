<?php
/**
 * comment-template.php's remaining tags (probe comment-template-more): a
 * comment author's URL and URL link, the comment type's name, the post's
 * reply link (open, closed, sign-in required, its arguments and filter), the
 * comment a ?replytocom= names (kept only when it belongs to the post and is
 * approved), and the trackback tags (the URL with pretty and plain
 * permalinks, and the RDF block). The probe's post and comments are removed.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'comments' => []];
$registration = get_option('comment_registration');
register_shutdown_function(static function () use (&$made, $registration): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    update_option('comment_registration', $registration);
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    // WP-CLI keeps https on the reference's site_url(); the engine's CLI run gives http, so the scheme is masked too.
    $value = (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};

$post = (int) wp_insert_post(['post_title' => 'zz ctm post', 'post_status' => 'publish', 'post_content' => 'x', 'comment_status' => 'open']);
$closed = (int) wp_insert_post(['post_title' => 'zz ctm closed', 'post_status' => 'publish', 'post_content' => 'x', 'comment_status' => 'closed']);
$other = (int) wp_insert_post(['post_title' => 'zz ctm other', 'post_status' => 'publish', 'post_content' => 'x']);
$titled = (int) wp_insert_post(['post_title' => 'zz ctm "quoted" & <b>bold</b> it\'s', 'post_name' => 'zz-ctm-titled', 'post_status' => 'publish', 'post_content' => 'x']);
foreach (['post' => $post, 'closed' => $closed, 'other' => $other, 'titled' => $titled] as $label => $id) {
    $made['posts'][] = $id;
    $ids[$id] = $label;
}
$comment = static function (string $label, array $fields) use ($post, &$made, &$ids): int {
    $id = (int) wp_insert_comment($fields + ['comment_post_ID' => $post, 'comment_content' => 'Said ' . $label, 'comment_approved' => 1]);
    $made['comments'][] = $id;
    $ids[$id] = $label;
    return $id;
};
$withUrl = $comment('with url', ['comment_author' => 'Zed', 'comment_author_url' => 'https://www.zz-ctm.example/path/']);
$plainHttp = $comment('plain http', ['comment_author' => 'Yan', 'comment_author_url' => 'http://zz-ctm.example']);
$noUrl = $comment('no url', ['comment_author' => 'Xi', 'comment_author_url' => '']);
$badUrl = $comment('bad url', ['comment_author' => 'Bad', 'comment_author_url' => 'javascript:alert(1)']);
$pingback = $comment('pingback', ['comment_author' => 'Ping', 'comment_type' => 'pingback', 'comment_author_url' => 'https://ping.example/']);
$trackback = $comment('trackback', ['comment_author' => 'Track', 'comment_type' => 'trackback']);
$held = $comment('held', ['comment_author' => 'Held', 'comment_approved' => 0]);
$httpWww = $comment('http www', ['comment_author' => 'Www', 'comment_author_url' => 'http://www.zz-ctm.example/']);
$amp = $comment('amp', ['comment_author' => 'Amp', 'comment_author_url' => 'http://zz-ctm.example/a//?x=1&y=<2>']);
$upper = $comment('upper', ['comment_author' => 'Up', 'comment_author_url' => 'HTTP://WWW.ZZ-CTM.EXAMPLE']);
$review = $comment('review', ['comment_author' => 'Rev', 'comment_type' => 'review']);
$inner = $comment('inner', ['comment_author' => 'In', 'comment_author_url' => 'https://zz-ctm.example/?u=http://www.x.example/http://y']);
$slashes = $comment('slashes', ['comment_author' => 'Sl', 'comment_author_url' => 'http://zz-ctm.example/b//']);
$elsewhere = (int) wp_insert_comment(['comment_post_ID' => $other, 'comment_content' => 'Elsewhere', 'comment_approved' => 1, 'comment_author' => 'Else']);
$made['comments'][] = $elsewhere;
$ids[$elsewhere] = 'elsewhere';

$say('author urls', $mask([
    $echoed(static fn () => comment_author_url($withUrl)),
    $echoed(static fn () => comment_author_url($plainHttp)),
    $echoed(static fn () => comment_author_url($noUrl)),
    $echoed(static fn () => comment_author_url($badUrl)),
]));
$say('author url links', $mask([
    get_comment_author_url_link('', '', '', $withUrl),
    get_comment_author_url_link('Visit', '<b>', '</b>', $withUrl),
    get_comment_author_url_link('', '', '', $plainHttp),
    get_comment_author_url_link('', '[', ']', $noUrl),
    get_comment_author_url_link('', '', '', get_comment($badUrl)),
    get_comment_author_url_link('', '', '', $httpWww),
    get_comment_author_url_link('', '', '', $amp),
    get_comment_author_url_link('', '', '', $upper),
    get_comment_author_url_link('', '', '', $inner),
    get_comment_author_url_link('', '', '', $slashes),
    get_comment_author_url_link('<i>Tagged</i> & more', '', '', $withUrl),
    $echoed(static fn () => comment_author_url_link('Echoed', '(', ')', $withUrl)),
]));
$urlFilter = static fn ($url, $id) => $url . '#from-filter-' . gettype($id) . '-' . $id;
$linkFilter = static fn ($link) => strtoupper($link);
add_filter('comment_url', $urlFilter, 10, 2);
add_filter('get_comment_author_url_link', $linkFilter);
$say('author url links, filtered', $mask([$echoed(static fn () => comment_author_url($withUrl)), get_comment_author_url_link('', '', '', $withUrl), get_comment_author_url_link('', '[b]', '[/b]', $plainHttp)]));
remove_filter('comment_url', $urlFilter, 10);
remove_filter('get_comment_author_url_link', $linkFilter);

$typed = [];
foreach ([$withUrl, $pingback, $trackback, $review] as $id) {
    $GLOBALS['comment'] = get_comment($id);
    $typed[] = [$echoed(static fn () => comment_type()), $echoed(static fn () => comment_type('A remark', 'A track', 'A ping'))];
}
unset($GLOBALS['comment']);
$say('comment types', $typed);

$say('post reply links', $mask([
    get_post_reply_link([], $post),
    get_post_reply_link(['reply_text' => 'Say something', 'before' => '<p>', 'after' => '</p>', 'add_below' => 'zz', 'respond_id' => 'zz-respond'], $post),
    get_post_reply_link([], $closed),
    $echoed(static fn () => post_reply_link(['reply_text' => 'Echo reply'], $post)),
]));
update_option('comment_registration', 1);
$say('post reply links, sign-in required', $mask([
    get_post_reply_link([], $post),
    get_post_reply_link(['login_text' => 'Sign in first', 'before' => '[', 'after' => ']'], $post),
    (static function () use ($post) {
        $filter = static fn ($link, $post) => $link . '<!-- filtered ' . $post->ID . ' -->';
        add_filter('post_comments_link', $filter, 10, 2);
        $out = get_post_reply_link([], $post);
        remove_filter('post_comments_link', $filter, 10);
        return $out;
    })(),
    (static function () use ($post) {
        wp_set_current_user(1);
        $out = get_post_reply_link([], $post);
        wp_set_current_user(0);
        return $out;
    })(),
]));
update_option('comment_registration', $registration);
$replyFilter = static fn ($link, $post) => $link . '<!-- ' . $post->ID . ' -->';
add_filter('post_comments_link', $replyFilter, 10, 2);
$say('post reply link, filtered', $mask([get_post_reply_link([], $post), get_post_reply_link(['before' => '<p>', 'after' => '</p>'], $post)]));
remove_filter('post_comments_link', $replyFilter, 10);

$replyTo = static function ($value, int $forPost) use (&$ids) {
    $_GET['replytocom'] = $value;
    $id = _get_comment_reply_id($forPost);
    unset($_GET['replytocom']);
    return [gettype($id), $ids[$id] ?? $id];
};
$say('reply to comment ids', [
    $replyTo((string) $withUrl, $post),
    $replyTo($withUrl, $post),
    $replyTo((string) $elsewhere, $post),
    $replyTo((string) $held, $post),
    $replyTo('abc', $post),
    $replyTo('', $post),
    $replyTo('999999999', $post),
    _get_comment_reply_id($post),
    $replyTo((string) $withUrl, 0),
    $replyTo(' ' . $withUrl, $post),
    $replyTo($withUrl . 'x', $post),
    $replyTo('-' . $withUrl, $post),
    (static function () use ($replyTo, $held, $post) {
        wp_set_current_user(1);
        $out = $replyTo((string) $held, $post);
        wp_set_current_user(0);
        return $out;
    })(),
]);
$formFor = static function ($value) use ($post, $echoed) {
    $_GET['replytocom'] = $value;
    $out = [get_comment_id_fields($post), $echoed(static fn () => comment_form_title(false, false, true, $post)), $echoed(static fn () => comment_form_title('None', 'To %s', false, $post)), str_contains(get_cancel_comment_reply_link('', $post), 'display:none')];
    unset($_GET['replytocom']);
    return $out;
};
$say('the form for a reply', $mask([$formFor((string) $withUrl), $formFor((string) $elsewhere), $formFor((string) $held), $formFor('abc'), $formFor('-' . $withUrl), $formFor('')]));

$GLOBALS['post'] = get_post($post);
setup_postdata($GLOBALS['post']);
$plain = static fn () => '';
$trackbacks = [get_trackback_url(), $echoed(static fn () => trackback_url()), $echoed(static fn () => trackback_url(true)), get_post_reply_link(), get_post_reply_link(['login_text' => 'Unused'], get_post($post))];
$heard = [];
$deprecated = static function ($function, $message, $version) use (&$heard): void {
    $heard[] = [$function, $version];
};
add_action('deprecated_argument_run', $deprecated, 10, 3);
add_filter('deprecated_argument_trigger_error', '__return_false');
$trackbacks[] = [$echoed(static function () use (&$returned) {
    $returned = trackback_url(false);
}), $returned, $heard];
add_filter('pre_option_permalink_structure', $plain);
$trackbacks[] = get_trackback_url();
// Which address the plain form starts from: the siteurl option or the home one.
$siteOption = static fn () => 'https://zz-ctm-siteurl.example';
$homeOption = static fn () => 'https://zz-ctm-home.example';
add_filter('pre_option_siteurl', $siteOption);
add_filter('pre_option_home', $homeOption);
$trackbacks[] = strstr(get_trackback_url(), '/wp-trackback.php', true);
remove_filter('pre_option_siteurl', $siteOption);
remove_filter('pre_option_home', $homeOption);
remove_filter('pre_option_permalink_structure', $plain);
$tbFilter = static fn ($url) => $url . '?from-filter';
add_filter('trackback_url', $tbFilter);
$trackbacks[] = get_trackback_url();
remove_filter('trackback_url', $tbFilter);
$say('trackback urls', $mask($trackbacks));
$agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
unset($_SERVER['HTTP_USER_AGENT']);
$rdf = [$echoed(static fn () => trackback_rdf())];
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
$rdf[] = $echoed(static fn () => trackback_rdf('ignored'));
$_SERVER['HTTP_USER_AGENT'] = 'W3C_Validator/1.3';
$rdf[] = $echoed(static fn () => trackback_rdf());
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla w3c_validator';
$rdf[] = $echoed(static fn () => trackback_rdf());
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla W3C_Validator';
$rdf[] = $echoed(static fn () => trackback_rdf());
if ($agent === null) {
    unset($_SERVER['HTTP_USER_AGENT']);
} else {
    $_SERVER['HTTP_USER_AGENT'] = $agent;
}
$GLOBALS['post'] = get_post($titled);
setup_postdata($GLOBALS['post']);
$rdf[] = $echoed(static fn () => trackback_rdf());
$rdf[] = $heard;
remove_action('deprecated_argument_run', $deprecated, 10);
remove_filter('deprecated_argument_trigger_error', '__return_false');
$say('trackback rdf', $mask($rdf));
wp_reset_postdata();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
