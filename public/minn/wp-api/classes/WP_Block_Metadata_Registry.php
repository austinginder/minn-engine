<?php

/**
 * Block metadata collections (probe block-collections): a plugin's
 * blocks-manifest.php registered for a directory, so each block's metadata
 * is read from the manifest instead of its block.json. A path belongs to
 * the first collection whose directory it starts with (as a string, the
 * last match tried first); the block is named by the path's last segment,
 * or the folder of a block.json. A collection may not sit at, or above, the
 * core directories (wp_allowed_block_metadata_collection_roots).
 */
#[AllowDynamicProperties]
class WP_Block_Metadata_Registry
{
    private static $collections = [];
    private static $last_matched_collection = null;
    private static $default_collection_roots = null;

    /** Registers a manifest for a directory (again replaces it, in place); false, with a notice, for a forbidden directory or a manifest that is not there. */
    public static function register_collection($path, $manifest)
    {
        $path = rtrim(wp_normalize_path((string) $path), '/');
        $roots = apply_filters('wp_allowed_block_metadata_collection_roots', self::get_default_collection_roots());
        if (!self::is_valid_collection_path($path, $roots)) {
            _doing_it_wrong(__METHOD__, sprintf(__('Block metadata collections cannot be registered as one of the following directories or their parent directories: %s'), implode(', ', $roots)), '6.7.2');
            return false;
        }
        if (!file_exists((string) $manifest)) {
            _doing_it_wrong(__METHOD__, __('The specified manifest file does not exist.'), '6.7.0');
            return false;
        }
        self::$collections[$path] = ['manifest' => (string) $manifest, 'blocks' => null];
        return true;
    }

    /** The metadata the collection holding a block's folder (or block.json) keeps for it, or null. */
    public static function get_metadata($file_or_folder)
    {
        $path = self::find_collection_path($file_or_folder);
        if ($path === null) {
            return null;
        }
        self::$collections[$path]['blocks'] ??= (array) require self::$collections[$path]['manifest'];
        return self::$collections[$path]['blocks'][self::default_identifier_callback($file_or_folder)] ?? null;
    }

    /** The block.json path of each block a collection lists; none, with a notice, for a directory with no collection. */
    public static function get_collection_block_metadata_files($path)
    {
        $path = rtrim(wp_normalize_path((string) $path), '/');
        if (!isset(self::$collections[$path])) {
            _doing_it_wrong(__METHOD__, __('No registered block metadata collection was found for the provided path.'), '6.8.0');
            return [];
        }
        self::$collections[$path]['blocks'] ??= (array) require self::$collections[$path]['manifest'];
        return array_map(static fn ($id) => $path . '/' . $id . '/block.json', array_keys(self::$collections[$path]['blocks']));
    }

    /** Whether a collection keeps metadata for a block's folder or block.json. */
    public static function has_metadata($file_or_folder)
    {
        return self::get_metadata($file_or_folder) !== null;
    }

    private static function find_collection_path($file_or_folder)
    {
        $path = wp_normalize_path((string) $file_or_folder);
        if ($path === '') {
            return null;
        }
        if (self::$last_matched_collection !== null && str_starts_with($path, self::$last_matched_collection)) {
            return self::$last_matched_collection;
        }
        foreach (array_keys(self::$collections) as $collection) {
            if (str_starts_with($path, (string) $collection)) {
                return self::$last_matched_collection = (string) $collection;
            }
        }
        return null;
    }

    private static function default_identifier_callback($path)
    {
        $path = (string) $path;
        return str_ends_with($path, 'block.json') ? basename(dirname($path)) : basename($path);
    }

    private static function is_valid_collection_path($path, $collection_roots)
    {
        foreach ((array) $collection_roots as $root) {
            $root = rtrim(wp_normalize_path((string) $root), '/');
            if ($path === $root || str_starts_with($root . '/', $path . '/')) {
                return false;
            }
        }
        return true;
    }

    private static function get_default_collection_roots()
    {
        if (self::$default_collection_roots === null) {
            $themes = (array) ($GLOBALS['wp_theme_directories'] ?? []) ?: [get_theme_root()];
            self::$default_collection_roots = array_map(static fn ($dir) => rtrim(wp_normalize_path((string) $dir), '/'), [ABSPATH . WPINC, WP_CONTENT_DIR, WPMU_PLUGIN_DIR, WP_PLUGIN_DIR, ...$themes]);
        }
        return self::$default_collection_roots;
    }
}
