<?php
/**
 * The block parser's steps (probe block-parser-steps): the next token from
 * an offset; each proceed() through a document with what it answered and
 * where the parser stood after it (offset, output, stack); the freeform,
 * inner-block and stack helpers called on a parser set up by hand; and the
 * frame and block objects. parse() itself is compared elsewhere (probes
 * theme-symbols, plugin-symbols4).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
// Blocks and frames as plain arrays, so both stacks print the same shape.
$plain = static function ($value) use (&$plain) {
    if ($value instanceof WP_Block_Parser_Frame) {
        return ['frame' => $plain($value->block), 'token_start' => $value->token_start, 'token_length' => $value->token_length, 'prev_offset' => $value->prev_offset, 'leading_html_start' => $value->leading_html_start];
    }
    if ($value instanceof WP_Block_Parser_Block) {
        return ['block' => $value->blockName, 'attrs' => $value->attrs, 'innerBlocks' => $plain($value->innerBlocks), 'innerHTML' => $value->innerHTML, 'innerContent' => $value->innerContent];
    }
    return is_array($value) ? array_map($plain, $value) : $value;
};
// A parser where parse() would start it: the document, offset 0, nothing out, nothing open.
$fresh = static function (string $document): WP_Block_Parser {
    $parser = new WP_Block_Parser();
    $parser->document = $document;
    $parser->offset = 0;
    $parser->output = [];
    $parser->stack = [];
    return $parser;
};
$state = static fn (WP_Block_Parser $parser) => ['offset' => $parser->offset, 'output' => $plain($parser->output), 'stack' => $plain($parser->stack)];
$stepped = static function (string $document) use ($fresh, $state, $try) {
    $parser = $fresh($document);
    $steps = [];
    for ($i = 0; $i < 20; $i++) {
        $answer = $try(static fn () => $parser->proceed());
        $steps[] = ['answer' => $answer] + $state($parser);
        if ($answer !== true) {
            break;
        }
    }
    return $steps;
};

$documents = [
    'nothing' => '',
    'text alone' => 'Just text.',
    'a void block' => '<!-- wp:separator /-->',
    'a void block with text around' => 'a<!-- wp:spacer {"height":"20px"} /-->b',
    'a block' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
    'a namespaced block with attributes' => '<!-- wp:zz/box {"a":1,"b":[true,null]} -->in<!-- /wp:zz/box -->',
    'nested blocks' => '<!-- wp:group --><div><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph -->mid<!-- wp:image /--></div><!-- /wp:group -->tail',
    'two at the top with text between' => '<!-- wp:paragraph -->1<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->2<!-- /wp:paragraph -->',
    'an unclosed block' => '<!-- wp:group --><p>open',
    'a stray closer' => 'x<!-- /wp:group -->y',
    'a closer that does not match' => '<!-- wp:group -->a<!-- /wp:columns -->b',
    'bad attributes' => '<!-- wp:paragraph {"a":} -->x<!-- /wp:paragraph -->',
    'core named in full' => '<!-- wp:core/paragraph -->x<!-- /wp:core/paragraph -->',
    'an empty block' => '<!-- wp:group --><!-- /wp:group -->',
    'a block holding only a child' => '<!-- wp:group --><!-- wp:image /--><!-- /wp:group -->',
    'children back to back' => '<!-- wp:columns --><!-- wp:column -->a<!-- /wp:column --><!-- wp:column /--><!-- /wp:columns -->',
    'two left open' => '<!-- wp:group -->a<!-- wp:columns -->b<!-- wp:paragraph -->c',
    'a closer with attributes' => '<!-- wp:group -->a<!-- /wp:group {"x":1} -->b',
];
$say('steps', array_map(static fn ($document) => $stepped($document), $documents));

$tokens = static function (string $document, array $offsets) use ($fresh, $try) {
    $out = [];
    foreach ($offsets as $offset) {
        $parser = $fresh($document);
        $parser->offset = $offset;
        $out[$offset] = $try(static fn () => $parser->next_token());
    }
    return $out;
};
$say('next token', [
    'nested' => $tokens($documents['nested blocks'], [0, 10, 18, 40, 200]),
    'text alone' => $tokens('Just text.', [0, 4]),
    'attributes' => $tokens($documents['a namespaced block with attributes'], [0, 5]),
    'whitespace in the delimiter' => $tokens("<!--  wp:paragraph   {\"a\":1}   -->x<!--   /wp:paragraph   -->", [0, 30]),
    'a void block' => $tokens('<!-- wp:separator {"className":"is-style-wide"} /-->', [0]),
    'not a block comment' => $tokens('<!-- just a comment --><!-- wp:Bad /--><!-- wp:9x /-->', [0]),
]);

// The helpers on a parser set up by hand.
$helpers = [];
$parser = $fresh('Hello <!-- wp:paragraph -->x<!-- /wp:paragraph --> world');
$parser->add_freeform(6);
$helpers['freeform with a length'] = $state($parser);
$parser = $fresh('abc');
$parser->offset = 1;
$parser->add_freeform();
$helpers['freeform to the end'] = $state($parser);
$parser = $fresh('');
$parser->add_freeform();
$helpers['freeform with nothing left'] = $state($parser);
$parser = $fresh('<!-- wp:group -->a<!-- wp:paragraph -->b<!-- /wp:paragraph -->c');
$parser->stack[] = new WP_Block_Parser_Frame(new WP_Block_Parser_Block('core/group', [], [], '', []), 0, 17, 17);
$parser->add_inner_block(new WP_Block_Parser_Block('core/paragraph', [], [], 'b', ['b']), 18, 22);
$helpers['an inner block'] = $state($parser);
$parser->add_inner_block(new WP_Block_Parser_Block('core/spacer', [], [], '', []), 63, 0, 70);
$helpers['another, with its last offset'] = $state($parser);
$parser = $fresh('<!-- wp:group -->a<!-- wp:paragraph -->b');
$parser->stack[] = new WP_Block_Parser_Frame(new WP_Block_Parser_Block('core/group', [], [], '', []), 0, 17, 17, null);
$parser->add_block_from_stack();
$helpers['a block off the stack, to the end'] = $state($parser);
$parser = $fresh('pre<!-- wp:group -->a b c');
$parser->stack[] = new WP_Block_Parser_Frame(new WP_Block_Parser_Block('core/group', [], [], '', []), 3, 17, 20, 0);
$parser->add_block_from_stack(22);
$helpers['a block off the stack, to an offset, with leading text'] = $state($parser);
$say('helpers', $helpers);
$say('freeform', $plain((new WP_Block_Parser())->freeform('<p>loose</p>')));

$say('objects', $plain([
    'a frame' => new WP_Block_Parser_Frame(new WP_Block_Parser_Block('core/a', ['x' => 1], [], 'h', ['h']), 5, 10),
    'a frame with offsets' => new WP_Block_Parser_Frame(new WP_Block_Parser_Block('core/a', [], [], '', []), 5, 10, 3, 1),
    'a block' => new WP_Block_Parser_Block('core/b', ['y' => [1]], [new WP_Block_Parser_Block(null, [], [], 't', ['t'])], 'o', ['o', null]),
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
