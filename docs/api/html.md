# `Minn\Html`

the HTML tag processor

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Decoder`](#decoder) | final class | 129 | Character reference decoding for text and attribute values: numeric and |
| [`Edits`](#edits) | final class | 329 | The edits pending on the current token: attribute sets and removals, |
| [`Escaped`](#escaped) | final readonly class | 6 | An attribute value that comes already escaped (a URL through esc_url), written as it is. |
| [`Scanner`](#scanner) | final class | 291 | Reads one token's shape out of raw HTML at an offset: a tag with its |
| [`TagQuery`](#tagquery) | final readonly class | 37 | A tag processor's next_tag() query: a tag name (any case), a class, the |
| [`Tags`](#tags) | final class | 584 | A streaming HTML tokenizer with in-place edits: tags, text, comments, |
| [`TokenMap`](#tokenmap) | final class | 87 | A map from words to replacements that reads the longest word at a place |

## Decoder

`final class Minn\Html\Decoder` · `public/minn/src/Minn/Html/Decoder.php`

Character reference decoding for text and attribute values: numeric and
named references, the legacy names that work without a semicolon (not in
an attribute when an "=" or alphanumeric follows), unknown ones left as is.

- const `LEGACY` = `array (   0 => 'AElig',   1 => 'AMP',   2 => 'Aacute',   3 => 'Acirc',   4 => 'Agrave',   5 => 'Aring',   6 => 'Atilde',   7 => 'Auml',   8 => 'COPY',   9 => 'Ccedil',   10 => 'ETH',   11 => 'Eacute',   12 => 'Ecirc',   13 => 'Egrave',   14 => 'Euml',   15 => 'GT',   16 => 'Iacute',   17 => 'Icirc',   18 => 'Igrave',   19 => 'Iuml',   20 => 'LT',   21 => 'Ntilde',   22 => 'Oacute',   23 => 'Ocirc',   24 => 'Ograve',   25 => 'Oslash',   26 => 'Otilde',   27 => 'Ouml',   28 => 'QUOT',   29 => 'REG',   30 => 'THORN',   31 => 'Uacute',   32 => 'Ucirc',   33 => 'Ugrave',   34 => 'Uuml',   35 => 'Yacute',   36 => 'aacute',   37 => 'acirc',   38 => 'acute',   39 => 'aelig',   40 => 'agrave',   41 => 'amp',   42 => 'aring',   43 => 'atilde',   44 => 'auml',   45 => 'brvbar',   46 => 'ccedil',   47 => 'cedil',   48 => 'cent',   49 => 'copy',   50 => 'curren',   51 => 'deg',   52 => 'divide',   53 => 'eacute',   54 => 'ecirc',   55 => 'egrave',   56 => 'eth',   57 => 'euml',   58 => 'frac12',   59 => 'frac14',   60 => 'frac34',   61 => 'gt',   62 => 'iacute',   63 => 'icirc',   64 => 'iexcl',   65 => 'igrave',   66 => 'iquest',   67 => 'iuml',   68 => 'laquo',   69 => 'lt',   70 => 'macr',   71 => 'micro',   72 => 'middot',   73 => 'nbsp',   74 => 'not',   75 => 'ntilde',   76 => 'oacute',   77 => 'ocirc',   78 => 'ograve',   79 => 'ordf',   80 => 'ordm',   81 => 'oslash',   82 => 'otilde',   83 => 'ouml',   84 => 'para',   85 => 'plusmn',   86 => 'pound',   87 => 'quot',   88 => 'raquo',   89 => 'reg',   90 => 'sect',   91 => 'shy',   92 => 'sup1',   93 => 'sup2',   94 => 'sup3',   95 => 'szlig',   96 => 'thorn',   97 => 'times',   98 => 'uacute',   99 => 'ucirc',   100 => 'ugrave',   101 => 'uml',   102 => 'uuml',   103 => 'yacute',   104 => 'yen',   105 => 'yuml', )` — The named references that decode without a semicolon (the HTML standard's legacy list).

Used by: `Minn\Html\Edits`, `Minn\Html\Scanner`, `Minn\Html\Tags`, `Minn\Html\Tree\Builder`, `Minn\Support\Kses`


### static `text(string $raw): string`

Text with its character references decoded.

### static `attribute(string $raw): string`

An attribute value with its character references decoded.

### static `reference(string $context, string $text, int $at): ?array`

The character reference at $at in a context ("data" or "attribute"):
its text and how many bytes it spans, or null when none decodes there.

- `@return array{0: string, 1: int}|null`

### static `char(int $code): string`

The UTF-8 text for a code point, U+FFFD for one that cannot be a character, Windows-1252's for the C1 range.

Internals: `decode()` (private, line 64), `codePoint()` (private, line 104), `legacy()` (private, line 128)


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

### `setEscapedAttribute(string $name, string $escaped): void`

Records an attribute value that comes already escaped (a URL through esc_url), written as it is.

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
removed), a removed one is cut, a new one is inserted after the tag name
(several in the order of their text).

- `@param list<array{name: string, lower: string, start: int, end: int, value: ?string}> $attributes the tag's own`
- `@return list<array{int, int, string}> start, end, text`

### `clear(): void`

Forgets everything pending.

### static `splice(string $html, array $replacements, array $bookmarks, int $tokenStart, int $tokenEnd): array`

Splices replacements into the source, ascending, and moves the bookmarks
and the cursor by what the token at [$tokenStart, $tokenEnd] gained or lost.

- `@param list<array{int, int, string}> $replacements`
- `@param array<string, array{0: int, 1: int}> $bookmarks`
- `@return array{0: string, 1: array<string, array{0: int, 1: int}>, 2: int} the source, the bookmarks, the cursor`

### static `classValue(?array $pending, array $attributes): ?string`

The class attribute's decoded value before class edits: a pending set, else the source's; null when there is none.

- `@param array{value: string|true}|null $pending`
- `@param list<array{lower: string, value: ?string}> $attributes`

### static `classList(?array $pending, array $attributes): array`

The class names before class edits.

- `@param array{value: string|true}|null $pending`
- `@param list<array{lower: string, value: ?string}> $attributes`
- `@return list<string>`

Internals: `rebuiltClassValue()` (private, line 238), `existingName()` (private, line 265), `escape()` (private, line 275)


## Escaped

`final readonly class Minn\Html\Escaped` · `public/minn/src/Minn/Html/Escaped.php`

An attribute value that comes already escaped (a URL through esc_url), written as it is.

Used by: `Minn\Html\Tags`

```php
__construct(string $value)
```

- readonly `string $value`


## Scanner

`final class Minn\Html\Scanner` · `public/minn/src/Minn/Html/Scanner.php`

Reads one token's shape out of raw HTML at an offset: a tag with its
name and attributes, a comment of one of the reference's kinds, a
doctype, or a processing instruction. Pure and stateless; null means the
input ends before the token does, which is where a streaming reader pauses.

Used by: `Minn\Html\Tags`, `Minn\Html\Tree\Builder`

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

What "<?" opens at $at: a processing instruction when an ASCII
alphanumeric target (not "xml") is followed by space, "?" or ">"
(its text after the space, up to "?>" or ">"); a PI lookalike comment
for another XML name closed by "?>"; otherwise an invalid comment.

- `@return array{kind: string, start: int, end: int, textStart: int, textLength: int, target?: string, commentType?: string, fullStart?: int, fullLength?: int}|null`

### static `attributeValue(array $attributes, string $lower): string|true|null`

A source attribute's decoded value (true when it has none), or null when the tag lacks it.

- `@param list<array{lower: string, value: ?string}> $attributes`

### static `opensMarkup(string $html, int $at): bool`

Whether the "<" at $at opens markup: a tag, a closer, "<!" or "<?".

### static `textEnd(string $html, int $at): int`

Where text starting at $at ends: at the next "<" that opens markup, before a "<" ending the input, or at the end.

### static `cdata(string $html, int $at): ?array`

A CDATA section in foreign content, "<![CDATA[" to "]]>"; null when it does not close.

- `@return array{kind: string, start: int, end: int, textStart: int, textLength: int}|null`

### static `doctype(string $body): array`

A doctype's body split into its name and public and system identifiers.

- `@return array{name: ?string, public: ?string, system: ?string}`

### static `isSpace(string $c): bool`

Whether a byte is HTML whitespace.

Internals: `attribute()` (private, line 250), `comment()` (private, line 299)


## TagQuery

`final readonly class Minn\Html\TagQuery` · `public/minn/src/Minn/Html/TagQuery.php`

A tag processor's next_tag() query: a tag name (any case), a class, the
nth match to stop at, and whether closers count.

Used by: `Minn\Html\Tags`

- readonly `?string $tagName`
- readonly `?string $className`
- readonly `int $offset`
- readonly `string $closers`

### static `from(array $query): self`

The query read from the array a caller passes.

- `@param array{tag_name?: ?string, class_name?: ?string, match_offset?: int, tag_closers?: string} $query`

### `matches(string $name, int $closer, callable $hasClass): bool`

Whether a tag (by name, closer or not, and a class test) matches, before the offset is counted.


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
- const `PRESUMPTUOUS` = `'#presumptuous-tag'`
- const `COMMENT_HTML` = `'COMMENT_AS_HTML_COMMENT'`
- const `COMMENT_ABRUPT` = `'COMMENT_AS_ABRUPTLY_CLOSED_COMMENT'`
- const `COMMENT_INVALID` = `'COMMENT_AS_INVALID_HTML'`
- const `COMMENT_CDATA` = `'COMMENT_AS_CDATA_LOOKALIKE'`
- const `COMMENT_PI` = `'COMMENT_AS_PI_NODE_LOOKALIKE'`
- const `RAW_TEXT` = `array (   0 => 'script',   1 => 'style',   2 => 'textarea',   3 => 'title',   4 => 'xmp',   5 => 'iframe',   6 => 'noembed',   7 => 'noframes', )` — Elements whose body is raw text up to the closer.
- const `RAW_DECODED` = `array (   0 => 'textarea',   1 => 'title', )`
- const `MAX_BOOKMARKS` = `10`

Used by: `Minn\Html\Edits`, `Minn\Html\Scanner`, `Minn\Html\Tree\Builder`

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

The current tag name, upper-cased; a processing instruction's target, or a PI lookalike's, as written.

### `isCloser(): bool`

Whether the current tag is a closer.

### `selfClosing(): bool`

Whether the current tag has the self-closing flag.

### `commentType(): ?string`

The current comment's kind.

### `fullCommentText(): ?string`

The current comment's whole text (a funky comment's is its text).

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

The current tag's classes. @return list<string> distinct class names after pending edits

- `@return list<string> distinct class names after pending edits`

### `hasClass(string $class): bool`

Whether the current tag has a class.

### `setAttribute(string $name, Minn\Html\Escaped|string|int|float|bool|null $value): bool`

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

### `parseAs(string $namespace, string $tagBodies = 'html'): void`

Reads what follows as content of a namespace: outside HTML "<![CDATA["
opens a CDATA section. Tag bodies are read as HTML ("html": SCRIPT,
STYLE and the other raw text elements take their text whole) unless
the start tags here are foreign content ("foreign").

### `allowBookmarks(int $max): void`

Raises how many bookmarks may be held at once.

### `rewind(): void`

Back to the start of the document, with every update written in and the bookmarks kept.

### `tokenSpan(): array`

Where the current token sits in the document: [start, end].

### `textSpan(): array`

Where the current text token's text sits: [start, length].

### `source(int $start, int $length): string`

The source between two offsets, updates written in.

### `bookmarkStart(string $name): ?int`

Where a bookmark starts, or null when there is none of that name.

### `html(): string`

The document with every update written in.

Internals: `setToken()` (private, line 134), `resetToken()` (private, line 144), `takeTag()` (private, line 158), `take()` (private, line 188), `baseClassList()` (private, line 368), `baseClassValue()` (private, line 373), `flush()` (private, line 540), `applyReplacements()` (private, line 561), `rescanCurrent()` (private, line 567), `rescan()` (private, line 583)


## TokenMap

`final class Minn\Html\TokenMap` · `public/minn/src/Minn/Html/TokenMap.php`

A map from words to replacements that reads the longest word at a place
in a text (as character reference names are read): words shorter than
the key length stand alone, the others are grouped by their first bytes,
longest first.

```php
__construct(array $mappings, int $keyLength)
```
- `@param array<string, string> $mappings`


### `keyLength(): int`

How many leading bytes group the words.

### `contains(string $word, string $caseSensitivity): bool`

Whether the word is in the map ("case-sensitive" or "ascii-case-insensitive").

### `read(string $text, int $offset, string $caseSensitivity): ?array`

The replacement for the longest word at $offset, and the word's length; null when none starts there.

- `@return array{0: string, 1: int}|null`

### `toArray(): array`

Every word and its replacement: the short words first, then each group longest first.

- `@return array<string, string>`

Internals: `same()` (private, line 95)

