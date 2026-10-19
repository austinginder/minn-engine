<?php
/**
 * How a template-part block finds its part, as the reference finds it
 * (probe template-part-lookup): only a part of the active theme is looked
 * for; a saved part first, through a WP_Query that pre_get_posts can steer
 * and loop_no_results hears miss; then the theme's file through
 * get_block_file_template; each found or missing one announced, and the
 * area's tag. Each template function on the way is traced alone too:
 * _get_block_template_file, _build_block_template_result_from_file and
 * _from_post, get_block_file_template, the area and type lists and the
 * area filter, and the theme's folders. Hooks are recorded as the block
 * itself fires them (see LOOKUP_HOOKS). Left out on both
 * sides, as no part of the lookup: the SQL each statement announces (query;
 * the engine answers some lookups from what it has read), and the hooks the
 * hook battery holds for every query and block: sanitize_key on column
 * names, kses's pass over block attributes (wp_kses_allowed_html, and the
 * block parser right after pre_kses) and get_block_type_uses_context.
 * The engine keeps tagName to the tags it allows, on purpose (security
 * review 2026-08-28, finding 4), where the reference prints any; no case
 * names another. Same protocol as api-probe.php; the probe's posts, its
 * terms and the part file it adds to the theme are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$theme = get_stylesheet();
$partsDir = get_stylesheet_directory() . '/parts';
$undeclared = $partsDir . '/zz-undeclared.html';
$posts = [];
$terms = [];
$areaTerms = [];
register_shutdown_function(static function () use (&$posts, &$terms, &$areaTerms, $undeclared): void {
    foreach ($posts as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($terms as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, 'wp_theme');
        }
    }
    foreach ($areaTerms as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, 'wp_template_part_area');
        }
    }
    if (is_file($undeclared)) {
        unlink($undeclared);
    }
});
if (is_file($undeclared) || file_put_contents($undeclared, "<!-- wp:paragraph -->\n<p>zz undeclared</p>\n<!-- /wp:paragraph -->\n") === false) {
    fwrite(STDERR, "template-part-lookup: could not write {$undeclared}\n");
    exit(1);
}
$saved = static function (string $slug, string $owner, string $text, string $area = '') use (&$posts, &$terms, &$areaTerms): int {
    $id = wp_insert_post(['post_type' => 'wp_template_part', 'post_name' => $slug, 'post_title' => "Zz {$slug}", 'post_status' => 'publish', 'post_content' => "<!-- wp:paragraph --><p>{$text}</p><!-- /wp:paragraph -->"]);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "template-part-lookup: could not save {$slug}\n");
        exit(1);
    }
    $posts[] = $id;
    $set = wp_set_object_terms($id, $owner, 'wp_theme');
    if (is_array($set) && $owner !== get_stylesheet()) {
        $term = get_term_by('name', $owner, 'wp_theme');
        if ($term && !in_array((int) $term->term_id, $terms, true)) {
            $terms[] = (int) $term->term_id;
        }
    }
    if ($area !== '') {
        $had = term_exists($area, 'wp_template_part_area');
        $set = wp_set_object_terms($id, $area, 'wp_template_part_area');
        if (!$had && is_array($set) && isset($set[0]) && is_int($set[0])) {
            $areaTerms[] = $set[0];
        }
    }
    return $id;
};
$savedPart = $saved('zz-saved', $theme, 'zz saved', 'footer');
$savedFooter = $saved('footer-newsletter', $theme, 'zz saved over the file');
$elsewhere = $saved('zz-elsewhere', 'zz-other-theme', 'zz other theme');
$savedTemplate = wp_insert_post(['post_type' => 'wp_template', 'post_name' => 'zz-saved-template', 'post_title' => 'Zz template', 'post_status' => 'publish', 'post_content' => '<!-- wp:template-part {"slug":"header"} /--><!-- wp:paragraph --><p>zz</p><!-- /wp:paragraph -->']);
if (!is_int($savedTemplate) || $savedTemplate <= 1) {
    fwrite(STDERR, "template-part-lookup: could not save a template\n");
    exit(1);
}
$posts[] = $savedTemplate;
wp_set_object_terms($savedTemplate, $theme, 'wp_theme');
$ids = [$savedPart => '{saved}', $savedFooter => '{saved footer}', $elsewhere => '{elsewhere}', $savedTemplate => '{saved template}'];

// Paths and ids as names; content as its length and its first words.
$mask = static function ($value) use (&$mask, $ids, $theme) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value) && isset($ids[$value])) {
        return $ids[$value];
    }
    if (is_string($value) && str_contains($value, '/wp-content/themes/')) {
        return '{themes}/' . substr($value, strpos($value, '/wp-content/themes/') + 19);
    }
    return $value;
};
$template = static function ($t) use ($mask) {
    if (!is_object($t)) {
        return is_wp_error($t) ? ['error', $t->get_error_code()] : $t;
    }
    $fields = $mask(array_intersect_key(get_object_vars($t), array_flip(['id', 'theme', 'slug', 'source', 'origin', 'type', 'title', 'description', 'status', 'has_theme_file', 'is_custom', 'author', 'area', 'post_types', 'wp_id', 'plugin'])));
    $fields['content'] = [strlen((string) $t->content), md5((string) $t->content)];
    return $fields;
};
// A block's trace keeps the hooks of the lookup, the template it builds and the part's own filters. A part whose
// blocks are the theme's (patterns, navigation) is traced until its blocks render, then only its last hooks: inner
// blocks rendered outside render_block (a navigation's links) would read as the part's own.
const LOOKUP_HOOKS = '/^(gettext|gettext_with_context|pre_get_posts|parse_query|posts_pre_query|split_the_query|posts_request_ids|posts_results|the_posts|loop_no_results|stylesheet|template|stylesheet_directory|template_directory|theme_root|pre_get_block_file_template|get_block_file_template|render_block_core_template_part_(post|file|none)|default_wp_template_part_areas|default_template_types|get_the_terms|get_post_metadata|metadata_lazyloader_queued_objects|block_parser_class|pre_do_shortcode_tag|do_shortcode_tag|run_wptexturize|no_texturize_tags|no_texturize_shortcodes|tag_escape|render_block|render_block_core\/template-part)$/';
$trace = static function (callable $call, string $mode = 'call'): array {
    $seen = [];
    $depth = 0;
    $afterKses = false;
    $announced = false;
    $cut = false;
    $record = static function () use (&$seen, &$depth, &$afterKses, &$announced, &$cut, $mode): void {
        $hook = current_filter();
        $kses = $afterKses && $hook === 'block_parser_class';
        $afterKses = $hook === 'pre_kses';
        if ($kses || in_array($hook, ['query', 'get_block_type_uses_context', 'sanitize_key', 'wp_kses_allowed_html'], true)) {
            return;
        }
        if ($mode === 'call') {
            $seen[] = $hook;
            return;
        }
        if ($hook === 'pre_render_block') {
            $depth++;
        }
        if ($mode === 'theme part' && $announced && $hook === 'block_parser_class') {
            $cut = true;
        }
        $announced = $announced || str_starts_with($hook, 'render_block_core_template_part_');
        if ($depth <= 1 && !$cut && preg_match(LOOKUP_HOOKS, $hook) === 1) {
            $seen[] = $hook;
        }
        // A block's render ends with render_block_{name}, after render_block.
        if (str_starts_with($hook, 'render_block_') && ($hook === 'render_block_' || str_contains($hook, '/'))) {
            $depth--;
        }
    };
    add_action('all', $record);
    $result = $call();
    remove_action('all', $record);
    if ($mode === 'theme part') {
        $seen[] = 'render_block_core/template-part';
    }
    $out = [];
    foreach ($seen as $hook) {
        if ($out !== [] && end($out)[0] === $hook) {
            $out[count($out) - 1][1]++;
        } else {
            $out[] = [$hook, 1];
        }
    }
    return [array_map(static fn ($pair) => $pair[1] > 1 ? "{$pair[0]} x{$pair[1]}" : $pair[0], $out), $result];
};
// The engine's probe runner fires no init, so a block's style would be enqueued too early there; the queue is
// opened as it is on the reference, where init has run.
if (!did_action('init') && !did_action('wp_enqueue_scripts')) {
    do_action('wp_enqueue_scripts');
}
$notices = [];
set_error_handler(static function (int $level, string $message) use (&$notices): bool {
    $notices[] = preg_replace('/ in \/.*$/', '', $message);
    return true;
}, E_USER_NOTICE | E_USER_WARNING);
// A layout's class carries a hash of its styles; the markup is compared without it.
$html = static fn (string $html): array => [strlen($html), md5((string) preg_replace('/-is-layout-[0-9a-f]{8}\b/', '-is-layout-{hash}', $html)), substr(trim(wp_strip_all_tags($html)), 0, 40), substr($html, 0, (int) strpos($html . '>', '>') + 1)];

// Each function alone, warmed once so caches agree.
$functions = [
    'stylesheet directory' => static fn () => basename((string) get_stylesheet_directory()),
    'template directory' => static fn () => basename((string) get_template_directory()),
    'areas' => static fn () => array_column(get_allowed_block_template_part_areas(), 'area_tag', 'area'),
    'template types' => static fn () => array_keys(get_default_block_template_types()),
    'area header' => static fn () => _filter_block_template_part_area('header'),
    'area unknown' => static fn () => _filter_block_template_part_area('zz'),
    'file: part' => static fn () => $mask(_get_block_template_file('wp_template_part', 'header')),
    'file: undeclared part' => static fn () => $mask(_get_block_template_file('wp_template_part', 'zz-undeclared')),
    'file: missing part' => static fn () => _get_block_template_file('wp_template_part', 'zz-none'),
    'file: template' => static fn () => $mask(_get_block_template_file('wp_template', 'index')),
    'file: other type' => static fn () => _get_block_template_file('wp_zz', 'header'),
    'build: part' => static fn () => $template(_build_block_template_result_from_file(_get_block_template_file('wp_template_part', 'footer'), 'wp_template_part')),
    'build: undeclared part' => static fn () => $template(_build_block_template_result_from_file(_get_block_template_file('wp_template_part', 'zz-undeclared'), 'wp_template_part')),
    'build: template' => static fn () => $template(_build_block_template_result_from_file(_get_block_template_file('wp_template', 'index'), 'wp_template')),
    'build: saved part' => static fn () => $template(_build_block_template_result_from_post(get_post($savedPart))),
    'build: saved over a file' => static fn () => $template(_build_block_template_result_from_post(get_post($savedFooter))),
    'build: saved elsewhere' => static fn () => $template(_build_block_template_result_from_post(get_post($elsewhere))),
    'build: saved template' => static fn () => $template(_build_block_template_result_from_post(get_post($savedTemplate))),
    'file template: part' => static fn () => $template(get_block_file_template($theme . '//header', 'wp_template_part')),
    'file template: missing' => static fn () => $template(get_block_file_template($theme . '//zz-none', 'wp_template_part')),
    'file template: other theme' => static fn () => $template(get_block_file_template('zz-other-theme//header', 'wp_template_part')),
    'file template: no theme' => static fn () => $template(get_block_file_template('header', 'wp_template_part')),
];
foreach ($functions as $label => $call) {
    $call();
    $notices = [];
    [$hooks, $result] = $trace($call);
    $say("function: {$label}", ['hooks' => $hooks, 'result' => $result, 'notices' => $notices]);
}
// A part the theme declares in an area the areas list has lost.
$drop = static fn ($areas) => array_values(array_filter($areas, static fn ($area) => $area['area'] !== 'header'));
add_filter('default_wp_template_part_areas', $drop);
$notices = [];
[$hooks, $result] = $trace(static fn () => $mask(_get_block_template_file('wp_template_part', 'header')));
$say('function: file: part of a lost area', ['hooks' => $hooks, 'result' => $result, 'notices' => $notices]);
remove_filter('default_wp_template_part_areas', $drop);

// The block, rendered.
$announced = [];
foreach (['post', 'file', 'none'] as $kind) {
    add_action("render_block_core_template_part_{$kind}", static function (...$args) use (&$announced, $kind, $mask): void {
        $args[2] = $kind === 'post' ? $mask($args[2]->ID ?? null) : $mask($args[2]);
        if (isset($args[3])) {
            $args[3] = strlen((string) $args[3]);
        }
        $announced[] = [$kind, $mask($args)];
    }, 10, 4);
}
$noResults = [];
add_action('loop_no_results', static function ($query) use (&$noResults): void {
    $noResults[] = [$query->get('post_type'), $query->get('post_name__in')];
});
$blocks = [
    'file part' => ['theme part', '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'],
    'file part, area tag' => ['theme part', '<!-- wp:template-part {"slug":"footer"} /-->'],
    'file part, the theme named' => ['theme part', '<!-- wp:template-part {"slug":"footer","theme":"' . $theme . '"} /-->'],
    'undeclared part' => ['part', '<!-- wp:template-part {"slug":"zz-undeclared"} /-->'],
    'undeclared part, a tag named' => ['part', '<!-- wp:template-part {"slug":"zz-undeclared","tagName":"section","className":"zz-class"} /-->'],
    'missing part' => ['part', '<!-- wp:template-part {"slug":"zz-none"} /-->'],
    'saved part' => ['part', '<!-- wp:template-part {"slug":"zz-saved"} /-->'],
    'saved over a file' => ['part', '<!-- wp:template-part {"slug":"footer-newsletter"} /-->'],
    'saved for another theme only' => ['part', '<!-- wp:template-part {"slug":"zz-elsewhere"} /-->'],
    'another theme named' => ['part', '<!-- wp:template-part {"slug":"footer","theme":"zz-other-theme"} /-->'],
    'no slug' => ['part', '<!-- wp:template-part {"tagName":"header"} /-->'],
];
// A page looks its parts up once; a lookup run again within a request is answered from the post-queries cache on the
// reference, so each is traced as a first lookup: the posts' last change moved on first.
foreach ($blocks as $label => [$mode, $markup]) {
    do_blocks($markup);
    wp_cache_set_last_changed('posts');
    $announced = [];
    $noResults = [];
    $notices = [];
    [$hooks, $out] = $trace(static fn () => do_blocks($markup), $mode);
    $say("block: {$label}", ['hooks' => $hooks, 'html' => $html($out), 'announced' => $announced, 'no results' => $noResults, 'notices' => $notices]);
}
// Hooked blocks: one after the part's paragraph, one as the part's own last child; the template as built and as rendered.
$hook = static function ($hooked, $position, $anchor) {
    if (($anchor === 'core/paragraph' && $position === 'after') || ($anchor === 'core/template-part' && $position === 'last_child')) {
        $hooked[] = 'core/site-tagline';
    }
    return $hooked;
};
add_filter('hooked_block_types', $hook, 10, 3);
$say('function: file template, hooked blocks', ['result' => $template(get_block_file_template($theme . '//zz-undeclared', 'wp_template_part'))]);
$say('function: saved part, hooked blocks', ['result' => $template(_build_block_template_result_from_post(get_post($savedPart)))]);
wp_cache_set_last_changed('posts');
$say('block: hooked blocks', ['html' => $html(do_blocks('<!-- wp:template-part {"slug":"zz-undeclared"} /-->'))]);
remove_filter('hooked_block_types', $hook, 10);

// pre_get_posts steering the lookup to another saved part.
$steer = static function ($query): void {
    if ($query->get('post_type') === 'wp_template_part' && $query->get('post_name__in') === ['zz-none']) {
        $query->set('post_name__in', ['zz-saved']);
    }
};
add_action('pre_get_posts', $steer);
wp_cache_set_last_changed('posts');
$announced = [];
$noResults = [];
[$hooks, $out] = $trace(static fn () => do_blocks('<!-- wp:template-part {"slug":"zz-none"} /-->'), 'part');
remove_action('pre_get_posts', $steer);
$say('block: steered by pre_get_posts', ['html' => $html($out), 'announced' => $announced, 'no results' => $noResults]);
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
