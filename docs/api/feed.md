# `Minn\Feed`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Charset`](#charset) | final class | 45 | A feed document as UTF-8. The encoding comes from a byte-order mark, |
| [`Dates`](#dates) | final class | 16 | Feed dates (RFC 822, W3C, and the looser forms feeds carry) as Unix timestamps; a date without a zone is UTC. |
| [`Document`](#document) | final readonly class | 114 | A parsed feed: the tree and the format it was recognized as (the |
| [`FeedError`](#feederror) | final class | 3 | A feed that could not be read: the message is the one the reference reports. |
| [`Iri`](#iri) | final class | 109 | URLs in feeds: a reference resolved against its base (RFC 3986), then |
| [`Locator`](#locator) | final class | 54 | Feed autodiscovery: the feeds an HTML page names in its |
| [`Parts`](#parts) | final class | 140 | The repeating parts of a feed element read out of the tree as raw |
| [`Tree`](#tree) | final class | 146 | A feed document parsed into the nested arrays plugin code reads through |

## Charset

`final class Minn\Feed\Charset` · `public/minn/src/Minn/Feed/Charset.php`

A feed document as UTF-8. The encoding comes from a byte-order mark,
then the XML declaration, then the Content-Type charset, then UTF-8; a
document in another encoding is converted and its declaration rewritten,
so the parser only ever reads UTF-8.

Used by: `Minn\Feed\Document`

### static `toUtf8(string $body, string $contentType = ''): string`

The document converted to UTF-8, with no byte-order mark.

### static `declared(string $body): ?string`

The encoding the XML declaration names, if it names one.

Internals: `fromContentType()` (private, line 35), `convert()` (private, line 40)


## Dates

`final class Minn\Feed\Dates` · `public/minn/src/Minn/Feed/Dates.php`

Feed dates (RFC 822, W3C, and the looser forms feeds carry) as Unix timestamps; a date without a zone is UTC.

### static `parse(?string $text): ?int`

The timestamp, or null when the text names no date.


## Document

`final readonly class Minn\Feed\Document` · `public/minn/src/Minn/Feed/Document.php`

A parsed feed: the tree and the format it was recognized as (the
reference's type bits: RSS 0.90 1, the 0.9x versions 2 to 32, RSS 1.0 64,
RSS 2.0 128, Atom 0.3 256, Atom 1.0 512), with the lookups the feed
object is built on: the root's children, the channel's, the image's, and
the items of every format the document carries.

- const `NONE` = `0`
- const `RSS_090` = `1`
- const `RSS_10` = `64`
- const `RSS_20` = `128`
- const `ATOM_03` = `256`
- const `ATOM_10` = `512`
- const `RDF` = `'http://www.w3.org/1999/02/22-rdf-syntax-ns#'`
- const `RSS_VERSIONS` = `array (   '0.91' => 4,   '0.92' => 8,   '0.93' => 16,   '0.94' => 32, )`

```php
__construct(array $tree, int $type)
```
- `@param array{child?: array<string, mixed>} $tree`

- readonly `array $tree`
- readonly `int $type`

### static `parse(string $body, string $contentType = ''): self`

A document read from bytes; the type is NONE when it parsed but is no feed.

### static `fromTree(array $tree): self`

A document over a tree already parsed (a cached feed), its type read from the root.

- `@param array{child?: array<string, mixed>} $tree`

### `root(): ?array`

The root element (rss, rdf:RDF or feed), or null.

### `channel(): ?array`

The channel element: an RSS channel, or the Atom feed itself.

### `image(): ?array`

The image element: inside an RSS 2.0 channel, beside an RSS 1.0 one.

### `feedTags(string $ns, string $tag): ?array`

Children of the root element by namespace and name, or null.

### `channelTags(string $ns, string $tag): ?array`

Children of the channel by namespace and name, or null.

### `imageTags(string $ns, string $tag): ?array`

Children of the image by namespace and name, or null.

### `items(): array`

Every item node, in document order: Atom 1.0 and 0.3 entries, RSS 1.0
and 0.90 items beside the channel, RSS 2.0 items inside it.

- `@return list<array<string, mixed>>`


## FeedError

`final class Minn\Feed\FeedError` · `public/minn/src/Minn/Feed/FeedError.php` · implements `Stringable`, `Throwable`

A feed that could not be read: the message is the one the reference reports.

Used by: `Minn\Feed\Tree`


## Iri

`final class Minn\Feed\Iri` · `public/minn/src/Minn/Feed/Iri.php`

URLs in feeds: a reference resolved against its base (RFC 3986), then
normalized the way the reference prints them: scheme and host in lower
case, the default port dropped, dot segments removed, and every byte a
URL may not carry (spaces, angle brackets, non-ASCII) percent-encoded.

- const `DEFAULT_PORTS` = `array (   'http' => 80,   'https' => 443,   'ftp' => 21, )`

Used by: `Minn\Feed\Locator`, `Minn\Feed\Tree`

### static `resolve(string $base, string $reference): ?string`

The reference resolved against the base and normalized; null when neither is absolute.

### static `clean(string $url): string`

An absolute URL normalized; anything else returned as given.

Internals: `split()` (private, line 50), `merge()` (private, line 56), `normalize()` (private, line 65), `removeDots()` (private, line 91), `encode()` (private, line 117)


## Locator

`final class Minn\Feed\Locator` · `public/minn/src/Minn/Feed/Locator.php`

Feed autodiscovery: the feeds an HTML page names in its
<link rel="alternate"> elements with a feed type, in page order, each
resolved against the page (or its <base href>).

- const `TYPES` = `array (   0 => 'application/rss+xml',   1 => 'application/atom+xml',   2 => 'application/rdf+xml',   3 => 'application/xml',   4 => 'text/xml', )`

### static `looksLikeFeed(string $body, string $contentType): bool`

Whether a response looks like a feed document rather than a page or text.

### static `alternates(string $html, string $pageUrl): array`

The feed URLs a page names, resolved, in page order, each once.

- `@return list<string>`

Internals: `attributes()` (private, line 56)


## Parts

`final class Minn\Feed\Parts` · `public/minn/src/Minn/Feed/Parts.php`

The repeating parts of a feed element read out of the tree as raw
values: links by relation, categories, and enclosures (Media RSS
contents, Atom enclosure links, RSS enclosures). The feed object
sanitizes and wraps them; each raw value travels with the node it came
from, so a relative URL resolves against that node's base.

- const `IANA` = `'http://www.iana.org/assignments/relation/'`
- const `MEDIA` = `'http://search.yahoo.com/mrss/'`
- const `DC` = `array (   0 => 'http://purl.org/dc/elements/1.1/',   1 => 'http://purl.org/dc/elements/1.0/', )`

### static `links(array $child, array $plain, array $more, Closure $iri): array`

Links by relation: Atom links (rel defaults to alternate, IANA names
answer to both spellings), then the RSS-style link elements and the
given extra alternates.

- `@param array<string, array<string, list<array<string, mixed>>>> $child the element's children`
- `@param list<array{0: string, 1: string}> $plain RSS-style link tags, by namespace and name`
- `@param list<string> $more alternates already in their final form`
- `@param Closure(string, array<string, mixed>): string $iri a URL as the feed hands it out, given its node`
- `@return array<string, list<string>>`

### static `categories(array $child): array`

Categories as raw text: Atom (term, scheme, label), RSS 2.0 (the text,
the domain as scheme), Dublin Core subjects.

- `@param array<string, array<string, list<array<string, mixed>>>> $child`
- `@return list<array{term: ?string, scheme: ?string, label: ?string, type: string}>`

### static `enclosures(array $item): array`

An item's enclosures as raw fields, Media RSS contents first (with the
item's thumbnails when a content has none of its own), then Atom
enclosure links, then RSS enclosures. "node" is where the URL came from.

- `@param array<string, mixed> $item the item's node`
- `@return list<array<string, mixed>>`

### static `thumbnails(array $node): array`

The media:thumbnail URLs directly under an element, each with its node.

- `@param array<string, mixed> $node`
- `@return list<array{0: string, 1: array<string, mixed>}>`

Internals: `media()` (private, line 145)


## Tree

`final class Minn\Feed\Tree` · `public/minn/src/Minn/Feed/Tree.php`

A feed document parsed into the nested arrays plugin code reads through
get_item_tags() and friends: each element is a node holding its own text
("data", whitespace between children included), its attributes grouped
by namespace, the xml:base and xml:lang in force, and its children by
namespace and local name. Two shapes differ from plain XML: an Atom text
construct of type "xhtml" keeps its markup as text, and an RSS title is
stored HTML-escaped. Named HTML references are read as characters unless
the document brings its own DOCTYPE.

- const `XML` = `'http://www.w3.org/XML/1998/namespace'`
- const `ATOM_10` = `'http://www.w3.org/2005/Atom'`
- const `ATOM_03` = `'http://purl.org/atom/ns#'`
- const `RSS_10` = `'http://purl.org/rss/1.0/'`
- const `RSS_090` = `'http://my.netscape.com/rdf/simple/0.9/'`
- const `SEPARATOR` = `''`
- const `VOID` = `array (   0 => 'area',   1 => 'base',   2 => 'br',   3 => 'col',   4 => 'embed',   5 => 'hr',   6 => 'img',   7 => 'input',   8 => 'link',   9 => 'meta',   10 => 'param',   11 => 'source',   12 => 'track',   13 => 'wbr', )`

Used by: `Minn\Feed\Document`, `Minn\Feed\Parts`


### static `parse(string $utf8): array`

The document's elements under a root holding only "child".

- `@return array{child: array<string, array<string, list<array<string, mixed>>>>}`

Internals: `prepare()` (private, line 48), `build()` (private, line 65), `open()` (private, line 79), `close()` (private, line 112), `text()` (private, line 138), `append()` (private, line 143), `split()` (private, line 151), `markupAttributes()` (private, line 157)

