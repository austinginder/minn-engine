# `Minn\Support`

escaping, serialized readers, small helpers

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Accents`](#accents) | final class | 35 | Accented and special characters to their plain spelling, from the |
| [`Backtrace`](#backtrace) | final class | 32 | A call stack named as wp_debug_backtrace_summary names it (probe |
| [`DirectoryListing`](#directorylisting) | final class | 34 | Walks a directory the way the filesystem API lists it: named entries, dot entries skipped, hidden ones optional, recursion optional. |
| [`Email`](#email) | final class | 69 | The address rules the reference applies: a local part from a fixed |
| [`Entities`](#entities) | final class | 77 | HTML special-character encoding with the reference's quote styles and its |
| [`Escape`](#escape) | final class | 25 | Text made safe for HTML the way WordPress's escapers make it (esc_html, |
| [`FileHeaders`](#fileheaders) | final class | 27 | Header values from a plugin or theme file. The labels (Plugin Name, |
| [`FileTree`](#filetree) | final class | 70 | Whole-directory reads and copies. The engine's own installer, the update |
| [`Files`](#files) | final class | 66 | Recursive filesystem work behind WP_Filesystem_Direct: best-effort tree |
| [`Flag`](#flag) | final class | 11 | A yes or no as WordPress reads one from loose input (wp_validate_boolean). |
| [`Html`](#html) | final class | 91 |  |
| [`Ip`](#ip) | final class | 22 | Addresses with their identifying tail removed, for logs and analytics that |
| [`Json`](#json) | final class | 19 | Makes a value encodable: strings that are not valid UTF-8 get their high bytes replaced, recursively. |
| [`Kses`](#kses) | final class | 545 | The HTML a user without unfiltered_html may store. Tags outside the |
| [`KsesEntities`](#ksesentities) | final class | 30 | The named references kses keeps as written: the list captured from the |
| [`KsesPolicy`](#ksespolicy) | final readonly class | 115 | What one kses pass allows: the tags, each tag's attributes (allowed |
| [`KsesValues`](#ksesvalues) | final class | 38 | The value rules an allowlist attribute may carry, as the reference judges |
| [`Lists`](#lists) | final class | 116 | List shaping behind the facade's array utilities: the multi-field sort |
| [`Locale`](#locale) | final class | 31 | The locale's calendar and number vocabulary as data: the names a site |
| [`Markers`](#markers) | final class | 59 | The BEGIN/END marker blocks insert_with_markers() maintains in files like |
| [`Paths`](#paths) | final class | 64 | File-system path and permission spellings. |
| [`ScriptTag`](#scripttag) | final class | 69 | Script elements as the reference builds them (probe script-tags): the |
| [`SearchReplace`](#searchreplace) | final class | 38 | String replace that walks serialized-PHP arrays of scalars without |
| [`Serialized`](#serialized) | final class | 311 | Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing |
| [`Slashes`](#slashes) | final class | 32 | Magic-quote slashes the way WordPress keeps them (wp_slash, wp_unslash): |
| [`Time`](#time) | final class | 21 | Human-scale spans: a number of seconds as the largest whole unit it fills, rounded, never below one. |
| [`Url`](#url) | final class | 214 | URL shaping the escaping and query helpers share: the character cleanup |
| [`Utf8`](#utf8) | final class | 8 | Whether bytes are well-formed UTF-8 as the reference judges them: overlong |
| [`WebServer`](#webserver) | final class | 8 | What the SERVER_SOFTWARE string says about the web server in front of the site. |

## Accents

`final class Minn\Support\Accents` · `public/minn/src/Minn/Support/Accents.php`

Accented and special characters to their plain spelling, from the
reference's own table (data/accents.json, captured with
tests/tools/accents-capture.php): text is put in composed form first, a
locale with spellings of its own (German, Danish, Catalan, Serbian,
Bosnian) has them, and a string that is not UTF-8 is read as Latin-1.
Anything the table does not name stays as it is.

Used by: `Minn\Content\Slug`


### static `strip(string $text, string $locale = ''): string`

The text with its accented characters spelled plainly, as the locale spells them.

Internals: `data()` (private, line 43)


## Backtrace

`final class Minn\Support\Backtrace` · `public/minn/src/Minn/Support/Backtrace.php`

A call stack named as wp_debug_backtrace_summary names it (probe
placeholders-a): Class->method, Class::method and function names; a
hook's function with the hook named; an included file by its path with
the given prefixes taken off, in order. Innermost first.

- const `HOOKS` = `array (   0 => 'do_action',   1 => 'apply_filters',   2 => 'do_action_ref_array',   3 => 'apply_filters_ref_array', )`
- const `INCLUDES` = `array (   0 => 'include',   1 => 'include_once',   2 => 'require',   3 => 'require_once', )`

### static `summary(array $frames, ?string $ignoreClass, int $skip, array $truncate): array`

The frames' names, after the first $skip.

- `@param list<array<string, mixed>> $frames debug_backtrace()'s frames, innermost first`
- `@param list<string> $truncate path prefixes taken off an included file`
- `@return list<string>`


## DirectoryListing

`final class Minn\Support\DirectoryListing` · `public/minn/src/Minn/Support/DirectoryListing.php`

Walks a directory the way the filesystem API lists it: named entries, dot entries skipped, hidden ones optional, recursion optional.

### static `read(string $path, bool $includeHidden, bool $recursive, ?string $onlyName, callable $describe): array|false`

A directory's entries described one by one, or false when unreadable.

- `@param callable(string): array<string, mixed> $describe the entry's own fields for an absolute path`
- `@return array<string, array<string, mixed>>|false false when the path is not a readable directory`


## Email

`final class Minn\Support\Email` · `public/minn/src/Minn/Support/Email.php`

The address rules the reference applies: a local part from a fixed
character class, a dotted domain of hyphen-trimmed labels. `check` names
the first rule an address breaks; `sanitize` strips what it can and names
why it gave up.

- const `LOCAL_CHARACTER` = `'a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~\\.-'`

Used by: `Minn\Rest\CommentsController`

### static `check(string $email): ?string`

Why an address is invalid, or null when it passes the reference's checks.

### static `sanitize(string $raw): array`

An address with the characters the reference strips removed, and what was removed.

- `@return array{0: string, 1: string|null} the cleaned address (empty when refused) and the reason`


## Entities

`final class Minn\Support\Entities` · `public/minn/src/Minn/Support/Entities.php`

HTML special-character encoding with the reference's quote styles and its
"do not double encode" rule: an ampersand that already starts a known
named or numeric entity stays as it is.

- const `DECODE` = `array (   '&amp;' => '&',   '&#038;' => '&',   '&#x26;' => '&',   '&lt;' => '<',   '&#060;' => '<',   '&#x3C;' => '<',   '&gt;' => '>',   '&#062;' => '>',   '&#x3E;' => '>', )`
- const `DOUBLE_QUOTES` = `array (   '&quot;' => '"',   '&#034;' => '"',   '&#x22;' => '"', )`
- const `SINGLE_QUOTES` = `array (   '&#039;' => '\'',   '&#x27;' => '\'',   '&#39;' => '\'',   '&apos;' => '\'', )`
- const `WINDOWS_1252` = `array (   '&#128;' => '&#8364;',   '&#129;' => '',   '&#130;' => '&#8218;',   '&#131;' => '&#402;',   '&#132;' => '&#8222;',   '&#133;' => '&#8230;',   '&#134;' => '&#8224;',   '&#135;' => '&#8225;',   '&#136;' => '&#710;',   '&#137;' => '&#8240;',   '&#138;' => '&#352;',   '&#139;' => '&#8249;',   '&#140;' => '&#338;',   '&#141;' => '',   '&#142;' => '&#381;',   '&#143;' => '',   '&#144;' => '',   '&#145;' => '&#8216;',   '&#146;' => '&#8217;',   '&#147;' => '&#8220;',   '&#148;' => '&#8221;',   '&#149;' => '&#8226;',   '&#150;' => '&#8211;',   '&#151;' => '&#8212;',   '&#152;' => '&#732;',   '&#153;' => '&#8482;',   '&#154;' => '&#353;',   '&#155;' => '&#8250;',   '&#156;' => '&#339;',   '&#157;' => '',   '&#158;' => '&#382;',   '&#159;' => '&#376;', )` — Decimal references 128 to 159 name Windows-1252 characters, not the C1
controls Unicode puts there; each becomes the reference to the Unicode
character it meant, and the five codes Windows-1252 leaves unassigned
are removed. Only the exact unpadded decimal spelling is touched.

Used by: `Minn\Support\Escape`, `Minn\Support\Kses`

### static `specialchars(string $text, string|int|false $quoteStyle, bool $doubleEncode, callable $knownEntity): string`

The reference's special-characters escaping, with its quote styles and double-encoding rule.

- `@param callable(string): bool $knownEntity whether a named entity is in the allowed table`

### static `decode(string $text, string|int $quoteStyle): string`

The reverse of specialchars: the five characters back, with the quote pairs the style asks for.

### static `convertInvalid(string $text): string`

Windows-1252 numeric references rewritten as the Unicode references they meant.

Internals: `encodeStrayAmpersands()` (private, line 40)


## Escape

`final class Minn\Support\Escape` · `public/minn/src/Minn/Support/Escape.php`

Text made safe for HTML the way WordPress's escapers make it (esc_html,
esc_attr): invalid UTF-8 emptied, entities already present normalised
rather than encoded again, the specials and both quotes encoded, then the
filter plugins hook (esc_html, attribute_escape) handed the result and the
original.

Used by: `Minn\Content\MediaShortcodes`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\PageLinks`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMarkup`, `Minn\Front\ToolbarMenus`, `Minn\Runtime\NavMenu`, `Minn\Runtime\OptionSanitizer`, `Minn\Runtime\UpdateCounts`, `Minn\Theme\HeadLinks`, `Minn\Widgets\WidgetForms`

### static `html(mixed $text): string`

Text for an element's content.

### static `attr(mixed $text): string`

Text for an attribute's value.

Internals: `encoded()` (private, line 28)


## FileHeaders

`final class Minn\Support\FileHeaders` · `public/minn/src/Minn/Support/FileHeaders.php`

Header values from a plugin or theme file. The labels (Plugin Name,
Theme Name, Version) are the published file-header contract; the
reader is a line scan of the first 8 KB, never PHP execution.

Used by: `Minn\Admin\App`, `Minn\Admin\ThemesController`, `Minn\Cli\ThemeCommand`, `Minn\Content\Inventory`, `Minn\Ops\InstalledSoftware`, `Minn\Ops\Packages`, `Minn\Ops\Updates`, `Minn\Rest\PluginsController`, `Minn\Runtime\PluginRequirements`, `Minn\Runtime\Plugins`, `Minn\Theme\Folder`

### static `values(string $file, array $labels): array`

The header values of a plugin or theme file, by label.

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

Used by: `Minn\Cli\Installer`, `Minn\Ops\EngineUpdate`, `Minn\Ops\Packages`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\ThemeSwitch`

### static `octalFromSymbolic(string $mode): string`

drwxr-xr-x (or any rwx string) to its octal digits, the reference's tallying shape.

### static `deleteTree(string $path): bool`

Delete a directory tree, files first, best effort; true only when
everything went. A symlinked directory is unlinked, never followed:
the dev sites symlink plugin folders into the tree, and a delete that
reached through one would empty the real source.

### static `chmodTree(string $path, int $mode): bool`

Apply a mode to every FILE under a directory (the directories themselves keep theirs); always reports true.


## Flag

`final class Minn\Support\Flag` · `public/minn/src/Minn/Support/Flag.php`

A yes or no as WordPress reads one from loose input (wp_validate_boolean).

Used by: `Minn\Content\MediaShortcodes`

### static `of(mixed $value): bool`

The string "false" in any case is no; anything else is PHP's truth.


## Html

`final class Minn\Support\Html` · `public/minn/src/Minn/Support/Html.php`

Used by: `Minn\Admin\AppController`, `Minn\Admin\LanguageChoices`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Renderer`, `Minn\Blocks\Wrapper`, `Minn\Content\Menus`, `Minn\Content\PasswordGate`, `Minn\Content\TermLinks`, `Minn\Front\AdminBar`, `Minn\Front\PostNavigation`, `Minn\Front\Renderer`, `Minn\Front\SitemapXml`, `Minn\Front\TermLists`, `Minn\Http\Failure`, `Minn\Login\LoginController`, `Minn\Login\LoginForm`, `Minn\Login\LoginNotices`, `Minn\Rest\MediaObject`, `Minn\Rest\PluginsController`, `Minn\Runtime\PageMenu`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\PageRenderer`

### static `esc(?string $value): string`

Text escaped for HTML.

### static `attr(?string $value): string`

Text escaped for an attribute value.

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

### static `scriptBody(string $markup): ?string`

The code inside markup that is exactly one bare script element once the
surrounding whitespace is gone, or null for anything else: a tag with
attributes, an empty element, a missing end. The tag names match in any
case and the code is returned as written.


## Ip

`final class Minn\Support\Ip` · `public/minn/src/Minn/Support/Ip.php`

Addresses with their identifying tail removed, for logs and analytics that
must not keep a visitor's exact address. An IPv4 address loses its last
octet, an IPv6 address its last four groups. Anything that is not an
address at all reads as the unspecified IPv4 address rather than as itself,
so a malformed value can never leak through unchanged.

- const `UNSPECIFIED` = `'0.0.0.0'`

### static `anonymize(string $address): string`

An address with its last octet, or its tail, zeroed.


## Json

`final class Minn\Support\Json` · `public/minn/src/Minn/Support/Json.php`

Makes a value encodable: strings that are not valid UTF-8 get their high bytes replaced, recursively.

### static `sanitize(mixed $value): mixed`

A value with every string made valid UTF-8, ready to encode.


## Kses

`final class Minn\Support\Kses` · `public/minn/src/Minn/Support/Kses.php`

The HTML a user without unfiltered_html may store. Tags outside the
allowlist are removed and their text kept; attributes outside the tag's
list are dropped; URL attributes lose unsafe schemes; style attributes
keep only listed properties and no code. Comments keep their text, and
block delimiters are written back with their attribute values filtered.

- const `COMMENT` = `array (   'a' =>    array (     0 => 'href',     1 => 'title',     2 => 'rel',   ),   'abbr' =>    array (     0 => 'title',   ),   'acronym' =>    array (     0 => 'title',   ),   'b' =>    array (   ),   'blockquote' =>    array (     0 => 'cite',   ),   'cite' =>    array (   ),   'code' =>    array (   ),   'del' =>    array (     0 => 'datetime',   ),   'em' =>    array (   ),   'i' =>    array (   ),   'q' =>    array (     0 => 'cite',   ),   's' =>    array (   ),   'strike' =>    array (   ),   'strong' =>    array (   ), )`
- const `URI_ATTRIBUTES` = `array (   0 => 'action',   1 => 'archive',   2 => 'background',   3 => 'cite',   4 => 'classid',   5 => 'codebase',   6 => 'data',   7 => 'formaction',   8 => 'href',   9 => 'icon',   10 => 'longdesc',   11 => 'manifest',   12 => 'poster',   13 => 'profile',   14 => 'src',   15 => 'usemap',   16 => 'xmlns', )` — Every attribute the reference treats as holding a URI, so its scheme is judged wherever the attribute is allowed.
- const `SCHEMES` = `array (   0 => 'http',   1 => 'https',   2 => 'ftp',   3 => 'ftps',   4 => 'mailto',   5 => 'news',   6 => 'irc',   7 => 'gopher',   8 => 'nntp',   9 => 'feed',   10 => 'telnet',   11 => 'mms',   12 => 'rtsp',   13 => 'sms',   14 => 'svn',   15 => 'tel',   16 => 'fax',   17 => 'xmpp',   18 => 'webcal',   19 => 'urn', )` — The URI schemes allowed by default.
- const `CSS_PROPERTIES` = `array (   0 => 'background',   1 => 'background-color',   2 => 'background-image',   3 => 'background-position',   4 => 'background-repeat',   5 => 'background-size',   6 => 'background-attachment',   7 => 'background-blend-mode',   8 => 'border',   9 => 'border-radius',   10 => 'border-width',   11 => 'border-color',   12 => 'border-style',   13 => 'border-right',   14 => 'border-right-color',   15 => 'border-right-style',   16 => 'border-right-width',   17 => 'border-bottom',   18 => 'border-bottom-color',   19 => 'border-bottom-left-radius',   20 => 'border-bottom-right-radius',   21 => 'border-bottom-style',   22 => 'border-bottom-width',   23 => 'border-bottom-right-radius',   24 => 'border-bottom-left-radius',   25 => 'border-left',   26 => 'border-left-color',   27 => 'border-left-style',   28 => 'border-left-width',   29 => 'border-top',   30 => 'border-top-color',   31 => 'border-top-left-radius',   32 => 'border-top-right-radius',   33 => 'border-top-style',   34 => 'border-top-width',   35 => 'border-top-left-radius',   36 => 'border-top-right-radius',   37 => 'border-spacing',   38 => 'border-collapse',   39 => 'caption-side',   40 => 'columns',   41 => 'column-count',   42 => 'column-fill',   43 => 'column-gap',   44 => 'column-rule',   45 => 'column-span',   46 => 'column-width',   47 => 'display',   48 => 'color',   49 => 'filter',   50 => 'font',   51 => 'font-family',   52 => 'font-size',   53 => 'font-style',   54 => 'font-variant',   55 => 'font-weight',   56 => 'letter-spacing',   57 => 'line-height',   58 => 'text-align',   59 => 'text-decoration',   60 => 'text-indent',   61 => 'text-transform',   62 => 'white-space',   63 => 'height',   64 => 'min-height',   65 => 'max-height',   66 => 'width',   67 => 'min-width',   68 => 'max-width',   69 => 'margin',   70 => 'margin-right',   71 => 'margin-bottom',   72 => 'margin-left',   73 => 'margin-top',   74 => 'margin-block-start',   75 => 'margin-block-end',   76 => 'margin-inline-start',   77 => 'margin-inline-end',   78 => 'padding',   79 => 'padding-right',   80 => 'padding-bottom',   81 => 'padding-left',   82 => 'padding-top',   83 => 'padding-block-start',   84 => 'padding-block-end',   85 => 'padding-inline-start',   86 => 'padding-inline-end',   87 => 'flex',   88 => 'flex-basis',   89 => 'flex-direction',   90 => 'flex-flow',   91 => 'flex-grow',   92 => 'flex-shrink',   93 => 'flex-wrap',   94 => 'gap',   95 => 'column-gap',   96 => 'row-gap',   97 => 'grid-template-columns',   98 => 'grid-auto-columns',   99 => 'grid-column-start',   100 => 'grid-column-end',   101 => 'grid-column',   102 => 'grid-column-gap',   103 => 'grid-template-rows',   104 => 'grid-auto-rows',   105 => 'grid-row-start',   106 => 'grid-row-end',   107 => 'grid-row',   108 => 'grid-row-gap',   109 => 'grid-gap',   110 => 'justify-content',   111 => 'justify-items',   112 => 'justify-self',   113 => 'align-content',   114 => 'align-items',   115 => 'align-self',   116 => 'clear',   117 => 'cursor',   118 => 'direction',   119 => 'float',   120 => 'list-style-type',   121 => 'object-fit',   122 => 'object-position',   123 => 'opacity',   124 => 'overflow',   125 => 'vertical-align',   126 => 'writing-mode',   127 => 'position',   128 => 'top',   129 => 'right',   130 => 'bottom',   131 => 'left',   132 => 'z-index',   133 => 'box-shadow',   134 => 'aspect-ratio',   135 => 'container-type',   136 => 'fill',   137 => 'fill-opacity',   138 => 'fill-rule',   139 => 'stroke',   140 => 'stroke-dasharray',   141 => 'stroke-dashoffset',   142 => 'stroke-linecap',   143 => 'stroke-linejoin',   144 => 'stroke-miterlimit',   145 => 'stroke-opacity',   146 => 'stroke-width',   147 => 'color-interpolation',   148 => 'color-interpolation-filters',   149 => 'paint-order',   150 => 'stop-color',   151 => 'stop-opacity',   152 => 'flood-color',   153 => 'flood-opacity',   154 => 'lighting-color',   155 => 'marker',   156 => 'marker-end',   157 => 'marker-mid',   158 => 'marker-start',   159 => 'clip-path',   160 => 'clip-rule',   161 => 'mask',   162 => 'mask-type',   163 => 'cx',   164 => 'cy',   165 => 'r',   166 => 'rx',   167 => 'ry',   168 => 'x',   169 => 'y',   170 => 'd',   171 => 'alignment-baseline',   172 => 'baseline-shift',   173 => 'dominant-baseline',   174 => 'glyph-orientation-horizontal',   175 => 'glyph-orientation-vertical',   176 => 'text-anchor',   177 => 'unicode-bidi',   178 => 'word-spacing',   179 => 'font-size-adjust',   180 => 'font-stretch',   181 => 'color-rendering',   182 => 'image-rendering',   183 => 'shape-rendering',   184 => 'text-rendering',   185 => 'vector-effect',   186 => 'transform',   187 => 'transform-origin',   188 => 'pointer-events',   189 => 'visibility',   190 => '--*', )` — The properties style attributes keep, as the reference lists them before safe_style_css (probe safety-filters); --* stands for custom properties.
- const `REFERENCE` = `'/&(#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{0,31});/'`
- const `STRAY_AMPERSAND` = `'/&(?!(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{0,31});)/'`
- const `MARKUP` = `array (   '&' => '&amp;',   '<' => '&lt;',   '>' => '&gt;',   '"' => '&quot;',   '\'' => '&apos;', )`

Used by: `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Content\Users`, `Minn\Media\Writer`, `Minn\Rest\MenusController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Support\Escape`, `Minn\Support\KsesPolicy`


### static `post(string $html): string`

Post content as an author without unfiltered_html may store it.

### static `comment(string $html): string`

A comment, profile or term description as anyone without unfiltered_html may store it: listed attributes only.

### static `sanitize(string $html, Minn\Support\KsesPolicy $policy): string`

The whole pass wp_kses makes with its default hooks: control
characters out, references normalized, a "<" that opens no tag
escaped, block attribute values filtered the same way, then the tags.

### static `withoutControls(string $html): string`

The control characters kses removes (tab, newline and carriage return stay).

### static `normalizeEntities(string $html): string`

Every "&" made a reference, in one pass: a known name or a code point
kses accepts stays one (decimal padded to three digits, hex with a
lower-case x), anything else becomes "&amp;".

### static `lessThan(string $html, ?Closure $escape = NULL): string`

A "<" that reaches the next "<" or the end without a ">" is text: the
run is escaped the way esc_html escapes it (quotes included).

- `@param (Closure(string): string)|null $escape`

### static `escapeHtml(string $text): string`

Text escaped for HTML with references kept: invalid UTF-8 gives nothing, quotes become &quot; and &#039;.

### static `blockAttributes(string $html, Closure $clean): string`

Block markup with every attribute key and string value passed through
the same filter, and the delimiters written back in their canonical
form ("--->" read as "-->"). Markup without a comment is left alone.

- `@param Closure(string): string $clean`

### static `filter(string $html, Minn\Support\KsesPolicy $policy): string`

The tag pass: each run from "<" to the next ">" (or the end) is a
comment, an inert bogus comment ("</" before a non-letter, "<!"
before a lower-case letter), or an element judged by the policy;
anything else in angle brackets goes. Text between keeps its
references, with stray ones escaped. Run alone (wp_kses_split), a
last "<" with no ">" still reads as a tag; inside sanitize() the
less-than pass has already escaped it.

### static `attributeList(string $raw): ?array`

The attributes written inside a tag, read the way kses reads them: a
name, then "=" and a quoted or bare value, or no value at all; junk
between (stray quotes, "=", "/") is skipped; the first of two
same-named attributes wins. Null when a quoted value never closes,
which costs the tag every attribute.

- `@return array<string, array{name: string, value: ?string, whole: string}>|null`

### static `text(string $value): string`

Plain text: tags gone, whitespace collapsed, control characters dropped.

### static `url(string $url): string`

A URL for a stored field: empty when its scheme is not one the reference allows.

### static `attributeUrl(string $url, array $schemes = self::SCHEMES): string`

A URL inside markup: whatever stands before the first colon, once
every character reference and percent escape is decoded as deep as it
goes and the invisible characters are dropped, must be an allowed
scheme, or it is cut off and the rest is judged again. The reference
cuts "?q=a:b" to "b" and "javascript:alert(1)//http://" to "//" the
same way. A value with a good scheme is returned as given.

- `@param list<string> $schemes`

### static `pdfObject(string $url, string $uploadsUrl): bool`

Whether a URL may be an object's data in post content: an http or https
URL on the uploads host and port, no credentials, query or fragment,
whose path ends in ".pdf".

### static `style(string $style): string`

A style attribute's value with only the listed properties kept.

### static `styleHooks(Closure $properties, Closure $allow): void`

The runtime's say over style attributes: the property list
(safe_style_css) and each declaration (safecss_filter_attr_allow_css).
The facade sets these as it loads.

- `@param Closure(list<string>): list<string> $properties`
- `@param Closure(bool, string): bool $allow`

Internals: `cleanValue()` (private, line 169), `run()` (private, line 203), `htmlComment()` (private, line 227), `deepDecode()` (private, line 333), `visible()` (private, line 346), `normalizeText()` (private, line 361), `normalizeAttribute()` (private, line 384), `named()` (private, line 404), `codePoint()` (private, line 411), `attributes()` (private, line 426), `rendered()` (private, line 451), `srcset()` (private, line 489), `css()` (private, line 513), `safeValue()` (private, line 540)


## KsesEntities

`final class Minn\Support\KsesEntities` · `public/minn/src/Minn/Support/KsesEntities.php`

The named references kses keeps as written: the list captured from the
reference (data/kses.json) plus the five XML ones. Any other name is
stored as "&amp;name;".

Used by: `Minn\Feed\Tree`, `Minn\Support\Escape`, `Minn\Support\Kses`, `Minn\Support\KsesPolicy`


### static `known(string $name): bool`

Whether "&name;" is a reference kses leaves alone.

### static `allowlist(string $context): array`

An allowlist the reference ships, by context ("post" or "data").

- `@return array<string, array<string, mixed>>`

Internals: `table()` (private, line 37)


## KsesPolicy

`final readonly class Minn\Support\KsesPolicy` · `public/minn/src/Minn/Support/KsesPolicy.php`

What one kses pass allows: the tags, each tag's attributes (allowed
plainly or with value rules), whether a tag takes data- attributes,
which attributes hold URIs, and the schemes those URIs may use.

Used by: `Minn\Support\Kses`

- readonly `array $schemes`

### static `post(): self`

Post content from an author without unfiltered_html: the reference's post allowlist as captured.

### static `comment(): self`

Comments, profiles and term descriptions: only the attributes each tag lists, nothing global.

### static `fromAllowlist(array $allowedHtml, array $schemes, array $uriAttributes): self`

A caller's own allowlist taken literally: a listed attribute is allowed
whatever it maps to (false included, as the reference only asks whether
the name is there), an array carries value rules, and "data-*" lets the
tag take any data- name. Nothing else is implied.

- `@param array<array-key, mixed> $allowedHtml`
- `@param list<string> $schemes`
- `@param list<string> $uriAttributes`

### `allowsTag(string $tag): bool`

Whether the tag may appear at all.

### `rules(string $tag, string $name): ?array`

The rules an attribute carries on a tag: an empty list when it is
allowed plainly, null when it is not allowed.

- `@return array<array-key, mixed>|null`

### `required(string $tag): array`

The attributes a tag must keep: lose one and the tag keeps none.

- `@return list<string>`

### `holdsUri(string $name): bool`

Whether the attribute holds a URI whose scheme must be judged.


## KsesValues

`final class Minn\Support\KsesValues` · `public/minn/src/Minn/Support/KsesValues.php`

The value rules an allowlist attribute may carry, as the reference judges
them: lengths count bytes, bounds need a whole number (up to six digits,
up to six spaces either side), "valueless" compares the attribute's form,
"values" compares without case, and a callback decides for itself. A rule
the reference does not know passes. Where the reference fatals (a values
rule that is not a list, a callback that does not exist) the value fails.

Used by: `Minn\Support\Kses`

### static `satisfies(string $value, string $valueless, array $rules): bool`

Whether a value meets every rule its attribute carries; "required" is
the tag's concern, so it is skipped here.

- `@param array<array-key, mixed> $rules`

### static `check(string $value, string $valueless, string $rule, mixed $expected): bool`

One rule against one value; $valueless is "y" for a bare attribute and "n" for one with a value.

Internals: `wholeNumber()` (private, line 48)


## Lists

`final class Minn\Support\Lists` · `public/minn/src/Minn/Support/Lists.php`

List shaping behind the facade's array utilities: the multi-field sort
wp_list_sort() promises (loose comparison per field, first difference
wins) and the row-shape conversions wpdb hands back for its OBJECT_K /
ARRAY_A / ARRAY_N output formats.

Used by: `Minn\Query\CommentOrder`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\RegisteredFields`, `Minn\Runtime\CommentQueryWhere`, `Minn\Runtime\Pages`, `Minn\Runtime\TermOrder`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\UserOrder`, `Minn\Runtime\UserQueryRunner`

### static `items(mixed $input): array`

A list as arguments give one (wp_parse_list): an array's entries
trimmed, the empty ones dropped (keys kept); a string split on commas
and whitespace.

- `@return array<int|string, string>`

### static `ids(mixed $input): array`

Ids as arguments give them (wp_parse_id_list): each a whole number made
positive, each once, in first-seen order.

- `@return list<int>`

### static `sort(array $items, array $orderby, bool $preserveKeys): array`

Items sorted by several fields, each ascending or descending.

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

Internals: `keyedByFirstColumn()` (private, line 116)


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

Replaces the lines between a marker's begin and end comments in a file.

- `@param list<string> $lines`

### static `read(string $file, string $marker): array`

The lines between a marker's begin and end comments, every block of it
in order. A line opens or closes a block when it contains the comment
anywhere (case-sensitive); lines that start with "#" are left out, so
the preamble and any nested markers are, while indented comments stay.
Lines split on "\n" only, and a block left open at the end of the file
keeps the empty line after its final newline.

- `@return list<string>`


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


## ScriptTag

`final class Minn\Support\ScriptTag` · `public/minn/src/Minn/Support/ScriptTag.php`

Script elements as the reference builds them (probe script-tags): the
attributes sorted by name, a src or href through esc_url and every other
value escaped (true or null printed bare, false left out); inline code
between newlines, with any "<script" or "</script" that would end or
open an element written with its "s" as a \u escape. Only JavaScript and
JSON can carry that escape: a script of another type whose code holds
such a sequence is not printed at all.

- const `ESCAPABLE` = `array (   0 => '',   1 => 'module',   2 => 'application/json',   3 => 'importmap',   4 => 'speculationrules',   5 => 'application/ecmascript',   6 => 'application/javascript',   7 => 'application/x-ecmascript',   8 => 'application/x-javascript',   9 => 'text/ecmascript',   10 => 'text/javascript',   11 => 'text/javascript1.0',   12 => 'text/javascript1.1',   13 => 'text/javascript1.2',   14 => 'text/javascript1.3',   15 => 'text/javascript1.4',   16 => 'text/javascript1.5',   17 => 'text/jscript',   18 => 'text/livescript',   19 => 'text/x-ecmascript',   20 => 'text/x-javascript', )` — The script types inline code is JavaScript or JSON in (lowercase, trimmed; no type at all counts as JavaScript).
- const `TAG` = `'#<(/?)(s)(cript[\\t\\n\\f\\r />])#i'` — A sequence that would open or close a script element inside one.

### static `element(array $attributes): string`

The empty element for a script file.

- `@param array<string, mixed> $attributes`

### static `inline(string $code, array $attributes): string`

The element for inline code (already between its newlines), or '' when
its type cannot hold the code safely.

- `@param array<string, mixed> $attributes`

Internals: `attributes()` (private, line 64)


## SearchReplace

`final class Minn\Support\SearchReplace` · `public/minn/src/Minn/Support/SearchReplace.php`

String replace that walks serialized-PHP arrays of scalars without
unserialize, so a domain change in an option blob keeps its lengths.

Used by: `Minn\Cli\SearchReplaceCommand`

### static `in(string $value, string $old, string $new): array`

A value with one string replaced, and how many times.

- `@return array{0: string, 1: int} replacement and how many times $old occurred`

Internals: `walk()` (private, line 33)


## Serialized

`final class Minn\Support\Serialized` · `public/minn/src/Minn/Support/Serialized.php`

Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing
here executes the blob; each reader scans for the one shape it needs.

- const `INVALID` = `'' . "\0" . 'minn:invalid' . "\0" . ''` — Returned by decode() when the blob is not a serialized value the reader accepts.

Used by: `Minn\Admin\App`, `Minn\Admin\Appearance`, `Minn\Admin\Dashboard`, `Minn\Admin\HiddenIntegrations`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\SiteController`, `Minn\Admin\UploadsSize`, `Minn\Auth\ApplicationPasswords`, `Minn\Auth\Capabilities`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Cli\OptionCommand`, `Minn\Cli\Preflight`, `Minn\Content\Inventory`, `Minn\Content\Menus`, `Minn\Content\PluginState`, `Minn\Content\PostWriter`, `Minn\Content\Terms`, `Minn\Extension\Loader`, `Minn\Mail\MailSettings`, `Minn\Ops\Updates`, `Minn\Rest\PluginsController`, `Minn\Rest\PostObject`, `Minn\Runtime\CronTable`, `Minn\Runtime\Options`, `Minn\Runtime\Recovery`, `Minn\Support\SearchReplace`


### static `stringList(?string $blob): array`

The string values of a serialized string list.

### static `serializeStringList(array $values): string`

A flat string list in the stored form.

### static `field(?string $blob, string $field): ?string`

The first "field";value pair inside a blob: string, int, or float value as a string.

### static `decode(string $blob, ?Closure $revive = NULL): mixed`

A serialized scalar or array as PHP data, parsed by this reader:
strings, integers, floats, booleans, null, and arrays of those. An
object record becomes a stdClass of its properties, or whatever the
reviver makes of the class name and those properties; nothing here
instantiates anything itself. A blob with trailing bytes or a
malformed shape is INVALID.

- `@param ?\Closure(string, \stdClass): object $revive`

### static `storedClass(object $object): ?string`

The class an object was stored as, when it came back as a stdClass of its properties.

### static `encode(mixed $value): string`

PHP's serialize() for the values decode() accepts: null, bool, int,
float, string, arrays of those, and objects written as their class.

### static `intList(?string $blob): array`

The integer values of a serialized list such as sticky_posts.

Internals: `read()` (private, line 79), `readObject()` (private, line 135), `encodeRecord()` (private, line 189), `encodeWrapper()` (private, line 206), `expect()` (private, line 213), `until()` (private, line 221), `encodeValue()` (private, line 254), `encodeObject()` (private, line 294)


## Slashes

`final class Minn\Support\Slashes` · `public/minn/src/Minn/Support/Slashes.php`

Magic-quote slashes the way WordPress keeps them (wp_slash, wp_unslash):
added to or stripped from every string inside a value, arrays and
objects walked all the way down, anything else left as it is.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\RestMeta`, `Minn\Runtime\ApplicationPasswordEvents`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentForm`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostSave`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermSave`

### static `add(mixed $value): mixed`

Every string in a value with its quotes and backslashes slashed.

### static `strip(mixed $value): mixed`

Every string in a value with one level of slashes taken off.

Internals: `deep()` (private, line 27)


## Time

`final class Minn\Support\Time` · `public/minn/src/Minn/Support/Time.php`

Human-scale spans: a number of seconds as the largest whole unit it fills, rounded, never below one.

- const `UNITS` = `array (   0 =>    array (     0 => 'second',     1 => 1,   ),   1 =>    array (     0 => 'minute',     1 => 60,   ),   2 =>    array (     0 => 'hour',     1 => 3600,   ),   3 =>    array (     0 => 'day',     1 => 86400,   ),   4 =>    array (     0 => 'week',     1 => 604800,   ),   5 =>    array (     0 => 'month',     1 => 2592000,   ),   6 =>    array (     0 => 'year',     1 => 31536000,   ), )`

### static `span(int $seconds): array`

A number of seconds as its largest whole unit and count.

- `@return array{0: int, 1: string} count and unit name`


## Url

`final class Minn\Support\Url` · `public/minn/src/Minn/Support/Url.php`

URL shaping the escaping and query helpers share: the character cleanup
esc_url applies, bracket encoding outside the authority, and query
argument merging. Behaviour pinned by contracts/fixtures/api/functions.json.

Used by: `Minn\Front\FeedTags`, `Minn\Front\PostEmbed`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\CommentPages`, `Minn\Runtime\NavMenu`, `Minn\Runtime\PostLinks`

### static `withTrailingSlash(string $value): string`

The value with one trailing slash, whatever slashes or backslashes it ended in (trailingslashit).

### static `withoutTrailingSlash(string $value): string`

The value with every trailing slash and backslash taken off (untrailingslashit).

### static `clean(string $url): string`

Spaces encoded, stray characters dropped, ";//" healed, a bare host given http; '' when nothing survives.

### static `encodeBrackets(string $url, array $parsed): string`

Square brackets after the authority are percent-encoded; the authority itself is left alone. @param array<string, mixed> $parsed

- `@param array<string, mixed> $parsed`

### static `withQuery(string $uri, array $new, Closure $encode, Closure $build): string`

The URI with query arguments merged in (false removes one), the
fragment kept, a bare query string treated as such; a URI that is
only a query (or nothing) keeps its leading question mark.

- `@param array<string, mixed> $new`
- `@param Closure(array): array $encode encodes the existing arguments the way the reference does`
- `@param Closure(array): string $build builds the query string`

### static `validateForHttp(string $url, string $homeHost, int $homePort, Closure $externalAllowed, ?Closure $safePorts = NULL): ?string`

The checks wp_http_validate_url makes on a URL whose protocol already
passed: an http(s) scheme, a host without credentials or a colon, no
private address unless it is this site or allowed, only the safe
ports (80, 443 and 8080 unless the caller's list says otherwise; the
site's own always). Returns the URL, or null when refused.

- `@param Closure(string, string): bool $externalAllowed whether a private host may be fetched anyway`
- `@param (Closure(list<int>, string, string): array)|null $safePorts the port list for a URL that names a port`

### static `buildQuery(array $data, string $prefix = ''): string`

A query string in the reference's spelling (probe query-args): keys and values as given, nested keys as `a%5Bb%5D`, null left out, booleans as 0/1.

### static `parse(string $url, int $component = -1): array|string|int|false|null`

parse_url that also accepts scheme-relative and path-only URLs; a single component by its PHP_URL_* constant.

### static `withScheme(string $url, string $scheme): string`

The URL under a scheme, or scheme-and-host stripped for 'relative'; a protocol-relative URL is read as http first.

### static `safeRedirect(string $location, Closure $allowedHosts): ?string`

A redirect target the site may send a browser to: http(s) only, no
credentials, and a host the caller allows (local paths always pass).

- `@param Closure(string): list<string> $allowedHosts the hosts allowed for the target's host`

Internals: `isPrivate()` (private, line 143)


## Utf8

`final class Minn\Support\Utf8` · `public/minn/src/Minn/Support/Utf8.php`

Whether bytes are well-formed UTF-8 as the reference judges them: overlong
forms, surrogates, code points past U+10FFFF, stray continuation bytes and
truncated sequences fail; noncharacters, NUL and a byte order mark pass.

Used by: `Minn\Support\Kses`

### static `isValid(string $bytes): bool`

True for well-formed UTF-8, including the empty string.


## WebServer

`final class Minn\Support\WebServer` · `public/minn/src/Minn/Support/WebServer.php`

What the SERVER_SOFTWARE string says about the web server in front of the site.

### static `isApache(string $software): bool`

Whether the server speaks Apache's module and .htaccess conventions: Apache itself, or LiteSpeed.

