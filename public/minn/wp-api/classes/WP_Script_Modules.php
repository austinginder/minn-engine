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
    private ?Minn\Runtime\ScriptModules $minn_registry = null;
    /** The registry this object keeps: the page's for wp_script_modules()'s, one of its own for any other (probe script-modules-printing). */
    private function minn(): Minn\Runtime\ScriptModules
    {
        return $this === self::$instance ? Runtime::scriptModules() : $this->minn_registry ??= Runtime::newScriptModules();
    }

    public function register(string $id, string $src, array $deps = [], $version = false, array $args = []): void
    {
        $this->minn()->register($id, $src, $deps, $version, $args);
    }

    public function get_queue(): array
    {
        return $this->minn()->queue();
    }

    public function set_fetchpriority(string $id, string $priority): bool
    {
        return $this->minn()->setFetchpriority($id, $priority);
    }

    public function set_in_footer(string $id, bool $in_footer): bool
    {
        return $in_footer ? $this->minn()->moveToFooter($id) : $this->minn()->moveToHead($id);
    }

    public function enqueue(string $id, string $src = '', array $deps = [], $version = false, array $args = []): void
    {
        $this->minn()->enqueue($id, $src, $deps, $version, $args);
    }

    public function dequeue(string $id): void
    {
        $this->minn()->dequeue($id);
    }

    public function deregister(string $id): void
    {
        $this->minn()->deregister($id);
    }

    /** @var array<string, array{domain: string, path: string}> each module's translation domain and folder */
    private array $minn_translations = [];

    /** A registered module's translations domain and folder, kept; false for a module not registered (probe hollow-methods). */
    public function set_translations(string $id, string $domain = 'default', string $path = ''): bool
    {
        if ($this->minn()->registered($id) === null) {
            return false;
        }
        $this->minn_translations[$id] = ['domain' => $domain, 'path' => $path];
        return true;
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
        echo $this->minn()->printHead();
    }

    public function print_enqueued_script_modules(): void
    {
        echo $this->minn()->printFooter();
    }

    public function print_script_module_preloads(): void
    {
        echo $this->minn()->printPreloads();
    }

    public function print_import_map(): void
    {
        echo $this->minn()->printImportMap();
    }

    public function get_registered(string $id): ?array
    {
        return $this->minn()->registered($id);
    }

    public function print_script_module_data(): void
    {
        echo $this->minn()->printData();
    }

    public function print_a11y_script_module_html(): void
    {
        echo $this->minn()->printA11y();
    }

    // Private in the reference too; plugins call them through reflection (Query Monitor's assets panel).
    private function get_marked_for_enqueue(): array
    {
        return $this->minn()->markedForEnqueue();
    }

    private function get_dependencies(array $ids, array $import_types = ['static', 'dynamic']): array
    {
        return $this->minn()->dependencyRecords($ids, $import_types);
    }

    private function get_src(string $id): string
    {
        return $this->minn()->source($id);
    }
}
