<?php
/**
 * A post's template through wp_update_post (probe page-template-save): no
 * template given, an empty one, "default", and an empty one over a stored
 * template, for a post, a page and a global styles post. An empty template
 * leaves the stored one as it is and deletes nothing. Same protocol as
 * api-probe.php; the probe's drafts are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$heard = [];
$listener = static function ($check, $objectId, $key) use (&$heard) {
    $heard[] = $key;
    return $check;
};
foreach (['post', 'page', 'wp_global_styles'] as $type) {
    $id = wp_insert_post(['post_type' => $type, 'post_title' => 'Zz page template', 'post_status' => 'draft']);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "page-template-save: could not make the {$type}\n");
        exit(1);
    }
    $made[] = $id;
    foreach (['no template' => [], 'an empty one' => ['page_template' => ''], 'default' => ['page_template' => 'default'], 'an empty one over a stored one' => ['page_template' => '']] as $label => $extra) {
        if ($label === 'an empty one over a stored one') {
            update_post_meta($id, '_wp_page_template', 'default');
        }
        $heard = [];
        add_filter('delete_post_metadata', $listener, 10, 3);
        $saved = wp_update_post(['ID' => $id, 'post_title' => "Zz page template, {$label}"] + $extra);
        remove_filter('delete_post_metadata', $listener, 10);
        $say("{$type}: {$label}", ['saved' => $saved === $id, 'deleted' => $heard, 'template' => get_post_meta($id, '_wp_page_template', true)]);
    }
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
