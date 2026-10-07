# `Minn\Content`

the repositories and records: posts, users, terms, comments, and the render pipeline

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Autop`](#autop) | final class | 34 | Classic-content paragraphing: blank lines become paragraphs, single |
| [`Blocks`](#blocks) | final class | 46 | The content pipeline's front door: block markup goes through the block |
| [`CommentClasses`](#commentclasses) | final class | 26 | The class tokens a rendered comment carries: its type, its author, odd/even and thread alternation, depth, then the caller's extras. |
| [`CommentModeration`](#commentmoderation) | final readonly class | 43 | Whether a comment may be stored and in what state: the duplicate and |
| [`CommentRecord`](#commentrecord) | final readonly class | 114 | One row of the comments table, read by name: $comment->author, ->content, |
| [`Comments`](#comments) | final readonly class | 268 | Reads and writes over the comments table. |
| [`ContentScan`](#contentscan) | final class | 196 | What a site's stored content asks of the engine: shortcodes, block |
| [`Emoji`](#emoji) | final class | 68 | Emoji as the reference's mail and feeds carry them, from the list the |
| [`Excerpt`](#excerpt) | final class | 101 | The reference's generated excerpt, as captured from probe posts: |
| [`Inventory`](#inventory) | final readonly class | 253 | Plugins, themes, must-use plugins, and drop-ins as they sit on disk. |
| [`MediaShortcodes`](#mediashortcodes) | final class | 187 | The [video] and [audio] shortcodes as the reference prints them: a |
| [`MenuItem`](#menuitem) | final readonly class | 22 | One classic nav_menu_item, fields resolved from the post, its |
| [`Menus`](#menus) | final readonly class | 526 | Classic nav_menu terms and nav_menu_item posts. The front uses these |
| [`MoreTag`](#moretag) | final class | 20 | The `<!--more-->` marker that splits a post into the part a listing shows |
| [`Page`](#page) | final readonly class | 49 | One page of a listing: the rows on it and how many rows the whole |
| [`PasswordGate`](#passwordgate) | final class | 34 | A password-protected post on the front end: its body is the password |
| [`PluginState`](#pluginstate) | final readonly class | 86 | Switching plugins on and off, the way the reference records it: a |
| [`PostClasses`](#postclasses) | final class | 55 | The class list a post carries on its article element, in the reference's |
| [`PostFilter`](#postfilter) | final readonly class | 55 | What a listing is narrowed to. Every field is optional and the object is |
| [`PostRecord`](#postrecord) | final readonly class | 156 | One row of the posts table, read by name. The columns keep their |
| [`PostSlugs`](#postslugs) | final readonly class | 75 | Which slug a live post may take beside the others, as the reference |
| [`PostStatus`](#poststatus) | enum | 42 | The statuses a post row can hold; the value is the column's own spelling. |
| [`PostWriter`](#postwriter) | final readonly class | 482 | Every write to the posts table and its satellites: rows, meta, term |
| [`Posts`](#posts) | final readonly class | 530 | Reads over the posts table. A single post comes back as a PostRecord and |
| [`Reader`](#reader) | final class | 72 | Who is reading this request: their user id, whether they may read |
| [`Revisions`](#revisions) | final readonly class | 95 | Revision rows: the plain snapshots and the per-author autosave slots. |
| [`Site`](#site) | final readonly class | 73 | Site-wide options and the site's clock. |
| [`SiteIcon`](#siteicon) | final readonly class | 50 | The site icon: the attachment the site_icon option names, as the file |
| [`Slug`](#slug) | final class | 81 |  |
| [`TagBalancer`](#tagbalancer) | final class | 82 | Closes what markup leaves open and drops what it closes without opening, |
| [`TermLinks`](#termlinks) | final class | 39 | A post's terms rendered as links, in the two shapes the reference |
| [`TermRecord`](#termrecord) | final readonly class | 88 | One term with its taxonomy row, read by name: $term->name, ->slug, |
| [`Terms`](#terms) | final readonly class | 221 |  |
| [`TextFilters`](#textfilters) | final class | 130 | The small text filters the reference runs over content, titles and |
| [`Texturize`](#texturize) | final class | 51 | The texturize subset the reference applies to rendered text: straight |
| [`UserRecord`](#userrecord) | final readonly class | 96 | One row of the users table, read by name. Columns keep their WordPress |
| [`Users`](#users) | final readonly class | 267 |  |

## Autop

`final class Minn\Content\Autop` · `public/minn/src/Minn/Content/Autop.php`

Classic-content paragraphing: blank lines become paragraphs, single
newlines become line breaks, and block-level tags are never wrapped.
The behaviour is pinned by the api suite row for row.

- const `BLOCKS` = `'(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)'`

### static `apply(string $text, bool $lineBreaks = true): string`

The classic paragraph rules: a blank line makes a paragraph, a single newline a line break when asked.


## Blocks

`final class Minn\Content\Blocks` · `public/minn/src/Minn/Content/Blocks.php`

The content pipeline's front door: block markup goes through the block
renderer (Minn\Blocks), classic content rides the paragraph pipeline.

Used by: `Minn\Admin\RenderController`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\FeedController`, `Minn\Front\Renderer`, `Minn\Rest\CommentObject`, `Minn\Rest\MediaObject`, `Minn\Rest\RenderedFields`, `Minn\Rest\RevisionsController`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`


### static `render(string $raw): string`

Post content as HTML: blocks through the renderer, classic content through the paragraph rules.

### static `renderer(): Minn\Blocks\Renderer`

The block renderer of the request being answered, which its runtime
holds and builds over that request's own database door. With no
runtime (the command line, the unit suite) one off-request renderer
stands in.

### static `paragraphs(string $raw): string`

Plain prose to paragraphs: texturize, split on blank lines, <br /> on
single newlines. Shared by classic post content and comment text.


## CommentClasses

`final class Minn\Content\CommentClasses` · `public/minn/src/Minn/Content/CommentClasses.php`

The class tokens a rendered comment carries: its type, its author, odd/even and thread alternation, depth, then the caller's extras.

### static `build(string $type, ?string $authorClass, bool $byPostAuthor, int $alt, int $depth, int $threadAlt, array $extra): array`

The class list a comment's list item carries, in the reference's order.

- `@param list<string> $extra`
- `@return list<string>`


## CommentModeration

`final readonly class Minn\Content\CommentModeration` · `public/minn/src/Minn/Content/CommentModeration.php`

Whether a comment may be stored and in what state: the duplicate and
flood refusals first, then the site's moderation settings, with a
moderator's own comment always approved.

- const `FLOOD_SECONDS` = `15`

Used by: `Minn\Front\CommentPostController`

```php
__construct(Minn\Content\Comments $comments)
```


### `refusal(array $data, bool $moderator): ?string`

"comment_duplicate", "comment_flood", or null when the comment may proceed.

### `approval(bool $moderator, string $author, string $email, Closure $option): string`

"1" approved or "0" held, from the settings the reader supplies.

- `@param Closure(string): ?string $option`


## CommentRecord

`final readonly class Minn\Content\CommentRecord` · `public/minn/src/Minn/Content/CommentRecord.php` · implements `ArrayAccess`

One row of the comments table, read by name: $comment->author, ->content,
->postId, ->parentId, and isApproved() for the status the reference
stores as '1'. Array access is the migration bridge, read-only.

Used by: `Minn\Admin\Notifications`, `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Content\Comments`, `Minn\Front\CommentList`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Runtime\CommentEvents`

- readonly `int $id`
- readonly `int $postId`
- readonly `string $author`
- readonly `string $authorEmail`
- readonly `string $authorUrl`
- readonly `string $authorIp`
- readonly `string $date`
- readonly `string $dateGmt`
- readonly `string $content`
- readonly `int $karma`
- readonly `string $approved`
- readonly `string $agent`
- readonly `string $type`
- readonly `int $parentId`
- readonly `int $userId`

### static `fromRow(array $row): self`

A record from a comments row; a missing column reads as empty.

- `@param array<string, mixed> $row`

### static `fromRows(array $rows): array`

A record for every row, in order.

- `@param list<array<string, mixed>> $rows @return list<self>`

### `row(): array`

The stored row, for the writers and shapers that still spell columns.

### `isApproved(): bool`

Approved, and so public.

### `isPending(): bool`

Held for moderation.

### `isComment(): bool`

A plain comment: the type column is '' on old rows and 'comment' on new ones.

### `column(string $name): mixed`

One stored column by its database name, or null.

### `offsetExists(mixed $offset): bool`

The migration bridge: the record answers to its column names the way the row did.

### `offsetGet(mixed $offset): mixed`

The migration bridge: one column by its stored name, or null.

### `offsetSet(mixed $offset, mixed $value): never`

Records are read-only; writes go through the repository.

### `offsetUnset(mixed $offset): never`

Records are read-only; writes go through the repository.


## Comments

`final readonly class Minn\Content\Comments` · `public/minn/src/Minn/Content/Comments.php`

Reads and writes over the comments table.

- const `UPDATABLE` = `array (   0 => 'comment_post_ID',   1 => 'comment_author',   2 => 'comment_author_email',   3 => 'comment_author_url',   4 => 'comment_author_IP',   5 => 'comment_date',   6 => 'comment_date_gmt',   7 => 'comment_content',   8 => 'comment_karma',   9 => 'comment_approved',   10 => 'comment_agent',   11 => 'comment_type',   12 => 'comment_parent',   13 => 'user_id', )`
- const `FIELD_LENGTHS` = `array (   'comment_author' => 245,   'comment_author_email' => 100,   'comment_author_url' => 200,   'comment_content' => 65525, )` — The comment form fields whose length the table limits, with the length a stock table gives each.

Used by: `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Content\CommentModeration`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\Services`, `Minn\Runtime\CommentEvents`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db)
```


### `find(int $id): ?Minn\Content\CommentRecord`

The comment with this id, or null.

### `meta(int $id, string $key): ?string`

One meta value of a comment, or null when it has none.

### `addMeta(int $id, string $key, string $value): void`

Adds a meta row; a second row with the same key is allowed, as the reference allows it.

### `duplicate(int $postId, string $author, string $email, string $content, int $userId): bool`

The same words on the same post from the same person, in any status but trash or spam.

### `duplicateId(int $postId, string $author, string $email, string $content, int $userId): ?int`

The id of a comment this one would repeat (same post, same author, same words, not spam or trash), or null.

### `lastCommentTime(int $userId, string $address, string $email, string $sinceGmt): ?int`

When the commenter last commented since the GMT time given, as a Unix
time: matched by user id when signed in, else by address, or by email
either way. Null when they have not.

### `hasApprovedByEmail(string $email): bool`

Whether this author email has any approved comment, the previously-approved gate check_comment applies.

### `flooding(string $email, string $address, int $seconds): bool`

Whether this author, by email or by address, commented within the last so many seconds: the flood check.

### `pendingCounts(array $postIds): array`

Held comments per post, for the ids given (a post without any reads 0).

### `previouslyApproved(string $author, string $email): bool`

True when this name and email already have an approved comment.

### `insert(array $columns): int`

Inserts a row from column => value pairs and returns the new id.

- `@param array<string, mixed> $columns`

### `update(int $id, array $columns): void`

Sets the given columns on one comment.

- `@param array<string, mixed> $columns`

### `fieldLengths(): array`

How long each comment form field may be, read from the table as it is:
a character column allows its declared length, a text column ten bytes
fewer than it holds. A column the schema does not describe keeps the
stock table's length.

- `@return array<string, int>`

### `idsOf(int $postId): array`

The ids of every comment on a post, whatever its status, oldest first. @return list<int>

- `@return list<int>`

### `statusesOf(int $postId): array`

Every comment on a post as id => status, which the trash keeps to give back. @return array<int, string>

- `@return array<int, string>`

### `setStatusOf(array $ids, string $status): void`

Sets one status on the given comments. @param list<int> $ids

- `@param list<int> $ids`

### `orphanReplies(int $id, int $parent): void`

A deleted comment's replies move up to its parent.

### `delete(int $id): void`

Removes the comment and its meta; the post's count is the caller's to recount.

### `recount(int $postId): void`

Recomputes a post's stored comment_count (approved comments only).

### static `statusOf(string $approved): string`

DB approval token to the wp/v2 status string.

### static `tokensFor(string $status): ?array`

wp/v2 status parameter to DB token(s); null for an unknown status.

- `@return list<string>|null`

### static `changedColumns(array $data, array $current): array`

The columns an update really changes, compared as strings against the
stored row; the approval shorthands hold/approve become the stored 0/1.

- `@param array<string, mixed> $data`
- `@param array<string, mixed> $current`
- `@return array<string, mixed>`


## ContentScan

`final class Minn\Content\ContentScan` · `public/minn/src/Minn/Content/ContentScan.php`

What a site's stored content asks of the engine: shortcodes, block
names, extra tables, extra post types. Preflight lights the findings;
the scan itself is pure string and list work so a suite can pin it
without a database.

- const `CORE_TABLES` = `array (   0 => 'commentmeta',   1 => 'comments',   2 => 'links',   3 => 'options',   4 => 'postmeta',   5 => 'posts',   6 => 'term_relationships',   7 => 'term_taxonomy',   8 => 'termmeta',   9 => 'terms',   10 => 'usermeta',   11 => 'users', )` — Tables the engine already speaks. Anything else is plugin data.
- const `CORE_TYPES` = `array (   0 => 'post',   1 => 'page',   2 => 'attachment',   3 => 'revision',   4 => 'nav_menu_item',   5 => 'custom_css',   6 => 'customize_changeset',   7 => 'oembed_cache',   8 => 'user_request',   9 => 'wp_block',   10 => 'wp_template',   11 => 'wp_template_part',   12 => 'wp_global_styles',   13 => 'wp_navigation',   14 => 'wp_font_family',   15 => 'wp_font_face', )` — Built-in types the engine stores. Public ones are in data/types.json;
the rest are silent core types that still occupy the posts table.
- const `CONTENT_TYPES` = `array (   0 => 'post',   1 => 'page',   2 => 'wp_block',   3 => 'wp_template',   4 => 'wp_template_part',   5 => 'wp_navigation', )` — post_content that a visitor (or a theme template) can actually see.

Used by: `Minn\Cli\Preflight`

### static `shortcodes(string $content): array`

Opening shortcode tags in $content. Escaped [[tag]] is skipped;
closers [/tag] never match because a tag name starts with a letter.

- `@return array<string, int> tag => occurrences`

### static `blocks(string $content): array`

Block names in $content, namespace included. A delimiter without a
namespace is core/, matching the parser.

- `@return array<string, int> name => occurrences`

### static `extraTables(array $tables, string $prefix): array`

The tables under the prefix that are not the reference's own.

- `@param list<string> $tables names from SHOW TABLES`
- `@return list<string> bare names after stripping the prefix`

### static `tableFamilies(array $bare): array`

Group extra tables by the first underscore segment. Wordfence's
tables are wfHits and wfls_* with no shared underscore prefix, so
those two stems collapse to one family.

- `@param list<string> $bare`
- `@return list<string>`

### static `extraTypes(array $types): array`

The post types in use that the reference does not register itself.

- `@param list<string> $types`
- `@return list<string>`

### static `thirdParty(array $named): array`

The block names that are not core/*, with their counts.

- `@param array<string, int> $named`
- `@return array<string, int>`

### static `listed(array $names, int $cap = 40): string`

Names sorted and comma-separated, cut with a count past the cap.

- `@param list<string> $names`

Internals: `walk()` (private, line 200)


## Emoji

`final class Minn\Content\Emoji` · `public/minn/src/Minn/Content/Emoji.php`

Emoji as the reference's mail and feeds carry them, from the list the
reference ships (data/emoji.json, read from it): encode() turns each emoji
character into its entity; staticize() turns each listed sequence in the
text (outside tags, and outside code and pre) into a CDN image, longest
sequence first. Text that already holds an entity is not encoded first;
once anything matched, the variation selectors left over are dropped.

- const `IGNORED` = `array (   0 => 'code',   1 => 'pre', )`


### static `encode(string $text): string`

Each emoji character in the text as its hexadecimal entity.

### static `staticize(string $text, string $baseUrl, string $extension): string`

The text with its emoji as images under $baseUrl (each file named for its code points, $extension after).

Internals: `replaceInText()` (private, line 58), `list()` (private, line 78)


## Excerpt

`final class Minn\Content\Excerpt` · `public/minn/src/Minn/Content/Excerpt.php`

The reference's generated excerpt, as captured from probe posts:

- Only text-bearing blocks contribute. Containers (group, columns,
column, media-text) pass their allowed children through; anything not
on the list vanishes with everything inside it (code, details, buttons,
images, galleries, covers, dynamic blocks, and the nested list-item,
so a modern list contributes nothing while a classic one does).
- The text stops at the first <!--more--> (in a feed it runs on).
- Block-level tags read as spaces; inline tags (and <br>) read as nothing.
- Whitespace collapses, 55 words, an ellipsis when cut, wrapped in <p>,
texturized (a feed texturizes first, so its inline code stays raw). A hand-written excerpt skips the block filter.

- const `ALLOWED` = `array (   0 => 'core/paragraph',   1 => 'core/heading',   2 => 'core/list',   3 => 'core/quote',   4 => 'core/pullquote',   5 => 'core/verse',   6 => 'core/preformatted',   7 => 'core/table',   8 => 'core/group',   9 => 'core/columns',   10 => 'core/column',   11 => 'core/media-text',   12 => 'core/html',   13 => 'core/more',   14 => 'core/freeform', )`
- const `INLINE` = `array (   0 => 'a',   1 => 'abbr',   2 => 'b',   3 => 'bdi',   4 => 'bdo',   5 => 'br',   6 => 'cite',   7 => 'code',   8 => 'data',   9 => 'dfn',   10 => 'em',   11 => 'i',   12 => 'kbd',   13 => 'mark',   14 => 'q',   15 => 's',   16 => 'samp',   17 => 'small',   18 => 'span',   19 => 'strong',   20 => 'sub',   21 => 'sup',   22 => 'time',   23 => 'u',   24 => 'var',   25 => 'wbr',   26 => 'del',   27 => 'ins', )`

Used by: `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\Renderer`, `Minn\Rest\RenderedFields`

### static `render(Minn\Content\PostRecord $post): string`

The more tag ends a listing's excerpt but not a feed's, and a feed
texturizes before the tags go (so inline code keeps straight quotes)
where a listing texturizes the finished text.

### static `forFeed(Minn\Content\PostRecord $post): string`

The excerpt as a feed carries it: the whole content counts (no stop at
the more tag), texturize runs before the tags are stripped, and the
paragraph is not texturized again.

Internals: `source()` (private, line 62), `words()` (private, line 73), `recordRendered()` (private, line 96), `allowedMarkup()` (private, line 106)


## Inventory

`final readonly class Minn\Content\Inventory` · `public/minn/src/Minn/Content/Inventory.php`

Plugins, themes, must-use plugins, and drop-ins as they sit on disk.
The shapes match `wp plugin list` / `wp theme list` captured from the
reference: name is the directory (or the drop-in filename), title and
version come from the file headers, status from the options.

- const `DROPINS` = `array (   0 => 'advanced-cache.php',   1 => 'db.php',   2 => 'db-error.php',   3 => 'fatal-error-handler.php',   4 => 'install.php',   5 => 'maintenance.php',   6 => 'object-cache.php',   7 => 'php-error.php',   8 => 'sunrise.php', )` — Drop-in filenames at wp-content/ that hosting tools treat as WordPress drop-ins.
- const `KNOWN_DROPINS` = `array (   'advanced-cache.php' =>    array (     0 => 'Advanced caching plugin.',     1 => 'WP_CACHE',   ),   'db.php' =>    array (     0 => 'Custom database class.',     1 => true,   ),   'db-error.php' =>    array (     0 => 'Custom database error message.',     1 => true,   ),   'install.php' =>    array (     0 => 'Custom installation script.',     1 => true,   ),   'maintenance.php' =>    array (     0 => 'Custom maintenance message.',     1 => true,   ),   'object-cache.php' =>    array (     0 => 'External object cache.',     1 => true,   ),   'php-error.php' =>    array (     0 => 'Custom PHP error message.',     1 => true,   ),   'fatal-error-handler.php' =>    array (     0 => 'Custom PHP fatal error handler.',     1 => true,   ), )` — The drop-ins a single site recognises, in the reference's order: the
file => its description and the constant that must be true for it to
load (true when nothing gates it).

Used by: `Minn\Cli\AssetUpdate`, `Minn\Cli\MinnCommand`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Content\PluginState`, `Minn\Cron\Cron`, `Minn\Ops\InstalledSoftware`, `Minn\Ops\Packages`, `Minn\Ops\Updates`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

```php
__construct(string $contentDir, Minn\Content\Site $site)
```


### `plugins(): array`

Regular plugins, then must-use, then drop-ins, each group sorted by name.

- `@return list<array<string, mixed>>`

### `mustUse(): array`

The mu-plugins folder's PHP files, each with its header.

- `@return list<array<string, mixed>>`

### `dropins(): array`

The drop-in files present in wp-content.

- `@return list<array<string, mixed>>`

### `themes(): array`

Every theme folder with its style.css header.

- `@return list<array<string, mixed>>`

### `pluginFiles(): array`

Every regular plugin's main file: relative "dir/file.php" (or
"file.php" for a single-file plugin) to its absolute path.

- `@return array<string, string>`

Internals: `regularPlugins()` (private, line 161), `mainPluginFile()` (private, line 236), `item()` (private, line 256)


## MediaShortcodes

`final class Minn\Content\MediaShortcodes` · `public/minn/src/Minn/Content/MediaShortcodes.php`

The [video] and [audio] shortcodes as the reference prints them: a
plugin may take either over first (wp_*_shortcode_override, handed the
shortcode's own attributes and its instance number), then the source by
src or by each format's attribute (a file of another kind is only a
link; YouTube and Vimeo addresses are video of their own; with none,
the post's first attached video or audio), the player's attributes (a
video no wider than the content, its height in proportion; the flags
bare), each source numbered by the instance, a fallback link, and the
class, library and final HTML through their filters.

- const `YOUTUBE` = `'#^https?://(?:www\\.)?(?:youtube\\.com/watch|youtu\\.be/)#'`
- const `VIMEO` = `'#^https?://(.+\\.)?vimeo\\.com/.*#'`

### static `video(array|string $attr, string $content): ?string`

[video]. @param array<string, mixed>|string $attr

- `@param array<string, mixed>|string $attr`

### static `audio(array|string $attr, string $content): ?string`

[audio]. @param array<string, mixed>|string $attr

- `@param array<string, mixed>|string $attr`

### static `attached(string $type, mixed $post): array`

get_attached_media: the post's attachments of a kind ("video", "audio", a MIME type), as the reference's children query finds them.

Internals: `sources()` (private, line 104), `anyOwnFormat()` (private, line 132), `sourceTags()` (private, line 143), `attributes()` (private, line 157), `fitted()` (private, line 170), `library()` (private, line 177), `fallback()` (private, line 188), `postId()` (private, line 193), `instance()` (private, line 199)


## MenuItem

`final readonly class Minn\Content\MenuItem` · `public/minn/src/Minn/Content/MenuItem.php`

One classic nav_menu_item, fields resolved from the post, its
`_menu_item_*` meta, and the object it points at.

Used by: `Minn\Content\Menus`, `Minn\Rest\MenuItemObject`

```php
__construct(int $id, string $title, string $url, string $type, string $object, int $objectId, int $parent, int $menuOrder, string $target, array $classes, array $xfn, string $attrTitle, string $description, string $status, int $menuId, bool $invalid)
```

- readonly `int $id`
- readonly `string $title`
- readonly `string $url`
- readonly `string $type`
- readonly `string $object`
- readonly `int $objectId`
- readonly `int $parent`
- readonly `int $menuOrder`
- readonly `string $target`
- readonly `array $classes`
- readonly `array $xfn`
- readonly `string $attrTitle`
- readonly `string $description`
- readonly `string $status`
- readonly `int $menuId`
- readonly `bool $invalid`


## Menus

`final readonly class Minn\Content\Menus` · `public/minn/src/Minn/Content/Menus.php`

Classic nav_menu terms and nav_menu_item posts. The front uses these
when a navigation block has no inner blocks and no wp_navigation post;
REST serves the same rows as wp/v2/menus and menu-items.

Used by: `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\Services`, `Minn\Runtime\MenuEvents`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Terms $terms, Minn\Front\Permalinks $permalinks, ?Minn\Content\PostWriter $writer = NULL, ?Minn\Content\Site $site = NULL)
```


### `all(): array`

Every nav_menu term.

- `@return list<TermRecord>`

### `find(int $id): ?Minn\Content\TermRecord`

One menu by id, or null.

### `idByName(string $name): ?int`

The id of the menu with exactly this name, or null.

### `items(?int $menuId = NULL): array`

The items of one menu, or of every menu, in menu order.

- `@return list<MenuItem>`

### `fallbackBlocks(): array`

Navigation-link blocks for the first classic menu. Empty when the
site has no nav_menu terms with items.

- `@return list<Block>`

### `toBlock(Minn\Content\MenuItem $item): Minn\Blocks\Block`

A menu item as the navigation block it renders through.

### `autoAdd(int $menuId): bool`

Whether this menu auto-adds new top-level pages, from the nav_menu_options blob.

### `locationsFor(int $menuId): array`

Location slugs from the active theme's theme_mods that point at this menu.

- `@return list<string>`

### `themeLocations(): array`

The theme's registered locations mapped to menu ids, from theme_mods.

- `@return array<string, int> location => menu term id`

### `findItem(int $id): ?Minn\Content\MenuItem`

One nav_menu_item by id, or null when it is missing or trashed.

### `refuseName(string $name, int $keeping = 0): ?Minn\Runtime\Refusal`

Whether a name may be given to a menu. A menu keeps its own name; any
other menu holding it refuses the write, and the refused id rides along
so a caller can point at the menu in the way.

### `createMenu(string $name, string $description = ''): int`

Creates a nav_menu term and returns its id.

### `updateMenu(int $id, ?string $name, ?string $description): void`

Renames or re-describes a menu; null keeps the current value.

### `deleteMenu(int $id): void`

Deletes a menu and every item in it.

### `createItem(array $fields): int`

title: string,
url: string,
type: string,
object: string,
objectId: int,
parent: int,
menuOrder: int,
target: string,
status: string,
menuId: int,
attrTitle: string,
description: string,
authorId: int,
classes?: list<string>,
xfn?: string
} $fields

- `@param array{`

### `updateItem(int $id, array $fields): void`

Applies the given fields to one item, stored the way the reference stores them.

- `@param array<string, mixed> $fields`

### `itemsPointingAt(int $objectId, string $type): array`

The ids of the menu items, in any menu or none, that point at an object of a kind (post_type, taxonomy). @return list<int>

- `@return list<int>`

### `appendPage(int $menuId, int $pageId, int $authorId): int`

Adds a page to the end of a menu, titled by the page itself, as a menu set to add new pages takes it.

### `deleteItem(int $id): void`

Hard-deletes one item.

Internals: `hydrate()` (private, line 185), `meta()` (private, line 244), `menuIdOf()` (private, line 257), `classList()` (private, line 269), `xfnList()` (private, line 279), `writeMeta()` (private, line 504), `originalParent()` (private, line 514), `content()` (private, line 524), `writer()` (private, line 529), `site()` (private, line 537)


## MoreTag

`final class Minn\Content\MoreTag` · `public/minn/src/Minn/Content/MoreTag.php`

The `<!--more-->` marker that splits a post into the part a listing shows
and the part only the single view does. The marker may carry its own link
text; a second marker further down is ordinary content and stays where it
is, as does the `<!--noteaser-->` flag that follows some of them.

### static `split(string $content): array`

The content on either side of a more tag, with the tag's own text.

- `@return array{main: string, extended: string, more_text: string}`


## Page

`final readonly class Minn\Content\Page` · `public/minn/src/Minn/Content/Page.php`

One page of a listing: the rows on it and how many rows the whole
listing has, which is what pagination is counted from.

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Posts`, `Minn\Theme\MainQueryBridge`

```php
__construct(array $posts, int $total)
```
- `@param list<PostRecord> $posts`

- readonly `array $posts`
- readonly `int $total`

### static `empty(): self`

No rows and a total of zero.

### `isEmpty(): bool`

Whether the page holds no rows.

### `count(): int`

How many rows are on this page.

### `ids(): array`

The ids of the rows on this page.

- `@return list<int>`

### `totalPages(int $perPage): int`

How many pages the whole listing makes at this page size.

### `withPosts(array $posts): self`

The same page with other rows on it and the same total behind it.


## PasswordGate

`final class Minn\Content\PasswordGate` · `public/minn/src/Minn/Content/PasswordGate.php`

A password-protected post on the front end: its body is the password
form, its excerpt a fixed sentence, its title prefixed, exactly as the
reference shows them to a reader who has not entered the password.

- const `EXCERPT` = `'There is no excerpt because this is a protected post.'`

Used by: `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\Renderer`, `Minn\Theme\ClassicContent`

### static `is(Minn\Content\PostRecord $post): bool`

True while the post has a password the reader's cookie does not match.

### static `title(Minn\Content\PostRecord $post): string`

"Protected: " for a password, "Private: " for a private post, as the reference prefixes titles.

### static `form(Minn\Content\PostRecord $post, string $siteUrl, string $permalink): string`

The form, with the reference's stray closing p after the hidden field.


## PluginState

`final readonly class Minn\Content\PluginState` · `public/minn/src/Minn/Content/PluginState.php`

Switching plugins on and off, the way the reference records it: a
WordPress plugin file joins or leaves the sorted active_plugins list; a
Minn extension joins or leaves minn_active_extensions, and deactivating
one also releases the plugin files it stood in for. The REST toggle and
the CLI verbs share this so they cannot drift.

Used by: `Minn\Cli\PluginCommand`, `Minn\Rest\PluginsController`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Extension\Loader $extensions)
```


### `find(string $slug): Minn\Extension\Manifest|string|null`

The relative "dir/file.php" of a WordPress plugin, else the extension
for a slug, else null. A folder carrying both records in
active_plugins like the reference does (the loader counts its own
folder there as active); only a pure extension uses the engine's list.

### `isActive(Minn\Extension\Manifest|string $plugin): bool`

Whether a plugin, by file, or a Minn extension is active.

### `activate(Minn\Extension\Manifest|string $plugin): void`

Records a plugin or an extension as active or not, in the option each kind uses.

### `deactivate(Minn\Extension\Manifest|string $plugin): void`

Records a plugin, by file, or a Minn extension as inactive; an extension also deactivates the plugins it replaced.

Internals: `ownWithout()` (private, line 80), `addFile()` (private, line 86), `removeFile()` (private, line 93), `filesWithout()` (private, line 99)


## PostClasses

`final class Minn\Content\PostClasses` · `public/minn/src/Minn/Content/PostClasses.php`

The class list a post carries on its article element, in the reference's
order: caller extras, identity, type, status, format, password state,
thumbnail, sticky, hentry, then one class per term of every public
taxonomy (post_tag reads as "tag-", post_format is the format above).

### static `build(array $post, array $extra, ?string $format, bool $thumbnail, bool $sticky, bool $passwordRequired, bool $hasPassword, array $terms): array`

The class list a post carries on its article element, in the reference's order.

- `@param list<string> $extra`
- `@param list<array{taxonomy: string, slug: string, term_id: int}> $terms`
- `@return list<string>`

### static `htmlClass(string $value): string`

The reference keeps letters, digits, hyphens and underscores in a class name.


## PostFilter

`final readonly class Minn\Content\PostFilter` · `public/minn/src/Minn/Content/PostFilter.php`

What a listing is narrowed to. Every field is optional and the object is
immutable, so a filter reads as a sentence: types('post')->inTerm(12).
Dates are site-local "Y-m-d H:i:s" bounds, from inclusive, to exclusive.

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Posts`, `Minn\Front\ArchiveAddresses`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\RuleRoutes`, `Minn\Theme\MainQueryBridge`

```php
__construct(array $types = array (   0 => 'post', ), ?int $term = NULL, ?int $author = NULL, ?string $from = NULL, ?string $to = NULL, ?string $search = NULL)
```
- `@param list<string> $types`

- readonly `array $types`
- readonly `?int $term`
- readonly `?int $author`
- readonly `?string $from`
- readonly `?string $to`
- readonly `?string $search`

### static `all(): self`

Published posts of type post, nothing narrower.

### static `types(string ...$types): self`

A filter over these post types.

### `inTerm(int $termTaxonomyId): self`

Posts linked to a term, by its term_taxonomy_id.

### `byAuthor(int $userId): self`

The same filter narrowed to one author.

### `between(string $from, string $to): self`

The same filter narrowed to a date window.

### `matching(string $search): self`

The same filter narrowed by a search string.

### `hasDates(): bool`

Whether both ends of the date window are set.


## PostRecord

`final readonly class Minn\Content\PostRecord` · `public/minn/src/Minn/Content/PostRecord.php` · implements `ArrayAccess`

One row of the posts table, read by name. The columns keep their
WordPress spelling on the way in (row()) and get plain names here:
$post->title, $post->slug, $post->status. A record is built from a row
and can hand the row back, so a writer or a REST shaper that still
works in columns is not disturbed.

Array access is the migration bridge: code that still reads
$post['post_title'] keeps working while it is moved over. New code
reads the properties. The style suite counts the bracket reads down.

Used by: `Minn\Auth\Capabilities`, `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Excerpt`, `Minn\Content\Page`, `Minn\Content\PasswordGate`, `Minn\Content\PostStatus`, `Minn\Content\PostWriter`, `Minn\Content\Posts`, `Minn\Engine`, `Minn\Extension\SeamRunner`, `Minn\Front\ArchiveAddresses`, `Minn\Front\AttachmentAddresses`, `Minn\Front\Canonical`, `Minn\Front\CommentPostController`, `Minn\Front\Endpoints`, `Minn\Front\Permalinks`, `Minn\Front\Renderer`, `Minn\Front\RequestParse`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Front\RuleRoutes`, `Minn\Front\SingleAddresses`, `Minn\Front\SingleQueries`, `Minn\Media\Writer`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\RenderedFields`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Runtime\CommentCloser`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostSave`, `Minn\Theme\ClassicContent`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\UserStyles`

- readonly `int $id`
- readonly `int $authorId`
- readonly `string $date`
- readonly `string $dateGmt`
- readonly `string $content`
- readonly `string $title`
- readonly `string $excerpt`
- readonly `string $status`
- readonly `string $commentStatus`
- readonly `string $pingStatus`
- readonly `string $password`
- readonly `string $slug`
- readonly `string $modified`
- readonly `string $modifiedGmt`
- readonly `int $parentId`
- readonly `string $guid`
- readonly `int $menuOrder`
- readonly `string $type`
- readonly `string $mimeType`
- readonly `int $commentCount`

### static `fromRow(array $row): self`

A record from a posts row; a missing column reads as empty.

- `@param array<string, mixed> $row a posts-table row, joined columns welcome`

### static `fromRows(array $rows): array`

A record for every row, in order.

- `@param list<array<string, mixed>> $rows @return list<self>`

### `row(): array`

The stored row, for the writers and shapers that still spell columns.

The row as the table holds it, joined columns and all.

### `status(): ?Minn\Content\PostStatus`

The status as an enum case, or null for a status only a plugin knows.

### `isPublished(): bool`

Published, and so public.

### `isLive(): bool`

Publish, future or private: it counts, it links, it is not a draft.

### `isTrashed(): bool`

In the trash.

### `isProtected(): bool`

Behind a password.

### `isPage(): bool`

A page.

### `isAttachment(): bool`

An attachment.

### `column(string $name): mixed`

One stored column by its database name, or null.

A joined column, or any column by its table name.

### `offsetExists(mixed $offset): bool`

The migration bridge: the record answers to its column names the way the row did.

### `offsetGet(mixed $offset): mixed`

The migration bridge: one column by its stored name, or null.

### `offsetSet(mixed $offset, mixed $value): never`

Records are read-only; writes go through the repository.

### `offsetUnset(mixed $offset): never`

Records are read-only; writes go through the repository.


## PostSlugs

`final readonly class Minn\Content\PostSlugs` · `public/minn/src/Minn/Content/PostSlugs.php`

Which slug a live post may take beside the others, as the reference
settles it (probe insert-defaults). A flat type's slugs are its own: a
post, a page and a plugin's type may share one. A hierarchical type's
are its own and its attachments' under the same parent. An attachment's
are every post's. Never free: the feed names and embed; for a
hierarchical type, a number or a page number (2, page2); for posts, a
number a date archive would answer to: any number when the permalink
structure begins with the post name, a month (below 13) when the post
name follows the year, a day (below 32) when it follows the month. A post
keeps a number it already has. A taken slug gets the first free -2, -3
form, cut (and stripped of a trailing hyphen) to fit the column's 200
characters. The slug is taken as given, not sanitized.

- const `LENGTH` = `200`
- const `FEEDS` = `array (   0 => 'feed',   1 => 'rdf',   2 => 'rss',   3 => 'rss2',   4 => 'atom', )`

Used by: `Minn\Content\PostWriter`, `Minn\Runtime\PostSave`

```php
__construct(Minn\Db $db, Minn\Content\Site $site)
```


### `unique(string $slug, int $excludeId, string $type, int $parent, ?Closure $bad = NULL): string`

The slug, or its first free numbered form.

- `@param Closure(string): bool|null $bad a plugin's say over the slug asked for (the bad-slug filters), asked last`

Internals: `taken()` (private, line 53), `reserved()` (private, line 66), `hierarchical()` (private, line 95)


## PostStatus

`enum Minn\Content\PostStatus` · `public/minn/src/Minn/Content/PostStatus.php`

The statuses a post row can hold; the value is the column's own spelling.

Cases: `Publish` = `'publish'`, `Draft` = `'draft'`, `Pending` = `'pending'`, `Private` = `'private'`, `Future` = `'future'`, `Trash` = `'trash'`, `Inherit` = `'inherit'`, `AutoDraft` = `'auto-draft'`

Used by: `Minn\Content\PostRecord`, `Minn\Content\Reader`, `Minn\Rest\PostsWriteController`

### static `of(Minn\Content\PostRecord|array $row): ?self`

A row's status, or null for a value no enum case spells (a plugin's own status).

### `isLive(): bool`

Publish, future and private are live: they count, they link, they are not drafts.

### `isPublic(): bool`

Whether the status shows the post to anonymous readers.

### static `values(array $statuses): array`

The stored strings of these statuses.

- `@param list<self> $statuses @return list<string> the column values, for a query`


## PostWriter

`final readonly class Minn\Content\PostWriter` · `public/minn/src/Minn/Content/PostWriter.php`

Every write to the posts table and its satellites: rows, meta, term
links with the published counts the reference trusts on read, sticky
and format side effects, and revision snapshots.

- const `FLOATING` = `array (   0 => 'draft',   1 => 'pending',   2 => 'auto-draft', )` — The statuses whose post may have a floating date (no GMT date yet).
- const `ZERO_DATE` = `'0000-00-00 00:00:00'`
- const `LENGTHS` = `array (   'post_status' => 20,   'comment_status' => 20,   'ping_status' => 20,   'post_password' => 255,   'post_name' => 200,   'guid' => 255,   'post_type' => 20,   'post_mime_type' => 100, )` — The posts table's short text columns, cut to fit as the reference's database layer cuts them.

Used by: `Minn\Admin\EditorController`, `Minn\Content\Menus`, `Minn\Content\Revisions`, `Minn\Cron\Cron`, `Minn\Media\Writer`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Services`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostInsert`, `Minn\Runtime\TermWriter`, `Minn\Theme\TemplateWriter`, `Minn\Theme\UserStyles`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Site $site)
```


### `insert(array $columns): int`

Inserts a posts row from column => value pairs and returns the new id.

- `@param array<string, mixed> $columns`

### `update(int $id, array $columns): void`

Sets the given columns on one post; nothing happens for none.

- `@param array<string, mixed> $columns`

### `setStatus(int $id, string $status): void`

Changes one post's status.

### `setType(int $id, string $type): bool`

Moves a post to another type, leaving it alone when it is already there.

### `setMeta(int $id, string $key, string $value): void`

Sets one meta value, inserting the row when the key is new.

### static `floating(Minn\Content\PostRecord $post): bool`

Whether a post's date floats: never given one, it is still a draft or pending with no GMT date.

### `addMeta(int $id, string $key, string $value): void`

Adds a meta row even when the key already has one (a non-unique key).

### `rememberOld(int $id, string $key, string $was, string $now): void`

Keeps a published post's previous slug or date on record
(_wp_old_slug, _wp_old_date) so its old address still finds it: the
value it moves to stops being an old one, the value it leaves becomes
one, once.

### `ensureCategory(int $id): void`

Gives a post the site's default category when it has none, as every save of a post does in the reference.

### `trash(Minn\Content\PostRecord $post): void`

The row of a post moving to the trash, as the reference writes it: the
slug gains __trashed, the modified time moves, a floating draft's date
settles, a post keeps a category, and the published counts follow. What
the trash keeps for the way back (the status, the time, the slug it
had) is meta the caller adds, and the revision comes from the save.

### `deleteMeta(int $id, string $key): void`

Removes every meta row with this key from a post.

### `uniqueSlug(string $desired, int $excludeId, string $type = 'post', int $parent = 0): string`

A free slug for the desired text, sanitized first, in the type's scope (see PostSlugs).

### `slugs(): Minn\Content\PostSlugs`

The rules a live post's slug is settled by.

### `setTerms(int $id, string $taxonomy, array $termIds): void`

Replaces a post's links in one taxonomy and refreshes that taxonomy's counts.

### `taxonomiesOf(int $id): array`

The taxonomies a post has terms in.

- `@return list<string> the distinct taxonomies a post has links in`

### `recountTaxonomiesOf(int $id): void`

Recounts every term the post is in, after a status change.

### `recount(string $taxonomy): void`

term_taxonomy.count is stored and trusted on read, so every status change refreshes it.

### `isSticky(int $id): bool`

Whether the post is in the sticky_posts option.

### `stick(int $id): void`

Rewrites the sticky_posts option with or without one id.

### `unstick(int $id): void`

Takes a post off the sticky list.

### `setFormat(int $id, string $format): void`

Assigns (or clears) the post-format term, creating it on first use.

### `applyExtendedFields(int $id, array $body, string $type): void`

The side effects shared by create and update: sticky, format, featured media.

### `applyCoreMeta(int $id, array $body, string $type): void`

The meta core registers, written directly when no plugins are loaded: footnotes, and a pattern's sync status.

### `applyTerms(int $id, array $body): void`

Assigns categories, tags, and pattern categories from a write body, replacing existing links.

### static `requestedTerms(array $body, ?array $map = NULL): array`

The terms a REST body names, by taxonomy: categories, tags, and pattern
categories, each only when the body has the field.

- `@param array<string, mixed> $body`
- `@param array<string, string>|null $map REST field => taxonomy; the core taxonomies when null`
- `@return array<string, list<int>>`

### `maybeSaveRevision(int $id, int $userId): void`

Snapshots the post's NEW state as a revision exactly when the
reference would: compared against the latest revision, identical
content-bearing fields add nothing, and the first update always
snapshots.

### `revisionColumns(int $id, int $userId): ?array`

The row of the revision a post's current state calls for, or null
when it calls for none (an unrevisioned type, or nothing changed
since the latest revision).

- `@return array<string, mixed>|null`

### `insertRevision(array $columns): int`

Writes a revision row and gives it its guid; returns its id. @param array<string, mixed> $columns

- `@param array<string, mixed> $columns`

### `reparentChildren(int $id, int $parent, array $types): void`

Moves a deleted post's children of the given types to another parent, the way the reference keeps pages and attachments attached. @param list<string> $types

- `@param list<string> $types`

### `reassignAuthor(int $from, int $to): void`

Moves every post of one author to another.

### `db(): Minn\Db`

The database door this writer writes through, for a caller wrapping several of its verbs in one transaction.

### `destroy(int $id): void`

Hard-deletes a post with its revisions and its meta.

Internals: `fit()` (private, line 171), `saveSticky()` (private, line 265), `revisionsToKeep()` (private, line 373), `pruneRevisions()` (private, line 380)


## Posts

`final readonly class Minn\Content\Posts` · `public/minn/src/Minn/Content/Posts.php`

Reads over the posts table. A single post comes back as a PostRecord and
a listing as a Page of them; rendering and escaping happen elsewhere.

Used by: `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Renderer`, `Minn\Content\Menus`, `Minn\Content\PostWriter`, `Minn\Content\SiteIcon`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Front\ArchiveAddresses`, `Minn\Front\Archives`, `Minn\Front\AttachmentAddresses`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\Permalinks`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\RuleRoutes`, `Minn\Front\SingleAddresses`, `Minn\Front\SingleQueries`, `Minn\Media\Writer`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\RevisionsController`, `Minn\Rest\Services`, `Minn\Rest\TemplateObject`, `Minn\Runtime\MenuEvents`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`, `Minn\Theme\UserStyles`

```php
__construct(Minn\Db $db)
```


### `find(int $id): ?Minn\Content\PostRecord`

The post with this id, or null.

### `findByName(string $name, array $types): ?Minn\Content\PostRecord`

The post with this slug among the given types; published only unless asked otherwise.

- `@param list<string> $types`

### `findByNameAnyStatus(string $name, array $types): ?Minn\Content\PostRecord`

The post with this slug among the given types in any status but trash, or null. @param list<string> $types

- `@param list<string> $types`

### `byOldSlug(string $slug, array $types): ?Minn\Content\PostRecord`

The post that once answered to the slug: every former slug stays in
`_wp_old_slug` meta, and the reference redirects it to the current
link whatever the post's status (a draft or trashed post goes to
its `?p=` form).

- `@param list<string> $types`

### `pageByPath(array $segments): ?Minn\Content\PostRecord`

Walks a page hierarchy: ["sample-page", "docs"] finds the page named
docs whose parent is named sample-page at the root.

- `@param list<string> $segments`

### `pageByPathAnyStatus(array $segments): ?Minn\Content\PostRecord`

The page at a slug path in any status but trash, or null. @param list<string> $segments

- `@param list<string> $segments`

### `byTypedPath(array $segments, array $types): ?Minn\Content\PostRecord`

The post at a slug path among the given types (each segment's parent
the one before it), in any status but trash; at the last segment the
first type wins over the others, as the reference prefers the type it
was asked for over the attachment it also looks at.

- `@param list<string> $segments`
- `@param list<string> $types`

### `pathOf(Minn\Content\PostRecord $page): string`

The slash-joined ancestry of a page: "sample-page/docs".

### `guess(string $prefix): ?Minn\Content\PostRecord`

The closest published post or page whose name starts with the given
text: pages first, then posts, newest first within each. This is the
order the reference follows when it guesses a destination for a
missing URL.

### `published(string $type = 'post', int $page = 1, int $perPage = 10): Minn\Content\Page`

The published posts of one type, newest first: the everyday listing.

### `hasPublished(string $type): bool`

Whether any post of a type is published.

### `count(Minn\Content\PostFilter $filter): int`

How many posts a filter reaches, without fetching any.

### `archive(Minn\Content\PostFilter $filter, int $page, int $perPage): Minn\Content\Page`

One page of the posts a filter reaches, newest first, title matches first for a search.

### `meta(int $postId, string $key): ?string`

One meta value of a post, or null when it has none.

### `terms(int $postId, string $taxonomy): array`

The post's terms in one taxonomy, as [term_id, slug] pairs.

- `@return list<array{0: int, 1: string}> term id and slug pairs, by name`

### `revisionCount(int $postId): int`

How many revisions the post has.

### `latestRevisionId(int $postId): int`

The latest revision id, an autosave counting as one, newest by date then id (probe rest-latest-revision), or 0.

### `hasNewerAutosave(int $postId, string $modifiedGmt): bool`

Whether a live post carries an autosave newer than its saved state.

### `next(Minn\Content\PostRecord $post): ?Minn\Content\PostRecord`

The adjacent published post by date; previous = older, next = newer.

### `previous(Minn\Content\PostRecord $post): ?Minn\Content\PostRecord`

The published post of the same type before this one, by date then id, or null.

### `pageTree(): array`

Published pages as a parent => children map, ordered by menu_order then title.

### `listing(Minn\Content\PostFilter $filter, int $page, int $perPage, array $stickyIds = array ( )): Minn\Content\Page`

The main query for a listing: sticky posts lead the first page of the
blog index, followed by the rest by date, and are excluded from later
pages.

- `@param list<int> $stickyIds`
- `@return array{posts: list<array>, total: int}`

### `lastModified(?string $type): ?string`

The newest modification time among published posts, for
get_lastpostmodified: one type or all of them, blog or GMT column.

### `lastModifiedGmt(?string $type): ?string`

The newest GMT modified stamp among published posts of a type, or of the three core types.

### `daysWithPosts(int $year, int $month, string $type): array`

The days of one month a published post of a type was dated on, ascending.

- `@return list<int>`

### `pages(string $sortColumn, string $order): array`

The published pages in a sort order, for a page list: id, parent, title.

- `@return list<array{id: int, parent: int, name: string, title: string}>`

### `archiveBuckets(string $granularity, string $type, string $order, int $limit): array`

The periods holding a published post of a type, newest first by
default: one row per month, year, day, or week, with the count and the
earliest post date inside it.

- `@return list<array{year: int, month: int, day: int, week: int, count: int, first: string}>`

### `archiveList(string $type, string $orderBy, string $order, int $limit): array`

Published posts of a type for a post-by-post archive, by date or by title.

- `@return list<PostRecord>`

### `monthBefore(int $year, int $month, string $type): ?array`

The nearest month before one with a published post of a type, as [year, month], or null.

### `monthAfter(int $year, int $month, string $type): ?array`

The nearest month after one with a published post of a type, as [year, month], or null.

### `newestAutosave(int $postId, int $userId): ?Minn\Content\PostRecord`

The newest autosave of a post by one author, or null.

### `firstCategorySlug(int $postId): ?string`

The slug of the post's first category, or null.

Internals: `record()` (private, line 21), `byName()` (private, line 49), `byPath()` (private, line 124), `scope()` (private, line 236), `like()` (private, line 262), `neighbour()` (private, line 337), `weekMode()` (private, line 462), `monthBeside()` (private, line 497), `latest()` (private, line 506)


## Reader

`final class Minn\Content\Reader` · `public/minn/src/Minn/Content/Reader.php`

Who is reading this request: their user id, whether they may read
private content, whether they may edit a given post, and the
post-password cookie they carry. Built once per surface by the engine,
carried by the request's context, and consulted by the resolver, the
queries, and the renderers.

Used by: `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Cli\Runtime`, `Minn\Content\PasswordGate`, `Minn\Content\Posts`, `Minn\Context`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\CommentPostController`, `Minn\Front\Resolver`, `Minn\Runtime\CurrentUser`, `Minn\Runtime\Runtime`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(int $userId, bool $readsPrivatePosts, bool $readsPrivatePages, Closure $canEditPost, string $postPassword, string $sessionToken = '', array $roles = array ( ))
```
- `@param Closure(int): bool $canEditPost`

- readonly `int $userId`
- readonly `bool $readsPrivatePosts`
- readonly `bool $readsPrivatePages`
- readonly `string $postPassword`
- readonly `string $sessionToken`
- readonly `array $roles`

### static `anonymous(string $postPassword = ''): self`

The reader who is nobody: no session, no private posts, only the post password they typed.

### static `forUser(int $userId, Minn\Auth\Capabilities $capabilities, string $postPassword = '', string $sessionToken = ''): self`

The reader a signed-in user is, asked of the capability engine: what
they may read privately, what they may edit, and the roles they hold.
User 0 is nobody, whatever the capabilities say.

### static `current(): self`

The reader of the request being answered. The runtime holds it (the
context it was built with carries it), so there is one holder and a
request cannot see the reader of the one before it. Without a
runtime, on the command line and in unit tests, nobody is reading.

### `loggedIn(): bool`

Whether the reader has a session.

### `canEdit(int $postId): bool`

Whether this reader may edit a given post.

### `listableStatuses(string $type = 'post'): array`

The statuses a listing may show this reader: published, plus private when they may read it. @return list<string>

- `@return list<string>`


## Revisions

`final readonly class Minn\Content\Revisions` · `public/minn/src/Minn/Content/Revisions.php`

Revision rows: the plain snapshots and the per-author autosave slots.

Used by: `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\RevisionsController`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\PostWriter $writer, Minn\Content\Site $site)
```


### `revisionsOf(int $parentId): array`

The revisions, or the autosaves, of a post, newest first, as rows.

- `@return list<array> newest first`

### `allOf(int $parentId): array`

Every revision of a post, its autosaves among them, newest first by date then id, as rows: what the revisions route lists (probe rest-latest-revision).

### `autosavesOf(int $parentId): array`

The autosaves of a post, newest first, as rows.

### `saveAutosave(int $parentId, int $userId, string $title, string $content, string $excerpt): int`

One autosave slot per author: updated in place when it exists.

Internals: `named()` (private, line 44)


## Site

`final readonly class Minn\Content\Site` · `public/minn/src/Minn/Content/Site.php`

Site-wide options and the site's clock.

Used by: `Minn\Admin\ActivityChart`, `Minn\Admin\App`, `Minn\Admin\BootPayload`, `Minn\Admin\Dashboard`, `Minn\Admin\LanguageController`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\ThemesController`, `Minn\Admin\Translations`, `Minn\Admin\UploadsSize`, `Minn\Blocks\Dynamic\Dates`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Cli\MinnCommand`, `Minn\Cli\Runtime`, `Minn\Content\Inventory`, `Minn\Content\Menus`, `Minn\Content\PluginState`, `Minn\Content\PostSlugs`, `Minn\Content\PostWriter`, `Minn\Content\Revisions`, `Minn\Content\SiteIcon`, `Minn\Context`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Extension\Loader`, `Minn\Extension\Seams`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\ProbeController`, `Minn\Login\LoginController`, `Minn\Mail\MailSettings`, `Minn\Mail\Mailer`, `Minn\Media\Images`, `Minn\Media\Uploads`, `Minn\Media\Writer`, `Minn\Ops\CoreStatus`, `Minn\Ops\Diagnostics`, `Minn\Ops\InstalledSoftware`, `Minn\Ops\Packages`, `Minn\Ops\Updates`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\CommentsController`, `Minn\Rest\IndexController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Services`, `Minn\Rest\Settings`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Runtime\Plugins`, `Minn\Runtime\Recovery`, `Minn\Runtime\Runtime`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\ClassicTheme`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplateWriter`, `Minn\Theme\Theme`, `Minn\Theme\ThemeStyles`, `Minn\Theme\UserStyles`

```php
__construct(Minn\Db $db)
```


### `option(string $name): ?string`

One option's raw value, or null when it is unset.

### `setOption(string $name, string $value): void`

Writes an option, inserting it as autoloaded when it does not exist.

### `deleteOption(string $name): void`

Removes an option row.

### `defaultDiscussion(string $type, string $kind): string`

A new post's comment or ping status, as the reference picks it: a post
follows the site's two defaults, an attachment follows the comment
default and never takes pings, a page and every other type start
closed; with plugins loaded, get_default_comment_status() decides
(support a plugin removed, the filter). $kind is "comment" or
"pingback".

### `gmtOffset(): int`

The gmt_offset option in seconds.

### `localNow(): string`

Now in the site's local time, MySQL format.

### `localDate(string $input): array`

A site-local datetime from a write body ("2031-01-01T00:00:00") to
the pair of columns it fills, plus its timestamp.

- `@return array{0: string, 1: string, 2: int} local, gmt, timestamp`


## SiteIcon

`final readonly class Minn\Content\SiteIcon` · `public/minn/src/Minn/Content/SiteIcon.php`

The site icon: the attachment the site_icon option names, as the file
under uploads it points at. Read by the favicon route, the head links,
and the admin app's sidebar.

Used by: `Minn\Admin\BootPayload`, `Minn\Engine`, `Minn\Front\ProbeController`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks)
```


### `file(): ?string`

The icon's path under uploads, or null when the site has none.

### `urlAt(int $size): string`

The URL of the icon's smallest generated size that covers a square of
$size pixels, or of the original when no size does; empty without an icon.

### `url(): string`

The icon's URL, empty when the site has none.


## Slug

`final class Minn\Content\Slug` · `public/minn/src/Minn/Content/Slug.php`

- const `SAVE_DASHES` = `array (   0 => '%c2%a0',   1 => '%e2%80%93',   2 => '%e2%80%94',   3 => '&nbsp;',   4 => '&#160;',   5 => '&ndash;',   6 => '&#8211;',   7 => '&mdash;',   8 => '&#8212;',   9 => '/', )`
- const `SAVE_DROPPED` = `array (   0 => '%c2%ad',   1 => '%c2%a1',   2 => '%c2%bf',   3 => '%c2%ab',   4 => '%c2%bb',   5 => '%e2%80%b9',   6 => '%e2%80%ba',   7 => '%e2%80%98',   8 => '%e2%80%99',   9 => '%e2%80%9c',   10 => '%e2%80%9d',   11 => '%e2%80%9a',   12 => '%e2%80%9b',   13 => '%e2%80%9e',   14 => '%e2%80%9f',   15 => '%e2%80%a2',   16 => '%c2%a9',   17 => '%c2%ae',   18 => '%c2%b0',   19 => '%e2%80%a6',   20 => '%e2%84%a2',   21 => '%c2%b4',   22 => '%cb%8a',   23 => '%cc%81',   24 => '%cd%81',   25 => '%cc%80',   26 => '%cc%84',   27 => '%cc%8c',   28 => '%e2%82%ac',   29 => '%c2%a3',   30 => '%e2%80%80',   31 => '%e2%80%81',   32 => '%e2%80%82',   33 => '%e2%80%83',   34 => '%e2%80%84',   35 => '%e2%80%85',   36 => '%e2%80%86',   37 => '%e2%80%87',   38 => '%e2%80%88',   39 => '%e2%80%89',   40 => '%e2%80%8a',   41 => '%e2%80%8b',   42 => '%e2%80%8c',   43 => '%e2%80%8d',   44 => '%e2%80%8e',   45 => '%e2%80%8f',   46 => '%e2%80%aa',   47 => '%e2%80%ab',   48 => '%e2%80%ac',   49 => '%e2%80%ad',   50 => '%e2%80%ae',   51 => '%e2%80%af',   52 => '%e2%81%9f',   53 => '%e3%80%80',   54 => '%ef%bb%bf', )`

Used by: `Minn\Content\PostWriter`, `Minn\Content\Terms`, `Minn\Content\Users`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\PostsWriteController`

### static `sanitize(string $text, string $locale = ''): string`

A title as the reference's sanitize_title makes a slug of it when
saving, without the filters (the runtime's sanitize_title adds those):
accents spelled plainly, then the dash form, non-ASCII percent-encoded
up to 200 bytes.

### static `uriEncode(string $text, int $length, Closure $ascii): string`

Non-ASCII characters as lower-case percent escapes, a whole character
at a time, stopping before one would take the text past $length bytes
(0 for no limit); each ASCII character goes through $ascii (as it is,
or rawurlencode).

- `@param Closure(string): string $ascii`

### static `truncate(string $slug, int $length): string`

A slug cut to a byte length without splitting a multibyte character or
leaving a dash hanging off the end.

### static `dashes(string $title, bool $forSave, Closure $utf8Encode): string`

The reference's dash form of a title: tags and marks stripped, spaces to dashes, and the save-time rules when saving.


## TagBalancer

`final class Minn\Content\TagBalancer` · `public/minn/src/Minn/Content/TagBalancer.php`

Closes what markup leaves open and drops what it closes without opening,
as the reference's force_balance_tags does, from its observed rules
(contracts/runtime.md "The content filters"): tag names are lowercased; a
void element is written self-closed (bare as <br />, with attributes as
<img src="x"/>, one already self-closed as given); reopening the
innermost open element closes it first unless it is one of the ten that
may contain themselves; a closer shuts every element opened inside its
own; a closer with nothing to close goes; what is still open at the end
closes in reverse. Script and style text is left as it is, and a "<" not
followed by a name is text.

- const `VOID` = `array (   0 => 'area',   1 => 'base',   2 => 'basefont',   3 => 'br',   4 => 'col',   5 => 'command',   6 => 'embed',   7 => 'frame',   8 => 'hr',   9 => 'img',   10 => 'input',   11 => 'isindex',   12 => 'link',   13 => 'meta',   14 => 'param',   15 => 'source',   16 => 'track',   17 => 'wbr', )`
- const `NESTABLE` = `array (   0 => 'article',   1 => 'aside',   2 => 'blockquote',   3 => 'details',   4 => 'div',   5 => 'figure',   6 => 'object',   7 => 'q',   8 => 'section',   9 => 'span', )`
- const `RAW` = `array (   0 => 'script',   1 => 'style', )`
- const `TAG` = `'/<(\\/?)([a-zA-Z][\\w:-]*)(\\s[^>]*|\\/)?>/'`

### static `balance(string $text): string`

The markup with every element closed in order and every stray closer gone.

Internals: `close()` (private, line 65), `voidAttributes()` (private, line 82), `rawText()` (private, line 92)


## TermLinks

`final class Minn\Content\TermLinks` · `public/minn/src/Minn/Content/TermLinks.php`

A post's terms rendered as links, in the two shapes the reference
emits: a plain joined run for a taxonomy, and the category list, which
becomes a `post-categories` unordered list when the caller names no
separator and a joined run when it does.

Names arrive already filtered; the caller applies the reference's own
filters around the result.

### static `joined(array $terms, string $rel, string $sep): string`

Anchor tags for these terms joined by a separator, the way the reference prints a term list.

- `@param list<array{name: string, url: string}> $terms`

### static `categories(array $categories, string $separator): string`

The category list: an unordered list when the separator is empty,
a joined run otherwise. Both carry rel="category tag".

- `@param list<array{name: string, url: string}> $categories`

Internals: `anchor()` (private, line 52)


## TermRecord

`final readonly class Minn\Content\TermRecord` · `public/minn/src/Minn/Content/TermRecord.php` · implements `ArrayAccess`

One term with its taxonomy row, read by name: $term->name, ->slug,
->taxonomy, ->parentId, ->count. The taxonomy and description are empty
when the query that built the record did not select them. Array access
is the migration bridge, read-only.

Used by: `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Menus`, `Minn\Content\Terms`, `Minn\Front\ArchiveAddresses`, `Minn\Front\Permalinks`, `Minn\Front\Resolution`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\TermFilters`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Runtime\TermWriter`

- readonly `int $id`
- readonly `string $name`
- readonly `string $slug`
- readonly `int $taxonomyId`
- readonly `string $taxonomy`
- readonly `string $description`
- readonly `int $parentId`
- readonly `int $count`

### static `fromRow(array $row): self`

A record from a joined terms row; a missing column reads as empty.

- `@param array<string, mixed> $row a terms row joined with term_taxonomy, whatever columns it carries`

### static `fromRows(array $rows): array`

A record for every row, in order.

- `@param list<array<string, mixed>> $rows @return list<self>`

### `row(): array`

The stored row, for the writers and shapers that still spell columns.

### `hasParent(): bool`

Whether the term sits under another.

### `column(string $name): mixed`

One stored column by its database name, or null.

### `offsetExists(mixed $offset): bool`

The migration bridge: the record answers to its column names the way the row did.

### `offsetGet(mixed $offset): mixed`

The migration bridge: one column by its stored name, or null.

### `offsetSet(mixed $offset, mixed $value): never`

Records are read-only; writes go through the repository.

### `offsetUnset(mixed $offset): never`

Records are read-only; writes go through the repository.


## Terms

`final readonly class Minn\Content\Terms` · `public/minn/src/Minn/Content/Terms.php`

Used by: `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Menus`, `Minn\Front\ArchiveAddresses`, `Minn\Front\Permalinks`, `Minn\Front\Resolver`, `Minn\Rest\Services`, `Minn\Rest\TermsController`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermWriter`, `Minn\Theme\TemplateWriter`, `Minn\Theme\UserStyles`

```php
__construct(Minn\Db $db)
```


### `findBySlug(string $taxonomy, string $slug): ?Minn\Content\TermRecord`

A term joined with its taxonomy row: term_id, name, slug, term_taxonomy_id, parent, count.

### `find(string $taxonomy, int $termId): ?Minn\Content\TermRecord`

One term in a taxonomy, or null.

### `row(int $termId, string $taxonomy): ?Minn\Content\TermRecord`

The row shape the term controllers work with, including term_taxonomy_id.

### `idByName(string $name, string $taxonomy): ?int`

The id of the term with exactly this name in a taxonomy, or null.

### `uniqueSlug(string $base, string $taxonomy, int $skipId = 0): string`

A slug unique within the taxonomy: base, -2, -3 on collision.

### `create(string $name, string $slug, string $taxonomy, string $description, int $parent): int`

Inserts a term and its taxonomy row and returns the term id.

### `insertRow(array $data): int`

Inserts a terms row alone (name, slug, term_group), as the reference
writes it before the taxonomy row; returns the term id.

- `@param array{name: string, slug: string, term_group: int} $data`

### `addToTaxonomy(int $termId, string $taxonomy, string $description, int $parent): int`

Puts a term in a taxonomy; returns the term_taxonomy id.

### `updateRow(int $termId, array $data): void`

Rewrites a terms row (name, slug, term_group).

- `@param array{name: string, slug: string, term_group: int} $data`

### `dropRows(int $termId, int $ttId): void`

Drops a term's rows that a plugin's duplicate check said to give up.

### `rename(int $termId, string $name, string $slug): void`

Changes a term's name and slug.

### `describe(int $termId, string $taxonomy, string $description, int $parent): void`

Changes a term's description and parent within one taxonomy.

### `delete(Minn\Content\TermRecord $term, bool $hierarchical): void`

Reparents children to the grandparent, detaches relationships, drops the rows.

### `pathOf(Minn\Content\TermRecord $term): string`

"parent/child" for hierarchical taxonomies, the bare slug otherwise.

Internals: `record()` (private, line 17), `refreshHierarchy()` (private, line 194), `keepsHierarchy()` (private, line 210)


## TextFilters

`final class Minn\Content\TextFilters` · `public/minn/src/Minn/Content/TextFilters.php`

The small text filters the reference runs over content, titles and
comments, each from its observed rules (contracts/runtime.md "The content
filters", "The comment form"): smilies, the capital P, insecure home
addresses, the feed's embed clean-up, and a comment's links and spans.

- const `IMAGE` = `'/\\.(png|gif|jpe?g|svg|webp)$/i'`
- const `IGNORED` = `array (   0 => 'code',   1 => 'pre',   2 => 'script',   3 => 'style',   4 => 'textarea', )`

### static `smilies(string $text, array $table, Closure $image): string`

Smilies become their emoji, or an image for the few that have one: a
code only counts as a whole word between spaces, and nothing inside a
code, pre, script, style or textarea element, or inside a tag, changes.

- `@param array<string, string> $table code => emoji, or an image file name`
- `@param Closure(string $file, string $code): string $image the <img> for an image smiley`

### static `capitalP(string $text): string`

"Wordpress" spelled right after a space, a parenthesis, a tag, or an opening curly quote; elsewhere it stays.

### static `capitalPEverywhere(string $text): string`

"Wordpress" spelled right everywhere, as a title is.

### static `secureHome(string $content, string $insecure, string $secure): string`

The site's own http address made https, escaped forms included.

### static `feedEmbeds(string $content): string`

A feed carries an embedded post's iframe without the style that hides it until its script runs.

### static `relUgc(string $slashed, Closure $internal): string`

Every link in a comment marked as user-generated, on slashed text as
the comment filters carry it: rel gains "nofollow ugc" after whatever
it held ("ugc" alone for a link to the site's own host), moves to the
end of the tag, and each attribute is written double-quoted.

- `@param Closure(string $href): bool $internal whether an href points at the site itself`

### static `noteMentionClasses(string $slashed): string`

A span keeps no class in a comment (where a note's mention would be faked); slashed text in, slashed out.

Internals: `ignoring()` (private, line 44), `words()` (private, line 53), `attributes()` (private, line 134)


## Texturize

`final class Minn\Content\Texturize` · `public/minn/src/Minn/Content/Texturize.php`

The texturize subset the reference applies to rendered text: straight
quotes, apostrophes, ellipses, dashes, and primes become numeric
entities, outside the skip elements only. Coverage is pinned by the
texturize battery post; anything beyond it is a documented gap.

- const `SKIP` = `'pre|code|kbd|style|script|tt|textarea'`

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\Format`, `Minn\Admin\Notifications`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Renderer`, `Minn\Content\Blocks`, `Minn\Content\Excerpt`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaObject`, `Minn\Rest\PluginsController`, `Minn\Rest\RenderedFields`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`

### static `html(string $html): string`

Curly quotes, dashes, and ellipses in the text of HTML, leaving tags and pre, code, kbd, style, and script alone.

### static `text(string $text): string`

The same substitutions on a plain string with no tags.


## UserRecord

`final readonly class Minn\Content\UserRecord` · `public/minn/src/Minn/Content/UserRecord.php` · implements `ArrayAccess`

One row of the users table, read by name. Columns keep their WordPress
spelling in row(); here they are $user->login, ->email, ->displayName.
Array access is the migration bridge, read-only; new code reads the
properties.

Used by: `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticated`, `Minn\Auth\Authenticator`, `Minn\Auth\Cookie`, `Minn\Auth\PasswordReset`, `Minn\Auth\SignIn`, `Minn\Cli\UserCommand`, `Minn\Content\Users`, `Minn\Front\AdminBar`, `Minn\Front\ArchiveAddresses`, `Minn\Front\CommentPostController`, `Minn\Front\Permalinks`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`

- readonly `int $id`
- readonly `string $login`
- readonly `string $passwordHash`
- readonly `string $nicename`
- readonly `string $email`
- readonly `string $url`
- readonly `string $registered`
- readonly `string $activationKey`
- readonly `int $status`
- readonly `string $displayName`

### static `fromRow(array $row): self`

A record from a users row; a missing column reads as empty.

- `@param array<string, mixed> $row a users-table row, joined columns welcome`

### static `fromRows(array $rows): array`

A record for every row, in order.

- `@param list<array<string, mixed>> $rows @return list<self>`

### `row(): array`

The stored row, for the writers and shapers that still spell columns.

The row as the table holds it.

### `name(): string`

The name shown for this person: the display name, or the login when none was set.

### `column(string $name): mixed`

One stored column by its database name, or null.

### `offsetExists(mixed $offset): bool`

The migration bridge: the record answers to its column names the way the row did.

### `offsetGet(mixed $offset): mixed`

The migration bridge: one column by its stored name, or null.

### `offsetSet(mixed $offset, mixed $value): never`

Records are read-only; writes go through the repository.

### `offsetUnset(mixed $offset): never`

Records are read-only; writes go through the repository.


## Users

`final readonly class Minn\Content\Users` · `public/minn/src/Minn/Content/Users.php`

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\Appearance`, `Minn\Admin\Dashboard`, `Minn\Admin\HiddenIntegrations`, `Minn\Admin\LanguageController`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\SessionsController`, `Minn\Admin\Translations`, `Minn\Auth\ApplicationPasswords`, `Minn\Auth\Authenticator`, `Minn\Auth\Capabilities`, `Minn\Auth\Cookie`, `Minn\Auth\PasswordReset`, `Minn\Auth\Sessions`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\PostObject`, `Minn\Rest\Services`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db)
```


### `find(int $id): ?Minn\Content\UserRecord`

The user with this id, or null.

### `findByLogin(string $login): ?Minn\Content\UserRecord`

The user with this login, or null.

### `findByEmail(string $email): ?Minn\Content\UserRecord`

The user with this email, or null.

### `findBySlug(string $nicename): ?Minn\Content\UserRecord`

The user with this nicename, or null.

### `uniqueNicename(string $base, int $skipId = 0): string`

The unique-nicename rule: sanitized login, -2, -3 on collision.

### `createAccount(array $fields): int`

Inserts a user and the 14 default meta rows the reference writes.

login: string,
email: string,
password: string,
role: string,
display_name: string,
url?: string,
nicename?: string,
nickname?: string,
first_name?: string,
last_name?: string,
description?: string,
locale?: string,
} $fields

- `@param array{`

### static `defaultDisplayName(string $first, string $last, string $login): string`

The display name an account gets when none is given, as the reference
picks it: first and last name, whichever of the two there is, or the
login. The nickname plays no part.

### `insertAccount(array $fields): int`

A new account's row alone: the caller adds its meta and role, and keeps the count. @param array<string, mixed> $fields

- `@param array<string, mixed> $fields`

### static `profileMeta(array $fields): array`

The twelve profile meta rows the reference gives a new account, in its
order (the role's two follow).

- `@param array<string, mixed> $fields`
- `@return array<string, string>`

### `recount(): void`

Recounts the accounts into user_count, quietly.

### `deleteRow(int $id): void`

Removes the account's row alone: the caller removes its meta and keeps the count.

### `insert(array $columns): int`

Inserts a users row from column => value pairs and returns the new id.

- `@param array<string, mixed> $columns`

### `update(int $id, array $columns): void`

Sets the given columns on one user.

- `@param array<string, mixed> $columns`

### `delete(int $id): void`

Removes the user and their meta; their posts are reassigned or removed first by the caller.

### `ids(int $limit): array`

User ids in login order, optionally the first N. @return list<int>

- `@return list<int>`

### `deleteAllMeta(int $userId): void`

Removes every meta row of a user.

### `setPassword(int $userId, string $hash): void`

Stores a new password hash and clears any pending reset key.

### `count(): int`

How many users the site has.

### `registeredAfter(string $since, int $limit): array`

The newest users registered since a site-local time, up to the limit.

- `@return list<array> the newest registrations after a site-local timestamp`

### `deleteMeta(int $userId, string $key): void`

Removes one meta key from a user.

### `meta(int $userId, string $key): ?string`

One usermeta value, raw. Serialized blobs come back as stored.

### `setMeta(int $userId, string $key, string $value): void`

Replaces one usermeta row. The (user_id, meta_key) pair carries no
unique index on the stock schema, so this is a delete plus insert
rather than an upsert.

Internals: `record()` (private, line 19), `refreshCount()` (private, line 101), `writeAccount()` (private, line 112)

