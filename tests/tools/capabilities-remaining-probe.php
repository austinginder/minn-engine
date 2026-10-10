<?php
/**
 * The rest of capabilities.php (probe capabilities-remaining): what a post's
 * author can do (primitive and meta capabilities, a missing post, a post
 * whose author is gone), the current user's capabilities for a site, super
 * admin granted and revoked on a single site, and the capabilities granted
 * from the ones that imply them (install languages, resume extensions, site
 * health), alone and as user_has_cap filters on real users. The probe's
 * users and posts are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'posts' => []];
$admins = get_option('site_admins', null);
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $admins, $pings): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id, 1);
        }
    }
    $admins === null ? delete_option('site_admins') : update_option('site_admins', $admins);
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$user = static function (string $login, string $role) use (&$made): int {
    $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => "{$login}@example.com", 'role' => $role]);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "capabilities-remaining: the user {$login} could not be made\n");
        exit(1);
    }
    $made['users'][] = $id;
    return $id;
};
$post = static function (int $author, array $extra = []) use (&$made): int {
    $id = wp_insert_post($extra + ['post_title' => 'zz cr post', 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => $author]);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "capabilities-remaining: a post could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    return $id;
};

$editor = $user('zz_cr_editor', 'editor');
$author = $user('zz_cr_author', 'author');
$reader = $user('zz_cr_reader', 'subscriber');
$byEditor = $post($editor);
$byAuthor = $post($author, ['post_status' => 'draft']);
$byReader = $post($reader);
$byGone = $post($author);
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_author' => 999999], ['ID' => $byGone]);
clean_post_cache($byGone);

$can = [];
foreach (['an editor' => $byEditor, 'an author, a draft' => $byAuthor, 'a subscriber' => $byReader, 'a gone author' => $byGone] as $label => $id) {
    $can[$label] = [
        author_can($id, 'edit_posts'), author_can($id, 'publish_posts'), author_can($id, 'edit_others_posts'), author_can($id, 'manage_options'), author_can(get_post($id), 'read'),
        author_can($id, 'edit_post', $id), author_can($id, 'delete_post', $id), author_can($id, 'edit_post', $byEditor), author_can($id, 'zz_no_such_cap'),
    ];
}
$can['a missing post'] = [author_can(999999, 'read'), author_can(0, 'read'), author_can(null, 'read')];
$say('what a post author can do', $can);

$forSite = [];
foreach ([1 => 'the administrator', $editor => 'an editor', $reader => 'a subscriber', 0 => 'nobody'] as $id => $label) {
    wp_set_current_user($id);
    $forSite[$label] = [
        current_user_can_for_site(1, 'manage_options'), current_user_can_for_site(1, 'edit_posts'), current_user_can_for_site(get_current_blog_id(), 'read'),
        current_user_can_for_site(1, 'edit_post', $byEditor), current_user_can_for_site(999, 'read'), current_user_can_for_site(1, 'zz_no_such_cap'),
    ];
}
wp_set_current_user(1);
$say('capabilities for a site', $forSite);

$say('super admin on a single site', [grant_super_admin($editor), revoke_super_admin($editor), grant_super_admin(1), revoke_super_admin(1), grant_super_admin(999999), get_option('site_admins', 'unset'), is_super_admin($editor), is_super_admin(1)]);

$sets = [
    'nothing' => [], 'update core' => ['update_core' => true], 'install plugins' => ['install_plugins' => true], 'install themes' => ['install_themes' => true],
    'activate plugins' => ['activate_plugins' => true], 'switch themes' => ['switch_themes' => true], 'false values' => ['install_plugins' => false, 'activate_plugins' => false, 'switch_themes' => 0],
    'an administrator' => get_userdata(1)->allcaps,
];
$granted = [];
foreach ($sets as $label => $caps) {
    $languages = wp_maybe_grant_install_languages_cap($caps);
    $resume = wp_maybe_grant_resume_extensions_caps($caps);
    $health = wp_maybe_grant_site_health_caps($caps, ['view_site_health_checks'], ['view_site_health_checks', 1], get_userdata(1));
    $added = static fn ($after) => array_values(array_diff(array_keys(array_filter($after)), array_keys(array_filter($caps))));
    $granted[$label] = [$added($languages), $added($resume), $added($health), array_diff_key($languages, $caps) === [] ? 'none' : array_diff_key($languages, $caps)];
}
$granted['site health for another user'] = wp_maybe_grant_site_health_caps(['install_plugins' => true], ['view_site_health_checks'], ['view_site_health_checks', $reader], get_userdata($reader));
$say('capabilities granted from others', $granted);

$users = [];
foreach ([1 => 'the administrator', $editor => 'an editor', $reader => 'a subscriber'] as $id => $label) {
    $users[$label] = array_map(static fn ($cap) => user_can($id, $cap), ['install_languages', 'resume_plugins', 'resume_themes', 'view_site_health_checks', 'update_languages']);
}
$say('the granted capabilities on real users', $users);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
