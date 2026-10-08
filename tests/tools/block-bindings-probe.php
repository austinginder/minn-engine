<?php
/**
 * Block bindings as the reference renders them (probe block-bindings): the
 * registered sources and the attributes each block may bind; paragraphs,
 * headings, buttons and images bound to post meta (registered, protected,
 * unregistered, on a post behind a password), to post data and term data
 * by key, to a source the probe registers (with block_bindings_source_value
 * filtering it), and to pattern overrides inside a synced pattern; a
 * binding on an attribute that cannot be bound; the registry's own calls.
 * The probe's posts, meta and source, removed at the end. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        wp_delete_post($id, true);
    }
});
set_error_handler(static fn () => true, E_USER_NOTICE | E_USER_WARNING | E_USER_DEPRECATED);
// Sources register at init, which a request (and wp eval-file) has reached by now.
if (!did_action('init')) {
    do_action('init');
}

$sources = [];
foreach (get_all_registered_block_bindings_sources() as $name => $source) {
    $sources[$name] = [get_class($source), $source->label, $source->uses_context ?? null];
}
ksort($sources);
$say('sources', $sources);
foreach (['core/paragraph', 'core/heading', 'core/button', 'core/image', 'core/post-date', 'core/navigation-link', 'core/navigation-submenu', 'core/post-title', 'core/quote', 'zz/nope'] as $block) {
    $say("supported attributes {$block}", get_block_bindings_supported_attributes($block));
}
$more = static fn ($attributes) => [...$attributes, 'zz_extra'];
add_filter('block_bindings_supported_attributes_core/paragraph', $more);
$say('supported attributes core/paragraph, filtered by name', get_block_bindings_supported_attributes('core/paragraph'));
remove_filter('block_bindings_supported_attributes_core/paragraph', $more);
$every = static fn ($attributes, $block) => $block === 'core/quote' ? ['citation'] : $attributes;
add_filter('block_bindings_supported_attributes', $every, 10, 2);
$say('supported attributes core/quote, filtered for every block', get_block_bindings_supported_attributes('core/quote'));
remove_filter('block_bindings_supported_attributes', $every, 10);

register_post_meta('post', 'zz_bound', ['show_in_rest' => true, 'single' => true, 'type' => 'string', 'default' => 'Meta default']);
register_post_meta('post', '_zz_hidden', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
register_post_meta('post', 'zz_unshown', ['show_in_rest' => false, 'single' => true, 'type' => 'string']);
$post = (int) wp_insert_post(['post_title' => 'Zz bound post', 'post_status' => 'publish', 'post_content' => 'x', 'post_date' => '2021-03-04 05:06:07', 'post_excerpt' => 'Zz excerpt']);
$made[] = $post;
update_post_meta($post, 'zz_bound', 'Bound <b>value</b> & "quotes"');
update_post_meta($post, '_zz_hidden', 'Hidden value');
update_post_meta($post, 'zz_unshown', 'Unshown value');
update_post_meta($post, 'zz_url', 'https://example.com/zz?a=1&b=2');
register_post_meta('post', 'zz_url', ['show_in_rest' => true, 'single' => true, 'type' => 'string']);
$locked = (int) wp_insert_post(['post_title' => 'Zz locked', 'post_status' => 'publish', 'post_password' => 'zz', 'post_content' => 'x']);
$made[] = $locked;
update_post_meta($locked, 'zz_bound', 'Locked value');

$render = static function (string $markup, int $postId) use ($post): string {
    $out = '';
    foreach (parse_blocks($markup) as $parsed) {
        if ($parsed['blockName'] === null) {
            continue;
        }
        $out .= (new WP_Block($parsed, ['postId' => $postId, 'postType' => get_post_type($postId)]))->render();
    }
    return str_replace((string) $post, '{post}', $out);
};
$bind = static fn (string $block, array $bindings, string $html, array $attrs = []) => '<!-- wp:' . $block . ' ' . wp_json_encode($attrs + ['metadata' => ['bindings' => $bindings]]) . ' -->' . $html . '<!-- /wp:' . $block . ' -->';
$meta = static fn (string $key) => ['source' => 'core/post-meta', 'args' => ['key' => $key]];

$say('paragraph to meta', $render($bind('paragraph', ['content' => $meta('zz_bound')], '<p>Default</p>'), $post));
$say('paragraph to protected meta', $render($bind('paragraph', ['content' => $meta('_zz_hidden')], '<p>Default</p>'), $post));
$say('paragraph to meta not shown in REST', $render($bind('paragraph', ['content' => $meta('zz_unshown')], '<p>Default</p>'), $post));
$say('paragraph to meta never registered', $render($bind('paragraph', ['content' => $meta('zz_never')], '<p>Default</p>'), $post));
$say('paragraph to meta on a locked post', $render($bind('paragraph', ['content' => $meta('zz_bound')], '<p>Default</p>'), $locked));
$empty = (int) wp_insert_post(['post_title' => 'Zz empty', 'post_status' => 'publish', 'post_content' => 'x']);
$made[] = $empty;
$say('paragraph to meta with no value', $render($bind('paragraph', ['content' => $meta('zz_bound')], '<p>Default</p>'), $empty));
$say('heading to meta', $render($bind('heading', ['content' => $meta('zz_bound')], '<h3 class="wp-block-heading">Default</h3>', ['level' => 3]), $post));
$say('button to meta', $render($bind('button', ['url' => $meta('zz_url'), 'text' => $meta('zz_bound')], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://default.example">Default</a></div>'), $post));
$say('image to meta', $render($bind('image', ['url' => $meta('zz_url'), 'alt' => $meta('zz_bound'), 'title' => $meta('zz_bound')], '<figure class="wp-block-image"><img src="https://default.example/a.png" alt="Default"/></figure>'), $post));
$say('an attribute that cannot be bound', $render($bind('paragraph', ['className' => $meta('zz_bound')], '<p>Default</p>'), $post));

foreach (['date', 'modified', 'link', 'title', 'excerpt', 'nope'] as $key) {
    $say("post data {$key}", $render($bind('paragraph', ['content' => ['source' => 'core/post-data', 'args' => ['key' => $key]]], '<p>Default</p>'), $post));
}
$term = get_term_by('slug', 'uncategorized', 'category');
foreach (['name', 'description', 'link', 'count', 'nope'] as $key) {
    $parsed = parse_blocks($bind('paragraph', ['content' => ['source' => 'core/term-data', 'args' => ['key' => $key]]], '<p>Default</p>'))[0];
    $say("term data {$key}", (new WP_Block($parsed, ['termId' => $term->term_id, 'taxonomy' => 'category']))->render());
}
foreach (['id', 'link', 'name'] as $key) {
    $parsed = parse_blocks('<!-- wp:navigation-link ' . wp_json_encode(['label' => 'Zz', 'url' => 'https://default.example', 'kind' => 'taxonomy', 'type' => 'category', 'id' => $term->term_id, 'metadata' => ['bindings' => ['url' => ['source' => 'core/term-data', 'args' => ['key' => $key]]]]]) . ' /-->')[0];
    $say("term data {$key} on a navigation link", str_replace((string) $term->term_id, '{term}', (string) preg_replace('/\s+/', ' ', (new WP_Block($parsed, ['termId' => $term->term_id, 'taxonomy' => 'category']))->render())));
}
$changed = (int) wp_insert_post(['post_title' => 'Zz changed', 'post_status' => 'publish', 'post_content' => 'x', 'post_date' => '2021-03-04 05:06:07']);
$made[] = $changed;
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_modified' => '2022-05-06 07:08:09', 'post_modified_gmt' => '2022-05-06 07:08:09'], ['ID' => $changed]);
clean_post_cache($changed);
$say('post data modified, changed later', $render($bind('paragraph', ['content' => ['source' => 'core/post-data', 'args' => ['key' => 'modified']]], '<p>Default</p>'), $changed));

// A source of the probe's own, and the filter on every value.
$heard = [];
register_block_bindings_source('zz/source', [
    'label' => 'Zz source',
    'get_value_callback' => static function ($args, $block, $attribute) use (&$heard) {
        $heard[] = [$args, $block->name, $attribute, $block->context['postId'] ?? null];
        return 'Zz ' . ($args['word'] ?? '?') . ' for ' . $attribute;
    },
    'uses_context' => ['postId'],
]);
$say('registered source object', [get_class(get_block_bindings_source('zz/source')), get_block_bindings_source('zz/source')->label, get_block_bindings_source('zz/source')->uses_context]);
$say('paragraph to the probe source', $render($bind('paragraph', ['content' => ['source' => 'zz/source', 'args' => ['word' => 'hello']]], '<p>Default</p>'), $post));
$say('the probe source heard', array_map(static fn ($h) => [$h[0], $h[1], $h[2], $h[3] === $post], $heard));
$filter = static fn ($value, $name, $args, $block, $attribute) => $name === 'zz/source' ? strtoupper((string) $value) : $value;
add_filter('block_bindings_source_value', $filter, 10, 5);
$say('paragraph to the probe source, filtered', $render($bind('paragraph', ['content' => ['source' => 'zz/source', 'args' => ['word' => 'hi']]], '<p>Default</p>'), $post));
remove_filter('block_bindings_source_value', $filter, 10);
$say('a source that is not registered', $render($bind('paragraph', ['content' => ['source' => 'zz/none']], '<p>Default</p>'), $post));
$say('registering a source twice', [register_block_bindings_source('zz/source', ['label' => 'Again', 'get_value_callback' => '__return_empty_string'])]);
$say('unregister_block_bindings_source', [get_class((object) unregister_block_bindings_source('zz/source')), unregister_block_bindings_source('zz/source'), get_block_bindings_source('zz/source')]);
$say('registering a bad name', [register_block_bindings_source('NoSlash', ['label' => 'Bad', 'get_value_callback' => '__return_empty_string'])]);

// Pattern overrides inside a synced pattern.
$pattern = (int) wp_insert_post(['post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => 'Zz synced', 'post_content' => $bind('paragraph', ['__default' => ['source' => 'core/pattern-overrides']], '<p>Pattern default</p>', ['metadata' => ['name' => 'Zz name', 'bindings' => ['__default' => ['source' => 'core/pattern-overrides']]]]) . '<!-- wp:paragraph --><p>Not overridable</p><!-- /wp:paragraph -->']);
$made[] = $pattern;
$say('a synced pattern without overrides', str_replace((string) $pattern, '{pattern}', do_blocks('<!-- wp:block {"ref":' . $pattern . '} /-->')));
$say('a synced pattern with an override', str_replace((string) $pattern, '{pattern}', do_blocks('<!-- wp:block {"ref":' . $pattern . ',"content":{"Zz name":{"content":"Overridden <em>text</em>"}}} /-->')));
$say('a synced pattern overriding another name', str_replace((string) $pattern, '{pattern}', do_blocks('<!-- wp:block {"ref":' . $pattern . ',"content":{"Other":{"content":"Nope"}}} /-->')));
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
