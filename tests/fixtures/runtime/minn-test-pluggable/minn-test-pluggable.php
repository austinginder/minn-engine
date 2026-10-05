<?php
/**
 * Plugin Name: Minn pluggable probe
 * Description: A WordPress plugin, unchanged, that defines its own wp_mail and wp_generate_password the way SMTP and security plugins replace pluggable functions (behind function_exists, as they must be to survive activation). The cli suite switches it on and calls them on both stacks.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
    {
        return 'mail handled by the plugin for ' . $to;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        return str_repeat('p', (int) $length);
    }
}
