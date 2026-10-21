<?php
/**
 * The facade review's doubts about the navigation helpers, POMO, the
 * deprecated user meta functions, WP_Embed, the text and HTML widgets, the
 * REST server, response and controllers, and the translation registry,
 * each measured on both stacks and matched
 * (~/Documents/minn-facade-review/doubts-2026-10-20/h-result.md). Same
 * protocol as api-probe.php; every row the probe makes (marker zzdoubth) is
 * removed in the finally blocks.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$section = static function (string $name, callable $run) use ($say): void {
    try {
        $run();
    } catch (Throwable $e) {
        $say("{$name}: threw", get_class($e) . ': ' . $e->getMessage());
    }
};
$deprecated = [];
$wrong = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong) {
    $wrong[] = [$function, $version];
}, 10, 3);
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('doing_it_wrong_trigger_error', '__return_false');
$posts = [];
$guard = static function ($id) {
    return is_int($id) && $id > 1 ? $id : null;
};

// The navigation helpers.
$section('navigation', static function () use ($say, &$deprecated, $guard, $wpdb) {
    $existing = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wp_navigation'"));
    $hide = static function ($query) use ($existing): void {
        if ($query->get('post_type') === 'wp_navigation' && $existing !== []) {
            $query->set('post__not_in', $existing);
        }
    };
    $menus = [];
    $made = [];
    $pages = [];
    $locations = get_theme_mod('nav_menu_locations');
    $front = [get_option('show_on_front'), get_option('page_on_front')];
    $newNavigations = static function () use ($wpdb, $existing, &$made) {
        $placeholders = $existing === [] ? '0' : implode(',', $existing);
        $new = [];
        foreach ((array) $wpdb->get_results("SELECT ID, post_title, post_name, post_status, post_content FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND ID NOT IN ({$placeholders}) ORDER BY ID") as $row) {
            if (!in_array((int) $row->ID, $made, true) && (int) $row->ID > 1) {
                $made[] = (int) $row->ID;
                $new[] = [$row->post_title, $row->post_name, $row->post_status, (string) preg_replace('/"id":\d+/', '"id":N', $row->post_content)];
            }
        }
        return $new;
    };
    $menuName = static fn ($menu) => is_object($menu) ? $menu->name : $menu;
    try {
        add_action('pre_get_posts', $hide);
        $a = $menus[] = wp_create_nav_menu('zzdoubth-menu-a');
        $b = $menus[] = wp_create_nav_menu('zzdoubth-menu-b');
        $pageA = $pages[] = $guard(wp_insert_post(['post_type' => 'page', 'post_title' => 'zzdoubth page a', 'post_status' => 'publish']));
        if (!is_int($a) || !is_int($b) || !$pageA) {
            throw new RuntimeException('fixtures');
        }
        $top = wp_update_nav_menu_item($a, 0, ['menu-item-title' => 'zzdoubth link', 'menu-item-url' => 'https://example.com/zz', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom']);
        wp_update_nav_menu_item($a, 0, ['menu-item-title' => 'zzdoubth child', 'menu-item-url' => 'https://example.com/zz/child', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => is_int($top) ? $top : 0]);
        $deprecated = [];
        set_theme_mod('nav_menu_locations', ['main' => $a]);
        $say('classic fallback: a menu in main (newest wins)', $menuName(block_core_navigation_get_classic_menu_fallback()));
        set_theme_mod('nav_menu_locations', ['menu-1' => $a]);
        $say('classic fallback: a menu in menu-1', $menuName(block_core_navigation_get_classic_menu_fallback()));
        set_theme_mod('nav_menu_locations', ['primary' => $a]);
        $say('classic fallback: a menu in primary', $menuName(block_core_navigation_get_classic_menu_fallback()));
        set_theme_mod('nav_menu_locations', []);
        $primary = $menus[] = wp_create_nav_menu('primary');
        $c = $menus[] = wp_create_nav_menu('zzdoubth-menu-c');
        $say('classic fallback: a menu slugged primary before a newer one', $menuName(block_core_navigation_get_classic_menu_fallback()));
        $say('classic fallback: deprecation notices', $deprecated);
        wp_delete_nav_menu($primary);
        wp_delete_nav_menu($c);

        // The fallback blocks: no navigation and a classic menu in primary makes one from it.
        set_theme_mod('nav_menu_locations', ['primary' => $a]);
        $deprecated = [];
        $blocks = block_core_navigation_get_fallback_blocks();
        $say('fallback blocks: no navigation, a classic menu', [array_map(static fn ($block) => $block['blockName'], (array) $blocks), $newNavigations(), $deprecated]);
        $clear = static function () use (&$made) {
            foreach ($made as $id) {
                wp_delete_post($id, true);
            }
            $made = [];
        };
        $clear();
        // An empty classic menu: no navigation is made; the page list only while registered.
        set_theme_mod('nav_menu_locations', ['primary' => $b]);
        $deprecated = [];
        $say('maybe_use_classic_menu_fallback with an empty menu', [block_core_navigation_maybe_use_classic_menu_fallback(), $newNavigations(), $deprecated]);
        $clear();
        $say('fallback blocks: no navigation, an empty classic menu', [block_core_navigation_get_fallback_blocks(), $newNavigations()]);
        $clear();
        $registry = WP_Block_Type_Registry::get_instance();
        $pageList = $registry->get_registered('core/page-list');
        if ($pageList) {
            $registry->unregister('core/page-list');
            try {
                $say('fallback blocks: page list not registered', [block_core_navigation_get_fallback_blocks(), $newNavigations()]);
                $clear();
            } finally {
                $registry->register($pageList);
            }
        }
        $never = static fn () => false;
        add_filter('wp_navigation_should_create_fallback', $never);
        $say('fallback blocks: told not to make one', [block_core_navigation_get_fallback_blocks(), $newNavigations()]);
        remove_filter('wp_navigation_should_create_fallback', $never);
        $clear();
        set_theme_mod('nav_menu_locations', ['primary' => $a]);
        $deprecated = [];
        $made_post = block_core_navigation_maybe_use_classic_menu_fallback();
        $say('maybe_use_classic_menu_fallback with items', [is_object($made_post) ? [$made_post->post_title, $made_post->post_status] : $made_post, $newNavigations(), $deprecated]);
        $clear();
        $deprecated = [];
        $menuObject = wp_get_nav_menu_object($a);
        $serialized = block_core_navigation_get_classic_menu_fallback_blocks($menuObject);
        $items = wp_get_nav_menu_items($a);
        $parsedItems = block_core_navigation_parse_blocks_from_menu_items($items, [0 => $items]);
        $say('classic menu blocks and parsed items: deprecation notices', [$serialized === '' ? '' : 'markup', count($parsedItems), $deprecated]);
        $deprecated = [];

        // The newest navigation through the query's filters.
        $n1 = $made[] = $guard(wp_insert_post(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'zzdoubth nav one', 'post_date' => '2001-01-01 00:00:00']));
        $n2 = $made[] = $guard(wp_insert_post(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'zzdoubth nav two', 'post_date' => '2001-01-02 00:00:00']));
        $where = static function ($sql) use ($wpdb, $n2) {
            return $sql . ' AND ' . $wpdb->posts . '.ID <> ' . (int) $n2;
        };
        add_filter('posts_where', $where);
        $newest = block_core_navigation_get_most_recently_published_navigation();
        remove_filter('posts_where', $where);
        $say('most recent navigation with posts_where hiding the newest', $newest ? $newest->post_title : $newest);
        $say('most recent navigation', ($newest = block_core_navigation_get_most_recently_published_navigation()) ? $newest->post_title : null);
        $empty = $made[] = $guard(wp_insert_post(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'zzdoubth nav empty', 'post_date' => '2001-01-03 00:00:00', 'post_content' => '']));
        $say('fallback blocks: the newest navigation is empty', [array_map(static fn ($block) => $block['blockName'], (array) block_core_navigation_get_fallback_blocks()), $newNavigations()]);
    } finally {
        remove_action('pre_get_posts', $hide);
        foreach ($made as $id) {
            if (is_int($id) && $id > 1) {
                wp_delete_post($id, true);
            }
        }
        foreach (array_filter($menus, 'is_int') as $menu) {
            wp_delete_nav_menu($menu);
        }
        foreach (array_filter($pages) as $page) {
            wp_delete_post($page, true);
        }
        set_theme_mod('nav_menu_locations', $locations);
        if ($locations === false) {
            remove_theme_mod('nav_menu_locations');
        }
        update_option('show_on_front', $front[0]);
        update_option('page_on_front', $front[1]);
    }
});

$section('navigation pure', static function () use ($say, $guard) {
    // The post ids inside a navigation's blocks.
    $parsed = parse_blocks('<!-- wp:navigation-submenu {"kind":"post-type","id":5} --><!-- wp:navigation-link {"kind":"post-type","id":5} /--><!-- wp:navigation-link {"kind":"post-type","id":7} /--><!-- wp:navigation-link {"kind":"taxonomy","id":9} /--><!-- wp:navigation-link {"kind":"custom","id":11} /--><!-- /wp:navigation-submenu --><!-- wp:navigation-link {"kind":"post-type","id":5} /--><!-- wp:navigation-link {"type":"page","id":13} /-->');
    $list = new WP_Block_List($parsed);
    $say('get_post_ids', block_core_navigation_get_post_ids($list));
    $say('from_block_get_post_ids of the submenu', block_core_navigation_from_block_get_post_ids($list[0]));
    $say('get_post_ids keeps the first keys', block_core_navigation_get_post_ids(new WP_Block_List(parse_blocks('<!-- wp:navigation-link {"kind":"post-type","id":5} /--><!-- wp:navigation-link {"kind":"post-type","id":5} /--><!-- wp:navigation-link {"kind":"post-type","id":7} /--><!-- wp:navigation-link {"kind":"post-type","id":"7"} /-->'))));

    $say('typographic presets', array_map(static fn ($value) => block_core_navigation_typographic_presets_backcompatibility(['blockName' => 'core/navigation', 'attrs' => ['style' => ['typography' => ['textDecoration' => $value, 'fontStyle' => 'var:preset|font-style|italic', 'fontWeight' => 'var:preset|font-weight|700', 'textTransform' => 'var:preset|text-transform|uppercase']]]])['attrs']['style']['typography'], ['strikethrough', 'var:preset|text-decoration|strikethrough', 'underline', 'var:preset|text-decoration|underline', 'none']));

    // A link's colours.
    $contexts = [
        'main only' => ['textColor' => 'red', 'backgroundColor' => 'blue'],
        'main custom only' => ['customTextColor' => '#111', 'customBackgroundColor' => '#222'],
        'style colours' => ['style' => ['color' => ['text' => '#333', 'background' => '#444']]],
        'named and custom' => ['textColor' => 'red', 'customTextColor' => '#555', 'backgroundColor' => 'blue', 'customBackgroundColor' => '#666'],
        'named and style' => ['textColor' => 'red', 'style' => ['color' => ['text' => '#777']]],
        'overlay' => ['textColor' => 'red', 'overlayTextColor' => 'green', 'customOverlayBackgroundColor' => '#888'],
        'overlay custom and named' => ['overlayTextColor' => 'green', 'customOverlayTextColor' => '#999'],
        'nothing' => [],
        'custom and style' => ['customTextColor' => '#121', 'style' => ['color' => ['text' => '#343', 'background' => '#454']], 'customBackgroundColor' => '#565'],
        'overlay text, main background' => ['overlayTextColor' => 'green', 'backgroundColor' => 'blue', 'customTextColor' => '#123'],
        'overlay background only' => ['customOverlayBackgroundColor' => '#abc', 'textColor' => 'red'],
        'style background only' => ['style' => ['color' => ['background' => '#def']]],
    ];
    $rows = [];
    foreach ($contexts as $name => $context) {
        $rows[$name] = [block_core_navigation_link_build_css_colors($context, [], false), block_core_navigation_link_build_css_colors($context, [], true), block_core_page_list_build_css_colors([], $context)];
    }
    $say('link and page list colours', $rows);

    // The variations' order.
    register_post_type('zz_cpt', ['public' => true, 'show_in_nav_menus' => true, 'label' => 'Zz']);
    register_taxonomy('zz_tax', 'post', ['public' => true, 'show_in_nav_menus' => true, 'label' => 'Zz tax']);
    try {
        $say('link variations order', array_column(block_core_navigation_link_build_variations(), 'name'));
    } finally {
        unregister_taxonomy('zz_tax');
        unregister_post_type('zz_cpt');
    }

    // Whether an item renders.
    $draft = $guard(wp_insert_post(['post_title' => 'zzdoubth draft', 'post_status' => 'draft']));
    $live = $guard(wp_insert_post(['post_title' => 'zzdoubth live', 'post_status' => 'publish']));
    try {
        $cases = [
            'page draft, no kind' => ['type' => 'page', 'id' => $draft],
            'post draft, no kind' => ['type' => 'post', 'id' => $draft],
            'custom draft' => ['kind' => 'custom', 'id' => $draft],
            'taxonomy, a draft id' => ['kind' => 'taxonomy', 'id' => $draft],
            'post-type draft' => ['kind' => 'post-type', 'id' => $draft],
            'post-type live' => ['kind' => 'post-type', 'id' => $live],
            'post-type missing' => ['kind' => 'post-type', 'id' => 999999999],
            'no id' => ['kind' => 'post-type'],
            'id as a string' => ['kind' => 'post-type', 'id' => (string) $draft],
            'custom kind, page type' => ['kind' => 'custom', 'type' => 'page', 'id' => $draft],
            'unknown type, no kind' => ['type' => 'zz', 'id' => $draft],
            'taxonomy kind, page type' => ['kind' => 'taxonomy', 'type' => 'page', 'id' => $draft],
            'category type, no kind' => ['type' => 'category', 'id' => $draft],
            'no kind, no type' => ['id' => $draft],
            'post-type kind, custom type' => ['kind' => 'post-type', 'type' => 'custom', 'id' => $draft],
            'page live, no kind' => ['type' => 'page', 'id' => $live],
        ];
        $rows = [];
        foreach ($cases as $name => $attributes) {
            $rows[$name] = block_core_shared_navigation_item_should_render($attributes, null);
        }
        $heard = [];
        // The draft's id is named for what it is, any other id masked (ids grow past any fixed bound).
        $allow = static function ($statuses) use (&$heard, $draft) {
            $heard[] = array_map(static fn ($arg) => is_object($arg) ? get_class($arg) : (is_array($arg) && isset($arg['id']) ? ['id' => (int) $arg['id'] === (int) $draft ? 'draft' : 'N'] + $arg : $arg), func_get_args());
            return array_merge((array) $statuses, ['draft']);
        };
        add_filter('render_block_core_navigation_link_allowed_post_status', $allow, 10, 3);
        $rows['draft allowed'] = block_core_shared_navigation_item_should_render(['kind' => 'post-type', 'id' => $draft], null);
        $rows['draft allowed, custom'] = block_core_shared_navigation_item_should_render(['kind' => 'custom', 'id' => $draft], null);
        $rows['draft allowed, missing post'] = block_core_shared_navigation_item_should_render(['kind' => 'post-type', 'id' => 999999999], null);
        remove_filter('render_block_core_navigation_link_allowed_post_status', $allow, 10);
        $rows['filter heard'] = $heard;
        $say('should_render', $rows);
    } finally {
        foreach ([$draft, $live] as $id) {
            if ($id) {
                wp_delete_post($id, true);
            }
        }
    }
});

$section('page list', static function () use ($say, $guard) {
    $front = [get_option('show_on_front'), get_option('page_on_front')];
    $home = $guard(wp_insert_post(['post_type' => 'page', 'post_title' => 'zzdoubth home', 'post_status' => 'publish']));
    try {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home);
        $pages = [
            $home => ['page_id' => $home, 'title' => 'zzdoubth home', 'link' => 'https://example.com/home', 'is_active' => false],
            7 => ['page_id' => 7, 'title' => '', 'link' => 'https://example.com/empty', 'is_active' => true],
        ];
        $html = block_core_page_list_render_nested_page_list('hover', true, false, $pages, false);
        $html2 = block_core_page_list_render_nested_page_list('click', true, true, $pages, true, [], ['overlay_css_classes' => ['zz-o'], 'overlay_inline_styles' => 'color: red;']);
        update_option('show_on_front', 'posts');
        $html3 = block_core_page_list_render_nested_page_list('hover', false, false, $pages, false);
        $say('nested page list: the front page and an empty title', str_replace((string) $home, 'HOME', [$html, $html2, $html3]));
    } finally {
        update_option('show_on_front', $front[0]);
        update_option('page_on_front', $front[1]);
        if ($home) {
            wp_delete_post($home, true);
        }
    }
});

$section('pomo', static function () use ($say) {
    $gettext = new Gettext_Translations();
    $say('parenthesize_plural_exression', array_map([$gettext, 'parenthesize_plural_exression'], ['n==1 ? 0 : 1;', 'n==1 ? 0 : 1; x', 'n==1 ? 0 : 1', 'n==1 ? 0 : n==2 ? 1 : 2;', 'a ? b : c; d ? e : f', ';', 'n;', 'n ? 1 : 0;;']));

    $entry = new Translation_Entry(['singular' => 'a', 'foo' => 'bar', 'is_plural' => true]);
    $plural = new Translation_Entry(['singular' => 'a', 'plural' => 'as', 'is_plural' => false]);
    $odd = new Translation_Entry(['singular' => 'a', 'translations' => 'x', 'flags' => 'f', 'context' => 'c']);
    $say('Translation_Entry::__construct', [get_object_vars($entry), $plural->is_plural, get_object_vars($odd), get_object_vars(new Translation_Entry(['plural' => 'p', 'foo' => 1]))]);

    $one = new Translation_Entry(['singular' => 'm', 'flags' => ['a', 'b'], 'references' => ['r1', 'r2'], 'extracted_comments' => 'x']);
    $two = new Translation_Entry(['singular' => 'm', 'flags' => ['b', 'c'], 'references' => ['r2', 'r3'], 'extracted_comments' => 'y']);
    $one->merge_with($two);
    $say('Translation_Entry::merge_with', [$one->flags, $one->references, $one->extracted_comments]);

    $rows = [];
    foreach (['Translations', 'MO', 'Gettext_Translations'] as $class) {
        $t = new $class();
        $t->add_entry(['singular' => 'empty', 'translations' => ['']]);
        $t->add_entry(['singular' => 'emptyx', 'translations' => ['', 'x']]);
        $t->add_entry(['singular' => 'none', 'translations' => []]);
        $t->add_entry(['singular' => 'full', 'translations' => ['F']]);
        $t->add_entry(['singular' => 'one', 'plural' => 'ones', 'translations' => ['uno', '']]);
        $t->add_entry(['singular' => 'two', 'plural' => 'twos', 'translations' => ['', 'dos']]);
        $t->add_entry(['singular' => 'three', 'plural' => 'threes', 'translations' => ['tres']]);
        $t->add_entry(['singular' => 'four', 'plural' => 'fours', 'translations' => []]);
        $rows[$class] = [
            array_map(static fn ($s) => $t->translate($s), ['empty', 'emptyx', 'none', 'full', 'missing']),
            array_map(static fn ($c) => [$t->translate_plural('one', 'ones', $c), $t->translate_plural('two', 'twos', $c), $t->translate_plural('three', 'threes', $c), $t->translate_plural('four', 'fours', $c), $t->translate_plural('nope', 'nopes', $c)], [1, 2]),
        ];
    }
    $say('translate and translate_plural', $rows);

    $mo = new class extends MO {
        public function export_original($entry)
        {
            return 'zzo:' . parent::export_original($entry);
        }
        public function export_translations($entry)
        {
            return 'zzt:' . parent::export_translations($entry);
        }
        public function export_headers()
        {
            return "X-Zz: 1\n" . parent::export_headers();
        }
    };
    $plain = new MO();
    foreach ([$mo, $plain] as $target) {
        $target->set_header('Project-Id-Version', 'zzdoubth');
        $target->add_entry(['singular' => 'b', 'translations' => ['B']]);
        $target->add_entry(['singular' => 'a', 'plural' => 'as', 'translations' => ['A', 'As'], 'context' => 'ctx']);
    }
    $say('MO::export with overrides, and plain', [strlen($mo->export()), base64_encode($mo->export()), strlen($plain->export()), md5($plain->export())]);
});

$section('deprecated user meta', static function () use ($say, $wpdb, $guard) {
    $user = $guard(wp_insert_user(['user_login' => 'zzdoubth_meta', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_meta@example.com']));
    $heard = [];
    $ids = [];
    $listen = static function ($hook) use (&$heard, &$ids) {
        return static function (...$args) use ($hook, &$heard, &$ids) {
            $id = $args[0];
            $heard[] = [$hook, is_array($id) ? 'array' : (array_search((int) $id, $ids, true) === false ? (is_numeric($id) && (int) $id > 0 ? 'new row' : $id) : 'row ' . array_search((int) $id, $ids, true)), $args[2], $args[3], gettype($id), wp_cache_get($args[1], 'user_meta') === false ? 'cache clean' : 'cached'];
        };
    };
    $hooks = ['delete_usermeta', 'deleted_usermeta', 'update_usermeta', 'updated_usermeta', 'added_usermeta'];
    $listeners = [];
    foreach ($hooks as $hook) {
        add_action($hook, $listeners[$hook] = $listen($hook), 10, 4);
    }
    $seed = static function (array $values) use ($user, $wpdb, &$ids) {
        delete_user_meta($user, 'zzdoubth_k');
        $ids = [];
        foreach ($values as $value) {
            $ids[] = (int) add_user_meta($user, 'zzdoubth_k', $value);
        }
        get_user_meta($user);
    };
    $stored = static function () use ($user, $wpdb) {
        return $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'zzdoubth_k' ORDER BY umeta_id", $user));
    };
    try {
        if (!$user) {
            throw new RuntimeException('no user');
        }
        $rows = [];
        foreach ([['a', '0', 'b'], '0', ['a', 'x', 'b'], ' x ', ['a', 'b'], 'missing', ['a', 'b'], '', ['a', 's:1:"z";'], ['z'], ['a', 'b'], 'b', ['a', 'b'], null] as $i => $case) {
            if ($i % 2 === 0) {
                $seed($case);
                continue;
            }
            $heard = [];
            $result = delete_usermeta($user, 'zzdoubth_k', $case);
            $rows[] = [$case, $result, $stored(), $heard];
        }
        $say('delete_usermeta', $rows);

        $rows = [];
        foreach ([['a', 'b'], 'c', ['a'], 'a', ['a'], ['arr' => 1], [], 'new', ['a'], '', ['a'], 'x\\y'] as $i => $case) {
            if ($i % 2 === 0) {
                $seed($case);
                continue;
            }
            $heard = [];
            $result = update_usermeta($user, 'zzdoubth_k', $case);
            $rows[] = [$case, $result, $stored(), $heard];
        }
        $say('update_usermeta', $rows);
    } finally {
        foreach ($listeners as $hook => $listener) {
            remove_action($hook, $listener, 10);
        }
        if ($user) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user);
        }
    }

    // The old thumbnail file of a post that is not an attachment.
    $dir = wp_upload_dir();
    $post = $guard(wp_insert_post(['post_title' => 'zzdoubth thumb', 'post_status' => 'draft']));
    $thumb = $dir['basedir'] . '/zzdoubth-thumb.jpg';
    try {
        file_put_contents($thumb, 'x');
        update_post_meta($post, '_wp_attached_file', 'zzdoubth-full.jpg');
        update_post_meta($post, '_wp_attachment_metadata', ['thumb' => 'zzdoubth-thumb.jpg']);
        $file = wp_get_attachment_thumb_file($post);
        $say('wp_get_attachment_thumb_file of a post', is_string($file) ? str_replace($dir['basedir'], 'UPLOADS', $file) : $file);
        update_post_meta($post, '_wp_attachment_metadata', 'zz');
        $say('wp_get_attachment_thumb_file without array metadata', wp_get_attachment_thumb_file($post));
    } finally {
        if ($post) {
            wp_delete_post($post, true);
        }
        @unlink($thumb);
    }
});

$section('embed', static function () use ($say, $wpdb, $guard) {
    global $wp_embed;
    $saved = [$wp_embed->post_ID, $wp_embed->usecache, $wp_embed->linkifunknown, $GLOBALS['post'] ?? null];
    $before = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'oembed_cache'"));
    $posts = [];
    $block = static fn () => new WP_Error('zz_blocked', 'No request leaves the probe.');
    $asked = [];
    $answer = static function ($pre, $url, $args) use (&$asked) {
        $asked[] = [$url, $args['discover'] ?? 'unset'];
        return str_starts_with((string) $url, 'https://zzdoubth.example/') ? '<iframe src="' . esc_url($url) . '"></iframe>' : $pre;
    };
    $ttl = 0;
    $countTtl = static function ($value) use (&$ttl) {
        $ttl++;
        return $value;
    };
    add_filter('pre_http_request', $block, 1);
    add_filter('pre_oembed_result', $answer, 1, 3);
    add_filter('oembed_ttl', $countTtl);
    $htmlAttr = [];
    $hearHtml = static function ($html, $url, $attr) use (&$htmlAttr) {
        $htmlAttr[] = $attr['discover'] ?? 'unset';
        return $html;
    };
    add_filter('embed_oembed_html', $hearHtml, 10, 3);
    register_post_type('zzdoubth_ui', ['public' => false, 'show_ui' => true, 'label' => 'Zz UI']);
    try {
        $host = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth embed host', 'post_status' => 'draft']));
        $wp_embed->post_ID = $host;
        $content = "<div\nhttps://zzdoubth.example/in-tag\nclass=\"zz\">x</div>\n<a title=\"\nhttps://zzdoubth.example/in-attr\n\">y</a>\nhttps://zzdoubth.example/alone\n";
        $say('autoembed: URLs inside a tag and an attribute, and alone', $wp_embed->autoembed($content));
        $asked = [];
        $ttl = 0;
        $wp_embed->shortcode([], 'https://zzdoubth.example/ttl');
        $first = $ttl;
        $ttl = 0;
        $wp_embed->shortcode([], 'https://zzdoubth.example/ttl');
        $say('inside a post: oembed_ttl calls, first and cached', [$first, $ttl]);

        $asked = [];
        $wp_embed->shortcode(['discover' => '0'], 'https://zzdoubth.example/discover-in');
        $GLOBALS['post'] = null;
        $wp_embed->post_ID = null;
        $wp_embed->shortcode(['discover' => '0'], 'https://zzdoubth.example/discover-out');
        $never = static fn () => false;
        add_filter('embed_oembed_discover', $never);
        $wp_embed->shortcode(['discover' => '1'], 'https://zzdoubth.example/discover-off');
        $wp_embed->post_ID = $host;
        $wp_embed->shortcode(['discover' => '1'], 'https://zzdoubth.example/discover-off-in');
        remove_filter('embed_oembed_discover', $never);
        $say('discover as the providers are asked', $asked);
        $say('discover in the attributes embed_oembed_html hears', $htmlAttr);

        // cache_oembed: the types it warms by default, an empty post skipped.
        $types = [];
        $seeTypes = static function ($value) use (&$types) {
            $types = [array_is_list((array) $value), array_values(array_intersect(['post', 'page', 'attachment', 'wp_block', 'zzdoubth_ui', 'wp_navigation', 'revision', 'nav_menu_item'], (array) $value))];
            return $value;
        };
        add_filter('embed_cache_oembed_types', $seeTypes);
        $ui = $posts[] = $guard(wp_insert_post(['post_type' => 'zzdoubth_ui', 'post_title' => 'zzdoubth ui', 'post_status' => 'draft', 'post_content' => "\nhttps://zzdoubth.example/ui\n"]));
        $wp_embed->post_ID = null;
        $wp_embed->cache_oembed($ui);
        $metas = array_filter(array_keys(get_post_meta($ui)), static fn ($k) => str_starts_with($k, '_oembed_'));
        $say('cache_oembed of a show_ui type', [$types, count($metas), $wp_embed->post_ID === $ui ? 'the post' : $wp_embed->post_ID, $wp_embed->usecache]);
        $blank = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth blank', 'post_status' => 'draft', 'post_content' => '']));
        $wp_embed->post_ID = null;
        $wp_embed->cache_oembed($blank);
        $say('cache_oembed of an empty post', [$wp_embed->post_ID, $wp_embed->usecache]);
        remove_filter('embed_cache_oembed_types', $seeTypes);

        // find_oembed_post_id through the query's filters.
        $key = md5('zzdoubth-find');
        $cache = $posts[] = $guard(wp_insert_post(['post_type' => 'oembed_cache', 'post_name' => $key, 'post_status' => 'publish', 'post_content' => 'zz']));
        wp_cache_delete($key, 'oembed_cache_post');
        $hide = static fn ($where) => $where . ' AND ' . $wpdb->posts . '.ID <> ' . (int) $cache;
        add_filter('posts_where', $hide);
        $hidden = $wp_embed->find_oembed_post_id($key);
        remove_filter('posts_where', $hide);
        wp_cache_delete($key, 'oembed_cache_post');
        $say('find_oembed_post_id with posts_where hiding it, then without', [$hidden, $wp_embed->find_oembed_post_id($key) === $cache]);
    } finally {
        remove_filter('pre_http_request', $block, 1);
        remove_filter('pre_oembed_result', $answer, 1);
        remove_filter('oembed_ttl', $countTtl);
        remove_filter('embed_oembed_html', $hearHtml, 10);
        unregister_post_type('zzdoubth_ui');
        [$wp_embed->post_ID, $wp_embed->usecache, $wp_embed->linkifunknown, $GLOBALS['post']] = $saved;
        foreach (array_filter($posts) as $id) {
            wp_delete_post($id, true);
        }
        foreach (array_diff(array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'oembed_cache'")), $before) as $id) {
            if ($id > 1) {
                wp_delete_post($id, true);
            }
        }
    }
});

$section('rest server', static function () use ($say) {
    $server = rest_get_server();
    $ran = 0;
    $server->register_route('zzdoubth/v1', '/zzdoubth/v1/real', [['methods' => 'GET, POST', 'callback' => static function () use (&$ran) {
        $ran++;
        return ['real' => true];
    }, 'permission_callback' => '__return_true']]);
    $describe = static function ($result) {
        if ($result instanceof WP_REST_Response) {
            return ['response', $result->get_status(), $result->get_data()];
        }
        if ($result instanceof WP_Error) {
            return ['error', $result->get_error_code()];
        }
        return ['raw', $result];
    };
    $rows = [];
    foreach (['empty string' => '', 'zero' => 0, 'false' => false, 'empty array' => [], 'array' => ['zz' => 1], 'string' => 'zzs', 'error' => new WP_Error('zz_early', 'Early.', ['status' => 418]), 'error without status' => new WP_Error('zz_early', 'Early.'), 'response' => new WP_REST_Response(['r' => 1], 201)] as $name => $early) {
        $pre = static fn () => $early;
        add_filter('rest_pre_dispatch', $pre);
        $rows[$name] = [$describe($server->dispatch(new WP_REST_Request('GET', '/zzdoubth/v1/real'))), $describe(rest_do_request(new WP_REST_Request('GET', '/zzdoubth/v1/real')))];
        remove_filter('rest_pre_dispatch', $pre);
    }
    $say('dispatch with an early rest_pre_dispatch answer', $rows);

    $say('get_headers', [
        $server->get_headers(['HTTP_X_ZZ' => 'a', 'CONTENT_TYPE' => 'text/zz', 'CONTENT_LENGTH' => '3', 'CONTENT_MD5' => 'md', 'REDIRECT_HTTP_AUTHORIZATION' => 'Basic zz', 'PHP_AUTH_USER' => 'u', 'SERVER_NAME' => 'x', 'CONTENT_OTHER' => 'o']),
        $server->get_headers(['HTTP_AUTHORIZATION' => 'Bearer one', 'REDIRECT_HTTP_AUTHORIZATION' => 'Basic two']),
        $server->get_headers(['REDIRECT_HTTP_AUTHORIZATION' => '']),
    ]);

    $options = new ReflectionMethod($server, 'get_json_encode_options');
    $options->setAccessible(true);
    $rows = [];
    foreach (['none' => null, '_pretty=1' => ['_pretty' => '1'], '_pretty=0' => ['_pretty' => '0'], '_pretty empty' => ['_pretty' => '']] as $name => $query) {
        $request = new WP_REST_Request('GET', '/zzdoubth/v1/real');
        if ($query !== null) {
            $request->set_query_params($query);
        }
        $rows[$name] = $options->invoke($server, $request);
    }
    $body = new WP_REST_Request('POST', '/zzdoubth/v1/real');
    $body->set_body_params(['_pretty' => '1']);
    $rows['in the body'] = $options->invoke($server, $body);
    $say('get_json_encode_options', $rows);

    $statuses = [];
    $hear = static function ($header, $code) use (&$statuses) {
        $statuses[] = $code;
        return $header;
    };
    add_filter('status_header', $hear, 10, 2);
    $error = new ReflectionMethod($server, 'json_error');
    $error->setAccessible(true);
    $say('json_error', [$error->invoke($server, 'zz_code', 'Zz message', 404), $error->invoke($server, 'zz_code', 'Zz message'), $statuses]);
    remove_filter('status_header', $hear, 10);

    $ran = 0;
    $batch = new WP_REST_Request('POST', '/batch/v1');
    $batch->set_body_params(['validation' => 'require-all-validate', 'requests' => [['method' => 'POST', 'path' => '/zzdoubth/v1/real'], ['method' => 'POST', 'path' => '/zzdoubth/v1/real']]]);
    $first = $describe($server->serve_batch_request_v1($batch));
    $firstRan = $ran;
    $ran = 0;
    $normal = new WP_REST_Request('POST', '/batch/v1');
    $normal->set_body_params(['requests' => [['method' => 'POST', 'path' => '/zzdoubth/v1/real']]]);
    $say('serve_batch_request_v1 called directly', [$first, $firstRan, $describe($server->serve_batch_request_v1($normal)), $ran]);
});

$section('widgets', static function () use ($say, $guard) {
    $text = new WP_Widget_Text();
    $legacy = [];
    foreach (['', 'plain', "a\nb", '<b>x</b> y', '<a href="x">y</a>', '<p>x</p>', '<div>x</div>', '<!-- c -->x', '<script>x</script>', '<span style="c">x</span>', '<img src="x" alt="a">', '<img src="x" width="1">', '<b></b>', '<b>&nbsp;</b>', '<ul><li>x</li></ul>', '<i/>', '<title>x</title>', '<html>', '</p>', "\n", '<b>0</b>', '<a href="x"><img src="y"></a>', '<abbr title="t">x</abbr>', '<![CDATA[x]]>', "<b>a</b>\n<i>b</i>"] as $value) {
        $legacy[] = [$value, $text->is_legacy_instance(['text' => $value]), $text->is_legacy_instance(['text' => $value, 'filter' => true])];
    }
    $legacy[] = ['instances', $text->is_legacy_instance([]), $text->is_legacy_instance(['filter' => true]), $text->is_legacy_instance(['visual' => false, 'text' => '']), $text->is_legacy_instance(['visual' => true, 'text' => "a\n<script>x</script>"]), $text->is_legacy_instance(['filter' => 'content', 'text' => "a\n<p>b</p>"]), $text->is_legacy_instance(['visual' => '', 'text' => 'x'])];
    $say('text widget: is_legacy_instance', $legacy);

    $rows = [];
    foreach ([
        'filter content' => [['title' => 't', 'text' => 'x', 'filter' => 'content'], []],
        'visual, filter off' => [['title' => 't', 'text' => 'x', 'visual' => true], []],
        'nothing, old visual' => [['title' => 't', 'text' => 'x'], ['visual' => true, 'filter' => true, 'zz' => 1]],
        'filter on' => [['title' => 't', 'text' => 'x', 'filter' => 'on'], ['title' => 'old']],
        'visual off' => [['title' => 't', 'text' => 'x', 'visual' => false, 'filter' => 'on'], ['visual' => true]],
        'nothing' => [['title' => ' <b>t</b> ', 'text' => 'x'], []],
        'empty' => [[], []],
    ] as $name => [$new, $old]) {
        $rows[$name] = $text->update($new, $old);
    }
    $say('text widget: update', $rows);

    add_shortcode('zzdoubth_sc', static fn () => 'ZZSC');
    $galleryIds = [];
    $hearGallery = static function ($out) use (&$galleryIds) {
        $galleryIds[] = $out['id'] ?? 'unset';
        return $out;
    };
    add_filter('shortcode_atts_gallery', $hearGallery, 20);
    $seen = [];
    $hearText = static function ($value, $instance) use (&$seen) {
        $seen[] = [$value, isset($GLOBALS['post']) ? 'post' : 'no post', $instance];
        return $value;
    };
    add_filter('widget_text', $hearText, 10, 2);
    $host = $guard(wp_insert_post(['post_title' => 'zzdoubth widget host', 'post_status' => 'publish']));
    $savedPost = $GLOBALS['post'] ?? null;
    try {
        $GLOBALS['post'] = get_post($host);
        $args = ['before_widget' => '<section class="widget">', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>'];
        $rows = [];
        foreach ([
            'legacy, no filter' => ['text' => "[zzdoubth_sc]\nb"],
            'legacy, filter' => ['text' => "[zzdoubth_sc]\n<p>b</p>", 'filter' => true],
            'one line' => ['text' => '[zzdoubth_sc]'],
            'visual' => ['text' => "[zzdoubth_sc]\nb", 'visual' => true, 'filter' => 'content'],
            'gallery' => ['text' => '[gallery]', 'filter' => true],
        ] as $name => $instance) {
            $seen = [];
            $galleryIds = [];
            ob_start();
            $text->widget($args, $instance);
            $rows[$name] = [ob_get_clean(), $seen, $galleryIds, isset($GLOBALS['post']) && $GLOBALS['post']->ID === $host];
        }
        $say('text widget: widget', $rows);

        $html = new WP_Widget_Custom_HTML();
        $seen = [];
        ob_start();
        $html->widget($args, ['title' => 'zz', 'content' => '[zzdoubth_sc] <b>x</b>']);
        $say('custom html widget: widget', [ob_get_clean(), $seen, isset($GLOBALS['post']) && $GLOBALS['post']->ID === $host]);

        $attrs = [];
        foreach ([['id' => 0, 'include' => ''], ['id' => '', 'include' => ''], [], ['id' => 5], ['include' => '1,2'], ['id' => 0, 'include' => '3']] as $given) {
            $attrs[] = [$text->_filter_gallery_shortcode_attrs($given), $html->_filter_gallery_shortcode_attrs($given)];
        }
        global $wp_query;
        $singular = $wp_query->is_singular;
        $wp_query->is_singular = true;
        $attrs[] = ['singular', $text->_filter_gallery_shortcode_attrs(['id' => 0, 'include' => '']), $html->_filter_gallery_shortcode_attrs([])];
        $wp_query->is_singular = $singular;
        $say('gallery attributes in the text and HTML widgets', $attrs);
    } finally {
        remove_filter('shortcode_atts_gallery', $hearGallery, 20);
        remove_filter('widget_text', $hearText, 10);
        remove_shortcode('zzdoubth_sc');
        $GLOBALS['post'] = $savedPost;
        if ($host) {
            wp_delete_post($host, true);
        }
    }

    $cloud = new WP_Widget_Tag_Cloud();
    $say('tag cloud widget: update', [$cloud->update(['title' => 't', 'count' => '1'], ['title' => 'old', 'zzextra' => 1, 'taxonomy' => 'category']), @$cloud->update([], ['zz' => 1]), $cloud->update(['taxonomy' => 'cat\\egory', 'count' => ''], [])]);
    $recent = new WP_Widget_Recent_Comments();
    $say('recent comments widget: alt_option_name', $recent->alt_option_name ?? 'unset');
    $meta = new WP_Widget_Meta();
    $echo = static function () {
        echo '<li>zzdoubth meta</li>';
    };
    add_action('wp_meta', $echo);
    ob_start();
    $meta->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], []);
    $out = ob_get_clean();
    remove_action('wp_meta', $echo);
    $say('meta widget: wp_meta', substr($out, strpos($out, 'Comments feed')));
});

$section('rest objects', static function () use ($say, $guard) {
    $response = new WP_REST_Response(['a' => 1]);
    $response->add_link('zz', 'https://a.test/x', ['href' => 'https://b.test/y', 'title' => 'T']);
    $heard = [];
    $curies = static function ($curies) use (&$heard) {
        $heard[] = $curies;
        return $curies;
    };
    add_filter('rest_response_link_curies', $curies);
    $first = $response->get_curies();
    remove_filter('rest_response_link_curies', $curies);
    $none = static fn () => [];
    add_filter('rest_response_link_curies', $none);
    $emptied = $response->get_curies();
    remove_filter('rest_response_link_curies', $none);
    $say('WP_REST_Response: add_link with an href attribute, get_curies', [$response->get_links(), $first, $heard, $emptied]);

    $posts = [];
    wp_set_current_user(1);
    register_post_meta('post', 'zzdoubth_m', ['show_in_rest' => true, 'single' => true, 'type' => 'string', 'revisions_enabled' => true]);
    register_post_meta('post', 'zzdoubth_multi', ['show_in_rest' => true, 'single' => false, 'type' => 'string']);
    register_post_meta('post', 'zzdoubth_gone', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
    register_post_type('zzdoubth_nt', ['public' => true, 'show_in_rest' => true, 'supports' => ['editor'], 'label' => 'Zz no title']);
    register_rest_field('block-pattern', 'zzdoubth_field', ['get_callback' => static fn () => 'zzf', 'schema' => ['type' => 'string']]);
    try {
        $post = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth meta host', 'post_status' => 'draft', 'post_content' => 'orig content']));
        update_post_meta($post, 'zzdoubth_m', 'abc');
        update_post_meta($post, 'zzdoubth_gone', 'g');
        $calls = [];
        $fields = new class('post', $calls) extends WP_REST_Post_Meta_Fields {
            public $calls;
            public function __construct($type, &$calls)
            {
                parent::__construct($type);
                $this->calls = &$calls;
            }
            protected function prepare_value_for_response($value, $request, $args)
            {
                $this->calls[] = 'prepare';
                return 'ZZ:' . json_encode(parent::prepare_value_for_response($value, $request, $args));
            }
            protected function get_registered_fields()
            {
                $this->calls[] = 'fields';
                return array_filter(parent::get_registered_fields(), static fn ($key) => str_starts_with($key, 'zzdoubth'), ARRAY_FILTER_USE_KEY);
            }
            protected function update_meta_value($object_id, $meta_key, $name, $value)
            {
                $this->calls[] = "update {$meta_key}";
                return parent::update_meta_value($object_id, $meta_key, $name, $value);
            }
            protected function update_multi_meta_value($object_id, $meta_key, $name, $values)
            {
                $this->calls[] = "update multi {$meta_key}";
                return parent::update_multi_meta_value($object_id, $meta_key, $name, $values);
            }
            protected function delete_meta_value($object_id, $meta_key, $name)
            {
                $this->calls[] = "delete {$meta_key}";
                return parent::delete_meta_value($object_id, $meta_key, $name);
            }
        };
        $value = $fields->get_value($post, new WP_REST_Request('GET', '/wp/v2/posts'));
        $readCalls = $calls;
        $calls = [];
        $result = $fields->update_value(['zzdoubth_m' => 'new', 'zzdoubth_multi' => ['a', 'b'], 'zzdoubth_gone' => null], $post);
        $say('WP_REST_Meta_Fields: overridden steps', [$value, array_values(array_unique($readCalls)), $calls, $result, get_post_meta($post, 'zzdoubth_m', true), get_post_meta($post, 'zzdoubth_multi'), get_post_meta($post, 'zzdoubth_gone')]);

        // The search handler's titles.
        $private = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth private', 'post_status' => 'private']));
        $untitled = $posts[] = $guard(wp_insert_post(['post_type' => 'zzdoubth_nt', 'post_title' => 'zzdoubth stored title', 'post_status' => 'publish']));
        $protected = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth protected', 'post_status' => 'publish', 'post_password' => 'pw']));
        $handler = new WP_REST_Post_Search_Handler();
        $search = rest_do_request(new WP_REST_Request('GET', '/wp/v2/search'));
        $request = new WP_REST_Request('GET', '/wp/v2/search');
        $request->set_query_params(['search' => 'zzdoubth', 'subtype' => 'post', 'per_page' => 20]);
        $found = rest_do_request($request)->get_data();
        $titles = [];
        foreach ((array) $found as $item) {
            $titles[] = [$item['title'] ?? null, $item['subtype'] ?? null];
        }
        usort($titles, static fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));
        $say('search handler: titles', [array_map(static fn ($id) => $handler->prepare_item($id, ['title', 'subtype'])['title'], [$private, $untitled, $protected]), $titles]);

        // Block patterns: a registered REST field.
        $patterns = new WP_REST_Block_Patterns_Controller();
        $item = $patterns->prepare_item_for_response(['name' => 'zz/p', 'title' => 'Zz', 'content' => ''], new WP_REST_Request('GET', '/wp/v2/block-patterns/patterns'));
        register_block_pattern('zzdoubth/pattern', ['title' => 'Zz pattern', 'content' => '<!-- wp:paragraph --><p>zz</p><!-- /wp:paragraph -->']);
        $listed = array_values(array_filter((array) rest_do_request(new WP_REST_Request('GET', '/wp/v2/block-patterns/patterns'))->get_data(), static fn ($pattern) => ($pattern['name'] ?? '') === 'zzdoubth/pattern'));
        unregister_block_pattern('zzdoubth/pattern');
        $say('block patterns: a registered field', [$item->get_data()['zzdoubth_field'] ?? 'missing', isset($listed[0]) ? ($listed[0]['zzdoubth_field'] ?? 'missing') : 'not listed']);

        // Autosaves: revisioned meta passed in.
        $autosaves = new WP_REST_Autosaves_Controller('post');
        update_post_meta($post, 'zzdoubth_m', 'orig');
        $saved = $autosaves->create_post_autosave(['ID' => $post, 'post_title' => 'zzdoubth changed', 'post_content' => 'changed'], ['zzdoubth_m' => 'metaval', 'zzdoubth_multi' => 'x']);
        $savedId = is_int($saved) ? $saved : (is_object($saved) ? ($saved->ID ?? 0) : 0);
        $again = $autosaves->create_post_autosave(['ID' => $post, 'post_title' => 'zzdoubth changed', 'post_content' => 'changed'], ['zzdoubth_m' => 'metaval2']);
        $say('autosave: revisioned meta', [is_wp_error($saved) ? $saved->get_error_code() : gettype($saved), $savedId ? [get_post_meta($savedId, 'zzdoubth_m'), get_post_meta($savedId, 'zzdoubth_multi')] : null, $again === $saved, $savedId ? get_post_meta($savedId, 'zzdoubth_m') : null, get_post_meta($post, 'zzdoubth_m')]);
        if ($savedId > 1) {
            $posts[] = $savedId;
        }

        // Terms: a post whose type is not in the taxonomy; the taxonomy field copied.
        $page = $posts[] = $guard(wp_insert_post(['post_type' => 'page', 'post_title' => 'zzdoubth page', 'post_status' => 'publish']));
        $cpt = $posts[] = $guard(wp_insert_post(['post_type' => 'zzdoubth_nt', 'post_title' => 'zzdoubth cpt', 'post_status' => 'publish']));
        $terms = new WP_REST_Terms_Controller('category');
        $rows = [];
        foreach ([0 => 'anonymous', 1 => 'administrator'] as $user => $who) {
            wp_set_current_user($user);
            $rows[$who] = [$terms->check_read_terms_permission_for_post(get_post($page), new WP_REST_Request()), $terms->check_read_terms_permission_for_post(get_post($cpt), new WP_REST_Request()), $terms->check_read_terms_permission_for_post(get_post($post), new WP_REST_Request())];
        }
        wp_set_current_user(1);
        $request = new WP_REST_Request('POST', '/wp/v2/categories');
        $request->set_body_params(['name' => 'n', 'slug' => 's', 'taxonomy' => 'zzx', 'description' => 'd', 'parent' => 0, 'count' => 5, 'link' => 'l', 'meta' => []]);
        $say('terms controller', [$rows, get_object_vars($terms->prepare_item_for_database($request))]);
    } finally {
        unregister_post_meta('post', 'zzdoubth_m');
        unregister_post_meta('post', 'zzdoubth_multi');
        unregister_post_meta('post', 'zzdoubth_gone');
        global $wp_rest_additional_fields;
        unset($wp_rest_additional_fields['block-pattern']['zzdoubth_field']);
        foreach (array_reverse(array_filter($posts)) as $id) {
            wp_delete_post($id, true);
        }
        unregister_post_type('zzdoubth_nt');
        wp_set_current_user(0);
    }
});

$section('rest controllers', static function () use ($say, $guard) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $posts = [];
    $users = [];
    $describe = static function ($result) {
        if ($result instanceof WP_REST_Response) {
            $data = $result->get_data();
            return [$result->get_status(), is_array($data) && isset($data['code']) ? $data['code'] : 'ok'];
        }
        return $result instanceof WP_Error ? ['error', $result->get_error_code()] : $result;
    };
    register_post_type('zzdoubth_doc', ['public' => true, 'show_in_rest' => true, 'supports' => ['title', 'editor', 'author'], 'capability_type' => 'zzdoc', 'map_meta_cap' => true, 'label' => 'Zz docs']);
    register_post_type('zzdoubth_na', ['public' => true, 'show_in_rest' => true, 'supports' => ['title'], 'label' => 'Zz no author']);
    try {
        // Plugins: a subclass's read check leaves a plugin out of the list.
        wp_set_current_user(1);
        $plugins = new class extends WP_REST_Plugins_Controller {
            protected function check_read_permission($plugin)
            {
                return str_starts_with((string) $plugin, 'minn-test-cli/') ? new WP_Error('zz_refused', 'No.') : parent::check_read_permission($plugin);
            }
            protected function plugin_status_permission_check($plugin, $new_status, $current_status)
            {
                $GLOBALS['zzdoubth_status_checked'] = true;
                return parent::plugin_status_permission_check($plugin, $new_status, $current_status);
            }
        };
        $listed = array_column((array) $plugins->get_items(new WP_REST_Request('GET', '/wp/v2/plugins'))->get_data(), 'plugin');
        $say('plugins controller: get_items with a read check refusing one', [in_array('minn-test-cli/minn-test-cli', $listed, true), in_array('minn-admin/minn-admin', $listed, true)]);

        $deny = static function ($caps, $cap) {
            return $cap === 'activate_plugin' ? ['do_not_allow'] : $caps;
        };
        add_filter('map_meta_cap', $deny, 10, 2);
        $rows = [];
        foreach (['active', 'inactive'] as $status) {
            $GLOBALS['zzdoubth_status_checked'] = false;
            $request = new WP_REST_Request('PUT', '/wp/v2/plugins/minn-admin/minn-admin');
            $request->set_url_params(['plugin' => 'minn-admin/minn-admin.php']);
            $request->set_param('status', $status);
            $rows[$status] = [$describe($plugins->update_item_permissions_check($request)), $GLOBALS['zzdoubth_status_checked']];
        }
        remove_filter('map_meta_cap', $deny, 10);
        $say('plugins controller: update permission with activation denied', $rows);

        // Users: a user whose only published post is a REST type's with authors.
        $writer = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_writer', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_writer@example.com', 'role' => 'subscriber']));
        $plain = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_plain', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_plain@example.com', 'role' => 'subscriber']));
        $contributor = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_contrib', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_contrib@example.com', 'role' => 'contributor']));
        $docEditor = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_doceditor', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_doceditor@example.com', 'role' => 'subscriber']));
        $author = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_author@example.com', 'role' => 'author']));
        get_userdata($docEditor)->add_cap('edit_zzdocs');
        $posts[] = $guard(wp_insert_post(['post_type' => 'zzdoubth_doc', 'post_title' => 'zzdoubth doc', 'post_status' => 'publish', 'post_author' => $writer]));
        $posts[] = $guard(wp_insert_post(['post_type' => 'zzdoubth_na', 'post_title' => 'zzdoubth na', 'post_status' => 'publish', 'post_author' => $plain]));
        wp_set_current_user(0);
        $say('users: anonymous read of a user with only a REST type post', [$describe(rest_do_request(new WP_REST_Request('GET', "/wp/v2/users/{$writer}"))), $describe(rest_do_request(new WP_REST_Request('GET', "/wp/v2/users/{$plain}")))]);
        $rows = [];
        foreach (['doc editor, who=authors' => [$docEditor, ['who' => 'authors']], 'contributor, roles and edit' => [$contributor, ['roles' => 'author', 'context' => 'edit']], 'contributor, edit and orderby' => [$contributor, ['context' => 'edit', 'orderby' => 'email']], 'contributor, capabilities and roles' => [$contributor, ['capabilities' => 'x', 'roles' => 'author']], 'contributor, who and edit' => [$contributor, ['who' => 'authors', 'context' => 'edit']], 'plain, who=authors' => [$plain, ['who' => 'authors']], 'contributor, orderby and roles' => [$contributor, ['orderby' => 'registered_date', 'roles' => 'author']], 'contributor, capabilities and edit' => [$contributor, ['capabilities' => 'x', 'context' => 'edit']], 'author, who=authors' => [$author, ['who' => 'authors']], 'contributor, who=authors' => [$contributor, ['who' => 'authors']]] as $name => [$user, $query]) {
            wp_set_current_user($user);
            $request = new WP_REST_Request('GET', '/wp/v2/users');
            $request->set_query_params($query);
            $result = rest_do_request($request);
            $rows[$name] = [$result->get_status(), $result->get_data()['code'] ?? 'ok', $result->get_data()['message'] ?? ''];
        }
        $say('users: list permission checks', $rows);

        // Posts: sticky needs edit_others_posts; a protected post read with its password.
        wp_set_current_user($author);
        $own = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth own draft', 'post_status' => 'draft', 'post_author' => $author]));
        $create = new WP_REST_Request('POST', '/wp/v2/posts');
        $create->set_body_params(['title' => 'zzdoubth sticky', 'sticky' => true, 'status' => 'draft']);
        $update = new WP_REST_Request('POST', "/wp/v2/posts/{$own}");
        $update->set_url_params(['id' => $own]);
        $update->set_body_params(['sticky' => true]);
        $controller = new WP_REST_Posts_Controller('post');
        $say('posts: sticky as an author', [$describe($controller->create_item_permissions_check($create)), $describe($controller->update_item_permissions_check($update)), $describe(rest_do_request($create)), $describe(rest_do_request($update))]);
        foreach ((array) get_posts(['post_type' => 'post', 'post_status' => 'any', 'title' => 'zzdoubth sticky', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
            $posts[] = (int) $id;
        }
        wp_set_current_user(0);
        $locked = $posts[] = $guard(wp_insert_post(['post_title' => 'zzdoubth locked', 'post_status' => 'publish', 'post_password' => 'zzpw', 'post_content' => 'secret body', 'post_excerpt' => 'secret excerpt', 'post_author' => 1]));
        $rows = [];
        foreach (['right' => 'zzpw', 'wrong' => 'nope', 'none' => null] as $name => $password) {
            $request = new WP_REST_Request('GET', "/wp/v2/posts/{$locked}");
            $request->set_url_params(['id' => $locked]);
            if ($password !== null) {
                $request->set_query_params(['password' => $password]);
            }
            $viaResponse = rest_do_request($request);
            $via = $viaResponse->get_data();
            $rows[$name . ' status'] = [$viaResponse->get_status(), $via['code'] ?? 'ok', $via['message'] ?? ''];
            $direct = $controller->prepare_item_for_response(get_post($locked), $request)->get_data();
            $rows[$name] = array_map(static fn ($data) => [$data['content']['protected'] ?? null, trim(strip_tags((string) ($data['content']['rendered'] ?? 'missing'))), $data['excerpt']['protected'] ?? null, trim(strip_tags((string) ($data['excerpt']['rendered'] ?? 'missing')))], [$via, $direct]);
        }
        wp_set_current_user(1);
        $request = new WP_REST_Request('GET', "/wp/v2/posts/{$locked}");
        $request->set_url_params(['id' => $locked]);
        $admin = rest_do_request($request)->get_data();
        $rows['administrator, no password'] = [$admin['content']['protected'] ?? null, trim(strip_tags((string) ($admin['content']['rendered'] ?? 'missing')))];
        wp_set_current_user(0);
        $say('posts: a protected post read with its password', $rows);
    } finally {
        wp_set_current_user(0);
        foreach (array_filter(array_unique($posts)) as $id) {
            wp_delete_post($id, true);
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach (array_filter($users) as $id) {
            wp_delete_user($id);
        }
        unregister_post_type('zzdoubth_doc');
        unregister_post_type('zzdoubth_na');
    }
});

$section('translations', static function () use ($say) {
    $dir = sys_get_temp_dir() . '/zzdoubth-l10n';
    $made = [];
    try {
        @mkdir($dir . '/sub', 0777, true);
        $write = static function (string $file, string $translation) use (&$made) {
            $mo = new MO();
            $mo->add_entry(['singular' => 'zzdoubth hello', 'translations' => [$translation]]);
            $mo->export_to_file($file);
            $made[] = $file;
        };
        $write($dir . '/sub/zzdoubth-fr_FR.mo', 'bonjour');
        $write($dir . '/sub/zzdoubth-de_DE.mo', 'hallo');
        $mask = static fn ($value) => json_decode(str_replace($dir, 'DIR', json_encode($value, JSON_UNESCAPED_SLASHES)), true);

        // The registry: the folder its filter hears, a listing kept, the paths a domain is looked for in, a falsy set().
        $registry = new WP_Textdomain_Registry();
        $heard = [];
        $hear = static function ($pre, $path) use (&$heard) {
            $heard[] = $path;
            return $pre;
        };
        add_filter('pre_get_language_files_from_path', $hear, 10, 2);
        $first = $registry->get_language_files_from_path($dir . '/sub');
        $registry->get_language_files_from_path($dir . '/sub/');
        $write($dir . '/sub/zzdoubth-es_ES.mo', 'hola');
        $again = $registry->get_language_files_from_path($dir . '/sub/');
        $fresh = (new WP_Textdomain_Registry())->get_language_files_from_path($dir . '/sub/');
        remove_filter('pre_get_language_files_from_path', $hear, 10);
        $sorted = static function ($files) {
            $files = array_map('basename', (array) $files);
            sort($files);
            return $files;
        };
        $paths = new ReflectionMethod($registry, 'get_paths_for_domain');
        $paths->setAccessible(true);
        $registry->set_custom_path('zzdoubth', $dir . '/custom');
        $registry->set('zzdoubth_a', 'fr_FR', '');
        $registry->set('zzdoubth_b', 'fr_FR', null);
        $registry->set('zzdoubth_c', 'fr_FR', $dir . '/x');
        $invalidated = [];
        foreach ([['type' => 'plugin'], ['type' => 'translation', 'translations' => [['type' => 'plugin', 'slug' => 'zz', 'language' => 'de_DE']]], ['type' => 'translation', 'translations' => [['type' => 'theme', 'slug' => 'zz', 'language' => 'de_DE'], ['type' => 'core', 'slug' => 'default', 'language' => 'de_DE']]]] as $extra) {
            foreach (['/', '/plugins/', '/themes/'] as $folder) {
                wp_cache_set(md5(WP_LANG_DIR . $folder), ['zz'], 'translation_files');
            }
            $registry->invalidate_mo_files_cache(null, $extra);
            $invalidated[] = array_map(static fn ($folder) => wp_cache_get(md5(WP_LANG_DIR . $folder), 'translation_files') !== false, ['/', '/plugins/', '/themes/']);
            foreach (['/', '/plugins/', '/themes/'] as $folder) {
                wp_cache_delete(md5(WP_LANG_DIR . $folder), 'translation_files');
            }
        }
        $say('textdomain registry: invalidate_mo_files_cache', $invalidated);
        $say('textdomain registry', $mask([$mask($heard), $sorted($first), $sorted($again), $sorted($fresh), str_replace(WP_LANG_DIR, 'LANG', $paths->invoke($registry, 'zzdoubth')), $registry->get('zzdoubth_a', 'fr_FR'), $registry->get('zzdoubth_b', 'fr_FR'), $registry->get('zzdoubth_c', 'fr_FR')]));

        // The controller: one file by any path is one file; a locale's unload leaves the others.
        $controller = WP_Translation_Controller::get_instance();
        $real = $dir . '/sub/zzdoubth-fr_FR.mo';
        @symlink($real, $dir . '/link.mo');
        $made[] = $dir . '/link.mo';
        $rows = [];
        $rows[] = [$controller->load_file($real, 'zzdoubth_one', 'fr_FR'), $controller->load_file($dir . '/link.mo', 'zzdoubth_one', 'fr_FR'), $controller->load_file($dir . '/sub/../sub/zzdoubth-fr_FR.mo', 'zzdoubth_one', 'fr_FR')];
        $rows[] = [$controller->unload_file($real, 'zzdoubth_one', 'fr_FR'), $controller->translate('zzdoubth hello', '', 'zzdoubth_one', 'fr_FR'), $controller->is_textdomain_loaded('zzdoubth_one', 'fr_FR')];
        $cwd = getcwd();
        chdir($dir);
        $rows[] = $controller->load_file('sub/zzdoubth-de_DE.mo', 'zzdoubth_rel', 'de_DE');
        chdir($cwd);
        $rows[] = [$controller->unload_file($dir . '/sub/zzdoubth-de_DE.mo', 'zzdoubth_rel', 'de_DE'), $controller->translate('zzdoubth hello', '', 'zzdoubth_rel', 'de_DE'), $controller->is_textdomain_loaded('zzdoubth_rel', 'de_DE')];
        $controller->load_file($dir . '/sub/zzdoubth-fr_FR.mo', 'zzdoubth_two', 'fr_FR');
        $controller->load_file($dir . '/sub/zzdoubth-de_DE.mo', 'zzdoubth_two', 'de_DE');
        // (Which locale's file answers a lookup is the translation pipeline's, left for after 0.1.0.)
        $rows[] = [$controller->unload_textdomain('zzdoubth_two', 'de_DE'), $controller->is_textdomain_loaded('zzdoubth_two', 'fr_FR'), $controller->is_textdomain_loaded('zzdoubth_two', 'de_DE'), $controller->translate('zzdoubth hello', '', 'zzdoubth_two', 'fr_FR')];
        $rows[] = [$controller->unload_textdomain('zzdoubth_two'), $controller->is_textdomain_loaded('zzdoubth_two', 'fr_FR'), $controller->translate('zzdoubth hello', '', 'zzdoubth_two', 'fr_FR')];
        $say('translation controller', $rows);
        foreach (['zzdoubth_one', 'zzdoubth_rel', 'zzdoubth_two'] as $domain) {
            $controller->unload_textdomain($domain);
        }
    } finally {
        foreach (array_reverse($made) as $file) {
            @unlink($file);
        }
        @rmdir($dir . '/sub');
        @rmdir($dir);
    }
});

$section('misc', static function () use ($say, &$wrong, $guard) {
    $rows = [];
    foreach (['s:1:"x";garbage', 'i:5;x', 'a:1:{i:0;s:1:"x";garbage', 'a:1:{xxxx', 'E:3:"a:b";', 'b:1;', 'N;', 's:1:"x";', ' s:1:"x"; ', 'O:8:"stdClass":0:{}', 'd:1.5;', 'x'] as $value) {
        $rows[] = [$value, is_serialized($value), is_serialized($value, false), is_serialized($value, true), maybe_serialize($value)];
    }
    $say('is_serialized and maybe_serialize', $rows);

    $wrong = [];
    $messages = [];
    $hear = static function ($function, $message) use (&$messages) {
        $messages[] = [$function, $message];
    };
    add_action('doing_it_wrong_run', $hear, 10, 2);
    $msgids = [];
    $hearText = static function ($translation, $text) use (&$msgids) {
        if (str_contains((string) $text, 'shortcode name')) {
            $msgids[] = $text;
        }
        return $translation;
    };
    add_filter('gettext', $hearText, 10, 2);
    $results = [];
    foreach (['', null, 'zz tag', 'zz/tag', 'zz[tag', 'zz<tag', 'zz=tag', 'zz&tag', 'zzdoubth_ok'] as $tag) {
        add_shortcode($tag, '__return_empty_string');
        $results[] = [$tag, shortcode_exists((string) $tag)];
    }
    remove_shortcode('zzdoubth_ok');
    remove_action('doing_it_wrong_run', $hear, 10);
    remove_filter('gettext', $hearText, 10);
    $say('add_shortcode with bad tags', [$results, $messages, array_values(array_unique($msgids))]);

    // Users: reassigning to the user deleted; the edit context of another user.
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $users = [];
    try {
        $gone = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_gone', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_gone@example.com', 'role' => 'author']));
        $lister = $users[] = $guard(wp_insert_user(['user_login' => 'zzdoubth_lister', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubth_lister@example.com', 'role' => 'contributor']));
        get_userdata($lister)->add_cap('list_users');
        wp_set_current_user($lister);
        $read = new WP_REST_Request('GET', "/wp/v2/users/{$gone}");
        $read->set_query_params(['context' => 'edit']);
        $readResult = rest_do_request($read);
        wp_set_current_user(1);
        $delete = new WP_REST_Request('DELETE', "/wp/v2/users/{$gone}");
        $delete->set_query_params(['force' => 'true', 'reassign' => (string) $gone]);
        $deleted = rest_do_request($delete);
        $say('users: edit context with list_users, reassigning to itself', [[$readResult->get_status(), $readResult->get_data()['code'] ?? 'ok'], [$deleted->get_status(), $deleted->get_data()['code'] ?? 'ok', get_userdata($gone) !== false]]);
    } finally {
        wp_set_current_user(0);
        foreach (array_filter($users) as $id) {
            if (get_userdata($id)) {
                wp_delete_user($id);
            }
        }
    }
});

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
