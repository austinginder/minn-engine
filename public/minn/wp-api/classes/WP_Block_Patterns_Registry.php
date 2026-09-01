<?php

use Minn\Runtime\Patterns;

/** Block patterns, their categories, and block styles: three registries over one Minn\Runtime\Patterns store; the engine renders none of them. */
final class WP_Block_Patterns_Registry
{
    private static $instance = null;
    private static ?Patterns $store = null;

    public static function get_instance()
    {
        return self::$instance ??= new self();
    }

    public static function store(): Patterns
    {
        return self::$store ??= new Patterns();
    }

    public function register($pattern_name, $pattern_properties)
    {
        $refused = self::store()->registerPattern($pattern_name, $pattern_properties, (bool) did_action('init'));
        if ($refused !== null) {
            _doing_it_wrong(__METHOD__, $refused->message, '5.5.0');
            return false;
        }
        return true;
    }

    public function unregister($pattern_name)
    {
        if (!self::store()->unregisterPattern((string) $pattern_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Pattern "%s" not found.', $pattern_name), '5.5.0');
            return false;
        }
        return true;
    }

    /** A file-backed pattern's content is the file's output, produced only when asked for. */
    /**
     * A retrieved pattern carries its hooked blocks: one pass that both
     * inserts the markup and stamps ignoredHookedBlocks on the anchor,
     * with the pattern array as the context (which is how a plugin's
     * callback tells a header pattern from any other).
     */
    private function with_content(array $pattern): array
    {
        if (isset($pattern['filePath']) && !isset($pattern['content'])) {
            ob_start();
            include $pattern['filePath'];
            $pattern['content'] = (string) ob_get_clean();
        }
        $pattern['content'] = (string) ($pattern['content'] ?? '');
        if ($pattern['content'] !== '') {
            $pattern['content'] = apply_block_hooks_to_content($pattern['content'], $pattern, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
        }
        return $pattern;
    }

    public function get_registered($pattern_name)
    {
        $pattern = self::store()->pattern((string) $pattern_name);
        return $pattern === null ? null : $this->with_content($pattern);
    }

    public function get_all_registered($outside_init_only = false)
    {
        return array_map($this->with_content(...), self::store()->patterns((bool) $outside_init_only));
    }

    public function is_registered($pattern_name)
    {
        return self::store()->pattern((string) $pattern_name) !== null;
    }

    public function __wakeup()
    {
        throw new LogicException('WP_Block_Patterns_Registry should not be serialized');
    }
}

final class WP_Block_Pattern_Categories_Registry
{
    private static $instance = null;

    public static function get_instance()
    {
        return self::$instance ??= new self();
    }

    public function register($category_name, $category_properties)
    {
        $refused = WP_Block_Patterns_Registry::store()->registerCategory($category_name, $category_properties, (bool) did_action('init'));
        if ($refused !== null) {
            _doing_it_wrong(__METHOD__, $refused->message, '5.5.0');
            return false;
        }
        return true;
    }

    public function unregister($category_name)
    {
        if (!WP_Block_Patterns_Registry::store()->unregisterCategory((string) $category_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Block pattern category "%s" not found.', $category_name), '5.5.0');
            return false;
        }
        return true;
    }

    public function get_registered($category_name)
    {
        return WP_Block_Patterns_Registry::store()->category((string) $category_name);
    }

    public function get_all_registered($outside_init_only = false)
    {
        return WP_Block_Patterns_Registry::store()->categories((bool) $outside_init_only);
    }

    public function is_registered($category_name)
    {
        return WP_Block_Patterns_Registry::store()->category((string) $category_name) !== null;
    }
}

final class WP_Block_Styles_Registry
{
    private static $instance = null;

    public static function get_instance()
    {
        return self::$instance ??= new self();
    }

    public function register($block_name, $style_properties)
    {
        $refused = WP_Block_Patterns_Registry::store()->registerStyle($block_name, $style_properties);
        if ($refused !== null) {
            _doing_it_wrong(__METHOD__, $refused->message, $refused->code === 'style_name_spaces' ? '5.9.0' : '5.3.0');
            return false;
        }
        return true;
    }

    public function unregister($block_name, $block_style_name)
    {
        if (!WP_Block_Patterns_Registry::store()->unregisterStyle((string) $block_name, (string) $block_style_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Block "%1$s" does not contain a style named "%2$s".', $block_name, $block_style_name), '5.3.0');
            return false;
        }
        return true;
    }

    public function get_registered($block_name, $block_style_name)
    {
        return WP_Block_Patterns_Registry::store()->style((string) $block_name, (string) $block_style_name);
    }

    public function get_all_registered()
    {
        return WP_Block_Patterns_Registry::store()->styles();
    }

    public function get_registered_styles_for_block($block_name)
    {
        return WP_Block_Patterns_Registry::store()->styles((string) $block_name);
    }

    public function is_registered($block_name, $block_style_name)
    {
        return WP_Block_Patterns_Registry::store()->style((string) $block_name, (string) $block_style_name) !== null;
    }
}
