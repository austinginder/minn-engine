<?php

use Minn\Html\Tags;

/** The HTML API's tag processor; every method maps onto Minn\Html\Tags. */
class WP_HTML_Tag_Processor
{
    protected Tags $tags;

    public function __construct($html)
    {
        $this->tags = new Tags((string) $html);
    }

    public function change_parsing_namespace(string $new_namespace): bool
    {
        return in_array($new_namespace, ['html', 'svg', 'math'], true);
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
        return 'html';
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
        $doctype = $this->tags->doctype();
        return $doctype === null ? null : new WP_HTML_Doctype_Info($doctype['name'], $doctype['public'], $doctype['system']);
    }

    public function __wakeup()
    {
        throw new \LogicException(__CLASS__ . ' should never be unserialized');
    }
}

/** The pieces of a doctype token. */
class WP_HTML_Doctype_Info
{
    public function __construct(public ?string $name, public ?string $public_identifier, public ?string $system_identifier)
    {
    }

    public static function from_doctype_token(string $doctype_html): ?self
    {
        $p = new WP_HTML_Tag_Processor($doctype_html);
        if (!$p->next_token() || $p->get_token_type() !== '#doctype') {
            return null;
        }
        return $p->get_doctype_info();
    }
}
