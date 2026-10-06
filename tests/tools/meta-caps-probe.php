<?php
/**
 * map_meta_cap and user_can for the meta capabilities WordPress maps
 * (plugins, themes, users, privacy, application passwords, comments, post
 * meta, the dynamic grants), as an administrator, an editor and an
 * author, with post or user 1 as the object. Same protocol as
 * api-probe.php.
 */
$caps = ['unfiltered_upload', 'unfiltered_html', 'edit_css', 'edit_files', 'edit_plugins', 'edit_themes', 'update_plugins', 'delete_plugins', 'install_plugins', 'upload_plugins', 'update_themes', 'delete_themes', 'install_themes', 'upload_themes', 'update_core', 'install_languages', 'update_languages', 'activate_plugins', 'deactivate_plugins', 'activate_plugin', 'deactivate_plugin', 'resume_plugin', 'resume_theme', 'delete_user', 'delete_users', 'create_users', 'promote_user', 'promote_users', 'add_users', 'remove_user', 'remove_users', 'list_users', 'edit_users', 'manage_options', 'export', 'import', 'export_others_personal_data', 'erase_others_personal_data', 'manage_privacy_options', 'customize', 'edit_dashboard', 'delete_site', 'create_app_password', 'list_app_passwords', 'read_app_password', 'edit_app_password', 'delete_app_passwords', 'delete_app_password', 'update_https', 'view_site_health_checks', 'setup_network', 'manage_network', 'upgrade_network', 'switch_themes', 'edit_theme_options', 'moderate_comments', 'manage_links', 'manage_categories', 'edit_comment', 'read', 'level_10', 'exist', 'upload_files', 'edit_block_binding', 'edit_post_meta', 'delete_post_meta', 'add_post_meta'];
$out = [];
foreach ([1, 2, 3] as $user) {
    foreach ($caps as $cap) {
        $out["{$user}:{$cap}"] = map_meta_cap($cap, $user, 1);
        $out["{$user}:{$cap}:can"] = user_can($user, $cap, 1);
    }
}
$log = [];
foreach ($out as $label => $value) {
    $log[] = [$label, $value];
}
echo json_encode($log), "\n";
