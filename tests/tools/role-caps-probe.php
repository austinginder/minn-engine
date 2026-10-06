<?php
/**
 * What a user holds (allcaps) for the capability maps a site stores: an
 * administrator, an editor with one capability added and one taken away,
 * a role set to false beside a real one, two roles, a key naming no role.
 * Role names are capabilities too: current_user_can('administrator') is
 * how Gravity Forms and others ask. Same protocol as api-probe.php; the
 * maps are set on an object in memory, nothing is saved.
 */

$log = [];
$user = new WP_User(1);
$cases = [
    'admin' => ['administrator' => true],
    'editor plus custom, minus edit_posts' => ['editor' => true, 'zz_custom' => true, 'edit_posts' => false],
    'role granted false' => ['editor' => false, 'author' => true],
    'two roles' => ['author' => true, 'contributor' => true, 'zz_off' => false],
    'unknown role-like key' => ['zz_no_such_role' => true, 'subscriber' => 1],
];
foreach ($cases as $label => $caps) {
    $user->caps = $caps;
    $all = $user->get_role_caps();
    $log[] = [$label . ': roles', $user->roles];
    $log[] = [$label . ': tail', array_slice($all, -4, null, true)];
    $log[] = [$label . ': count', count($all)];
    $log[] = [$label . ': has each stored cap', array_map(static fn ($cap) => $user->has_cap($cap), array_keys($caps))];
}
wp_set_current_user(1);
$log[] = ['current_user_can administrator', current_user_can('administrator')];
$log[] = ['user_can 1 editor', user_can(1, 'editor')];
echo json_encode($log, JSON_UNESCAPED_SLASHES), "\n";
