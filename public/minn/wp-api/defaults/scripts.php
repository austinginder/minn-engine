<?php
// The script handles the reference registers itself and plugin code depends on.
// The engine ships the MIT-licensed libraries under assets/vendor and serves them
// at the reference's paths so plugin markup stays byte-identical.

add_action('init', static function (): void {
    wp_register_script('jquery-core', '/wp-includes/js/jquery/jquery.min.js', [], '3.7.1');
    wp_register_script('jquery-migrate', '/wp-includes/js/jquery/jquery-migrate.min.js', [], '3.4.1');
    wp_register_script('jquery', false, ['jquery-core', 'jquery-migrate'], '3.7.1');
}, 0);
