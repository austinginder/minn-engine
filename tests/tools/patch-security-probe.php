<?php
/**
 * The behaviour WordPress's 7.1.1 to 7.1.3 security releases changed, where
 * Minn reimplements it (probe patch-security): template paths that walk out
 * of their folder, sticky posts through REST and the stuck hooks, creating
 * a post with an id, comment text in the HTML API, the oEmbed providers
 * (Imgur no longer trusted), a custom header's size, the status transition
 * hooks, and the REST template list's filters. The probe's users, posts
 * and HTTP answers are its own and are removed; sticky_posts is put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'posts' => []];
$sticky = get_option('sticky_posts');
register_shutdown_function(static function () use (&$made, $sticky): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
    update_option('sticky_posts', $sticky);
});
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong) {
    $wrong[] = [$function, wp_strip_all_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$theme = get_template_directory();
$mask = static function ($value) use ($theme, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace([$theme, ABSPATH], ['{theme}', '{abs}/'], $value) : $value;
};

$say('template paths', $mask([
    array_map(static fn ($path) => [$path, _wp_is_template_path_allowed($path)], ['index.php', 'templates/single.html', '../twentytwentyfour/index.php', 'x/../y', 'x/..', '..', '...', 'a/.../b', 'a..b/c', '..b/c', 'b../c', './a', 'a/./b', '..\\x', 'a\\..\\b', 'a\\..b', '/etc/passwd', 'C:/x', '', 'phar://x.phar/a']),
    locate_template(['functions.php']),
    locate_template(['../twentytwentyfour/functions.php']),
    locate_template(['templates/../functions.php']),
    locate_template(['../twentytwentyfour/functions.php', 'functions.php']),
    locate_template(['.../functions.php', './functions.php']),
]));

$user = static function (string $role) use (&$made) {
    $id = wp_insert_user(['user_login' => 'zz-patch-' . $role, 'user_pass' => wp_generate_password(), 'user_email' => "zz-patch-{$role}@example.invalid", 'role' => $role]);
    $made['users'][] = $id;
    return $id;
};
$post = static function (array $args) use (&$made) {
    $id = wp_insert_post($args + ['post_title' => 'Zz patch', 'post_status' => 'publish']);
    $made['posts'][] = $id;
    return $id;
};
$rest = static function (string $method, string $route, array $params = []) use (&$made) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    $data = $response->get_data();
    if ($method === 'POST' && $response->get_status() === 201 && isset($data['id'])) {
        $made['posts'][] = $data['id'];
    }
    return [$response->get_status(), isset($data['code']) ? [$data['code'], $data['message']] : ['sticky' => $data['sticky'] ?? null]];
};
$author = $user('author');
$editor = $user('editor');
$contributor = $user('contributor');
$authorsOwn = $post(['post_author' => $author]);
$authorsStuck = $post(['post_author' => $author]);
stick_post($authorsStuck);
$contributorsOwn = $post(['post_author' => $contributor, 'post_status' => 'draft']);
$say('sticky through REST', (static function () use ($rest, $author, $editor, $contributor, $authorsOwn, $authorsStuck, $contributorsOwn) {
    $out = [];
    wp_set_current_user($author);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsOwn}", ['sticky' => true]);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsOwn}", ['sticky' => false]);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsStuck}", ['sticky' => false]);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsStuck}", ['sticky' => true]);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsStuck}", ['title' => 'Zz renamed']);
    $out[] = $rest('POST', '/wp/v2/posts', ['title' => 'Zz author sticky', 'status' => 'publish', 'sticky' => true]);
    $out[] = $rest('POST', '/wp/v2/posts', ['title' => 'Zz author plain', 'sticky' => false]);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsOwn}", ['sticky' => true, 'status' => 'zz-bogus']);
    wp_set_current_user($editor);
    $out[] = $rest('POST', "/wp/v2/posts/{$authorsOwn}", ['sticky' => true]);
    wp_set_current_user($contributor);
    $out[] = $rest('POST', "/wp/v2/posts/{$contributorsOwn}", ['sticky' => true]);
    wp_set_current_user(0);
    $out[] = [is_sticky($authorsOwn), is_sticky($authorsStuck)];
    return $out;
})());
$say('creating a post with an id', (static function () use ($rest, $contributor, $authorsOwn) {
    $out = [];
    wp_set_current_user($contributor);
    $out[] = $rest('POST', '/wp/v2/posts', ['id' => $authorsOwn, 'title' => 'Zz with id']);
    $out[] = $rest('POST', '/wp/v2/posts', ['id' => 999999999, 'title' => 'Zz missing id']);
    $out[] = $rest('POST', '/wp/v2/posts', ['id' => 0, 'title' => 'Zz zero id']);
    wp_set_current_user(0);
    $out[] = $rest('POST', '/wp/v2/posts', ['id' => $authorsOwn, 'title' => 'Zz anon id']);
    $out[] = $rest('POST', '/wp/v2/posts', ['title' => 'Zz anon']);
    return $out;
})());
$say('the stuck hooks', (static function () use ($post) {
    $id = $post([]);
    $log = [];
    $listen = static function ($stuck) use (&$log, $id) {
        $log[] = [current_filter(), $stuck === $id];
    };
    add_action('post_stuck', $listen);
    add_action('post_unstuck', $listen);
    stick_post($id);
    stick_post((string) $id);
    unstick_post($id);
    unstick_post($id);
    stick_post($id);
    wp_trash_post($id);
    $trashed = is_sticky($id);
    wp_untrash_post($id);
    stick_post($id);
    wp_delete_post($id, true);
    remove_action('post_stuck', $listen);
    remove_action('post_unstuck', $listen);
    return [$log, $trashed, is_sticky($id)];
})());

$say('comment text in the HTML API', array_map(static function ($text) use (&$wrong) {
    $wrong = [];
    $processor = new WP_HTML_Tag_Processor('<p>1</p><!-- old --><p>2</p>');
    $processor->next_tag();
    $processor->next_token();
    $processor->next_token();
    $processor->next_token();
    return [$text, $processor->set_modifiable_text($text), $processor->get_updated_html(), $wrong];
}, ['a-->', 'a--!>', 'a--!><img src=x onerror=alert(1)>', 'a --!> b', 'a--!', 'a--', '<!-- x -->', 'a-- >']));
$wrong = [];

$say('oEmbed providers', (static function () {
    $providers = _wp_oembed_get_object()->providers;
    add_filter('pre_http_request', $answer = static function ($pre, $args, $url) {
        if (!str_contains($url, 'imgur')) {
            return $pre;
        }
        $body = str_contains($url, 'oembed') ? wp_json_encode(['type' => 'rich', 'version' => '1.0', 'html' => '<blockquote class="imgur-embed-pub" data-id="a/x" onclick="alert(2)"><a href="javascript:alert(3)">Zz</a></blockquote><script src="//s.imgur.com/min/embed.js"></script>']) : '<link rel="alternate" type="application/json+oembed" href="https://api.imgur.com/oembed.json?url=https://imgur.com/a/x">';
        return ['headers' => ['content-type' => str_contains($url, 'oembed') ? 'application/json' : 'text/html'], 'body' => $body, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }, 10, 3);
    $out = [count($providers), array_values(array_filter(array_keys($providers), static fn ($pattern) => (bool) preg_match('/imgur|someecards|reverbnation/', $pattern))), wp_oembed_get('https://imgur.com/a/x'), (new WP_oEmbed())->get_provider('https://imgur.com/a/x', ['discover' => false])];
    remove_filter('pre_http_request', $answer, 10);
    return $out;
})());

$say('a custom header\'s size', (static function () {
    $data = static fn () => (object) ['url' => 'https://zz.example/h.jpg', 'thumbnail_url' => 'https://zz.example/t.jpg', 'width' => '1" onerror="alert(1)', 'height' => '<b>2', 'attachment_id' => 0];
    add_filter('theme_mod_header_image_data', $data);
    add_filter('theme_mod_header_image', $image = static fn () => 'https://zz.example/h.jpg');
    add_theme_support('custom-header', ['width' => 100, 'height' => 50]);
    $out = [get_custom_header(), get_header_image_tag()];
    remove_theme_support('custom-header');
    $out[] = get_custom_header();
    remove_filter('theme_mod_header_image_data', $data);
    remove_filter('theme_mod_header_image', $image);
    return $out;
})());

$say('status transition hooks', (static function () use ($post) {
    register_post_status('zz_patch_reg', ['label' => 'Zz Patch']);
    register_post_status('wp_insert', ['label' => 'Wp Insert']);
    register_post_type('zz_patch_type', ['public' => true]);
    $fired = [];
    $hooks = ['draft_post', 'publish_post', 'zz_patch_reg_post', 'wp_insert_post', 'zz_post', 'zz-odd_post', 'new_post', 'delete_post', 'publish_zz_patch_type', 'publish_zz_unregistered', 'wp_insert_post_type', 'new_to_publish', 'draft_to_zz', 'zz_to_draft', 'draft_to_publish', 'new_to_zz', 'auto-draft_to_publish', 'new_to_inherit'];
    foreach ($hooks as $hook) {
        add_action($hook, static function () use ($hook, &$fired) {
            $fired[] = $hook;
        }, 10, 0);
    }
    $subject = get_post($post(['post_status' => 'draft']));
    $run = static function (string $new, string $old, WP_Post $subject) use (&$fired) {
        $fired = [];
        wp_transition_post_status($new, $old, $subject);
        return $fired;
    };
    $out = [];
    foreach ([['draft', 'draft'], ['publish', 'draft'], ['zz_patch_reg', 'draft'], ['wp_insert', 'draft'], ['zz', 'draft'], ['zz-odd', 'draft'], ['new', 'draft'], ['delete', 'draft'], ['draft', 'zz'], ['publish', 'new'], ['zz', 'new'], ['publish', 'auto-draft'], ['inherit', 'new']] as [$new, $old]) {
        $out[] = [$new, $old, $run($new, $old, $subject)];
    }
    foreach (['zz_patch_type', 'zz_unregistered', 'post_type'] as $type) {
        $typed = clone $subject;
        $typed->post_type = $type;
        $out[] = [$type, $run('publish', 'draft', $typed), $run('wp_insert', 'draft', $typed)];
    }
    return $out;
})());

$say('the REST template lists', (static function () {
    wp_set_current_user(1);
    $list = static function (string $route, array $params) {
        $request = new WP_REST_Request('GET', $route);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        $response = rest_do_request($request);
        $data = $response->get_data();
        return [$response->get_status(), $data['code'] ?? array_map(static fn ($row) => $row['slug'] ?? null, (array) $data)];
    };
    $out = [];
    foreach ([[], ['post_type' => 'page'], ['post_type' => 'post'], ['post_type' => '../x'], ['wp_id' => 0], ['wp_id' => 5], ['wp_id' => 'abc'], ['post_type' => ['page']], ['area' => 'header']] as $params) {
        $out[] = [$params, $list('/wp/v2/templates', $params)];
    }
    foreach ([['area' => 'header'], ['area' => 'zz'], ['post_type' => 'page'], ['wp_id' => 0]] as $params) {
        $out[] = [$params, $list('/wp/v2/template-parts', $params)];
    }
    wp_set_current_user(0);
    return $out;
})());

$say('calls done wrong along the way', $wrong);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
