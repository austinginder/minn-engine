<?php
// The script modules registry hooks its printing into the head and footer,
// and the interactivity runtime hands its state and config to the client.

add_action('init', 'wp_default_script_modules', 0);
wp_script_modules()->add_hooks();
add_filter('script_module_data_@wordpress/interactivity', static function ($data) {
    $interactivity = Minn\Runtime\Runtime::interactivity();
    $config = $interactivity->allConfig();
    $state = $interactivity->allState();
    if ($config !== []) {
        $data['config'] = $config;
    }
    if ($state !== []) {
        $data['state'] = $state;
    }
    return $data;
});
add_filter('script_module_data_@wordpress/interactivity-router', static function ($data) {
    $data['i18n'] = ['loading' => __('Loading page, please wait.'), 'loaded' => __('Page Loaded.')];
    return $data;
});
