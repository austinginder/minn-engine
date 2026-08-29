<?php

/** A theme by its style.css headers and folder. */
class WP_Theme
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
        $this->theme_root = (string) $theme_root;
        $this->stylesheet = (string) $theme_dir;
        $file = $this->theme_root . '/' . $this->stylesheet . '/style.css';
        if (!is_file($file)) {
            $this->errors = new WP_Error('theme_not_found', 'The theme directory "' . $this->stylesheet . '" does not exist.');
            $this->template = $this->stylesheet;
            foreach (array_keys(self::$headers) as $key) {
                $this->headersData[$key] = '';
            }
            $this->headersData['Name'] = $this->stylesheet;
            return;
        }
        $this->headersData = get_file_data($file, self::$headers, 'theme');
        $this->template = $this->headersData['Template'] !== '' ? $this->headersData['Template'] : $this->stylesheet;
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
            'author' => $this->get('Author'),
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

    public function exists()
    {
        return $this->errors === null;
    }

    public function parent()
    {
        return $this->parent ?? false;
    }

    public function get($header)
    {
        $value = $this->headersData[$header] ?? false;
        if ($header === 'Tags' && is_string($value)) {
            return $value === '' ? [] : array_map('trim', explode(',', $value));
        }
        return $value;
    }

    public function display($header, $markup = true, $translate = true)
    {
        $value = $this->get($header);
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $markup && is_string($value) ? wp_kses($value, ['a' => ['href' => true, 'title' => true], 'abbr' => ['title' => true], 'acronym' => ['title' => true], 'code' => [], 'em' => [], 'strong' => []]) : $value;
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
        foreach (['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'] as $ext) {
            if (is_file($this->get_stylesheet_directory() . '/screenshot.' . $ext)) {
                return $uri === 'relative' ? 'screenshot.' . $ext : $this->get_stylesheet_directory_uri() . '/screenshot.' . $ext;
            }
        }
        return false;
    }

    public function get_files($type = null, $depth = 0, $search_parent = false)
    {
        return [];
    }

    public function get_post_templates()
    {
        return [];
    }

    public function get_page_templates($post = null, $post_type = 'page')
    {
        return [];
    }

    public function load_textdomain()
    {
        return true;
    }

    public function is_allowed($check = 'both', $blog_id = null)
    {
        return true;
    }

    public function is_block_theme()
    {
        foreach ([$this->get_stylesheet_directory(), $this->get_template_directory()] as $dir) {
            if (is_file($dir . '/templates/index.html') || is_file($dir . '/block-templates/index.html')) {
                return true;
            }
        }
        return false;
    }

    public function get_file_path($file = '')
    {
        $file = ltrim((string) $file, '/');
        if ($file === '') {
            return $this->get_stylesheet_directory();
        }
        if (is_file($this->get_stylesheet_directory() . '/' . $file)) {
            return $this->get_stylesheet_directory() . '/' . $file;
        }
        return $this->get_template_directory() . '/' . $file;
    }

    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    public static function get_allowed($blog_id = null)
    {
        return [];
    }

    public static function sort_by_name(&$themes)
    {
        uasort($themes, static fn (WP_Theme $a, WP_Theme $b) => strnatcasecmp($a->get('Name'), $b->get('Name')));
    }
}
