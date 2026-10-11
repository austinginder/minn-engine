<?php

/**
 * The block parser classes (probes theme-symbols, block-parser-steps).
 * parse() walks the document with the step methods, the reference's way,
 * so parse_blocks() answers a malformed document as the reference does (a
 * stray closer, attributes that are not JSON, blocks left open), and a
 * theme that swaps in a subclass through block_parser_class (Blocksy adds
 * keys to the top-level blocks) gets its parent's answer. The engine's own
 * renderer reads content with Minn\Blocks\Parser.
 */
class WP_Block_Parser
{
    public $document;
    public $offset;
    public $output;
    public $stack;

    /** The blocks of a document, as parse_blocks hands them out: the steps below, from the start until they are done. */
    public function parse($document)
    {
        $this->document = (string) $document;
        $this->offset = 0;
        $this->output = [];
        $this->stack = [];
        while ($this->proceed()) {
            continue;
        }
        return $this->output;
    }

    /**
     * One step through the document from the offset (probe
     * block-parser-steps): an opener goes on the stack; a void block goes
     * to its parent, or out with the text before it; a closer closes
     * whatever is on top, whatever it names. With nothing left, every open
     * block closes at the document's end, or the rest is freeform. A closer
     * with nothing open stops the walk, the rest freeform. False when the
     * walk is done.
     */
    public function proceed()
    {
        [$type, $name, $attrs, $start, $length] = $this->next_token();
        if ($type === 'no-more-tokens' || ($type === 'block-closer' && $this->stack === [])) {
            $this->minn_finish();
            return false;
        }
        if ($type === 'block-opener') {
            $this->stack[] = new WP_Block_Parser_Frame(new WP_Block_Parser_Block($name, $attrs, [], '', []), $start, $length, $start + $length, $start > $this->offset ? $this->offset : null);
        } elseif ($type === 'void-block') {
            $this->minn_void(new WP_Block_Parser_Block($name, $attrs, [], '', []), $start, $length);
        } else {
            $this->minn_close($start, $start + $length);
        }
        $this->offset = $start + $length;
        return true;
    }

    /** The walk's end: the rest freeform when nothing is open, else every open block closed at the document's end. */
    private function minn_finish(): void
    {
        if ($this->stack === []) {
            $this->add_freeform();
        }
        while ($this->stack !== []) {
            $this->add_block_from_stack();
        }
    }

    /** A closer: the top block out when it is the only one open, else into its parent. */
    private function minn_close(int $start, int $end): void
    {
        if (count($this->stack) === 1) {
            $this->add_block_from_stack($start);
            return;
        }
        $closed = array_pop($this->stack);
        $this->minn_append($closed, $closed->prev_offset, $start);
        $this->add_inner_block($closed->block, $closed->token_start, $closed->token_length, $end);
    }

    /** A void block: into the open block, or out after the text before it. */
    private function minn_void(WP_Block_Parser_Block $block, int $start, int $length): void
    {
        if ($this->stack !== []) {
            $this->add_inner_block($block, $start, $length);
            return;
        }
        if ($start > $this->offset) {
            $this->output[] = (array) $this->freeform(substr((string) $this->document, (int) $this->offset, $start - $this->offset));
        }
        $this->output[] = (array) $block;
    }

    /**
     * The next delimiter from the offset as [type, name, attributes, start,
     * length]: block-opener, block-closer or void-block, the attributes
     * decoded (an empty list when none are written, null when they are not
     * JSON, null on a closer); no-more-tokens when none is left.
     */
    public function next_token()
    {
        $token = Minn\Blocks\Parser::token((string) $this->document, (int) $this->offset);
        if ($token === null) {
            return ['no-more-tokens', null, null, null, null];
        }
        $attrs = $token['closer'] ? null : ($token['attrs'] === null ? [] : json_decode($token['attrs'], true));
        return [$token['closer'] ? 'block-closer' : ($token['void'] ? 'void-block' : 'block-opener'), $token['name'], $attrs, $token['start'], $token['length']];
    }

    /** Text outside any block, as a block with no name. */
    public function freeform($inner_html)
    {
        return new WP_Block_Parser_Block(null, [], [], $inner_html, [$inner_html]);
    }

    /** The document from the offset (as long as asked, else to the end) out as freeform, when there is any; the offset stays. */
    public function add_freeform($length = null)
    {
        $text = $length === null ? substr((string) $this->document, (int) $this->offset) : substr((string) $this->document, (int) $this->offset, $length);
        if ($text !== '') {
            $this->output[] = (array) $this->freeform($text);
        }
    }

    /** A finished block into the open one: the text since its last child first, then the block; the parent's place moves past it. */
    public function add_inner_block($block, $token_start, $token_length, $last_offset = null)
    {
        $parent = $this->stack[count($this->stack) - 1];
        $this->minn_append($parent, $parent->prev_offset, $token_start);
        $parent->block->innerBlocks[] = (array) $block;
        $parent->block->innerContent[] = null;
        $parent->prev_offset = $last_offset ?? $token_start + $token_length;
    }

    /** The top block closed (at an offset, else at the document's end) and out, after the text that led to it. */
    public function add_block_from_stack($end_offset = null)
    {
        $closed = array_pop($this->stack);
        $this->minn_append($closed, $closed->prev_offset, $end_offset ?? strlen((string) $this->document));
        if ($closed->leading_html_start !== null) {
            $this->output[] = (array) $this->freeform(substr((string) $this->document, $closed->leading_html_start, $closed->token_start - $closed->leading_html_start));
        }
        $this->output[] = (array) $closed->block;
    }

    /** The document between two offsets onto a frame's block, when there is any. */
    private function minn_append(WP_Block_Parser_Frame $frame, int $from, int $to): void
    {
        $text = substr((string) $this->document, $from, $to - $from);
        if ($text !== '') {
            $frame->block->innerHTML .= $text;
            $frame->block->innerContent[] = $text;
        }
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
