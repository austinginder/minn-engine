<?php
/**
 * The sixth wave of the plugin catalogue's queue (probe plugin-queue6), as
 * the reference answers it: a block theme's template files listed and
 * found one by one, built into templates from the file and from a saved
 * post, the file's own template by id past what the site saved, a
 * template located for a request and resolved for a type, the theme
 * attribute put into and taken out of template parts; the supports a
 * block type declares for skipping serialization and borders, the presets
 * class, the pagination arrows, a block's name from a theme.json path, a
 * block asset's address, empty navigation blocks
 * filtered out, the empty-template warning, and the block-support style
 * queued for printing. Everything made is removed at the end. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING);
if (!did_action('init')) {
    do_action('init');
}
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    if (!in_array([$function, $replacement, $version], $deprecated, true)) {
        $deprecated[] = [$function, $replacement, $version];
    }
}, 10, 3);
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$stylesheet = get_stylesheet();
$themeDir = get_stylesheet_directory();
$ids = [];
$mask = static function ($value) use (&$mask, &$ids, $stylesheet, $themeDir) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    $canvases = array_filter([ABSPATH . WPINC . '/template-canvas.php', defined('MINN_ENGINE_DIR') ? MINN_ENGINE_DIR . '/wp-api/template-canvas.php' : '']);
    $host = (string) parse_url(home_url(), PHP_URL_HOST);
    // The site's address is one token, either scheme: the CLI runtimes differ only in whether they count as https.
    $value = str_replace([...$canvases, $themeDir, ABSPATH, 'https://' . $host, 'http://' . $host, $stylesheet], [...array_fill(0, count($canvases), '{canvas}'), '{theme-dir}', '{abspath}/', '{home}', '{home}', '{theme}'], $value);
    if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) && abs(strtotime($value . ' UTC') - (int) current_time('timestamp')) < 300) {
        return '{now}';
    }
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
// A template as callers compare it: every field, the content by length and digest.
$template = static function ($found) use ($mask) {
    if (!$found instanceof WP_Block_Template) {
        return is_wp_error($found) ? ['error' => $found->get_error_code()] : $found;
    }
    $fields = get_object_vars($found);
    $content = (string) ($fields['content'] ?? '');
    $fields['content'] = [strlen($content), md5($content), substr($content, 0, 120)];
    ksort($fields);
    return $mask($fields);
};

// Template files.
$say('_get_block_template_file', $mask([
    _get_block_template_file('wp_template', 'index'),
    _get_block_template_file('wp_template', 'single'),
    _get_block_template_file('wp_template_part', 'header'),
    _get_block_template_file('wp_template_part', 'footer'),
    _get_block_template_file('wp_template', 'zz-nope'),
    _get_block_template_file('wp_template_part', 'index'),
    $try(static fn () => _get_block_template_file('nope', 'index')),
]));
$files = static fn ($type, $query = []) => $mask(array_map(static function ($file) {
    ksort($file);
    return $file;
}, (array) _get_block_templates_files($type, $query)));
$say('_get_block_templates_files templates', $files('wp_template'));
$say('_get_block_templates_files parts', $files('wp_template_part'));
$say('_get_block_templates_files by slug', $files('wp_template', ['slug__in' => ['index', 'page', 'zz-nope']]));
$say('_get_block_templates_files not by slug', array_column($files('wp_template', ['slug__not_in' => ['index', 'page']]), 'slug'));
$say('_get_block_templates_files by area', $files('wp_template_part', ['area' => 'header']));
$say('_get_block_templates_files for a post type', array_column($files('wp_template', ['post_type' => 'page']), 'slug'));
$say('_get_block_templates_files of nothing', [$try(static fn () => _get_block_templates_files('nope')), $try(static fn () => _get_block_templates_files('wp_template', ['slug__in' => ['zz-nope']]))]);
$say('_build_block_template_result_from_file a template', $template(_build_block_template_result_from_file(_get_block_template_file('wp_template', 'index'), 'wp_template')));
$say('_build_block_template_result_from_file a part', $template(_build_block_template_result_from_file(_get_block_template_file('wp_template_part', 'header'), 'wp_template_part')));
$say('_build_block_template_result_from_file a custom template', (function () use ($template, $themeDir) {
    $custom = array_values(array_filter((array) _get_block_templates_files('wp_template'), static fn ($file) => !isset(get_default_block_template_types()[$file['slug']])));
    return $custom === [] ? 'no custom template' : $template(_build_block_template_result_from_file($custom[0], 'wp_template'));
})());

// The file's template, past what the site saved.
$saved = wp_insert_post(['post_type' => 'wp_template', 'post_status' => 'publish', 'post_name' => 'single', 'post_title' => 'ZZ Saved Single', 'post_content' => '<!-- wp:paragraph --><p>ZZ saved single.</p><!-- /wp:paragraph -->', 'post_excerpt' => 'ZZ saved', 'tax_input' => ['wp_theme' => [$stylesheet]]]);
$saved = is_int($saved) ? $saved : 0;
if ($saved > 0) {
    $made[] = $saved;
    $ids[$saved] = 'saved';
    wp_set_post_terms($saved, [$stylesheet], 'wp_theme');
}
$part = wp_insert_post(['post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => 'zz-queue-part', 'post_title' => 'ZZ Queue Part', 'post_content' => '<!-- wp:paragraph --><p>ZZ part.</p><!-- /wp:paragraph -->']);
$part = is_int($part) ? $part : 0;
if ($part > 0) {
    $made[] = $part;
    $ids[$part] = 'part';
    wp_set_post_terms($part, [$stylesheet], 'wp_theme');
    wp_set_post_terms($part, ['footer'], 'wp_template_part_area');
}
$orphan = wp_insert_post(['post_type' => 'wp_template', 'post_status' => 'publish', 'post_name' => 'zz-orphan', 'post_title' => 'ZZ Orphan', 'post_content' => 'x']);
$orphan = is_int($orphan) ? $orphan : 0;
if ($orphan > 0) {
    $made[] = $orphan;
    $ids[$orphan] = 'orphan';
}
$say('get_block_template, saved over the file', $template(get_block_template($stylesheet . '//single')));
$heard = [];
$spy = static function ($value, ...$rest) use (&$heard) {
    $heard[] = [current_filter(), count($rest), is_object($value) ? get_class($value) : gettype($value)];
    return $value;
};
add_filter('pre_get_block_file_template', $spy, 10, 3);
add_filter('get_block_file_template', $spy, 10, 3);
$say('get_block_file_template, the file under it', $template(get_block_file_template($stylesheet . '//single')));
$say('get_block_file_template, a part', $template(get_block_file_template($stylesheet . '//header', 'wp_template_part')));
$say('get_block_file_template of nothing', [$template(get_block_file_template($stylesheet . '//zz-nope')), $template(get_block_file_template('zz-other//index')), $template(get_block_file_template('index')), $template(get_block_file_template($stylesheet . '//zz-queue-part', 'wp_template_part'))]);
$say('the file template filters', $heard);
remove_filter('pre_get_block_file_template', $spy, 10);
remove_filter('get_block_file_template', $spy, 10);
$short = static fn () => 'zz-short';
add_filter('pre_get_block_file_template', $short);
$say('get_block_file_template, answered early', get_block_file_template($stylesheet . '//index'));
remove_filter('pre_get_block_file_template', $short);
$say('_build_block_template_result_from_post a saved template', $template($try(static fn () => _build_block_template_result_from_post(get_post($saved)))));
$say('_build_block_template_result_from_post a saved part', $template($try(static fn () => _build_block_template_result_from_post(get_post($part)))));
$say('_build_block_template_result_from_post without a theme', $template($try(static fn () => _build_block_template_result_from_post(get_post($orphan)))));
$say('_build_block_template_result_from_post of a post', $template($try(static fn () => _build_block_template_result_from_post(get_post(1)))));

// Located and resolved.
$say('resolve_block_template', [
    $template(resolve_block_template('single', ['single-post-hello-world', 'single-post', 'single'], '')),
    $template(resolve_block_template('page', ['page-zz', 'page'], '')),
    $template(resolve_block_template('zz', ['zz-nope'], '')),
    $template(resolve_block_template('index', ['index'], '')),
]);
global $_wp_current_template_id, $_wp_current_template_content;
$located = $try(static fn () => locate_block_template(ABSPATH . 'wp-content/themes/zz/page.php', 'page', ['page-zz.php', 'page.php']));
$say('locate_block_template', $mask([$located, $_wp_current_template_id, strlen((string) $_wp_current_template_content), md5((string) $_wp_current_template_content)]));
$_wp_current_template_id = $_wp_current_template_content = null;
$say('locate_block_template, no template there', $mask([$try(static fn () => locate_block_template('', 'zz', ['zz-nope.php'])), $_wp_current_template_id, $_wp_current_template_content]));
$_wp_current_template_id = $_wp_current_template_content = null;
foreach (['with no PHP template' => ['', 'page', ['page.php']], 'with the theme\'s own index' => [get_stylesheet_directory() . '/index.php', 'index', ['index.php']], 'with a PHP template elsewhere' => [ABSPATH . 'zz/page.php', 'page', ['page.php']], 'for a single post' => ['', 'single', ['single-post-zz.php', 'single-post.php', 'single.php']]] as $label => [$given, $type, $candidates]) {
    $found = $try(static fn () => locate_block_template($given, $type, $candidates));
    $say("locate_block_template {$label}", $mask([$found, $_wp_current_template_id, strlen((string) $_wp_current_template_content), md5((string) $_wp_current_template_content)]));
    $_wp_current_template_id = $_wp_current_template_content = null;
}

// The theme attribute in template parts.
$markup = '<!-- wp:template-part {"slug":"header"} /--><!-- wp:group --><div class="wp-block-group"><!-- wp:template-part {"slug":"footer","theme":"other"} /--></div><!-- /wp:group --><!-- wp:paragraph --><p>p</p><!-- /wp:paragraph -->';
$say('_inject_theme_attribute_in_block_template_content', $mask(_inject_theme_attribute_in_block_template_content($markup)));
$say('_remove_theme_attribute_in_block_template_content', $mask(_remove_theme_attribute_in_block_template_content(_inject_theme_attribute_in_block_template_content($markup))));

// Block supports.
$registry = WP_Block_Type_Registry::get_instance();
$skip = [];
foreach (['core/button' => ['border', 'color', 'spacing', 'typography'], 'core/image' => ['border', 'shadow'], 'core/paragraph' => ['color', 'typography'], 'core/search' => ['border', 'color', 'typography'], 'core/heading' => ['typography']] as $name => $sets) {
    foreach ($sets as $set) {
        $type = $registry->get_registered($name);
        $skip["{$name} {$set}"] = $type ? [wp_should_skip_block_supports_serialization($type, $set), wp_should_skip_block_supports_serialization($type, $set, 'radius'), wp_should_skip_block_supports_serialization($type, $set, 'text'), wp_should_skip_block_supports_serialization($type, $set, 'fontSize')] : null;
    }
}
$say('wp_should_skip_block_supports_serialization', $skip);
$say('wp_should_skip_block_supports_serialization of nothing', [wp_should_skip_block_supports_serialization(null, 'color'), wp_should_skip_block_supports_serialization($registry->get_registered('core/paragraph'), 'zz')]);
$borders = [];
foreach (['core/group', 'core/image', 'core/paragraph', 'core/button', 'core/column', 'core/table'] as $name) {
    $type = $registry->get_registered($name);
    $borders[$name] = $type ? array_map(static fn ($feature) => wp_has_border_feature_support($type, $feature), ['color', 'radius', 'style', 'width', 'zz']) : null;
}
$say('wp_has_border_feature_support', $borders);
$say('wp_has_border_feature_support with a default', [wp_has_border_feature_support($registry->get_registered('core/paragraph'), 'color', true), wp_has_border_feature_support(null, 'color', true)]);
$say('_wp_get_presets_class_name', [_wp_get_presets_class_name(['blockName' => 'core/group', 'attrs' => ['settings' => ['color' => []]]]), _wp_get_presets_class_name(['blockName' => 'core/group', 'attrs' => []]), _wp_get_presets_class_name(['blockName' => 'core/group', 'attrs' => ['settings' => ['color' => []]]])]);

// Pagination arrows.
$arrows = [];
foreach (['none', 'arrow', 'chevron', 'zz'] as $arrow) {
    $comments = [];
    foreach (['next', 'previous'] as $direction) {
        $block = new WP_Block(['blockName' => 'core/comments-pagination-' . $direction, 'attrs' => []], ['comments/paginationArrow' => $arrow]);
        $comments[] = get_comments_pagination_arrow($block, $direction);
    }
    $query = [];
    foreach ([true, false] as $isNext) {
        $block = new WP_Block(['blockName' => 'core/query-pagination-' . ($isNext ? 'next' : 'previous'), 'attrs' => []], ['paginationArrow' => $arrow]);
        $query[] = get_query_pagination_arrow($block, $isNext);
    }
    $arrows[$arrow] = [$comments, $query];
}
$say('the pagination arrows', $arrows);
$say('the pagination arrows without context', [get_comments_pagination_arrow(new WP_Block(['blockName' => 'core/comments-pagination-next', 'attrs' => []], []), 'next'), get_query_pagination_arrow(new WP_Block(['blockName' => 'core/query-pagination-next', 'attrs' => []], []), true)]);

// Names, addresses and small helpers.
$say('wp_get_block_name_from_theme_json_path', array_map('wp_get_block_name_from_theme_json_path', [['styles', 'blocks', 'core/paragraph', 'elements', 'link'], ['styles', 'blocks', 'core/group'], ['styles', 'elements', 'link'], ['settings', 'blocks', 'core/heading', 'color'], ['styles'], [], ['styles', 'blocks', 'my-plugin/thing', 'variations', 'x']]));
$say('get_block_asset_url', $mask([
    get_block_asset_url(ABSPATH . WPINC . '/blocks/paragraph/style.css'),
    get_block_asset_url(WP_PLUGIN_DIR . '/zz-plugin/build/block.css'),
    get_block_asset_url(get_template_directory() . '/assets/zz.css'),
    get_block_asset_url(get_stylesheet_directory() . '/assets/zz.css'),
    get_block_asset_url('/tmp/zz.css'),
    get_block_asset_url(''),
]));
$parsed = [['blockName' => 'core/navigation-link', 'attrs' => []], ['blockName' => null, 'attrs' => [], 'innerHTML' => "\n\n"], ['blockName' => null, 'attrs' => [], 'innerHTML' => 'text'], ['blockName' => 'core/spacer', 'attrs' => []]];
$say('block_core_navigation_filter_out_empty_blocks', array_map(static fn ($block) => $block['blockName'] ?? 'null', array_values(block_core_navigation_filter_out_empty_blocks($parsed))));
$say('wp_render_empty_block_template_warning', $try(static function () {
    $tpl = new WP_Block_Template();
    $tpl->title = 'ZZ Empty';
    $tpl->slug = 'zz-empty';
    $tpl->id = 'x//zz-empty';
    $out = [wp_render_empty_block_template_warning($tpl)];
    wp_set_current_user(1);
    $out[] = wp_render_empty_block_template_warning($tpl);
    wp_set_current_user(0);
    return $out;
}));
$say('get_classic_theme_supports_block_editor_settings', $try(static function () {
    $settings = get_classic_theme_supports_block_editor_settings();
    ksort($settings);
    return $settings;
}));
$before = count($GLOBALS['wp_filter']['wp_head']->callbacks[10] ?? []) + count($GLOBALS['wp_filter']['wp_footer']->callbacks[10] ?? []);
wp_enqueue_block_support_styles('.zz-support{color:red}');
wp_enqueue_block_support_styles('.zz-late{color:blue}', 99);
$printed = [];
foreach (['wp_head', 'wp_footer'] as $hook) {
    foreach ([10, 99] as $priority) {
        $callbacks = $GLOBALS['wp_filter'][$hook]->callbacks[$priority] ?? [];
        foreach ($callbacks as $callback) {
            if ($callback['function'] instanceof Closure) {
                ob_start();
                ($callback['function'])();
                $out = ob_get_clean();
                if (str_contains($out, 'zz-')) {
                    $printed[] = [$hook, $priority, $out];
                }
            }
        }
    }
}
$say('wp_enqueue_block_support_styles', $printed);

$say('deprecated, as reported', $deprecated);
restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
