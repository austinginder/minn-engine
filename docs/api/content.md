# `Minn\Content`

the repositories and records: posts, users, terms, comments, and the render pipeline

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Autop`](#autop) | final class | 34 | Classic-content paragraphing: blank lines become paragraphs, single |
| [`Blocks`](#blocks) | final class | 40 | The content pipeline's front door: block markup goes through the block |
| [`CommentClasses`](#commentclasses) | final class | 26 | The class tokens a rendered comment carries: its type, its author, odd/even and thread alternation, depth, then the caller's extras. |
| [`CommentFilter`](#commentfilter) | final readonly class | 46 | What a comment listing is narrowed to. Every field is optional; the id |
| [`CommentModeration`](#commentmoderation) | final readonly class | 43 | Whether a comment may be stored and in what state: the duplicate and |
| [`CommentRecord`](#commentrecord) | final readonly class | 114 | One row of the comments table, read by name: $comment->author, ->content, |
| [`Comments`](#comments) | final readonly class | 255 | Reads and writes over the comments table. |
| [`ContentScan`](#contentscan) | final class | 196 | What a site's stored content asks of the engine: shortcodes, block |
| [`Excerpt`](#excerpt) | final class | 101 | The reference's generated excerpt, as captured from probe posts: |
| [`Inventory`](#inventory) | final readonly class | 237 | Plugins, themes, must-use plugins, and drop-ins as they sit on disk. |
| [`MenuItem`](#menuitem) | final readonly class | 22 | One classic nav_menu_item, fields resolved from the post, its |
| [`Menus`](#menus) | final readonly class | 485 | Classic nav_menu terms and nav_menu_item posts. The front uses these |
| [`MoreTag`](#moretag) | final class | 20 | The `<!--more-->` marker that splits a post into the part a listing shows |
| [`Page`](#page) | final readonly class | 49 | One page of a listing: the rows on it and how many rows the whole |
| [`PasswordGate`](#passwordgate) | final class | 34 | A password-protected post on the front end: its body is the password |
| [`PluginState`](#pluginstate) | final readonly class | 86 | Switching plugins on and off, the way the reference records it: a |
| [`PostClasses`](#postclasses) | final class | 55 | The class list a post carries on its article element, in the reference's |
| [`PostFilter`](#postfilter) | final readonly class | 55 | What a listing is narrowed to. Every field is optional and the object is |
| [`PostRecord`](#postrecord) | final readonly class | 156 | One row of the posts table, read by name. The columns keep their |
| [`PostStatus`](#poststatus) | enum | 42 | The statuses a post row can hold; the value is the column's own spelling. |
| [`PostWriter`](#postwriter) | final readonly class | 315 | Every write to the posts table and its satellites: rows, meta, term |
| [`Posts`](#posts) | final readonly class | 401 | Reads over the posts table. A single post comes back as a PostRecord and |
| [`Reader`](#reader) | final class | 54 | Who is reading this request: their user id, whether they may read |
| [`Revisions`](#revisions) | final readonly class | 86 | Revision rows: the plain snapshots and the per-author autosave slots. |
| [`Site`](#site) | final readonly class | 54 | Site-wide options and the site's clock. |
| [`SiteIcon`](#siteicon) | final readonly class | 24 | The site icon: the attachment the site_icon option names, as the file |
| [`Slug`](#slug) | final class | 56 |  |
| [`TermLinks`](#termlinks) | final class | 39 | A post's terms rendered as links, in the two shapes the reference |
| [`TermRecord`](#termrecord) | final readonly class | 88 | One term with its taxonomy row, read by name: $term->name, ->slug, |
| [`Terms`](#terms) | final readonly class | 137 |  |
| [`Texturize`](#texturize) | final class | 51 | The texturize subset the reference applies to rendered text: straight |
| [`UserRecord`](#userrecord) | final readonly class | 96 | One row of the users table, read by name. Columns keep their WordPress |
| [`Users`](#users) | final readonly class | 202 |  |

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

Used by: `Minn\Admin\RenderController`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\FeedController`, `Minn\Front\Feeds`, `Minn\Front\Renderer`, `Minn\Rest\CommentObject`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\RevisionsController`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`


### static `render(string $raw): string`

Post content as HTML: blocks through the renderer, classic content through the paragraph rules.

### static `renderer(): Minn\Blocks\Renderer`

The shared block renderer, built once from the shared database door.

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


## CommentFilter

`final readonly class Minn\Content\CommentFilter` · `public/minn/src/Minn/Content/CommentFilter.php`

What a comment listing is narrowed to. Every field is optional; the id
lists keep zero, because post=0 means "comments without a post". Dates
are site-local "Y-m-d H:i:s", after and before both exclusive. With publicPostsOnly the
comments of unpublished or protected posts are left out.

Used by: `Minn\Content\Comments`, `Minn\Rest\CommentsController`

```php
__construct(array $post = array ( ), array $include = array ( ), array $exclude = array ( ), array $parent = array ( ), array $parentExclude = array ( ), array $author = array ( ), array $authorExclude = array ( ), string $authorEmail = '', string $type = 'comment', string $search = '', string $after = '', string $before = '', bool $publicPostsOnly = false)
```
- `@param list<int> $post`
- `@param list<int> $include`
- `@param list<int> $exclude`
- `@param list<int> $parent`
- `@param list<int> $parentExclude`
- `@param list<int> $author`
- `@param list<int> $authorExclude`

- readonly `array $post`
- readonly `array $include`
- readonly `array $exclude`
- readonly `array $parent`
- readonly `array $parentExclude`
- readonly `array $author`
- readonly `array $authorExclude`
- readonly `string $authorEmail`
- readonly `string $type`
- readonly `string $search`
- readonly `string $after`
- readonly `string $before`
- readonly `bool $publicPostsOnly`

### `onPublicPosts(): self`

The same filter kept to comments on published, unprotected posts: what an anonymous reader may see.

### static `all(): self`

No narrowing at all.

### `isPlainType(): bool`

The plain kind: an empty type or "comment".


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

Used by: `Minn\Admin\Notifications`, `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Content\Comments`, `Minn\Front\CommentList`, `Minn\Front\Feeds`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`

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

Used by: `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Content\CommentModeration`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Front\Feeds`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\Services`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db)
```


### `find(int $id): ?Minn\Content\CommentRecord`

The comment with this id, or null.

### `meta(int $id, string $key): ?string`

One meta value of a comment, or null when it has none.

### `addMeta(int $id, string $key, string $value): void`

Adds a meta row; a second row with the same key is allowed, as the reference allows it.

### `page(array $approvedTokens, int $page, int $perPage, ?Minn\Content\CommentFilter $filter = NULL): array`

One page of comments carrying the given approval tokens, newest first,
narrowed by the filter.

- `@param list<string> $approvedTokens`
- `@return array{comments: list<CommentRecord>, total: int}`

### `duplicate(int $postId, string $author, string $email, string $content, int $userId): bool`

The same words on the same post from the same person, in any status but trash or spam.

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

Internals: `idFilter()` (private, line 95)


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

Used by: `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\Feeds`, `Minn\Front\Renderer`, `Minn\Rest\PostObject`

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

Used by: `Minn\Admin\InstalledSoftware`, `Minn\Admin\Packages`, `Minn\Admin\Updates`, `Minn\Cli\AssetUpdate`, `Minn\Cli\MinnCommand`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Content\PluginState`, `Minn\Engine`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

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

Internals: `regularPlugins()` (private, line 145), `mainPluginFile()` (private, line 220), `item()` (private, line 240)


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

Used by: `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\Services`

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
authorId: int
} $fields

- `@param array{`

### `updateItem(int $id, array $fields): void`

Applies the given fields to one item, stored the way the reference stores them.

- `@param array<string, mixed> $fields`

### `deleteItem(int $id): void`

Hard-deletes one item.

Internals: `hydrate()` (private, line 185), `meta()` (private, line 242), `menuIdOf()` (private, line 255), `classList()` (private, line 267), `xfnList()` (private, line 277), `writeMeta()` (private, line 474), `writer()` (private, line 488), `site()` (private, line 496)


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

Used by: `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\Feeds`, `Minn\Front\Renderer`, `Minn\Theme\ClassicContent`

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

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Posts`, `Minn\Front\FeedController`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Theme\MainQueryBridge`

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

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Excerpt`, `Minn\Content\Page`, `Minn\Content\PasswordGate`, `Minn\Content\PostStatus`, `Minn\Content\Posts`, `Minn\Engine`, `Minn\Extension\SeamRunner`, `Minn\Front\Canonical`, `Minn\Front\CommentPostController`, `Minn\Front\Feeds`, `Minn\Front\Permalinks`, `Minn\Front\Renderer`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Front\Sitemaps`, `Minn\Media\Writer`, `Minn\Rest\CommentsController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Runtime\CommentCloser`, `Minn\Theme\ClassicContent`, `Minn\Theme\HeadLinks`

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


## PostStatus

`enum Minn\Content\PostStatus` · `public/minn/src/Minn/Content/PostStatus.php`

The statuses a post row can hold; the value is the column's own spelling.

Cases: `Publish` = `'publish'`, `Draft` = `'draft'`, `Pending` = `'pending'`, `Private` = `'private'`, `Future` = `'future'`, `Trash` = `'trash'`, `Inherit` = `'inherit'`, `AutoDraft` = `'auto-draft'`

Used by: `Minn\Content\PostRecord`, `Minn\Content\Reader`, `Minn\Rest\PostObject`, `Minn\Rest\PostsWriteController`

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

Used by: `Minn\Admin\EditorController`, `Minn\Cli\MinnCommand`, `Minn\Content\Menus`, `Minn\Content\Revisions`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Media\Writer`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Services`, `Minn\Runtime\PostInsert`, `Minn\Runtime\TermWriter`, `Minn\Theme\TemplateWriter`

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

### `deleteMeta(int $id, string $key): void`

Removes every meta row with this key from a post.

### `uniqueSlug(string $desired, int $excludeId): string`

A slug unique within the posts table: base, -2, -3 on collision.

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

The side effects shared by create and update: sticky, format, featured media, footnotes.

### `applyTerms(int $id, array $body): void`

Assigns categories and tags from a write body, replacing existing links.

### `maybeSaveRevision(int $id, int $userId): void`

Snapshots the post's NEW state as a revision exactly when the
reference would: compared against the latest revision, identical
content-bearing fields add nothing, and the first update always
snapshots.

### `reparentChildren(int $id, int $parent, array $types): void`

Moves a deleted post's children of the given types to another parent, the way the reference keeps pages and attachments attached. @param list<string> $types

- `@param list<string> $types`

### `reassignAuthor(int $from, int $to): void`

Moves every post of one author to another.

### `destroy(int $id): void`

Hard-deletes a post with its revisions and its meta.

Internals: `saveSticky()` (private, line 184)


## Posts

`final readonly class Minn\Content\Posts` · `public/minn/src/Minn/Content/Posts.php`

Reads over the posts table. A single post comes back as a PostRecord and
a listing as a Page of them; rendering and escaping happen elsewhere.

Used by: `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Renderer`, `Minn\Cli\MinnCommand`, `Minn\Content\Menus`, `Minn\Content\PostWriter`, `Minn\Content\SiteIcon`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\Feeds`, `Minn\Front\Permalinks`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Media\Writer`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\Services`, `Minn\Rest\TemplateObject`, `Minn\Runtime\PostQuery`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`

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

### `pathOf(Minn\Content\PostRecord $page): string`

The slash-joined ancestry of a page: "sample-page/docs".

### `guess(string $prefix): ?Minn\Content\PostRecord`

The closest published post or page whose name starts with the given
text: pages first, then posts, newest first within each. This is the
order the reference follows when it guesses a destination for a
missing URL.

### `published(string $type = 'post', int $page = 1, int $perPage = 10): Minn\Content\Page`

The published posts of one type, newest first: the everyday listing.

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

The latest plain (non-autosave) revision id, or 0.

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

### `newestAutosave(int $postId, int $userId): ?Minn\Content\PostRecord`

The newest autosave of a post by one author, or null.

### `blocks(string $status): array`

Reusable blocks (wp_block rows) in one status, newest first, capped at 100.

### `firstCategorySlug(int $postId): ?string`

The slug of the post's first category, or null.

Internals: `record()` (private, line 21), `byName()` (private, line 49), `byPath()` (private, line 96), `scope()` (private, line 202), `like()` (private, line 228), `neighbour()` (private, line 303), `latest()` (private, line 367)


## Reader

`final class Minn\Content\Reader` · `public/minn/src/Minn/Content/Reader.php`

Who is reading this request: their user id, whether they may read
private content, whether they may edit a given post, and the
post-password cookie they carry. Set once per request by the engine
and consulted by the resolver, the queries, and the renderers.

Used by: `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Content\PasswordGate`, `Minn\Content\Posts`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\CommentPostController`, `Minn\Front\Resolver`, `Minn\Runtime\PostQuery`, `Minn\Runtime\Runtime`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

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

### static `set(self $reader): void`

Makes this reader the current one for the request.

### static `current(): self`

The request's reader, anonymous until one is set.

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

Used by: `Minn\Rest\RevisionsController`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\PostWriter $writer, Minn\Content\Site $site)
```


### `revisionsOf(int $parentId): array`

The revisions, or the autosaves, of a post, newest first, as rows.

- `@return list<array> newest first`

### `autosavesOf(int $parentId): array`

The autosaves of a post, newest first, as rows.

### `saveAutosave(int $parentId, int $userId, string $title, string $content, string $excerpt): int`

One autosave slot per author: updated in place when it exists.

Internals: `named()` (private, line 35)


## Site

`final readonly class Minn\Content\Site` · `public/minn/src/Minn/Content/Site.php`

Site-wide options and the site's clock.

Used by: `Minn\Admin\ActivityChart`, `Minn\Admin\App`, `Minn\Admin\BootPayload`, `Minn\Admin\CoreStatus`, `Minn\Admin\Dashboard`, `Minn\Admin\Diagnostics`, `Minn\Admin\InstalledSoftware`, `Minn\Admin\LanguageController`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\Packages`, `Minn\Admin\PackagesController`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\ThemesController`, `Minn\Admin\Translations`, `Minn\Admin\Updates`, `Minn\Blocks\Dynamic\Dates`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Cli\MinnCommand`, `Minn\Cli\Runtime`, `Minn\Content\Inventory`, `Minn\Content\Menus`, `Minn\Content\PluginState`, `Minn\Content\PostWriter`, `Minn\Content\Revisions`, `Minn\Content\SiteIcon`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Extension\Loader`, `Minn\Extension\Seams`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\Feeds`, `Minn\Front\ProbeController`, `Minn\Front\Sitemaps`, `Minn\Login\LoginController`, `Minn\Mail\MailSettings`, `Minn\Mail\Mailer`, `Minn\Media\Images`, `Minn\Media\Uploads`, `Minn\Media\Writer`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\CommentsController`, `Minn\Rest\IndexController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Services`, `Minn\Rest\Settings`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Runtime\Plugins`, `Minn\Runtime\Recovery`, `Minn\Runtime\Runtime`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\ClassicTheme`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplateWriter`, `Minn\Theme\Theme`

```php
__construct(Minn\Db $db)
```


### `option(string $name): ?string`

One option's raw value, or null when it is unset.

### `setOption(string $name, string $value): void`

Writes an option, inserting it as autoloaded when it does not exist.

### `deleteOption(string $name): void`

Removes an option row.

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

Used by: `Minn\Admin\BootPayload`, `Minn\Engine`, `Minn\Front\ProbeController`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks)
```


### `file(): ?string`

The icon's path under uploads, or null when the site has none.

### `url(): string`

The icon's URL, empty when the site has none.


## Slug

`final class Minn\Content\Slug` · `public/minn/src/Minn/Content/Slug.php`

- const `SAVE_DASHES` = `array (   0 => '%c2%a0',   1 => '%e2%80%93',   2 => '%e2%80%94',   3 => '&nbsp;',   4 => '&#160;',   5 => '&ndash;',   6 => '&#8211;',   7 => '&mdash;',   8 => '&#8212;',   9 => '/',   10 => '×',   11 => '%c3%97', )`
- const `SAVE_DROPPED` = `array (   0 => '%c2%ad',   1 => '%c2%a1',   2 => '%c2%bf',   3 => '%c2%ab',   4 => '%c2%bb',   5 => '%e2%80%b9',   6 => '%e2%80%ba',   7 => '%e2%80%98',   8 => '%e2%80%99',   9 => '%e2%80%9c',   10 => '%e2%80%9d',   11 => '%e2%80%9a',   12 => '%e2%80%9b',   13 => '%e2%80%9e',   14 => '%e2%80%9f',   15 => '%e2%80%a2',   16 => '%c2%a9',   17 => '%c2%ae',   18 => '%c2%b0',   19 => '%e2%80%a6',   20 => '%e2%84%a2',   21 => '%c2%b4',   22 => '%cb%8a',   23 => '%cc%81',   24 => '%cd%81',   25 => '%cc%80',   26 => '%cc%84',   27 => '%cc%8c',   28 => '%e2%82%ac',   29 => '%c2%a3',   30 => '%e2%80%80',   31 => '%e2%80%81',   32 => '%e2%80%82',   33 => '%e2%80%83',   34 => '%e2%80%84',   35 => '%e2%80%85',   36 => '%e2%80%86',   37 => '%e2%80%87',   38 => '%e2%80%88',   39 => '%e2%80%89',   40 => '%e2%80%8a',   41 => '%e2%80%8b',   42 => '%e2%80%8c',   43 => '%e2%80%8d',   44 => '%e2%80%8e',   45 => '%e2%80%8f',   46 => '%e2%80%aa',   47 => '%e2%80%ab',   48 => '%e2%80%ac',   49 => '%e2%80%ad',   50 => '%e2%80%ae',   51 => '%e2%80%af',   52 => '%e2%81%9f',   53 => '%e3%80%80',   54 => '%ef%bb%bf', )`

Used by: `Minn\Content\PostWriter`, `Minn\Content\Terms`, `Minn\Content\Users`, `Minn\Media\Writer`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Runtime\PostInsert`

### static `sanitize(string $text): string`

Lower-case, hyphen-separated, ASCII only: the shape post_name takes.

### static `truncate(string $slug, int $length): string`

A slug cut to a byte length without splitting a multibyte character or
leaving a dash hanging off the end.

### static `dashes(string $title, bool $forSave, Closure $utf8Encode): string`

The reference's dash form of a title: tags and marks stripped, spaces to dashes, and the save-time rules when saving.


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

Used by: `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Menus`, `Minn\Content\Terms`, `Minn\Front\Permalinks`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Front\Sitemaps`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Runtime\TermWriter`

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

Used by: `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\Menus`, `Minn\Front\Permalinks`, `Minn\Front\Resolver`, `Minn\Rest\Services`, `Minn\Rest\TermsController`, `Minn\Runtime\TermWriter`, `Minn\Theme\TemplateWriter`

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

### `rename(int $termId, string $name, string $slug): void`

Changes a term's name and slug.

### `describe(int $termId, string $taxonomy, string $description, int $parent): void`

Changes a term's description and parent within one taxonomy.

### `delete(Minn\Content\TermRecord $term, bool $hierarchical): void`

Reparents children to the grandparent, detaches relationships, drops the rows.

### `pathOf(Minn\Content\TermRecord $term): string`

"parent/child" for hierarchical taxonomies, the bare slug otherwise.

Internals: `record()` (private, line 16)


## Texturize

`final class Minn\Content\Texturize` · `public/minn/src/Minn/Content/Texturize.php`

The texturize subset the reference applies to rendered text: straight
quotes, apostrophes, ellipses, dashes, and primes become numeric
entities, outside the skip elements only. Coverage is pinned by the
texturize battery post; anything beyond it is a documented gap.

- const `SKIP` = `'pre|code|kbd|style|script|tt|textarea'`

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\Format`, `Minn\Admin\Notifications`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Renderer`, `Minn\Content\Blocks`, `Minn\Content\Excerpt`, `Minn\Front\Feeds`, `Minn\Rest\MediaObject`, `Minn\Rest\PluginsController`, `Minn\Rest\PostObject`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`

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

Used by: `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticated`, `Minn\Auth\Authenticator`, `Minn\Auth\Cookie`, `Minn\Auth\PasswordReset`, `Minn\Auth\SignIn`, `Minn\Cli\UserCommand`, `Minn\Content\Users`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Front\Permalinks`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Front\Sitemaps`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`

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

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\Appearance`, `Minn\Admin\Dashboard`, `Minn\Admin\HiddenIntegrations`, `Minn\Admin\LanguageController`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\SessionsController`, `Minn\Admin\Translations`, `Minn\Auth\ApplicationPasswords`, `Minn\Auth\Authenticator`, `Minn\Auth\Capabilities`, `Minn\Auth\Cookie`, `Minn\Auth\PasswordReset`, `Minn\Auth\Sessions`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Front\Feeds`, `Minn\Login\LoginController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\PostObject`, `Minn\Rest\Services`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Theme\PageRenderer`

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

Internals: `record()` (private, line 19)

