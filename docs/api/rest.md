# `Minn\Rest`

the wp/v2 surface: shapes and controllers

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AdditionalFields`](#additionalfields) | final class | 46 | Which object type a wp/v2 route serves, so fields registered for that type can ride on the engine's own responses. |
| [`Api`](#api) | final readonly class | 164 | The REST API: wires the controllers for one request and dispatches a |
| [`ApplicationPasswordsController`](#applicationpasswordscontroller) | final readonly class | 171 | wp/v2/users/{id}/application-passwords: list, create, rename, delete, |
| [`BatchRequest`](#batchrequest) | final class | 32 | The requests a batch payload names, normalised into descriptors the |
| [`BlocksController`](#blockscontroller) | final readonly class | 69 | wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block |
| [`Caller`](#caller) | final class | 95 | Who is making this REST call. Resolved once from the cookie and nonce; |
| [`CommentObject`](#commentobject) | final readonly class | 75 | The wp/v2 comment object; edit context adds the moderation-desk fields. |
| [`CommentsController`](#commentscontroller) | final readonly class | 280 | wp/v2/comments: the status tabs with pagination headers, single, |
| [`Context`](#context) | enum | 18 | The view a REST caller asked for. View is the public shape, edit adds the |
| [`DeclaredPostsController`](#declaredpostscontroller) | final readonly class | 56 | wp/v2/{rest_base} for extra post types declared by an active extension. |
| [`Embed`](#embed) | final class | 180 | The _embed decoration and the embed context. Every embeddable link in an |
| [`EngineRoutes`](#engineroutes) | final class | 52 | The engine's own REST routes in the reference's regex form, for the |
| [`Fields`](#fields) | final readonly class | 92 | The _fields response filter. Dot paths descend ("title.rendered"); the |
| [`GlobalStylesController`](#globalstylescontroller) | final readonly class | 138 | wp/v2/global-styles: the site editor's saved styles (one post per |
| [`GlobalStylesObject`](#globalstylesobject) | final readonly class | 87 | The wp/v2/global-styles item, theme, and revision shapes. |
| [`IndexController`](#indexcontroller) | final readonly class | 53 | The API index at /wp-json/: the site facts monitors read (name, url, |
| [`Links`](#links) | final class | 35 | Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along. |
| [`ListQuery`](#listquery) | final readonly class | 170 | The collection parameters a wp/v2 list accepts, read once from the |
| [`MediaController`](#mediacontroller) | final readonly class | 199 | wp/v2/media: list, single, upload on both transports (multipart field |
| [`MediaObject`](#mediaobject) | final readonly class | 158 | The wp/v2 media object, view and edit context. |
| [`MenuItemObject`](#menuitemobject) | final readonly class | 74 | The wp/v2/menu-items resource. |
| [`MenuObject`](#menuobject) | final readonly class | 37 | The wp/v2/menus resource: a nav_menu term plus locations and auto_add. |
| [`MenusController`](#menuscontroller) | final readonly class | 282 | wp/v2/menus, menu-items, and menu-locations. Viewing needs edit_posts; |
| [`NavigationController`](#navigationcontroller) | final readonly class | 48 | wp/v2/navigation: the block theme's navigation menus, stored as |
| [`ParamCheck`](#paramcheck) | final class | 75 | The required / validate / sanitize pass over a request's declared arguments. |
| [`PluginsController`](#pluginscontroller) | final readonly class | 236 | wp/v2 plugins: what sits in wp-content/plugins, in the reference's |
| [`PolicyGate`](#policygate) | final readonly class | 58 | Judges a route's policy against the caller, with the reference's |
| [`PostObject`](#postobject) | final readonly class | 476 | Builds the wp/v2 post and page objects in the reference's shape: the |
| [`PostsController`](#postscontroller) | final readonly class | 174 | wp/v2 posts and pages, read side. |
| [`PostsWriteController`](#postswritecontroller) | final readonly class | 306 | wp/v2 posts and pages, write side: create, update, trash, and force |
| [`Reply`](#reply) | final class | 48 | JSON responses in the reference's shape: its header set, its json_encode |
| [`RestUrl`](#resturl) | final readonly class | 38 | REST URLs in the form the reference emits for the site's permalink mode: |
| [`RevisionsController`](#revisionscontroller) | final readonly class | 142 | wp/v2 revisions and autosaves under posts, pages, and blocks. |
| [`RouteArgs`](#routeargs) | final class | 36 | The argument groups a route registers with, filled the way register_rest_route fills them. |
| [`RouteIndex`](#routeindex) | final class | 61 | The description of one route the REST index publishes: namespace, methods, endpoints with their argument schemas, self link. |
| [`RouteMatch`](#routematch) | final class | 42 | Finds the registered handler for a method and path among the runtime's route table. |
| [`RouteTable`](#routetable) | final class | 38 | The registered endpoints in dispatch shape: one handler list per route, methods as a set, non-numeric keys lifted into the route's options. |
| [`RuntimeRoutes`](#runtimeroutes) | final class | 96 | Routes plugin code registered with register_rest_route(), answered |
| [`Schema`](#schema) | final readonly class | 468 | JSON-schema handling the way the REST API's argument validation does it: |
| [`SchemaValues`](#schemavalues) | final class | 201 | The value side of JSON Schema, as the reference applies it: what counts |
| [`SearchController`](#searchcontroller) | final readonly class | 136 | wp/v2 search over published content: id, title, url, type, and the |
| [`Services`](#services) | final class | 380 | The objects one REST request shares, each made once, on first use, from |
| [`Settings`](#settings) | final readonly class | 76 | The registered settings the Settings views read and write, mapped to |
| [`SettingsController`](#settingscontroller) | final readonly class | 24 | wp/v2/settings: read and write, both behind manage_options. |
| [`Taxonomies`](#taxonomies) | final class | 48 | The taxonomy registry the wp/v2 surface describes: the core set seeded |
| [`TaxonomiesController`](#taxonomiescontroller) | final readonly class | 47 | wp/v2 taxonomies: the registry, whole or per type, in view or edit context. |
| [`TemplateObject`](#templateobject) | final readonly class | 98 | The wp/v2/templates and wp/v2/template-parts resource. |
| [`TemplatesController`](#templatescontroller) | final readonly class | 205 | wp/v2/templates and wp/v2/template-parts: the block theme's templates as |
| [`TermObject`](#termobject) | final readonly class | 76 | The wp/v2 category and tag objects. |
| [`TermsController`](#termscontroller) | final readonly class | 198 | wp/v2 categories, tags, and pattern categories: list, single, and the create/update/delete the taxonomy admin drives. |
| [`Types`](#types) | final class | 87 | The engine's registry of built-in post types, seeded from the observed |
| [`TypesController`](#typescontroller) | final readonly class | 24 | wp/v2 types. |
| [`UserObject`](#userobject) | final readonly class | 97 | The wp/v2 user objects: the public view shape and the edit-context shape. |
| [`UsersController`](#userscontroller) | final readonly class | 301 | wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives. |

## AdditionalFields

`final class Minn\Rest\AdditionalFields` · `public/minn/src/Minn/Rest/AdditionalFields.php`

Which object type a wp/v2 route serves, so fields registered for that type can ride on the engine's own responses.

### static `typeForRoute(string $route, Minn\Runtime\Registry $registry): ?array`

The object type a wp/v2 route serves, or null.

- `@return array{0: string, 1: bool}|null the object type and whether the route is a single item`

### static `apply(array $fields, string $type, bool $single, mixed $data, callable $call): mixed`

The registered fields' values added to a response, item by item.

- `@param array<string, array<string, mixed>> $fields the type's registered fields`
- `@param callable(callable, array, string, string): mixed $call runs one get_callback`


## Api

`final readonly class Minn\Rest\Api` · `public/minn/src/Minn/Rest/Api.php`

The REST API: wires the controllers for one request and dispatches a
route. Errors thrown anywhere underneath become the reference's error
payload under the reference's headers.

Used by: `Minn\Engine`

```php
__construct(Minn\Http\Request $request, Minn\Http\Router $router, Minn\Rest\Services $services, Minn\Rest\Embed $embed)
```


### `routes(): array`

The engine's routes in the reference's index form.

- `@return array<string, list<string>> the engine's routes in the reference's form, route => methods`

### static `forRequest(Minn\Db $db, Minn\Http\Request $request): self`

The API for one request: the shared services and the route table.

### `caller(): Minn\Rest\Caller`

Who is making this request.

### `postObject(): Minn\Rest\PostObject`

The wp/v2 post shape.

### `termObject(): Minn\Rest\TermObject`

The wp/v2 term shape.

### `userObject(): Minn\Rest\UserObject`

The wp/v2 user shape.

### `types(): Minn\Rest\Types`

The post types the surface knows.

### `actingAs(int $userId, string $token): self`

Runs the request as a user already proven by the outer request, for
the in-process calls plugin code makes; a user who no longer exists
leaves the caller as the request itself resolves it.

### `handle(string $route): Minn\Http\Response`

Resolves a REST route (from the path or from ?rest_route=) to a response.

### `handleEngineOnly(string $route): ?Minn\Http\Response`

The engine's own answer to a route, or null when no engine route
takes it; the runtime's table is never consulted. This is what the
runtime's server calls for a core route, so a route the engine
declines cannot bounce between the two.

Internals: `controllers()` (private, line 71), `engineResponse()` (private, line 194)


## ApplicationPasswordsController

`final readonly class Minn\Rest\ApplicationPasswordsController` · `public/minn/src/Minn/Rest/ApplicationPasswordsController.php`

wp/v2/users/{id}/application-passwords: list, create, rename, delete,
and introspect the password that authenticated the call. Refusals and
shapes follow the reference: 501 when passwords are unavailable (no
HTTPS), 401 anonymous, 404 for an unknown user or password, arguments
checked before permissions.

- const `NAME_SCHEMA` = `array (   'type' => 'string',   'minLength' => 1,   'pattern' => '.*\\S.*', )`
- const `APP_ID_SCHEMA` = `array (   'type' => 'string',   'oneOf' =>    array (     0 =>      array (       'type' => 'string',       'format' => 'uuid',     ),     1 =>      array (       'type' => 'string',       'enum' =>        array (         0 => '',       ),     ),   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Users $users, Minn\Content\Site $site, Minn\Auth\ApplicationPasswords $passwords, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Rest\Schema $schema)
```


### `list(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/users/{id:\d+|me}/application-passwords`

The user's application passwords.

### `create(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/users/{id:\d+|me}/application-passwords`

Mints one; the plain password is in this answer only.

### `deleteAll(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/users/{id:\d+|me}/application-passwords`

Removes every one.

### `introspect(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/users/{id:\d+|me}/application-passwords/introspect`

The password the current Basic auth session used.

### `single(Minn\Http\Request $request, string $id, string $uuid): Minn\Http\Response`

Route: `GET /wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}`

One password by uuid.

### `update(Minn\Http\Request $request, string $id, string $uuid): Minn\Http\Response`

Route: `POST /wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}`

Route: `PUT /wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}`

Route: `PATCH /wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}`

Renames one.

### `delete(Minn\Http\Request $request, string $id, string $uuid): Minn\Http\Response`

Route: `DELETE /wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}`

Removes one.

Internals: `subject()` (private, line 129), `existing()` (private, line 154), `validate()` (private, line 163), `item()` (private, line 173), `when()` (private, line 187)


## BatchRequest

`final class Minn\Rest\BatchRequest` · `public/minn/src/Minn/Rest/BatchRequest.php`

The requests a batch payload names, normalised into descriptors the
caller turns into real request objects. A path's query string is split
off here so each sub-request carries its own query parameters, which is
what lets one round trip stand in for several.

### static `describe(mixed $requests): array`

The batch body's requests in the reference's normalised form.

- `@return list<array{method: string, path: string, query: array<string, mixed>, body: ?array, headers: ?array}>`


## BlocksController

`final readonly class Minn\Rest\BlocksController` · `public/minn/src/Minn/Rest/BlocksController.php`

wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block
posts. The reading and writing is the posts machinery with the post
type's own capabilities; what is this type's alone is the reading gate:
without edit_posts the list is empty and a single block is refused,
published or not.

- const `TYPE` = `'wp_block'`
- const `BASE` = `'blocks'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\PostsController $reads, Minn\Rest\PostsWriteController $writes, Minn\Content\Posts $posts, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/blocks`

The blocks the caller may edit; an empty list for anyone else.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/blocks/{id:\d+}`

One block. Editing context is the posts machinery's own gate
(rest_forbidden_context); a view is refused to anyone who cannot edit
posts, and a trashed or unknown block is not found rather than refused.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/blocks`

Creates a block.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/blocks/{id:\d+}`

Route: `PUT /wp/v2/blocks/{id:\d+}`

Route: `PATCH /wp/v2/blocks/{id:\d+}`

Updates a block.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/blocks/{id:\d+}`

Trashes or deletes a block.


## Caller

`final class Minn\Rest\Caller` · `public/minn/src/Minn/Rest/Caller.php`

Who is making this REST call. Resolved once from the cookie and nonce;
an anonymous or failed caller has id 0 and every capability check fails.

Used by: `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`

```php
__construct(Minn\Http\Request $request, Minn\Auth\Authenticator $authenticator, Minn\Auth\Capabilities $capabilities)
```


### `resolveAs(Minn\Auth\Authenticated $session): void`

Settles the caller as a session already proven elsewhere: an
in-process request a plugin makes through rest_do_request() carries
no nonce, so it runs as whoever the outer request resolved.

### `session(): ?Minn\Auth\Authenticated`

The session, or null for an anonymous or refused caller.

### `id(): int`

The caller's user id, 0 when anonymous.

### `can(string $capability, ?int $postId = NULL): bool`

Whether the caller holds a capability, on a post when given.

### `capabilities(): Minn\Auth\Capabilities`

The capability engine.

### `require(string $code = 'rest_not_logged_in', string $message = 'You are not currently logged in.', int $status = 401): Minn\Auth\Authenticated`

The session, or the reference's refusal: a bad nonce is always 403
rest_cookie_invalid_nonce; no identity is the caller-supplied error.

### `requireFloor(string ...$capabilities): int`

The floor every Minn Admin route shares: a signed-in caller who can
edit posts, plus any further capability named, all refused with the
same rest_forbidden the reference uses. Returns the caller's id.

### `requireCap(string ...$capabilities): void`

403 rest_forbidden unless the caller holds every capability named.

### `refuse(string $code, string $message): Minn\RestError`

401 for an anonymous caller, 403 for one who is signed in but refused.

Internals: `resolve()` (private, line 108)


## CommentObject

`final readonly class Minn\Rest\CommentObject` · `public/minn/src/Minn/Rest/CommentObject.php`

The wp/v2 comment object; edit context adds the moderation-desk fields.

Used by: `Minn\Rest\CommentsController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Comments $comments, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `url(): Minn\Rest\RestUrl`

The REST URL builder.

### `build(Minn\Content\CommentRecord $c, Minn\Rest\Context $context): array`

The wp/v2 comment shape, with the edit-context fields when asked.


## CommentsController

`final readonly class Minn\Rest\CommentsController` · `public/minn/src/Minn/Rest/CommentsController.php`

wp/v2/comments: the status tabs with pagination headers, single,
signed-in replies, moderation updates, trash, and force delete.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Comments $comments, Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Rest\CommentObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/comments`

The comments list with its status tabs and pagination headers.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/comments/{id:\d+}`

One comment, if the caller may read it.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/comments`

A signed-in reply; the author fields come from the user.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/comments/{id:\d+}`

Route: `PUT /wp/v2/comments/{id:\d+}`

Route: `PATCH /wp/v2/comments/{id:\d+}`

Status flips and content or author edits, for moderators.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/comments/{id:\d+}`

Trash remembers where the comment came from; force removes it outright.

Internals: `notifyModerator()` (private, line 144), `filter()` (private, line 212), `guarded()` (private, line 235), `date()` (private, line 266), `plainComment()` (private, line 281), `cleanComment()` (private, line 295)


## Context

`enum Minn\Rest\Context` · `public/minn/src/Minn/Rest/Context.php`

The view a REST caller asked for. View is the public shape, edit adds the
raw halves and the caller's own action links, embed is the reduced shape
a linked resource carries when it rides inside another response.

Cases: `View` = `'view'`, `Edit` = `'edit'`, `Embed` = `'embed'`

Used by: `Minn\Rest\BlocksController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenusController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\RevisionsController`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`

### static `of(Minn\Http\Request $request): self`

The context a request names, view when it names none or names one the route does not serve.

### `isEdit(): bool`

Whether this is the edit context.


## DeclaredPostsController

`final readonly class Minn\Rest\DeclaredPostsController` · `public/minn/src/Minn/Rest/DeclaredPostsController.php`

wp/v2/{rest_base} for extra post types declared by an active extension.
Registered last so core collections (users, comments, menus, ...) match
first; an unknown base is declined, so a plugin's own route under wp/v2
(answered by the runtime after the engine's routes) is not shadowed.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Types $types, Minn\Rest\PostsController $reads, Minn\Rest\PostsWriteController $writes)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:[a-z0-9_-]+}`

A declared type's list.

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:[a-z0-9_-]+}/{id:\d+}`

A declared type's single post.

### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:[a-z0-9_-]+}`

Creates a post of a declared type.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:[a-z0-9_-]+}/{id:\d+}`

Route: `PUT /wp/v2/{base:[a-z0-9_-]+}/{id:\d+}`

Route: `PATCH /wp/v2/{base:[a-z0-9_-]+}/{id:\d+}`

Updates a post of a declared type.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:[a-z0-9_-]+}/{id:\d+}`

Trashes or deletes a post of a declared type.

Internals: `slug()` (private, line 66)


## Embed

`final class Minn\Rest\Embed` · `public/minn/src/Minn/Rest/Embed.php`

The _embed decoration and the embed context. Every embeddable link in an
object's _links is answered in-process as the same caller with
context=embed and placed under _embedded[rel]; a rel whose every embed
came back empty or as an error is left out, while an empty list beside a
full one stays (wp:term keeps its empty tags list next to the categories).
Lists have _fields applied per item before the links are read, so a
_fields value without _links embeds nothing there; a single object embeds
first and is filtered afterwards. The embed context is the view object
cut to the reference's key set for that kind of item.

- const `KEYS` = `array (   'post' =>    array (     0 => 'id',     1 => 'date',     2 => 'slug',     3 => 'type',     4 => 'link',     5 => 'title',     6 => 'excerpt',     7 => 'author',     8 => 'featured_media',     9 => '_links',   ),   'media' =>    array (     0 => 'id',     1 => 'date',     2 => 'slug',     3 => 'type',     4 => 'link',     5 => 'title',     6 => 'author',     7 => 'featured_media',     8 => 'caption',     9 => 'alt_text',     10 => 'media_type',     11 => 'mime_type',     12 => 'media_details',     13 => 'source_url',     14 => '_links',   ),   'user' =>    array (     0 => 'id',     1 => 'name',     2 => 'url',     3 => 'description',     4 => 'link',     5 => 'slug',     6 => 'avatar_urls',     7 => '_links',   ),   'term' =>    array (     0 => 'id',     1 => 'link',     2 => 'name',     3 => 'slug',     4 => 'taxonomy',     5 => '_links',   ),   'comment' =>    array (     0 => 'id',     1 => 'parent',     2 => 'author',     3 => 'author_name',     4 => 'author_url',     5 => 'date',     6 => 'content',     7 => 'link',     8 => 'type',     9 => 'author_avatar_urls',     10 => '_links',   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Http\Router $router, Minn\Rest\Types $types, Minn\Rest\Taxonomies $taxonomies)
```


### static `requested(array $query): ?array`

Null when _embed is absent; [] for every rel; otherwise the rels asked for. @return ?list<string>

- `@return ?list<string>`

### `decorate(Minn\Http\Request $request, Minn\Http\Response $response): Minn\Http\Response`

Decorates a finished response: the embed context, then _embedded, then the deferred _fields.

Internals: `decorateItem()` (private, line 94), `answer()` (private, line 126), `target()` (private, line 155), `kind()` (private, line 173), `taxonomyBase()` (private, line 189), `context()` (private, line 199)


## EngineRoutes

`final class Minn\Rest\EngineRoutes` · `public/minn/src/Minn/Rest/EngineRoutes.php`

The engine's own REST routes in the reference's regex form, for the
index and for the runtime's server, whose route table plugin code reads
to learn what the site answers (a missing /wp/v2/comments there reads as
"comments are off").

Used by: `Minn\Rest\Api`, `Minn\Rest\IndexController`

### static `map(Minn\Http\Router $router): array`

The router's routes as route => methods, the way the index lists them.

- `@return array<string, list<string>> route => methods`

### static `forms(string $pattern): array`

A route attribute pattern as the reference writes routes: `{id:\d+}`
becomes `(?P<id>\d+)`, a bare capture matches one segment, a `{rest*}`
capture the remainder, and an alternation of literals expands to one
route per literal.

- `@return list<string>`


## Fields

`final readonly class Minn\Rest\Fields` · `public/minn/src/Minn/Rest/Fields.php`

The _fields response filter. Dot paths descend ("title.rendered"); the
object's own key order is kept. Applied per item on list responses and to
the whole payload otherwise, which is why _fields on the associative
types response strips every key and yields [] over HTTP, a reference
quirk the engine reproduces by construction.

Used by: `Minn\Admin\SessionsController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

- readonly `array $paths`
- readonly `bool $deferred`

### static `fromQuery(array $query): ?self`

The _fields selection a query carries, or null for everything.

- `@param array<string, mixed> $query`

### `withLinksForEmbedded(): self`

The same paths plus _links whenever _embedded is among them: the reference's single-object rule under _embed.

### `apply(array $object): array`

The object with only the selected fields.

### static `select(array $available, array $requested): array`

The fields a response keeps when a caller names some: known fields as
given, dotted paths whose root is known, and `id` whenever it exists.

- `@param list<string> $available`
- `@param list<string> $requested the parsed `_fields` list`
- `@return list<string>`

Internals: `filter()` (private, line 58)


## GlobalStylesController

`final readonly class Minn\Rest\GlobalStylesController` · `public/minn/src/Minn/Rest/GlobalStylesController.php`

wp/v2/global-styles: the site editor's saved styles (one post per
theme, read and written by id, with its revisions) and the active
theme's own styles and variations. There is no collection, no create
and no delete; the post is made on first use, as on the reference.

- const `CANNOT_EDIT` = `'Sorry, you are not allowed to edit this global style.'`
- const `CANNOT_READ_REVISIONS` = `'Sorry, you are not allowed to view revisions of this post.'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Theme\UserStyles $styles, Minn\Theme\ThemeStyles $theme, Minn\Rest\GlobalStylesObject $object, Minn\Content\Revisions $revisions, Minn\Rest\Caller $caller)
```


### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/{id:\d+}`

The saved styles by id: anyone who edits posts may read them, editing context needs the theme.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/global-styles/{id:\d+}`

Route: `PUT /wp/v2/global-styles/{id:\d+}`

Route: `PATCH /wp/v2/global-styles/{id:\d+}`

Replaces the title, settings, or styles the body names; what it leaves out stays.

### `theme(Minn\Http\Request $request, string $stylesheet): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/themes/{stylesheet:[^/]+} (cap edit_posts)`

The active theme's settings and styles, the engine's defaults underneath.

### `variations(Minn\Http\Request $request, string $stylesheet): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/themes/{stylesheet:[^/]+}/variations (cap edit_posts)`

The style variations the active theme ships.

### `revisions(Minn\Http\Request $request, string $parent): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/{parent:\d+}/revisions`

The revisions of the saved styles, newest first; all of them unless per_page pages them.

### `revision(Minn\Http\Request $request, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/{parent:\d+}/revisions/{id:\d+}`

One revision of the saved styles.

Internals: `post()` (private, line 112), `requireTheme()` (private, line 118), `requireParent()` (private, line 126), `title()` (private, line 139), `node()` (private, line 148)


## GlobalStylesObject

`final readonly class Minn\Rest\GlobalStylesObject` · `public/minn/src/Minn/Rest/GlobalStylesObject.php`

The wp/v2/global-styles item, theme, and revision shapes.

Used by: `Minn\Rest\Api`, `Minn\Rest\GlobalStylesController`

```php
__construct(Minn\Content\Revisions $revisions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `item(Minn\Content\PostRecord $post, Minn\Rest\Context $context): array`

The saved styles: the title both ways, settings by origin, styles
with their presets resolved, the links; in editing context the
app's lock fields too, which a global-styles post never carries.

### `theme(array $settings, array $styles, string $stylesheet): array`

The active theme's own styles as the themes/{stylesheet} route answers them.

### `revision(Minn\Content\PostRecord $revision): array`

One revision row: both nodes when the body holds anything at all,
neither when it is blank, then the row's own facts; no links.

Internals: `links()` (private, line 79), `node()` (private, line 97)


## IndexController

`final readonly class Minn\Rest\IndexController` · `public/minn/src/Minn/Rest/IndexController.php`

The API index at /wp-json/: the site facts monitors read (name, url,
home, namespaces) and the routes this engine serves, described from its
own router rather than the reference's full schema.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Http\Router $router)
```


### `index(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET / (public)`

The REST index: namespaces, routes, and the site's description.


## Links

`final class Minn\Rest\Links` · `public/minn/src/Minn/Rest/Links.php`

Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along.

### static `compact(array $links, array $curies): array`

Links with their curies applied, as the reference compacts them.

- `@param array<string, mixed> $links rel => items`
- `@param list<array{name: string, href: string, templated?: bool}> $curies`
- `@return array<string, mixed>`


## ListQuery

`final readonly class Minn\Rest\ListQuery` · `public/minn/src/Minn/Rest/ListQuery.php`

The collection parameters a wp/v2 list accepts, read once from the
request into typed fields: the page, the id lists, the slugs, the search
words, and the ordering. clauses() turns the narrowing ones into the SQL
fragments and parameters a controller appends to its own visibility
clause, so posts, pages, and media build their lists the same way.

- const `TERM_ARGS` = `array (   'categories' => 'category',   'tags' => 'post_tag',   'wp_pattern_category' => 'wp_pattern_category', )` — The list parameters that name terms, by the taxonomy they filter on.

Used by: `Minn\Rest\CommentsController`, `Minn\Rest\MediaController`, `Minn\Rest\PostsController`

```php
__construct(int $page = 1, int $perPage = 10, array $include = array ( ), array $exclude = array ( ), array $author = array ( ), array $authorExclude = array ( ), array $parent = array ( ), array $parentExclude = array ( ), array $slugs = array ( ), array $words = array ( ), ?int $menuOrder = NULL, string $orderBy = 'date', string $order = 'DESC', array $terms = array ( ), array $termsExclude = array ( ))
```
- `@param list<int> $include`
- `@param list<int> $exclude`
- `@param list<int> $author`
- `@param list<int> $authorExclude`
- `@param list<int> $parent`
- `@param list<int> $parentExclude`
- `@param list<string> $slugs`
- `@param list<string> $words`

- readonly `int $page`
- readonly `int $perPage`
- readonly `array $include`
- readonly `array $exclude`
- readonly `array $author`
- readonly `array $authorExclude`
- readonly `array $parent`
- readonly `array $parentExclude`
- readonly `array $slugs`
- readonly `array $words`
- readonly `?int $menuOrder`
- readonly `string $orderBy`
- readonly `string $order`
- readonly `array $terms`
- readonly `array $termsExclude`

### static `fromRequest(Minn\Http\Request $request): self`

The list parameters read from the request.

### `clauses(): array`

The narrowing clauses, each starting with " AND", and their parameters
in the same order. Every search word must appear in the title, the
excerpt, or the content.

- `@return array{string, list<mixed>}`

### `isSearch(): bool`

Whether a search narrows the list.

### `offset(): int`

The first row of the requested page.

### `totalPages(int $total): int`

How many pages a total makes at this page size.

### `isPastTheEnd(int $total): bool`

A page past the last one is a parameter error, except page one of nothing.

### static `ids(string $csv): array`

A comma-separated id list as distinct integers, zero dropped: author=0
means nothing.

- `@return list<int>`

### static `idsWithZero(string $csv): array`

The same list with zero kept: parent=0 means "top level", and a comment's
post=0 means "no post".

- `@return list<int>`

Internals: `termFilters()` (private, line 76), `list()` (private, line 175), `words()` (private, line 181)


## MediaController

`final readonly class Minn\Rest\MediaController` · `public/minn/src/Minn/Rest/MediaController.php`

wp/v2/media: list, single, upload on both transports (multipart field
"file", or a raw body with Content-Disposition), field edits, and force
delete with the files.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Media\Writer $library, Minn\Rest\MediaObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/media`

The media library list.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/media/{id:\d+}`

One attachment.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/media`

Uploads a file and creates its attachment.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/media/{id:\d+}`

Route: `PUT /wp/v2/media/{id:\d+}`

Route: `PATCH /wp/v2/media/{id:\d+}`

The editable fields the app uses.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/media/{id:\d+}`

Attachments cannot be trashed; force removes the row, its meta, and its files.

Internals: `libraryClauses()` (private, line 70), `restDate()` (private, line 102), `attachment()` (private, line 214)


## MediaObject

`final readonly class Minn\Rest\MediaObject` · `public/minn/src/Minn/Rest/MediaObject.php`

The wp/v2 media object, view and edit context.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Posts $posts, Minn\Media\Uploads $uploads, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `url(): Minn\Rest\RestUrl`

The REST URL builder.

### `build(Minn\Content\PostRecord $p, Minn\Rest\Context $context): array`

The wp/v2 media shape, with the edit-context fields when asked.

Internals: `details()` (private, line 118), `descriptionHtml()` (private, line 152)


## MenuItemObject

`final readonly class Minn\Rest\MenuItemObject` · `public/minn/src/Minn/Rest/MenuItemObject.php`

The wp/v2/menu-items resource.

- const `TYPE_LABEL` = `array (   'page' => 'Page',   'post' => 'Post',   'category' => 'Category',   'post_tag' => 'Tag',   'custom' => 'Custom Link', )`

Used by: `Minn\Rest\Api`, `Minn\Rest\MenusController`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `view(Minn\Content\MenuItem $item, Minn\Rest\Context $context): array`

The wp/v2 menu-item shape.


## MenuObject

`final readonly class Minn\Rest\MenuObject` · `public/minn/src/Minn/Rest/MenuObject.php`

The wp/v2/menus resource: a nav_menu term plus locations and auto_add.

Used by: `Minn\Rest\Api`, `Minn\Rest\MenusController`

```php
__construct(Minn\Content\Menus $menus, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `view(Minn\Content\TermRecord $term): array`

The wp/v2 menu shape.

- `@param array<string, mixed> $term`


## MenusController

`final readonly class Minn\Rest\MenusController` · `public/minn/src/Minn/Rest/MenusController.php`

wp/v2/menus, menu-items, and menu-locations. Viewing needs edit_posts;
writes need edit_theme_options. Anonymous callers get 401.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Menus $menus, Minn\Rest\MenuObject $menuObject, Minn\Rest\MenuItemObject $itemObject, Minn\Rest\Caller $caller, Minn\Rest\RestUrl $url)
```


### `listMenus(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/menus (cap edit_posts)`

Every classic menu.

### `singleMenu(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/menus/{id:\d+} (cap edit_posts)`

One menu.

### `createMenu(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/menus (cap edit_theme_options)`

Creates a menu.

### `updateMenu(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/menus/{id:\d+} (cap edit_theme_options)`

Route: `PUT /wp/v2/menus/{id:\d+} (cap edit_theme_options)`

Route: `PATCH /wp/v2/menus/{id:\d+} (cap edit_theme_options)`

Renames or re-describes a menu.

### `deleteMenu(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/menus/{id:\d+} (cap edit_theme_options)`

Deletes a menu and its items.

### `listItems(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/menu-items (cap edit_posts)`

The items of a menu.

### `singleItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/menu-items/{id:\d+} (cap edit_posts)`

One menu item.

### `createItem(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/menu-items (cap edit_theme_options)`

Creates a menu item.

### `updateItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/menu-items/{id:\d+} (cap edit_theme_options)`

Route: `PUT /wp/v2/menu-items/{id:\d+} (cap edit_theme_options)`

Route: `PATCH /wp/v2/menu-items/{id:\d+} (cap edit_theme_options)`

Updates a menu item.

### `deleteItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/menu-items/{id:\d+} (cap edit_theme_options)`

Deletes a menu item.

### `locations(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/menu-locations (cap edit_posts)`

The theme's menu locations.

Internals: `titleFrom()` (private, line 270), `urlFrom()` (private, line 280), `refuse()` (private, line 290), `plain()` (private, line 300)


## NavigationController

`final readonly class Minn\Rest\NavigationController` · `public/minn/src/Minn/Rest/NavigationController.php`

wp/v2/navigation: the block theme's navigation menus, stored as
wp_navigation posts. The reading and writing is the posts machinery with
this type's own capabilities (every one of them edit_theme_options), so
only the routes live here.

- const `TYPE` = `'wp_navigation'`
- const `BASE` = `'navigation'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\PostsController $reads, Minn\Rest\PostsWriteController $writes)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/navigation`

The navigation posts.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/navigation/{id:\d+}`

One navigation post.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/navigation`

Creates a navigation post.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/navigation/{id:\d+}`

Route: `PUT /wp/v2/navigation/{id:\d+}`

Route: `PATCH /wp/v2/navigation/{id:\d+}`

Updates a navigation post.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/navigation/{id:\d+}`

Trashes or deletes a navigation post.


## ParamCheck

`final class Minn\Rest\ParamCheck` · `public/minn/src/Minn/Rest/ParamCheck.php`

The required / validate / sanitize pass over a request's declared arguments.
The callbacks are plugin code and may answer with a WP_Error; the caller
hands them in already normalised to true, false, or [message, details].

### static `validate(array $args, callable $param, callable $validate): ?Minn\Runtime\Refusal`

The first refusal among the request's parameters, or null when all pass.

- `@param array<string, array<string, mixed>> $args`
- `@param callable(string): mixed $param the request's value for one argument`
- `@param callable(string, mixed): (bool|array{0: string, 1: mixed}) $validate true, false, or [message, details]`

### static `sanitize(array $params, array $args, callable $sanitize): array`

The parameters after sanitising, or the refusal listing every invalid one.

- `@param array<string, array<string, mixed>> $params the request's parameter groups, by source, in precedence order`
- `@param array<string, array<string, mixed>> $args`
- `@param callable(string, mixed): (array{value: mixed}|array{error: array{0: string, 1: mixed}}) $sanitize`
- `@return array{0: array<string, array<string, mixed>>, 1: Refusal|null} the parameters after sanitising, and the refusal if any failed`

Internals: `refusal()` (private, line 81)


## PluginsController

`final readonly class Minn\Rest\PluginsController` · `public/minn/src/Minn/Rest/PluginsController.php`

wp/v2 plugins: what sits in wp-content/plugins, in the reference's
shape. Minn extensions (a minn.json manifest) are listed alongside the
WordPress plugins the folder still holds; the engine runs the former
and only records the latter's stored state. Activation writes the same
options the reference and the extension loader read; files are never
installed or deleted from here.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Extension\Loader $extensions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Admin\Packages $packages, string $contentDir)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/plugins (cap activate_plugins)`

The plugins list, optionally by status.

### `install(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/plugins (cap activate_plugins)`

Installs a wordpress.org plugin by slug, optionally activating it; answers 201 with the item.

### `single(Minn\Http\Request $request, string $plugin): Minn\Http\Response`

Route: `GET /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

One plugin.

### `update(Minn\Http\Request $request, string $plugin): Minn\Http\Response`

Route: `PUT /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Route: `POST /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Route: `PATCH /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Activates or deactivates a plugin.

### `delete(Minn\Http\Request $request, string $plugin): Minn\Http\Response`

Route: `DELETE /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Deletes an inactive plugin.

Internals: `items()` (private, line 138), `find()` (private, line 153), `manifestFor()` (private, line 163), `extensionItem()` (private, line 173), `pluginItem()` (private, line 196), `text()` (private, line 228), `description()` (private, line 234), `uri()` (private, line 253), `links()` (private, line 258), `extensionKey()` (private, line 263)


## PolicyGate

`final readonly class Minn\Rest\PolicyGate` · `public/minn/src/Minn/Rest/PolicyGate.php`

Judges a route's policy against the caller, with the reference's
refusals: a caller who is not signed in gets the policy's sign-in code
at 401, a bad nonce is always 403 rest_cookie_invalid_nonce, and a
signed-in caller who lacks a capability gets the refusal code at 403.
The edit-context policy is judged as well when the request asks for it.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Caller $caller)
```


### `closure(): Closure`

The judge as the router takes it.

### `judge(Minn\Http\Policy $policy, Minn\Http\Request $request, array $captures): void`

Throws the refusal the policy names, or returns.

- `@param array<string, string> $captures`

Internals: `capabilities()` (private, line 50), `own()` (private, line 64)


## PostObject

`final readonly class Minn\Rest\PostObject` · `public/minn/src/Minn/Rest/PostObject.php`

Builds the wp/v2 post and page objects in the reference's shape: the
view context, the edit context (raw+rendered dual fields, editor-only
fields, and cap-gated wp:action-* links), and their _links blocks.

- const `NAVIGATION` = `'wp_navigation'` — The one post type whose REST shape is not post-shaped.
- const `BLOCK` = `'wp_block'` — The other: a pattern carries its content raw, its category, and its sync status.

Used by: `Minn\Rest\Api`, `Minn\Rest\CommentObject`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\Services`, `Minn\Rest\UserObject`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### static `restBase(string $type): string`

The rest_base of a type.

### `view(Minn\Content\PostRecord $p): array`

The view-context object: the shared fields, then the type's own, then class_list and _links.

### `links(Minn\Content\PostRecord $p): array`

The _links of a post in the view context.

### `edit(Minn\Content\PostRecord $p, int $userId): array`

The edit-context object for a given caller: raw+rendered dual fields,
password, permalink_template, generated_slug, block_version, Minn
Admin's registered list fields, and the cap-gated action links.

### `permalinkTemplate(Minn\Content\PostRecord $p): string`

The editor's sample permalink: the structure with the name token left
in place (pages: the parent path plus %pagename%), or the query form
when permalinks are plain.

### `modifiedUnsaved(Minn\Content\PostRecord $p, int $userId): bool`

Whether a live post carries an autosave newer than its saved revision.

### `lockHolder(int $id, int $userId): ?array`

The OTHER user holding a live _edit_lock (150 second window), or null.

### `editLinks(Minn\Content\PostRecord $p, int $userId): array`

The view links plus the caller's verbs and cap-gated wp:action-* entries.

### static `date(string $mysql): string`

A MySQL datetime in the reference's ISO form.

Internals: `navigationView()` (private, line 60), `blockView()` (private, line 90), `viewTerms()` (private, line 155), `typeFields()` (private, line 171), `classList()` (private, line 201), `format()` (private, line 223), `termLinks()` (private, line 267), `allow()` (private, line 467), `gmt()` (private, line 486)


## PostsController

`final readonly class Minn\Rest\PostsController` · `public/minn/src/Minn/Rest/PostsController.php`

wp/v2 posts and pages, read side.

- const `ORDER_BY` = `array (   'date' => 'post_date',   'modified' => 'post_modified',   'title' => 'post_title',   'slug' => 'post_name',   'id' => 'ID',   'author' => 'post_author',   'menu_order' => 'menu_order',   'include' => 'include', )`

Used by: `Minn\Rest\Api`, `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\NavigationController`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Rest\PostObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages}`

The posts or pages list.

### `serveList(Minn\Http\Request $request, string $type): Minn\Http\Response`

The list for any post type, with the reference's status and visibility rules.

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages}/{id:\d+}`

One post or page.

### `serveSingle(Minn\Http\Request $request, string $type, string $id): Minn\Http\Response`

One post of any type, with the reference's read rules.

Internals: `visibleStatuses()` (private, line 99), `visibility()` (private, line 129), `orderSql()` (private, line 143)


## PostsWriteController

`final readonly class Minn\Rest\PostsWriteController` · `public/minn/src/Minn/Rest/PostsWriteController.php`

wp/v2 posts and pages, write side: create, update, trash, and force
delete, each gated by the capability engine and answering with the
edit-context object the reference returns.

- const `LIVE` = `array (   0 => 'publish',   1 => 'future',   2 => 'private', )` — The statuses that make a post live: they need the publish capability and give the post its slug.

Used by: `Minn\Rest\Api`, `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\MediaController`, `Minn\Rest\NavigationController`, `Minn\Rest\RevisionsController`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\PostWriter $writer, Minn\Content\Site $site, Minn\Rest\PostObject $object, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts|pages}`

Creates a post or page.

### `serveCreate(Minn\Http\Request $request, string $type, string $base): Minn\Http\Response`

Creates a post of any type from the body.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts|pages}/{id:\d+}`

Route: `PUT /wp/v2/{base:posts|pages}/{id:\d+}`

Route: `PATCH /wp/v2/{base:posts|pages}/{id:\d+}`

Updates a post or page.

### `serveUpdate(Minn\Http\Request $request, string $type, string $id): Minn\Http\Response`

Updates a post of any type from the body.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:posts|pages}/{id:\d+}`

Trashes or deletes a post or page.

### `serveDelete(Minn\Http\Request $request, string $type, string $id): Minn\Http\Response`

Trashes a post of any type, or deletes it with force.

### static `field(mixed $value): string`

A field that may arrive as a scalar or as {raw: ...}.

Internals: `scheduledIfFuture()` (private, line 207), `fieldColumns()` (private, line 226), `statusColumns()` (private, line 271), `checkStickyPasswordConflict()` (private, line 296), `validStatus()` (private, line 308), `clean()` (private, line 317)


## Reply

`final class Minn\Rest\Reply` · `public/minn/src/Minn/Rest/Reply.php`

JSON responses in the reference's shape: its header set, its json_encode
flags (slashes escaped), the _fields filter, and the pagination headers
on lists.

- const `HEADERS` = `array (   'Content-Type' => 'application/json; charset=UTF-8',   'X-Content-Type-Options' => 'nosniff',   'Access-Control-Expose-Headers' => 'X-WP-Total, X-WP-TotalPages, Link',   'Access-Control-Allow-Headers' => 'Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type',   'Allow' => 'GET', )`

Used by: `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

### static `answer(Minn\Http\Request $request, mixed $data, int $status = 200): Minn\Http\Response`

One item, shaped by the request's own _fields: what nearly every handler ends with.

### static `item(mixed $data, ?Minn\Rest\Fields $fields, int $status = 200): Minn\Http\Response`

One object as a response, the selected fields applied.

### static `list(array $rows, int $total, int $totalPages, ?Minn\Rest\Fields $fields): Minn\Http\Response`

A list response with the total and page-count headers.

- `@param list<array> $rows`

### static `error(Minn\RestError $error): Minn\Http\Response`

A REST error as the reference's error body.


## RestUrl

`final readonly class Minn\Rest\RestUrl` · `public/minn/src/Minn/Rest/RestUrl.php`

REST URLs in the form the reference emits for the site's permalink mode:
{home}/wp-json/wp/v2/... when pretty, otherwise
{home}/index.php?rest_route=/wp/v2/... with the route value URL-encoded
when query args ride along.

Used by: `Minn\Engine`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\CommentObject`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\IndexController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostObject`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\Taxonomies`, `Minn\Rest\TemplateObject`, `Minn\Rest\TermObject`, `Minn\Rest\Types`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`

```php
__construct(Minn\Front\Permalinks $permalinks)
```


### `home(string $path = ''): string`

A URL under the site's home.

### `to(string $route, array $args = array ( )): string`

The URL of a REST route, pretty or plain as the site is set.

- `@param array<string, string|int> $args`

### static `curies(): array`

The curies entry every wp/v2 _links block ends with.


## RevisionsController

`final readonly class Minn\Rest\RevisionsController` · `public/minn/src/Minn/Rest/RevisionsController.php`

wp/v2 revisions and autosaves under posts, pages, and blocks.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Revisions $revisions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `revisions(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks}/{id:\d+}/revisions`

Real revisions, not autosaves.

### `revision(Minn\Http\Request $request, string $base, string $id, string $revisionId): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks}/{id:\d+}/revisions/{revisionId:\d+}`

One revision of a post, page, or block.

### `autosaves(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks}/{id:\d+}/autosaves`

The autosaves of a post.

### `createAutosave(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts|pages|blocks}/{id:\d+}/autosaves`

One autosave slot per author; the reply carries a preview link.

### `object(Minn\Content\PostRecord|array $r, Minn\Rest\Context $context = Minn\Rest\Context::View): array`

One revision row as wp/v2 serves it (autosaves and revisions alike).

Internals: `clean()` (private, line 84), `withPreviewLink()` (private, line 124), `meta()` (private, line 136), `requireParent()` (private, line 148)


## RouteArgs

`final class Minn\Rest\RouteArgs` · `public/minn/src/Minn/Rest/RouteArgs.php`

The argument groups a route registers with, filled the way register_rest_route fills them.

### static `normalise(array $args): array`

Route arguments with the shared args folded into each endpoint.

- `@param array<string, mixed> $args a single handler (with 'callback') or a list of handler groups, plus optional shared 'args'`
- `@return array{0: array<int|string, mixed>, 1: bool} the groups, and whether any lacks a permission_callback`


## RouteIndex

`final class Minn\Rest\RouteIndex` · `public/minn/src/Minn/Rest/RouteIndex.php`

The description of one route the REST index publishes: namespace, methods, endpoints with their argument schemas, self link.

- const `SCHEMA_KEYWORDS` = `array (   0 => 'default',   1 => 'enum',   2 => 'description',   3 => 'type',   4 => 'items',   5 => 'minimum',   6 => 'maximum',   7 => 'exclusiveMinimum',   8 => 'exclusiveMaximum',   9 => 'minLength',   10 => 'maxLength',   11 => 'pattern',   12 => 'format',   13 => 'properties',   14 => 'additionalProperties',   15 => 'oneOf',   16 => 'anyOf',   17 => 'minItems',   18 => 'maxItems',   19 => 'uniqueItems', )`

### static `describe(string $route, array $callbacks, array $options, string $context, callable $restUrl): ?array`

One route as the index describes it, or null when hidden.

- `@param list<array<string, mixed>> $callbacks the route's handler groups`
- `@param array<string, mixed> $options the route's non-numeric options (namespace, schema)`
- `@param callable(string): string $restUrl`
- `@return array<string, mixed>|null null when no endpoint shows in the index`

Internals: `argument()` (private, line 52)


## RouteMatch

`final class Minn\Rest\RouteMatch` · `public/minn/src/Minn/Rest/RouteMatch.php`

Finds the registered handler for a method and path among the runtime's route table.

### static `find(array $namespaces, callable $routesFor, string $method, string $path): Minn\Runtime\Refusal|array`

The handler for a method and path across the registered namespaces, or the refusal.

- `@param list<string> $namespaces the registered namespaces`
- `@param callable(string): array<string, list<array<string, mixed>>> $routesFor the (filtered) routes of one namespace, or all when given ''`
- `@return array{route: string, handler: array<string, mixed>, params: array<string, string>, defaults: array<string, mixed>}|Refusal`


## RouteTable

`final class Minn\Rest\RouteTable` · `public/minn/src/Minn/Rest/RouteTable.php`

The registered endpoints in dispatch shape: one handler list per route, methods as a set, non-numeric keys lifted into the route's options.

- const `HANDLER_DEFAULTS` = `array (   'methods' =>    array (   ),   'accept_json' => false,   'accept_raw' => false,   'show_in_index' => true,   'args' =>    array (   ), )`

### static `normalise(array $endpoints): array`

Registered endpoints as a route table with their options.

- `@param array<string, mixed> $endpoints route => a handler or a list of handlers plus options`
- `@return array{0: array<string, list<array<string, mixed>>>, 1: array<string, array<string, mixed>>} the routes, and the options found per route`


## RuntimeRoutes

`final class Minn\Rest\RuntimeRoutes` · `public/minn/src/Minn/Rest/RuntimeRoutes.php`

Routes plugin code registered with register_rest_route(), answered
through the runtime's server after the engine's own routes have had
their turn; the runtime's say before the engine answers at all (an
authentication refusal, a pre-dispatch answer, a removed endpoint);
and the runtime's namespaces folded into the index.

Used by: `Minn\Rest\Api`

### static `gate(Minn\Http\Request $request): ?Minn\Http\Response`

What plugin code decides before any route runs, engine routes
included, in the reference's order: rest_authentication_errors may
refuse the request, rest_pre_dispatch may answer it outright, and a
route a rest_endpoints filter removed is no route at all. Null lets
the engine's router proceed.

### static `dispatch(Minn\Http\Request $request): ?Minn\Http\Response`

Null when the runtime has no route for the request either.

### static `mergeIndex(Minn\Http\Response $response): Minn\Http\Response`

The engine's index plus the namespaces and routes the runtime holds.

Internals: `wpRequest()` (private, line 80), `ensure()` (private, line 96), `toResponse()` (private, line 103)


## Schema

`final readonly class Minn\Rest\Schema` · `public/minn/src/Minn/Rest/Schema.php`

JSON-schema handling the way the REST API's argument validation does it:
validate a value against a schema (a Refusal names what failed), sanitise
it into the schema's type, filter a response by context, and the type
tests the schema vocabulary needs. Behaviour pinned by contracts/fixtures/api/rest.json.

- const `TYPES` = `array (   0 => 'array',   1 => 'object',   2 => 'string',   3 => 'number',   4 => 'integer',   5 => 'boolean',   6 => 'null', )`
- const `ENDPOINT_KEYWORDS` = `array (   0 => 'type',   1 => 'format',   2 => 'enum',   3 => 'items',   4 => 'properties',   5 => 'additionalProperties',   6 => 'patternProperties',   7 => 'minProperties',   8 => 'maxProperties',   9 => 'minimum',   10 => 'maximum',   11 => 'exclusiveMinimum',   12 => 'exclusiveMaximum',   13 => 'multipleOf',   14 => 'minLength',   15 => 'maxLength',   16 => 'pattern',   17 => 'minItems',   18 => 'maxItems',   19 => 'uniqueItems',   20 => 'anyOf',   21 => 'oneOf', )` — An object schema that names its properties forbids the others unless it says otherwise, all the way down.

Used by: `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\SchemaValues`, `Minn\Rest\Services`

```php
__construct(Closure $email, Closure $number, Closure $format)
```
- `@param Closure(string): bool $email the email test, so the reference's is_email filter applies`
- `@param Closure(int|float): string $number the localised number formatter for messages`
- `@param Closure(string, mixed): mixed $format the sanitiser for a string format (hex-color, email, uri, ...)`


### `validate(mixed $value, array $args, string $param = ''): Minn\Runtime\Refusal|true`

Whether a value satisfies a schema, or the reference's refusal.

### `sanitize(mixed $value, array $args, string $param = ''): mixed`

A value coerced to its schema's types.

### `filterByContext(mixed $data, array $schema, string $context): mixed`

Drops the properties whose schema does not list the context; an array whose items do not is emptied.

### `anyMatching(mixed $value, array $args, string $param): ?array`

The first anyOf branch the value validates against.

### static `endpointArgs(array $schema, bool $creatable): array`

The argument map a route derives from an item schema: every writable
property with the default validators, its keywords, defaults and
required flags on the create route only, and any arg_options overrides.

### static `closeObjects(array $schema): array`

The schema with additionalProperties closed on every object.

Internals: `validateComposite()` (private, line 102), `validateString()` (private, line 128), `validateNumber()` (private, line 146), `validateArray()` (private, line 158), `validateObject()` (private, line 184), `validateFormat()` (private, line 221), `validateBounds()` (private, line 233), `enum()` (private, line 270), `wrongType()` (private, line 285)


## SchemaValues

`final class Minn\Rest\SchemaValues` · `public/minn/src/Minn/Rest/SchemaValues.php`

The value side of JSON Schema, as the reference applies it: what counts
as a boolean, integer, array, or object, the coercions to each, the
comparisons and date, colour, and uuid parsers the formats use, and the
combining walk anyOf and oneOf share. Schema holds the rules; this holds
what they are applied to.

Used by: `Minn\Rest\Schema`

### static `isBoolean(mixed $value): bool`

Whether a value reads as a boolean, the strings included.

### static `toBoolean(mixed $value): mixed`

The boolean a value reads as.

### static `isInteger(mixed $value): bool`

Whether a value is a whole number.

### static `isArray(mixed $value): bool`

Whether a value reads as a list, a comma-separated string included.

### static `toArray(mixed $value): array`

The list a value reads as.

### static `isObject(mixed $value): bool`

Whether a value reads as an object.

### static `toObject(mixed $value): array`

The object a value reads as, as an array.

### static `bestType(mixed $value, array|string $types): string`

The one type among the candidates the value reads as, in the reference's order of preference; '' when none.

### static `valuesEqual(mixed $a, mixed $b): bool`

Whether two values are equal by the reference's loose rules.

### static `patternProperty(string $property, array $args): ?array`

The schema of the patternProperties entry a property name matches, or null.

### static `stabilize(mixed $value): mixed`

Keys sorted at every level, so two equal structures serialise the same.

### static `parseDate(mixed $date, bool $forceUtc = false): int|false`

A timestamp for an RFC3339-shaped date, false otherwise; $forceUtc reads the offset as Z.

### static `parseHexColor(mixed $color): mixed`

A hex colour as given, or false.

### static `isUuid(mixed $uuid): bool`

Whether a value is a version-4 uuid.

### static `list(mixed $value): array`

A comma- or space-separated string as a list.

- `@return list<string>`

### static `combining(mixed $value, array $args, bool $stopAfterFirst, Closure $validate): array`

Which of a combining schema's branches a value matches: the branch on
a single match (or the first, for anyOf or when asked to stop), else
every match with its index, else the errors the branches raised.

- `@param Closure(mixed, array): (true|Refusal) $validate a branch validator`
- `@return array{schema?: array, matches?: array<int, array>, errors?: list<array{error: Refusal, schema: array, index: int}>}`


## SearchController

`final readonly class Minn\Rest\SearchController` · `public/minn/src/Minn/Rest/SearchController.php`

wp/v2 search over published content: id, title, url, type, and the
post type as subtype. Ordered the way the reference orders a search:
a title holding every term first, then a title holding any term, then
an excerpt match, then a content match, newest first within a rank.

- const `MAX_PER_PAGE` = `100`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Rest\Types $types, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/search`

Search across post types with the reference's relevance order.

Internals: `item()` (private, line 72), `subtypes()` (private, line 95), `rankExpression()` (private, line 110), `terms()` (private, line 121), `escapeLike()` (private, line 138), `intParam()` (private, line 143)


## Services

`final class Minn\Rest\Services` · `public/minn/src/Minn/Rest/Services.php`

The objects one REST request shares, each made once, on first use, from
the database door and the request. Every getter is typed and names its
dependencies in plain constructor calls: there is no autowiring, and
get() knows only the names listed here, so a wrong one fails at the
first call rather than deep in a handler.

- const `NAMED` = `array (   'Minn\\Content\\Users' => 'users',   'Minn\\Content\\Posts' => 'posts',   'Minn\\Content\\Terms' => 'terms',   'Minn\\Content\\Comments' => 'comments',   'Minn\\Content\\Site' => 'site',   'Minn\\Content\\PostWriter' => 'writer',   'Minn\\Front\\Permalinks' => 'permalinks',   'Minn\\Rest\\RestUrl' => 'url',   'Minn\\Auth\\Capabilities' => 'capabilities',   'Minn\\Rest\\Caller' => 'caller',   'Minn\\Extension\\Loader' => 'loader',   'Minn\\Rest\\Types' => 'types',   'Minn\\Rest\\Taxonomies' => 'taxonomies',   'Minn\\Media\\Uploads' => 'uploads',   'Minn\\Content\\Inventory' => 'inventory',   'Minn\\Admin\\Packages' => 'packages',   'Minn\\Admin\\App' => 'app',   'Minn\\Admin\\Logs' => 'logs',   'Minn\\Admin\\Updates' => 'updates',   'Minn\\Content\\Menus' => 'menus',   'Minn\\Content\\Revisions' => 'revisions',   'Minn\\Auth\\Sessions' => 'sessions',   'Minn\\Auth\\ApplicationPasswords' => 'applicationPasswords',   'Minn\\Admin\\Translations' => 'translations',   'Minn\\Admin\\Appearance' => 'appearance',   'Minn\\Admin\\HiddenIntegrations' => 'hiddenIntegrations',   'Minn\\Admin\\ActivityFeed' => 'activityFeed',   'Minn\\Admin\\Dashboard' => 'dashboard',   'Minn\\Admin\\Notifications' => 'notifications',   'Minn\\Admin\\Diagnostics' => 'diagnostics',   'Minn\\Media\\Writer' => 'mediaWriter',   'Minn\\Rest\\Schema' => 'schema',   'Minn\\Rest\\PostObject' => 'postObject',   'Minn\\Rest\\TermObject' => 'termObject',   'Minn\\Rest\\UserObject' => 'userObject',   'Minn\\Rest\\MediaObject' => 'mediaObject',   'Minn\\Rest\\CommentObject' => 'commentObject',   'Minn\\Theme\\UserStyles' => 'userStyles',   'Minn\\Theme\\ThemeStyles' => 'themeStyles', )`

Used by: `Minn\Rest\Api`


### static `forRequest(Minn\Db $db, Minn\Http\Request $request): self`

The services for one request, none made yet.

### `get(string $class): object`

The service registered under a class name. Only the names in NAMED
answer; anything else is a programming error and says so at once.

### `db(): Minn\Db`

The database door.

### `request(): Minn\Http\Request`

The request being answered.

### `root(): string`

The site root, no trailing slash.

### `contentDir(): string`

wp-content under the site root.

### `users(): Minn\Content\Users`

The users repository.

### `posts(): Minn\Content\Posts`

The posts repository.

### `terms(): Minn\Content\Terms`

The terms repository.

### `comments(): Minn\Content\Comments`

The comments repository.

### `site(): Minn\Content\Site`

The site's options.

### `writer(): Minn\Content\PostWriter`

The post writer.

### `revisions(): Minn\Content\Revisions`

Revisions and autosaves.

### `userStyles(): Minn\Theme\UserStyles`

The site editor's saved global styles.

### `themeStyles(): Minn\Theme\ThemeStyles`

The active theme's global styles and variations.

### `menus(): Minn\Content\Menus`

Classic menus and their items.

### `inventory(): Minn\Content\Inventory`

What wp-content holds: plugins, themes, drop-ins.

### `permalinks(): Minn\Front\Permalinks`

Link building from the permalink structure.

### `url(): Minn\Rest\RestUrl`

REST URL building.

### `capabilities(): Minn\Auth\Capabilities`

The capability engine.

### `caller(): Minn\Rest\Caller`

Who is making this call.

### `sessions(): Minn\Auth\Sessions`

The session store.

### `applicationPasswords(): Minn\Auth\ApplicationPasswords`

Application passwords for Basic auth.

### `loader(): Minn\Extension\Loader`

The extension loader.

### `types(): Minn\Rest\Types`

The post types the REST surface knows.

### `taxonomies(): Minn\Rest\Taxonomies`

The taxonomies the REST surface knows.

### `uploads(): Minn\Media\Uploads`

The uploads directory.

### `mediaWriter(): Minn\Media\Writer`

Stores an upload as an attachment.

### `schema(): Minn\Rest\Schema`

The JSON Schema validator, with the reference's filters passed in.

### `app(): Minn\Admin\App`

The Minn Admin bundle on disk.

### `packages(): Minn\Admin\Packages`

Installing and removing themes and extensions.

### `logs(): Minn\Admin\Logs`

The debug log reader.

### `updates(): Minn\Admin\Updates`

Update checks and offers.

### `translations(): Minn\Admin\Translations`

Locales and the app's catalogs.

### `appearance(): Minn\Admin\Appearance`

The app's per-user appearance.

### `hiddenIntegrations(): Minn\Admin\HiddenIntegrations`

The app's per-user hidden views.

### `activityFeed(): Minn\Admin\ActivityFeed`

What happened lately.

### `dashboard(): Minn\Admin\Dashboard`

The overview payload.

### `notifications(): Minn\Admin\Notifications`

The bell feed.

### `diagnostics(): Minn\Admin\Diagnostics`

The System view's payload.

### `templates(): ?Minn\Theme\TemplateIndex`

The active block theme's templates; null under a classic theme, where the routes answer as the reference does when it never registered them.

### `templateWriter(): ?Minn\Theme\TemplateWriter`

Writes block templates; null under a classic theme.

### `postObject(): Minn\Rest\PostObject`

The wp/v2 post shape.

### `termObject(): Minn\Rest\TermObject`

The wp/v2 term shape.

### `userObject(): Minn\Rest\UserObject`

The wp/v2 user shape.

### `mediaObject(): Minn\Rest\MediaObject`

The wp/v2 media shape.

### `commentObject(): Minn\Rest\CommentObject`

The wp/v2 comment shape.

Internals: `share()` (private, line 431)


## Settings

`final readonly class Minn\Rest\Settings` · `public/minn/src/Minn/Rest/Settings.php`

The registered settings the Settings views read and write, mapped to
the options WordPress stores. Registration order is the payload order.

- const `REGISTRY` = `array (   'blog_public' =>    array (     0 => 'blog_public',     1 => 'int',   ),   'minn_admin_maintenance' =>    array (     0 => 'minn_admin_maintenance',     1 => 'bool',   ),   'users_can_register' =>    array (     0 => 'users_can_register',     1 => 'int',   ),   'default_role' =>    array (     0 => 'default_role',     1 => 'string',   ),   'comment_moderation' =>    array (     0 => 'comment_moderation',     1 => 'int',   ),   'comment_registration' =>    array (     0 => 'comment_registration',     1 => 'int',   ),   'show_avatars' =>    array (     0 => 'show_avatars',     1 => 'int',   ),   'title' =>    array (     0 => 'blogname',     1 => 'string',   ),   'description' =>    array (     0 => 'blogdescription',     1 => 'string',   ),   'url' =>    array (     0 => 'siteurl',     1 => 'string',   ),   'email' =>    array (     0 => 'admin_email',     1 => 'string',   ),   'timezone' =>    array (     0 => 'timezone_string',     1 => 'string',   ),   'date_format' =>    array (     0 => 'date_format',     1 => 'string',   ),   'time_format' =>    array (     0 => 'time_format',     1 => 'string',   ),   'start_of_week' =>    array (     0 => 'start_of_week',     1 => 'int',   ),   'language' =>    array (     0 => 'WPLANG',     1 => 'language',   ),   'use_smilies' =>    array (     0 => 'use_smilies',     1 => 'bool',   ),   'default_category' =>    array (     0 => 'default_category',     1 => 'int',   ),   'default_post_format' =>    array (     0 => 'default_post_format',     1 => 'string',   ),   'posts_per_page' =>    array (     0 => 'posts_per_page',     1 => 'int',   ),   'show_on_front' =>    array (     0 => 'show_on_front',     1 => 'string',   ),   'page_on_front' =>    array (     0 => 'page_on_front',     1 => 'int',   ),   'page_for_posts' =>    array (     0 => 'page_for_posts',     1 => 'int',   ),   'default_ping_status' =>    array (     0 => 'default_ping_status',     1 => 'string',   ),   'default_comment_status' =>    array (     0 => 'default_comment_status',     1 => 'string',   ),   'site_logo' =>    array (     0 => 'site_logo',     1 => 'int_or_null',   ),   'site_icon' =>    array (     0 => 'site_icon',     1 => 'int',   ), )` — setting key => [option name, type]

Used by: `Minn\Rest\Api`, `Minn\Rest\SettingsController`

```php
__construct(Minn\Content\Site $site)
```


### `payload(): array`

Every registered setting with its current value.

### `store(array $body): void`

Writes the registered keys in a body; unregistered keys are ignored.


## SettingsController

`final readonly class Minn\Rest\SettingsController` · `public/minn/src/Minn/Rest/SettingsController.php`

wp/v2/settings: read and write, both behind manage_options.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Settings $settings, Minn\Rest\Caller $caller)
```


### `settings(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/settings (signed in)`

Route: `POST /wp/v2/settings (signed in)`

Route: `PUT /wp/v2/settings (signed in)`

Route: `PATCH /wp/v2/settings (signed in)`

The site settings: read, or write from the body.


## Taxonomies

`final class Minn\Rest\Taxonomies` · `public/minn/src/Minn/Rest/Taxonomies.php`

The taxonomy registry the wp/v2 surface describes: the core set seeded
from the reference (data/taxonomies.json) with the reference's own
labels, capabilities, and visibility flags.

Used by: `Minn\Admin\StructureController`, `Minn\Rest\Embed`, `Minn\Rest\Services`, `Minn\Rest\TaxonomiesController`

```php
__construct(Minn\Rest\RestUrl $url)
```


### `all(): array`

Every taxonomy the surface knows, by slug.

- `@return array<string, array> keyed by taxonomy slug`

### `find(string $slug): ?array`

One taxonomy by slug, or null.

### `forType(string $type): array`

The taxonomies attached to a post type, in registry order. @return array<string, array>

- `@return array<string, array>`

### static `view(array $taxonomy): array`

The reference's view-context projection: the keys an anonymous reader sees.


## TaxonomiesController

`final readonly class Minn\Rest\TaxonomiesController` · `public/minn/src/Minn/Rest/TaxonomiesController.php`

wp/v2 taxonomies: the registry, whole or per type, in view or edit context.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Taxonomies $taxonomies, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/taxonomies`

A whole-payload reply like types: _fields filters the map, not its members.

### `single(Minn\Http\Request $request, string $slug): Minn\Http\Response`

Route: `GET /wp/v2/taxonomies/{slug:[\w-]+}`

One taxonomy.

Internals: `context()` (private, line 52)


## TemplateObject

`final readonly class Minn\Rest\TemplateObject` · `public/minn/src/Minn/Rest/TemplateObject.php`

The wp/v2/templates and wp/v2/template-parts resource.

Used by: `Minn\Rest\Api`, `Minn\Rest\TemplatesController`

```php
__construct(Minn\Theme\TemplateIndex $index, Minn\Content\Posts $posts, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `base(string $type): string`

The rest_base of a template type.

### `view(Minn\Theme\TemplateRecord $record, Minn\Rest\Context $context): array`

The wp/v2 template shape.

Internals: `links()` (private, line 84)


## TemplatesController

`final readonly class Minn\Rest\TemplatesController` · `public/minn/src/Minn/Rest/TemplatesController.php`

wp/v2/templates and wp/v2/template-parts: the block theme's templates as
one list, whether they come from the theme's files, from a row the site
saved over one, or from a plugin. Reading needs only edit_posts (the
reference lets an editor or author see the layout); changing anything
needs edit_theme_options.

- const `READ_CAP` = `'edit_posts'`
- const `WRITE_CAP` = `'edit_theme_options'`
- const `REFUSAL` = `'Sorry, you are not allowed to access the templates on this site.'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Theme\TemplateIndex $index, Minn\Theme\TemplateWriter $writer, Minn\Rest\TemplateObject $object, Minn\Rest\Caller $caller)
```


### `templates(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/templates (cap edit_posts)`

The templates list.

### `parts(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/template-parts (cap edit_posts)`

The template parts list.

### `template(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/templates/{id*} (cap edit_posts)`

One template.

### `part(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/template-parts/{id*} (cap edit_posts)`

One template part.

### `saveTemplate(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/templates/{id*} (cap edit_posts + edit_theme_options)`

Route: `PUT /wp/v2/templates/{id*} (cap edit_posts + edit_theme_options)`

Route: `PATCH /wp/v2/templates/{id*} (cap edit_posts + edit_theme_options)`

Saves a template.

### `savePart(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/template-parts/{id*} (cap edit_posts + edit_theme_options)`

Route: `PUT /wp/v2/template-parts/{id*} (cap edit_posts + edit_theme_options)`

Route: `PATCH /wp/v2/template-parts/{id*} (cap edit_posts + edit_theme_options)`

Saves a template part.

### `deleteTemplate(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/templates/{id*} (cap edit_posts + edit_theme_options)`

Deletes a customised template.

### `deletePart(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/template-parts/{id*} (cap edit_posts + edit_theme_options)`

Deletes a customised template part.

Internals: `listing()` (private, line 99), `single()` (private, line 117), `save()` (private, line 126), `delete()` (private, line 153), `trashed()` (private, line 170), `record()` (private, line 192), `readable()` (private, line 202), `requireWrite()` (private, line 213), `text()` (private, line 222)


## TermObject

`final readonly class Minn\Rest\TermObject` · `public/minn/src/Minn/Rest/TermObject.php`

The wp/v2 category and tag objects.

- const `TAXONOMIES` = `array (   'categories' =>    array (     'taxonomy' => 'category',     'has_parent' => true,     'post_arg' => 'categories',     'post_base' => 'posts',   ),   'tags' =>    array (     'taxonomy' => 'post_tag',     'has_parent' => false,     'post_arg' => 'tags',     'post_base' => 'posts',   ),   'wp_pattern_category' =>    array (     'taxonomy' => 'wp_pattern_category',     'has_parent' => false,     'post_arg' => 'wp_pattern_category',     'post_base' => 'blocks',   ), )`

Used by: `Minn\Rest\Api`, `Minn\Rest\Services`, `Minn\Rest\TermsController`

```php
__construct(Minn\Db $db, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `url(): Minn\Rest\RestUrl`

The REST URL builder.

### static `config(string $restBase): array`

The taxonomy behind a rest_base.

- `@return array{taxonomy: string, has_parent: bool, post_arg: string, post_base: string}`

### `view(Minn\Content\TermRecord $term, string $restBase): array`

The wp/v2 term shape.

Internals: `allowedVerbs()` (private, line 78)


## TermsController

`final readonly class Minn\Rest\TermsController` · `public/minn/src/Minn/Rest/TermsController.php`

wp/v2 categories, tags, and pattern categories: list, single, and the create/update/delete the taxonomy admin drives.

- const `ORDER_BY` = `array (   'name' => 't.name',   'count' => 'tt.count',   'id' => 't.term_id',   'slug' => 't.slug', )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Terms $terms, Minn\Content\Site $site, Minn\Rest\TermObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:categories|tags|wp_pattern_category}`

The categories or tags list.

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:categories|tags|wp_pattern_category}/{id:\d+}`

One category or tag.

### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:categories|tags|wp_pattern_category}`

Tags and pattern categories are open to edit_posts holders; categories need manage_categories.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:categories|tags|wp_pattern_category}/{id:\d+}`

Route: `PUT /wp/v2/{base:categories|tags|wp_pattern_category}/{id:\d+}`

Route: `PATCH /wp/v2/{base:categories|tags|wp_pattern_category}/{id:\d+}`

Updates a category or tag.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:categories|tags|wp_pattern_category}/{id:\d+}`

The default category is capability-denied before the force check.


## Types

`final class Minn\Rest\Types` · `public/minn/src/Minn/Rest/Types.php`

The engine's registry of built-in post types, seeded from the observed
contract (src/data/types.json) with _links attached at runtime.

Used by: `Minn\Admin\AdminTypes`, `Minn\Admin\StructureController`, `Minn\Engine`, `Minn\Rest\Api`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\TypesController`

```php
__construct(Minn\Rest\RestUrl $url, array $declared = array ( ))
```
- `@param array<string, array<string, mixed>> $declared extra types from active extensions`


### `all(): array`

Every post type the surface knows, by slug.

- `@return array<string, array> keyed by type slug`

### `find(string $slug): ?array`

One post type by slug, or null.

### `slugForRestBase(string $base): ?string`

The type behind a rest_base, or null.

### `isDeclared(string $slug): bool`

Whether an extension declared this type.

### `restBase(string $slug): string`

The rest_base of a type slug.


## TypesController

`final readonly class Minn\Rest\TypesController` · `public/minn/src/Minn/Rest/TypesController.php`

wp/v2 types.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Types $types)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/types (public)`

Deliberately a whole-payload reply: _fields strips every type key, yielding [].

### `single(Minn\Http\Request $request, string $slug): Minn\Http\Response`

Route: `GET /wp/v2/types/{slug:[\w-]+} (public)`

One post type.


## UserObject

`final readonly class Minn\Rest\UserObject` · `public/minn/src/Minn/Rest/UserObject.php`

The wp/v2 user objects: the public view shape and the edit-context shape.

Used by: `Minn\Blocks\Dynamic\LatestComments`, `Minn\Rest\Api`, `Minn\Rest\CommentObject`, `Minn\Rest\Services`, `Minn\Rest\UsersController`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### static `avatarUrls(string $email): array`

Gravatar URLs in the sizes the reference emits (sha256 of the email).

### `view(Minn\Content\UserRecord $u): array`

The wp/v2 user shape in the view context.

### `edit(Minn\Content\UserRecord $u): array`

The private fields a caller who can edit the user sees. The
capabilities map is the union of role primitives plus each role name
as a pseudo-capability, exactly as the reference emits.


## UsersController

`final readonly class Minn\Rest\UsersController` · `public/minn/src/Minn/Rest/UsersController.php`

wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives.

- const `ORDER_BY` = `array (   'id' => 'u.ID',   'name' => 'u.display_name',   'registered_date' => 'u.user_registered',   'slug' => 'u.user_nicename',   'email' => 'u.user_email', )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Rest\UserObject $object, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Auth\Roles $roles)
```


### `me(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/users/me`

The signed-in user.

### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/users`

View context lists published authors; edit context lists everyone.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/users/{id:\d+}`

One user.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/users`

Engine-created users carry real scheme hashes and the full default meta set.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/users/{id:\d+}`

Route: `PUT /wp/v2/users/{id:\d+}`

Route: `PATCH /wp/v2/users/{id:\d+}`

Updates a user.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/users/{id:\d+}`

reassign is REQUIRED (checked before the user lookup), and so is force.

Internals: `welcome()` (private, line 141), `hasPublishedContent()` (private, line 154), `validRole()` (private, line 163), `validEmail()` (private, line 171)

