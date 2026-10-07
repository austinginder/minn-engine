<?php
/**
 * Behaviour probe for the block API: registration (arguments and block.json),
 * the registry, parsing and serializing, rendering through a plugin's
 * callback, wrapper attributes, patterns, styles. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$kind = static fn ($r) => $r instanceof WP_Error ? 'error:' . $r->get_error_code() : (is_object($r) ? get_class($r) : var_export($r, true));
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace($home, '{home}', $v) : $v;

// Parsing and serializing.
$markup = "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"#123456\"}}} -->\n<p class=\"has-text-align-center\">Hi <strong>there</strong></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\"><!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Title</h3>\n<!-- /wp:heading -->\n\n<!-- wp:minn-probe/marker {\"word\":\"x\"} /--></div>\n<!-- /wp:group -->\n\nfree text\n<!-- wp:core/separator /-->";
$parsed = parse_blocks($markup);
$say('parse_blocks count', count($parsed));
$say('parse_blocks shapes', array_map(static fn ($b) => [$b['blockName'], $b['attrs'], count($b['innerBlocks']), $b['innerHTML'], $b['innerContent']], $parsed));
$say('parse_blocks nested', array_map(static fn ($b) => [$b['blockName'], $b['attrs'], $b['innerHTML']], $parsed[2]['innerBlocks']));
$say('parse_blocks empty', parse_blocks(''));
$say('parse_blocks plain', parse_blocks('just text'));
$say('serialize round trip', serialize_blocks($parsed) === $markup);
$say('serialize_block', serialize_block(['blockName' => 'core/paragraph', 'attrs' => ['align' => 'left', 'q' => 'a"b', 'u' => 'é/x'], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]));
$say('serialize_block no attrs', serialize_block(['blockName' => 'core/separator', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$say('serialize_block freeform', serialize_block(['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'free', 'innerContent' => ['free']]));
$say('serialize_block_attributes', [serialize_block_attributes(['a' => 'x--y', 'b' => '<>&"\'', 'c' => ['d' => 1]]), serialize_block_attributes([])]);
$say('get_comment_delimited_block_content', [get_comment_delimited_block_content('core/paragraph', ['a' => 1], '<p>x</p>'), get_comment_delimited_block_content('minn-probe/thing', [], ''), get_comment_delimited_block_content(null, [], 'raw')]);
$say('strip_core_block_namespace', [strip_core_block_namespace('core/paragraph'), strip_core_block_namespace('minn-probe/x'), strip_core_block_namespace(null)]);
$say('has_blocks', [has_blocks($markup), has_blocks('<p>no</p>'), has_blocks(1), has_blocks(7), has_blocks(999999)]);
$say('has_block', [has_block('core/paragraph', $markup), has_block('paragraph', $markup), has_block('core/heading', $markup), has_block('minn-probe/marker', $markup), has_block('core/nope', $markup), has_block('core/paragraph', 1), has_block('core/paragraph', 999999)]);
$say('block_version', [block_version($markup), block_version('<p>x</p>')]);
$say('excerpt_remove_blocks', excerpt_remove_blocks("<!-- wp:paragraph -->\n<p>keep</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>drop</code></pre>\n<!-- /wp:code -->\n\n<!-- wp:minn-probe/marker /-->"));

// Registration.
$registry = WP_Block_Type_Registry::get_instance();
$say('registry class', [get_class($registry), $registry === WP_Block_Type_Registry::get_instance()]);
$say('core registered', [$registry->is_registered('core/paragraph'), $registry->is_registered('core/latest-posts'), $registry->is_registered('core/nope'), count($registry->get_all_registered()) > 50]);
$say('core paragraph type', (static function () use ($registry) { $t = $registry->get_registered('core/paragraph'); return [get_class($t), $t->name, $t->title, $t->category, $t->api_version, $t->is_dynamic(), array_keys($t->attributes), $t->supports['align'] ?? null, $t->supports['anchor'] ?? null, $t->supports['typography']['fontSize'] ?? null, $t->render_callback, $t->textdomain, $t->style_handles, $t->editor_script_handles, $t->uses_context, $t->provides_context]; })());
$say('core latest-posts dynamic', (static function () use ($registry) { $t = $registry->get_registered('core/latest-posts'); return [$t->is_dynamic(), is_callable($t->render_callback), $t->attributes['postsToShow'] ?? null, $t->uses_context]; })());
$say('core paragraph attributes', $registry->get_registered('core/paragraph')->attributes);
$say('core paragraph supports', $registry->get_registered('core/paragraph')->supports);
$say('core group supports keys', array_keys($registry->get_registered('core/group')->supports));
$callback = static function ($attributes, $content, $block) {
    return '<div ' . get_block_wrapper_attributes(['class' => 'extra', 'data-word' => $attributes['word']]) . '>' . $attributes['word'] . '|' . $attributes['count'] . '|' . (is_array($attributes['list']) ? implode(',', $attributes['list']) : '') . '|' . $content . '|' . get_class($block) . '|' . $block->name . '|' . json_encode($block->context) . '|' . count($block->inner_blocks) . '</div>';
};
$type = register_block_type('minn-probe/marker', [
    'api_version' => 3,
    'title' => 'Marker',
    'category' => 'widgets',
    'attributes' => ['word' => ['type' => 'string', 'default' => 'dflt'], 'count' => ['type' => 'number', 'default' => 2], 'list' => ['type' => 'array', 'default' => ['a']], 'flag' => ['type' => 'boolean']],
    'supports' => ['align' => true, 'color' => ['text' => true, 'background' => true], 'anchor' => true, 'html' => false, 'className' => true, 'customClassName' => true],
    'uses_context' => ['postId', 'postType'],
    'provides_context' => ['minn-probe/word' => 'word'],
    'render_callback' => $callback,
    'editor_script' => 'minn-probe-editor',
    'style' => 'minn-probe-style',
    'keywords' => ['a', 'b'],
    'description' => 'A probe.',
    'textdomain' => 'minn-probe',
]);
$say('register_block_type', [get_class($type), $type->name, $type->title, $type->api_version, $type->category, $type->is_dynamic(), $type->attributes, $type->supports, $type->uses_context, $type->provides_context, $type->editor_script_handles, $type->script_handles, $type->view_script_handles, $type->editor_style_handles, $type->style_handles, $type->view_style_handles, $type->keywords, $type->description, $type->textdomain, $type->parent, $type->ancestor, $type->icon, $type->styles, $type->variations, $type->example, $type->block_hooks, $type->editor_script, $type->style, $type->script, $type->editor_style, $type->view_script, $type->view_style]);
$say('registry after', [$registry->is_registered('minn-probe/marker'), $registry->get_registered('minn-probe/marker') === $type, in_array('minn-probe/marker', get_dynamic_block_names(), true), in_array('core/latest-posts', get_dynamic_block_names(), true), in_array('core/paragraph', get_dynamic_block_names(), true)]);
$say('register twice', [$kind(register_block_type('minn-probe/marker', ['title' => 'Again'])), $registry->get_registered('minn-probe/marker')->title]);
$say('register bad names', [$kind(register_block_type('NoSlash', [])), $kind(register_block_type('Upper/Case', [])), $kind(register_block_type('ok/name-1', [])), $kind(register_block_type('ok/name-1', ['title' => 'x']))]);
unregister_block_type('ok/name-1');
$say('register type object', (static function () use ($kind, $registry) { $t = new WP_Block_Type('minn-probe/object', ['title' => 'Obj']); $r = register_block_type($t); return [$kind($r), $r === $t, $registry->is_registered('minn-probe/object')]; })());
$say('unregister', [$kind(unregister_block_type('minn-probe/object')), $registry->is_registered('minn-probe/object'), $kind(unregister_block_type('minn-probe/nope'))]);
$say('block_has_support', [block_has_support($type, 'align'), block_has_support($type, ['color', 'text']), block_has_support($type, ['color', 'link']), block_has_support($type, 'html'), block_has_support($type, 'nope', 'd'), block_has_support('minn-probe/marker', 'anchor'), block_has_support($registry->get_registered('core/paragraph'), ['typography', 'fontSize'])]);
$say('prepare_attributes_for_render', [$type->prepare_attributes_for_render(['word' => 'w', 'extra' => 1, 'count' => '5', 'flag' => 'yes']), $type->prepare_attributes_for_render([])]);
$say('wp_get_block_default_classname', [wp_get_block_default_classname('core/paragraph'), wp_get_block_default_classname('minn-probe/marker'), wp_get_block_default_classname('core/nope')]);
$say('wp_get_block_css_selector', [wp_get_block_css_selector($type), wp_get_block_css_selector($registry->get_registered('core/paragraph')), wp_get_block_css_selector($registry->get_registered('core/paragraph'), 'typography'), wp_get_block_css_selector($type, ['color', 'text'], true)]);

// Rendering.
$say('render_block dynamic', render_block(['blockName' => 'minn-probe/marker', 'attrs' => ['word' => 'hi', 'align' => 'wide', 'className' => 'custom', 'anchor' => 'anch', 'style' => ['color' => ['text' => '#123456', 'background' => '#abcdef']], 'textColor' => 'vivid-red', 'backgroundColor' => 'pale-cyan'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$say('render_block defaults', render_block(['blockName' => 'minn-probe/marker', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$say('render_block with inner', render_block(['blockName' => 'minn-probe/marker', 'attrs' => ['word' => 'p'], 'innerBlocks' => [['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>inner</p>', 'innerContent' => ['<p>inner</p>']]], 'innerHTML' => '<div>|</div>', 'innerContent' => ['<div>', null, '</div>']]));
$say('render_block context', (static function () { $GLOBALS['post'] = get_post(1); setup_postdata($GLOBALS['post']); $out = render_block(['blockName' => 'minn-probe/marker', 'attrs' => ['word' => 'ctx'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]); wp_reset_postdata(); return $out; })());
$say('render_block unknown', render_block(['blockName' => 'minn-probe/unknown', 'attrs' => ['a' => 1], 'innerBlocks' => [], 'innerHTML' => '<p>static kept</p>', 'innerContent' => ['<p>static kept</p>']]));
$say('render_block freeform', render_block(['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'raw text', 'innerContent' => ['raw text']]));
$say('render_block core static', render_block(['blockName' => 'core/paragraph', 'attrs' => ['align' => 'center'], 'innerBlocks' => [], 'innerHTML' => "\n<p class=\"has-text-align-center\">Hi</p>\n", 'innerContent' => ["\n<p class=\"has-text-align-center\">Hi</p>\n"]]));
$say('do_blocks', do_blocks("<!-- wp:paragraph -->\n<p>A</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:minn-probe/marker {\"word\":\"d\"} /-->\n\n<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:minn-probe/marker {\"word\":\"in\"} /--></div>\n<!-- /wp:group -->"));
$say('do_blocks filters', (static function () { add_filter('render_block', static fn ($c, $b) => $b['blockName'] === 'minn-probe/marker' ? '[' . $c . ']' : $c, 10, 2); add_filter('render_block_minn-probe/marker', static fn ($c) => 'M' . $c, 10, 1); add_filter('pre_render_block', static fn ($pre, $b) => $b['blockName'] === 'core/separator' ? 'PRE' : $pre, 10, 2); add_filter('render_block_data', static function ($b) { if ($b['blockName'] === 'minn-probe/marker') { $b['attrs']['word'] = 'filtered'; } return $b; }); $out = do_blocks("<!-- wp:minn-probe/marker /--><!-- wp:separator /-->"); remove_all_filters('render_block'); remove_all_filters('render_block_minn-probe/marker'); remove_all_filters('pre_render_block'); remove_all_filters('render_block_data'); return $out; })());
$say('WP_Block object', (static function () { $b = new WP_Block(['blockName' => 'minn-probe/marker', 'attrs' => ['word' => 'o'], 'innerBlocks' => [['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>i</p>', 'innerContent' => ['<p>i</p>']]], 'innerHTML' => '', 'innerContent' => [null]], ['postId' => 5, 'postType' => 'post', 'unused' => 1]); return [$b->name, $b->attributes, $b->context, $b->available_context, get_class($b->block_type), get_class($b->inner_blocks), count($b->inner_blocks), $b->inner_blocks[0]->name, $b->inner_blocks[0]->context, $b->inner_html, $b->inner_content, isset($b->attributes), isset($b->nope), $b->render(), $b->render(['dynamic' => false])]; })());
$say('wrapper attributes outside render', get_block_wrapper_attributes(['class' => 'x']));
$say('wrapper attributes extra', (static function () { add_filter('render_block_data', static fn ($b) => $b); $out = render_block(['blockName' => 'minn-probe/marker', 'attrs' => ['word' => 'w', 'style' => ['spacing' => ['padding' => ['top' => '1em']]]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]); remove_all_filters('render_block_data'); return $out; })());
$say('block.json', (static function () use ($kind, $registry, $rel) {
    $dir = get_temp_dir() . 'minn-probe-block';
    wp_mkdir_p($dir . '/build');
    file_put_contents($dir . '/block.json', json_encode(['$schema' => 'https://schemas.wp.org/trunk/block.json', 'apiVersion' => 3, 'name' => 'minn-probe/json', 'version' => '1.2.3', 'title' => 'From JSON', 'category' => 'text', 'icon' => 'smiley', 'description' => 'Json block.', 'keywords' => ['k'], 'textdomain' => 'minn-probe', 'attributes' => ['n' => ['type' => 'number', 'default' => 4]], 'supports' => ['html' => false, 'align' => ['wide']], 'usesContext' => ['postId'], 'providesContext' => ['minn-probe/n' => 'n'], 'editorScript' => 'file:./build/index.js', 'editorStyle' => 'file:./build/index.css', 'style' => 'file:./build/style-index.css', 'viewScript' => ['file:./build/view.js', 'minn-probe-shared'], 'script' => 'minn-probe-script', 'render' => 'file:./render.php', 'example' => ['attributes' => ['n' => 1]], 'styles' => [['name' => 'default', 'label' => 'Default', 'isDefault' => true], ['name' => 'alt', 'label' => 'Alt']], 'variations' => [['name' => 'v1', 'title' => 'V1']], 'blockHooks' => ['core/paragraph' => 'after'], 'parent' => ['core/group'], 'ancestor' => ['core/columns'], 'allowedBlocks' => ['core/paragraph']]));
    file_put_contents($dir . '/build/index.js', '/* js */');
    file_put_contents($dir . '/build/index.css', '/* css */');
    file_put_contents($dir . '/build/style-index.css', '/* style */');
    file_put_contents($dir . '/build/view.js', '/* view */');
    file_put_contents($dir . '/build/index.asset.php', '<?php return ["dependencies" => ["wp-blocks"], "version" => "abc"];');
    file_put_contents($dir . '/render.php', '<?php echo "R:" . $attributes["n"] . ":" . $content . ":" . get_class($block) . ":" . $block->name;');
    $t = register_block_type_from_metadata($dir, ['render_callback' => null]);
    $out = [$kind($t)];
    if ($t instanceof WP_Block_Type) {
        $out[] = [$t->name, $t->title, $t->api_version, $t->category, $t->icon, $t->description, $t->keywords, $t->textdomain, $t->attributes, $t->supports, $t->uses_context, $t->provides_context, $t->editor_script_handles, $t->editor_style_handles, $t->style_handles, $t->view_script_handles, $t->script_handles, $t->view_style_handles, $t->is_dynamic(), is_callable($t->render_callback), $t->example, $t->styles, $t->variations, $t->block_hooks, $t->parent, $t->ancestor, $t->allowed_blocks, $t->version ?? null];
        $out[] = render_block(['blockName' => 'minn-probe/json', 'attrs' => ['n' => 9], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
        $scripts = wp_scripts();
        $out[] = [$rel($scripts->registered['minn-probe-json-editor-script']->src ?? null), $scripts->registered['minn-probe-json-editor-script']->deps ?? null, $scripts->registered['minn-probe-json-editor-script']->ver ?? null, $rel($scripts->registered['minn-probe-json-view-script']->src ?? null), $rel(wp_styles()->registered['minn-probe-json-style']->src ?? null), $rel(wp_styles()->registered['minn-probe-json-editor-style']->src ?? null), wp_styles()->registered['minn-probe-json-style']->ver ?? null];
        $out[] = [register_block_type_from_metadata($dir . '/block.json') instanceof WP_Block_Type, $kind(register_block_type_from_metadata('/nope/nope')), $kind(register_block_type($dir))];
        unregister_block_type('minn-probe/json');
    }
    foreach (glob($dir . '/build/*') as $f) { unlink($f); }
    rmdir($dir . '/build');
    unlink($dir . '/block.json');
    unlink($dir . '/render.php');
    rmdir($dir);
    return $out;
})());
$say('register_block_style', (static function () use ($kind) { $r = register_block_style('core/paragraph', ['name' => 'minn-probe-style', 'label' => 'Probe', 'inline_style' => '.is-style-minn-probe-style{color:red}']); $reg = WP_Block_Styles_Registry::get_instance(); $out = [$r, $reg->is_registered('core/paragraph', 'minn-probe-style'), $reg->get_registered_styles_for_block('core/paragraph')['minn-probe-style'] ?? null, $kind(register_block_style('core/paragraph', ['label' => 'no name'])), $kind(register_block_style('core/paragraph', ['name' => 'bad name', 'label' => 'x'])), unregister_block_style('core/paragraph', 'minn-probe-style'), unregister_block_style('core/paragraph', 'minn-probe-style')]; return $out; })());
$say('register_block_pattern', (static function () use ($kind) { $r = register_block_pattern('minn-probe/pattern', ['title' => 'Probe pattern', 'content' => '<!-- wp:paragraph --><p>P</p><!-- /wp:paragraph -->', 'categories' => ['text'], 'keywords' => ['k'], 'description' => 'd', 'viewportWidth' => 800, 'blockTypes' => ['core/paragraph'], 'inserter' => false]); $reg = WP_Block_Patterns_Registry::get_instance(); $out = [$r, $reg->is_registered('minn-probe/pattern'), $reg->get_registered('minn-probe/pattern'), $kind(register_block_pattern('no-slash', ['title' => 'x', 'content' => 'y'])), $kind(register_block_pattern('minn-probe/notitle', ['content' => 'y'])), $kind(register_block_pattern('minn-probe/nocontent', ['title' => 'x'])), unregister_block_pattern('minn-probe/pattern'), unregister_block_pattern('minn-probe/pattern'), register_block_pattern_category('minn-probe', ['label' => 'Probe cat', 'description' => 'd']), WP_Block_Pattern_Categories_Registry::get_instance()->get_registered('minn-probe'), unregister_block_pattern_category('minn-probe')]; return $out; })());
$say('wp_interactivity_data_wp_context', [wp_interactivity_data_wp_context(['a' => 1, 'b' => 'x"y']), wp_interactivity_data_wp_context(['a' => 1], 'ns'), wp_interactivity_data_wp_context([])]);
$say('wp_interactivity_state', [wp_interactivity_state('minn-probe', ['k' => 'v']), wp_interactivity_state('minn-probe'), wp_interactivity_state('minn-probe', ['k2' => 2]), wp_interactivity_config('minn-probe', ['c' => 1]), wp_interactivity_config('minn-probe')]);
$say('wp_get_global_styles_svg_filters', gettype(wp_get_global_styles_svg_filters()));
$say('filter_block_kses', filter_block_kses(['blockName' => 'core/paragraph', 'attrs' => ['a' => '<script>x</script>', 'b' => ['c' => 'ok']], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']], ['p' => []]));
$say('get_hooked_blocks', get_hooked_blocks());
unregister_block_type('minn-probe/marker');
$say('registry cleaned', $registry->is_registered('minn-probe/marker'));

// Rewrite rules and the small leftovers.
$say('rewrite', (static function () use ($rel) { add_rewrite_rule('^probe/([^/]*)/?', 'index.php?probe=$matches[1]', 'top'); add_rewrite_tag('%probe%', '([^&]+)'); add_rewrite_endpoint('probe-ep', EP_PERMALINK); $rules = $GLOBALS['wp_rewrite']->extra_rules_top ?? null; return [$rules, in_array('probe', $GLOBALS['wp']->public_query_vars, true), $GLOBALS['wp_rewrite']->endpoints[0] ?? null, get_class($GLOBALS['wp_rewrite']), $GLOBALS['wp_rewrite']->permalink_structure, $GLOBALS['wp_rewrite']->using_permalinks(), $GLOBALS['wp_rewrite']->using_index_permalinks(), $GLOBALS['wp_rewrite']->get_page_permastruct(), $GLOBALS['wp_rewrite']->get_category_permastruct(), $GLOBALS['wp_rewrite']->get_tag_permastruct(), $GLOBALS['wp_rewrite']->get_author_permastruct(), $GLOBALS['wp_rewrite']->get_date_permastruct(), $GLOBALS['wp_rewrite']->get_search_permastruct(), $GLOBALS['wp_rewrite']->front, $GLOBALS['wp_rewrite']->root, $GLOBALS['wp_rewrite']->index, $GLOBALS['wp_rewrite']->pagination_base, $GLOBALS['wp_rewrite']->feed_base, $GLOBALS['wp_rewrite']->search_base, $GLOBALS['wp_rewrite']->author_base, $rel($GLOBALS['wp_rewrite']->get_extra_permastruct('category')), get_class($GLOBALS['wp'])]; })());
// The stored rules go back as they were, so the probe's own rule and endpoint leave nothing behind.
$say('flush_rewrite_rules', (static function () {
    $stored = get_option('rewrite_rules');
    $flushed = flush_rewrite_rules();
    update_option('rewrite_rules', $stored);
    return $flushed;
})());
$say('wp_filesize', [wp_filesize(get_attached_file(607)) > 0, wp_filesize('/nope')]);
$say('edit_post_link anon', (static function () { ob_start(); edit_post_link('Edit', '<b>', '</b>', 1); return ob_get_clean(); })());
$say('get_num_queries', is_int(get_num_queries()));
$say('wp_json_file_decode', (static function () { $f = get_temp_dir() . 'minn-probe.json'; file_put_contents($f, '{"a":{"b":1}}'); $r = [wp_json_file_decode($f), wp_json_file_decode($f, ['associative' => true]), wp_json_file_decode('/nope.json')]; file_put_contents($f, '{bad'); $r[] = wp_json_file_decode($f); unlink($f); return $r; })());
$say('links_add_base_url', [links_add_base_url('<a href="x">y</a><img src="/i.png"><a href="http://z/">z</a>', 'http://base.test/dir/'), links_add_base_url('<a data-href="x">y</a>', 'http://base.test/', ['data-href'])]);
$say('get_comment_type', [get_comment_type(get_comments(['post_id' => 1, 'number' => 1])[0]->comment_ID ?? 0), get_comment_type(999999)]);
$say('post formats', [get_post_format(1), get_post_format(999999), get_post_format_strings(), get_post_format_slugs()]);
$say('set_post_format', (static function () { $id = wp_insert_post(['post_title' => 'Probe format', 'post_content' => 'x', 'post_status' => 'draft']); $r = [set_post_format($id, 'aside'), get_post_format($id), set_post_format($id, 'nope'), set_post_format($id, ''), get_post_format($id), set_post_format(999999, 'aside')]; wp_delete_post($id, true); return $r; })());
$say('users', (static function () use ($kind) { $id = wp_insert_user(['user_login' => 'probeuser', 'user_pass' => 'pw', 'user_email' => 'probeuser@example.test', 'role' => 'subscriber', 'first_name' => 'Pro', 'display_name' => 'Probe User']); $u = get_userdata($id); $r = [is_int($id) && $id > 0, $u->user_login, $u->user_nicename, $u->display_name, $u->roles, $u->first_name, get_user_meta($id, 'nickname', true), $kind(wp_insert_user(['user_login' => 'probeuser', 'user_pass' => 'x', 'user_email' => 'other@example.test'])), $kind(wp_insert_user(['user_login' => 'other', 'user_pass' => 'x', 'user_email' => 'probeuser@example.test'])), $kind(wp_insert_user(['user_login' => '', 'user_pass' => 'x'])), is_int(wp_create_user('probeuser2', 'pw'))]; $upd = wp_update_user(['ID' => $id, 'display_name' => 'Renamed', 'role' => 'editor', 'user_email' => 'probeuser-new@example.test']); $u = get_userdata($id); $r[] = [$upd === $id, $u->display_name, $u->roles, $u->user_email, $kind(wp_update_user(['ID' => 999999, 'display_name' => 'x']))]; $r[] = [wp_delete_user($id), get_userdata($id), wp_delete_user(999999)]; foreach (['probeuser2', 'probeuser3'] as $login) { $x = get_user_by('login', $login); if ($x) { wp_delete_user($x->ID); } } return $r; })());
$say('get_preview_post_link', [preg_replace('/preview_nonce=[a-f0-9]+/', 'preview_nonce=N', $rel(get_preview_post_link(1))), $rel(get_preview_post_link(3)), get_preview_post_link(999999)]);
$say('wp_delete_object_term_relationships', (static function () { $id = wp_insert_post(['post_title' => 'Probe rel', 'post_content' => 'x', 'post_status' => 'publish', 'tags_input' => ['engine']]); $before = wp_get_object_terms($id, 'post_tag', ['fields' => 'ids']); wp_delete_object_term_relationships($id, ['post_tag']); $after = wp_get_object_terms($id, 'post_tag', ['fields' => 'ids']); wp_delete_post($id, true); return [$before, $after]; })());
$say('custom css', [wp_get_custom_css(), wp_get_custom_css('nope'), (static function () { $r = wp_update_custom_css_post('body{color:red}'); $out = [get_class($r), $r->post_type, $r->post_status, $r->post_content, wp_get_custom_css()]; wp_delete_post($r->ID, true); remove_theme_mod('custom_css_post_id'); return $out; })()]);
$say('post lock', [wp_check_post_lock(1), (static function () { $r = wp_set_post_lock(1); $out = [is_array($r) && count($r) === 2, wp_check_post_lock(1)]; delete_post_meta(1, '_edit_lock'); return $out; })(), wp_set_post_lock(999999)]);
$say('wp_get_pomo_file_data', (static function () { $f = get_temp_dir() . 'minn-probe.po'; file_put_contents($f, "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Probe 1.0\\n\"\n\"PO-Revision-Date: 2020-01-02 03:04+0000\\n\"\n\"X-Generator: probe\\n\"\n"); $r = wp_get_pomo_file_data($f); unlink($f); return $r; })());
$say('wp_post_revision_title', (static function () { $revs = wp_get_post_revisions(5); $rev = $revs === [] ? null : reset($revs); return [$rev ? preg_replace('/\d+/', 'N', wp_post_revision_title($rev, false)) : 'no revisions', wp_post_revision_title(1, false)]; })());
$say('wp_sprintf_l', [wp_sprintf_l('%l', ['a', 'b', 'c']), wp_sprintf_l('%l', ['a']), wp_sprintf_l('%l', ['a', 'b']), wp_sprintf('%l', ['x', 'y'])]);
$say('oembed', (static function () { $r = [wp_oembed_add_provider('#https?://probe\.test/.*#i', 'https://probe.test/oembed', true), wp_embed_register_handler('minn-probe', '#https?://probe-handler\.test/(\d+)#i', static fn ($m) => '<b>H' . $m[1] . '</b>'), wp_oembed_get('https://probe-handler.test/5'), wp_oembed_remove_provider('#https?://probe\.test/.*#i'), wp_embed_unregister_handler('minn-probe')]; return $r; })());
$say('fetch_feed', (static function () use ($kind) { $f = fetch_feed('http://nonexistent.invalid/feed'); return [$kind($f), $f instanceof WP_Error ? $f->get_error_code() : null]; })());

$say('wp_theme_get_element_class_name', [wp_theme_get_element_class_name('button'), wp_theme_get_element_class_name('caption'), wp_theme_get_element_class_name('heading'), wp_theme_get_element_class_name(''), wp_theme_get_element_class_name('nope')]);
// The merged styles node plugin code reads to decide whether the theme
// styles an element at all (WooCommerce gates a body class on it).
// Key ORDER carries no contract (callers ask array_key_exists; the CSS
// order is the styles suite's business), so the sets are what is pinned.
$sorted = static function (mixed $value): array {
    $keys = array_keys((array) $value);
    sort($keys);
    return $keys;
};
$say('wp_get_global_styles keys', [
    $sorted(wp_get_global_styles()),
    $sorted(wp_get_global_styles(['elements'])),
    $sorted(wp_get_global_styles(['elements', 'button'])),
    // The values, by name: the map's own key order is a merge artifact,
    // and CSS emission order is the styles suite's business.
    array_map(static fn (string $k) => wp_get_global_styles(['elements', 'button', 'color'])[$k] ?? null, ['text', 'background']),
    wp_get_global_styles(['nope', 'deep']) === wp_get_global_styles(),
]);

// Context cascade: rendering a WP_Block's inner blocks passes each one's
// context through render_block_context at every nesting level (how a query
// loop hands postId down), and the filtered context reaches a dynamic
// child's $block->context and a core block like post-title.
register_block_type('minn-probe/ctx-child', [
    'uses_context' => ['postId', 'postType'],
    'render_callback' => static fn ($attrs, $content, $block) => '[child ' . json_encode($block->context) . ']',
]);
register_block_type('minn-probe/ctx-loop', [
    'render_callback' => static function ($attrs, $content, $block) {
        $out = '';
        foreach ([1, 5] as $id) {
            $GLOBALS['post'] = get_post($id);
            setup_postdata($GLOBALS['post']);
            $inject = static fn ($context) => array_merge($context, ['postId' => $id, 'postType' => 'post']);
            add_filter('render_block_context', $inject, 1);
            $instance = $block->parsed_block;
            $instance['blockName'] = 'core/null';
            $out .= (new WP_Block($instance, []))->render(['dynamic' => false]);
            remove_filter('render_block_context', $inject, 1);
        }
        wp_reset_postdata();
        return $out;
    },
]);
$ctxTrace = [];
$ctxTraceFilter = static function ($context, $parsed) use (&$ctxTrace) {
    $ctxTrace[] = [$parsed['blockName'] ?? '-', array_intersect_key((array) $context, ['postId' => 1, 'postType' => 1])];
    return $context;
};
add_filter('render_block_context', $ctxTraceFilter, 20, 2);
$say('context cascade child', do_blocks('<!-- wp:minn-probe/ctx-loop --><!-- wp:minn-probe/ctx-child /--><!-- /wp:minn-probe/ctx-loop -->'));
$say('context cascade post-title', do_blocks('<!-- wp:minn-probe/ctx-loop --><!-- wp:post-title /--><!-- /wp:minn-probe/ctx-loop -->'));
remove_filter('render_block_context', $ctxTraceFilter, 20);
$say('context cascade trace', $ctxTrace);
WP_Block_Type_Registry::get_instance()->unregister('minn-probe/ctx-child');
WP_Block_Type_Registry::get_instance()->unregister('minn-probe/ctx-loop');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
