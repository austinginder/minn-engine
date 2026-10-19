<?php
/**
 * When kses asks for the HTML a context allows (probe kses-allowed-asks):
 * wp_kses_allowed_html once for each element tag the content holds, opening
 * or closing, and never for text, comments or a lone bracket; an allowed
 * list given as an array asks nothing. A term's name, sanitized on its way
 * into a query, holds no tags, so it asks nothing. A gallery asks once it
 * has images, past post_gallery, and keeps an item, caption or icon tag only
 * when a post may hold it (tag_escape first), else dl, dd or dt. The Custom
 * HTML widget's note names the unsafe tags a post may not hold. Each pass
 * asks for the block parser, and passes a kept comment's body again. Same
 * protocol as api-probe.php; the probe's post and attachment are removed at
 * the end.
 */

$log = [];
foreach (['plain', 'a > b', 'a < b', 'a <b', '<b>x</b>', '</b>', '<b>a</b><i>b</i>', '<!-- c -->', 'x &amp; y', '<1>', '< b>', '<a title="x>y">z</a>', '<b', "<p>\n<br />\n</p>"] as $content) {
    $asks = 0;
    $count = static function ($html) use (&$asks) {
        $asks++;
        return $html;
    };
    add_filter('wp_kses_allowed_html', $count);
    $filtered = wp_filter_kses($content);
    $byFilter = $asks;
    $asks = 0;
    $post = wp_kses($content, 'post');
    $byPost = $asks;
    $asks = 0;
    $listed = wp_kses($content, ['b' => []]);
    $byList = $asks;
    remove_filter('wp_kses_allowed_html', $count);
    $log[] = [$content, ['asks' => [$byFilter, $byPost, $byList], 'out' => [$filtered, $post, $listed]]];
}
$asks = 0;
$count = static function ($html) use (&$asks) {
    $asks++;
    return $html;
};
add_filter('wp_kses_allowed_html', $count);
$name = sanitize_term_field('name', 'Zz <em>Name</em> & more', 0, 'category', 'db');
remove_filter('wp_kses_allowed_html', $count);
$log[] = ['a term name for the database', ['asks' => $asks, 'out' => $name]];

// Each pass asks for the block parser (wp_pre_kses_block_attributes reads the blocks with it), and a comment kses
// keeps has its body passed again, its own pre_kses and parser heard; a parser a plugin swaps in has its blocks
// written back as it read them.
foreach (['plain', '<!-- c -->', '<!-- wp:paragraph {"x":"<b>y</b>"} --><p>z</p><!-- /wp:paragraph -->', '<!-- wp:paragraph --><p>z</p><!-- /wp:paragraph -->', 'a <!-- x <b>y</b> --> b', '<!-- <script>alert(1)</script> <b>ok</b> -->', '<!-- a -- b -->'] as $content) {
    $passes = [];
    $parser = static function ($class) use (&$passes) {
        $passes[] = 'parser';
        return $class;
    };
    $pre = static function ($html) use (&$passes) {
        $passes[] = 'pre_kses: ' . $html;
        return $html;
    };
    add_filter('block_parser_class', $parser);
    add_filter('pre_kses', $pre, 1);
    $out = wp_kses($content, 'post');
    remove_filter('pre_kses', $pre, 1);
    remove_filter('block_parser_class', $parser);
    $log[] = ["passes: {$content}", ['passes' => $passes, 'out' => $out]];
}
if (!class_exists('Zz_Kses_Asks_Parser')) {
    eval('class Zz_Kses_Asks_Parser extends WP_Block_Parser { public function parse($document) { $blocks = parent::parse($document); foreach ($blocks as &$block) { if (!empty($block["blockName"])) { $block["attrs"]["zzAdded"] = "yes"; } } return $blocks; } }');
}
$swap = static fn () => 'Zz_Kses_Asks_Parser';
add_filter('block_parser_class', $swap);
foreach (['<!-- wp:paragraph {"a":"b"} --><p>z</p><!-- /wp:paragraph -->', 'plain <b>x</b>', "a\n\n<!-- wp:separator /-->\n\nb"] as $content) {
    $log[] = ["a swapped parser: {$content}", [wp_kses($content, 'post'), wp_kses_post($content), filter_block_content($content)]];
}
remove_filter('block_parser_class', $swap);

// Galleries.
$post = wp_insert_post(['post_type' => 'post', 'post_title' => 'Zz kses gallery', 'post_status' => 'publish']);
$image = is_int($post) && $post > 1 ? wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => 'zz', 'post_excerpt' => 'zz caption', 'post_status' => 'inherit', 'guid' => 'https://example.com/zz-kses.jpg'], 'zz/zz-kses.jpg', $post) : 0;
register_shutdown_function(static function () use ($post, $image): void {
    if (is_int($image) && $image > 1) {
        wp_delete_attachment($image, true);
    }
    if (is_int($post) && $post > 1) {
        wp_delete_post($post, true);
    }
});
if (!is_int($image) || $image <= 1) {
    fwrite(STDERR, "kses-allowed-asks: could not make the gallery's image\n");
    exit(1);
}
$tagsOf = static function (string $html): array {
    preg_match_all('/<(\/?[a-zA-Z0-9]+)[\s>]/', $html, $m);
    return array_values(array_unique($m[1]));
};
$drop = static function ($html, $context) {
    if ($context === 'post') {
        unset($html['li'], $html['p']);
    }
    return $html;
};
foreach ([
    'unsafe and unknown tags' => [['itemtag' => 'script', 'icontag' => 'zzx', 'captiontag' => 'section'], false],
    'tags a post holds' => [['itemtag' => 'li', 'icontag' => 'span', 'captiontag' => 'p'], false],
    'tags escaped first' => [['itemtag' => 'Div', 'icontag' => 'b r', 'captiontag' => ''], false],
    'tags the list no longer holds' => [['itemtag' => 'li', 'icontag' => 'span', 'captiontag' => 'p'], true],
] as $label => [$tags, $narrowed]) {
    $asks = 0;
    $escaped = [];
    $count = static function ($html) use (&$asks) {
        $asks++;
        return $html;
    };
    $escape = static function ($safe, $raw) use (&$escaped) {
        $escaped[] = $raw;
        return $safe;
    };
    add_filter('wp_kses_allowed_html', $count);
    add_filter('tag_escape', $escape, 10, 2);
    if ($narrowed) {
        add_filter('wp_kses_allowed_html', $drop, 10, 2);
    }
    $html = gallery_shortcode(['ids' => (string) $image] + $tags);
    $empty = gallery_shortcode(['ids' => '999999999'] + $tags);
    remove_filter('wp_kses_allowed_html', $drop, 10);
    remove_filter('tag_escape', $escape, 10);
    remove_filter('wp_kses_allowed_html', $count);
    $log[] = ["gallery: {$label}", ['asks' => $asks, 'escaped' => $escaped, 'tags' => $tagsOf($html), 'empty' => $empty]];
}
$answered = static fn () => 'zz';
$asks = 0;
$count = static function ($html) use (&$asks) {
    $asks++;
    return $html;
};
add_filter('wp_kses_allowed_html', $count);
add_filter('post_gallery', $answered);
gallery_shortcode(['ids' => (string) $image]);
remove_filter('post_gallery', $answered);
remove_filter('wp_kses_allowed_html', $count);
$log[] = ['gallery: post_gallery answers first', ['asks' => $asks]];

// The Custom HTML widget's note, for a user who may not post unfiltered HTML.
$note = static function (): string {
    ob_start();
    WP_Widget_Custom_HTML::render_control_template_scripts();
    $template = (string) ob_get_clean();
    $start = (int) strpos($template, '</textarea>');
    return substr($template, $start, (int) strpos($template, 'code-editor-error-container') - $start);
};
$allow = static function (array $tags) {
    return static function ($html, $context) use ($tags) {
        if ($context === 'post') {
            foreach ($tags as $tag) {
                $html[$tag] = [];
            }
        }
        return $html;
    };
};
$log[] = ['widget note', $note()];
foreach (['some unsafe tags allowed' => ['script', 'form'], 'every unsafe tag allowed' => ['script', 'iframe', 'form', 'input', 'style']] as $label => $tags) {
    $more = $allow($tags);
    add_filter('wp_kses_allowed_html', $more, 10, 2);
    $log[] = ["widget note: {$label}", $note()];
    remove_filter('wp_kses_allowed_html', $more, 10);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
