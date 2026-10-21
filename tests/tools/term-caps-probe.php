<?php
/**
 * The term meta capabilities (edit_term, delete_term, assign_term) on one
 * term of every registered taxonomy, built-in ones too (a menu's, a link
 * category's), as an administrator, an editor and an author: map_meta_cap
 * and user_can. The terms are made for the probe and removed after it.
 * Same protocol as api-probe.php.
 */
$terms = [];
foreach (get_taxonomies() as $taxonomy) {
    $made = wp_insert_term('zz-term-caps-' . $taxonomy, $taxonomy);
    if (!is_wp_error($made)) {
        $terms[$taxonomy] = (int) $made['term_id'];
    }
}
$log = [];
foreach ($terms as $taxonomy => $id) {
    foreach ([1, 2, 3] as $user) {
        foreach (['edit_term', 'delete_term', 'assign_term'] as $cap) {
            $log[] = ["{$taxonomy}:{$user}:{$cap}", map_meta_cap($cap, $user, $id)];
            $log[] = ["{$taxonomy}:{$user}:{$cap}:can", user_can($user, $cap, $id)];
        }
    }
}
foreach ($terms as $taxonomy => $id) {
    wp_delete_term($id, $taxonomy);
}
echo json_encode($log), "\n";
