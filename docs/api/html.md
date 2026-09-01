# `Minn\Html`

the HTML tag processor

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Decoder`](#decoder) | final class | 84 | Character reference decoding for text and attribute values: numeric and |
| [`Tags`](#tags) | final class | 905 | A streaming HTML tokenizer with in-place edits: tags, text, comments, |

## Decoder

`final class Minn\Html\Decoder` · `public/minn/src/Minn/Html/Decoder.php`

Character reference decoding for text and attribute values: numeric and
named references, the legacy names that work without a semicolon (not in
an attribute when an "=" or alphanumeric follows), unknown ones left as is.

Used by: `Minn\Html\Tags`


### static `text(string $raw): string`

### static `attribute(string $raw): string`

Internals: `decode()` (private, line 27), `codePoint()` (private, line 67), `legacy()` (private, line 80)


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

### `nextTag(array $query): bool`

- `@param array{tag_name?: ?string, class_name?: ?string, match_offset?: int, tag_closers?: string} $query`

### `pausedAtIncompleteToken(): bool`

### `tokenType(): ?string`

### `tokenName(): ?string`

### `tag(): ?string`

### `isCloser(): bool`

### `selfClosing(): bool`

### `commentType(): ?string`

### `fullCommentText(): ?string`

### `modifiableText(): string`

### `doctype(): ?array`

Doctype pieces, or null when the token is not a doctype. @return array{name: ?string, public: ?string, system: ?string}|null

- `@return array{name: ?string, public: ?string, system: ?string}|null`

### `attribute(string $name): string|true|null`

The decoded value, true for a bare attribute, null when absent; a pending edit answers first.

### `attributeNames(string $prefix): ?array`

- `@return list<string>|null lowercase names, in source order, null off a tag`

### `classes(): array`

- `@return list<string> distinct class names after pending edits`

### `hasClass(string $class): bool`

### `setAttribute(string $name, string|int|float|bool|null $value): bool`

### `removeAttribute(string $name): bool`

### `addClass(string $class): bool`

### `removeClass(string $class): bool`

### `setModifiableText(string $text): bool`

### `setBookmark(string $name): bool`

### `releaseBookmark(string $name): bool`

### `hasBookmark(string $name): bool`

### `seek(string $name): bool`

### `html(): string`

Internals: `scanTag()` (private, line 127), `scanAttribute()` (private, line 188), `enterRawText()` (private, line 241), `scanMarkupDeclaration()` (private, line 263), `scanQuestion()` (private, line 318), `setComment()` (private, line 346), `setToken()` (private, line 355), `resetToken()` (private, line 365), `isSpace()` (private, line 377), `baseClassList()` (private, line 568), `baseClassValue()` (private, line 578), `flush()` (private, line 742), `attributeReplacements()` (private, line 771), `applyReplacements()` (private, line 817), `rescanCurrent()` (private, line 843), `rescan()` (private, line 861), `existingName()` (private, line 875), `rebuiltClassValue()` (private, line 886), `escape()` (private, line 914)

