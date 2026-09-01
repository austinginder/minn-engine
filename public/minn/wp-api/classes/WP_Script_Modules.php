<?php

use Minn\Runtime\Runtime;

/** The script modules object wp_script_modules() returns; every method maps onto the runtime's registry. */
class WP_Script_Modules
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function register(string $id, string $src, array $deps = [], $version = false, array $args = []): void
    {
        Runtime::scriptModules()->register($id, $src, $deps, $version, $args);
    }

    public function get_queue(): array
    {
        return Runtime::scriptModules()->queue();
    }

    public function set_fetchpriority(string $id, string $priority): bool
    {
        return Runtime::scriptModules()->setFetchpriority($id, $priority);
    }

    public function set_in_footer(string $id, bool $in_footer): bool
    {
        return $in_footer ? Runtime::scriptModules()->moveToFooter($id) : Runtime::scriptModules()->moveToHead($id);
    }

    public function enqueue(string $id, string $src = '', array $deps = [], $version = false, array $args = []): void
    {
        Runtime::scriptModules()->enqueue($id, $src, $deps, $version, $args);
    }

    public function dequeue(string $id): void
    {
        Runtime::scriptModules()->dequeue($id);
    }

    public function deregister(string $id): void
    {
        Runtime::scriptModules()->deregister($id);
    }

    public function set_translations(string $id, string $domain = 'default', string $path = ''): void
    {
    }

    public function print_script_module_translations(): void
    {
    }

    public function add_hooks(): void
    {
        add_action('wp_head', [$this, 'print_import_map']);
        add_action('wp_head', [$this, 'print_head_enqueued_script_modules']);
        add_action('wp_head', [$this, 'print_script_module_preloads']);
        add_action('wp_footer', [$this, 'print_enqueued_script_modules']);
        add_action('wp_footer', [$this, 'print_script_module_data']);
        add_action('wp_footer', [$this, 'print_a11y_script_module_html'], 20);
        add_action('wp_footer', [$this, 'print_script_module_translations'], 21);
        add_action('admin_print_footer_scripts', [$this, 'print_import_map'], 9);
        add_action('admin_print_footer_scripts', [$this, 'print_enqueued_script_modules']);
        add_action('admin_print_footer_scripts', [$this, 'print_script_module_preloads']);
        add_action('admin_print_footer_scripts', [$this, 'print_script_module_data']);
        add_action('admin_print_footer_scripts', [$this, 'print_script_module_translations'], 11);
        add_action('admin_print_footer_scripts', [$this, 'print_a11y_script_module_html'], 20);
    }

    public function print_head_enqueued_script_modules(): void
    {
        echo Runtime::scriptModules()->printHead();
    }

    public function print_enqueued_script_modules(): void
    {
        echo Runtime::scriptModules()->printFooter();
    }

    public function print_script_module_preloads(): void
    {
        echo Runtime::scriptModules()->printPreloads();
    }

    public function print_import_map(): void
    {
        echo Runtime::scriptModules()->printImportMap();
    }

    public function get_registered(string $id): ?array
    {
        return Runtime::scriptModules()->registered($id);
    }

    public function print_script_module_data(): void
    {
        echo Runtime::scriptModules()->printData();
    }

    public function print_a11y_script_module_html(): void
    {
        echo Runtime::scriptModules()->printA11y();
    }
}
