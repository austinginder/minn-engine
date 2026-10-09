<?php
/**
 * Small output helpers (probe output-helpers): the author's link and post
 * count printed, a feed's Atom text construct, a post format's archive
 * link through term_link, crossorigin attributes added to a page's
 * assets, and UTF-8 in a redirect's address. The probe's users, post and
 * terms are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'posts' => [], 'terms' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'post_format');
        }
    }
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use ($host, &$ids, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($ids as $id => $name) {
        $value = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $value);
    }
    return str_ireplace($host, '{host}', (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '//{host}', $value));
};
$printed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};

$made['users'][] = $site = (int) wp_insert_user(['user_login' => 'zz_link_site', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-site@zz.example', 'display_name' => 'Zz "Site" <b>Owner</b>', 'user_url' => 'https://zz.example/me?a=1&b=2', 'user_nicename' => 'zz-site']);
$made['users'][] = $bare = (int) wp_insert_user(['user_login' => 'zz_link_bare', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-bare@zz.example', 'display_name' => 'Zz Bare', 'user_nicename' => 'zz-bare']);
$made['users'][] = $odd = (int) wp_insert_user(['user_login' => 'zz_link_odd', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-odd@zz.example', 'display_name' => 'Zz Odd', 'user_url' => 'javascript:alert(1)', 'user_nicename' => 'zz-odd']);
$ids[$site] = 'site';
$ids[$bare] = 'bare';
$ids[$odd] = 'odd';
$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'Zz Author Link', 'post_status' => 'publish', 'post_author' => $site, 'post_content' => 'x']);
$made['posts'][] = (int) wp_insert_post(['post_title' => 'Zz Author Link 2', 'post_status' => 'publish', 'post_author' => $site, 'post_content' => 'x']);
$made['posts'][] = (int) wp_insert_post(['post_title' => 'Zz Author Page', 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $site, 'post_content' => 'x']);
$ids[$post] = 'post';
$as = static function (int $author, Closure $call) {
    global $authordata, $post;
    $saved = [$authordata, $post];
    $authordata = $author > 0 ? get_userdata($author) : null;
    $post = get_post((int) $GLOBALS['zz_probe_post']);
    $out = $call();
    [$authordata, $post] = $saved;
    return $out;
};
$GLOBALS['zz_probe_post'] = $post;
$say('the author\'s link', $mask([
    $as($site, static fn () => [get_the_author_link(), get_the_author_link(false), $printed(static fn () => the_author_link()), $printed(static fn () => the_author_link(false))]),
    $as($bare, static fn () => [get_the_author_link(), $printed(static fn () => the_author_link())]),
    $as($odd, static fn () => [get_the_author_link(), $printed(static fn () => the_author_link())]),
    $as(0, static fn () => [get_the_author_link(), $printed(static fn () => the_author_link())]),
    $as($site, static function () use ($printed) {
        $heard = [];
        $listen = static function ($link, ...$rest) use (&$heard) {
            $heard[] = [$link, count($rest), $rest[0] ?? null, ($rest[1] ?? null) instanceof WP_User ? ['WP_User', $rest[1]->user_login] : gettype($rest[1] ?? null)];
            return '[' . $link . ']';
        };
        add_filter('the_author_link', $listen, 10, 3);
        $out = [get_the_author_link(), $printed(static fn () => the_author_link())];
        remove_filter('the_author_link', $listen, 10);
        $out[] = $heard;
        return $out;
    }),
]));

$say('the author\'s post count printed', $mask([
    $as($site, static fn () => [$printed(static fn () => the_author_posts()), get_the_author_posts()]),
    (static function () use ($printed) {
        global $post;
        $saved = $post;
        $post = null;
        $out = [$printed(static fn () => the_author_posts())];
        $post = $saved;
        return $out;
    })(),
    $as($site, static function () use ($printed) {
        $filter = static fn ($count) => $count . ' zz';
        add_filter('get_usernumposts', $filter);
        $out = $printed(static fn () => the_author_posts());
        remove_filter('get_usernumposts', $filter);
        return $out;
    }),
]));

$say('an Atom text construct', array_map(static fn ($data) => prep_atom_text_construct($data), [
    'plain text', 'a <b>bold</b> word', '<p>unclosed', 'a & b', 'a &amp; b', '<div>wrapped</div>', '<br>', '<br/>', '', 'x]]>y', '<b>x]]>y</b>',
    '<p>one</p><p>two</p>', 'text <em>x</em> tail', '<img src="x" alt="y" />', '&nbsp;', '<b>&nbsp;</b>', '<b>a & b</b>', '<x:y>ns</x:y>', "<p>line\nbreak</p>", '<!-- c --> t', '<?pi x?>',
]));

$say('a post format\'s link', $mask((static function () use (&$made, &$ids) {
    $out = [has_filter('term_link', '_post_format_link')];
    foreach (['aside', 'gallery'] as $format) {
        $term = term_exists('post-format-' . $format, 'post_format');
        if (!$term) {
            $term = wp_insert_term('post-format-' . $format, 'post_format');
            $made['terms'][] = (int) $term['term_id'];
        }
        $ids[(int) $term['term_id']] = 'term-' . $format;
    }
    $aside = get_term_by('slug', 'post-format-aside', 'post_format');
    $category = get_term(1, 'category');
    $out[] = [get_term_link($aside), get_post_format_link('aside'), get_post_format_link('gallery'), get_post_format_link('zz-none')];
    $out[] = [_post_format_link('https://zz.example/x', $aside, 'post_format'), _post_format_link('https://zz.example/x', $aside, 'category'), _post_format_link('https://zz.example/x', $category, 'category')];
    $out[] = [_post_format_link('https://zz.example/x', (object) ['slug' => 'post-format-zz', 'term_id' => 0], 'post_format'), _post_format_link('https://zz.example/x', (object) ['slug' => 'plain', 'term_id' => 0], 'post_format')];
    $query = 'https://zz.example/?post_format=post-format-aside&post_format=post-format-aside';
    $links = ['https://zz.example/type/post-format-aside/', 'https://zz.example/post-format-aside/post-format-aside/', 'https://zz.example/type/post-format-asidex/', 'https://zz.example/post-format-aside', $query, 'https://zz.example/?zz=1'];
    $out[] = array_map(static fn ($link) => _post_format_link($link, $aside, 'post_format'), $links);
    global $wp_rewrite;
    $permastruct = $wp_rewrite->extra_permastructs['post_format'] ?? null;
    unset($wp_rewrite->extra_permastructs['post_format']);
    $out[] = array_map(static fn ($link) => _post_format_link($link, $aside, 'post_format'), $links);
    $wp_rewrite->extra_permastructs['post_format'] = $permastruct;
    add_filter('pre_option_permalink_structure', '__return_empty_string');
    $out[] = [
        _post_format_link($query, $aside, 'post_format'),
        _post_format_link('https://zz.example/?post_format=post-format-asidex', $aside, 'post_format'),
        _post_format_link('https://zz.example/?post_format=aside', $aside, 'post_format'),
        _post_format_link($query, $aside, 'category'),
        _post_format_link('https://zz.example/?post_format=post-format-zz', (object) ['slug' => 'post-format-zz', 'term_id' => 0], 'post_format'),
        _post_format_link('https://zz.example/?post_format=plain', (object) ['slug' => 'plain', 'term_id' => 0], 'post_format'),
    ];
    remove_filter('pre_option_permalink_structure', '__return_empty_string');
    return $out;
})()));

// Same-site addresses are built from site_url(), whose scheme WP-CLI forces; the other scheme is cross-origin on both stacks.
$own = static fn (string $path) => site_url($path);
$other = static fn (string $path) => (string) preg_replace('#^https?:#', str_starts_with(site_url(), 'https:') ? 'http:' : 'https:', site_url($path));
$say('crossorigin attributes', $mask(array_map(static fn ($html) => wp_add_crossorigin_attributes($html), [
    '<img src="https://zz.example/a.png">',
    '<img src="https://' . $host . '/a.png">',
    '<img src="/a.png">',
    '<script src="https://zz.example/a.js"></script>',
    '<link rel="stylesheet" href="https://zz.example/a.css">',
    '<link rel="preconnect" href="https://zz.example">',
    '<img src="https://zz.example/a.png" crossorigin="use-credentials">',
    '<audio src="https://zz.example/a.mp3"></audio>',
    '<video src="https://zz.example/a.mp4" poster="https://zz.example/p.png"></video>',
    '<source src="https://zz.example/a.mp4">',
    '<iframe src="https://zz.example/"></iframe>',
    '<a href="https://zz.example/">link</a>',
    '<img srcset="https://zz.example/a.png 1x">',
    '<IMG SRC="https://zz.example/a.png">',
    '<img src="//zz.example/a.png">',
    '<img src="data:image/png;base64,AAAA">',
    '<p>none</p>',
    '<script src="' . $own('/a.js') . '"></script>',
    '<script src="/a.js"></script>',
    '<script src="//zz.example/a.js"></script>',
    '<script src="http://zz.example/a.js"></script>',
    '<script>inline()</script>',
    '<script src="https://zz.example/a.js" crossorigin></script>',
    '<script src="https://zz.example/a.js" crossorigin="use-credentials"></script>',
    '<SCRIPT SRC="https://zz.example/a.js"></SCRIPT>',
    '<link rel="stylesheet" href="' . $own('/a.css') . '">',
    '<link rel="stylesheet" href="/a.css">',
    '<link rel="icon" href="https://zz.example/i.png">',
    '<link rel="stylesheet">',
    '<audio><source src="https://zz.example/a.mp3"></audio>',
    '<video src="/a.mp4"></video>',
    '<script src="https://zz.example/a.js"></script><script src="https://zz.example/b.js"></script><link rel="stylesheet" href="https://zz.example/c.css">',
    '<script src="https://' . strtoupper($host) . '/a.js"></script>',
    '<script src="' . $own('') . ':8443/a.js"></script>',
    '<script src="https://sub.' . $host . '/a.js"></script>',
    '<script src="javascript:x"></script>',
    '<script src=""></script>',
    '<script src="' . $other('/a.js') . '"></script>',
    '<audio><source src="/a.mp3"></audio>',
    '<audio></audio>',
    '<video><source src="/a.mp4"><source src="https://zz.example/b.mp4"></video>',
    '<video src="https://zz.example/a.mp4"><source src="/b.mp4"></video>',
    '<track src="https://zz.example/a.vtt">',
    '<link href="https://zz.example/a.css" rel="stylesheet" crossorigin="anonymous">',
    '<video crossorigin="use-credentials"><source src="https://zz.example/a.mp4"></video>',
    '<audio><source src="https://zz.example/a.mp3"></audio><video><source src="/b.mp4"></video>',
    '<audio src="/a.mp3"><source src="https://zz.example/b.mp3"></audio>',
    '<video><source src="https://zz.example/a.mp4"><source src="https://zz.example/b.mp4"></video>',
    '<audio><p><source src="https://zz.example/a.mp3"></p></audio>',
    '<video></video><source src="https://zz.example/a.mp4">',
    '<script src></script>',
    '',
])));
$say('crossorigin against which address', $mask((static function () {
    $out = [];
    foreach (['home_url', 'site_url'] as $filter) {
        $moved = static fn ($url) => str_replace((string) parse_url($url, PHP_URL_HOST), 'zz-moved.example', $url);
        add_filter($filter, $moved);
        $pair = [wp_add_crossorigin_attributes('<script src="' . site_url('/a.js') . '"></script>'), wp_add_crossorigin_attributes('<script src="' . str_replace('zz-moved.example', 'zz-other.example', home_url('/a.js')) . '"></script>')];
        $out[] = [$filter, ...preg_replace('#https?://(zz-[a-z]+\.example)#', '//$1', $pair)];
        remove_filter($filter, $moved);
    }
    return $out;
})()));

$say('UTF-8 in a redirect', [
    array_map(static fn ($match) => _wp_sanitize_utf8_in_redirect($match), [['é'], ['日本'], ['a b'], ['é', 'x'], ['']]),
    array_map(static fn ($url) => wp_sanitize_redirect($url), ['https://zz.example/é', 'https://zz.example/日本?q=ü', 'https://zz.example/a b', 'https://zz.example/%C3%A9', "https://zz.example/\xC3", "https://zz.example/\xFF\xFE", 'https://zz.example/€<>"\'', "https://zz.example/a\r\nb", 'https://zz.example/%0d%0a']),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
