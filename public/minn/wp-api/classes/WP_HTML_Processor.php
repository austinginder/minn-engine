<?php
/**
 * The HTML API's processor: the tag processor's tokens put through HTML
 * tree construction (Minn\Html\Tree\Builder), so every token knows its
 * breadcrumbs and depth, implied elements appear as virtual tokens, and
 * markup the reference does not build a tree for stops the walk with an
 * "unsupported" error. Reads and edits pass through to the tokenizer only
 * for tokens the document wrote.
 */

require_once __DIR__ . '/WP_HTML_Tag_Processor.php';

use Minn\Html\Tree\Builder;
use Minn\Html\Tree\BodyRules;
use Minn\Html\Tree\Event;
use Minn\Html\Tree\Node;
use Minn\Html\Tree\Serializer;

class WP_HTML_Processor extends WP_HTML_Tag_Processor
{
    const MAX_BOOKMARKS = 10000;
    const PROCESS_NEXT_NODE = 'process-next-node';
    const REPROCESS_CURRENT_NODE = 'reprocess-current-node';
    const PROCESS_CURRENT_NODE = 'process-current-node';
    const ERROR_UNSUPPORTED = 'unsupported';
    const ERROR_EXCEEDED_MAX_BOOKMARKS = 'exceeded-max-bookmarks';
    const CONSTRUCTOR_UNLOCK_CODE = 'Use WP_HTML_Processor::create_fragment() instead of calling the class constructor directly.';

    private Builder $minnBuilder;
    private ?Event $minnEvent = null;
    private $last_error = null;
    private $unsupported_exception = null;
    /** @var array<string, string> bookmark name => the operation (push or pop) it marks */
    private array $minnBookmarkOps = [];

    public static function create_fragment($html, $context = '<body>', $encoding = 'UTF-8')
    {
        if ($context !== '<body>' || $encoding !== 'UTF-8') {
            return null;
        }
        $processor = new static((string) $html, self::CONSTRUCTOR_UNLOCK_CODE);
        $processor->minnBuilder = new Builder($processor->tags, Builder::FRAGMENT);
        return $processor;
    }

    public static function create_full_parser($html, $known_definite_encoding = 'UTF-8')
    {
        if ($known_definite_encoding !== 'UTF-8') {
            return null;
        }
        return new static((string) $html, self::CONSTRUCTOR_UNLOCK_CODE);
    }

    public function __construct($html, $use_the_static_create_methods_instead = null)
    {
        parent::__construct($html);
        if ($use_the_static_create_methods_instead !== self::CONSTRUCTOR_UNLOCK_CODE) {
            _doing_it_wrong(__METHOD__, sprintf('Call %s to create an HTML Processor instead of calling the constructor directly.', '<code>WP_HTML_Processor::create_fragment()</code>'), '6.4.0');
        }
        $this->tags->allowBookmarks(self::MAX_BOOKMARKS);
        $this->minnBuilder = new Builder($this->tags, Builder::DOCUMENT);
    }

    public function get_last_error()
    {
        return $this->last_error;
    }

    public function get_unsupported_exception()
    {
        return $this->unsupported_exception;
    }

    public function next_tag($query = null): bool
    {
        $query = is_string($query) ? ['tag_name' => $query] : (array) $query;
        $visitClosers = ($query['tag_closers'] ?? '') === 'visit';
        // A match offset counts only among breadcrumb matches.
        $wanted = isset($query['breadcrumbs']) ? max(1, (int) ($query['match_offset'] ?? 1)) : 1;
        $matched = 0;
        while ($this->next_token()) {
            if ($this->minnEvent->node->type !== '#tag' || ($this->minnEvent->isCloser() && !$visitClosers) || !$this->minnMatches($query)) {
                continue;
            }
            if (++$matched >= $wanted) {
                return true;
            }
        }
        return false;
    }

    public function next_token(): bool
    {
        $this->minnEvent = $this->minnBuilder->next();
        $error = $this->minnBuilder->error();
        if ($this->minnEvent === null && $error !== null && $this->last_error === null) {
            $this->last_error = self::ERROR_UNSUPPORTED;
            $this->unsupported_exception = new WP_HTML_Unsupported_Exception($error->getMessage(), $error->token->name, $error->token->offset, $error->tokenText, $error->stack, $error->formatting);
        }
        return $this->minnEvent !== null;
    }

    public function is_tag_closer(): bool
    {
        return $this->minnEvent !== null && $this->minnEvent->isCloser();
    }

    public function matches_breadcrumbs($breadcrumbs): bool
    {
        $have = $this->get_breadcrumbs();
        $want = array_values((array) $breadcrumbs);
        if ($this->minnEvent === null || $this->minnEvent->node->type !== '#tag' || count($want) > count($have)) {
            return false;
        }
        $have = array_slice($have, count($have) - count($want));
        foreach ($want as $i => $crumb) {
            if ($crumb !== '*' && strtoupper((string) $crumb) !== $have[$i]) {
                return false;
            }
        }
        return true;
    }

    public function expects_closer($node = null): ?bool
    {
        return $this->minnEvent?->node->expectsCloser();
    }

    public function step($node_to_process = self::PROCESS_NEXT_NODE): bool
    {
        return $this->next_token();
    }

    public function get_breadcrumbs(): ?array
    {
        return $this->minnEvent !== null ? $this->minnEvent->breadcrumbs : $this->minnBuilder->breadcrumbs();
    }

    public function get_current_depth(): int
    {
        return count((array) $this->get_breadcrumbs());
    }

    public static function normalize(string $html): ?string
    {
        return static::create_fragment($html)?->serialize();
    }

    public function serialize(): ?string
    {
        $html = '';
        while ($this->next_token()) {
            $html .= $this->serialize_token();
        }
        return $this->last_error === null ? $html : null;
    }

    public function serialize_token(): string
    {
        $event = $this->minnEvent;
        if ($event === null) {
            return '';
        }
        $node = $event->node;
        if ($node->type !== '#tag') {
            return Serializer::leaf($node, (string) ($this->minnReal() ? $this->tags->fullCommentText() : ''), (string) ($this->minnReal() ? $this->tags->tag() : ''));
        }
        if ($event->isCloser()) {
            return Serializer::close($node);
        }
        return Serializer::open($node, $this->minnAttributes(), $this->minnReal() ? $this->tags->modifiableText() : '');
    }

    public function get_namespace(): string
    {
        return $this->minnEvent?->node->namespace ?? 'html';
    }

    public function get_tag(): ?string
    {
        return $this->minnEvent !== null && $this->minnEvent->node->type === '#tag' ? $this->minnEvent->node->name : null;
    }

    public function has_self_closing_flag(): bool
    {
        return $this->minnReal() && parent::has_self_closing_flag();
    }

    public function get_token_name(): ?string
    {
        return $this->minnEvent?->node->name;
    }

    public function get_token_type(): ?string
    {
        return $this->minnEvent?->node->type;
    }

    public function get_attribute($name)
    {
        return $this->minnOpener() ? parent::get_attribute($name) : null;
    }

    public function set_attribute($name, $value): bool
    {
        return $this->minnOpener() && parent::set_attribute($name, $value);
    }

    public function remove_attribute($name): bool
    {
        return $this->minnOpener() && parent::remove_attribute($name);
    }

    public function get_attribute_names_with_prefix($prefix): ?array
    {
        return $this->minnOpener() ? parent::get_attribute_names_with_prefix($prefix) : null;
    }

    public function add_class($class_name): bool
    {
        return $this->minnOpener() && parent::add_class($class_name);
    }

    public function remove_class($class_name): bool
    {
        return $this->minnOpener() && parent::remove_class($class_name);
    }

    public function has_class($wanted_class): ?bool
    {
        return $this->minnOpener() ? parent::has_class($wanted_class) : null;
    }

    public function class_list()
    {
        if (!$this->minnOpener()) {
            return;
        }
        yield from parent::class_list();
    }

    public function get_modifiable_text(): string
    {
        $node = $this->minnEvent?->node;
        if ($node === null || $this->minnEvent->isCloser()) {
            return '';
        }
        return $node->type !== '#tag' ? $node->text() : ($this->minnReal() ? parent::get_modifiable_text() : '');
    }

    public function set_modifiable_text(string $plaintext_content): bool
    {
        return $this->minnReal() && !$this->minnEvent->isCloser() && parent::set_modifiable_text($plaintext_content);
    }

    public function get_comment_type(): ?string
    {
        return $this->minnReal() ? parent::get_comment_type() : null;
    }

    public function release_bookmark($bookmark_name): bool
    {
        unset($this->minnBookmarkOps[$bookmark_name]);
        return $this->tags->releaseBookmark((string) $bookmark_name);
    }

    public function seek($bookmark_name): bool
    {
        $start = $this->tags->bookmarkStart((string) $bookmark_name);
        $op = $this->minnBookmarkOps[$bookmark_name] ?? null;
        $this->tags->rewind();
        $this->minnBuilder->reset();
        $this->minnEvent = null;
        while ($start !== null && ($event = $this->minnBuilder->next()) !== null) {
            if ($event->offset === $start && $event->op === $op) {
                $this->minnEvent = $event;
                return true;
            }
        }
        $this->minnBuilder->reset();
        $this->minnBuilder->halt();
        return false;
    }

    public function set_bookmark($bookmark_name): bool
    {
        if (!$this->minnReal() || !$this->tags->setBookmark((string) $bookmark_name)) {
            return false;
        }
        $this->minnBookmarkOps[$bookmark_name] = $this->minnEvent->op;
        return true;
    }

    public function has_bookmark($bookmark_name): bool
    {
        return $this->tags->hasBookmark((string) $bookmark_name);
    }

    public static function is_special($tag_name): bool
    {
        return in_array(strtoupper((string) $tag_name), BodyRules::SPECIAL, true);
    }

    public static function is_void($tag_name): bool
    {
        return in_array(strtoupper((string) $tag_name), Node::VOID, true);
    }

    protected static function get_encoding(string $label): ?string
    {
        return in_array(strtolower(trim($label)), ['unicode-1-1-utf-8', 'unicode11utf8', 'unicode20utf8', 'utf-8', 'utf8', 'x-unicode20utf8'], true) ? 'UTF-8' : null;
    }

    /** Whether the current token is one the document wrote, and the tokenizer is on it. */
    private function minnReal(): bool
    {
        return $this->minnEvent !== null && $this->minnEvent->offset !== null && $this->minnEvent->offset === $this->tags->tokenSpan()[0];
    }

    /** Whether the current token is a written opening tag, whose attributes can be read and edited. */
    private function minnOpener(): bool
    {
        return $this->minnReal() && !$this->minnEvent->isCloser() && $this->minnEvent->node->type === '#tag';
    }

    /** @return list<array{0: string, 1: string|true}> */
    private function minnAttributes(): array
    {
        if (!$this->minnOpener()) {
            return [];
        }
        $attributes = [];
        foreach ((array) parent::get_attribute_names_with_prefix('') as $name) {
            $attributes[] = [$name, parent::get_attribute($name)];
        }
        return $attributes;
    }

    private function minnMatches(array $query): bool
    {
        if (isset($query['tag_name']) && strtoupper((string) $query['tag_name']) !== $this->get_tag()) {
            return false;
        }
        if (isset($query['class_name']) && !$this->has_class($query['class_name'])) {
            return false;
        }
        return !isset($query['breadcrumbs']) || $this->matches_breadcrumbs($query['breadcrumbs']);
    }
}

class WP_HTML_Unsupported_Exception extends Exception
{
    public $token_name;
    public $token_at;
    public $token;
    public $stack_of_open_elements = [];
    public $active_formatting_elements = [];

    public function __construct(string $message, string $token_name, int $token_at, string $token, array $stack_of_open_elements, array $active_formatting_elements)
    {
        parent::__construct($message);
        $this->token_name = $token_name;
        $this->token_at = $token_at;
        $this->token = $token;
        $this->stack_of_open_elements = $stack_of_open_elements;
        $this->active_formatting_elements = $active_formatting_elements;
    }
}
