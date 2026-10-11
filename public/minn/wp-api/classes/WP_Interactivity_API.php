<?php

use Minn\Runtime\Runtime;

/** The Interactivity API object wp_interactivity() returns; every method maps onto the runtime's processor. */
class WP_Interactivity_API
{
    private static ?self $instance = null;
    /** @var list<string> the script modules that support client navigation */
    private array $minn_navigation_modules = [];
    private bool $minn_router_ready = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function state(?string $store_namespace = null, array $state = []): array
    {
        return Runtime::interactivity()->state($store_namespace, $state);
    }

    public function config(string $store_namespace, array $config = []): array
    {
        return Runtime::interactivity()->config($store_namespace, $config);
    }

    public function get_context(?string $store_namespace = null): array
    {
        return Runtime::interactivity()->context($store_namespace);
    }

    public function get_element(): ?array
    {
        return Runtime::interactivity()->element();
    }

    public function process_directives(string $html): string
    {
        return Runtime::interactivity()->process($html);
    }

    public function data_wp_context(array $context, string $store_namespace = ''): string
    {
        return wp_interactivity_data_wp_context($context, $store_namespace);
    }

    /** Hands the client its data, opts modules into client navigation, and readies the router once a region is processed. */
    public function add_hooks(): void
    {
        add_filter('script_module_data_@wordpress/interactivity', [$this, 'filter_script_module_interactivity_data']);
        add_filter('script_module_data_@wordpress/interactivity-router', [$this, 'filter_script_module_interactivity_router_data']);
        add_filter('wp_script_attributes', [$this, 'add_load_on_client_navigation_attribute_to_script_modules']);
        Runtime::interactivity()->whenRouterRegion(fn () => $this->minn_router_region());
    }

    public function print_client_interactivity_data()
    {
        _deprecated_function(__METHOD__, '6.7.0');
    }

    /** The interactivity module's data: the stored config, then state, each when there is some. */
    public function filter_script_module_interactivity_data(array $data): array
    {
        $config = Runtime::interactivity()->allConfig();
        $state = Runtime::interactivity()->allState();
        if ($config !== []) {
            $data['config'] = $config;
        }
        if ($state !== []) {
            $data['state'] = $state;
        }
        return $data;
    }

    /** The router module's data: its two announcements. */
    public function filter_script_module_interactivity_router_data(array $data): array
    {
        $data['i18n'] = ['loading' => __('Loading page, please wait.'), 'loaded' => __('Page Loaded.')];
        return $data;
    }

    public function register_script_modules()
    {
        _deprecated_function(__METHOD__, '6.7.0', 'wp_default_script_modules');
    }

    /** A module's script tag (its id with or without -js-module) loads on client navigation once the module supports it. */
    public function add_load_on_client_navigation_attribute_to_script_modules($attributes)
    {
        if (is_array($attributes) && ($attributes['type'] ?? null) === 'module' && isset($attributes['id'])
            && in_array(preg_replace('/-js-module$/', '', (string) $attributes['id']), $this->minn_navigation_modules, true)) {
            $attributes['data-wp-router-options'] = wp_json_encode(['loadOnClientNavigation' => true]);
        }
        return $attributes;
    }

    public function add_client_navigation_support_to_script_module(string $script_module_id)
    {
        $this->minn_navigation_modules[] = $script_module_id;
    }

    public function print_router_loading_and_screen_reader_markup()
    {
        _deprecated_function(__METHOD__, '6.7.0', 'WP_Interactivity_API::print_router_markup');
        $this->print_router_markup();
    }

    /** The router's loading bar. */
    public function print_router_markup()
    {
        echo "\t\t\t<div\n\t\t\t\tclass=\"wp-interactivity-router-loading-bar\"\n\t\t\t\tdata-wp-interactive=\"core/router/private\"\n\t\t\t\tdata-wp-class--start-animation=\"state.navigation.hasStarted\"\n\t\t\t\tdata-wp-class--finish-animation=\"state.navigation.hasFinished\"\n\t\t\t></div>";
    }

    /** @internal the first router region processed puts the loading bar in the footer and its animation styles on the page (probe interactivity-api-methods) */
    private function minn_router_region(): void
    {
        if ($this->minn_router_ready) {
            return;
        }
        $this->minn_router_ready = true;
        add_action('wp_footer', [$this, 'print_router_markup']);
        wp_register_style('wp-interactivity-router-animations', false);
        wp_add_inline_style('wp-interactivity-router-animations', "\t\t\t.wp-interactivity-router-loading-bar {\n\t\t\t\tposition: fixed;\n\t\t\t\ttop: 0;\n\t\t\t\tleft: 0;\n\t\t\t\tmargin: 0;\n\t\t\t\tpadding: 0;\n\t\t\t\twidth: 100vw;\n\t\t\t\tmax-width: 100vw !important;\n\t\t\t\theight: 4px;\n\t\t\t\tbackground-color: #000;\n\t\t\t\topacity: 0\n\t\t\t}\n\t\t\t.wp-interactivity-router-loading-bar.start-animation {\n\t\t\t\tanimation: wp-interactivity-router-loading-bar-start-animation 30s cubic-bezier(0.03, 0.5, 0, 1) forwards\n\t\t\t}\n\t\t\t.wp-interactivity-router-loading-bar.finish-animation {\n\t\t\t\tanimation: wp-interactivity-router-loading-bar-finish-animation 300ms ease-in\n\t\t\t}\n\t\t\t@keyframes wp-interactivity-router-loading-bar-start-animation {\n\t\t\t\t0% { transform: scaleX(0); transform-origin: 0 0; opacity: 1 }\n\t\t\t\t100% { transform: scaleX(1); transform-origin: 0 0; opacity: 1 }\n\t\t\t}\n\t\t\t@keyframes wp-interactivity-router-loading-bar-finish-animation {\n\t\t\t\t0% { opacity: 1 }\n\t\t\t\t50% { opacity: 1 }\n\t\t\t\t100% { opacity: 0 }\n\t\t\t}");
        wp_enqueue_style('wp-interactivity-router-animations');
    }
}
