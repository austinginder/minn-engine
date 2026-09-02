# `Minn\Html`

the HTML tag processor

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Decoder`](#decoder) | final class | 86 | Character reference decoding for text and attribute values: numeric and |
| [`Edits`](#edits) | final class | 252 | The edits pending on the current token: attribute sets and removals, |
| [`Scanner`](#scanner) | final class | 238 | Reads one token's shape out of raw HTML at an offset: a tag with its |
| [`Tags`](#tags) | final class | 581 | A streaming HTML tokenizer with in-place edits: tags, text, comments, |

## Decoder

`final class Minn\Html\Decoder` · `public/minn/src/Minn/Html/Decoder.php`

Character reference decoding for text and attribute values: numeric and
named references, the legacy names that work without a semicolon (not in
an attribute when an "=" or alphanumeric follows), unknown ones left as is.

Used by: `Minn\Html\Tags`, `Minn\Support\Kses`


### static `text(string $raw): string`

Text with its character references decoded.

### static `attribute(string $raw): string`

An attribute value with its character references decoded.

Internals: `decode()` (private, line 29), `codePoint()` (private, line 69), `legacy()` (private, line 82)


## Edits

`final class Minn\Html\Edits` · `public/minn/src/Minn/Html/Edits.php`

The edits pending on the current token: attribute sets and removals,
class additions and removals, and a text replacement, in call order.
They answer reads before they are written, and become the source
replacements the reader splices in when it moves on.

Used by: `Minn\Html\Tags`


### static `validName(string $name): bool`

Whether an attribute name may be set at all, by the reference's rules.

### `setAttribute(string $name, string|true $value): void`

Records a set; setting class outright forgets the class edits before it.

### `removeAttribute(string $name): void`

Records a removal of an attribute the source has.

### `cancelAttribute(string $name): void`

Forgets a pending set that never reached the source: cancelling an addition is not a removal.

### `attribute(string $lower): ?array`

The pending set or removal for a lowercase name, or null when there is none. @return array{name: string, value: string|true|null}|null

- `@return array{name: string, value: string|true|null}|null`

### `addClass(string $class): void`

Records a class to add; a later removal of the same class wins.

### `removeClass(string $class): void`

Records a class to remove; a later addition of the same class wins.

### `hasClassEdits(): bool`

Whether any class edit is pending.

### `hasTagEdits(): bool`

Whether any attribute or class edit is pending.

### `classesAfter(array $base): array`

The class list after the pending edits: additions appended once,
removals gone.

- `@param list<string> $base`
- `@return list<string>`

### `setTextFor(?string $type, ?string $tagName, ?string $commentType, string $text): bool`

Records a text replacement for a token of a kind that takes one: text
(escaped), an ordinary HTML comment, or a raw-text element's body
(script and style bodies get their closers escaped). False when the
kind takes none, or the text would end the element early.

### `takeText(): ?string`

The pending text replacement, taken: it is cleared on read.

### `takeReplacements(array $attributes, int $nameEnd, ?string $baseClassValue): array`

The source replacements the attribute and class edits amount to, and
forgets them: a changed attribute is rewritten in place (its duplicates
removed), a removed one is cut, a new one is inserted after the tag name.

- `@param list<array{name: string, lower: string, start: int, end: int, value: ?string}> $attributes the tag's own`
- `@return list<array{int, int, string}> start, end, text`

### `clear(): void`

Forgets everything pending.

Internals: `rebuiltClassValue()` (private, line 223), `existingName()` (private, line 250), `escape()` (private, line 260)


## Scanner

`final class Minn\Html\Scanner` · `public/minn/src/Minn/Html/Scanner.php`

Reads one token's shape out of raw HTML at an offset: a tag with its
name and attributes, a comment of one of the reference's kinds, a
doctype, or a processing instruction. Pure and stateless; null means the
input ends before the token does, which is where a streaming reader pauses.

Used by: `Minn\Html\Tags`

### static `tag(string $html, int $at, int $nameStart): ?array`

A tag starting at $at, its name from $nameStart (one byte in for an
opener, two for a closer): the name, where it ends, the end offset,
the attributes with their source offsets, and the self-closing flag.

- `@return array{name: string, nameEnd: int, end: int, attributes: list<array{name: string, lower: string, start: int, end: int, value: ?string, quoted: bool}>, selfClosing: bool}|null`

### static `rawText(string $html, string $name, int $from): ?array`

A raw-text element's body (script, style, textarea, ...): from $from to
its closing tag, or null when the closer never comes.

- `@return array{textStart: int, textLength: int, end: int}|null`

### static `markupDeclaration(string $html, int $at): ?array`

What "<!" opens at $at: an HTML comment (abruptly closed or not), a
doctype, a CDATA lookalike, or a bogus comment.

- `@return array{kind: string, start: int, end: int, textStart: int, textLength: int, commentType?: string, fullStart?: int, fullLength?: int}|null`

### static `question(string $html, int $at): ?array`

What "<?" opens at $at: a PHP tag as a processing instruction, another
PI-shaped run as a comment lookalike, or a plain bogus comment.

- `@return array{kind: string, start: int, end: int, textStart: int, textLength: int, commentType?: string, fullStart?: int, fullLength?: int}|null`

### static `doctype(string $body): array`

A doctype's body split into its name and public and system identifiers.

- `@return array{name: ?string, public: ?string, system: ?string}`

### static `isSpace(string $c): bool`

Whether a byte is HTML whitespace.

Internals: `attribute()` (private, line 197), `comment()` (private, line 246)


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
- const `RAW_TEXT` = `array (   0 => 'script',   1 => 'style',   2 => 'textarea',   3 => 'title',   4 => 'xmp',   5 => 'iframe',   6 => 'noembed',   7 => 'noframes', )` — Elements whose body is raw text up to the closer.
- const `RAW_DECODED` = `array (   0 => 'textarea',   1 => 'title', )`
- const `MAX_BOOKMARKS` = `10`

Used by: `Minn\Html\Edits`, `Minn\Html\Scanner`

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

Replaces the current token's text, when its kind allows it.

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

Internals: `setToken()` (private, line 124), `resetToken()` (private, line 134), `takeTag()` (private, line 147), `take()` (private, line 176), `baseClassList()` (private, line 373), `baseClassValue()` (private, line 383), `flush()` (private, line 512), `applyReplacements()` (private, line 538), `rescanCurrent()` (private, line 564), `rescan()` (private, line 580)

