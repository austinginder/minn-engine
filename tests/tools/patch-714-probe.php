<?php
/**
 * The behaviour WordPress's 7.1.4 security release changed, where Minn
 * reimplements it (probe patch-714): which whitespace a block delimiter may
 * hold, kses and the Interactivity API's bindings (a binding that would set
 * a value kses checks, an event handler, an object's data or type), the two
 * kses helpers the release added, the labels of the Categories, Comments
 * Pagination and Post Navigation Link blocks, Image and Playlist content
 * holding replacement tokens ($0, \1), the notes a comment moderator may
 * touch, and the upgrader's working folder. The probe's users, posts,
 * comments and files are its own and are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => [], 'posts' => [], 'comments' => [], 'roles' => []];
$held = ['page_comments' => get_option('page_comments'), 'comments_per_page' => get_option('comments_per_page')];
register_shutdown_function(static function () use (&$made, $held): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
    foreach ($made['roles'] as $role) {
        remove_role($role);
    }
    foreach ($held as $name => $value) {
        update_option($name, $value);
    }
});
// Leftovers of a run that died before its shutdown.
foreach (get_posts(['post_type' => 'any', 'post_status' => 'any', 'name' => 'zz-patch-714', 'numberposts' => -1, 'fields' => 'ids']) as $stale) {
    wp_delete_post($stale, true);
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach (['zz-patch-714-moderator', 'zz-patch-714-author'] as $login) {
    $stale = get_user_by('login', $login);
    if ($stale) {
        wp_delete_user($stale->ID);
    }
}
remove_role('zz_patch_714_moderator');

// What a section was told it did wrong (the message without its markup and the version it names).
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong) {
    $wrong[] = [$function, wp_strip_all_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$told = static function () use (&$wrong): array {
    $said = $wrong;
    $wrong = [];
    return $said;
};

$guard = static function (callable $run) {
    try {
        return $run();
    } catch (\Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$names = static function (array $blocks) use (&$names): array {
    $out = [];
    foreach ($blocks as $block) {
        $out[] = [$block['blockName'], $block['attrs'], $block['innerHTML'], $names($block['innerBlocks'])];
    }
    return $out;
};

// Block delimiters: the whitespace a delimiter may hold, each character around the name, the attributes and the close.
$spaces = ['space' => ' ', 'tab' => "\t", 'lf' => "\n", 'cr' => "\r", 'ff' => "\f", 'vt' => "\v", 'nul' => "\0", 'nbsp' => "\u{00A0}", 'em space' => "\u{2003}", 'ideographic' => "\u{3000}", 'bom' => "\u{FEFF}", 'nbsp byte' => "\xA0", 'nel' => "\u{0085}"];
$delimiters = [];
foreach ($spaces as $name => $c) {
    $delimiters[$name] = [
        $names(parse_blocks("<!--{$c}wp:paragraph{$c}-->\n<p>a</p>\n<!--{$c}/wp:paragraph{$c}-->")),
        $names(parse_blocks("<!-- wp:paragraph{$c}{\"className\":\"x\"}{$c}-->\n<p>b</p>\n<!-- /wp:paragraph -->")),
        $names(parse_blocks("<!-- wp:separator{$c}/-->")),
        $names(parse_blocks("<!-- wp:paragraph -->\n<p>c</p>\n<!-- /wp:paragraph{$c}-->")),
    ];
}
$say('block delimiters: whitespace', $delimiters);
$say('block delimiters: rendered', array_map(static fn ($c) => do_blocks("<!--{$c}wp:html{$c}--><b>x</b><!--{$c}/wp:html{$c}-->"), $spaces));

// kses on the Interactivity API's directives.
$kses = [
    '<a href="https://example.invalid/" data-wp-bind--href="state.url">a</a>',
    '<a data-wp-bind--href="state.url">a</a>',
    '<a data-wp-bind--HREF="state.url">a</a>',
    '<a data-wp-bind--title="state.title">a</a>',
    '<img src="https://example.invalid/a.png" data-wp-bind--src="state.src" alt="">',
    '<img data-wp-bind--srcset="state.src" alt="">',
    '<div data-wp-bind--onclick="state.code">d</div>',
    '<div data-wp-bind--style="state.css">d</div>',
    '<div data-wp-style--background-image="state.css">d</div>',
    '<div data-wp-on--click="actions.go">d</div>',
    '<div data-wp-context=\'{"__proto__":{"polluted":1}}\'>d</div>',
    '<object data="https://example.invalid/a.pdf" type="application/pdf" data-wp-bind--data="state.url"></object>',
    '<object data="https://example.invalid/a.pdf" type="application/pdf" data-wp-bind--type="state.type"></object>',
    '<area href="https://example.invalid/" data-wp-bind--href="state.url">',
    '<blockquote cite="https://example.invalid/" data-wp-bind--cite="state.url">q</blockquote>',
    '<del datetime="2026-01-01" data-wp-bind--datetime="state.when">d</del>',
    '<form action="/x" data-wp-bind--action="state.url"></form>',
    '<span data-wp-bind--class="state.c" data-wp-class--x="state.on">s</span>',
    '<a href="https://example.invalid/" data-wp-bind--href--x="state.url">a</a>',
    '<a href="https://example.invalid/" data-wp-bind="state.url">a</a>',
];
$told();
$say('kses: directives in post content', array_map('wp_kses_post', $kses));
$say('kses: directives for a comment', array_map(static fn ($html) => wp_kses_data($html), $kses));
$allowed = wp_kses_allowed_html('post');
$say('kses: the restricted-values helper', array_map(static fn ($attributes) => _wp_kses_restricts_attribute_values($attributes), [
    $allowed['a'] ?? [], $allowed['img'] ?? [], $allowed['div'] ?? [], $allowed['object'] ?? [], $allowed['span'] ?? [], [], true, ['href' => true], ['href' => ['values' => ['x']]], ['data-*' => true],
    ['x' => ['required' => true]], ['x' => ['maxlen' => 5]], ['x' => ['minlen' => 1]], ['x' => ['maxval' => 5]], ['x' => ['valueless' => 'y']], ['x' => ['value_callback' => 'is_string']], ['x' => ['values' => []]], ['x' => ['values' => 'x']], ['x' => ['value' => 'x']], ['x' => []], ['x' => false], ['x' => 'values'], ['x' => true, 'y' => ['values' => ['z']]],
    ['x' => ['minval' => 1]], ['x' => ['foo' => 1]], ['x' => ['VALUES' => ['y']]], ['x' => ['required' => true, 'valueless' => 'y']], ['x' => (object) ['values' => ['y']]], 'not a list',
]));
$binds = [];
foreach (['a', 'img', 'div', 'object', 'area', 'blockquote', 'span'] as $tag) {
    foreach (['data-wp-bind--href', 'data-wp-bind--src', 'data-wp-bind--title', 'data-wp-bind--data', 'data-wp-bind--type', 'data-wp-bind--cite', 'data-wp-bind--onclick', 'data-wp-bind--style', 'data-wp-bind--HREF', 'data-wp-bind--href--x', 'data-wp-bind', 'data-wp-on--click', 'data-wp-class--href'] as $directive) {
        $binds["{$tag} {$directive}"] = _wp_kses_directive_binds_value_restricted_attribute($directive, $allowed[$tag] ?? []);
    }
}
$rules = ['href' => ['values' => ['x']], 'title' => true, 'data-*' => true, 'aria-label' => true];
foreach (['data-wp-bind--href', 'data-wp-bind--title', 'data-wp-bind--TITLE', 'data-wp-bind--title--x', 'data-wp-bind--x', 'data-wp-bind--data-foo', 'data-wp-bind--aria-label', 'data-wp-bind--', 'data-wp-bind---title', 'DATA-WP-BIND--x', 'data-wp-bind-x', 'data-wp-bind--hidden', ' data-wp-bind--x', 'data-wp-bindings--x'] as $directive) {
    $binds["rules {$directive}"] = _wp_kses_directive_binds_value_restricted_attribute($directive, $rules);
}
foreach (['data-wp-bind--x' => ['x' => ['values' => ['y']], 'y' => true], 'data-wp-bind--y' => ['x' => ['values' => ['y']], 'y' => true], 'data-wp-bind--z' => [], 'data-wp-bind--z ' => true, 'data-wp-bind--r' => ['x' => ['values' => ['y']], 'r' => ['required' => true]], 'data-wp-bind--v' => ['x' => ['values' => ['y']], 'v' => ['valueless' => 'y']], 'data-wp-bind--f' => ['x' => ['values' => ['y']], 'f' => false], 'data-wp-bind--e' => ['x' => ['values' => ['y']], 'e' => []], 'data-wp-bind--data-q' => ['x' => ['values' => ['y']]], 'data-wp-bind--aria-q' => ['x' => ['values' => ['y']], 'aria-*' => true], 'data-wp-bind--data-q ' => ['x' => ['values' => ['y']], 'data-*' => false]] as $directive => $given) {
    $binds['given ' . $directive . ' ' . json_encode($given)] = _wp_kses_directive_binds_value_restricted_attribute($directive, $given);
}
$say('kses: the binding helper', $binds);
$uploads = wp_upload_dir()['baseurl'];
$say('kses: bindings on an object kses keeps', array_map(static fn ($html) => str_replace($uploads, '{uploads-url}', wp_kses_post(str_replace('{uploads-url}', $uploads, $html))), [
    '<object data="{uploads-url}/a.pdf" type="application/pdf"></object>',
    '<object data="{uploads-url}/a.pdf" type="application/pdf" data-wp-bind--data="state.url"></object>',
    '<object data="{uploads-url}/a.pdf" type="application/pdf" data-wp-bind--type="state.type"></object>',
    '<object data="{uploads-url}/a.pdf" type="application/pdf" data-wp-bind--title="state.t" data-wp-bind--class="state.c" data-wp-bind--href="state.u" data-wp-on--click="actions.go" data-x="1"></object>',
    '<object data="{uploads-url}/a.pdf" type="application/pdf" data-wp-bind--TITLE="state.t" data-wp-bind--title--x="state.t"></object>',
]));

// The Interactivity API's directive pass on the server.
wp_interactivity_state('zzpatch', ['url' => 'javascript:alert(1)', 'safe' => 'https://example.invalid/b', 'type' => 'text/html', 'code' => 'alert(1)', 'title' => 'T', 'css' => 'color:red', 'odd' => 'https://example.invalid/?a=<b>&c="d"', 'empty' => '', 'relative' => '/x?y=1']);
$process = static fn (string $inner) => wp_interactivity_process_directives('<div data-wp-interactive="zzpatch">' . $inner . '</div>');
$say('interactivity: server bindings', array_map($process, [
    '<a href="https://example.invalid/a" data-wp-bind--href="state.url">a</a>',
    '<a data-wp-bind--href="state.safe">a</a>',
    '<a data-wp-bind--href="state.url">a</a>',
    '<a href="https://example.invalid/a" data-wp-bind--href="state.odd">a</a>',
    '<a href="https://example.invalid/a" data-wp-bind--href="state.empty">a</a>',
    '<a href="https://example.invalid/a" data-wp-bind--href="state.relative">a</a>',
    '<img src="https://example.invalid/a.png" data-wp-bind--src="state.url">',
    '<a data-wp-bind--title="state.url">a</a>',
    '<object data-wp-bind--DATA="state.safe"></object>',
    '<OBJECT data-wp-bind--type="state.type"></OBJECT>',
    '<object data="https://example.invalid/c" data-wp-bind--data="state.safe" title="t"></object>',
    '<a data-wp-bind--title="state.title">a</a>',
    '<div data-wp-bind--onclick="state.code">d</div>',
    '<div data-wp-bind--ONCLICK="state.code">d</div>',
    '<div data-wp-bind--onMouseOver="state.code">d</div>',
    '<div data-wp-bind--on="state.code">d</div>',
    '<object data-wp-bind--data="state.safe"></object>',
    '<object data-wp-bind--type="state.type"></object>',
    '<object data-wp-bind--title="state.title"></object>',
    '<embed data-wp-bind--src="state.safe">',
    '<iframe data-wp-bind--src="state.safe"></iframe>',
    '<div data-wp-style--color="state.css">d</div>',
    '<div data-wp-context=\'{"__proto__":{"polluted":1},"x":1}\' data-wp-bind--title="context.x">d</div>',
    '<div data-wp-context=\'{"constructor":{"prototype":{"polluted":1}}}\' data-wp-text="context.constructor">d</div>',
]));
$say('interactivity: what the server pass was told', $told());

// The Categories block's dropdown label.
$say('categories: the dropdown label', array_map(static fn ($label) => render_block(['blockName' => 'core/categories', 'attrs' => ['displayAsDropdown' => true, 'showLabel' => true, 'label' => $label], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]), [
    'Pick one',
    'Pick %s or %1$s or %d %%',
    '<b>Bold</b> %s<script>x</script>',
]));

// Post Navigation Link and Comments Pagination labels.
$author = wp_insert_user(['user_login' => 'zz-patch-714-author', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-patch-714-author@example.invalid', 'role' => 'author']);
$made['users'][] = $author;
$posts = [];
foreach (['2001-01-01 00:00:01', '2001-01-01 00:00:02', '2001-01-01 00:00:03'] as $date) {
    $posts[] = $made['posts'][] = wp_insert_post(['post_title' => 'Zz patch 714', 'post_name' => 'zz-patch-714', 'post_status' => 'publish', 'post_author' => $author, 'post_date' => $date, 'post_content' => 'x']);
}
$labels = ['Next <b>post</b>', '<img src=x onerror=alert(1)>', 'A %s label', '"quoted" & <i>em</i>'];
$navigation = [];
// The block links from the main query's single post.
wp(['p' => $posts[1]]);
the_post();
foreach ($labels as $label) {
    foreach (['next', 'previous'] as $type) {
        $navigation[] = render_block(['blockName' => 'core/post-navigation-link', 'attrs' => ['type' => $type, 'label' => $label, 'showTitle' => false], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
    }
}
foreach ([['showTitle' => true], ['showTitle' => true, 'linkLabel' => true], ['arrow' => 'arrow'], ['arrow' => 'chevron', 'type' => 'previous'], ['arrow' => 'chevron'], ['arrow' => 'arrow', 'type' => 'previous'], ['showTitle' => true, 'label' => ''], ['label' => '']] as $more) {
    $navigation[] = render_block(['blockName' => 'core/post-navigation-link', 'attrs' => $more + ['type' => 'next', 'label' => 'Lab<b>el</b>'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
}
wp_reset_postdata();
$say('post navigation link: labels', array_map(static fn ($html) => str_replace(array_map('get_permalink', $posts), ['{p0}', '{p1}', '{p2}'], $html), $navigation));
update_option('page_comments', 1);
update_option('comments_per_page', 1);
foreach ([1, 2, 3, 4, 5] as $n) {
    $made['comments'][] = wp_insert_comment(['comment_post_ID' => $posts[0], 'comment_content' => "Zz {$n}", 'comment_approved' => 1, 'comment_author' => 'Zz', 'comment_date' => "2001-01-02 00:00:0{$n}"]);
}
// A middle page of the main query's post.
wp(['p' => $posts[0]]);
the_post();
set_query_var('cpage', 3);
$pagination = [];
foreach ($labels as $label) {
    // The older-comments link only: whether the newer one shows outside a comment loop is another question than its label.
    foreach (['core/comments-pagination-previous'] as $name) {
        $pagination[] = $guard(static fn () => (new WP_Block(['blockName' => $name, 'attrs' => ['label' => $label], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], ['postId' => $posts[0], 'comments/paginationArrow' => 'none']))->render());
    }
}
wp_reset_postdata();
$say('comments pagination: labels', array_map(static fn ($html) => is_string($html) ? str_replace(get_permalink($posts[0]), '{p0}', $html) : $html, $pagination));

// Image and Playlist content holding replacement tokens.
$tokens = 'x $0 \\0 \\1 ${1} $1 y';
// The lightbox numbers each image with a fresh unique id.
$unique = static fn (string $html): string => (string) preg_replace('/\b[0-9a-f]{13}\b/', '{uniqid}', $html);
$say('image: replacement tokens in content', array_map(static fn ($markup) => $unique(do_blocks($markup)), [
    '<!-- wp:image {"id":999999,"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="https://example.invalid/a.jpg" alt="' . $tokens . '" class="wp-image-999999"/><figcaption class="wp-element-caption">' . $tokens . '</figcaption></figure><!-- /wp:image -->',
    '<!-- wp:image {"lightbox":{"enabled":true},"id":999999} --><figure class="wp-block-image"><img src="https://example.invalid/b.jpg" alt="' . $tokens . '" class="wp-image-999999" title="' . $tokens . '"/></figure><!-- /wp:image -->',
    '<!-- wp:image {"className":"z$0 \\\\1"} --><figure class="wp-block-image z$0"><img src="https://example.invalid/c.jpg" alt=""/></figure><!-- /wp:image -->',
]));
$say('playlist: replacement tokens in content', array_map('do_blocks', [
    '<!-- wp:playlist --><figure class="wp-block-playlist"><figcaption>' . $tokens . '</figcaption></figure><!-- /wp:playlist -->',
    '<!-- wp:playlist {"className":"p$0"} --><figure class="wp-block-playlist p$0"><ol class="wp-block-playlist__tracks"><li>' . $tokens . '</li></ol></figure><!-- /wp:playlist -->',
]));

// Notes: a role that moderates comments without editing the note's post.
add_role('zz_patch_714_moderator', 'Zz moderator', ['read' => true, 'moderate_comments' => true]);
$made['roles'][] = 'zz_patch_714_moderator';
$moderator = wp_insert_user(['user_login' => 'zz-patch-714-moderator', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-patch-714-moderator@example.invalid', 'role' => 'zz_patch_714_moderator']);
$made['users'][] = $moderator;
$note = wp_insert_comment(['comment_post_ID' => $posts[2], 'comment_content' => 'Zz note', 'comment_type' => 'note', 'comment_approved' => 1, 'user_id' => $author, 'comment_author' => 'Zz']);
$made['comments'][] = $note;
$comment = wp_insert_comment(['comment_post_ID' => $posts[2], 'comment_content' => 'Zz comment', 'comment_approved' => 1, 'comment_author' => 'Zz']);
$made['comments'][] = $comment;
$GLOBALS['zz_patch_714_comment'] = $comment;
$rest = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    $data = $response->get_data();
    return [$response->get_status(), is_array($data) && isset($data['code']) ? $data['code'] : (is_array($data) && isset($data['id']) ? 'ok' : (is_array($data) ? count($data) : $data))];
};
$previous = get_current_user_id();
$asks = static fn () => [
    'read note' => $rest('GET', "/wp/v2/comments/{$note}"),
    'read note to edit' => $rest('GET', "/wp/v2/comments/{$note}", ['context' => 'edit']),
    'list notes' => $rest('GET', '/wp/v2/comments', ['type' => 'note', 'post' => $posts[2]]),
    'list notes to edit' => $rest('GET', '/wp/v2/comments', ['type' => 'note', 'post' => $posts[2], 'context' => 'edit']),
    'list every note to edit' => $rest('GET', '/wp/v2/comments', ['type' => 'note', 'context' => 'edit']),
    'list comments to edit' => $rest('GET', '/wp/v2/comments', ['post' => $posts[2], 'context' => 'edit']),
    'read comment to edit' => $rest('GET', "/wp/v2/comments/{$GLOBALS['zz_patch_714_comment']}", ['context' => 'edit']),
];
foreach (['the post\'s author' => $author, 'an administrator' => 1] as $who => $user) {
    wp_set_current_user($user);
    $say("notes: {$who}", $asks() + ['edit note' => $rest('POST', "/wp/v2/comments/{$note}", ['content' => "Zz changed by {$who}"]), 'note after' => get_comment($note)->comment_content, 'edit comment' => $rest('POST', "/wp/v2/comments/{$comment}", ['content' => "Zz comment by {$who}"])]);
}
wp_set_current_user($moderator);
$say('notes: a comment moderator reading', $asks());
$say('notes: a comment moderator who cannot edit the post', [
    'read note' => $rest('GET', "/wp/v2/comments/{$note}", ['type' => 'note', 'context' => 'edit']),
    'list notes' => $rest('GET', '/wp/v2/comments', ['type' => 'note', 'post' => $posts[2], 'context' => 'edit']),
    'edit note' => $rest('POST', "/wp/v2/comments/{$note}", ['content' => 'Zz changed']),
    'read comment' => $rest('GET', "/wp/v2/comments/{$comment}", ['context' => 'edit']),
    'edit comment' => $rest('POST', "/wp/v2/comments/{$comment}", ['content' => 'Zz changed too']),
    'delete note' => $rest('DELETE', "/wp/v2/comments/{$note}", ['force' => true]),
    'note after' => get_comment($note) ? get_comment($note)->comment_content : null,
]);
wp_set_current_user($previous);

// The upgrader's working folder: its name and the folder's index.php.
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$say('upgrader: the working folder', $guard(static function () {
    WP_Filesystem();
    $zip = wp_tempnam('zz-patch-714.zip');
    $archive = new ZipArchive();
    $archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('zz-patch-714/zz.php', "<?php\n");
    $archive->close();
    $named = dirname($zip) . '/zz-patch-714-package.zip';
    rename($zip, $named);
    // A skin that says nothing: the stock one's feedback flushes every output buffer.
    $quiet = new class (['url' => '', 'title' => '']) extends WP_Upgrader_Skin {
        public function feedback($feedback, ...$args)
        {
        }
    };
    $upgrader = new WP_Upgrader($quiet);
    $upgrader->init();
    $working = $upgrader->unpack_package($named, true);
    $result = is_wp_error($working) ? ['error' => $working->get_error_code()] : [
        'parent' => str_replace(WP_CONTENT_DIR, '{content}', dirname($working)),
        'name' => preg_replace('/[A-Za-z0-9]{16}/', '{16}', basename($working)),
        'name length' => strlen(basename($working)),
        'holds' => array_values(array_diff(scandir($working) ?: [], ['.', '..'])),
        'index.php in upgrade' => file_exists(WP_CONTENT_DIR . '/upgrade/index.php'),
    ];
    if (!is_wp_error($working)) {
        global $wp_filesystem;
        $wp_filesystem->delete($working, true);
    }
    @unlink($named);
    return $result;
}));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
