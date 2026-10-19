<?php
/**
 * The Block Hooks API over a post's content (probe post-block-hooks):
 * apply_block_hooks_to_content_from_post_object wraps the content in the
 * block that stands for its post (core/block for a synced pattern,
 * core/navigation for a menu, core/post-content for any other), that
 * block carrying the post's stored ignored list, so its first_child and
 * last_child hooks apply; its own before and after are asked and nothing
 * goes there; content without blocks is wrapped in a freeform block; the
 * wrapper's ignored list comes back once it has one; without a post the
 * content's own blocks get their hooks. insert_hooked_blocks and
 * set_ignored_hooked_blocks_metadata run hooked_block and
 * hooked_block_{type} for each type (one a filter suppresses goes in
 * nothing and is not marked; one the anchor ignores is filtered, then
 * left out). A post's content on the_content, in a post-content block and
 * a synced pattern's on its render carry their hooked blocks. Same
 * protocol as api-probe.php; the probe's posts are removed at the end.
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
$make = static function (string $type, string $content) use (&$made): int {
    $id = wp_insert_post(['post_type' => $type, 'post_status' => $type === 'attachment' ? 'inherit' : 'publish', 'post_title' => "Zz post hooks {$type}", 'post_content' => $content, 'post_name' => "zz-post-hooks-{$type}"]);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "post-block-hooks: could not make the {$type}\n");
        exit(1);
    }
    $made[] = $id;
    return $id;
};

$hooks = [
    'first' => ['core/post-content' => 'first_child', 'core/block' => 'first_child', 'core/navigation' => 'first_child'],
    'last' => ['core/post-content' => 'last_child', 'core/block' => 'last_child', 'core/navigation' => 'last_child'],
    'outer' => ['core/post-content' => 'before', 'core/block' => 'after', 'core/navigation' => 'before'],
    'para' => ['core/paragraph' => 'after'],
];
foreach ($hooks as $name => $at) {
    register_block_type("zz-post-hooks/{$name}", ['block_hooks' => $at, 'render_callback' => static fn () => "<i>{$name}</i>"]);
}
$asked = [];
$anchors = [];
$listen = static function () use (&$asked, &$anchors): array {
    $types = static function ($list, $position, $anchor, $context) use (&$asked) {
        $asked[] = "{$position} {$anchor} " . (is_object($context) ? $context->post_type : gettype($context));
        return $list;
    };
    $hooked = static function ($parsed, $type, $position, $anchor) use (&$anchors) {
        $anchors[] = [$type, $position, $anchor['blockName'] ?? null, array_map(static fn ($block) => $block['blockName'] ?? null, $anchor['innerBlocks'] ?? []), $anchor['innerContent'] ?? null, $anchor['attrs'] ?? null];
        return $parsed;
    };
    add_filter('hooked_block_types', $types, 10, 4);
    add_filter('hooked_block', $hooked, 10, 4);
    return [$types, $hooked];
};
$quiet = static function (array $listeners): void {
    remove_filter('hooked_block_types', $listeners[0], 10);
    remove_filter('hooked_block', $listeners[1], 10);
};

// Each post type's wrapper, through each callback, and with an ignored list stored.
$paragraph = '<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph -->';
$ids = [];
foreach (['post', 'page', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'attachment'] as $type) {
    $ids[$type] = $make($type, $paragraph);
    foreach (['insert_hooked_blocks', 'set_ignored_hooked_blocks_metadata', 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata'] as $callback) {
        [$asked, $anchors] = [[], []];
        $listeners = $listen();
        $root = 'unset';
        $result = apply_block_hooks_to_content_from_post_object($paragraph, get_post($ids[$type]), $callback, $root);
        $quiet($listeners);
        $say("{$type}: {$callback}", ['result' => $result, 'root' => $root, 'asked' => $asked, 'anchors' => $anchors]);
    }
    update_post_meta($ids[$type], '_wp_ignored_hooked_blocks', wp_json_encode(['zz-post-hooks/first']));
    $root = 'unset';
    $say("{$type}: an ignored list stored", [apply_block_hooks_to_content_from_post_object($paragraph, get_post($ids[$type]), 'insert_hooked_blocks', $root), $root]);
    delete_post_meta($ids[$type], '_wp_ignored_hooked_blocks');
}

// Contents of other shapes, and no post.
foreach ([
    'two paragraphs' => $paragraph . '<!-- wp:paragraph --><p>b</p><!-- /wp:paragraph -->',
    'two with a gap' => $paragraph . "\n\n" . '<!-- wp:paragraph --><p>b</p><!-- /wp:paragraph -->',
    'nothing' => '',
    'classic text' => 'just <b>text</b>',
    'text around a block' => 'hi ' . $paragraph . ' bye',
] as $label => $content) {
    [$asked, $anchors] = [[], []];
    $listeners = $listen();
    $root = 'unset';
    $result = apply_block_hooks_to_content_from_post_object($content, get_post($ids['post']), 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata', $root);
    $quiet($listeners);
    $say("a post's content: {$label}", ['result' => $result, 'root' => $root, 'asked' => $asked, 'anchors' => $anchors]);
}
[$asked, $anchors] = [[], []];
$listeners = $listen();
$say('no post', [apply_block_hooks_to_content_from_post_object($paragraph, 999999999), $asked]);
$quiet($listeners);

// What the hooked_block filters make of a type, inserting and marking.
$calls = [];
$filters = [
    'hooked_block' => static function ($parsed, $type) use (&$calls) {
        $calls[] = "hooked_block {$type}";
        return $parsed;
    },
    'hooked_block_zz/a' => static function ($parsed) use (&$calls) {
        $calls[] = 'a';
        return $parsed;
    },
    'hooked_block_zz/b' => static function ($parsed) use (&$calls) {
        $calls[] = 'b';
        return null;
    },
    'hooked_block_zz/c' => static function ($parsed) use (&$calls) {
        $calls[] = 'c';
        $parsed['attrs']['x'] = 1;
        return $parsed;
    },
];
foreach ($filters as $hook => $filter) {
    add_filter($hook, $filter, 10, 2);
}
$hooked = ['core/paragraph' => ['after' => ['zz/a', 'zz/b', 'zz/c']]];
foreach (['insert_hooked_blocks', 'set_ignored_hooked_blocks_metadata', 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata'] as $callback) {
    foreach (['no ignored list' => [], 'a ignored' => ['metadata' => ['ignoredHookedBlocks' => ['zz/a']]]] as $label => $attrs) {
        $anchor = ['blockName' => 'core/paragraph', 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '<p>a</p>', 'innerContent' => ['<p>a</p>']];
        $calls = [];
        $markup = match ($callback) {
            'insert_hooked_blocks' => insert_hooked_blocks($anchor, 'after', $hooked, null),
            'set_ignored_hooked_blocks_metadata' => set_ignored_hooked_blocks_metadata($anchor, 'after', $hooked, null),
            default => insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata($anchor, 'after', $hooked, null),
        };
        $say("{$callback}, {$label}", ['markup' => $markup, 'attrs' => $anchor['attrs'], 'calls' => $calls]);
    }
}
foreach ($filters as $hook => $filter) {
    remove_filter($hook, $filter, 10);
}

// Rendered: a post's content on the_content and in a post-content block, a synced pattern's.
$GLOBALS['post'] = get_post($ids['post']);
setup_postdata($GLOBALS['post']);
$say('the_content', apply_filters('the_content', $paragraph));
wp_reset_postdata();
wp_cache_set_last_changed('posts');
$say('a post-content block', (string) preg_replace('/\bpost-\d+\b/', 'post-{id}', do_blocks('<!-- wp:query {"queryId":95,"query":{"perPage":1,"postType":"post","include":[' . $ids['post'] . '],"inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-content /--><!-- /wp:post-template --></div><!-- /wp:query -->')));
$say('a synced pattern', do_blocks('<!-- wp:block {"ref":' . $ids['wp_block'] . '} /-->'));

foreach (array_keys($hooks) as $name) {
    unregister_block_type("zz-post-hooks/{$name}");
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
