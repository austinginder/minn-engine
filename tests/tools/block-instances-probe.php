<?php
/**
 * The WP_Block each block renders as, and the hooks around it (probe
 * block-instances): a document's top block asked pre_render_block,
 * render_block_data and render_block_context with no parent, then made; an
 * inner one made from what its parent gives its inner blocks (its type
 * asked get_block_type_uses_context, twice when it uses any), then asked
 * the three with its parent WP_Block, the context filter handed the context
 * its type uses; what a changed block or context becomes (eight cases);
 * render_block's instance and its context; a short-circuit before priority
 * 10 dropped (_wp_add_block_level_preset_styles answers null); a pattern's,
 * a template part's and a post's content beginning documents of their own;
 * a synced pattern's blocks inside its block; a post template's items
 * inside a block of no type (core/null) carrying the post; a navigation's
 * own items made without the filters before them, a submenu's with, and one
 * naming no menu rendering its fallback as
 * block_core_navigation_render_fallback leaves it; a social link list's
 * links as blocks; a comments block's comments each inside a comment
 * template of their own; a plugin block's provided context reaching a
 * plugin block inside a core one; the global post as it was after a query
 * loop; and which inner documents a parser block_parser_class swaps in
 * reads. Uses counts past the twice rule, block_parser_class counts and
 * block hooks are not compared (see contracts/runtime.md). Same protocol as
 * api-probe.php; the posts, part and registrations the probe makes are
 * removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$make = static function (array $post) use (&$made): int {
    $id = wp_insert_post($post);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "block-instances: could not make a {$post['post_type']}\n");
        exit(1);
    }
    $made[] = $id;
    return $id;
};

// Each object a hook is handed, numbered as it first appears (held, so no number is reused).
$trace = [];
$numbers = [];
$held = [];
$name = static function ($object) use (&$numbers, &$held): string {
    if (!is_object($object)) {
        return gettype($object);
    }
    $held[] = $object;
    $id = spl_object_id($object);
    $numbers[$id] ??= count($numbers) + 1;
    return ($object->name ?? '?') . '#' . $numbers[$id];
};
$keys = static function ($context): string {
    $keys = array_keys((array) $context);
    sort($keys);
    return implode(',', $keys);
};
$uses = [];
$parsed = 0;
$listeners = [
    'pre_render_block' => static function ($value, $block, $parent = null) use (&$trace, $name) {
        $trace[] = "pre {$block['blockName']} < " . $name($parent);
        return $value;
    },
    'render_block_data' => static function ($block, $source, $parent = null) use (&$trace, $name) {
        $trace[] = "data {$block['blockName']} < " . $name($parent);
        return $block;
    },
    'render_block_context' => static function ($context, $block, $parent = null) use (&$trace, $name, $keys) {
        $trace[] = "context {$block['blockName']} [" . $keys($context) . '] < ' . $name($parent);
        return $context;
    },
    'render_block' => static function ($html, $block, $instance = null) use (&$trace, $name, $keys) {
        $trace[] = "render {$block['blockName']} " . $name($instance) . (is_object($instance) ? ' {' . $keys($instance->context) . '}' : '');
        return $html;
    },
    'get_block_type_uses_context' => static function ($names, $type) use (&$uses) {
        $uses[] = $type->name;
        return $names;
    },
    'block_parser_class' => static function ($class) use (&$parsed) {
        $parsed++;
        return $class;
    },
];
$traced = static function (callable $run) use (&$trace, &$numbers, &$held, &$uses, &$parsed, $listeners): array {
    [$trace, $numbers, $held, $uses, $parsed] = [[], [], [], [], 0];
    foreach ($listeners as $hook => $listener) {
        add_filter($hook, $listener, 10, 3);
    }
    $html = $run();
    foreach ($listeners as $hook => $listener) {
        remove_filter($hook, $listener, 10);
    }
    $asked = array_values(array_unique($uses));
    sort($asked);
    // Masked: a layout's container hash and the probe's post ids.
    $html = is_string($html) ? (string) preg_replace(['/(wp-container-[a-z0-9-]+-is-layout-)[0-9a-f]{8}/', '/\bpost-\d+/'], ['$1{hash}', 'post-{id}'], $html) : null;
    return ['hooks' => $trace, 'uses asked for' => $asked, 'parser asked' => $parsed > 0, 'html' => $html];
};

// The type is asked what context it uses, and again when it uses any.
$add = static fn ($names, $type) => $type->name === 'core/paragraph' ? [...$names, 'zzKey'] : $names;
$paragraph = parse_blocks('<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->')[0];
$count = static function () use ($paragraph): array {
    $asks = 0;
    $counter = static function ($names) use (&$asks) {
        $asks++;
        return $names;
    };
    add_filter('get_block_type_uses_context', $counter, 20);
    $block = new WP_Block($paragraph, ['zzKey' => 'v', 'postId' => 5]);
    remove_filter('get_block_type_uses_context', $counter, 20);
    return ['asks' => $asks, 'context' => $block->context];
};
$say('a block that uses no context', $count());
add_filter('get_block_type_uses_context', $add, 10, 2);
$say('a block a filter gives a context name', $count());
remove_filter('get_block_type_uses_context', $add, 10);

// A short-circuit before priority 10 is dropped: the preset styles answer null.
foreach (['a string' => '<p>x</p>', 'null' => null, 'false' => false] as $label => $value) {
    $say("_wp_add_block_level_preset_styles given {$label}", _wp_add_block_level_preset_styles($value, ['blockName' => 'core/paragraph', 'attrs' => []]));
}
$skip = static fn ($value, $block) => $block['blockName'] === 'core/paragraph' ? '<p>skipped</p>' : $value;
foreach ([5, 10, 11] as $priority) {
    add_filter('pre_render_block', $skip, $priority, 2);
    $say("a short-circuit at {$priority}", [do_blocks('<!-- wp:paragraph --><p>top</p><!-- /wp:paragraph -->'), do_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --></div><!-- /wp:group -->')]);
    remove_filter('pre_render_block', $skip, $priority);
}

// Nested blocks, and what a changed block or context becomes.
$nested = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2 class="wp-block-heading">h</h2><!-- /wp:heading --></div><!-- /wp:group --></div><!-- /wp:group -->';
$say('nested blocks', $traced(static fn () => do_blocks($nested)));
$columns = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->';
$say('columns', $traced(static fn () => do_blocks($columns)));
$heading = static fn ($block) => $block['blockName'] === 'core/heading' ? ['attrs' => ['level' => 3]] + $block : $block;
add_filter('render_block_data', $heading, 5);
$say('an inner block render_block_data changes', $traced(static fn () => do_blocks($nested)));
remove_filter('render_block_data', $heading, 5);

register_block_type('zz/instances-box', ['render_callback' => static fn ($attributes, $content) => '<div>' . $content . '</div>', 'provides_context' => ['zz/box' => 'tone'], 'attributes' => ['tone' => ['type' => 'string', 'default' => 'warm']]]);
register_block_type('zz/instances-leaf', ['render_callback' => static fn ($attributes, $content, $block) => '<i>' . ($block->context['zz/box'] ?? '-') . ($block->context['extra'] ?? '-') . '</i>', 'uses_context' => ['zz/box', 'extra']]);
$boxed = '<!-- wp:zz/instances-box --><!-- wp:group --><div class="wp-block-group"><!-- wp:zz/instances-leaf /--></div><!-- /wp:group --><!-- /wp:zz/instances-box -->';
$give = static fn ($context, $block, $parent = null) => is_object($parent) && $parent->name === 'core/group' ? $context + ['zzKey' => 'given'] : $context;
$extra = static fn ($context, $block) => $block['blockName'] === 'core/group' ? $context + ['extra' => 'x'] : $context;
foreach ([
    'given a context the paragraph uses' => [$nested, [['render_block_context', $give, 5, 3], ['get_block_type_uses_context', $add, 5, 2]]],
    'given a context nothing uses' => [$nested, [['render_block_context', $give, 5, 3]]],
    'a plugin block\'s context through a core block' => [$boxed, []],
    'a group given more under a box' => [$boxed, [['render_block_context', $extra, 5, 2]]],
    'a leaf given another' => [$boxed, [['render_block_context', static fn ($context, $block) => $block['blockName'] === 'zz/instances-leaf' ? ['zz/box' => 'cold'] : $context, 5, 2]]],
    'a leaf given nothing' => [$boxed, [['render_block_context', static fn ($context, $block) => $block['blockName'] === 'zz/instances-leaf' ? [] : $context, 5, 2]]],
    'a group given another box' => [$boxed, [['render_block_context', static fn ($context, $block) => $block['blockName'] === 'core/group' ? ['zz/box' => 'swapped'] : $context, 5, 2]]],
    'a group changed and given more' => [$boxed, [['render_block_context', $extra, 5, 2], ['render_block_data', static fn ($block) => $block['blockName'] === 'core/group' ? ['attrs' => ['tagName' => 'section']] + $block : $block, 5, 1]]],
    'a root given more' => ['<!-- wp:zz/instances-box --><!-- wp:zz/instances-leaf /--><!-- /wp:zz/instances-box -->', [['render_block_context', static fn ($context, $block) => $block['blockName'] === 'zz/instances-box' ? $context + ['extra' => 'r'] : $context, 5, 2]]],
] as $label => [$markup, $filters]) {
    foreach ($filters as [$hook, $filter, $priority, $args]) {
        add_filter($hook, $filter, $priority, $args);
    }
    $say($label, $traced(static fn () => do_blocks($markup)));
    foreach ($filters as [$hook, $filter, $priority]) {
        remove_filter($hook, $filter, $priority);
    }
}
unregister_block_type('zz/instances-box');
unregister_block_type('zz/instances-leaf');

// Documents of their own: a pattern's, a part's, a post's content; a synced pattern's blocks inside its block.
register_block_pattern('zz/instances', ['title' => 'Zz instances', 'content' => '<!-- wp:paragraph --><p>pattern</p><!-- /wp:paragraph -->']);
$say('a pattern', $traced(static fn () => do_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:pattern {"slug":"zz/instances"} /--></div><!-- /wp:group -->')));
$synced = $make(['post_type' => 'wp_block', 'post_status' => 'publish', 'post_title' => 'Zz instances synced', 'post_content' => '<!-- wp:paragraph --><p>synced</p><!-- /wp:paragraph -->']);
$say('a synced pattern', $traced(static fn () => do_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:block {"ref":' . $synced . '} /--></div><!-- /wp:group -->')));
$part = $make(['post_type' => 'wp_template_part', 'post_name' => 'zz-instances-part', 'post_title' => 'Zz instances part', 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>part</p><!-- /wp:paragraph -->']);
wp_set_object_terms($part, get_stylesheet(), 'wp_theme');
wp_cache_set_last_changed('posts');
$say('a template part', $traced(static fn () => do_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:template-part {"slug":"zz-instances-part"} /--></div><!-- /wp:group -->')));

// A post template's items, inside a block of no type that carries the post; the global post after.
// A page, which schedules no pings as a published post does (the hook battery's capture would hang on the cron's state).
$post = $make(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Zz instances', 'post_content' => '<!-- wp:paragraph --><p>content</p><!-- /wp:paragraph -->']);
$loop = static fn (int $id, string $inner) => '<!-- wp:query {"queryId":' . (900 + $id % 50) . ',"query":{"perPage":1,"postType":"page","include":[' . $id . '],"inherit":false}} --><div class="wp-block-query"><!-- wp:post-template -->' . $inner . '<!-- /wp:post-template --></div><!-- /wp:query -->';
wp_cache_set_last_changed('posts');
$say('a query loop', $traced(static fn () => do_blocks($loop($post, '<!-- wp:post-title /-->'))));
$say('the global post after a query loop', get_post() === null);
wp_cache_set_last_changed('posts');
$say('post content in a query loop', $traced(static fn () => do_blocks($loop($post, '<!-- wp:post-content /-->'))));
$items = [];
$item = static function ($html, $block, $instance = null) use (&$items) {
    if ($block['blockName'] === 'core/null') {
        $items[] = ['html' => $html, 'keys' => array_keys($block), 'attrs' => $block['attrs'], 'inner' => array_map(static fn ($inner) => $inner['blockName'], $block['innerBlocks']), 'innerHTML' => $block['innerHTML'], 'context' => $instance->context ?? null];
    }
    return $html;
};
add_filter('render_block', $item, 10, 3);
wp_cache_set_last_changed('posts');
do_blocks($loop($post, '<!-- wp:post-title /--> <!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'));
remove_filter('render_block', $item, 10);
$say('a post template item', $items);

// A navigation's own items, and a submenu's; a social links list's.
$nav = $make(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Zz instances nav', 'post_content' => '<!-- wp:navigation-link {"label":"One","url":"https://example.com/one","kind":"custom"} /--><!-- wp:navigation-submenu {"label":"Two","url":"https://example.com/two","kind":"custom"} --><!-- wp:navigation-link {"label":"Three","url":"https://example.com/three","kind":"custom"} /--><!-- /wp:navigation-submenu -->']);
$navigation = $traced(static fn () => do_blocks('<!-- wp:navigation {"ref":' . $nav . '} /-->'));
// The reference renders a submenu's links through render_block twice; which blocks render is compared, not how often.
$rendered = array_values(array_unique(array_map(static fn ($line) => preg_replace('/#\d+/', '', $line), array_filter($navigation['hooks'], static fn ($line) => str_starts_with($line, 'render ')))));
$navigation['hooks'] = array_values(array_filter($navigation['hooks'], static fn ($line) => !str_starts_with($line, 'render ')));
$say('a navigation by ref', $navigation + ['rendered' => $rendered]);
// A navigation that names no menu renders its fallback (the latest menu's blocks, here the probe's) as
// block_core_navigation_render_fallback leaves it; one naming a menu, or with blocks of its own, asks nothing.
$asked = [];
$fallback = static function ($blocks) use (&$asked) {
    $asked[] = array_map(static fn ($block) => is_array($block) ? ($block['blockName'] ?? null) : gettype($block), (array) $blocks);
    return $blocks;
};
add_filter('block_core_navigation_render_fallback', $fallback);
$items = static fn (string $html): int => substr_count($html, 'wp-block-navigation-item ');
$found = [$items(do_blocks('<!-- wp:navigation /-->')), $items(do_blocks('<!-- wp:navigation {"ref":' . $nav . '} /-->')), $items(do_blocks('<!-- wp:navigation --><!-- wp:navigation-link {"label":"One","url":"https://example.com/one","kind":"custom"} /--><!-- /wp:navigation -->'))];
remove_filter('block_core_navigation_render_fallback', $fallback);
$none = static fn () => [];
add_filter('block_core_navigation_render_fallback', $none);
$emptied = $items(do_blocks('<!-- wp:navigation /-->'));
remove_filter('block_core_navigation_render_fallback', $none);
$say('a navigation that names no menu', ['asked with' => $asked, 'items' => $found, 'items with an empty fallback' => $emptied]);
$say('social links', $traced(static fn () => do_blocks('<!-- wp:social-links {"openInNewTab":true} --><ul class="wp-block-social-links"><!-- wp:social-link {"url":"https://example.com","service":"wordpress"} /--></ul><!-- /wp:social-links -->')));

// A comments block with comments: each rendered inside a comment template of its own, the comment's id all the
// context it has; the comments block through render_block twice.
$commented = $make(['post_type' => 'post', 'post_status' => 'private', 'post_title' => 'Zz instances comments', 'post_content' => 'x', 'comment_status' => 'open']);
foreach (['one', 'two'] as $i => $text) {
    wp_insert_comment(['comment_post_ID' => $commented, 'comment_content' => "zz {$text}", 'comment_author' => 'Zz', 'comment_approved' => 1, 'comment_date' => '2026-01-0' . ($i + 1) . ' 10:00:00']);
}
$comments = '<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template --></div><!-- /wp:comments -->';
$GLOBALS['post'] = get_post($commented);
setup_postdata($GLOBALS['post']);
$traced_comments = $traced(static fn () => do_blocks($comments));
$traced_comments['html'] = preg_replace('/comment-\d+/', 'comment-{id}', (string) $traced_comments['html']);
$say('comments with comments', $traced_comments);
$wrap = static fn ($html) => '<div class="zz-wrap">' . $html . '</div>';
add_filter('render_block_core/comments', $wrap);
add_filter('render_block_core/comment-template', $wrap);
$say('comments with comments, each block wrapped', preg_replace('/comment-\d+/', 'comment-{id}', do_blocks($comments)));
remove_filter('render_block_core/comments', $wrap);
remove_filter('render_block_core/comment-template', $wrap);
wp_reset_postdata();

// The inner documents a swapped-in parser reads.
if (!class_exists('Zz_Instances_Parser')) {
    eval('class Zz_Instances_Parser extends WP_Block_Parser { public static $seen = []; public function parse($document) { self::$seen[] = $document; return parent::parse($document); } }');
}
$swap = static fn () => 'Zz_Instances_Parser';
add_filter('block_parser_class', $swap, 20);
wp_cache_set_last_changed('posts');
do_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:pattern {"slug":"zz/instances"} /--><!-- wp:block {"ref":' . $synced . '} /--><!-- wp:template-part {"slug":"zz-instances-part"} /--></div><!-- /wp:group -->' . $loop($post, '<!-- wp:post-content /-->'));
remove_filter('block_parser_class', $swap, 20);
$documents = [];
foreach (['the pattern' => '<!-- wp:paragraph --><p>pattern</p><!-- /wp:paragraph -->', 'the synced pattern' => '<!-- wp:paragraph --><p>synced</p><!-- /wp:paragraph -->', 'the part' => '<!-- wp:paragraph --><p>part</p><!-- /wp:paragraph -->', 'the post' => '<!-- wp:paragraph --><p>content</p><!-- /wp:paragraph -->'] as $label => $document) {
    $documents[$label] = in_array($document, Zz_Instances_Parser::$seen, true);
}
$say('documents a swapped-in parser reads', $documents);
unregister_block_pattern('zz/instances');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
