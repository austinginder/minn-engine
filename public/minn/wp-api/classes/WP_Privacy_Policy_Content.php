<?php

/** Suggested privacy policy text plugins add from admin_init, kept for the policy guide. */
#[AllowDynamicProperties]
final class WP_Privacy_Policy_Content
{
    private static $policy_content = [];

    public static function add($plugin_name, $policy_text)
    {
        if (empty($plugin_name) || empty($policy_text)) {
            return;
        }
        self::$policy_content[] = ['plugin_name' => $plugin_name, 'policy_text' => $policy_text];
    }
}
