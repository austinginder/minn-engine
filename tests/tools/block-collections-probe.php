<?php
/**
 * Block metadata collections (probe block-collections): a plugin's
 * blocks-manifest.php registered as a collection, where one may and may not
 * live, the metadata found for a block's folder or block.json through it,
 * the block.json files it lists, and blocks registered from it, one or all
 * at once. The probe's folders under the plugins directory are removed, and
 * so are the block types it registers.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$root = WP_PLUGIN_DIR . '/zz-probe-collection';
register_shutdown_function(static function () use ($root): void {
    if (is_dir($root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
    foreach (['zz/one', 'zz/two', 'zz/deep'] as $name) {
        if (WP_Block_Type_Registry::get_instance()->is_registered($name)) {
            unregister_block_type($name);
        }
    }
});
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong) {
    $wrong[] = [$function, wp_strip_all_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function () use (&$wrong) {
    $out = $wrong;
    $wrong = [];
    return $out;
};
// The directories differ between the stacks; their names stand in for them, longest first.
$places = [WP_PLUGIN_DIR => '{plugins}', WPMU_PLUGIN_DIR => '{mu-plugins}', get_theme_root() => '{themes}', WP_CONTENT_DIR => '{content}', rtrim(ABSPATH, '/') => '{abspath}'];
$mask = static function ($value) use ($places, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? strtr($value, $places) : $value;
};
$manifest = static function (string $dir, array $blocks): string {
    is_dir($dir) || mkdir($dir, 0777, true);
    file_put_contents($file = $dir . '/blocks-manifest.php', "<?php\nreturn " . var_export($blocks, true) . ";\n");
    return $file;
};

$one = ['apiVersion' => 3, 'name' => 'zz/one', 'title' => 'Zz One', 'category' => 'widgets', 'attributes' => ['a' => ['type' => 'string', 'default' => 'x']]];
$two = ['apiVersion' => 3, 'name' => 'zz/two', 'title' => 'Zz Two', 'supports' => ['html' => false]];
$build = $root . '/build';
$file = $manifest($build, ['zz-one' => $one, 'zz-two' => $two, 'zz-one-extra' => ['name' => 'zz/one-extra']]);

$say('collections registered', $mask([
    (static function () use ($build, $file, $heard) {
        $roots = [];
        $listen = static function ($value, ...$rest) use (&$roots) {
            $roots[] = [is_array($value) ? $value : gettype($value), count($rest)];
            return $value;
        };
        add_filter('wp_allowed_block_metadata_collection_roots', $listen, 10, 5);
        $out = [WP_Block_Metadata_Registry::register_collection($build . '/', $file), $heard(), $roots];
        remove_filter('wp_allowed_block_metadata_collection_roots', $listen, 10);
        return $out;
    })(),
    [WP_Block_Metadata_Registry::register_collection($root . '/missing', $root . '/no-manifest.php'), $heard()],
    [WP_Block_Metadata_Registry::register_collection(WP_PLUGIN_DIR, $file), $heard()],
    [WP_Block_Metadata_Registry::register_collection(WP_CONTENT_DIR . '/', $file), $heard()],
    [WP_Block_Metadata_Registry::register_collection(dirname(ABSPATH), $file), $heard()],
    [WP_Block_Metadata_Registry::register_collection(WP_PLUGIN_DIR, $root . '/no-manifest.php'), $heard()],
    [WP_Block_Metadata_Registry::register_collection('/zz-elsewhere', $file), $heard()],
    (static function () use ($root, $heard) {
        $narrowed = static fn () => [WP_PLUGIN_DIR . '/zz-probe-collection'];
        add_filter('wp_allowed_block_metadata_collection_roots', $narrowed);
        $out = [WP_Block_Metadata_Registry::register_collection($root, $root . '/build/blocks-manifest.php'), $heard()];
        remove_filter('wp_allowed_block_metadata_collection_roots', $narrowed);
        return $out;
    })(),
]));

$say('metadata found', $mask([
    WP_Block_Metadata_Registry::get_metadata($build . '/zz-one'),
    WP_Block_Metadata_Registry::get_metadata($build . '/zz-two/block.json'),
    WP_Block_Metadata_Registry::get_metadata($build . '/zz-three'),
    array_map(static fn ($path) => WP_Block_Metadata_Registry::has_metadata($path), [
        $build . '/zz-one', $build . '/zz-one/', $build . '/zz-one/block.json', $build . '/zz-one-extra', $build . '/zz-one/index.php', $build . '/sub/zz-one',
        $root . '/buildzz/zz-one', $build, '', '/zz-elsewhere/zz-one', '/zz-elsewhere-more/zz-two', '/other/zz-one',
    ]),
]));

$say('the block.json files listed', $mask([
    WP_Block_Metadata_Registry::get_collection_block_metadata_files($build),
    WP_Block_Metadata_Registry::get_collection_block_metadata_files($build . '/'),
    WP_Block_Metadata_Registry::get_collection_block_metadata_files($root . '/nowhere'),
]));

$deep = $manifest($build . '/deep', ['zz-one' => ['name' => 'zz/deep', 'title' => 'Zz Deep']]);
$say('collections inside collections', $mask([
    WP_Block_Metadata_Registry::register_collection($build . '/deep', $deep),
    WP_Block_Metadata_Registry::get_metadata($build . '/deep/zz-one'),
    WP_Block_Metadata_Registry::get_metadata($build . '/zz-one'),
    WP_Block_Metadata_Registry::get_metadata($build . '/deep/zz-one'),
    (static function () use ($build, $manifest) {
        $again = $manifest($build . '/again', ['zz-one' => ['name' => 'zz/again']]);
        return [WP_Block_Metadata_Registry::register_collection($build, $again), WP_Block_Metadata_Registry::get_metadata($build . '/zz-one'), WP_Block_Metadata_Registry::get_metadata($build . '/zz-two')];
    })(),
]));

$type = static function ($result) {
    return $result instanceof WP_Block_Type ? [$result->name, $result->title, $result->category, $result->attributes, $result->supports, $result->api_version] : $result;
};
$fresh = $root . '/fresh';
$freshFile = $manifest($fresh, ['zz-one' => $one, 'zz-two' => $two]);
$say('blocks registered from a collection', $mask([
    (static function () use ($fresh, $freshFile, $type, $heard) {
        WP_Block_Metadata_Registry::register_collection($fresh, $freshFile);
        $out = [$type(register_block_type_from_metadata($fresh . '/zz-one')), $heard()];
        unregister_block_type('zz/one');
        $out[] = $type(register_block_type_from_metadata($fresh . '/zz-one/block.json', ['title' => 'Zz Given']));
        unregister_block_type('zz/one');
        $out[] = [$type(register_block_type_from_metadata($fresh . '/zz-nothing')), $heard()];
        return $out;
    })(),
    (static function () use ($fresh, $freshFile, $type, $heard) {
        $result = wp_register_block_types_from_metadata_collection($fresh, $freshFile);
        $out = [$result, $type(WP_Block_Type_Registry::get_instance()->get_registered('zz/one')), $type(WP_Block_Type_Registry::get_instance()->get_registered('zz/two')), $heard()];
        unregister_block_type('zz/one');
        unregister_block_type('zz/two');
        return $out;
    })(),
    (static function () use ($fresh, $heard) {
        $result = wp_register_block_types_from_metadata_collection($fresh);
        $out = [$result, WP_Block_Type_Registry::get_instance()->is_registered('zz/one'), $heard()];
        foreach (['zz/one', 'zz/two'] as $name) {
            if (WP_Block_Type_Registry::get_instance()->is_registered($name)) {
                unregister_block_type($name);
            }
        }
        return $out;
    })(),
    (static function () use ($root, $heard) {
        return [wp_register_block_types_from_metadata_collection($root . '/nowhere', $root . '/no-manifest.php'), $heard()];
    })(),
    (static function () use ($root, $one, $manifest, $heard) {
        $other = $manifest($root . '/other', ['zz-one' => $one]);
        return [wp_register_block_metadata_collection($root . '/other', $other), WP_Block_Metadata_Registry::has_metadata($root . '/other/zz-one'), $heard()];
    })(),
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
