<?php
/**
 * The request object, WP (probe wp-request): its public vars added,
 * removed and set; a request parsed from the address and the query string
 * (what matched, the vars, the query string built from them, the hooks it
 * ran), with extra vars, a plugin's own var, and a plugin taking the parse
 * over; the headers it would send; the main query it runs, the 404 it
 * decides, the globals it sets; main() and wp() whole. Each case runs on a
 * fresh WP with the address and query string it names; the post the probe
 * makes is removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};

wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ wpr post', 'post_name' => 'zz-wpr-post', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'zz']);
wp_set_current_user(0);
register_shutdown_function(static function () use ($post): void {
    if (is_int($post) && $post > 1) {
        wp_delete_post($post, true);
    }
});
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$mask = static function ($value) use ($post) {
    $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $json = preg_replace('/(?<=[:\[,])' . $post . '(?=[,\]}])/', '"{post}"', $json);
    $json = preg_replace('/(?<![0-9])' . $post . '(?![0-9])/', '{post}', $json);
    return json_decode($json, true);
};

// The hooks the request steps run, in order, each once.
$traced = static function (Closure $run) {
    $names = ['do_parse_request', 'query_vars', 'request', 'parse_request', 'wp_headers', 'send_headers', 'status_header', 'pre_handle_404', 'pre_get_posts', 'wp'];
    $heard = [];
    $listen = static function () use (&$heard, $names) {
        $name = current_filter();
        if (in_array($name, $names, true) && !in_array($name, $heard, true)) {
            $heard[] = $name;
        }
        return func_num_args() ? func_get_arg(0) : null;
    };
    add_filter('all', $listen);
    $result = $run();
    remove_filter('all', $listen);
    return [$result, $heard];
};
// A request as the server would hand it: the address, and its query string as $_GET.
$at = static function (string $address, array $post = []) {
    $_SERVER['REQUEST_URI'] = $address;
    unset($_SERVER['PATH_INFO']);
    parse_str((string) parse_url($address, PHP_URL_QUERY), $get);
    $_GET = $get;
    $_POST = $post;
    $_REQUEST = $get + $post;
};
$parsed = static function (string $address, $extra = '', ?Closure $before = null) use ($at, $traced) {
    $at($address);
    $wp = new WP();
    if ($before !== null) {
        $before($wp);
    }
    [$result, $hooks] = $traced(static fn () => $wp->parse_request($extra));
    return ['answer' => $result, 'vars' => $wp->query_vars, 'query string' => $wp->query_string, 'request' => $wp->request, 'rule' => $wp->matched_rule, 'query' => $wp->matched_query, 'permalink' => $wp->did_permalink, 'extra' => $wp->extra_query_vars, 'hooks' => $hooks];
};

// A die is caught: the handler records what it was given, and the case stops there.
$dies = static function (Closure $run) {
    $handler = static fn () => static function ($message, $title = '', $args = []) {
        throw new RuntimeException((string) json_encode([is_string($message) ? $message : get_class($message), $title, is_array($args) ? ($args['response'] ?? null) : $args]));
    };
    add_filter('wp_die_handler', $handler, PHP_INT_MAX);
    try {
        $out = $run();
    } catch (RuntimeException $e) {
        $out = ['died' => json_decode($e->getMessage(), true)];
    }
    remove_filter('wp_die_handler', $handler, PHP_INT_MAX);
    $_POST = [];
    return $out;
};
// A request with a form posted beside its query string.
$formed = static function (array $form, string $address = '/?s=from-get') use ($at) {
    $at($address, $form);
    $wp = new WP();
    $wp->parse_request();
    return $wp->query_vars;
};

$vars = new WP();
$public = $vars->public_query_vars;
$vars->add_query_var('zz_wpr_var');
$vars->add_query_var('zz_wpr_var');
$added = array_slice($vars->public_query_vars, count($public));
$vars->remove_query_var('zz_wpr_var');
$vars->remove_query_var('s');
$vars->set_query_var('zz_set', 'x');
$vars->set_query_var('p', 3);
$say('its vars', ['public' => $public, 'private' => $vars->private_query_vars, 'added' => $added, 'removed' => [in_array('zz_wpr_var', $vars->public_query_vars, true), in_array('s', $vars->public_query_vars, true), count($vars->public_query_vars) === count($public) - 1], 'set' => $vars->query_vars]);

$say('requests parsed', $mask([
    'the root' => $parsed('/'),
    'a post' => $parsed('/zz-wpr-post/'),
    'a post, paged' => $parsed('/zz-wpr-post/2/'),
    'a category' => $parsed('/category/uncategorized/'),
    'a page of posts' => $parsed('/page/2/'),
    'a month' => $parsed('/2026/10/'),
    'the feed' => $parsed('/feed/'),
    'a post\'s comment feed' => $parsed('/zz-wpr-post/feed/'),
    'a search' => $parsed('/?s=zz+wpr&paged=2'),
    'a search address' => $parsed('/search/zz/'),
    'nothing there' => $parsed('/zz-wpr-nothing/here/'),
    'a query string id' => $parsed('/?p=' . $post),
    'a private var in the query string' => $parsed('/?posts_per_page=3&order=ASC'),
    'an encoded address' => $parsed('/zz%2Dwpr%2Dpost/'),
    'extra vars as text' => $parsed('/', 'posts_per_page=3&zz=1&s=x'),
    'extra vars as a list' => $parsed('/category/uncategorized/', ['posts_per_page' => 2, 'cat' => 1]),
    'no rule fits' => $parsed('/zz/wpr/deep/nothing/at/all/'),
    'the query string over the address' => $parsed('/category/uncategorized/?category_name=zz-other'),
    'extra vars over the query string' => $parsed('/?s=from-get', ['s' => 'from-extra']),
    'the form beside the query string' => $dies(static fn () => $formed(['paged' => '3', 's' => 'from-get'])),
    'the form disagreeing with the query string' => $dies(static fn () => $formed(['s' => 'from-post'])),
    'the form disagreeing on a private var' => $dies(static fn () => $formed(['posts_per_page' => '9'], '/?s=from-get&posts_per_page=2')),
    'an array in the query string' => $dies(static fn () => $parsed('/?cat[]=1&cat[]=2&s=zz')),
    'an array for the post type' => $dies(static fn () => $parsed('/?post_type[]=post&post_type[]=page&post_type[]=zz')),
    'a page by post type' => $parsed('/?post_type=page'),
    'an unknown post type' => $parsed('/?post_type=zz'),
    'posts by post type' => $parsed('/?post_type=post&paged=2'),
    'a post type with a name' => $parsed('/?post_type=post&name=zz-wpr-post'),
    'an array for a single var' => $dies(static fn () => $parsed('/?p[]=1&name[]=zz')),
    'a plugin\'s own var' => $parsed('/?zz_wpr_var=on', '', static fn (WP $wp) => $wp->add_query_var('zz_wpr_var')),
    'a var it removed' => $parsed('/?s=zz', '', static fn (WP $wp) => $wp->remove_query_var('s')),
    'a plugin takes it over' => (static function () use ($parsed) {
        $take = static fn () => false;
        add_filter('do_parse_request', $take);
        $out = $parsed('/zz-wpr-post/');
        remove_filter('do_parse_request', $take);
        return $out;
    })(),
    'the request filter' => (static function () use ($parsed) {
        $change = static fn (array $vars) => $vars + ['zz_added' => 'y'];
        add_filter('request', $change);
        $out = $parsed('/zz-wpr-post/');
        remove_filter('request', $change);
        return $out;
    })(),
]));

// The query string built from the vars, and the deprecated filter that may rewrite it.
$built = static function (array $vars) {
    $wp = new WP();
    $wp->query_vars = $vars;
    $wp->build_query_string();
    return [$wp->query_string, $wp->query_vars];
};
$say('query strings', [
    'some vars' => $built(['name' => 'a b', 'paged' => '2', 'empty' => '', 'zero' => '0']),
    'none' => $built([]),
]);

// The headers it would send: the wp_headers filter sees them, the status what status_header was given.
$headers = static function (string $address, int $user = 0) use ($at) {
    $at($address);
    wp_set_current_user($user);
    $wp = new WP();
    $wp->parse_request();
    $seen = [];
    $look = static function ($headers, $given) use (&$seen) {
        $seen['headers'] = $headers;
        $seen['the object'] = $given instanceof WP;
        return $headers;
    };
    $status = static function ($header, $code) use (&$seen) {
        $seen['status'] = $code;
        return $header;
    };
    add_filter('wp_headers', $look, 10, 2);
    add_filter('status_header', $status, 10, 2);
    $wp->send_headers();
    remove_filter('wp_headers', $look, 10);
    remove_filter('status_header', $status, 10);
    wp_set_current_user(0);
    if (isset($seen['headers']['Expires'])) {
        $seen['headers']['Expires'] = '{date}';
    }
    return $seen;
};
$say('headers', [
    'a post, for a visitor' => $headers('/zz-wpr-post/'),
    'a post, signed in' => $headers('/zz-wpr-post/', 1),
    'nothing there' => $headers('/zz-wpr-nothing/here/'),
    'nothing there, signed in' => $headers('/zz-wpr-nothing/here/', 1),
]);

// The main query it runs, the 404 it decides, the globals it sets.
$ran = static function (string $address) use ($at, $traced) {
    $at($address);
    $wp = new WP();
    $wp->init();
    $wp->parse_request();
    [, $hooks] = $traced(static function () use ($wp) {
        $wp->query_posts();
        $wp->handle_404();
        $wp->register_globals();
    });
    global $wp_query;
    return [
        'query string' => $wp->query_string,
        'posts' => array_map(static fn ($post) => $post->post_name, $wp_query->posts),
        'found' => (int) $wp_query->found_posts,
        'flags' => ['single' => $wp_query->is_single(), 'home' => $wp_query->is_home(), 'archive' => $wp_query->is_archive(), 'search' => $wp_query->is_search(), 'paged' => $wp_query->is_paged(), '404' => $wp_query->is_404(), 'feed' => $wp_query->is_feed()],
        'globals' => ['name' => $GLOBALS['name'] ?? null, 'query_string' => $GLOBALS['query_string'] ?? null, 'post' => isset($GLOBALS['post']->post_name) ? $GLOBALS['post']->post_name : null, 'more' => $GLOBALS['more'] ?? null, 'single' => $GLOBALS['single'] ?? null],
        'hooks' => $hooks,
    ];
};
$say('the main query', $mask([
    'a post' => $ran('/zz-wpr-post/'),
    'a category' => $ran('/category/uncategorized/'),
    'a page past the last' => $ran('/page/999/'),
    'nothing there' => $ran('/zz-wpr-nothing/here/'),
    'a search with nothing' => $ran('/?s=zz-wpr-none-at-all'),
]));

// main() and wp() whole: the hooks in order, and what the main query became.
$whole = static function (Closure $run, string $address) use ($at, $traced) {
    $at($address);
    [, $hooks] = $traced($run);
    global $wp_query;
    return ['hooks' => $hooks, 'posts' => array_map(static fn ($post) => $post->post_name, $wp_query->posts), 'single' => $wp_query->is_single(), 'the query' => $GLOBALS['wp_the_query'] === $wp_query];
};
$say('main and wp()', [
    'main' => $whole(static fn () => (new WP())->main(), '/zz-wpr-post/'),
    'main with vars' => $whole(static fn () => (new WP())->main('posts_per_page=1'), '/'),
    'wp()' => $whole(static function () {
        $GLOBALS['wp'] = new WP();
        return wp();
    }, '/zz-wpr-post/'),
    'wp() with vars' => $whole(static function () {
        $GLOBALS['wp'] = new WP();
        return wp(['posts_per_page' => 1, 's' => 'zz wpr']);
    }, '/'),
]);
$_SERVER['REQUEST_URI'] = '/';
$_GET = $_POST = $_REQUEST = [];

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
