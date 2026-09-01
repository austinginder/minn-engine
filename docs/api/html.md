# `Minn\Html`

the HTML tag processor

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Decoder`](#decoder) | final class | 86 | Character reference decoding for text and attribute values: numeric and |
| [`Tags`](#tags) | final class | 938 | A streaming HTML tokenizer with in-place edits: tags, text, comments, |

## Decoder

`final class Minn\Html\Decoder` · `public/minn/src/Minn/Html/Decoder.php`

Character reference decoding for text and attribute values: numeric and
named references, the legacy names that work without a semicolon (not in
an attribute when an "=" or alphanumeric follows), unknown ones left as is.

Used by: `Minn\Html\Tags`


### static `text(string $raw): string`

Text with its character references decoded.

### static `attribute(string $raw): string`

An attribute value with its character references decoded.

Internals: `decode()` (private, line 29), `codePoint()` (private, line 69), `legacy()` (private, line 82)


## Tags

`final class Minn\Html\Tags` · `public/minn/src/Minn/Html/Tags.php`

A streaming HTML tokenizer with in-place edits: tags, text, comments,
doctypes, and processing instructions in document order, attribute reads
with character references decoded, attribute and class edits applied to
the source without reserialising anything else, bookmarks to seek back to.
Behaviour pinned by the html-tag-processor probe fixture.

- const `TAG` = `'#tag'`
- const `TEXT` = `'#text'`
- const `COMMENT` = `'#comment'`
- const `DOCTYPE` = `'#doctype'`
- const `PI` = `'#processing-instruction'`
- const `FUNKY` = `'#funky-comment'`
- const `CDATA` = `'#cdata-section'`
- const `COMMENT_HTML` = `'COMMENT_AS_HTML_COMMENT'`
- const `COMMENT_ABRUPT` = `'COMMENT_AS_ABRUPTLY_CLOSED_COMMENT'`
- const `COMMENT_INVALID` = `'COMMENT_AS_INVALID_HTML'`
- const `COMMENT_CDATA` = `'COMMENT_AS_CDATA_LOOKALIKE'`
- const `COMMENT_PI` = `'COMMENT_AS_PI_NODE_LOOKALIKE'`
- const `RAW_TEXT` = `array (   0 => 'script',   1 => 'style',   2 => 'textarea',   3 => 'title',   4 => 'xmp',   5 => 'iframe',   6 => 'noembed',   7 => 'noframes', )`
- const `RAW_DECODED` = `array (   0 => 'textarea',   1 => 'title', )`
- const `MAX_BOOKMARKS` = `10`

```php
__construct(string $html)
```


### `nextToken(): bool`

Advances to the next token; false at the end or at an incomplete one.

### `nextTag(array $query): bool`

Advances to the next tag matching the query; false when none.

- `@param array{tag_name?: ?string, class_name?: ?string, match_offset?: int, tag_closers?: string} $query`

### `pausedAtIncompleteToken(): bool`

Whether the scan stopped inside an unfinished token.

### `tokenType(): ?string`

The current token's kind.

### `tokenName(): ?string`

The current token's name.

### `tag(): ?string`

The current tag name, upper-cased.

### `isCloser(): bool`

Whether the current tag is a closer.

### `selfClosing(): bool`

Whether the current tag has the self-closing flag.

### `commentType(): ?string`

The current comment's kind.

### `fullCommentText(): ?string`

The current comment's whole text.

### `modifiableText(): string`

The current token's text as a reader sees it.

### `doctype(): ?array`

Doctype pieces, or null when the token is not a doctype. @return array{name: ?string, public: ?string, system: ?string}|null

- `@return array{name: ?string, public: ?string, system: ?string}|null`

### `attribute(string $name): string|true|null`

The decoded value, true for a bare attribute, null when absent; a pending edit answers first.

### `attributeNames(string $prefix): ?array`

The current tag's attribute names with a prefix, lower-cased.

- `@return list<string>|null lowercase names, in source order, null off a tag`

### `classes(): array`

The current tag's classes.

- `@return list<string> distinct class names after pending edits`

### `hasClass(string $class): bool`

Whether the current tag has a class.

### `setAttribute(string $name, string|int|float|bool|null $value): bool`

Sets an attribute on the current tag.

### `removeAttribute(string $name): bool`

Removes an attribute from the current tag.

### `addClass(string $class): bool`

Adds a class to the current tag.

### `removeClass(string $class): bool`

Removes a class from the current tag.

### `setModifiableText(string $text): bool`

Replaces the current token's text.

### `setBookmark(string $name): bool`

Names the current token's position.

### `releaseBookmark(string $name): bool`

Forgets a bookmark.

### `hasBookmark(string $name): bool`

Whether a bookmark exists.

### `seek(string $name): bool`

Moves to a bookmark.

### `html(): string`

The document with every update written in.

Internals: `scanTag()` (private, line 128), `scanAttribute()` (private, line 189), `enterRawText()` (private, line 242), `scanMarkupDeclaration()` (private, line 264), `scanQuestion()` (private, line 319), `setComment()` (private, line 347), `setToken()` (private, line 356), `resetToken()` (private, line 366), `isSpace()` (private, line 378), `baseClassList()` (private, line 591), `baseClassValue()` (private, line 601), `flush()` (private, line 775), `attributeReplacements()` (private, line 804), `applyReplacements()` (private, line 850), `rescanCurrent()` (private, line 876), `rescan()` (private, line 894), `existingName()` (private, line 908), `rebuiltClassValue()` (private, line 919), `escape()` (private, line 947)

