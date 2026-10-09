<?php
/**
 * Canonical redirect helpers (probe canonical-helpers): fragments stripped
 * from a URL, query args dropped when the URL does not carry them, the
 * post a 404 guesses from its name (with its filters), the posts found by
 * a slug or a date they used to have, the queries all three ask, and the
 * admin and login addresses a 404 redirects. The probe's posts are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => json_decode(str_replace($host, '{host}', (string) wp_json_encode($value)), true);
$with = static function (string $hook, $callback, Closure $call) {
    add_filter($hook, $callback, 10, 9);
    try {
        return $call();
    } finally {
        remove_filter($hook, $callback, 10);
    }
};
$vars = static function (array $vars, Closure $call) {
    global $wp_query;
    $saved = $wp_query->query_vars;
    foreach ($vars as $key => $value) {
        $wp_query->set($key, $value);
    }
    try {
        return $call();
    } finally {
        $wp_query->query_vars = $saved;
    }
};

$say('fragments stripped', array_map(static fn ($url) => strip_fragment_from_url($url), [
    'https://zz.example/a/#frag', 'https://zz.example/a/?x=1#f', '/rel#f', '#only', 'https://zz.example', 'https://user:pw@zz.example:8080/p?q=1#f',
    'zz.example/a#f', '', 'https://zz.example/a#', 'https://zz.example/a?x=1', 'https://zz.example/a/?x=1&y=2#f', 'http://zz.example/a#f', '//zz.example/a#f', 'https://zz.example:8080/a#f',
]));

$say('query args kept only when the URL has them', [
    _remove_qs_args_if_not_in_url('?p=1&page=2', ['p', 'page'], 'https://zz.example/a/?page=2'),
    _remove_qs_args_if_not_in_url('?p=1&page=2', ['p', 'page'], 'https://zz.example/a/'),
    _remove_qs_args_if_not_in_url('?p=1&page=2', ['p'], 'https://zz.example/a/?p=1'),
    _remove_qs_args_if_not_in_url('p=1&cpage=3&x=y', ['cpage', 'x'], 'https://zz.example/a/?x=z'),
    _remove_qs_args_if_not_in_url('', ['p'], 'https://zz.example/a/'),
    _remove_qs_args_if_not_in_url('?p=1', [], 'https://zz.example/a/'),
    _remove_qs_args_if_not_in_url('?p=1', ['p'], 'https://zz.example/a/?P=1'),
    _remove_qs_args_if_not_in_url('?p=1', ['p'], 'https://zz.example/a/?pp=1'),
    _remove_qs_args_if_not_in_url('?p=1', ['p'], 'https://zz.example/a/?x=1&p'),
]);

$post = static function (array $args) use (&$made): int {
    $made[] = $id = (int) wp_insert_post($args + ['post_status' => 'publish', 'post_content' => 'x', 'post_type' => 'post']);
    return $id;
};
$guessable = $post(['post_title' => 'Zz Guessable Post', 'post_name' => 'zz-guessable-post', 'post_date' => '2021-03-04 05:06:07']);
$guessable_page = $post(['post_title' => 'Zz Guessable Page', 'post_name' => 'zz-guessable-page', 'post_type' => 'page', 'post_date' => '2022-05-06 07:08:09']);
$draft = $post(['post_title' => 'Zz Guess Draft', 'post_name' => 'zz-guessdraft', 'post_status' => 'draft']);
$ids = [$guessable => 'post', $guessable_page => 'page', $draft => 'draft'];
// Which post a guess sent to, by its permalink (url_to_postid is left out of it).
$which = static function ($url) use ($ids, $mask) {
    if (!is_string($url)) {
        return $url;
    }
    foreach ($ids as $id => $label) {
        if (str_starts_with($url, (string) get_permalink($id))) {
            return [$label, $mask($url)];
        }
    }
    return [0, $mask($url)];
};
$guess = static fn (array $query) => $vars($query, static fn () => $which(redirect_guess_404_permalink()));
register_post_type('zzpub', ['public' => true]);
register_post_type('zzexcl', ['public' => true, 'exclude_from_search' => true]);
register_post_type('zzpq', ['public' => false, 'publicly_queryable' => true]);
$say('a 404 guesses', [
    $guess(['name' => 'zz-guessable']),
    $guess(['name' => 'zz-guessable-p']),
    $guess(['name' => 'zz-guessable-post']),
    $guess(['name' => 'zz-guessable', 'post_type' => 'page']),
    $guess(['name' => 'zz-guessable', 'post_type' => 'post']),
    $guess(['name' => 'zz-guessable', 'year' => 2021]),
    $guess(['name' => 'zz-guessable', 'year' => 2022]),
    $guess(['name' => 'zz-guessable', 'year' => 2021, 'monthnum' => 3]),
    $guess(['name' => 'zz-guessable', 'year' => 2021, 'monthnum' => 4]),
    $guess(['name' => 'zz-guessable', 'year' => 2021, 'monthnum' => 3, 'day' => 4]),
    $guess(['name' => 'zz-guessable', 'year' => 2021, 'monthnum' => 3, 'day' => 5]),
    $guess(['name' => 'zz-guessdraft']),
    $guess(['name' => 'zz-never-anything']),
    $guess(['name' => '']),
    $guess([]),
    $guess(['pagename' => 'zz-guessable']),
    $guess(['name' => 'zz-guessable', 'post_type' => ['page', 'post']]),
    $guess(['name' => 'zz-guessable', 'post_type' => 'zz-no-type']),
    $guess(['name' => 'zz-guessable', 'post_type' => 'attachment']),
    $guess(['name' => 'zz-guessable', 'page' => 2]),
    $guess(['name' => 'Zz-Guessable']),
    $guess(['name' => 'zz_guessable']),
    $guess(['name' => 'zz%guessable']),
    $with('do_redirect_guess_404_permalink', '__return_false', static fn () => $guess(['name' => 'zz-guessable'])),
    $with('pre_redirect_guess_404_permalink', static fn () => 'https://zz.example/pre/', static fn () => $guess(['name' => 'zz-guessable'])),
    $with('pre_redirect_guess_404_permalink', static fn () => false, static fn () => $guess(['name' => 'zz-guessable'])),
    $with('pre_redirect_guess_404_permalink', static fn () => null, static fn () => $guess(['name' => 'zz-guessable'])),
    $with('strict_redirect_guess_404_permalink', '__return_true', static fn () => $guess(['name' => 'zz-guessable'])),
    $with('strict_redirect_guess_404_permalink', '__return_true', static fn () => $guess(['name' => 'zz-guessable-post'])),
    $guess(['name' => 'zz-guessable-post', 'post_type' => 'revision']),
    $guess(['name' => 'zz-guessable-post', 'post_type' => 'wp_block']),
    $guess(['name' => 'zz-guessable-post', 'post_type' => 'zzpq']),
    $guess(['name' => 'zz-guessable-post', 'post_type' => 'zzexcl']),
    $guess(['name' => 'zz-guessable-post', 'post_type' => ['zz-none', 'post']]),
    $guess(['name' => 'zz-guessable-post', 'post_type' => ['zz-none']]),
    $guess(['name' => 'zz-guessable-post', 'post_type' => []]),
    $guess(['name' => 'zz-guessable-post', 'post_type' => ['zzexcl', 'zzpub']]),
    $guess(['name' => 'zz-guessable-post', 'page' => 1]),
    $guess(['name' => 'zz-guessable-post', 'page' => '3']),
    $guess(['name' => 'zz-guessable-post', 'page' => 'x']),
    $guess(['name' => 'zz-guessable-post', 'feed' => 'rss2']),
    $guess(['name' => 'zz-guessable-post', 'feed' => 'feed']),
    $guess(['name' => 'zz-guessable-post', 'feed' => 'atom', 'page' => 3]),
    $guess(['name' => 'zz-guessable-post', 'paged' => 2, 'cpage' => 2, 'attachment' => 'zz-guessable']),
]);

$moved = $post(['post_title' => 'Zz Moved', 'post_name' => 'zz-moved-new', 'post_date' => '2020-01-02 03:04:05']);
$ids[$moved] = 'moved';
add_post_meta($moved, '_wp_old_slug', 'zz-moved-old');
add_post_meta($moved, '_wp_old_slug', 'zz-moved-older');
$redated = $post(['post_title' => 'Zz Redated', 'post_name' => 'zz-redated', 'post_date' => '2020-06-07 08:09:10']);
add_post_meta($redated, '_wp_old_date', '2019-02-03');
$moved_page = $post(['post_title' => 'Zz Moved Page', 'post_name' => 'zz-moved-page-new', 'post_type' => 'page']);
add_post_meta($moved_page, '_wp_old_slug', 'zz-moved-page-old');
$names = [$moved => 'moved', $redated => 'redated', $moved_page => 'moved page'];
$found = static fn (Closure $find) => static fn (array $query, string $type = 'post') => $vars($query, static function () use ($find, $type, $names) {
    $id = $find($type);
    return [gettype($id), $names[$id] ?? $id];
});
$by_slug = $found(static fn ($type) => _find_post_by_old_slug($type));
$by_date = $found(static fn ($type) => _find_post_by_old_date($type));
$say('found by an old slug', [
    $by_slug(['name' => 'zz-moved-old']),
    $by_slug(['name' => 'zz-moved-older']),
    $by_slug(['name' => 'zz-moved-new']),
    $by_slug(['name' => 'zz-moved-old', 'year' => 2020]),
    $by_slug(['name' => 'zz-moved-old', 'year' => 2019]),
    $by_slug(['name' => 'zz-moved-old', 'year' => 2020, 'monthnum' => 1]),
    $by_slug(['name' => 'zz-moved-old', 'year' => 2020, 'monthnum' => 2]),
    $by_slug(['name' => 'zz-moved-old', 'year' => 2020, 'monthnum' => 1, 'day' => 2]),
    $by_slug(['name' => 'zz-moved-old', 'year' => 2020, 'monthnum' => 1, 'day' => 3]),
    $by_slug(['name' => 'zz-moved-old'], 'page'),
    $by_slug(['name' => 'zz-moved-page-old'], 'page'),
    $by_slug(['name' => 'zz-moved-page-old']),
    $by_slug(['name' => 'zz-never-old']),
    $by_slug(['name' => '']),
]);
$say('found by an old date', [
    $by_date(['name' => 'zz-redated', 'year' => 2019]),
    $by_date(['name' => 'zz-redated', 'year' => 2019, 'monthnum' => 2]),
    $by_date(['name' => 'zz-redated', 'year' => 2019, 'monthnum' => 2, 'day' => 3]),
    $by_date(['name' => 'zz-redated', 'year' => 2019, 'monthnum' => 3]),
    $by_date(['name' => 'zz-redated', 'year' => 2020]),
    $by_date(['name' => 'zz-redated']),
    $by_date(['name' => 'zz-moved-old', 'year' => 2019]),
    $by_date(['name' => 'zz-redated', 'year' => 2019], 'page'),
    $by_date(['name' => 'zz-never', 'year' => 2019]),
]);
$queries = static function (Closure $call): array {
    $seen = [];
    $listen = static function ($sql) use (&$seen) {
        if (str_contains((string) $sql, 'zz')) {
            $seen[] = $sql;
        }
        return $sql;
    };
    add_filter('query', $listen);
    try {
        $call();
    } finally {
        remove_filter('query', $listen);
    }
    return $seen;
};
$say('the queries they ask', [
    $queries(static fn () => $vars(['name' => 'zz-guessable'], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $vars(['name' => 'zz-guessable', 'post_type' => 'page', 'year' => 2021, 'monthnum' => 3, 'day' => 4], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $vars(['name' => 'zz-guessable', 'post_type' => ['page', 'post'], 'monthnum' => 3], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $vars(['name' => 'zz-guessable', 'year' => 'abc'], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $with('strict_redirect_guess_404_permalink', '__return_true', static fn () => $vars(['name' => 'zz-guessable'], static fn () => redirect_guess_404_permalink()))),
    $queries(static fn () => $vars(['name' => 'zz-guessable', 'post_type' => 'zz-no-type'], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $vars(['name' => 'zz-guessable', 'post_type' => ['zzexcl', 'zzpub', 'zz-none']], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $vars(['name' => 'zz-guessable', 'post_type' => []], static fn () => redirect_guess_404_permalink())),
    $queries(static fn () => $vars(['name' => 'zz-moved-old'], static fn () => [_find_post_by_old_slug('post'), _find_post_by_old_date('post')])),
    $queries(static fn () => $vars(['name' => 'zz-moved-old', 'year' => 2020, 'monthnum' => 1, 'day' => 2], static fn () => [_find_post_by_old_slug('post'), _find_post_by_old_date('page')])),
    $queries(static fn () => $vars(['name' => 'zz-never-x', 'day' => 9], static fn () => [_find_post_by_old_slug('post'), _find_post_by_old_slug('post'), _find_post_by_old_date('post'), _find_post_by_old_date('post')])),
    $queries(static fn () => $vars(['name' => "zz'q_%", 'year' => 2020], static fn () => [redirect_guess_404_permalink(), _find_post_by_old_slug('post'), _find_post_by_old_date('post')])),
]);

$say('salted cache entries', [
    wp_cache_set_salted('zz-salted', ['a' => 1], 'zz-salt-group', 'salt-1'),
    wp_cache_get('zz-salted', 'zz-salt-group'),
    wp_cache_get_salted('zz-salted', 'zz-salt-group', 'salt-1'),
    wp_cache_get_salted('zz-salted', 'zz-salt-group', 'salt-2'),
    wp_cache_get_salted('zz-never', 'zz-salt-group', 'salt-1'),
    wp_cache_set_salted('zz-salted-list', 'v', 'zz-salt-group', ['s1', 's2']),
    wp_cache_get('zz-salted-list', 'zz-salt-group'),
    wp_cache_get_salted('zz-salted-list', 'zz-salt-group', ['s1', 's2']),
    wp_cache_get_salted('zz-salted-list', 'zz-salt-group', ['s2', 's1']),
    wp_cache_get_salted('zz-salted-list', 'zz-salt-group', 's1s2'),
    wp_cache_get_salted('zz-salted-list', 'zz-salt-group', 's1:s2'),
    wp_cache_set_salted('zz-salted-false', false, 'zz-salt-group', 'x'),
    wp_cache_get_salted('zz-salted-false', 'zz-salt-group', 'x'),
    wp_cache_set_salted('zz-salted-null', null, 'zz-salt-group', 'x'),
    wp_cache_get_salted('zz-salted-null', 'zz-salt-group', 'x'),
    (static function () {
        wp_cache_set('zz-plain', 'plain', 'zz-salt-group');
        wp_cache_set('zz-shaped', ['data' => 'shaped', 'salt' => 'x'], 'zz-salt-group');
        wp_cache_set('zz-shaped-partial', ['data' => 'shaped'], 'zz-salt-group');
        return [wp_cache_get_salted('zz-plain', 'zz-salt-group', 'x'), wp_cache_get_salted('zz-shaped', 'zz-salt-group', 'x'), wp_cache_get_salted('zz-shaped-partial', 'zz-salt-group', 'x')];
    })(),
    wp_cache_set_multiple_salted(['zz-m1' => 1, 'zz-m2' => [2], 'zz-m3' => false], 'zz-salt-group', 'ms'),
    wp_cache_get_multiple(['zz-m1', 'zz-m2'], 'zz-salt-group'),
    wp_cache_get_multiple_salted(['zz-m1', 'zz-m2', 'zz-m3', 'zz-m-never', 'zz-salted'], 'zz-salt-group', 'ms'),
    wp_cache_get_multiple_salted(['zz-m1', 'zz-salted'], 'zz-salt-group', 'salt-1'),
    wp_cache_get_multiple_salted([], 'zz-salt-group', 'ms'),
    wp_cache_set_multiple_salted([], 'zz-salt-group', 'ms'),
]);
$say('old slugs are cached until posts change', (static function () use ($vars, $queries) {
    return $vars(['name' => 'zz-cache-old', 'year' => 2020], static function () use ($queries) {
        $last = static fn () => wp_cache_get_last_changed('posts');
        $first = $queries(static fn () => [_find_post_by_old_slug('post'), _find_post_by_old_date('post')]);
        $again = $queries(static fn () => [_find_post_by_old_slug('post'), _find_post_by_old_date('post')]);
        $other = $queries(static fn () => [_find_post_by_old_slug('page')]);
        $slug_key = 'find_post_by_old_slug:' . md5((string) ($first[0] ?? ''));
        $date_key = 'find_post_by_old_date:' . md5((string) ($first[1] ?? ''));
        $stored = [wp_cache_get_salted($slug_key, 'post-queries', $last()), wp_cache_get_salted($date_key, 'post-queries', $last())];
        wp_cache_set_salted($slug_key, 4242, 'post-queries', $last());
        $planted = _find_post_by_old_slug('post');
        wp_cache_set_posts_last_changed();
        $changed = $queries(static fn () => [_find_post_by_old_slug('post'), _find_post_by_old_date('post')]);
        return [count($first), count($again), count($other), $stored, $planted, count($changed)];
    });
})());

// Admin locations: WP-CLI forces a scheme on 'relative' home and site URLs, so they are made relative again here (a no-op where they already are).
$relative = static fn ($url, $path, $scheme) => $scheme === 'relative' ? (string) preg_replace('#^\w+://[^/]*#', '', (string) $url) : $url;
$redirected = static function () use ($mask): array {
    $heard = [];
    $catch = static function ($location, $status) use (&$heard) {
        $heard[] = [$location, $status];
        throw new RuntimeException('zz-redirect');
    };
    add_filter('wp_redirect', $catch, 1, 2);
    try {
        wp_redirect_admin_locations();
    } catch (RuntimeException $e) {
        $heard[] = $e->getMessage();
    } finally {
        remove_filter('wp_redirect', $catch, 1);
    }
    return $mask($heard);
};
$say('admin locations', (static function () use ($relative, $redirected) {
    global $wp_query;
    add_filter('home_url', $relative, PHP_INT_MAX, 3);
    add_filter('site_url', $relative, PHP_INT_MAX, 3);
    $uri = $_SERVER['REQUEST_URI'] ?? null;
    $out = [];
    $_SERVER['REQUEST_URI'] = '/admin';
    $out['not a 404'] = $redirected();
    $wp_query->set_404();
    foreach (['/admin', '/admin/', '/dashboard', '/dashboard/', '/login', '/login.php', '/wp-admin', '/wp-login.php', '/admin?x=1', '/x/admin', '/ADMIN', '/admin//', '/zz', '/signin', '/wp-login', '/admin.php'] as $path) {
        $_SERVER['REQUEST_URI'] = $path;
        $out[$path] = $redirected();
    }
    $wp_query->is_404 = false;
    $_SERVER['REQUEST_URI'] = $uri;
    remove_filter('home_url', $relative, PHP_INT_MAX);
    remove_filter('site_url', $relative, PHP_INT_MAX);
    return $out;
})());
$say('the hooks', [has_action('template_redirect', 'wp_redirect_admin_locations'), has_action('template_redirect', 'redirect_canonical'), has_action('template_redirect', 'wp_old_slug_redirect')]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
