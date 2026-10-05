# `Minn\Html\Tree`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`BodyRules`](#bodyrules) | final class | 418 | The "in body" insertion mode, with select handled in body as the current |
| [`Builder`](#builder) | final class | 551 | The HTML tree construction algorithm over the engine's tokenizer, as the |
| [`Compat`](#compat) | final class | 79 | The compatibility mode a doctype indicates, by the identifier lists in |
| [`Event`](#event) | final readonly class | 17 | One step of the walk the HTML processor reports: a node opened (pushed) |
| [`ForeignRules`](#foreignrules) | final class | 73 | The rules for content in SVG and MathML: text and comments stay in the |
| [`Formatting`](#formatting) | final class | 89 | The list of active formatting elements, with its markers (null): the |
| [`HeadRules`](#headrules) | final class | 276 | The insertion modes around the body: initial (doctype and the |
| [`Node`](#node) | final class | 109 | A node the tree builder places: an element (named as the HTML API |
| [`OpenElements`](#openelements) | final class | 125 | The stack of open elements and the spec's scope tests over it: an |
| [`Serializer`](#serializer) | final class | 72 | Normalized HTML for the processor's walk, token by token: tag names |
| [`TableRules`](#tablerules) | final class | 292 | The table insertion modes: in table (and its text), in caption, in |
| [`Token`](#token) | final readonly class | 45 | A token as the tree builder reads it: a tag (name upper-cased), a run of |
| [`Unsupported`](#unsupported) | final class | 16 | Where the reference's HTML processor stops: markup whose tree it does |

## BodyRules

`final class Minn\Html\Tree\BodyRules` · `public/minn/src/Minn/Html/Tree/BodyRules.php`

The "in body" insertion mode, with select handled in body as the current
spec does (no "in select" mode). The adoption agency runs its simple
cases (the formatting element is closed with nothing special inside it);
the reference stops on the others, and on an end tag for a formatting
element that is not active.

- const `FORMATTING` = `array (   0 => 'A',   1 => 'B',   2 => 'BIG',   3 => 'CODE',   4 => 'EM',   5 => 'FONT',   6 => 'I',   7 => 'NOBR',   8 => 'S',   9 => 'SMALL',   10 => 'STRIKE',   11 => 'STRONG',   12 => 'TT',   13 => 'U', )`
- const `SPECIAL` = `array (   0 => 'ADDRESS',   1 => 'APPLET',   2 => 'AREA',   3 => 'ARTICLE',   4 => 'ASIDE',   5 => 'BASE',   6 => 'BASEFONT',   7 => 'BGSOUND',   8 => 'BLOCKQUOTE',   9 => 'BODY',   10 => 'BR',   11 => 'BUTTON',   12 => 'CAPTION',   13 => 'CENTER',   14 => 'COL',   15 => 'COLGROUP',   16 => 'DD',   17 => 'DETAILS',   18 => 'DIR',   19 => 'DIV',   20 => 'DL',   21 => 'DT',   22 => 'EMBED',   23 => 'FIELDSET',   24 => 'FIGCAPTION',   25 => 'FIGURE',   26 => 'FOOTER',   27 => 'FORM',   28 => 'FRAME',   29 => 'FRAMESET',   30 => 'H1',   31 => 'H2',   32 => 'H3',   33 => 'H4',   34 => 'H5',   35 => 'H6',   36 => 'HEAD',   37 => 'HEADER',   38 => 'HGROUP',   39 => 'HR',   40 => 'HTML',   41 => 'IFRAME',   42 => 'IMG',   43 => 'INPUT',   44 => 'KEYGEN',   45 => 'LI',   46 => 'LINK',   47 => 'LISTING',   48 => 'MAIN',   49 => 'MARQUEE',   50 => 'MENU',   51 => 'META',   52 => 'NAV',   53 => 'NOEMBED',   54 => 'NOFRAMES',   55 => 'NOSCRIPT',   56 => 'OBJECT',   57 => 'OL',   58 => 'P',   59 => 'PARAM',   60 => 'PLAINTEXT',   61 => 'PRE',   62 => 'SCRIPT',   63 => 'SEARCH',   64 => 'SECTION',   65 => 'SELECT',   66 => 'SOURCE',   67 => 'STYLE',   68 => 'SUMMARY',   69 => 'TABLE',   70 => 'TBODY',   71 => 'TD',   72 => 'TEMPLATE',   73 => 'TEXTAREA',   74 => 'TFOOT',   75 => 'TH',   76 => 'THEAD',   77 => 'TITLE',   78 => 'TR',   79 => 'TRACK',   80 => 'UL',   81 => 'WBR',   82 => 'XMP', )`
- const `BLOCKS` = `array (   0 => 'ADDRESS',   1 => 'ARTICLE',   2 => 'ASIDE',   3 => 'BLOCKQUOTE',   4 => 'CENTER',   5 => 'DETAILS',   6 => 'DIALOG',   7 => 'DIR',   8 => 'DIV',   9 => 'DL',   10 => 'FIELDSET',   11 => 'FIGCAPTION',   12 => 'FIGURE',   13 => 'FOOTER',   14 => 'HEADER',   15 => 'HGROUP',   16 => 'MAIN',   17 => 'MENU',   18 => 'NAV',   19 => 'OL',   20 => 'P',   21 => 'SEARCH',   22 => 'SECTION',   23 => 'SUMMARY',   24 => 'UL', )`
- const `BLOCK_ENDS` = `array (   0 => 'ADDRESS',   1 => 'ARTICLE',   2 => 'ASIDE',   3 => 'BLOCKQUOTE',   4 => 'BUTTON',   5 => 'CENTER',   6 => 'DETAILS',   7 => 'DIALOG',   8 => 'DIR',   9 => 'DIV',   10 => 'DL',   11 => 'FIELDSET',   12 => 'FIGCAPTION',   13 => 'FIGURE',   14 => 'FOOTER',   15 => 'HEADER',   16 => 'HGROUP',   17 => 'LISTING',   18 => 'MAIN',   19 => 'MENU',   20 => 'NAV',   21 => 'OL',   22 => 'PRE',   23 => 'SEARCH',   24 => 'SECTION',   25 => 'SUMMARY',   26 => 'UL', )`
- const `HEADINGS` = `array (   0 => 'H1',   1 => 'H2',   2 => 'H3',   3 => 'H4',   4 => 'H5',   5 => 'H6', )`

Used by: `Minn\Html\Tree\Builder`, `Minn\Html\Tree\HeadRules`, `Minn\Html\Tree\TableRules`

### static `process(Minn\Html\Tree\Builder $b, Minn\Html\Tree\Token $t): void`

Processes one token in the "in body" insertion mode.

### static `isSpecial(Minn\Html\Tree\Node $node): bool`

Whether the node is in the spec's "special" category.

Internals: `text()` (private, line 33), `startTag()` (private, line 45), `endTag()` (private, line 77), `outerStart()` (private, line 95), `outerEnd()` (private, line 106), `block()` (private, line 118), `heading()` (private, line 124), `pre()` (private, line 133), `form()` (private, line 141), `listItem()` (private, line 154), `button()` (private, line 174), `anchor()` (private, line 185), `nobr()` (private, line 198), `formatting()` (private, line 208), `withMarker()` (private, line 214), `table()` (private, line 222), `voidElement()` (private, line 232), `hr()` (private, line 249), `rawText()` (private, line 259), `select()` (private, line 269), `option()` (private, line 280), `ruby()` (private, line 291), `foreign()` (private, line 299), `ordinary()` (private, line 310), `closeBlock()` (private, line 316), `closeForm()` (private, line 325), `closeParagraph()` (private, line 343), `closeListItem()` (private, line 351), `closeHeading()` (private, line 360), `closeWithMarker()` (private, line 369), `adoptionAgency()` (private, line 380), `anyOtherEnd()` (private, line 408)


## Builder

`final class Minn\Html\Tree\Builder` · `public/minn/src/Minn/Html/Tree/Builder.php`

The HTML tree construction algorithm over the engine's tokenizer, as the
reference's HTML processor runs it: tokens go through the insertion
modes (HeadRules, BodyRules, TableRules) or the foreign content rules,
and every node opened or closed becomes an Event with its breadcrumbs.
Text is read one kind at a time (NUL bytes, white space, the rest). At
the end of the input every open element closes; nothing implied is
added. Where the reference does not build a tree (Unsupported), the walk
stops with that error.

- const `FRAGMENT` = `'fragment'`
- const `DOCUMENT` = `'document'`

Used by: `Minn\Html\Tree\BodyRules`, `Minn\Html\Tree\ForeignRules`, `Minn\Html\Tree\HeadRules`, `Minn\Html\Tree\TableRules`

```php
__construct(Minn\Html\Tags $tags, string $kind)
```


### `reset(): void`

Back to the state before the first token.

### `next(): ?Minn\Html\Tree\Event`

The next opened or closed node, or null at the end, at an incomplete token, or once unsupported markup stopped the walk.

### `halt(): void`

Stops the walk for good (a failed seek leaves the processor like this).

### `error(): ?Minn\Html\Tree\Unsupported`

Why the walk stopped early, if it did.

### `breadcrumbs(): array`

The breadcrumbs of the open elements now.

- `@return list<string>`

### `token(): Minn\Html\Tree\Token`

The token being processed.

### `stack(): Minn\Html\Tree\OpenElements`

The stack of open elements.

### `formatting(): Minn\Html\Tree\Formatting`

The list of active formatting elements.

### `isFragment(): bool`

Whether this builder parses a fragment (in a BODY context) rather than a document.

### `mode(): string`

The insertion mode.

### `switchTo(string $mode): void`

Switches the insertion mode.

### `reprocessIn(string $mode): void`

Switches the insertion mode and processes the current token again under it.

### `process(Minn\Html\Tree\Token $token): void`

Processes a token under the current insertion mode, or the foreign content rules when they apply.

### `processIn(string $mode, Minn\Html\Tree\Token $token): void`

Processes a token using the rules of an insertion mode other than the current one.

### `framesetOk(): bool`

Whether the frameset-ok flag is still set.

### `framesetNotOk(): void`

Clears the frameset-ok flag.

### `compat(): string`

The document's compatibility mode ("no-quirks", "limited-quirks", "quirks").

### `setCompat(string $compat): void`

Sets the compatibility mode the doctype indicated.

### `headPointer(): ?Minn\Html\Tree\Node`

The head element pointer.

### `formPointer(): ?Minn\Html\Tree\Node`

The form element pointer.

### `setFormPointer(?Minn\Html\Tree\Node $node): void`

Sets (or clears) the form element pointer.

### `templateModes(): array`

The stack of template insertion modes.

- `@return list<string>`

### `pushTemplateMode(string $mode): void`

Pushes a template insertion mode.

### `popTemplateMode(): void`

Pops the current template insertion mode.

### `adjustedCurrent(): ?Minn\Html\Tree\Node`

The adjusted current node: the context element when only the fragment's root is open.

### `insert(string $namespace = 'html'): Minn\Html\Tree\Node`

Inserts an element for the current start tag, real, in the namespace.

### `insertImplied(string $name): Minn\Html\Tree\Node`

Inserts an element the markup implied.

### `insertLeafElement(string $namespace = 'html'): Minn\Html\Tree\Node`

Inserts and at once closes an element for the current start tag: a void element, one read whole, or self-closed foreign content.

### `insertLeaf(string $namespace = 'html'): void`

Inserts a leaf (text, comment, CDATA, processing instruction) for the current token, in the namespace.

### `insertDocumentLeaf(): void`

Inserts a leaf at the top of the document (a doctype or a comment before or around the html element).

### `pop(): ?Minn\Html\Tree\Node`

Closes the current node as implied.

### `popUntil(string ...$names): void`

Pops until an HTML element of one of the names closes; when the current end tag names it, that last close is real.

### `popUntilNode(Minn\Html\Tree\Node $target): void`

Pops until this very node closes.

### `generateImpliedEndTags(string $except = ''): void`

Generates implied end tags, leaving an element of the name open.

### `generateAllImpliedEndTags(): void`

Generates all implied end tags thoroughly (table parts as well).

### `closeP(): void`

Closes a P element: implied end tags but P's, then pops through the P.

### `closePInButtonScope(): void`

Closes a P element when one is in button scope.

### `reconstructFormatting(): void`

Reconstructs the active formatting elements; the reference stops when any would have to be reopened.

### `skipNextNewline(): void`

Asks the parser to drop one leading line feed from the next token (after PRE, LISTING).

### `resetInsertionMode(): void`

Resets the insertion mode from the stack of open elements.

### `unsupported(string $message): void`

Stops: the reference's processor does not build a tree for this markup.

### `attribute(string $name): string|true|null`

The current tag's attribute, decoded; null when absent.

### `doctype(): array`

The doctype's name and identifiers, for the compatibility mode. @return array{name: ?string, public: ?string, system: ?string}

- `@return array{name: ?string, public: ?string, system: ?string}`

Internals: `step()` (private, line 428), `read()` (private, line 454), `textRuns()` (private, line 473), `textValue()` (private, line 491), `usesHtmlRules()` (private, line 498), `finish()` (private, line 515), `pushNode()` (private, line 523), `event()` (private, line 529), `keptAttributes()` (private, line 543)


## Compat

`final class Minn\Html\Tree\Compat` · `public/minn/src/Minn/Html/Tree/Compat.php`

The compatibility mode a doctype indicates, by the identifier lists in
the HTML standard: quirks for legacy and missing doctypes, limited
quirks for the XHTML 1.0 transitional and frameset ones (and HTML 4.01's
with a system identifier), no quirks otherwise.

- const `QUIRKS_PUBLIC` = `array (   0 => '-//w3o//dtd w3 html strict 3.0//en//',   1 => '-/w3c/dtd html 4.0 transitional/en',   2 => 'html', )`
- const `QUIRKS_PREFIXES` = `array (   0 => '+//silmaril//dtd html pro v0r11 19970101//',   1 => '-//as//dtd html 3.0 aswedit + extensions//',   2 => '-//advasoft ltd//dtd html 3.0 aswedit + extensions//',   3 => '-//ietf//dtd html 2.0 level 1//',   4 => '-//ietf//dtd html 2.0 level 2//',   5 => '-//ietf//dtd html 2.0 strict level 1//',   6 => '-//ietf//dtd html 2.0 strict level 2//',   7 => '-//ietf//dtd html 2.0 strict//',   8 => '-//ietf//dtd html 2.0//',   9 => '-//ietf//dtd html 2.1e//',   10 => '-//ietf//dtd html 3.0//',   11 => '-//ietf//dtd html 3.2 final//',   12 => '-//ietf//dtd html 3.2//',   13 => '-//ietf//dtd html 3//',   14 => '-//ietf//dtd html level 0//',   15 => '-//ietf//dtd html level 1//',   16 => '-//ietf//dtd html level 2//',   17 => '-//ietf//dtd html level 3//',   18 => '-//ietf//dtd html strict level 0//',   19 => '-//ietf//dtd html strict level 1//',   20 => '-//ietf//dtd html strict level 2//',   21 => '-//ietf//dtd html strict level 3//',   22 => '-//ietf//dtd html strict//',   23 => '-//ietf//dtd html//',   24 => '-//metrius//dtd metrius presentational//',   25 => '-//microsoft//dtd internet explorer 2.0 html strict//',   26 => '-//microsoft//dtd internet explorer 2.0 html//',   27 => '-//microsoft//dtd internet explorer 2.0 tables//',   28 => '-//microsoft//dtd internet explorer 3.0 html strict//',   29 => '-//microsoft//dtd internet explorer 3.0 html//',   30 => '-//microsoft//dtd internet explorer 3.0 tables//',   31 => '-//netscape comm. corp.//dtd html//',   32 => '-//netscape comm. corp.//dtd strict html//',   33 => '-//o\'reilly and associates//dtd html 2.0//',   34 => '-//o\'reilly and associates//dtd html extended 1.0//',   35 => '-//o\'reilly and associates//dtd html extended relaxed 1.0//',   36 => '-//sq//dtd html 2.0 hotmetal + extensions//',   37 => '-//softquad software//dtd hotmetal pro 6.0::19990601::extensions to html 4.0//',   38 => '-//softquad//dtd hotmetal pro 4.0::19970916::extensions to html 4.0//',   39 => '-//spyglass//dtd html 2.0 extended//',   40 => '-//sun microsystems corp.//dtd hotjava html//',   41 => '-//sun microsystems corp.//dtd hotjava strict html//',   42 => '-//w3c//dtd html 3 1995-03-24//',   43 => '-//w3c//dtd html 3.2 draft//',   44 => '-//w3c//dtd html 3.2 final//',   45 => '-//w3c//dtd html 3.2//',   46 => '-//w3c//dtd html 3.2s draft//',   47 => '-//w3c//dtd html 4.0 frameset//',   48 => '-//w3c//dtd html 4.0 transitional//',   49 => '-//w3c//dtd html experimental 19960712//',   50 => '-//w3c//dtd html experimental 970421//',   51 => '-//w3c//dtd w3 html//',   52 => '-//w3o//dtd w3 html 3.0//',   53 => '-//webtechs//dtd mozilla html 2.0//',   54 => '-//webtechs//dtd mozilla html//', )`
- const `HTML401` = `array (   0 => '-//w3c//dtd html 4.01 frameset//',   1 => '-//w3c//dtd html 4.01 transitional//', )`
- const `XHTML10` = `array (   0 => '-//w3c//dtd xhtml 1.0 frameset//',   1 => '-//w3c//dtd xhtml 1.0 transitional//', )`

Used by: `Minn\Html\Tree\HeadRules`

### static `of(array $doctype): string`

The mode ("no-quirks", "limited-quirks", "quirks") for a doctype's pieces.

- `@param array{name: ?string, public: ?string, system: ?string} $doctype`

### static `read(string $html): ?array`

A whole doctype token read: its name (lower-cased), identifiers and
mode; a malformed one is quirks. Null when the text is not one doctype.

- `@return array{name: ?string, public: ?string, system: ?string, mode: string}|null`

Internals: `startsWithAny()` (private, line 82)


## Event

`final readonly class Minn\Html\Tree\Event` · `public/minn/src/Minn/Html/Tree/Event.php`

One step of the walk the HTML processor reports: a node opened (pushed)
or closed (popped), the breadcrumbs at that moment, and the offset of
the token behind it when the document wrote it (null when implied).

Used by: `Minn\Html\Tree\Builder`

```php
__construct(string $op, Minn\Html\Tree\Node $node, array $breadcrumbs, ?int $offset)
```
- `@param list<string> $breadcrumbs`

- readonly `string $op`
- readonly `Minn\Html\Tree\Node $node`
- readonly `array $breadcrumbs`
- readonly `?int $offset`

### `isCloser(): bool`

Whether this event closes its node.


## ForeignRules

`final class Minn\Html\Tree\ForeignRules` · `public/minn/src/Minn/Html/Tree/ForeignRules.php`

The rules for content in SVG and MathML: text and comments stay in the
foreign namespace, an HTML-only start tag (or a font with color, face or
size) breaks out back to HTML, other start tags open elements in the
current foreign namespace, and an end tag closes the nearest element of
its name or falls through to the HTML rules.

- const `BREAKOUT` = `array (   0 => 'B',   1 => 'BIG',   2 => 'BLOCKQUOTE',   3 => 'BODY',   4 => 'BR',   5 => 'CENTER',   6 => 'CODE',   7 => 'DD',   8 => 'DIV',   9 => 'DL',   10 => 'DT',   11 => 'EM',   12 => 'EMBED',   13 => 'H1',   14 => 'H2',   15 => 'H3',   16 => 'H4',   17 => 'H5',   18 => 'H6',   19 => 'HEAD',   20 => 'HR',   21 => 'I',   22 => 'IMG',   23 => 'LI',   24 => 'LISTING',   25 => 'MENU',   26 => 'META',   27 => 'NOBR',   28 => 'OL',   29 => 'P',   30 => 'PRE',   31 => 'RUBY',   32 => 'S',   33 => 'SMALL',   34 => 'SPAN',   35 => 'STRONG',   36 => 'STRIKE',   37 => 'SUB',   38 => 'SUP',   39 => 'TABLE',   40 => 'TT',   41 => 'U',   42 => 'UL',   43 => 'VAR', )`

Used by: `Minn\Html\Tree\Builder`

### static `process(Minn\Html\Tree\Builder $b, Minn\Html\Tree\Token $t): void`

Processes one token by the rules for foreign content.

Internals: `text()` (private, line 32), `breaksOut()` (private, line 40), `breakOut()` (private, line 54), `start()` (private, line 62), `end()` (private, line 71)


## Formatting

`final class Minn\Html\Tree\Formatting` · `public/minn/src/Minn/Html/Tree/Formatting.php`

The list of active formatting elements, with its markers (null): the
formatting elements opened since the last marker that may need
reopening, three of a kind at most (the "Noah's Ark" clause).

Used by: `Minn\Html\Tree\Builder`


### `push(Minn\Html\Tree\Node $node): void`

Adds a formatting element, dropping the earliest of three identical ones since the last marker.

### `insertMarker(): void`

Adds a marker (a cell, caption, template, applet, marquee or object opened).

### `clearToLastMarker(): void`

Drops entries back to and including the last marker.

### `remove(Minn\Html\Tree\Node $node): void`

Drops one element from the list.

### `contains(Minn\Html\Tree\Node $node): bool`

Whether this very element is in the list.

### `lastNamed(string $name): ?Minn\Html\Tree\Node`

The last HTML element of the name since the last marker.

### `needsReconstruction(Minn\Html\Tree\OpenElements $stack): bool`

Whether reopening would be needed: the last entry is an element no longer open.

### `names(): array`

The entries' names, markers left out.

- `@return list<string>`

Internals: `sameAttributes()` (private, line 92)


## HeadRules

`final class Minn\Html\Tree\HeadRules` · `public/minn/src/Minn/Html/Tree/HeadRules.php`

The insertion modes around the body: initial (doctype and the
compatibility mode it indicates), before html, before head, in head, in
head noscript, after head, in template, after body, the frameset modes,
and after after body (where a comment would land outside the html
element, which the reference does not support).

- const `HEAD_LEAVES` = `array (   0 => 'BASE',   1 => 'BASEFONT',   2 => 'BGSOUND',   3 => 'LINK',   4 => 'META',   5 => 'TITLE',   6 => 'NOFRAMES',   7 => 'STYLE',   8 => 'SCRIPT', )`

Used by: `Minn\Html\Tree\BodyRules`, `Minn\Html\Tree\Builder`, `Minn\Html\Tree\TableRules`

### static `process(Minn\Html\Tree\Builder $b, string $mode, Minn\Html\Tree\Token $t): void`

Processes one token in one of these insertion modes.

Internals: `initial()` (private, line 36), `beforeHtml()` (private, line 55), `beforeHead()` (private, line 76), `openHead()` (private, line 90), `impliedHead()` (private, line 96), `inHead()` (private, line 102), `open()` (private, line 121), `closeHead()` (private, line 127), `leaveHead()` (private, line 133), `openTemplate()` (private, line 139), `closeTemplate()` (private, line 148), `inHeadNoscript()` (private, line 160), `closeNoscript()` (private, line 173), `leaveNoscript()` (private, line 179), `afterHead()` (private, line 185), `openBody()` (private, line 202), `impliedBody()` (private, line 209), `backIntoHead()` (private, line 215), `inTemplate()` (private, line 220), `afterBody()` (private, line 242), `frameset()` (private, line 253), `closeFrameset()` (private, line 268), `afterAfter()` (private, line 279)


## Node

`final class Minn\Html\Tree\Node` · `public/minn/src/Minn/Html/Tree/Node.php`

A node the tree builder places: an element (named as the HTML API
reports it, upper-cased, with its namespace) or a text, comment, doctype
or other leaf. Real when a token in the document made it (its offset is
where that token starts), virtual when the parser implied it.

- const `VOID` = `array (   0 => 'AREA',   1 => 'BASE',   2 => 'BASEFONT',   3 => 'BGSOUND',   4 => 'BR',   5 => 'COL',   6 => 'EMBED',   7 => 'FRAME',   8 => 'HR',   9 => 'IMG',   10 => 'INPUT',   11 => 'KEYGEN',   12 => 'LINK',   13 => 'META',   14 => 'PARAM',   15 => 'SOURCE',   16 => 'TRACK',   17 => 'WBR', )`
- const `ATOMIC` = `array (   0 => 'SCRIPT',   1 => 'STYLE',   2 => 'TEXTAREA',   3 => 'TITLE',   4 => 'XMP',   5 => 'IFRAME',   6 => 'NOEMBED',   7 => 'NOFRAMES', )` — Elements whose body the tokenizer reads whole, so they never take a closer.

Used by: `Minn\Html\Tree\BodyRules`, `Minn\Html\Tree\Builder`, `Minn\Html\Tree\Event`, `Minn\Html\Tree\Formatting`, `Minn\Html\Tree\OpenElements`, `Minn\Html\Tree\Serializer`

- readonly `string $type`
- readonly `string $name`
- readonly `string $namespace`
- readonly `?int $offset`

### static `element(string $name, string $namespace, ?int $offset, array $attributes = array ( )): self`

An element; the attributes kept are the ones the builder compares.

- `@param array<string, string|true> $attributes`

### static `leaf(string $type, string $name, string $namespace, ?int $offset, string $text): self`

A leaf: text, a comment, a doctype, a CDATA section, a processing instruction.

### `selfClosed(): self`

The same element, marked as written with "/>".

### `isSelfClosing(): bool`

Whether the element was written with "/>".

### `text(): string`

A leaf's text.

### `attributes(): array`

The attributes kept for comparison.

- `@return array<string, string|true>`

### `isHtml(string ...$names): bool`

Whether this is an HTML element with one of the names.

### `isIn(string $namespace, string ...$names): bool`

Whether this is an element of the namespace with one of the names.

### `expectsCloser(): bool`

Whether the element is closed by a closer of its own: not a leaf, not void, not read whole, not self-closed foreign content.

### `isHtmlIntegrationPoint(): bool`

Whether this element is an HTML integration point (SVG foreignObject, desc, title; MathML annotation-xml for HTML).

### `isMathTextIntegrationPoint(): bool`

Whether this element is a MathML text integration point.


## OpenElements

`final class Minn\Html\Tree\OpenElements` · `public/minn/src/Minn/Html/Tree/OpenElements.php`

The stack of open elements and the spec's scope tests over it: an
element is in scope when it is reached walking down from the current
node before any of the scope's boundary elements.

- const `DEFAULT_SCOPE` = `array (   'html' =>    array (     0 => 'APPLET',     1 => 'CAPTION',     2 => 'HTML',     3 => 'TABLE',     4 => 'TD',     5 => 'TH',     6 => 'MARQUEE',     7 => 'OBJECT',     8 => 'TEMPLATE',   ),   'math' =>    array (     0 => 'MI',     1 => 'MO',     2 => 'MN',     3 => 'MS',     4 => 'MTEXT',     5 => 'ANNOTATION-XML',   ),   'svg' =>    array (     0 => 'FOREIGNOBJECT',     1 => 'DESC',     2 => 'TITLE',   ), )`

Used by: `Minn\Html\Tree\Builder`, `Minn\Html\Tree\Formatting`


### `push(Minn\Html\Tree\Node $node): void`

Puts a node on top.

### `pop(): ?Minn\Html\Tree\Node`

Takes the current node off, or null when the stack is empty.

### `current(): ?Minn\Html\Tree\Node`

The current (bottom-most open) node.

### `at(int $index): ?Minn\Html\Tree\Node`

The node at a position, 0 being the root.

### `count(): int`

How many nodes are open.

### `all(): array`

Every open node, root first.

- `@return list<Node>`

### `containsNode(Minn\Html\Tree\Node $node): bool`

Whether this very node is open.

### `hasHtml(string $name): bool`

Whether an HTML element of the name is open anywhere.

### `inScope(string ...$names): bool`

Whether one of the HTML elements is in the default scope.

### `inListItemScope(string $name): bool`

Whether the HTML element is in list item scope (the default plus OL and UL).

### `inButtonScope(string $name): bool`

Whether the HTML element is in button scope (the default plus BUTTON).

### `inTableScope(string ...$names): bool`

Whether one of the HTML elements is in table scope (HTML, TABLE and TEMPLATE bound it).

### `inSelectScope(string $name): bool`

Whether the HTML element is in select scope (anything but OPTGROUP and OPTION bounds it).

Internals: `inSpecificScope()` (private, line 123)


## Serializer

`final class Minn\Html\Tree\Serializer` · `public/minn/src/Minn/Html/Tree/Serializer.php`

Normalized HTML for the processor's walk, token by token: tag names
lower-cased (SVG's mixed-case names and attributes restored), the first
of repeated attributes kept and double-quoted, text and attribute values
escaped (& < > " '), RCDATA escaped and raw text left alone, a line
break after the PRE, LISTING and TEXTAREA openers, self-closed foreign
elements written " />", funky comments and doctypes in a body dropped.

- const `SVG_TAGS` = `array (   'altglyph' => 'altGlyph',   'altglyphdef' => 'altGlyphDef',   'altglyphitem' => 'altGlyphItem',   'animatecolor' => 'animateColor',   'animatemotion' => 'animateMotion',   'animatetransform' => 'animateTransform',   'clippath' => 'clipPath',   'feblend' => 'feBlend',   'fecolormatrix' => 'feColorMatrix',   'fecomponenttransfer' => 'feComponentTransfer',   'fecomposite' => 'feComposite',   'feconvolvematrix' => 'feConvolveMatrix',   'fediffuselighting' => 'feDiffuseLighting',   'fedisplacementmap' => 'feDisplacementMap',   'fedistantlight' => 'feDistantLight',   'fedropshadow' => 'feDropShadow',   'feflood' => 'feFlood',   'fefunca' => 'feFuncA',   'fefuncb' => 'feFuncB',   'fefuncg' => 'feFuncG',   'fefuncr' => 'feFuncR',   'fegaussianblur' => 'feGaussianBlur',   'feimage' => 'feImage',   'femerge' => 'feMerge',   'femergenode' => 'feMergeNode',   'femorphology' => 'feMorphology',   'feoffset' => 'feOffset',   'fepointlight' => 'fePointLight',   'fespecularlighting' => 'feSpecularLighting',   'fespotlight' => 'feSpotLight',   'fetile' => 'feTile',   'feturbulence' => 'feTurbulence',   'foreignobject' => 'foreignObject',   'glyphref' => 'glyphRef',   'lineargradient' => 'linearGradient',   'radialgradient' => 'radialGradient',   'textpath' => 'textPath', )`
- const `SVG_ATTRIBUTES` = `array (   'attributename' => 'attributeName',   'attributetype' => 'attributeType',   'basefrequency' => 'baseFrequency',   'baseprofile' => 'baseProfile',   'calcmode' => 'calcMode',   'clippathunits' => 'clipPathUnits',   'diffuseconstant' => 'diffuseConstant',   'edgemode' => 'edgeMode',   'filterunits' => 'filterUnits',   'glyphref' => 'glyphRef',   'gradienttransform' => 'gradientTransform',   'gradientunits' => 'gradientUnits',   'kernelmatrix' => 'kernelMatrix',   'kernelunitlength' => 'kernelUnitLength',   'keypoints' => 'keyPoints',   'keysplines' => 'keySplines',   'keytimes' => 'keyTimes',   'lengthadjust' => 'lengthAdjust',   'limitingconeangle' => 'limitingConeAngle',   'markerheight' => 'markerHeight',   'markerunits' => 'markerUnits',   'markerwidth' => 'markerWidth',   'maskcontentunits' => 'maskContentUnits',   'maskunits' => 'maskUnits',   'numoctaves' => 'numOctaves',   'pathlength' => 'pathLength',   'patterncontentunits' => 'patternContentUnits',   'patterntransform' => 'patternTransform',   'patternunits' => 'patternUnits',   'pointsatx' => 'pointsAtX',   'pointsaty' => 'pointsAtY',   'pointsatz' => 'pointsAtZ',   'preservealpha' => 'preserveAlpha',   'preserveaspectratio' => 'preserveAspectRatio',   'primitiveunits' => 'primitiveUnits',   'refx' => 'refX',   'refy' => 'refY',   'repeatcount' => 'repeatCount',   'repeatdur' => 'repeatDur',   'requiredextensions' => 'requiredExtensions',   'requiredfeatures' => 'requiredFeatures',   'specularconstant' => 'specularConstant',   'specularexponent' => 'specularExponent',   'spreadmethod' => 'spreadMethod',   'startoffset' => 'startOffset',   'stddeviation' => 'stdDeviation',   'stitchtiles' => 'stitchTiles',   'surfacescale' => 'surfaceScale',   'systemlanguage' => 'systemLanguage',   'tablevalues' => 'tableValues',   'targetx' => 'targetX',   'targety' => 'targetY',   'textlength' => 'textLength',   'viewbox' => 'viewBox',   'viewtarget' => 'viewTarget',   'xchannelselector' => 'xChannelSelector',   'ychannelselector' => 'yChannelSelector',   'zoomandpan' => 'zoomAndPan', )`
- const `RAW` = `array (   0 => 'SCRIPT',   1 => 'STYLE',   2 => 'XMP',   3 => 'IFRAME',   4 => 'NOEMBED',   5 => 'NOFRAMES', )`

### static `qualifiedName(Minn\Html\Tree\Node $node): string`

An element's name as written: lower-case, SVG's mixed-case names restored.

### static `attributeName(Minn\Html\Tree\Node $node, string $name): string`

An attribute's name as written on an element of the node's namespace.

### static `open(Minn\Html\Tree\Node $node, array $attributes, string $text): string`

An opening tag; an element read whole (SCRIPT, TEXTAREA, ...) carries its text and closer.

- `@param list<array{0: string, 1: string|true}> $attributes in document order, repeats already dropped`

### static `close(Minn\Html\Tree\Node $node): string`

A closing tag.

### static `leaf(Minn\Html\Tree\Node $node, string $fullText, string $target): string`

A leaf: text escaped, a comment from its whole text, a CDATA section, a processing instruction; nothing for the rest.

Internals: `escape()` (private, line 82)


## TableRules

`final class Minn\Html\Tree\TableRules` · `public/minn/src/Minn/Html/Tree/TableRules.php`

The table insertion modes: in table (and its text), in caption, in
column group, in table body, in row, in cell. Anything that would be
foster-parented out of the table (text other than white space, most
elements) is where the reference stops.

- const `TABLE_PARTS` = `array (   0 => 'CAPTION',   1 => 'COL',   2 => 'COLGROUP',   3 => 'TBODY',   4 => 'TD',   5 => 'TFOOT',   6 => 'TH',   7 => 'THEAD',   8 => 'TR', )`

Used by: `Minn\Html\Tree\Builder`

### static `process(Minn\Html\Tree\Builder $b, string $mode, Minn\Html\Tree\Token $t): void`

Processes one token in one of the table insertion modes.

Internals: `inTable()` (private, line 30), `tableText()` (private, line 54), `clearToTableContext()` (private, line 65), `clearToTableBodyContext()` (private, line 72), `clearToRowContext()` (private, line 79), `openInTable()` (private, line 86), `impliedInTable()` (private, line 96), `nestedTable()` (private, line 103), `closeTable()` (private, line 113), `form()` (private, line 122), `inCaption()` (private, line 132), `closeCaption()` (private, line 150), `inColumnGroup()` (private, line 162), `closeColumnGroup()` (private, line 176), `leaveColumnGroup()` (private, line 186), `inTableBody()` (private, line 193), `openRow()` (private, line 205), `impliedRow()` (private, line 212), `closeBody()` (private, line 219), `leaveBody()` (private, line 229), `inRow()` (private, line 239), `openCell()` (private, line 251), `closeRow()` (private, line 259), `leaveRow()` (private, line 270), `inCell()` (private, line 277), `closeCell()` (private, line 288), `leaveCell()` (private, line 296)


## Token

`final readonly class Minn\Html\Tree\Token` · `public/minn/src/Minn/Html/Tree/Token.php`

A token as the tree builder reads it: a tag (name upper-cased), a run of
text (one kind at a time: NUL bytes, white space, or anything else), a
comment-like leaf, or the end of the input.

- const `CLOSER` = `1`
- const `SELF_CLOSING` = `2`

Used by: `Minn\Html\Tree\BodyRules`, `Minn\Html\Tree\Builder`, `Minn\Html\Tree\ForeignRules`, `Minn\Html\Tree\HeadRules`, `Minn\Html\Tree\TableRules`, `Minn\Html\Tree\Unsupported`

```php
__construct(string $type, string $name, int $flags, int $offset, string $text = '', string $kind = '')
```

- readonly `string $type`
- readonly `string $name`
- readonly `int $flags`
- readonly `int $offset`
- readonly `string $text`
- readonly `string $kind`

### `isStart(string ...$names): bool`

Whether this is a start tag with one of the names (any name when none are given).

### `isEnd(string ...$names): bool`

Whether this is an end tag with one of the names (any name when none are given).

### `selfClosing(): bool`

Whether the tag was written with "/>".

### `isText(string $kind): bool`

Whether this is a run of text of the kind ("null", "whitespace", "generic").

### `renamed(string $name, int $flags): self`

The same token under another name (an "image" start tag read as "img", "</br>" as "<br>").


## Unsupported

`final class Minn\Html\Tree\Unsupported` · `public/minn/src/Minn/Html/Tree/Unsupported.php` · implements `Stringable`, `Throwable`

Where the reference's HTML processor stops: markup whose tree it does
not build (foster parenting, the adoption agency's hard cases, PLAINTEXT,
content after </html>), with the token it stopped at and the state then.

Used by: `Minn\Html\Tree\Builder`

```php
__construct(string $message, Minn\Html\Tree\Token $token, string $tokenText, array $stack, array $formatting)
```
- `@param list<string> $stack the stack of open elements, root first`
- `@param list<string> $formatting the active formatting elements`

- readonly `Minn\Html\Tree\Token $token`
- readonly `string $tokenText`
- readonly `array $stack`
- readonly `array $formatting`

