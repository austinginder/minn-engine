<?php

/** Block patterns, their categories, and block styles: registered and listed; the engine renders none of them. */
final class WP_Block_Patterns_Registry
{
    private $registered_patterns = [];
    private $registered_patterns_outside_init = [];
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register($pattern_name, $pattern_properties)
    {
        if (!isset($pattern_name) || !is_string($pattern_name)) {
            _doing_it_wrong(__METHOD__, 'Pattern name must be a string.', '5.5.0');
            return false;
        }
        if (!isset($pattern_properties['title']) || !is_string($pattern_properties['title'])) {
            _doing_it_wrong(__METHOD__, 'Pattern title must be a string.', '5.5.0');
            return false;
        }
        if (!isset($pattern_properties['filePath'])) {
            if (!isset($pattern_properties['content']) || !is_string($pattern_properties['content'])) {
                _doing_it_wrong(__METHOD__, 'Pattern content must be a string.', '5.5.0');
                return false;
            }
        }
        $pattern = array_merge($pattern_properties, ['name' => $pattern_name]);
        $this->registered_patterns[$pattern_name] = $pattern;
        if (did_action('init')) {
            $this->registered_patterns_outside_init[$pattern_name] = $pattern;
        }
        return true;
    }

    public function unregister($pattern_name)
    {
        if (!$this->is_registered($pattern_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Pattern "%s" not found.', $pattern_name), '5.5.0');
            return false;
        }
        unset($this->registered_patterns[$pattern_name], $this->registered_patterns_outside_init[$pattern_name]);
        return true;
    }

    private function get_content($pattern_name, $outside_init_only = false)
    {
        $pattern = $outside_init_only ? ($this->registered_patterns_outside_init[$pattern_name] ?? null) : ($this->registered_patterns[$pattern_name] ?? null);
        if ($pattern === null) {
            return '';
        }
        if (isset($pattern['filePath']) && !isset($pattern['content'])) {
            ob_start();
            include $pattern['filePath'];
            return (string) ob_get_clean();
        }
        return (string) ($pattern['content'] ?? '');
    }

    public function get_registered($pattern_name)
    {
        if (!$this->is_registered($pattern_name)) {
            return null;
        }
        $pattern = $this->registered_patterns[$pattern_name];
        $pattern['content'] = $this->get_content($pattern_name);
        return $pattern;
    }

    public function get_all_registered($outside_init_only = false)
    {
        $patterns = array_values($outside_init_only ? $this->registered_patterns_outside_init : $this->registered_patterns);
        foreach ($patterns as $index => $pattern) {
            $patterns[$index]['content'] = $this->get_content($pattern['name'], $outside_init_only);
        }
        return $patterns;
    }

    public function is_registered($pattern_name)
    {
        return isset($this->registered_patterns[$pattern_name]);
    }

    public function __wakeup()
    {
        throw new LogicException('WP_Block_Patterns_Registry should not be serialized');
    }
}

final class WP_Block_Pattern_Categories_Registry
{
    private $registered_categories = [];
    private $registered_categories_outside_init = [];
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register($category_name, $category_properties)
    {
        if (!isset($category_name) || !is_string($category_name)) {
            _doing_it_wrong(__METHOD__, 'Block pattern category name must be a string.', '5.5.0');
            return false;
        }
        $category = array_merge(['name' => $category_name], $category_properties);
        $this->registered_categories[$category_name] = $category;
        if (did_action('init')) {
            $this->registered_categories_outside_init[$category_name] = $category;
        }
        return true;
    }

    public function unregister($category_name)
    {
        if (!$this->is_registered($category_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Block pattern category "%s" not found.', $category_name), '5.5.0');
            return false;
        }
        unset($this->registered_categories[$category_name], $this->registered_categories_outside_init[$category_name]);
        return true;
    }

    public function get_registered($category_name)
    {
        return $this->registered_categories[$category_name] ?? null;
    }

    public function get_all_registered($outside_init_only = false)
    {
        return array_values($outside_init_only ? $this->registered_categories_outside_init : $this->registered_categories);
    }

    public function is_registered($category_name)
    {
        return isset($this->registered_categories[$category_name]);
    }
}

final class WP_Block_Styles_Registry
{
    private $registered_block_styles = [];
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register($block_name, $style_properties)
    {
        if (!isset($block_name) || (!is_string($block_name) && !is_array($block_name))) {
            _doing_it_wrong(__METHOD__, 'Block name must be a string or array.', '5.3.0');
            return false;
        }
        if (!isset($style_properties['name']) || !is_string($style_properties['name'])) {
            _doing_it_wrong(__METHOD__, 'Block style name must be a string.', '5.3.0');
            return false;
        }
        if (str_contains($style_properties['name'], ' ')) {
            _doing_it_wrong(__METHOD__, 'Block style name must not contain any spaces.', '5.9.0');
            return false;
        }
        foreach ((array) $block_name as $name) {
            if (!isset($this->registered_block_styles[$name])) {
                $this->registered_block_styles[$name] = [];
            }
            $this->registered_block_styles[$name][$style_properties['name']] = $style_properties;
        }
        return true;
    }

    public function unregister($block_name, $block_style_name)
    {
        if (!$this->is_registered($block_name, $block_style_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Block "%1$s" does not contain a style named "%2$s".', $block_name, $block_style_name), '5.3.0');
            return false;
        }
        unset($this->registered_block_styles[$block_name][$block_style_name]);
        return true;
    }

    public function get_registered($block_name, $block_style_name)
    {
        return $this->registered_block_styles[$block_name][$block_style_name] ?? null;
    }

    public function get_all_registered()
    {
        return $this->registered_block_styles;
    }

    public function get_registered_styles_for_block($block_name)
    {
        return $this->registered_block_styles[$block_name] ?? [];
    }

    public function is_registered($block_name, $block_style_name)
    {
        return isset($this->registered_block_styles[$block_name][$block_style_name]);
    }
}
