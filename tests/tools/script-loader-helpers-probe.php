<?php
/**
 * Script loader helpers (probe script-loader-helpers): the dataset name
 * converters, relative links in a stylesheet made absolute, block nodes
 * filtered out, the polyfill loader, prototype before jQuery, the printers
 * for concatenated scripts and styles, the scripts localized just in
 * time (with the shortcodes they are told, [embed] among them), localized
 * data kept as the script's data string, the style engine's stored rules
 * enqueued, block style variations' assets, every registered block's
 * assets, and the development scripts. Same protocol as api-probe.php; it
 * writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => json_decode(str_replace([$host, (string) ($GLOBALS['wp_version'] ?? '')], ['{host}', '{version}'], (string) wp_json_encode($value)), true);
$printed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$with = static function (string $hook, $callback, Closure $call) {
    add_filter($hook, $callback, 10, 9);
    try {
        return $call();
    } finally {
        remove_filter($hook, $callback, 10);
    }
};

$say('dataset names', [
    array_map(static fn ($name) => wp_js_dataset_name($name), ['data-foo', 'data-foo-bar', 'data-wp-on--click', 'data-', 'foo', 'data-Foo-Bar', 'DATA-FOO', 'data-foo-1', 'data--foo', 'data-foo-', 'data-foo_bar', 'data-a-b-c', 'data-x-Y', 'data-1a', 'data-a--b', 'Data-foo']),
    array_map(static fn ($name) => wp_html_custom_data_attribute_name($name), ['foo', 'fooBar', 'wpOn-Click', '', 'FooBar', 'foo-bar', 'foo bar', 'a1B', '-foo', 'foo=', 'x"y', 'fooBAR', 'foo-Bar', '1a', 'foo/bar', 'foo>']),
    array_map(static fn ($name) => wp_html_custom_data_attribute_name($name), ["a'b", 'a<b', "a\0b", "a\tb", "a\nb", "a\fb", "a\rb", 'a&b', 'a:b', 'a.b', 'aéb', 'a`b', 'aÉb', 'a_B', 'a\\b', 'a-', 'Ab']),
    array_map(static fn ($name) => wp_js_dataset_name($name), ['data-a b', 'data-a=b', 'data-a/b', 'data-a>b', "data-a'b", 'data-aéb', 'data-a-é', 'data-a-_', 'data-A', 'data-a-B-c', 'xdata-a', ' data-a', 'data-a-b-', 'data']),
]);

$css = "a{background:url(img/a.png)} b{background:url('../b.png')} c{background:url(\"/abs/c.png\")} d{background:url(https://zz.example/d.png)} e{background:url(data:image/png;base64,AAA)} f{background:url(#frag)} g{background:url( 'sp.png' )} h{background:url(//cdn.zz/x.png)} i{background:URL(up.png)} j{src:url(fonts/a.woff2) format('woff2')} k{background:url(./dot.png)} l{background:url(\"q.png?x=1#y\")} m{background:url(about:blank)} n{background:url(ftp://x/y.png)} o{background:url(HTTPS://x/y.png)} p{background:url()} q{background:url('')} r{background:url(.hidden.png)} s{background:url(../../x.png)} t{background:url( img/u.png )} u{background:url(%2Fenc.png)} v{background:url(mailto:a@b)} w{background:url(  \"w.png\"  )} x{background: url(\n'nl.png')} y{background:url(Img/Y.PNG)}";
$say('relative links made absolute', $mask([
    _wp_normalize_relative_css_links($css, 'https://zz.example/wp-content/themes/t/style.css'),
    _wp_normalize_relative_css_links($css, '/wp-content/themes/t/css/style.css'),
    _wp_normalize_relative_css_links($css, home_url('/wp-content/plugins/p/a.css?ver=1')),
    _wp_normalize_relative_css_links($css, 'style.css'),
    _wp_normalize_relative_css_links('a{color:red}', 'https://zz.example/x/style.css'),
    _wp_normalize_relative_css_links('a{background:url(i.png)}', 'https://zz.example'),
    _wp_normalize_relative_css_links('a{background:url(i.png)}', 'https://zz.example/'),
    _wp_normalize_relative_css_links('a{background:url(i.png)}', 'https://zz.example/dir/'),
    _wp_normalize_relative_css_links('a{background:url(i.png)}', ''),
    _wp_normalize_relative_css_links('a{background:url(i.png)} b{background:url(i.png)}', '/x/y/z.css'),
    array_map(static fn ($url) => _wp_normalize_relative_css_links('a{background:url(i.png)}', $url), ['//cdn.zz/x/y.css', 'HTTPS://zz.example/a/b.css', 'ftp://zz.example/a/b.css', 'https://zz.example/a?x=/y/z', 'https://user@zz.example:8080/a/b.css', 'https:///a/b.css', 'https://zz.example/a/b/', 'a/b/c.css', '../a/b.css', '/']),
    array_map(static fn ($css) => _wp_normalize_relative_css_links($css, '/base/s.css'), ['url()', 'url() url(x.png)', "url('a)b.png')", 'url(a)b.png)', "url( 'x.png')", "url('x.png\")", 'url(x.png', 'url("")', 'url( )', 'url(  )', "url('  x.png  ')", 'url(x.png)url(y.png)', 'url(http:x.png)', 'url(https:/x.png)', "url(' /x.png')", "url('#x')", "url(\t x.png\t)", 'url(./x.png) url(x.png)', 'url(a.png) url(b/a.png)', 'url(()', 'url(httpfoo.png)', 'url(datafoo.png)', 'url(Data:x)', 'url(././x.png)', 'url(./)', 'url(..)']),
]));

$nodes = [
    ['path' => ['styles'], 'selector' => 'body'],
    ['path' => ['styles', 'blocks', 'core/paragraph'], 'selector' => 'p'],
    ['path' => ['styles', 'elements', 'link'], 'selector' => 'a'],
    ['path' => ['styles', 'blocks', 'core/group', 'elements', 'link'], 'selector' => '.wp-block-group a'],
    ['path' => ['settings', 'blocks', 'core/paragraph'], 'selector' => 'p'],
    ['path' => ['styles', 'blocks'], 'selector' => 'x'],
    ['path' => ['blocks', 'core/x'], 'selector' => 'y'],
    ['path' => ['styles', 'elements', 'blocks'], 'selector' => 'z'],
    ['path' => ['a', 'b', 'blocks'], 'selector' => 'w'],
    ['path' => ['styles', 'Blocks'], 'selector' => 'v'],
    ['path' => [], 'selector' => 'u'],
    ['path' => ['styles', 'variations', 'blocks'], 'selector' => 't'],
];
$say('block nodes filtered out', [wp_filter_out_block_nodes($nodes), wp_filter_out_block_nodes([])]);

$scripts = wp_scripts();
wp_register_script('zz-poly', 'https://zz.example/poly.js', [], '1.2');
wp_register_script('zz-poly-rel', '/zz/poly-rel.js', [], false);
wp_register_script('zz-poly-null', 'https://zz.example/poly-null.js', [], null);
wp_register_script('zz-poly-empty', 'https://zz.example/poly-empty.js', [], '');
wp_register_script('zz-poly-amp', 'https://zz.example/p.js?a=1&b="2"', [], 'v<1>');
wp_register_script('zz-poly-bare', 'bare/poly.js', [], '2');
wp_register_script('zz-poly-nosrc', false, [], '2');
wp_register_script('zz-poly-proto', '//cdn.zz/p.js', [], '3');
wp_register_script('zz-poly-upper', 'HTTPS://zz.example/up.js', [], '3');
wp_register_script('zz-poly-httpx', 'httpx.js', [], '3');
$say('the polyfill loader', $mask([
    wp_get_script_polyfill($scripts, ['"fetch" in window' => 'zz-poly']),
    wp_get_script_polyfill($scripts, ['"fetch" in window' => 'zz-poly', 'window.Promise' => 'zz-poly-rel', 'x' => 'zz-poly-null', 'y' => 'zz-poly-empty']),
    wp_get_script_polyfill($scripts, ['y' => 'zz-never']),
    wp_get_script_polyfill($scripts, []),
    wp_get_script_polyfill($scripts, ['a' => 'zz-poly-amp', 'b' => 'zz-poly-bare', 'c' => 'zz-poly-nosrc']),
    wp_get_script_polyfill($scripts, ['a' => 'zz-poly-proto', 'b' => 'zz-poly-upper', 'c' => 'zz-poly-httpx', "d'q\"" => 'zz-poly']),
    $with('script_loader_src', static fn ($src, $handle) => $handle === 'zz-poly' ? '' : $src, static fn () => wp_get_script_polyfill($scripts, ['a' => 'zz-poly', 'b' => 'zz-poly-rel'])),
    $with('script_loader_src', static fn ($src) => $src . '#filtered', static fn () => wp_get_script_polyfill($scripts, ['a' => 'zz-poly'])),
    (static function () use ($scripts) {
        $base = $scripts->base_url;
        $scripts->base_url = 'https://zz.example/base';
        $out = wp_get_script_polyfill($scripts, ['a' => 'zz-poly-rel']);
        $scripts->base_url = $base;
        return $out;
    })(),
]));

$say('prototype before jquery', array_map(static fn ($list) => wp_prototype_before_jquery($list), [['jquery', 'prototype', 'x'], ['prototype', 'jquery'], ['jquery', 'x'], ['x', 'prototype'], [], ['a', 'jquery', 'b', 'prototype'], ['jquery', 'jquery', 'prototype'], ['prototype', 'x', 'jquery'], ['jquery', 'prototype', 'prototype'], ['x', 'y'], ['prototype'], ['jquery']]));

$styles = wp_styles();
$say('the concatenation printers', $mask([
    (static function () use ($scripts, $printed) {
        $scripts->do_concat = false;
        $scripts->concat = '';
        $scripts->print_html = '<!-- zz html -->';
        $scripts->print_code = 'var zz = 1;';
        $out = $printed(static fn () => _print_scripts());
        return [$out, $scripts->print_html, $scripts->print_code, $scripts->concat];
    })(),
    (static function () use ($scripts, $printed) {
        $scripts->do_concat = true;
        $scripts->concat = 'zz-a,zz-b,';
        $scripts->print_html = '';
        $scripts->print_code = 'var zz = 2;';
        $out = $printed(static fn () => _print_scripts());
        $scripts->do_concat = false;
        return [$out, $scripts->print_html, $scripts->print_code, $scripts->concat];
    })(),
    (static function () use ($scripts, $printed) {
        $scripts->do_concat = true;
        $scripts->concat = str_repeat('zz-long-handle,', 12);
        $scripts->print_html = '<!-- zz -->';
        $scripts->print_code = '';
        $out = $printed(static fn () => _print_scripts());
        $scripts->do_concat = false;
        $scripts->concat = '';
        $scripts->print_html = '';
        return $out;
    })(),
    (static function () use ($scripts, $printed) {
        $GLOBALS['compress_scripts'] = true;
        $base = $scripts->base_url;
        $version = $scripts->default_version;
        $scripts->base_url = 'https://zz.example/base';
        $scripts->default_version = 'zz-ver';
        $scripts->do_concat = true;
        $scripts->concat = ', zz-a, zz-b ,';
        $scripts->print_code = '';
        $out = $printed(static fn () => _print_scripts());
        unset($GLOBALS['compress_scripts']);
        $scripts->base_url = $base;
        $scripts->default_version = $version;
        $scripts->do_concat = false;
        $scripts->concat = '';
        return $out;
    })(),
    (static function () use ($scripts, $printed) {
        $scripts->do_concat = false;
        $scripts->concat = 'zz-a,';
        $scripts->print_code = 'var zz = 4;';
        $scripts->print_html = '';
        $out = $printed(static fn () => _print_scripts());
        $scripts->concat = '';
        $scripts->print_code = '';
        return $out;
    })(),
    (static function () use ($scripts, $printed) {
        $scripts->do_concat = true;
        $scripts->concat = '';
        $scripts->print_code = 'var zz = 3;';
        $scripts->print_html = '<!-- zz3 -->';
        $out = $printed(static fn () => _print_scripts());
        $scripts->do_concat = false;
        $scripts->print_code = '';
        $scripts->print_html = '';
        return $out;
    })(),
    (static function () use ($styles, $printed) {
        $styles->do_concat = false;
        $styles->concat = '';
        $styles->print_html = '<!-- zz style html -->';
        $styles->print_code = '.zz{}';
        $out = $printed(static fn () => _print_styles());
        return [$out, $styles->print_html, $styles->print_code];
    })(),
    (static function () use ($styles, $printed) {
        $styles->do_concat = true;
        $styles->concat = 'zz-a,zz-b,';
        $styles->print_html = '';
        $styles->print_code = '.zz2{}';
        $out = $printed(static fn () => _print_styles());
        $styles->do_concat = false;
        $styles->concat = '';
        $styles->print_code = '';
        return $out;
    })(),
    (static function () use ($styles, $printed) {
        $GLOBALS['compress_css'] = true;
        $styles->do_concat = true;
        $styles->concat = str_repeat('zz-long-style,', 10);
        $styles->print_html = '<!-- zz style html 2 -->';
        $styles->print_code = '';
        $out = $printed(static fn () => _print_styles());
        unset($GLOBALS['compress_css']);
        $styles->do_concat = false;
        $styles->concat = '';
        $styles->print_html = '';
        return $out;
    })(),
    (static function () use ($styles, $printed) {
        $base = $styles->base_url;
        $version = $styles->default_version;
        $styles->base_url = 'https://zz.example/sbase';
        $styles->default_version = 'zz-sver';
        $styles->do_concat = false;
        $styles->concat = ' zz-c, zz-d ';
        $styles->print_code = '.zz4{}';
        $styles->print_html = '';
        $out = [$printed(static fn () => _print_styles())];
        $styles->do_concat = true;
        $out[] = $printed(static fn () => _print_styles());
        $styles->base_url = $base;
        $styles->default_version = $version;
        $styles->do_concat = false;
        $styles->concat = '';
        $styles->print_code = '';
        return $out;
    })(),
    (static function () use ($styles, $printed) {
        $styles->text_direction = 'rtl';
        $styles->do_concat = true;
        $styles->concat = 'zz-a';
        $out = $printed(static fn () => _print_styles());
        $styles->text_direction = 'ltr';
        $styles->do_concat = false;
        $styles->concat = '';
        return $out;
    })(),
    (static function () use ($styles, $printed) {
        $styles->do_concat = true;
        $styles->concat = '';
        $styles->print_html = '<!-- zz style html 3 -->';
        $styles->print_code = '.zz3{}';
        $out = $printed(static fn () => _print_styles());
        $styles->do_concat = false;
        $styles->print_html = '';
        $styles->print_code = '';
        return $out;
    })(),
]));

$say('localized data is the script\'s data string', (static function () use ($scripts, $printed) {
    wp_register_script('zz-l1', 'https://zz.example/1.js', [], '1');
    wp_localize_script('zz-l1', 'zzA', ['a' => 1]);
    $out = [$scripts->get_data('zz-l1', 'data'), $scripts->registered['zz-l1']->extra['data'] ?? null];
    wp_localize_script('zz-l1', 'zzB', ['b' => '2']);
    $out[] = $scripts->get_data('zz-l1', 'data');
    wp_register_script('zz-l2', 'https://zz.example/2.js', [], '1');
    wp_script_add_data('zz-l2', 'data', 'var raw = 1;');
    wp_localize_script('zz-l2', 'zzC', ['c' => 3]);
    $out[] = $scripts->get_data('zz-l2', 'data');
    wp_script_add_data('zz-l2', 'data', 'var replaced = 1;');
    $out[] = $scripts->get_data('zz-l2', 'data');
    wp_localize_script('zz-l2', 'zzD', ['d' => 4]);
    $out[] = $scripts->get_data('zz-l2', 'data');
    wp_register_script('zz-l3', 'https://zz.example/3.js', [], '1');
    $scripts->registered['zz-l3']->extra['data'] = 'var direct = 1;';
    wp_localize_script('zz-l3', 'zzE', ['e' => 5]);
    $out[] = $scripts->get_data('zz-l3', 'data');
    $out[] = $printed(static fn () => $scripts->print_extra_script('zz-l2'));
    $out[] = $printed(static fn () => wp_print_scripts(['zz-l3']));
    return $out;
})());

$jit = ['autosave', 'mce-view', 'word-count'];
foreach ($jit as $handle) {
    wp_deregister_script($handle);
    wp_register_script($handle, "https://zz.example/{$handle}.js", [], '1');
}
$before = array_map(static fn ($handle) => $scripts->get_data($handle, 'data'), $jit);
wp_just_in_time_script_localization();
$say('the embed shortcode outside the_content', [shortcode_exists('embed'), strip_shortcodes('a [embed]https://zz.example/v[/embed] b'), do_shortcode('a [embed]https://zz.example/v[/embed] b')]);
$say('scripts localized just in time', [$before, array_combine($jit, array_map(static fn ($handle) => $scripts->get_data($handle, 'data'), $jit))]);

$zz_after = static function () use ($styles): array {
    $out = [];
    foreach ($styles->registered as $handle => $item) {
        foreach ((array) ($item->extra['after'] ?? []) as $after) {
            if (str_contains((string) $after, '.zz-')) {
                $out[$handle][] = $after;
            }
        }
    }
    return $out;
};
$say('stored styles enqueued', [
    (static function () use ($styles) {
        wp_enqueue_stored_styles();
        return [wp_style_is('core-block-supports', 'registered'), wp_style_is('core-block-supports', 'enqueued'), $styles->get_data('core-block-supports', 'after')];
    })(),
    (static function () use ($styles) {
        wp_style_engine_get_stylesheet_from_css_rules([['selector' => '.zz-a', 'declarations' => ['color' => 'red', 'margin-top' => '1px']]], ['context' => 'block-supports']);
        wp_style_engine_get_stylesheet_from_css_rules([['selector' => '.zz-core', 'declarations' => ['color' => 'blue']]], ['context' => 'core']);
        wp_enqueue_stored_styles();
        $out = [];
        foreach (['core-block-supports', 'core-core', 'core'] as $handle) {
            $out[$handle] = [wp_style_is($handle, 'registered'), wp_style_is($handle, 'enqueued'), $styles->get_data($handle, 'after'), $styles->registered[$handle]->src ?? null];
        }
        return $out;
    })(),
    (static function () use ($styles) {
        wp_style_engine_get_stylesheet_from_css_rules([['selector' => '.zz-b', 'declarations' => ['color' => 'green']]], ['context' => 'block-supports']);
        wp_enqueue_stored_styles(['prettify' => true]);
        return $styles->get_data('core-block-supports', 'after');
    })(),
    (static function () use ($zz_after) {
        wp_enqueue_stored_styles(['prettify' => false]);
        return $zz_after();
    })(),
    (static function () use ($zz_after) {
        wp_style_engine_get_stylesheet_from_css_rules([['selector' => '.zz-other', 'declarations' => ['color' => 'pink']]], ['context' => 'zz-context']);
        wp_style_engine_get_stylesheet_from_css_rules([['selector' => '.zz-ctx', 'declarations' => ['color' => 'teal']]], ['context' => 'block-supports-duotone']);
        wp_enqueue_stored_styles();
        return $zz_after();
    })(),
]);

wp_register_style('zz-bs-handle', 'https://zz.example/bs.css', [], '1');
register_block_style('core/paragraph', ['name' => 'zz-fancy', 'label' => 'Zz Fancy', 'style_handle' => 'zz-bs-handle']);
if (!wp_style_is('wp-block-library', 'registered')) {
    wp_register_style('wp-block-library', false);
}
register_block_style('zz/never-registered', ['name' => 'zz-nev', 'label' => 'Zz Never', 'inline_style' => '.zz-inline-marker-never{color:red}']);
wp_register_style('zz-styled-css', 'https://zz.example/styled.css', [], '1');
register_block_type('zz/styled', ['style_handles' => ['zz-styled-css', 'zz-styled-second']]);
register_block_type('zz/unstyled', []);
register_block_style('zz/styled', ['name' => 'zz-s', 'label' => 'Zz S', 'inline_style' => '.zz-inline-marker-styled{color:red}']);
register_block_style('zz/unstyled', ['name' => 'zz-u', 'label' => 'Zz U', 'inline_style' => '.zz-inline-marker-unstyled{color:red}']);
register_block_style('core/zz-fake', ['name' => 'zz-f', 'label' => 'Zz F', 'inline_style' => '.zz-inline-marker-fake{color:red}']);
wp_register_style('wp-block-zz-fake2', false);
register_block_style('core/zz-fake2', ['name' => 'zz-f2', 'label' => 'Zz F2', 'inline_style' => '.zz-inline-marker-fake2{color:red}']);
register_block_type('core/zz-reg', []);
register_block_style('core/zz-reg', ['name' => 'zz-r', 'label' => 'Zz R', 'inline_style' => '.zz-inline-marker-reg{color:red}']);
$markers = static function () use ($styles): array {
    $out = [];
    foreach ($styles->registered as $handle => $item) {
        foreach ((array) ($item->extra['after'] ?? []) as $after) {
            if (preg_match('/zz-inline-marker[\w-]*/', (string) $after, $m) === 1) {
                $out[$m[0]][] = [$handle, $after];
            }
        }
    }
    ksort($out);
    return $out;
};
$say('block style variations', [
    has_action('enqueue_block_assets', 'enqueue_block_styles_assets'),
    has_action('wp_enqueue_scripts', 'enqueue_block_styles_assets'),
    wp_should_load_separate_core_block_assets(),
    (static function () use ($markers) {
        enqueue_block_styles_assets();
        return [wp_style_is('zz-bs-handle', 'enqueued'), $markers()];
    })(),
    (static function () use ($markers, $with) {
        return $with('should_load_separate_core_block_assets', '__return_false', static function () use ($markers) {
            wp_dequeue_style('zz-bs-handle');
            enqueue_block_styles_assets();
            return [wp_style_is('zz-bs-handle', 'enqueued'), $markers()];
        });
    })(),
    (static function () use ($with, $markers) {
        return $with('should_load_block_assets_on_demand', '__return_false', static function () use ($markers) {
            wp_dequeue_style('zz-bs-handle');
            $before = $markers();
            enqueue_block_styles_assets();
            $after = $markers();
            return [wp_style_is('zz-bs-handle', 'enqueued'), array_map(static fn ($key) => array_slice($after[$key], count($before[$key] ?? [])), array_combine(array_keys($after), array_keys($after)))];
        });
    })(),
    (static function () {
        wp_dequeue_style('zz-bs-handle');
        enqueue_block_styles_assets();
        render_block(['blockName' => 'core/heading', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<h2>x</h2>', 'innerContent' => ['<h2>x</h2>']]);
        $other = wp_style_is('zz-bs-handle', 'enqueued');
        render_block(['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]);
        $plain = [$other, wp_style_is('zz-bs-handle', 'enqueued')];
        render_block(['blockName' => 'core/paragraph', 'attrs' => ['className' => 'is-style-zz-fancy'], 'innerBlocks' => [], 'innerHTML' => '<p class="is-style-zz-fancy">x</p>', 'innerContent' => ['<p class="is-style-zz-fancy">x</p>']]);
        return [$plain, wp_style_is('zz-bs-handle', 'enqueued')];
    })(),
]);

wp_register_style('zz-blk-style', 'https://zz.example/blk.css', [], '1');
wp_register_style('zz-blk-editor-style', 'https://zz.example/blk-editor.css', [], '1');
wp_register_script('zz-blk-script', 'https://zz.example/blk.js', [], '1');
wp_register_script('zz-blk-view', 'https://zz.example/blk-view.js', [], '1');
wp_register_script('zz-blk-editor', 'https://zz.example/blk-editor.js', [], '1');
register_block_type('zz/probe-assets', ['style_handles' => ['zz-blk-style'], 'editor_style_handles' => ['zz-blk-editor-style'], 'script_handles' => ['zz-blk-script'], 'view_script_handles' => ['zz-blk-view'], 'editor_script_handles' => ['zz-blk-editor']]);
$queued = static fn () => [wp_style_is('zz-blk-style', 'enqueued'), wp_style_is('zz-blk-editor-style', 'enqueued'), wp_script_is('zz-blk-script', 'enqueued'), wp_script_is('zz-blk-view', 'enqueued'), wp_script_is('zz-blk-editor', 'enqueued')];
$say('every registered block\'s assets', [
    has_action('enqueue_block_assets', 'wp_enqueue_registered_block_scripts_and_styles'),
    (static function () use ($queued) {
        wp_enqueue_registered_block_scripts_and_styles();
        return $queued();
    })(),
    $with('should_load_separate_core_block_assets', '__return_false', static function () use ($queued) {
        wp_enqueue_registered_block_scripts_and_styles();
        return $queued();
    }),
    $with('should_load_block_assets_on_demand', '__return_false', static function () use ($queued) {
        wp_enqueue_registered_block_scripts_and_styles();
        return $queued();
    }),
]);

$say('development scripts', (static function () use ($scripts) {
    $handles = array_keys($scripts->registered);
    wp_register_development_scripts($scripts);
    return array_values(array_diff(array_keys($scripts->registered), $handles));
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
