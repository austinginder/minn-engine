# `Minn\Support`

escaping, serialized readers, small helpers

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Accents`](#accents) | final class | 21 | Accented and special Latin characters to their plain ASCII spelling; a character with no ASCII form stays as it is. |
| [`DirectoryListing`](#directorylisting) | final class | 32 | Walks a directory the way the filesystem API lists it: named entries, dot entries skipped, hidden ones optional, recursion optional. |
| [`Email`](#email) | final class | 61 | The address rules the reference applies: a local part from a fixed |
| [`Entities`](#entities) | final class | 52 | HTML special-character encoding with the reference's quote styles and its |
| [`FileHeaders`](#fileheaders) | final class | 25 | Header values from a plugin or theme file. The labels (Plugin Name, |
| [`FileTree`](#filetree) | final class | 70 | Whole-directory reads and copies. The engine's own installer, the update |
| [`Files`](#files) | final class | 61 | Recursive filesystem work behind WP_Filesystem_Direct: best-effort tree |
| [`Html`](#html) | final class | 78 |  |
| [`Ip`](#ip) | final class | 21 | Addresses with their identifying tail removed, for logs and analytics that |
| [`Json`](#json) | final class | 18 | Makes a value encodable: strings that are not valid UTF-8 get their high bytes replaced, recursively. |
| [`Kses`](#kses) | final class | 211 | The HTML a user without unfiltered_html may store. Tags outside the |
| [`Lists`](#lists) | final class | 88 | List shaping behind the facade's array utilities: the multi-field sort |
| [`Locale`](#locale) | final class | 31 | The locale's calendar and number vocabulary as data: the names a site |
| [`Markers`](#markers) | final class | 24 | The BEGIN/END marker blocks insert_with_markers() maintains in files like |
| [`Paths`](#paths) | final class | 64 | File-system path and permission spellings. |
| [`SearchReplace`](#searchreplace) | final class | 36 | String replace that walks serialized-PHP arrays of scalars without |
| [`Serialized`](#serialized) | final class | 195 | Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing |
| [`Time`](#time) | final class | 17 | Human-scale spans: a number of seconds as the largest whole unit it fills, rounded, never below one. |
| [`Url`](#url) | final class | 196 | URL shaping the escaping and query helpers share: the character cleanup |

## Accents

`final class Minn\Support\Accents` · `public/minn/src/Minn/Support/Accents.php`

Accented and special Latin characters to their plain ASCII spelling; a character with no ASCII form stays as it is.

### static `strip(string $text): string`


## DirectoryListing

`final class Minn\Support\DirectoryListing` · `public/minn/src/Minn/Support/DirectoryListing.php`

Walks a directory the way the filesystem API lists it: named entries, dot entries skipped, hidden ones optional, recursion optional.

### static `read(string $path, bool $includeHidden, bool $recursive, ?string $onlyName, callable $describe): array|false`

- `@param callable(string): array<string, mixed> $describe the entry's own fields for an absolute path`
- `@return array<string, array<string, mixed>>|false false when the path is not a readable directory`


## Email

`final class Minn\Support\Email` · `public/minn/src/Minn/Support/Email.php`

The address rules the reference applies: a local part from a fixed
character class, a dotted domain of hyphen-trimmed labels. `check` names
the first rule an address breaks; `sanitize` strips what it can and names
why it gave up.

### static `check(string $email): ?string`

### static `sanitize(string $raw): array`

- `@return array{0: string, 1: string|null} the cleaned address (empty when refused) and the reason`


## Entities

`final class Minn\Support\Entities` · `public/minn/src/Minn/Support/Entities.php`

HTML special-character encoding with the reference's quote styles and its
"do not double encode" rule: an ampersand that already starts a known
named or numeric entity stays as it is.

### static `specialchars(string $text, string|int|false $quoteStyle, bool $doubleEncode, callable $knownEntity): string`

- `@param callable(string): bool $knownEntity whether a named entity is in the allowed table`

### static `decode(string $text, string|int $quoteStyle): string`

The reverse of specialchars: the five characters back, with the quote pairs the style asks for.


## FileHeaders

`final class Minn\Support\FileHeaders` · `public/minn/src/Minn/Support/FileHeaders.php`

Header values from a plugin or theme file. The labels (Plugin Name,
Theme Name, Version) are the published file-header contract; the
reader is a line scan of the first 8 KB, never PHP execution.

### static `values(string $file, array $labels): array`

- `@param list<string> $labels`
- `@return array<string, string>`


## FileTree

`final class Minn\Support\FileTree` · `public/minn/src/Minn/Support/FileTree.php`

Whole-directory reads and copies. The engine's own installer, the update
unpacker and the plugins that reach for WordPress's copy_dir all want the
same two operations: list a tree, and duplicate one.

### static `files(string $dir, int $depth = 0, array $skip = array ( ), int $level = 1): array|false`

Every file under a directory, and every directory itself with a trailing
slash, to the given depth. Depth 0 means no limit; depth 1 stops at the
directory's own entries and names its subdirectories without descending.

- `@param list<string> $skip names to leave out at every level`
- `@return list<string>|false false when the directory cannot be read`

### static `copy(string $from, string $to, array $skip = array ( )): bool`

Copies a directory's contents into another, creating it if it is not
there. Names in $skip are left behind at the top level only, which is
how the update unpacker keeps a directory it means to preserve.

- `@param list<string> $skip`


## Files

`final class Minn\Support\Files` · `public/minn/src/Minn/Support/Files.php`

Recursive filesystem work behind WP_Filesystem_Direct: best-effort tree
delete and chmod (suppressed errors, keep-going semantics, the AND of
every step as the result), and the symbolic-to-octal permission string
conversion. The facade class keeps the reference's argument handling and
maps each operation here.

### static `octalFromSymbolic(string $mode): string`

drwxr-xr-x (or any rwx string) to its octal digits, the reference's tallying shape.

### static `deleteTree(string $path): bool`

Delete a directory tree, files first, best effort; true only when
everything went. A symlinked directory is unlinked, never followed:
the dev sites symlink plugin folders into the tree, and a delete that
reached through one would empty the real source.

### static `chmodTree(string $path, int $mode): bool`

Apply a mode to every FILE under a directory (the directories themselves keep theirs); always reports true.


## Html

`final class Minn\Support\Html` · `public/minn/src/Minn/Support/Html.php`

### static `esc(?string $value): string`

### static `attr(?string $value): string`

### static `addClasses(string $html, array $classes, ?string $onClass = NULL): string`

Appends classes to the first element in a fragment (or to the first
element already carrying $onClass), adding a class attribute when
the element has none. Existing classes keep their order.

- `@param list<string> $classes`

### static `stripAllTags(string $text, bool $collapseWhitespace = false): string`

Drops script and style elements with their contents, then every other tag; whitespace runs optionally collapse to one space.

### static `textField(string $text, bool $keepNewlines, Closure $validUtf8, Closure $encodeLessThan): string`

A single line of plain text from user input: tags stripped, control
whitespace folded, percent escapes removed. The caller passes its own
UTF-8 check and less-than encoder so its filters keep applying.

- `@param Closure(string): string $validUtf8 @param Closure(string): string $encodeLessThan`


## Ip

`final class Minn\Support\Ip` · `public/minn/src/Minn/Support/Ip.php`

Addresses with their identifying tail removed, for logs and analytics that
must not keep a visitor's exact address. An IPv4 address loses its last
octet, an IPv6 address its last four groups. Anything that is not an
address at all reads as the unspecified IPv4 address rather than as itself,
so a malformed value can never leak through unchanged.

### static `anonymize(string $address): string`


## Json

`final class Minn\Support\Json` · `public/minn/src/Minn/Support/Json.php`

Makes a value encodable: strings that are not valid UTF-8 get their high bytes replaced, recursively.

### static `sanitize(mixed $value): mixed`


## Kses

`final class Minn\Support\Kses` · `public/minn/src/Minn/Support/Kses.php`

The HTML a user without unfiltered_html may store. Tags outside the
allowlist are removed and their text kept; attributes outside the tag's
list (and the global set) are dropped; URL attributes lose unsafe
schemes; style attributes keep only listed properties and no code.
Comments (the block delimiters) pass through untouched.

- const `POST` = `array (   'a' =>    array (     0 => 'href',     1 => 'rel',     2 => 'rev',     3 => 'name',     4 => 'target',     5 => 'download',   ),   'abbr' =>    array (   ),   'acronym' =>    array (   ),   'address' =>    array (   ),   'article' =>    array (   ),   'aside' =>    array (   ),   'audio' =>    array (     0 => 'autoplay',     1 => 'controls',     2 => 'loop',     3 => 'muted',     4 => 'preload',     5 => 'src',   ),   'b' =>    array (   ),   'bdi' =>    array (   ),   'bdo' =>    array (   ),   'big' =>    array (   ),   'blockquote' =>    array (     0 => 'cite',   ),   'br' =>    array (   ),   'button' =>    array (     0 => 'disabled',     1 => 'name',     2 => 'type',     3 => 'value',   ),   'caption' =>    array (     0 => 'align',   ),   'cite' =>    array (   ),   'code' =>    array (   ),   'col' =>    array (     0 => 'align',     1 => 'span',     2 => 'valign',     3 => 'width',   ),   'colgroup' =>    array (     0 => 'align',     1 => 'span',     2 => 'valign',     3 => 'width',   ),   'dd' =>    array (   ),   'del' =>    array (     0 => 'datetime',   ),   'details' =>    array (     0 => 'open',   ),   'dfn' =>    array (   ),   'div' =>    array (     0 => 'align',   ),   'dl' =>    array (   ),   'dt' =>    array (   ),   'em' =>    array (   ),   'fieldset' =>    array (   ),   'figcaption' =>    array (   ),   'figure' =>    array (     0 => 'align',   ),   'font' =>    array (     0 => 'color',     1 => 'face',     2 => 'size',   ),   'footer' =>    array (   ),   'h1' =>    array (     0 => 'align',   ),   'h2' =>    array (     0 => 'align',   ),   'h3' =>    array (     0 => 'align',   ),   'h4' =>    array (     0 => 'align',   ),   'h5' =>    array (     0 => 'align',   ),   'h6' =>    array (     0 => 'align',   ),   'header' =>    array (   ),   'hgroup' =>    array (   ),   'hr' =>    array (     0 => 'align',     1 => 'noshade',     2 => 'size',     3 => 'width',   ),   'i' =>    array (   ),   'img' =>    array (     0 => 'alt',     1 => 'align',     2 => 'border',     3 => 'decoding',     4 => 'fetchpriority',     5 => 'height',     6 => 'hspace',     7 => 'loading',     8 => 'longdesc',     9 => 'sizes',     10 => 'src',     11 => 'srcset',     12 => 'usemap',     13 => 'vspace',     14 => 'width',   ),   'ins' =>    array (     0 => 'cite',     1 => 'datetime',   ),   'kbd' =>    array (   ),   'label' =>    array (     0 => 'for',   ),   'legend' =>    array (     0 => 'align',   ),   'li' =>    array (     0 => 'align',     1 => 'value',   ),   'main' =>    array (     0 => 'align',   ),   'map' =>    array (     0 => 'name',   ),   'mark' =>    array (   ),   'menu' =>    array (     0 => 'type',   ),   'nav' =>    array (     0 => 'align',   ),   'object' =>    array (   ),   'ol' =>    array (     0 => 'reversed',     1 => 'start',     2 => 'type',   ),   'p' =>    array (     0 => 'align',   ),   'picture' =>    array (   ),   'pre' =>    array (     0 => 'width',   ),   'q' =>    array (     0 => 'cite',   ),   'rb' =>    array (   ),   'rp' =>    array (   ),   'rt' =>    array (   ),   'rtc' =>    array (   ),   'ruby' =>    array (   ),   's' =>    array (   ),   'samp' =>    array (   ),   'section' =>    array (     0 => 'align',   ),   'small' =>    array (   ),   'source' =>    array (     0 => 'height',     1 => 'media',     2 => 'sizes',     3 => 'src',     4 => 'srcset',     5 => 'type',     6 => 'width',   ),   'span' =>    array (     0 => 'align',   ),   'strike' =>    array (   ),   'strong' =>    array (   ),   'sub' =>    array (   ),   'summary' =>    array (     0 => 'align',   ),   'sup' =>    array (   ),   'table' =>    array (     0 => 'align',     1 => 'bgcolor',     2 => 'border',     3 => 'cellpadding',     4 => 'cellspacing',     5 => 'rules',     6 => 'summary',     7 => 'width',   ),   'tbody' =>    array (     0 => 'align',     1 => 'valign',   ),   'td' =>    array (     0 => 'abbr',     1 => 'align',     2 => 'axis',     3 => 'bgcolor',     4 => 'colspan',     5 => 'headers',     6 => 'height',     7 => 'nowrap',     8 => 'rowspan',     9 => 'scope',     10 => 'valign',     11 => 'width',   ),   'textarea' =>    array (     0 => 'cols',     1 => 'disabled',     2 => 'name',     3 => 'readonly',     4 => 'rows',   ),   'tfoot' =>    array (     0 => 'align',     1 => 'valign',   ),   'th' =>    array (     0 => 'abbr',     1 => 'align',     2 => 'axis',     3 => 'bgcolor',     4 => 'colspan',     5 => 'headers',     6 => 'height',     7 => 'nowrap',     8 => 'rowspan',     9 => 'scope',     10 => 'valign',     11 => 'width',   ),   'thead' =>    array (     0 => 'align',     1 => 'valign',   ),   'title' =>    array (   ),   'tr' =>    array (     0 => 'align',     1 => 'bgcolor',     2 => 'valign',   ),   'track' =>    array (     0 => 'default',     1 => 'kind',     2 => 'label',     3 => 'src',     4 => 'srclang',   ),   'tt' =>    array (   ),   'u' =>    array (   ),   'ul' =>    array (     0 => 'type',   ),   'var' =>    array (   ),   'video' =>    array (     0 => 'autoplay',     1 => 'controls',     2 => 'height',     3 => 'loop',     4 => 'muted',     5 => 'playsinline',     6 => 'poster',     7 => 'preload',     8 => 'src',     9 => 'width',   ), )`
- const `COMMENT` = `array (   'a' =>    array (     0 => 'href',     1 => 'title',     2 => 'rel',   ),   'abbr' =>    array (     0 => 'title',   ),   'acronym' =>    array (     0 => 'title',   ),   'b' =>    array (   ),   'blockquote' =>    array (     0 => 'cite',   ),   'cite' =>    array (   ),   'code' =>    array (   ),   'del' =>    array (     0 => 'datetime',   ),   'em' =>    array (   ),   'i' =>    array (   ),   'q' =>    array (     0 => 'cite',   ),   's' =>    array (   ),   'strike' =>    array (   ),   'strong' =>    array (   ), )`

### static `filter(string $html, array $allowed): string`

- `@param array<string, list<string>> $allowed`

### static `text(string $value): string`

Plain text: tags gone, whitespace collapsed, control characters dropped.

### static `url(string $url): string`

A URL for a stored field: empty when its scheme is not one the reference allows.

### static `attributeUrl(string $url): string`

A URL inside markup: an unsafe scheme is cut off and the rest kept, as the reference does.

### static `style(string $style): string`

A style attribute's value with only the listed properties kept.


## Lists

`final class Minn\Support\Lists` · `public/minn/src/Minn/Support/Lists.php`

List shaping behind the facade's array utilities: the multi-field sort
wp_list_sort() promises (loose comparison per field, first difference
wins) and the row-shape conversions wpdb hands back for its OBJECT_K /
ARRAY_A / ARRAY_N output formats.

### static `sort(array $items, array $orderby, bool $preserveKeys): array`

- `@param array<int|string, mixed> $items`
- `@param array<string, string> $orderby field => ASC|DESC`
- `@return array<int|string, mixed>`

### static `shapeRows(array $rows, string $output): array`

Rows of stdClass shaped for a wpdb output format: keyed by their first
column (first row wins a duplicate key), associative, or numeric.

- `@param list<object> $rows`
- `@return array<int|string, mixed>`

### static `descendants(array $items, int $rootId, callable $id, callable $parent, array $visited = array ( )): array`

Every descendant of one root in a flat parent-linked list, preorder
(a child ahead of its own children), the shape _get_term_children and
get_page_children walk. Visited ids guard against a parent cycle.

- `@param list<mixed> $items`
- `@param callable(mixed): int $id`
- `@param callable(mixed): int $parent`
- `@param list<int> $visited`
- `@return list<mixed>`


## Locale

`final class Minn\Support\Locale` · `public/minn/src/Minn/Support/Locale.php`

The locale's calendar and number vocabulary as data: the names a site
prints for weekdays, months and meridiems, and how it separates
thousands and decimals.

The names are English here because the engine carries no core
translations yet; the facade runs each one through the translation
filters on the way out, so a catalogue can answer for them later
without this class changing.

- const `WEEKDAYS` = `array (   0 => 'Sunday',   1 => 'Monday',   2 => 'Tuesday',   3 => 'Wednesday',   4 => 'Thursday',   5 => 'Friday',   6 => 'Saturday', )`
- const `MONTHS` = `array (   0 => 'January',   1 => 'February',   2 => 'March',   3 => 'April',   4 => 'May',   5 => 'June',   6 => 'July',   7 => 'August',   8 => 'September',   9 => 'October',   10 => 'November',   11 => 'December', )`
- const `MERIDIEM` = `array (   'am' => 'am',   'pm' => 'pm',   'AM' => 'AM',   'PM' => 'PM', )`
- const `NUMBER_FORMAT` = `array (   'thousands_sep' => ',',   'decimal_point' => '.', )`
- const `LIST_ITEM_SEPARATOR` = `', '`
- const `WORD_COUNT_TYPE` = `'words'`
- const `TEXT_DIRECTION` = `'ltr'`

### static `weekdayInitial(string $day): string`

A weekday's one-letter form.

### static `abbreviation(string $name): string`

A weekday's or month's three-letter form; May is already three letters.

### static `monthKey(string|int $month): string`

Months are keyed by their zero-padded number, both ways round.


## Markers

`final class Minn\Support\Markers` · `public/minn/src/Minn/Support/Markers.php`

The BEGIN/END marker blocks insert_with_markers() maintains in files like
.htaccess: the captured shape keeps everything outside the markers,
replaces the block inside them, and writes the reference's four-line
do-not-edit preamble ahead of the inserted lines.

### static `write(string $file, string $marker, array $lines): bool`

- `@param list<string> $lines`


## Paths

`final class Minn\Support\Paths` · `public/minn/src/Minn/Support/Paths.php`

File-system path and permission spellings.

### static `translationDir(string $domain, string $locale, string $langDir, ?string $customPath): string|false`

The trailing-slashed directory holding a text domain's {domain}-{locale}.mo:
a custom path first, then the language dir's plugins and themes folders,
then the dir itself for the default domain; false when none has the file.

### static `normalize(string $path): string`

Forward slashes only, runs collapsed, a stream wrapper kept, a Windows drive letter upper-cased.

### static `symbolicMode(int $mode): string`

The `ls -l` spelling of a mode: type letter, then rwx for owner, group and world with the setuid, setgid and sticky bits folded in.


## SearchReplace

`final class Minn\Support\SearchReplace` · `public/minn/src/Minn/Support/SearchReplace.php`

String replace that walks serialized-PHP arrays of scalars without
unserialize, so a domain change in an option blob keeps its lengths.

### static `in(string $value, string $old, string $new): array`

- `@return array{0: string, 1: int} replacement and how many times $old occurred`


## Serialized

`final class Minn\Support\Serialized` · `public/minn/src/Minn/Support/Serialized.php`

Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing
here executes the blob; each reader scans for the one shape it needs.

- const `INVALID` = `'' . "\0" . 'minn:invalid' . "\0" . ''` — Returned by decode() when the blob is not a serialized value the reader accepts.

### static `stringList(?string $blob): array`

The string values of a serialized string list.

### static `serializeStringList(array $values): string`

A flat string list in the stored form.

### static `field(?string $blob, string $field): ?string`

The first "field";value pair inside a blob: string, int, or float value as a string.

### static `decode(string $blob): mixed`

A serialized scalar or array as PHP data, parsed by this reader:
strings, integers, floats, booleans, null, and arrays of those.
Objects are refused (nothing here instantiates anything), as is any
blob with trailing bytes or a malformed shape, with INVALID.

### static `encode(mixed $value): string`

PHP's serialize() for the values decode() accepts: null, bool, int,
float, string, and arrays of those. Objects are refused.

### static `intList(?string $blob): array`

The integer values of a serialized list such as sticky_posts.


## Time

`final class Minn\Support\Time` · `public/minn/src/Minn/Support/Time.php`

Human-scale spans: a number of seconds as the largest whole unit it fills, rounded, never below one.

### static `span(int $seconds): array`

- `@return array{0: int, 1: string} count and unit name`


## Url

`final class Minn\Support\Url` · `public/minn/src/Minn/Support/Url.php`

URL shaping the escaping and query helpers share: the character cleanup
esc_url applies, bracket encoding outside the authority, and query
argument merging. Behaviour pinned by contracts/fixtures/api/functions.json.

### static `clean(string $url): string`

Spaces encoded, stray characters dropped, ";//" healed, a bare host given http; '' when nothing survives.

### static `encodeBrackets(string $url, array $parsed): string`

Square brackets after the authority are percent-encoded; the authority itself is left alone. @param array<string, mixed> $parsed

- `@param array<string, mixed> $parsed`

### static `withQuery(string $uri, array $new, Closure $encode, Closure $build): string`

The URI with query arguments merged in (false removes one), the
fragment kept, a bare query string treated as such.

- `@param array<string, mixed> $new`
- `@param Closure(array): array $encode encodes the existing arguments the way the reference does`
- `@param Closure(array): string $build builds the query string`

### static `validateForHttp(string $url, string $homeHost, int $homePort, Closure $externalAllowed): ?string`

The checks wp_http_validate_url makes on a URL whose protocol already
passed: an http(s) scheme, a host without credentials or a colon, no
private address unless it is this site or allowed, only the usual
ports (or the site's own). Returns the URL, or null when refused.

- `@param Closure(string, string): bool $externalAllowed whether a private host may be fetched anyway`

### static `buildQuery(array $data, string $prefix = ''): string`

A query string in the reference's spelling: nested keys as `a%5Bb%5D`, null as a bare key, booleans as 0/1.

### static `parse(string $url, int $component = -1): array|string|int|false|null`

parse_url that also accepts scheme-relative and path-only URLs; a single component by its PHP_URL_* constant.

### static `withScheme(string $url, string $scheme): string`

The URL under a scheme, or scheme-and-host stripped for 'relative'; a protocol-relative URL is read as http first.

### static `safeRedirect(string $location, Closure $allowedHosts): ?string`

A redirect target the site may send a browser to: http(s) only, no
credentials, and a host the caller allows (local paths always pass).

- `@param Closure(string): list<string> $allowedHosts the hosts allowed for the target's host`

