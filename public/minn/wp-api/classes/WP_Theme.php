<?php

use Minn\Theme\Folder;

/** A theme by its style.css headers and folder. */
#[AllowDynamicProperties]
class WP_Theme implements ArrayAccess
{
    private static $headers = ['Name' => 'Theme Name', 'ThemeURI' => 'Theme URI', 'Description' => 'Description', 'Author' => 'Author', 'AuthorURI' => 'Author URI', 'Version' => 'Version', 'Template' => 'Template', 'Status' => 'Status', 'Tags' => 'Tags', 'TextDomain' => 'Text Domain', 'DomainPath' => 'Domain Path', 'RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP', 'UpdateURI' => 'Update URI'];
    public $update = false;
    private $theme_root;
    private $stylesheet;
    private $template;
    private $headersData = [];
    private $parent;
    private $errors;

    public function __construct($theme_dir, $theme_root, $_child = null)
    {
        $folder = Folder::read((string) $theme_root, (string) $theme_dir, self::$headers, static fn (string $file, array $labels) => get_file_data($file, $labels, 'theme'));
        $this->theme_root = $folder->root;
        $this->stylesheet = $folder->slug;
        $this->headersData = $folder->headers;
        $this->template = $folder->template;
        if (!$folder->exists) {
            $this->errors = new WP_Error('theme_not_found', 'The theme directory "' . $this->stylesheet . '" does not exist.');
            return;
        }
        if ($this->template !== $this->stylesheet) {
            $this->parent = new WP_Theme($this->template, $this->theme_root, $this);
        }
    }

    public function __toString()
    {
        return (string) $this->display('Name');
    }

    public function __get($offset)
    {
        return match ($offset) {
            'name', 'title' => $this->get('Name'),
            'version' => $this->get('Version'),
            'parent_theme' => $this->parent() ? $this->parent()->get('Name') : '',
            'template_dir' => $this->get_template_directory(),
            'stylesheet_dir' => $this->get_stylesheet_directory(),
            'template' => $this->get_template(),
            'stylesheet' => $this->get_stylesheet(),
            'screenshot' => $this->get_screenshot('relative'),
            'description' => $this->get('Description'),
            'author' => $this->display('Author'),
            'tags' => $this->get('Tags'),
            'theme_root' => $this->get_theme_root(),
            'theme_root_uri' => $this->get_theme_root_uri(),
            default => null,
        };
    }

    public function __isset($offset)
    {
        return in_array($offset, ['name', 'title', 'version', 'parent_theme', 'template_dir', 'stylesheet_dir', 'template', 'stylesheet', 'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri'], true);
    }

    public function errors()
    {
        return $this->errors ?? false;
    }

    /** The folders the theme keeps block templates and parts in: the old block-templates and block-template-parts when it has either, else templates and parts. */
    public function get_block_template_folders()
    {
        $dir = $this->get_stylesheet_directory();
        if (file_exists($dir . '/block-templates') || file_exists($dir . '/block-template-parts')) {
            return ['wp_template' => 'block-templates', 'wp_template_part' => 'block-template-parts'];
        }
        return ['wp_template' => 'templates', 'wp_template_part' => 'parts'];
    }

    public function exists()
    {
        return $this->errors === null;
    }

    public function parent()
    {
        return $this->parent ?? false;
    }

    /** A header as written; Tags as a list, and Status "publish" when an existing theme leaves it out. A theme that is not there has only its Name (its folder). */
    public function get($header)
    {
        if (!$this->exists() && $header !== 'Name') {
            return false;
        }
        $value = $this->headersData[$header] ?? false;
        if ($header === 'Tags' && is_string($value)) {
            return $value === '' ? [] : array_map('trim', explode(',', $value));
        }
        if ($header === 'Status' && $value === '' && $this->exists()) {
            return 'publish';
        }
        return $value;
    }

    /** A header as shown: feature tags by name in the admin, and with markup tags joined, the author linked to their address, addresses escaped. */
    public function display($header, $markup = true, $translate = true)
    {
        $value = $this->get($header);
        if ($translate && $header === 'Tags' && is_array($value) && is_admin()) {
            // In the admin, where the reference has its theme feature list loaded, feature tags read as their
            // names (data/theme-tags.json); a REST or front request gets them as written.
            static $names = null;
            $names ??= (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/theme-tags.json'), true);
            $value = array_map(static fn ($tag) => $names[$tag] ?? $tag, $value);
        }
        if (!$markup || !is_string($value) && !is_array($value)) {
            return $value;
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        $value = wp_kses($value, ['a' => ['href' => true, 'title' => true], 'abbr' => ['title' => true], 'acronym' => ['title' => true], 'code' => [], 'em' => [], 'strong' => []]);
        $authorUri = (string) $this->get('AuthorURI');
        return match (true) {
            $header === 'Author' && $value !== '' && $authorUri !== '' => sprintf('<a href="%1$s">%2$s</a>', esc_url($authorUri), $value),
            $header === 'AuthorURI', $header === 'ThemeURI' => esc_url($value),
            default => $value,
        };
    }

    public function get_stylesheet()
    {
        return $this->stylesheet;
    }

    public function get_template()
    {
        return $this->template;
    }

    public function get_stylesheet_directory()
    {
        return $this->theme_root . '/' . $this->stylesheet;
    }

    public function get_template_directory()
    {
        return ($this->parent ? $this->parent->theme_root : $this->theme_root) . '/' . $this->template;
    }

    public function get_stylesheet_directory_uri()
    {
        return $this->get_theme_root_uri() . '/' . str_replace('%2F', '/', rawurlencode($this->stylesheet));
    }

    public function get_template_directory_uri()
    {
        return $this->get_theme_root_uri() . '/' . str_replace('%2F', '/', rawurlencode($this->template));
    }

    public function get_theme_root()
    {
        return $this->theme_root;
    }

    public function get_theme_root_uri()
    {
        return get_theme_root_uri($this->stylesheet, $this->theme_root);
    }

    public function get_screenshot($uri = 'uri')
    {
        $file = Folder::read($this->theme_root, $this->stylesheet, self::$headers)->screenshot();
        if ($file === null) {
            return false;
        }
        return $uri === 'relative' ? $file : $this->get_stylesheet_directory_uri() . '/' . $file;
    }

    /** The theme's files of a type (an extension or a list of them; any when null), to a depth (-1 for all), relative path => path; its parent's after when asked. */
    public function get_files($type = null, $depth = 0, $search_parent = false)
    {
        $exclusions = (array) apply_filters('theme_scandir_exclusions', ['CVS', 'node_modules', 'vendor', 'bower_components']);
        $types = $type === null ? null : (array) $type;
        $files = self::scan($this->get_stylesheet_directory(), '', (int) $depth, $exclusions, $types);
        if ($search_parent && $this->parent()) {
            $files += self::scan($this->get_template_directory(), '', (int) $depth, $exclusions, $types);
        }
        return array_filter($files);
    }

    /**
     * The templates a post can choose, by post type (probe placeholders-a):
     * the theme's PHP files, one folder deep and in folder order, that name
     * themselves with a Template Name header (for the types a Template Post
     * Type header lists, pages by default); then, where the site's theme
     * makes templates of blocks, the custom templates its theme.json names.
     *
     * @return array<string, array<string, string>>
     */
    public function get_post_templates()
    {
        if ($this->errors()) {
            return [];
        }
        $templates = [];
        foreach ($this->php_files() as $relative => $path) {
            $headers = get_file_data($path, ['Name' => 'Template Name', 'PostType' => 'Template Post Type']);
            if ($headers['Name'] === '') {
                continue;
            }
            $types = $headers['PostType'] === '' ? ['page'] : array_map('sanitize_key', array_map('trim', explode(',', $headers['PostType'])));
            foreach ($types as $type) {
                $templates[$type][$relative] = translate($headers['Name'], (string) $this->get('TextDomain'));
            }
        }
        if (current_theme_supports('block-templates')) {
            foreach (self::custom_block_templates() as $template) {
                foreach ((array) ($template['postTypes'] ?? ['page']) as $type) {
                    $templates[(string) $type][(string) $template['name']] = (string) ($template['title'] ?? $template['name']);
                }
            }
        }
        return $templates;
    }

    /** One post type's templates, file => name, through theme_templates and theme_{$post_type}_templates. */
    public function get_page_templates($post = null, $post_type = 'page')
    {
        if ($post) {
            $post_type = get_post_type($post);
        }
        $templates = $this->get_post_templates()[$post_type] ?? [];
        $templates = (array) apply_filters('theme_templates', $templates, $this, $post, $post_type);
        return (array) apply_filters("theme_{$post_type}_templates", $templates, $this, $post, $post_type);
    }

    /** The theme's PHP files one folder deep, in folder order (a parent's after the theme's own), relative path => path. @return array<string, string> */
    private function php_files(): array
    {
        return $this->get_files('php', 1, $this->get_stylesheet() !== $this->get_template());
    }

    /**
     * A folder's files, skipping dot files and the exclusions, to a depth
     * (-1 for all), of the types given (all when null).
     *
     * @param list<string> $exclusions @param list<string>|null $types
     * @return array<string, string>
     */
    private static function scan(string $dir, string $prefix, int $depth, array $exclusions, ?array $types): array
    {
        $found = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $entry) {
            if ($entry[0] === '.' || in_array($entry, $exclusions, true)) {
                continue;
            }
            if (is_dir("{$dir}/{$entry}")) {
                $found += $depth !== 0 ? self::scan("{$dir}/{$entry}", "{$prefix}{$entry}/", $depth - 1, $exclusions, $types) : [];
            } elseif ($types === null || in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $types, true)) {
                $found["{$prefix}{$entry}"] = "{$dir}/{$entry}";
            }
        }
        return $found;
    }

    /** The custom templates the site's theme.json names (a child's replacing its parent's by name). @return list<array<string, mixed>> */
    private static function custom_block_templates(): array
    {
        $byName = [];
        foreach (array_unique([get_template_directory(), get_stylesheet_directory()]) as $dir) {
            $json = is_file("{$dir}/theme.json") ? (array) json_decode((string) file_get_contents("{$dir}/theme.json"), true) : [];
            foreach ((array) ($json['customTemplates'] ?? []) as $template) {
                if (is_array($template) && isset($template['name'])) {
                    $byName[(string) $template['name']] = $template;
                }
            }
        }
        return array_values($byName);
    }

    /** The theme's translations loaded under its Text Domain; false for a theme that names none. */
    public function load_textdomain()
    {
        return (bool) $this->get('TextDomain');
    }

    public function is_allowed($check = 'both', $blog_id = null)
    {
        return true;
    }

    public function is_block_theme()
    {
        return Folder::isBlockTheme([$this->get_stylesheet_directory(), $this->get_template_directory()]);
    }

    public function get_file_path($file = '')
    {
        return Folder::filePath($this->get_stylesheet_directory(), $this->get_template_directory(), (string) $file);
    }

    /** The theme's patterns by file name, read from their headers (Minn\Theme\PatternHeaders); [] for a theme that is not there. */
    public function get_block_patterns()
    {
        return $this->exists() ? \Minn\Theme\PatternHeaders::all($this->get_stylesheet_directory()) : [];
    }

    /** Nothing is cached of a theme's patterns here. */
    public function delete_pattern_cache()
    {
    }

    /** Nothing is cached of a theme here. */
    public function cache_delete()
    {
    }

    /** A theme read back from a serialized copy. */
    public function __wakeup()
    {
    }

    /** The themes allowed for a site: the network's list, then the site's (probe wp-theme). */
    /** The themes allowed for a site: the network's (network_allowed_themes), then the site's own. */
    public static function get_allowed($blog_id = null)
    {
        $network = (array) apply_filters('network_allowed_themes', self::get_allowed_on_network(), (int) ($blog_id ?? get_current_blog_id()));
        return array_merge($network, self::get_allowed_on_site($blog_id));
    }

    /** The network's allowedthemes site option, as a list (false read as [false], as the reference reads it), through allowed_themes. */
    public static function get_allowed_on_network()
    {
        return (array) apply_filters('allowed_themes', (array) get_site_option('allowedthemes'));
    }

    /** The site's allowedthemes option when it is a list, [] otherwise, through site_allowed_themes. */
    public static function get_allowed_on_site($blog_id = null)
    {
        $allowed = get_option('allowedthemes');
        return (array) apply_filters('site_allowed_themes', is_array($allowed) ? $allowed : [], (int) ($blog_id ?? get_current_blog_id()));
    }

    /** Themes allowed across the network: the stylesheets added to the allowedthemes site option. */
    public static function network_enable_theme($stylesheets)
    {
        $allowed = array_filter((array) get_site_option('allowedthemes'));
        foreach ((array) $stylesheets as $stylesheet) {
            $allowed[$stylesheet] = true;
        }
        update_site_option('allowedthemes', $allowed);
    }

    /** Themes no longer allowed across the network: the stylesheets taken out of the allowedthemes site option. */
    public static function network_disable_theme($stylesheets)
    {
        $allowed = array_filter((array) get_site_option('allowedthemes'));
        foreach ((array) $stylesheets as $stylesheet) {
            unset($allowed[$stylesheet]);
        }
        update_site_option('allowedthemes', $allowed);
    }

    /** The default theme (WP_DEFAULT_THEME) when it is installed, else the newest core theme there is; false for none. */
    public static function get_core_default_theme()
    {
        foreach (array_filter([defined('WP_DEFAULT_THEME') ? (string) WP_DEFAULT_THEME : '', 'twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty']) as $slug) {
            $theme = wp_get_theme($slug);
            if ($theme->exists()) {
                return $theme;
            }
        }
        return false;
    }

    public static function sort_by_name(&$themes)
    {
        uasort($themes, static fn (WP_Theme $a, WP_Theme $b) => strnatcasecmp($a->get('Name'), $b->get('Name')));
    }

    public function offsetExists($offset): bool
    {
        return in_array($offset, [
            'Name', 'Version', 'Title', 'Author', 'Author Name', 'Author URI', 'Description',
            'Template', 'Stylesheet', 'Screenshot', 'Tags', 'Theme Root', 'Theme Root URI',
            'Parent Theme', 'Status', 'Template Dir', 'Stylesheet Dir', 'Template Files', 'Stylesheet Files',
        ], true);
    }

    /** Offsets that are simply a header under another name. */
    private const OFFSET_HEADERS = ['Name' => 'Name', 'Title' => 'Name', 'Version' => 'Version', 'Author URI' => 'AuthorURI', 'Tags' => 'Tags', 'Status' => 'Status'];

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $header = self::OFFSET_HEADERS[$offset] ?? null;
        if ($header === null) {
            return $this->offsetComputed((string) $offset);
        }
        $value = $this->get($header);
        return $offset === 'Status' ? ($value ?: 'publish') : $value;
    }

    /** The offsets that are built rather than read straight off a header. */
    private function offsetComputed(string $offset)
    {
        return match ($offset) {
            'Author' => $this->display('Author'),
            'Author Name' => $this->display('Author', false, false),
            'Description' => $this->display('Description', false),
            'Template' => $this->get_template(),
            'Stylesheet' => $this->get_stylesheet(),
            'Screenshot' => $this->get_screenshot('relative'),
            'Theme Root' => $this->get_theme_root(),
            'Theme Root URI' => $this->get_theme_root_uri(),
            'Parent Theme' => $this->parent() ? $this->parent()->get('Name') : '',
            default => $this->offsetPlace($offset),
        };
    }

    /** The legacy keys for the theme's folders and files. */
    private function offsetPlace(string $offset)
    {
        return match ($offset) {
            'Template Dir' => $this->get_template_directory(),
            'Stylesheet Dir' => $this->get_stylesheet_directory(),
            'Template Files' => array_values($this->get_files('php', 1, true)),
            'Stylesheet Files' => array_values($this->get_files('css', 0)),
            default => null,
        };
    }

    public function offsetSet($offset, $value): void
    {
    }

    public function offsetUnset($offset): void
    {
    }

}
