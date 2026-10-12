<?php
/**
 * The revision and autosave routes beyond a post's lists (probe
 * rest-autosaves): a navigation menu's revisions (listed, read, refused
 * deletion), autosaves (saved, listed, read) and those of a menu item; one
 * autosave of a post, a draft and a page read by id (the user's own autosave
 * of the parent answers, whatever id is asked for; none saved, or no such
 * parent, answer errors); a post's and a page's revision deleted (refused
 * without force); and the routes refused signed out. Ids are named by
 * appearance, dates and preview nonces masked; everything made here is
 * deleted on the way out.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$menus = [];
register_shutdown_function(static function () use (&$made, &$menus): void {
    wp_set_current_user(1);
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($menus as $menu) {
        if (is_int($menu) && $menu > 0) {
            wp_delete_nav_menu($menu);
        }
    }
});
wp_set_current_user(1);
if (!did_action('init')) {
    do_action('init');
}
$ids = [];
$label = static function ($id) use (&$ids): void {
    $ids[(int) $id] ??= '{id' . (count($ids) + 1) . '}';
};
$mask = static function ($value, $key = '') use (&$mask, &$ids) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $item) {
            $out[$k] = $mask($item, (string) $k);
        }
        return $out;
    }
    if (in_array($key, ['date', 'date_gmt', 'modified', 'modified_gmt'], true)) {
        return '{date}';
    }
    if (is_int($value) && in_array($key, ['id', 'parent'], true)) {
        return $ids[$value] ?? $value;
    }
    if (is_string($value)) {
        $value = (string) preg_replace('/preview_nonce=[0-9a-f]+/', 'preview_nonce={nonce}', $value);
        $value = (string) preg_replace('#https?://#', '{scheme}://', $value);
        $value = (string) preg_replace_callback('/(?<=[=\/])\d+\b|^\d+(?=-(?:revision|autosave)-)/', static fn ($m) => $ids[(int) $m[0]] ?? $m[0], $value);
    }
    return $value;
};
$do = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    $data = $response->get_data();
    if (is_array($data) && isset($data['_links'])) {
        $data['_links'] = array_keys($data['_links']);
    } elseif (is_array($data) && array_is_list($data)) {
        $data = array_map(static fn ($item) => is_array($item) && isset($item['_links']) ? ['_links' => array_keys($item['_links'])] + $item : $item, $data);
    }
    return ['status' => $response->get_status(), 'data' => $data];
};

// A navigation menu with two revisions.
$nav = wp_insert_post(['post_type' => 'wp_navigation', 'post_title' => 'ZZ nav', 'post_content' => '<!-- wp:navigation-link {"label":"A","url":"/a"} /-->', 'post_status' => 'publish']);
if (!is_int($nav) || $nav <= 1) {
    fwrite(STDERR, "no navigation post\n");
    exit(1);
}
$made[] = $nav;
$label($nav);
wp_update_post(['ID' => $nav, 'post_content' => '<!-- wp:navigation-link {"label":"B","url":"/b"} /-->']);
wp_update_post(['ID' => $nav, 'post_title' => 'ZZ nav 3']);
foreach (wp_get_post_revisions($nav, ['order' => 'ASC']) as $revision) {
    $label($revision->ID);
}
$navRevisions = array_values(array_map(static fn ($revision) => $revision->ID, wp_get_post_revisions($nav)));
$say('a navigation menu\'s revisions', $do('GET', "/wp/v2/navigation/$nav/revisions"));
$say('one, in the edit context', $do('GET', "/wp/v2/navigation/$nav/revisions/{$navRevisions[0]}", ['context' => 'edit']));
$say('deleting one, without force and with it', [$do('DELETE', "/wp/v2/navigation/$nav/revisions/{$navRevisions[1]}"), $do('DELETE', "/wp/v2/navigation/$nav/revisions/{$navRevisions[1]}", ['force' => true])]);
$saved = $do('POST', "/wp/v2/navigation/$nav/autosaves", ['title' => 'ZZ nav autosave', 'content' => '<!-- wp:navigation-link {"label":"C","url":"/c"} /-->']);
$autosave = wp_get_post_autosave($nav, 1);
if ($autosave) {
    $label($autosave->ID);
}
$say('a navigation autosave saved', $do('GET', "/wp/v2/navigation/$nav/autosaves") + ['saved' => $saved]);
$say('read by id', $autosave ? $do('GET', "/wp/v2/navigation/$nav/autosaves/{$autosave->ID}") : 'none');

// A menu item's autosave keeps nothing of what is sent.
$menu = wp_create_nav_menu('ZZ autosave menu');
if (is_int($menu)) {
    $menus[] = $menu;
}
$item = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'ZZ item', 'menu-item-url' => 'https://example.com/zz', 'menu-item-status' => 'publish']);
if (!is_int($item) || $item <= 1) {
    fwrite(STDERR, "no menu item\n");
    exit(1);
}
$made[] = $item;
$label($item);
$saved = $do('POST', "/wp/v2/menu-items/$item/autosaves", ['title' => 'ZZ item autosave']);
$itemAutosave = wp_get_post_autosave($item, 1);
if ($itemAutosave) {
    $label($itemAutosave->ID);
}
$say('a menu item\'s autosave', ['saved' => $saved, 'listed' => $do('GET', "/wp/v2/menu-items/$item/autosaves"), 'read' => $itemAutosave ? $do('GET', "/wp/v2/menu-items/$item/autosaves/{$itemAutosave->ID}") : 'none', 'stored' => $itemAutosave ? [$itemAutosave->post_title, $itemAutosave->post_content, $itemAutosave->post_excerpt] : null, 'the item' => [get_post($item)->post_title]]);

// One autosave read by id: a published post, a draft, a page.
$post = wp_insert_post(['post_title' => 'ZZ auto post', 'post_name' => 'zz-auto-post', 'post_content' => 'p', 'post_status' => 'publish']);
$draft = wp_insert_post(['post_title' => 'ZZ auto draft', 'post_content' => 'd', 'post_status' => 'draft', 'post_author' => 2]);
$page = wp_insert_post(['post_title' => 'ZZ auto page', 'post_content' => 'g', 'post_status' => 'publish', 'post_type' => 'page']);
$plain = wp_insert_post(['post_title' => 'ZZ no autosave', 'post_status' => 'publish']);
foreach ([$post, $draft, $page, $plain] as $id) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture post\n");
        exit(1);
    }
    $made[] = $id;
    $label($id);
}
$one = [];
foreach (['a published post' => [$post, 'posts'], 'a draft by another author' => [$draft, 'posts'], 'a page' => [$page, 'pages']] as $what => [$id, $base]) {
    $saved = $do('POST', "/wp/v2/$base/$id/autosaves", ['title' => "ZZ $what autosaved", 'content' => 'saved']);
    $row = wp_get_post_autosave($id, 1);
    if ($row) {
        $label($row->ID);
    }
    $one[$what] = ['saved' => $saved, 'view' => $row ? $do('GET', "/wp/v2/$base/$id/autosaves/{$row->ID}") : 'none', 'edit' => $row ? $do('GET', "/wp/v2/$base/$id/autosaves/{$row->ID}", ['context' => 'edit']) : 'none', 'another id' => $do('GET', "/wp/v2/$base/$id/autosaves/$plain")];
}
$say('one autosave read by id', $one);
$say('read by id with none saved, and with no such parent', [$do('GET', "/wp/v2/posts/$plain/autosaves/$plain"), $do('GET', '/wp/v2/posts/99999999/autosaves/' . $post), $do('GET', "/wp/v2/pages/$post/autosaves/$post")]);

// A post's and a page's revision deleted.
wp_update_post(['ID' => $post, 'post_content' => 'p2']);
wp_update_post(['ID' => $page, 'post_content' => 'g2']);
$deleted = [];
foreach (['a post\'s' => [$post, 'posts'], 'a page\'s' => [$page, 'pages']] as $what => [$id, $base]) {
    $revision = array_values(array_map(static fn ($r) => $r->ID, wp_get_post_revisions($id)))[0] ?? 0;
    $label($revision);
    $deleted[$what] = [$do('DELETE', "/wp/v2/$base/$id/revisions/$revision"), $do('DELETE', "/wp/v2/$base/$id/revisions/$revision", ['force' => true]), 'gone' => get_post($revision) === null];
}
$say('a revision deleted', $deleted);

wp_set_current_user(0);
$say('signed out', [$do('GET', "/wp/v2/navigation/$nav/revisions"), $do('POST', "/wp/v2/menu-items/$item/autosaves", ['title' => 'x']), $do('GET', "/wp/v2/posts/$post/autosaves/$post"), $do('DELETE', "/wp/v2/posts/$post/revisions/$post", ['force' => true])]);

// Masked once every id made here has its name.
echo json_encode($mask($log), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
