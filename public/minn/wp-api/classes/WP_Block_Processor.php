<?php

use Minn\Blocks\BlockStream;

/**
 * The streaming block processor (probe block-processor): block markup read
 * a token at a time by Minn\Blocks\BlockStream, with the narrower walks
 * (next_delimiter, next_block) and whole blocks extracted in parse_blocks
 * shape. Attributes are parsed only when asked; get_attributes() refuses,
 * as the reference's does, since lazy parsing is not there yet.
 */
class WP_Block_Processor
{
    const CLOSER = 'closer';
    const OPENER = 'opener';
    const VOID = 'void';
    const READY = 'processor-ready';
    const MATCHED = 'processor-matched';
    const HTML_SPAN = 'processor-html-span';
    const INCOMPLETE_INPUT = 'incomplete-input';
    const COMPLETE = 'processor-complete';

    protected $source_text;
    private $stream;

    public function __construct(string $source_text)
    {
        $this->source_text = $source_text;
        $this->stream = new BlockStream($source_text);
    }

    /** The next block opener or void (of the type, when given); * also stops at HTML. */
    public function next_block(?string $block_type = null): bool
    {
        while ($this->stream->next()) {
            $closer = $this->stream->delimiterType() === self::CLOSER && !$this->stream->isHtml();
            if (!$closer && ($block_type === '*' || (!$this->stream->isHtml() && ($block_type === null || $this->stream->isType($block_type))))) {
                return true;
            }
        }
        return false;
    }

    /** The next delimiter (of the type, when given); * also stops at HTML. */
    public function next_delimiter(?string $block_name = null): bool
    {
        while ($this->stream->next()) {
            if ($block_name === '*' || (!$this->stream->isHtml() && ($block_name === null || $this->stream->isType($block_name)))) {
                return true;
            }
        }
        return false;
    }

    public function next_token(): bool
    {
        return $this->stream->next();
    }

    public function get_breadcrumbs(): array
    {
        return $this->stream->breadcrumbs();
    }

    public function get_depth(): int
    {
        return count($this->stream->breadcrumbs());
    }

    /**
     * The block at the current token in parse_blocks shape, inner blocks and
     * HTML gathered until its closer; it stops on that closer. HTML and a
     * void block move on to the next token.
     */
    public function extract_full_block_and_advance(): ?array
    {
        if ($this->is_html() || $this->get_delimiter_type() === self::VOID) {
            return $this->take_single();
        }
        $stack = [$this->current_block()];
        $depth = $this->get_depth();
        while ($this->next_token() && !($this->get_delimiter_type() === self::CLOSER && !$this->is_html() && $this->get_depth() < $depth)) {
            $stack = self::gather($stack, $this);
        }
        while (count($stack) > 1) {
            $inner = array_pop($stack);
            $stack = self::nest($stack, $inner);
        }
        return $stack[0];
    }

    public function get_last_error(): ?string
    {
        return $this->stream->error();
    }

    public function get_last_json_error(): int
    {
        return $this->stream->jsonError();
    }

    public function get_delimiter_type(): ?string
    {
        return $this->stream->delimiterType();
    }

    public function has_closing_flag(): bool
    {
        return $this->stream->closing();
    }

    public function is_block_type(string $block_type): bool
    {
        return $this->stream->isType($block_type);
    }

    public static function are_equal_block_types(string $a_text, int $a_at, int $a_length, string $b_text, int $b_at, int $b_length): bool
    {
        return BlockStream::normalize(substr($a_text, $a_at, $a_length)) === BlockStream::normalize(substr($b_text, $b_at, $b_length));
    }

    public function opens_block(?string $block_type = null): bool
    {
        return $this->stream->opens($block_type);
    }

    public function is_html(): bool
    {
        return $this->stream->isHtml();
    }

    public function is_non_whitespace_html(): bool
    {
        return $this->stream->nonWhitespaceHtml();
    }

    public function get_html_content(): ?string
    {
        return $this->stream->htmlContent();
    }

    public function get_block_type(): ?string
    {
        return $this->stream->blockType();
    }

    public function get_printable_block_type(): ?string
    {
        return $this->stream->printableType();
    }

    public static function normalize_block_type(string $block_type): string
    {
        return BlockStream::normalize($block_type);
    }

    public function get_attributes()
    {
        throw new Exception('Lazy attribute parsing not yet supported');
    }

    public function allocate_and_return_parsed_attributes(): ?array
    {
        return $this->stream->attributes();
    }

    public function get_span(): ?WP_HTML_Span
    {
        $span = $this->stream->span();
        return $span === null ? null : new WP_HTML_Span($span[0], $span[1]);
    }

    /** HTML as a freeform block, or a void block, moving on to the next token. */
    private function take_single(): array
    {
        $block = $this->is_html() ? self::block(null, [], (string) $this->get_html_content()) : $this->current_block();
        $this->next_token();
        return $block;
    }

    /** The current delimiter as a block with no inner content yet. */
    private function current_block(): array
    {
        return self::block($this->get_block_type(), $this->allocate_and_return_parsed_attributes() ?? [], '');
    }

    private static function block(?string $name, array $attrs, string $html): array
    {
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => $html === '' ? [] : [$html]];
    }

    /** One token taken into the block being gathered: HTML as content, a void as an inner block, an opener started, a closer finishing one. */
    private static function gather(array $stack, self $processor): array
    {
        if ($processor->is_html()) {
            $top = count($stack) - 1;
            $stack[$top]['innerHTML'] .= (string) $processor->get_html_content();
            $stack[$top]['innerContent'][] = (string) $processor->get_html_content();
            return $stack;
        }
        if ($processor->get_delimiter_type() !== self::CLOSER) {
            return $processor->get_delimiter_type() === self::VOID ? self::nest($stack, $processor->current_block()) : [...$stack, $processor->current_block()];
        }
        if (count($stack) === 1) {
            return $stack;
        }
        $inner = array_pop($stack);
        return self::nest($stack, $inner);
    }

    private static function nest(array $stack, array $block): array
    {
        $top = count($stack) - 1;
        $stack[$top]['innerBlocks'][] = $block;
        $stack[$top]['innerContent'][] = null;
        return $stack;
    }
}
