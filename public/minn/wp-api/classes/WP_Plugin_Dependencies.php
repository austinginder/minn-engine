<?php

/**
 * Plugin dependencies from Requires Plugins headers (probe
 * plugin-dependencies): which plugins depend on which slugs, both ways;
 * the files and names behind them; unmet and circular dependencies; and
 * the admin notices about them. Slugs are lowercase words joined by
 * single hyphens; anything else in the header is dropped. A dependency
 * loop is found by walking each dependent's dependencies, and their
 * dependencies in turn, leaving out slugs already on the path: a pair is
 * a dependent and the dependency that needs it back, and a slug that
 * requires itself pairs with itself. The plugins API is asked about
 * missing dependencies only on admin screens, so Minn's update service
 * answers there and nothing is asked anywhere else.
 */
class WP_Plugin_Dependencies
{
    protected static $plugins;
    protected static $plugin_dirnames;
    protected static $dependencies;
    protected static $dependency_slugs;
    protected static $dependent_slugs;
    protected static $dependency_api_data;
    protected static $dependency_filepaths;
    protected static $circular_dependencies_pairs;
    protected static $circular_dependencies_slugs;
    protected static $initialized = false;

    public static function initialize()
    {
        if (self::$initialized) {
            return;
        }
        self::read_dependencies_from_plugin_headers();
        self::get_dependency_api_data();
        self::$initialized = true;
    }

    public static function has_dependents($plugin_file)
    {
        return in_array(self::convert_to_slug($plugin_file), (array) self::$dependency_slugs, true);
    }

    public static function has_dependencies($plugin_file)
    {
        return isset(self::$dependencies[$plugin_file]);
    }

    public static function has_active_dependents($plugin_file)
    {
        foreach (self::get_dependents(self::convert_to_slug($plugin_file)) as $dependent) {
            if (is_plugin_active($dependent)) {
                return true;
            }
        }
        return false;
    }

    public static function get_dependents($slug)
    {
        return array_keys(array_filter((array) self::$dependencies, static fn ($slugs) => in_array($slug, (array) $slugs, true)));
    }

    public static function get_dependencies($plugin_file)
    {
        return self::$dependencies[$plugin_file] ?? [];
    }

    public static function get_dependent_filepath($slug)
    {
        return array_search($slug, (array) self::$dependent_slugs, true);
    }

    public static function has_unmet_dependencies($plugin_file)
    {
        foreach (self::get_dependencies($plugin_file) as $slug) {
            $file = self::get_dependency_filepath($slug);
            if ($file === false || !is_plugin_active($file)) {
                return true;
            }
        }
        return false;
    }

    public static function has_circular_dependency($plugin_file)
    {
        self::get_circular_dependencies();
        return in_array(self::convert_to_slug($plugin_file), (array) self::$circular_dependencies_slugs, true);
    }

    public static function get_dependent_names($plugin_file)
    {
        $plugins = self::get_plugins();
        $names = array_map(static fn ($file) => (string) ($plugins[$file]['Name'] ?? $file), self::get_dependents(self::convert_to_slug($plugin_file)));
        sort($names);
        return $names;
    }

    /** Each dependency's name: the installed plugin's, the plugins API's, or its slug. */
    public static function get_dependency_names($plugin_file)
    {
        $plugins = self::get_plugins();
        $names = [];
        foreach (self::get_dependencies($plugin_file) as $slug) {
            $file = self::get_dependency_filepath($slug);
            $names[$slug] = $file !== false ? (string) ($plugins[$file]['Name'] ?? $slug) : (string) (self::$dependency_api_data[$slug]['name'] ?? $slug);
        }
        return $names;
    }

    public static function get_dependency_filepath($slug)
    {
        return self::get_dependency_filepaths()[$slug] ?? false;
    }

    public static function get_dependency_data($slug)
    {
        return self::$dependency_api_data[$slug] ?? false;
    }

    public static function display_admin_notice_for_unmet_dependencies()
    {
        foreach (array_keys((array) self::$dependencies) as $dependent) {
            if (self::has_unmet_dependencies($dependent)) {
                $on_plugins = (get_current_screen()->base ?? '') === 'plugins';
                wp_admin_notice('Some required plugins are missing or inactive.' . ($on_plugins ? '' : sprintf(' <a href="%s">Manage plugins</a>.', esc_url(admin_url('plugins.php')))), ['type' => 'warning']);
                return;
            }
        }
    }

    public static function display_admin_notice_for_circular_dependencies()
    {
        // Each loop once, as first found.
        $pairs = array_values(array_unique(array_map(static fn (array $pair) => implode('|', $pair), self::get_circular_dependencies())));
        if ($pairs === []) {
            return;
        }
        $plugins = self::get_plugins();
        $name = static fn (string $slug) => (string) ($plugins[self::get_dependent_filepath($slug)]['Name'] ?? $slug);
        $items = implode('', array_map(static fn (string $pair) => vsprintf('<li><strong>%1$s</strong> requires <strong>%2$s</strong></li>', array_map(static fn ($slug) => esc_html($name($slug)), explode('|', $pair))), $pairs));
        wp_admin_notice('<p>These plugins cannot be activated because their requirements are invalid.</p><ul>' . $items . '</ul><p>Please contact the plugin authors for more information.</p>', ['type' => 'warning', 'paragraph_wrap' => false]);
    }

    /** The plugin install screen asks whether a plugin's dependencies are met before it is activated. */
    public static function check_plugin_dependencies_during_ajax()
    {
        check_ajax_referer('updates');
        $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        if ($slug === '') {
            wp_send_json_error(['slug' => '', 'pluginName' => '', 'errorCode' => 'no_plugin_specified', 'errorMessage' => 'No plugin specified.']);
        }
        self::initialize();
        $file = self::get_dependent_filepath($slug);
        $unmet = $file === false ? [] : array_keys(array_filter(self::get_dependency_names($file), static fn ($name, $dependency) => self::get_dependency_filepath($dependency) === false || !is_plugin_active(self::get_dependency_filepath($dependency)), ARRAY_FILTER_USE_BOTH));
        if ($unmet !== []) {
            wp_send_json_error(['slug' => $slug, 'pluginName' => (string) (self::get_plugins()[$file]['Name'] ?? $slug), 'errorCode' => 'unmet_dependencies', 'errorMessage' => sprintf('This plugin requires the following plugins: %s.', implode(', ', $unmet))]);
        }
        wp_send_json_success();
    }

    protected static function get_plugins()
    {
        return self::$plugins ??= get_plugins();
    }

    protected static function read_dependencies_from_plugin_headers()
    {
        self::$dependencies = [];
        self::$dependency_slugs = [];
        self::$dependent_slugs = [];
        foreach (self::get_plugins() as $file => $header) {
            $slugs = empty($header['RequiresPlugins']) ? [] : self::sanitize_dependency_slugs($header['RequiresPlugins']);
            if ($slugs !== []) {
                self::$dependencies[$file] = $slugs;
                self::$dependent_slugs[$file] = self::convert_to_slug($file);
                self::$dependency_slugs = [...self::$dependency_slugs, ...$slugs];
            }
        }
        self::$dependency_slugs = array_values(array_unique(self::$dependency_slugs));
    }

    /** The slugs a header names, each through wp_plugin_dependencies_slug: valid ones only, once each, sorted. */
    protected static function sanitize_dependency_slugs($slugs)
    {
        $sanitized = [];
        foreach (explode(',', (string) $slugs) as $slug) {
            $slug = (string) apply_filters('wp_plugin_dependencies_slug', trim($slug));
            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1) {
                $sanitized[] = $slug;
            }
        }
        $sanitized = array_values(array_unique($sanitized));
        sort($sanitized);
        return $sanitized;
    }

    protected static function get_dependency_filepaths()
    {
        if (is_array(self::$dependency_filepaths)) {
            return self::$dependency_filepaths;
        }
        $dirnames = self::get_plugin_dirnames();
        self::$dependency_filepaths = [];
        foreach ((array) self::$dependency_slugs as $slug) {
            self::$dependency_filepaths[$slug] = $dirnames[$slug] ?? false;
        }
        return self::$dependency_filepaths;
    }

    /** What the plugins API says about each dependency, on admin screens only, kept for a day. */
    protected static function get_dependency_api_data()
    {
        if (!is_admin()) {
            return;
        }
        self::$dependency_api_data = (array) get_site_transient('wp_plugin_dependencies_plugin_data');
        foreach ((array) self::$dependency_slugs as $slug) {
            if (!isset(self::$dependency_api_data[$slug]) && self::get_dependency_filepath($slug) === false) {
                $information = plugins_api('plugin_information', (object) ['slug' => $slug, 'fields' => ['short_description' => true, 'icons' => true]]);
                self::$dependency_api_data[$slug] = is_wp_error($information) ? ['name' => $slug] : (array) $information;
            }
        }
        set_site_transient('wp_plugin_dependencies_plugin_data', self::$dependency_api_data, DAY_IN_SECONDS);
        return self::$dependency_api_data;
    }

    protected static function get_plugin_dirnames()
    {
        if (is_array(self::$plugin_dirnames)) {
            return self::$plugin_dirnames;
        }
        self::$plugin_dirnames = [];
        foreach (array_keys(self::get_plugins()) as $file) {
            self::$plugin_dirnames[self::convert_to_slug($file)] = $file;
        }
        return self::$plugin_dirnames;
    }

    protected static function get_circular_dependencies()
    {
        if (is_array(self::$circular_dependencies_pairs)) {
            return self::$circular_dependencies_pairs;
        }
        self::$circular_dependencies_slugs = [];
        $pairs = [];
        foreach ((array) self::$dependencies as $file => $slugs) {
            $pairs = [...$pairs, ...self::check_for_circular_dependencies([self::$dependent_slugs[$file]], $slugs)];
        }
        return self::$circular_dependencies_pairs = $pairs;
    }

    protected static function check_for_circular_dependencies($dependents, $dependencies)
    {
        $pairs = [];
        foreach (array_intersect($dependents, $dependencies) as $self) {
            $pairs[] = [$self, $self];
            self::$circular_dependencies_slugs[] = $self;
        }
        foreach (array_diff($dependencies, $dependents) as $dependency) {
            $file = array_search($dependency, (array) self::$dependent_slugs, true);
            if ($file !== false) {
                $pairs = [...$pairs, ...self::loops_through($dependents, $dependency, (array) self::$dependencies[$file])];
                $dependents[] = $dependency;
            }
        }
        return $pairs;
    }

    /** The loops through one dependency: each dependent on the path it needs back, then its own dependencies off the path. */
    private static function loops_through(array $dependents, string $dependency, array $theirs): array
    {
        $pairs = [];
        foreach (array_intersect($dependents, $theirs) as $dependent) {
            $pairs[] = [$dependent, $dependency];
            array_push(self::$circular_dependencies_slugs, $dependent, $dependency);
        }
        $dependents[] = $dependency;
        return [...$pairs, ...self::check_for_circular_dependencies($dependents, array_values(array_diff(array_unique($theirs), $dependents)))];
    }

    /** A plugin file's slug: its folder, or its file name; hello.php is hello-dolly. */
    protected static function convert_to_slug($plugin_file)
    {
        if ($plugin_file === 'hello.php') {
            return 'hello-dolly';
        }
        return str_contains((string) $plugin_file, '/') ? dirname((string) $plugin_file) : str_replace('.php', '', (string) $plugin_file);
    }
}
