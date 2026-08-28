<?php
/**
 * Minn Engine ships this file for the tooling that reads it: WP-CLI parses
 * $wp_version from it, hosting panels and backup tools stat it. The values
 * name the WordPress release whose contracts the engine speaks; the engine
 * contains none of that release's code.
 */

$wp_version = '7.1';
$wp_db_version = 61833;
$tinymce_version = '49110-20250317';
$required_php_version = '7.4';
$required_mysql_version = '5.5.5';
$required_php_extensions = array('json', 'hash');
