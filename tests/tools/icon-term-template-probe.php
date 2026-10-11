<?php
/**
 * The icon block and the term template (probe icon-term-template): each
 * block's registered callback swapped, for its render only, for one that
 * calls the core function by name. An icon the probe registers (its own
 * drawing), flipped, rotated, labelled, with colours, unknown or missing;
 * and a terms query over the probe's own taxonomy, its template listing the
 * terms with their name, count and description, nested or not, empty or
 * not, ordered, limited, included, laid out. The terms and post the probe
 * makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function (string $html) use (&$ids, $host): string {
    $html = (string) preg_replace(['#https?://' . preg_quote($host, '#') . '#', '/(wp-container-[a-z0-9-]+-is-layout-)[0-9a-f]{8}/'], ['{site}', '$1HASH'], $html);
    foreach ($ids as $id => $label) {
        $html = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $label . '}', $html);
    }
    return $html;
};
// The block's own callback stands aside for one render; this one calls the core function by name.
$through = static function (string $name, Closure $callback, string $markup) use ($mask) {
    $type = WP_Block_Type_Registry::get_instance()->get_registered($name);
    $saved = $type->render_callback;
    $type->render_callback = $callback;
    $html = do_blocks($markup);
    $type->render_callback = $saved;
    return $mask(trim($html));
};

// An icon is registered in a collection: the probe's own, removed with it.
WP_Icon_Collections_Registry::get_instance()->register('zz', ['label' => 'ZZ icons', 'description' => 'The probe\'s own.']);
$icons = WP_Icons_Registry::get_instance();
$icons->register('zz/square', ['label' => 'ZZ square', 'content' => '<svg viewBox="0 0 24 24" width="24" height="24"><path d="M4 4h16v16H4z"/></svg>']);
$icon = static fn (string $attrs) => '<!-- wp:icon ' . $attrs . ' /-->';
$byName = static fn ($a, $c, $b) => render_block_core_icon($a);
$say('icon', [
    'plain' => $through('core/icon', $byName, $icon('{"icon":"zz/square"}')),
    'flipped both ways' => $through('core/icon', $byName, $icon('{"icon":"zz/square","flipHorizontal":true,"flipVertical":true}')),
    'rotated' => $through('core/icon', $byName, $icon('{"icon":"zz/square","rotation":180}')),
    'labelled' => $through('core/icon', $byName, $icon('{"icon":"zz/square","ariaLabel":"ZZ \"label\""}')),
    'with supports' => $through('core/icon', $byName, $icon('{"icon":"zz/square","align":"center","className":"zz-extra","textColor":"accent-1","style":{"spacing":{"padding":"4px"}}}')),
    'everything at once' => $through('core/icon', $byName, $icon('{"icon":"zz/square","flipHorizontal":true,"rotation":90,"ariaLabel":"ZZ all","textColor":"accent-1","backgroundColor":"base","style":{"border":{"width":"2px","color":"#111111","radius":"3px"},"spacing":{"padding":"2px","margin":"3px"},"dimensions":{"width":"48px"}}}')),
    'custom colours' => $through('core/icon', $byName, $icon('{"icon":"zz/square","style":{"color":{"text":"#123456","background":"#abcdef"}}}')),
    'a flip with a colour' => $through('core/icon', $byName, $icon('{"icon":"zz/square","flipVertical":true,"textColor":"accent-1"}')),
    'an anchor' => $through('core/icon', $byName, $icon('{"icon":"zz/square","anchor":"zz-anchor"}')),
    'unknown' => $through('core/icon', $byName, $icon('{"icon":"zz/none"}')),
    'none named' => $through('core/icon', $byName, $icon('{}')),
]);
$icons->unregister('zz/square');
WP_Icon_Collections_Registry::get_instance()->unregister('zz');

// A taxonomy of the probe's own: alpha (with a child, beta) and gamma, empty; delta in a flat one.
register_taxonomy('zz_tt', 'post', ['hierarchical' => true, 'public' => true, 'label' => 'ZZ hierarchical']);
register_taxonomy('zz_tt_flat', 'post', ['hierarchical' => false, 'public' => true, 'label' => 'ZZ flat']);
register_taxonomy('zz_tt_empty', 'post', ['hierarchical' => true, 'public' => true, 'label' => 'ZZ empty']);
$made = ['terms' => [], 'post' => 0];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, $taxonomy);
        }
    }
    if (is_int($made['post']) && $made['post'] > 1) {
        wp_delete_post($made['post'], true);
    }
});
$term = static function (string $name, string $taxonomy, array $args = []) use (&$made, &$ids) {
    $result = wp_insert_term($name, $taxonomy, $args);
    $id = is_array($result) ? (int) $result['term_id'] : 0;
    if ($id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['terms'][] = [$id, $taxonomy];
    $ids[$id] = strtolower(str_replace('ZZ ', '', $name));
    return $id;
};
$alpha = $term('ZZ alpha', 'zz_tt', ['description' => 'Alpha described.']);
$beta = $term('ZZ beta', 'zz_tt', ['parent' => $alpha]);
$gamma = $term('ZZ gamma', 'zz_tt');
$delta = $term('ZZ delta', 'zz_tt_flat');
$post = wp_insert_post(['post_title' => 'ZZ tt post', 'post_status' => 'publish', 'post_content' => 'zz']);
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['post'] = $post;
wp_set_object_terms($post, [$alpha, $beta], 'zz_tt');
wp_set_object_terms($post, [$delta], 'zz_tt_flat');

$terms = static function (array $query, string $inner = '<!-- wp:term-name /-->', string $templateAttrs = '') use ($through) {
    $query += ['perPage' => 10, 'taxonomy' => 'zz_tt', 'order' => 'asc', 'orderBy' => 'name', 'include' => [], 'hideEmpty' => true, 'showNested' => false, 'inherit' => false];
    $markup = '<!-- wp:terms-query {"termQuery":' . json_encode($query) . '} --><div class="wp-block-terms-query"><!-- wp:term-template ' . $templateAttrs . ' -->' . $inner . '<!-- /wp:term-template --></div><!-- /wp:terms-query -->';
    return $through('core/term-template', static fn ($a, $c, $b) => render_block_core_term_template($a, $c, $b), $markup);
};
$say('term template', [
    'the defaults' => $terms([]),
    'empty ones too' => $terms(['hideEmpty' => false]),
    'nested' => $terms(['hideEmpty' => false, 'showNested' => true]),
    'by count, descending' => $terms(['hideEmpty' => false, 'orderBy' => 'count', 'order' => 'desc']),
    'by count' => $terms(['hideEmpty' => false, 'orderBy' => 'count']),
    'one' => $terms(['hideEmpty' => false, 'perPage' => 1]),
    'included' => $terms(['hideEmpty' => false, 'include' => [$gamma, $alpha]]),
    'inherited, off an archive' => $terms(['hideEmpty' => false, 'inherit' => true]),
    'a flat taxonomy' => $terms(['taxonomy' => 'zz_tt_flat']),
    'an empty taxonomy' => $terms(['taxonomy' => 'zz_tt_empty', 'hideEmpty' => false]),
    'name, count and description' => $terms(['hideEmpty' => false], '<!-- wp:term-name {"isLink":true,"level":3} /--><!-- wp:term-count /--><!-- wp:term-description /-->'),
    'a grid with a colour' => $terms(['hideEmpty' => false], '<!-- wp:term-name /-->', '{"layout":{"type":"grid","columnCount":2},"textColor":"accent-1"}'),
    'the template alone' => $through('core/term-template', static fn ($a, $c, $b) => render_block_core_term_template($a, $c, $b), '<!-- wp:term-template --><!-- wp:term-name /--><!-- /wp:term-template -->'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
