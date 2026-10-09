<?php
/**
 * The streaming block processor (probe block-processor): WP_Block_Processor
 * walked token by token over a set of block documents (nested, void,
 * namespaced, freeform HTML between, broken JSON, unfinished input,
 * unmatched closers, names it refuses), what it reports at each step
 * (delimiter type, block type, closing flag, depth, breadcrumbs,
 * attributes, the span, HTML content), the narrower walks (next_block,
 * next_delimiter), extracting a whole block, and the static name helpers.
 * Same protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$try = static function (Closure $call) {
    try {
        return $call();
    } catch (Throwable $e) {
        return [get_class($e), $e->getMessage()];
    }
};
$step = static function (WP_Block_Processor $processor) use ($try): array {
    $span = $processor->get_span();
    return [
        'type' => $processor->get_delimiter_type(),
        'block' => $processor->get_block_type(),
        'printable' => $processor->get_printable_block_type(),
        'closing' => $processor->has_closing_flag(),
        'html' => $processor->is_html(),
        'text' => $processor->is_non_whitespace_html(),
        'content' => $processor->get_html_content(),
        'depth' => $processor->get_depth(),
        'crumbs' => $processor->get_breadcrumbs(),
        'attributes' => $try(static fn () => $processor->allocate_and_return_parsed_attributes()),
        'opens' => [$processor->opens_block(), $processor->opens_block('paragraph'), $processor->opens_block('core/group')],
        'is' => [$processor->is_block_type('paragraph'), $processor->is_block_type('core/paragraph'), $processor->is_block_type('*'), $processor->is_block_type('core/freeform')],
        'span' => $span === null ? null : [$span->start, $span->length],
        'errors' => [$processor->get_last_error(), $processor->get_last_json_error()],
    ];
};
$walk = static function (string $text, string $how = 'next_token', ...$args) use ($step): array {
    $processor = new WP_Block_Processor($text);
    $out = [];
    for ($i = 0; $i < 40 && $processor->{$how}(...$args); $i++) {
        $out[] = $step($processor);
    }
    $out[] = ['ended', $processor->get_last_error(), $processor->get_depth(), $processor->get_breadcrumbs(), $processor->get_delimiter_type(), $processor->opens_block(), $processor->is_block_type('*'), $processor->get_printable_block_type(), $processor->is_html(), $processor->get_span() === null];
    return $out;
};

$documents = [
    'plain' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
    'nested' => '<!-- wp:group {"layout":{"type":"flex"}} --><div><!-- wp:paragraph {"align":"center"} --><p>A</p><!-- /wp:paragraph --><!-- wp:image {"id":5} /--></div><!-- /wp:group -->',
    'namespaced' => '<!-- wp:my-plugin/thing {"a":[1,2],"b":"<x>"} /-->',
    'freeform' => "before <!-- wp:spacer /--> middle\n\n<!-- wp:separator --><hr/><!-- /wp:separator --> after",
    'whitespace between' => "<!-- wp:a /-->\n\n<!-- wp:b /-->",
    'broken json' => '<!-- wp:paragraph {"a": } --><p>x</p><!-- /wp:paragraph -->',
    'unfinished' => '<!-- wp:paragraph --><p>x</p><!-- /wp:para',
    'unclosed' => '<!-- wp:group --><p>x</p>',
    'stray closer' => '<!-- /wp:group -->text',
    'mismatched closer' => '<!-- wp:group --><!-- /wp:columns -->',
    'refused names' => '<!-- wp:Paragraph --><!-- wp:core/ --><!-- wp:9lives /-->',
    'ordinary comment' => '<!-- just a comment --><!-- wp:paragraph -->x<!-- /wp:paragraph -->',
    'tight and spaced' => '<!--wp:paragraph--><!-- wp:paragraph   {"x":1}   /-->',
    'empty' => '',
    'core prefix' => '<!-- wp:core/paragraph --><!-- /wp:core/paragraph -->',
    'closer with void flag' => '<!-- /wp:image /-->',
    'json with tricky strings' => '<!-- wp:code {"content":"} --> not the end"} /-->',
    'unfinished comment' => 'text <!-- wp:para',
    'unfinished json' => '<!-- wp:group {"a":',
    'refused name alone' => '<!-- wp:Paragraph -->',
    'refused name between' => 'text<!-- wp:Paragraph -->more<!-- wp:spacer /-->',
    'refused name inside' => '<!-- wp:paragraph -->x<!-- wp:Bad -->y<!-- /wp:paragraph -->',
    'name shapes' => '<!-- wp:a_b-c /--><!-- wp:ns/a/b /--><!-- wp:para graph --><!-- wp:x /-->',
    'json spacing' => '<!-- wp:paragraph{"a":1} --><!-- wp:paragraph {"a":1}--><!-- wp:x /-->',
    'void flag spacing' => '<!-- wp:paragraph /  --><!-- wp:x /-->',
    'whitespace kinds' => "<!--  wp:a /--><!--\twp:b\t/--><!--\nwp:c\n/--><!-- wp:d /-->",
    'unfinished plain comment' => 'text <!-- just a comment',
    'abrupt comments' => '<!--> <!---> <!-- wp:a /-->',
    'json not an object' => '<!-- wp:a [1,2] /--><!-- wp:b "x" /--><!-- wp:c {} /-->',
    'dashes in comment' => '<!-- wp:a -- /--><!-- wp:b /-->',
    'just html' => '<p>Hello</p>',
    'html then a block' => '<p>Hello</p><!-- wp:a /-->',
    'cut after opener' => 'text <!--',
    'cut in w' => 'text <!-- w',
    'cut in closer' => 'text <!-- /w',
    'block then cut plain comment' => '<!-- wp:a /--> <!-- just a comment',
    'block then cut delimiter' => '<!-- wp:a /--> <!-- wp:b',
    'block then plain comment' => '<!-- wp:a /--> <!-- just a comment -->',
];
foreach ($documents as $label => $text) {
    $say("tokens: {$label}", $walk($text));
}

$nested = $documents['nested'];
$say('narrower walks', [
    $walk($nested, 'next_delimiter'),
    $walk($nested, 'next_delimiter', 'paragraph'),
    $walk($nested, 'next_block'),
    $walk($nested, 'next_block', 'core/image'),
    $walk($documents['freeform'], 'next_block', '*'),
    $walk($documents['freeform'], 'next_delimiter', '*'),
]);

$extract = static function (string $text, int $skip) {
    $processor = new WP_Block_Processor($text);
    for ($i = 0; $i < $skip; $i++) {
        $processor->next_block();
    }
    $first = $processor->extract_full_block_and_advance();
    $after = [$processor->get_delimiter_type(), $processor->get_block_type(), $processor->get_depth()];
    $second = $processor->extract_full_block_and_advance();
    return [$first, $after, $second, $processor->get_last_error()];
};
$say('whole blocks extracted', [
    $extract($nested, 1),
    $extract($nested, 2),
    $extract($documents['freeform'], 1),
    $extract("<!-- wp:a /--><!-- wp:b {\"n\":1} -->in<!-- wp:c /-->side<!-- /wp:b -->tail", 1),
    $extract($documents['unclosed'], 1),
    (static function () use ($documents) {
        $processor = new WP_Block_Processor($documents['freeform']);
        $processor->next_token();
        return [$processor->extract_full_block_and_advance(), $processor->get_delimiter_type()];
    })(),
]);

$say('attributes', (static function () use ($documents, $try) {
    $out = [];
    foreach (['nested', 'namespaced', 'broken json', 'tight and spaced'] as $label) {
        $processor = new WP_Block_Processor($documents[$label]);
        $row = [];
        while ($processor->next_delimiter()) {
            $row[] = [$processor->get_block_type(), $try(static fn () => $processor->get_attributes()), $processor->allocate_and_return_parsed_attributes(), $processor->get_last_json_error()];
        }
        $out[$label] = $row;
    }
    return $out;
})());

$say('json parsed when asked', (static function () use ($documents) {
    $processor = new WP_Block_Processor($documents['broken json']);
    $processor->next_delimiter();
    $before = $processor->get_last_json_error();
    $parsed = $processor->allocate_and_return_parsed_attributes();
    $after = $processor->get_last_json_error();
    $processor->next_token();
    $html = [$processor->get_last_json_error(), $processor->allocate_and_return_parsed_attributes(), $processor->get_last_json_error()];
    $good = new WP_Block_Processor($documents['nested']);
    $good->next_delimiter();
    return [$before, $parsed, $after, $html, $good->get_last_json_error(), $good->allocate_and_return_parsed_attributes() === $good->allocate_and_return_parsed_attributes()];
})());

$say('names', [
    WP_Block_Processor::normalize_block_type('paragraph'), WP_Block_Processor::normalize_block_type('core/paragraph'), WP_Block_Processor::normalize_block_type('my/thing'), WP_Block_Processor::normalize_block_type(''),
    WP_Block_Processor::are_equal_block_types('core/paragraph', 0, 14, 'paragraph', 0, 9),
    WP_Block_Processor::are_equal_block_types('xxparagraphyy', 2, 9, 'core/paragraph', 0, 14),
    WP_Block_Processor::are_equal_block_types('core/image', 0, 10, 'core/paragraph', 0, 14),
    WP_Block_Processor::are_equal_block_types('my/thing', 0, 8, 'thing', 0, 5),
    WP_Block_Processor::are_equal_block_types('core/', 0, 5, '', 0, 0),
    [WP_Block_Processor::CLOSER, WP_Block_Processor::OPENER, WP_Block_Processor::VOID, WP_Block_Processor::READY, WP_Block_Processor::MATCHED, WP_Block_Processor::HTML_SPAN, WP_Block_Processor::INCOMPLETE_INPUT, WP_Block_Processor::COMPLETE],
]);

$say('before the first step', (static function () use ($nested, $step) {
    $processor = new WP_Block_Processor($nested);
    return $step($processor);
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
