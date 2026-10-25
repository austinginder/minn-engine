<?php
/**
 * Menu items left without a title (probe nav-menu-untitled): what
 * wp_setup_nav_menu_item gives a post, term, archive or custom item whose
 * own title is empty and whose object is gone, untitled, or an id of 0, an
 * item that keeps its own title, the label and address beside the title,
 * and the the_title calls made on the way. The items and the page it makes
 * are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$titles = [];
$untitled = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => '', 'post_content' => 'x']);
$made[] = $untitled;
add_filter('the_title', static function ($title, $id = null) use (&$titles, $untitled) {
    $titles[] = [$title, $id === null ? null : ((int) $id === $untitled ? 'the untitled page' : 'another post')];
    return $title;
}, 10, 2);
$item = static function (array $meta, string $title = '') use (&$made): object {
    $id = (int) wp_insert_post(['post_type' => 'nav_menu_item', 'post_status' => 'publish', 'post_title' => $title]);
    $made[] = $id;
    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }
    return get_post($id);
};
$cases = [
    'a page gone, no title' => [['_menu_item_type' => 'post_type', '_menu_item_object' => 'page', '_menu_item_object_id' => '999999'], ''],
    'a page gone, its own title' => [['_menu_item_type' => 'post_type', '_menu_item_object' => 'page', '_menu_item_object_id' => '999999'], 'Mine'],
    'a post gone, no title' => [['_menu_item_type' => 'post_type', '_menu_item_object' => 'post', '_menu_item_object_id' => '999998'], ''],
    'an untitled page, no title' => [['_menu_item_type' => 'post_type', '_menu_item_object' => 'page', '_menu_item_object_id' => (string) $untitled], ''],
    'a term gone, no title' => [['_menu_item_type' => 'taxonomy', '_menu_item_object' => 'category', '_menu_item_object_id' => '999997'], ''],
    'an archive of a type not registered, no title' => [['_menu_item_type' => 'post_type_archive', '_menu_item_object' => 'zz_nope'], ''],
    'a custom link, no title' => [['_menu_item_type' => 'custom', '_menu_item_object' => 'custom', '_menu_item_url' => 'https://example.com/'], ''],
    'a page id 0, no title' => [['_menu_item_type' => 'post_type', '_menu_item_object' => 'page', '_menu_item_object_id' => '0'], ''],
];
foreach ($cases as $name => [$meta, $title]) {
    $titles = [];
    $out = wp_setup_nav_menu_item($item($meta, $title));
    $say($name, [str_replace((string) $untitled, '{page}', (string) $out->title), $out->_invalid ?? 'unset', $out->type_label, is_string($out->url) ? str_replace((string) $untitled, '{page}', $out->url) : $out->url, $titles]);
}
foreach ($made as $id) {
    if (is_int($id) && $id > 1) {
        wp_delete_post($id, true);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
