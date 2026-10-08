<?php

use Minn\Html\Tags;

/** The HTML API's tag processor; every method maps onto Minn\Html\Tags. */
class WP_HTML_Tag_Processor
{
    const MAX_BOOKMARKS = 10;
    const MAX_SEEK_OPS = 1000;
    const ADD_CLASS = true;
    const REMOVE_CLASS = false;
    const SKIP_CLASS = null;
    const STATE_READY = 'STATE_READY';
    const STATE_COMPLETE = 'STATE_COMPLETE';
    const STATE_INCOMPLETE_INPUT = 'STATE_INCOMPLETE_INPUT';
    const STATE_MATCHED_TAG = 'STATE_MATCHED_TAG';
    const STATE_TEXT_NODE = 'STATE_TEXT_NODE';
    const STATE_CDATA_NODE = 'STATE_CDATA_NODE';
    const STATE_COMMENT = 'STATE_COMMENT';
    const STATE_DOCTYPE = 'STATE_DOCTYPE';
    const STATE_PRESUMPTUOUS_TAG = 'STATE_PRESUMPTUOUS_TAG';
    const STATE_FUNKY_COMMENT = 'STATE_WP_FUNKY';
    const STATE_PROCESSING_INSTRUCTION = 'STATE_PROCESSING_INSTRUCTION';
    const COMMENT_AS_ABRUPTLY_CLOSED_COMMENT = 'COMMENT_AS_ABRUPTLY_CLOSED_COMMENT';
    const COMMENT_AS_CDATA_LOOKALIKE = 'COMMENT_AS_CDATA_LOOKALIKE';
    const COMMENT_AS_HTML_COMMENT = 'COMMENT_AS_HTML_COMMENT';
    const COMMENT_AS_PI_NODE_LOOKALIKE = 'COMMENT_AS_PI_NODE_LOOKALIKE';
    const COMMENT_AS_INVALID_HTML = 'COMMENT_AS_INVALID_HTML';
    const NO_QUIRKS_MODE = 'no-quirks-mode';
    const QUIRKS_MODE = 'quirks-mode';
    const TEXT_IS_GENERIC = 'TEXT_IS_GENERIC';
    const TEXT_IS_NULL_SEQUENCE = 'TEXT_IS_null_SEQUENCE';
    const TEXT_IS_WHITESPACE = 'TEXT_IS_WHITESPACE';

    protected Tags $tags;
    private string $minnNamespace = 'html';

    public function __construct($html)
    {
        $this->tags = new Tags((string) $html);
    }

    public function change_parsing_namespace(string $new_namespace): bool
    {
        if (!in_array($new_namespace, ['html', 'svg', 'math'], true)) {
            return false;
        }
        $this->minnNamespace = $new_namespace;
        $this->tags->parseAs($new_namespace, $new_namespace === 'html' ? 'html' : 'foreign');
        return true;
    }

    public function next_tag($query = null): bool
    {
        if (is_string($query)) {
            $query = ['tag_name' => $query];
        }
        return $this->tags->nextTag(is_array($query) ? $query : []);
    }

    public function next_token(): bool
    {
        return $this->tags->nextToken();
    }

    public function paused_at_incomplete_token(): bool
    {
        return $this->tags->pausedAtIncompleteToken();
    }

    public function class_list()
    {
        foreach ($this->tags->classes() as $class) {
            yield $class;
        }
    }

    public function has_class($wanted_class): ?bool
    {
        if ($this->tags->tokenType() !== Tags::TAG || $this->tags->isCloser()) {
            return null;
        }
        return $this->tags->hasClass((string) $wanted_class);
    }

    public function set_bookmark($name): bool
    {
        return $this->tags->setBookmark((string) $name);
    }

    public function release_bookmark($name): bool
    {
        return $this->tags->releaseBookmark((string) $name);
    }

    public function has_bookmark($bookmark_name): bool
    {
        return $this->tags->hasBookmark((string) $bookmark_name);
    }

    public function seek($bookmark_name): bool
    {
        return $this->tags->seek((string) $bookmark_name);
    }

    public function get_attribute($name)
    {
        return $this->tags->attribute((string) $name);
    }

    public function get_attribute_names_with_prefix($prefix): ?array
    {
        return $this->tags->attributeNames((string) $prefix);
    }

    public function get_namespace(): string
    {
        return $this->minnNamespace;
    }

    public function get_tag(): ?string
    {
        return $this->tags->tag();
    }

    public function get_qualified_tag_name(): ?string
    {
        return $this->tags->tag();
    }

    public function get_qualified_attribute_name($attribute_name): ?string
    {
        return $this->tags->tokenType() === Tags::TAG ? strtolower((string) $attribute_name) : null;
    }

    public function has_self_closing_flag(): bool
    {
        return $this->tags->selfClosing();
    }

    public function is_tag_closer(): bool
    {
        return $this->tags->isCloser();
    }

    public function get_token_type(): ?string
    {
        return $this->tags->tokenType();
    }

    public function get_token_name(): ?string
    {
        return $this->tags->tokenName();
    }

    public function get_comment_type(): ?string
    {
        return $this->tags->commentType();
    }

    public function get_full_comment_text(): ?string
    {
        return $this->tags->fullCommentText();
    }

    public function subdivide_text_appropriately(): bool
    {
        return false;
    }

    public function get_modifiable_text(): string
    {
        return $this->tags->modifiableText();
    }

    public function set_modifiable_text(string $plaintext_content): bool
    {
        return $this->tags->setModifiableText($plaintext_content);
    }

    public function set_attribute($name, $value): bool
    {
        if (!is_string($name) || (!is_scalar($value) && $value !== null)) {
            return false;
        }
        // A URL attribute's value goes through esc_url, and one it wipes out is refused.
        if (is_string($value) && in_array(strtolower($name), wp_kses_uri_attributes(), true)) {
            $escaped = esc_url($value);
            return ($escaped !== '' || $value === '') && $this->tags->setAttribute($name, new Minn\Html\Escaped($escaped));
        }
        $ok = $this->tags->setAttribute($name, $value);
        if (!$ok && $value !== null && $value !== false && $name !== '' && preg_match('/[\s"\'>\/=]/', $name)) {
            _doing_it_wrong('WP_HTML_Tag_Processor::set_attribute', 'Invalid attribute name.', '6.2.0');
        }
        return $ok;
    }

    public function remove_attribute($name): bool
    {
        return $this->tags->removeAttribute((string) $name);
    }

    public function add_class($class_name): bool
    {
        return $this->tags->addClass((string) $class_name);
    }

    public function remove_class($class_name): bool
    {
        return $this->tags->removeClass((string) $class_name);
    }

    public function __toString(): string
    {
        return $this->get_updated_html();
    }

    public function get_updated_html(): string
    {
        return $this->tags->html();
    }

    public function get_doctype_info(): ?WP_HTML_Doctype_Info
    {
        if ($this->tags->tokenType() !== Tags::DOCTYPE) {
            return null;
        }
        [$start, $end] = $this->tags->tokenSpan();
        return WP_HTML_Doctype_Info::from_doctype_token($this->tags->source($start, $end - $start));
    }

    public function __wakeup()
    {
        throw new \LogicException(__CLASS__ . ' should never be unserialized');
    }
}

/** The pieces of a doctype token and the compatibility mode it indicates. */
class WP_HTML_Doctype_Info
{
    public $name;
    public $public_identifier;
    public $system_identifier;
    public $indicated_compatibility_mode;

    private function __construct(?string $name, ?string $public_identifier, ?string $system_identifier, string $indicated_compatibility_mode)
    {
        $this->name = $name;
        $this->public_identifier = $public_identifier;
        $this->system_identifier = $system_identifier;
        $this->indicated_compatibility_mode = $indicated_compatibility_mode;
    }

    public static function from_doctype_token($doctype_html)
    {
        $doctype = \Minn\Html\Tree\Compat::read((string) $doctype_html);
        return $doctype === null ? null : new self($doctype['name'], $doctype['public'], $doctype['system'], $doctype['mode']);
    }
}
