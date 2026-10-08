<?php

/**
 * The block parser classes (probe theme-symbols). parse() is the engine's
 * own parser (Minn\Blocks\Parser) answering in the reference's shape, so a
 * theme that swaps in a subclass through block_parser_class (Blocksy adds
 * keys to the top-level blocks) gets its parent's answer; the tokenizer's
 * step methods are kept for their names, since the engine's parser does
 * the stepping itself.
 */
class WP_Block_Parser
{
    public $document;
    public $offset;
    public $output;
    public $stack;

    /** The blocks of a document, as parse_blocks hands them out. */
    public function parse($document)
    {
        $this->document = (string) $document;
        $this->offset = strlen($this->document);
        $this->stack = [];
        $this->output = $this->document === '' ? [] : array_map('_minn_block_to_array', Minn\Blocks\Parser::parse($this->document));
        return $this->output;
    }

    /** The engine's parser steps through the document in parse(); there is nothing left to step. */
    public function proceed()
    {
        return false;
    }

    public function next_token()
    {
        return ['no-more-tokens', null, null, null, null];
    }

    /** Text outside any block, as a block with no name. */
    public function freeform($inner_html)
    {
        return new WP_Block_Parser_Block(null, [], [], $inner_html, [$inner_html]);
    }

    public function add_freeform($length = null)
    {
    }

    public function add_inner_block($block, $token_start, $token_length, $last_offset = null)
    {
    }

    public function add_block_from_stack($end_offset = null)
    {
    }
}

class WP_Block_Parser_Block
{
    public $blockName;
    public $attrs;
    public $innerBlocks;
    public $innerHTML;
    public $innerContent;

    public function __construct($name, $attrs, $inner_blocks, $inner_html, $inner_content)
    {
        $this->blockName = $name;
        $this->attrs = $attrs;
        $this->innerBlocks = $inner_blocks;
        $this->innerHTML = $inner_html;
        $this->innerContent = $inner_content;
    }
}

class WP_Block_Parser_Frame
{
    public $block;
    public $token_start;
    public $token_length;
    public $prev_offset;
    public $leading_html_start;

    public function __construct($block, $token_start, $token_length, $prev_offset = null, $leading_html_start = null)
    {
        $this->block = $block;
        $this->token_start = $token_start;
        $this->token_length = $token_length;
        $this->prev_offset = $prev_offset ?? $token_start + $token_length;
        $this->leading_html_start = $leading_html_start;
    }
}
