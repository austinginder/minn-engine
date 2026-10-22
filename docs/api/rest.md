# `Minn\Rest`

the wp/v2 surface: shapes and controllers

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Api`](#api) | final readonly class | 468 | The REST API: wires the controllers for one request and dispatches a |
| [`ApplicationPasswordsController`](#applicationpasswordscontroller) | final readonly class | 177 | wp/v2/users/{id}/application-passwords: list, create, rename, delete, |
| [`ArgCheck`](#argcheck) | final readonly class | 142 | Judges a route's declared arguments against the request before the |
| [`BatchController`](#batchcontroller) | final readonly class | 164 | batch/v1 as the reference answers it (probe rest-batch): up to 25 |
| [`BatchRequest`](#batchrequest) | final class | 32 | The requests a batch payload names, normalised into descriptors the |
| [`BlockDirectoryController`](#blockdirectorycontroller) | final readonly class | 72 | wp/v2/block-directory/search (Austin, 2026-10-21, decision 1B): the |
| [`BlockEditorController`](#blockeditorcontroller) | final readonly class | 29 | Two of wp-block-editor/v1's routes, for someone who may edit theme |
| [`BlockRendererController`](#blockrenderercontroller) | final readonly class | 93 | wp/v2/block-renderer as the reference answers it (probe |
| [`BlockTypesController`](#blocktypescontroller) | final readonly class | 133 | wp/v2/block-types as the reference answers it (probe rest-block-types): |
| [`BlocksController`](#blockscontroller) | final readonly class | 59 | wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block |
| [`Caller`](#caller) | final class | 149 | Who is making this REST call. Resolved once from the cookie and nonce; |
| [`Catalogue`](#catalogue) | final class | 70 | The route table read from the classes alone: every #[Route] under |
| [`ClientSchema`](#clientschema) | final class | 81 | A JSON schema trimmed for a client (probe json-schema): only the keywords |
| [`CommentListArgs`](#commentlistargs) | final class | 34 | The WP_Comment_Query arguments a comment list request makes, as the |
| [`CommentObject`](#commentobject) | final readonly class | 87 | The wp/v2 comment object; edit context adds the moderation-desk fields. |
| [`CommentsController`](#commentscontroller) | final readonly class | 311 | wp/v2/comments: the status tabs with pagination headers, single, |
| [`Context`](#context) | enum | 18 | The view a REST caller asked for. View is the public shape, edit adds the |
| [`DeclaredPostsController`](#declaredpostscontroller) | final readonly class | 56 | wp/v2/{rest_base} for extra post types declared by an active extension. |
| [`DeclaredTermsController`](#declaredtermscontroller) | final readonly class | 43 | wp/v2/{rest_base} for the taxonomies plugin code registers to show in |
| [`Embed`](#embed) | final class | 264 | The _embed decoration and the embed context. Every embeddable link in an |
| [`EngineRoutes`](#engineroutes) | final class | 66 | The engine's own REST routes in the reference's regex form, for the |
| [`ExistingPost`](#existingpost) | final class | 10 | A post created with an id names one that exists: refused with |
| [`Fields`](#fields) | final readonly class | 106 | The _fields response filter. Dot paths descend ("title.rendered"); the |
| [`FontArgs`](#fontargs) | final class | 53 | The font library's parameters as the reference's REST index lists them |
| [`FontCollectionsController`](#fontcollectionscontroller) | final readonly class | 78 | wp/v2/font-collections (probe: the font library routes on the |
| [`FontFaceRecord`](#fontfacerecord) | final class | 21 | A font face route names a font face: refused as an invalid post id |
| [`FontFamilyRecord`](#fontfamilyrecord) | final class | 8 | A font family route names a font family: refused as an invalid post id |
| [`FontObject`](#fontobject) | final readonly class | 87 | A font family and a font face as wp/v2 shapes them (probe: the font |
| [`FontsController`](#fontscontroller) | final readonly class | 162 | wp/v2/font-families and their font-faces, read and deleted (probe: the |
| [`GlobalStylesController`](#globalstylescontroller) | final readonly class | 158 | wp/v2/global-styles: the site editor's saved styles (one post per |
| [`GlobalStylesObject`](#globalstylesobject) | final readonly class | 87 | The wp/v2/global-styles item, theme, and revision shapes. |
| [`IndexController`](#indexcontroller) | final readonly class | 106 | The API index at /wp-json/: the site facts monitors read (name, url, |
| [`InstalledThemesController`](#installedthemescontroller) | final readonly class | 142 | wp/v2/themes as the reference answers it (probe rest-themes): the |
| [`ItemRender`](#itemrender) | final class | 54 | What a post read sets up around its rendering, as the reference does for |
| [`Links`](#links) | final class | 83 | Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along. |
| [`LiveSettings`](#livesettings) | final readonly class | 77 | wp/v2/settings with plugins loaded, served from the registered settings |
| [`MediaController`](#mediacontroller) | final readonly class | 294 | wp/v2/media: list, single, upload on both transports (multipart field |
| [`MediaObject`](#mediaobject) | final readonly class | 171 | The wp/v2 media object, view and edit context. |
| [`MenuCollectionParams`](#menucollectionparams) | final class | 8 | The menus list's parameters as the reference declares them: a flat |
| [`MenuItemCollectionParams`](#menuitemcollectionparams) | final class | 39 | The menu items list's parameters as the reference declares them: the |
| [`MenuItemObject`](#menuitemobject) | final readonly class | 75 | The wp/v2/menu-items resource. |
| [`MenuItemStatus`](#menuitemstatus) | final class | 13 | Menu items listed with a status beyond publish by a caller who may not |
| [`MenuLists`](#menulists) | final readonly class | 84 | The menus and menu items lists, served as the reference serves them: the |
| [`MenuObject`](#menuobject) | final readonly class | 41 | The wp/v2/menus resource: a nav_menu term plus locations and auto_add. |
| [`MenusController`](#menuscontroller) | final readonly class | 262 | wp/v2/menus, menu-items, and menu-locations. Viewing needs edit_posts; |
| [`NavigationController`](#navigationcontroller) | final readonly class | 48 | wp/v2/navigation: the block theme's navigation menus, stored as |
| [`ParamCheck`](#paramcheck) | final class | 76 | The required / validate / sanitize pass over a request's declared arguments. |
| [`PathArgs`](#pathargs) | final class | 71 | The arguments a route's path names, as the reference lists them among the |
| [`PatternDirectoryController`](#patterndirectorycontroller) | final readonly class | 120 | wp/v2/pattern-directory/patterns (Austin, 2026-10-21, decision 1B): the |
| [`PluginsController`](#pluginscontroller) | final readonly class | 287 | wp/v2 plugins: what sits in wp-content/plugins, in the reference's |
| [`PolicyGate`](#policygate) | final readonly class | 176 | Judges a route's policy against the caller, with the reference's |
| [`PostCollectionParams`](#postcollectionparams) | final class | 242 | A post type's collection parameters as the reference builds them for its |
| [`PostListArgs`](#postlistargs) | final class | 170 | The WP_Query arguments a post list request makes, as the reference makes |
| [`PostObject`](#postobject) | final readonly class | 567 | Builds the wp/v2 post and page objects in the reference's shape: the |
| [`PostsController`](#postscontroller) | final readonly class | 264 | wp/v2 posts and pages, read side. |
| [`PostsWriteController`](#postswritecontroller) | final readonly class | 528 | wp/v2 posts and pages, write side: create, update, trash, and force |
| [`RegisteredFields`](#registeredfields) | final class | 105 | The fields plugin code adds to an object type with register_rest_field, |
| [`RegisteredPostFields`](#registeredpostfields) | final readonly class | 89 | The REST object of a post whose type plugin code registered (probe rest-plugin-types), built by what the type supports. |
| [`RegisteredType`](#registeredtype) | final readonly class | 46 | A post type plugin code registered, as its REST object follows it |
| [`RenderedFields`](#renderedfields) | final class | 68 | A post's rendered title, content and excerpt as a REST response carries |
| [`Reply`](#reply) | final class | 63 | JSON responses in the reference's shape: its header set, its json_encode |
| [`Responses`](#responses) | final class | 95 | REST answers as the WordPress layer shapes them: any value made a |
| [`RestMeta`](#restmeta) | final class | 296 | An object's meta field over REST, from the keys registered to show |
| [`RestUrl`](#resturl) | final readonly class | 38 | REST URLs in the form the reference emits for the site's permalink mode: |
| [`RevisionCollection`](#revisioncollection) | final class | 177 | A revisions list as the reference answers it (probe of the posts, |
| [`RevisionsController`](#revisionscontroller) | final readonly class | 299 | wp/v2 revisions under posts, pages, patterns and navigation menus, and |
| [`RouteArgs`](#routeargs) | final class | 34 | The argument groups a route registers with, filled the way register_rest_route fills them. |
| [`RouteCatalogue`](#routecatalogue) | final readonly class | 143 | The routes the engine serves, described in the reference's shape for the |
| [`RouteIndex`](#routeindex) | final class | 80 | The description of one route the REST index publishes: namespace, methods, endpoints with their argument schemas, self link. |
| [`RouteMatch`](#routematch) | final class | 68 | Finds the registered handler for a method and path among the runtime's route table. |
| [`RouteTable`](#routetable) | final class | 38 | The registered endpoints in dispatch shape: one handler list per route, methods as a set, non-numeric keys lifted into the route's options. |
| [`RuntimeEnvelope`](#runtimeenvelope) | final readonly class | 123 | The REST server's filters around one of Minn's own routes, as the |
| [`RuntimePrepare`](#runtimeprepare) | final class | 145 | An item a REST response carries, through the filter the reference runs |
| [`RuntimeRoutes`](#runtimeroutes) | final class | 528 | Routes plugin code registered with register_rest_route(), answered |
| [`Schema`](#schema) | final readonly class | 503 | JSON-schema handling the way the REST API's argument validation does it: |
| [`SchemaChoices`](#schemachoices) | final readonly class | 136 | anyOf and oneOf the way the REST API's validation judges them (probe |
| [`SchemaData`](#schemadata) | final class | 12 | REST schemas captured from the reference for the facade's controller |
| [`SchemaValues`](#schemavalues) | final class | 198 | The value side of JSON Schema, as the reference applies it: what counts |
| [`SearchController`](#searchcontroller) | final readonly class | 171 | wp/v2 search over published content: id, title, url, type, and the |
| [`Services`](#services) | final class | 417 | The objects one REST request shares, each made once, on first use, from |
| [`Settings`](#settings) | final readonly class | 113 | The registered settings the Settings views read and write, mapped to |
| [`SettingsController`](#settingscontroller) | final readonly class | 32 | wp/v2/settings: read and write, both behind manage_options; with plugins loaded, every registered setting (LiveSettings). |
| [`SidebarsController`](#sidebarscontroller) | final readonly class | 207 | wp/v2/sidebars and wp/v2/widget-types as the reference answers them |
| [`SiteHealthController`](#sitehealthcontroller) | final readonly class | 17 | wp-site-health/v1 (probe: the site health routes on the reference): the |
| [`SiteSchema`](#siteschema) | final class | 38 | The site's JSON-schema handler: one Schema whose email test, number |
| [`StatusesController`](#statusescontroller) | final readonly class | 97 | wp/v2/statuses as the reference answers it (probe rest-statuses): every |
| [`Subjects`](#subjects) | final readonly class | 53 | Whether the record a route capture names exists, for the policy gate to |
| [`Taxonomies`](#taxonomies) | final class | 48 | The taxonomy registry the wp/v2 surface describes: the core set seeded |
| [`TemplateObject`](#templateobject) | final readonly class | 105 | The wp/v2/templates and wp/v2/template-parts resource. |
| [`TemplateRevisionsController`](#templaterevisionscontroller) | final readonly class | 197 | wp/v2/templates and wp/v2/template-parts revisions and autosaves: the |
| [`TemplatesController`](#templatescontroller) | final readonly class | 329 | wp/v2/templates and wp/v2/template-parts: the block theme's templates as |
| [`TermCollectionParams`](#termcollectionparams) | final class | 13 | A taxonomy's term list parameters as the reference declares them (probe |
| [`TermFilters`](#termfilters) | final class | 25 | A term a REST read answers with, as plugin code filters it on the |
| [`TermListArgs`](#termlistargs) | final class | 28 | The get_terms arguments a term list request makes, as the reference |
| [`TermObject`](#termobject) | final readonly class | 107 | The wp/v2 category and tag objects. |
| [`TermsController`](#termscontroller) | final readonly class | 193 | wp/v2 categories, tags, and pattern categories: list, single, and the create/update/delete the taxonomy admin drives. |
| [`Types`](#types) | final class | 190 | The engine's registry of built-in post types, seeded from the observed |
| [`UrlDetails`](#urldetails) | final class | 64 | A page's details as the editor's link previews read them (probe |
| [`UserCollectionParams`](#usercollectionparams) | final class | 11 | The users list's parameters as the reference declares them (probe |
| [`UserListArgs`](#userlistargs) | final class | 47 | The WP_User_Query arguments a user list request makes, as the reference |
| [`UserObject`](#userobject) | final readonly class | 103 | The wp/v2 user objects: the public view shape and the edit-context shape. |
| [`UsersController`](#userscontroller) | final readonly class | 380 | wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives. |
| [`ViewConfigPatch`](#viewconfigpatch) | final class | 151 | A patch to a view configuration (WP_View_Config_Data; probe |
| [`WidgetObject`](#widgetobject) | final readonly class | 72 | A widget as wp/v2/widgets shows it (probe rest-widgets): its id and base, |
| [`WidgetsController`](#widgetscontroller) | final readonly class | 170 | wp/v2/widgets as the reference answers it (probe rest-widgets), for |

## Api

`final readonly class Minn\Rest\Api` · `public/minn/src/Minn/Rest/Api.php`

The REST API: wires the controllers for one request and dispatches a
route. Errors thrown anywhere underneath become the reference's error
payload under the reference's headers.

- const `ADDRESSING` = `array (   0 => 'rest_route',   1 => '_method',   2 => '_envelope',   3 => '_jsonp',   4 => '_wpnonce',   5 => '_fields',   6 => '_embed', )` — Query keys that address a request (its route, method override, envelope, nonce, selection) rather than name a write's argument.

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

### `listPosts(string $route, string $type, Closure $shape, ?Minn\Objects\RestRequest $as = NULL): Minn\Http\Response`

A post type's list as its route serves it (the caller, the statuses,
the parameters, the query, the totals and page links), each post given
the shape asked for: the facade's controller class shapes them itself.

- `@param \Closure(\Minn\Content\PostRecord, Context): array<string, mixed> $shape`

### `revisionObject(array $row, Minn\Rest\Context $context): array`

A revision (or autosave) row as wp/v2 shapes it, in the view or edit context. @param array<string, mixed> $row

- `@param array<string, mixed> $row`

### `postObject(): Minn\Rest\PostObject`

The wp/v2 post shape.

### `termObject(): Minn\Rest\TermObject`

The wp/v2 term shape.

### `commentObject(): Minn\Rest\CommentObject`

The wp/v2 comment shape.

### `menuObject(): Minn\Rest\MenuObject`

The wp/v2 menu shape.

### `menuItemView(int $id, string $context): array`

One menu item as wp/v2 shapes it in a context (view, edit or embed); empty when there is none. @return array<string, mixed>

- `@return array<string, mixed>`

### `mediaView(int $id, string $context): array`

One attachment as wp/v2 shapes it in a context (view, edit or embed), links included; empty when there is none. @return array<string, mixed>

- `@return array<string, mixed>`

### `mediaLinks(int $id, string $context): array`

One attachment's wp/v2 links in a context, without rendering the rest of it; empty when there is none. @return array<string, mixed>

- `@return array<string, mixed>`

### `templateView(Minn\Theme\TemplateRecord $record, string $context, string $as): array`

A template as wp/v2 shapes it in a context, for the route's own type (templates or template parts). @return array<string, mixed>

- `@return array<string, mixed>`

### `themeFields(Minn\Objects\Theme $theme, Minn\Objects\RestRequest $request): array`

A theme in the wp/v2 shape, links included, before rest_prepare_theme. @return array<string, mixed>

- `@return array<string, mixed>`

### `embedLinks(array $data, array $rels): array`

A response's data with its embeddable links answered in-process as this caller; every rel when none is named. @param list<string> $rels @return array<string, mixed>

- `@param list<string> $rels @return array<string, mixed>`

### `userObject(): Minn\Rest\UserObject`

The wp/v2 user shape.

### `types(): Minn\Rest\Types`

The post types the surface knows.

### `actingAs(int $userId, string $token): self`

Runs the request as a user already proven by the outer request, for
the in-process calls plugin code makes, or as the user plugin code
settled (determine_current_user; 0 for nobody); a user who no longer
exists leaves the caller as the request itself resolves it.

### `handle(string $route): Minn\Http\Response`

Resolves a REST route (from the path or from ?rest_route=) to a response.

### `withServerHeaders(Minn\Http\Response $response, string $route): Minn\Http\Response`

The headers the reference's server adds to every REST answer over
HTTP (probe rest-server-headers): X-Robots-Tag noindex always; and,
while rest_send_cors_headers stays hooked, for a request from an
Origin the CORS headers echoing it with Vary: Origin, otherwise Vary:
Origin for a GET from someone not signed in. A signed-in caller's
answer also carries the no-cache headers (rest_send_nocache_headers,
nocache_headers), and a cookie caller whose nonce held a fresh
X-WP-Nonce, refused or not. The CORS header lists go through
rest_exposed_cors_headers and rest_allowed_cors_headers, handed the
route's request.

### `withDiscovery(Minn\Http\Response $response): Minn\Http\Response`

A REST answer as it leaves over HTTP: pointing at the API's root, unless it carries a Link header of its own (a batch's parts do not).

### `writePost(string $verb, string $route, string $type, string $base, string $id = '', ?Minn\Objects\RestRequest $as = NULL): Minn\Http\Response`

A post of a REST type the engine has no route for (one under a
namespace of its own, probe namespaced-type) created, updated or
deleted by the engine's own writer, for the type's controller, whose
permission checks have already run.

### `handleEngineOnly(string $route, ?Minn\Objects\RestRequest $as = NULL): ?Minn\Http\Response`

The engine's own answer to a route, or null when no engine route
takes it; the runtime's table is never consulted. This is what the
runtime's server calls for a core route, so a route the engine
declines cannot bounce between the two. $as is the caller's own
request object, which the route's parameters are set on.

### `allowedOn(string $route, ?Minn\Objects\RestRequest $as = NULL): array`

The methods this caller may use on one of the engine's routes, as
its Allow header lists them (rest_send_allow_header asks).

- `@return list<string>`

Internals: `controllers()` (private, line 81), `engineResponse()` (private, line 420), `withPageLinks()` (private, line 435), `options()` (private, line 466), `withSchema()` (private, line 483), `withAllow()` (private, line 504)


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


### `list(Minn\Http\Request $request, string $userId): Minn\Http\Response`

Route: `GET /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords (cap edit_user on {user_id}; user {user_id} must exist)`

The user's application passwords.

### `create(Minn\Http\Request $request, string $userId): Minn\Http\Response`

Route: `POST /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords (cap edit_user on {user_id}; user {user_id} must exist)`

Mints one; the plain password is in this answer only.

### `deleteAll(Minn\Http\Request $request, string $userId): Minn\Http\Response`

Route: `DELETE /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords (cap edit_user on {user_id}; user {user_id} must exist)`

Removes every one.

### `introspect(Minn\Http\Request $request, string $userId): Minn\Http\Response`

Route: `GET /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords/introspect (cap edit_user on {user_id}; user {user_id} must exist)`

The password the current Basic auth session used.

### `single(Minn\Http\Request $request, string $userId, string $uuid): Minn\Http\Response`

Route: `GET /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords/{uuid:[\w\-]+} (cap edit_user on {user_id}; user {user_id} must exist)`

One password by uuid.

### `update(Minn\Http\Request $request, string $userId, string $uuid): Minn\Http\Response`

Route: `POST /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords/{uuid:[\w\-]+} (cap edit_user on {user_id}; user {user_id} must exist)`

Route: `PUT /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords/{uuid:[\w\-]+} (cap edit_user on {user_id}; user {user_id} must exist)`

Route: `PATCH /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords/{uuid:[\w\-]+} (cap edit_user on {user_id}; user {user_id} must exist)`

Renames one.

### `delete(Minn\Http\Request $request, string $userId, string $uuid): Minn\Http\Response`

Route: `DELETE /wp/v2/users/{user_id:(?:[\d]+|me)}/application-passwords/{uuid:[\w\-]+} (cap edit_user on {user_id}; user {user_id} must exist)`

Removes one.

Internals: `subject()` (private, line 140), `existing()` (private, line 164), `validate()` (private, line 173), `item()` (private, line 183), `when()` (private, line 200)


## ArgCheck

`final readonly class Minn\Rest\ArgCheck` · `public/minn/src/Minn/Rest/ArgCheck.php`

Judges a route's declared arguments against the request before the
policy is judged, the way the reference does: a required parameter that
did not arrive is rest_missing_callback_param; the shared collection
parameters (context, page, per_page, search) are judged first and a
refusal among them is answered alone; then every other invalid one is
listed in rest_invalid_param, in the order the route declares them, with
the schema's own refusal under details (none for an argument whose
refusal is bare, Args::BARE_REFUSAL). An argument judged in the
sanitize round (Args::SANITIZE_ROUND) is read only once all the others
passed. The query is read against the route's args, the JSON body
against its body set, and a JSON body that does not parse is refused on
every route first. An argument its handler judges
(Args::HANDLER_VALIDATES) is left to the handler.

Used by: `Minn\Rest\Api`, `Minn\Rest\BatchController`

```php
__construct(Minn\Rest\Schema $schema)
```


### `closure(): Closure`

The check as the router takes it.

### `check(Minn\Http\Route $route, Minn\Http\Request $request, array $captures = array ( )): void`

Throws the refusal the declared arguments earn, or returns. @param array<string, string> $captures what the path named, for a route whose parameters depend on it

- `@param array<string, string> $captures what the path named, for a route whose parameters depend on it`

Internals: `json()` (private, line 69), `missing()` (private, line 85), `round()` (private, line 99), `sanitizeRound()` (private, line 130), `password()` (private, line 152), `refuse()` (private, line 168)


## BatchController

`final readonly class Minn\Rest\BatchController` · `public/minn/src/Minn/Rest/BatchController.php`

batch/v1 as the reference answers it (probe rest-batch): up to 25
writes (POST, PUT, PATCH, DELETE) in one request, each answered in turn
as {body, status, headers}, the whole as 207. Only routes that take part
in batches may be asked: posts, pages and the other post types shown in
REST (not media, templates, global styles or fonts), the taxonomies,
users, widgets, and a plugin's routes that opt in with allow_batch. Any
other route answers rest_batch_not_allowed with its Allow header, a path
with no route rest_no_route. With require-all-validate, every request's
arguments are judged first and one failure answers the whole batch with
the failures alone (null for the rest), nothing written.

- const `BODY` = `array (   'validation' =>    array (     'type' => 'string',     'enum' =>      array (       0 => 'require-all-validate',       1 => 'normal',     ),     'default' => 'normal',     'required' => false,   ),   'requests' =>    array (     'required' => true,     'type' => 'array',     'maxItems' => 25,     'items' =>      array (       'type' => 'object',       'properties' =>        array (         'method' =>          array (           'type' => 'string',           'enum' =>            array (             0 => 'POST',             1 => 'PUT',             2 => 'PATCH',             3 => 'DELETE',           ),           'default' => 'POST',         ),         'path' =>          array (           'type' => 'string',           'required' => true,         ),         'body' =>          array (           'type' => 'object',           'properties' =>            array (           ),           'additionalProperties' => true,         ),         'headers' =>          array (           'type' => 'object',           'properties' =>            array (           ),           'additionalProperties' =>            array (             'type' =>              array (               0 => 'string',               1 => 'array',             ),             'items' =>              array (               'type' => 'string',             ),           ),         ),       ),     ),   ), )`
- const `NOT_BATCHED` = `array (   0 => 'attachment',   1 => 'wp_template',   2 => 'wp_template_part',   3 => 'wp_global_styles',   4 => 'wp_font_family',   5 => 'wp_font_face', )` — Post types served by controllers that do not take part in batches.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Http\Router $router, Closure $dispatch, Minn\Rest\ArgCheck $args, Minn\Rest\Types $types)
```
- `@param Closure(Request): Response $dispatch answers one request as the REST API would on its own`


### `batch(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /batch/v1 (public)`

The requests answered in turn.

Internals: `refusal()` (private, line 92), `invalid()` (private, line 110), `claims()` (private, line 141), `batchablePath()` (private, line 151), `subRequest()` (private, line 169), `error()` (private, line 187), `envelope()` (private, line 193)


## BatchRequest

`final class Minn\Rest\BatchRequest` · `public/minn/src/Minn/Rest/BatchRequest.php`

The requests a batch payload names, normalised into descriptors the
caller turns into real request objects. A path's query string is split
off here so each sub-request carries its own query parameters, which is
what lets one round trip stand in for several.

### static `describe(mixed $requests): array`

The batch body's requests in the reference's normalised form.

- `@return list<array{method: string, path: string, query: array<string, mixed>, body: ?array, headers: ?array}>`


## BlockDirectoryController

`final readonly class Minn\Rest\BlockDirectoryController` · `public/minn/src/Minn/Rest/BlockDirectoryController.php`

wp/v2/block-directory/search (Austin, 2026-10-21, decision 1B): the
directory's block plugins for a term, asked as plugins_api asks (its
filters run) of wordpress.org through the Minn update service. The term
is required before the caller is judged; then only someone who may
install and activate plugins searches. A plugin with blocks is one item,
named for its first block: its short description as the directory words
it, ratings out of five, the author's name without its link, the last
update as a date and as how long ago, and a link to install it.

- const `REFUSAL` = `'Sorry, you are not allowed to browse the block directory.'`
- const `ARGS` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',     ),     'default' => 'view',     'required' => false,   ),   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'term' =>    array (     'description' => 'Limit result set to blocks matching the search term.',     'type' => 'string',     'minLength' => 1,     'required' => true,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url)
```


### `search(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/block-directory/search (cap install_plugins + activate_plugins)`

The block plugins matching a term, a page at a time.

Internals: `item()` (private, line 80)


## BlockEditorController

`final readonly class Minn\Rest\BlockEditorController` · `public/minn/src/Minn/Rest/BlockEditorController.php`

Two of wp-block-editor/v1's routes, for someone who may edit theme
options (probe: the block editor routes on the reference).
navigation-fallback answers the navigation menu a navigation block
falls back to (the newest published one, made when there is none) by
its id, linked so it embeds. export is refused to anyone else as the
reference refuses it; the theme it packs (the theme's files with its
templates and theme.json as the site has them, as a zip) the engine
does not build, so for someone allowed that route declines.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `export(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-block-editor/v1/export (cap edit_theme_options)`

The active theme packed with the site's templates and styles.

### `navigationFallback(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-block-editor/v1/navigation-fallback (cap edit_theme_options)`

The navigation menu a navigation block falls back to.


## BlockRendererController

`final readonly class Minn\Rest\BlockRendererController` · `public/minn/src/Minn/Rest/BlockRendererController.php`

wp/v2/block-renderer as the reference answers it (probe
rest-block-renderer), the route the editor's ServerSideRender asks: a
dynamic block rendered from the attributes sent (query or body), checked
against the block's own attributes and filled in with their defaults,
inside the post named by post_id when there is one (it becomes the
global post, and the block's postId and postType context). Only the edit
context is accepted, and asking for none is asking for the view context,
which is refused, as on the reference. Arguments are judged before the
caller: someone who can edit posts may render, or edit the post named.

- const `ARGS` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'edit',     ),     'default' => 'view',     'required' => false,     'handler_validates' => true,   ),   'attributes' =>    array (     'description' => 'Attributes for the block.',     'type' => 'object',     'default' =>      array (     ),     'required' => false,     'handler_validates' => true,   ),   'post_id' =>    array (     'description' => 'ID of the post context.',     'type' => 'integer',     'required' => false,     'handler_validates' => true,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Schema $schema, Minn\Rest\Caller $caller)
```


### `render(Minn\Http\Request $request, string $name): Minn\Http\Response`

Route: `GET /wp/v2/block-renderer/{name:[a-z0-9-]+/[a-z0-9-]+} (public)`

Route: `POST /wp/v2/block-renderer/{name:[a-z0-9-]+/[a-z0-9-]+} (public)`

The block's markup, as {"rendered": "..."}.

Internals: `refuseInvalid()` (private, line 74), `permit()` (private, line 92), `rendered()` (private, line 106)


## BlockTypesController

`final readonly class Minn\Rest\BlockTypesController` · `public/minn/src/Minn/Rest/BlockTypesController.php`

wp/v2/block-types as the reference answers it (probe rest-block-types):
every registered block type, core's and a plugin's, in registration
order, or a namespace's share, for anyone who can edit a type shown in
REST. Each carries its registration (attributes, supports, selectors,
contexts, asset handles and the first of each as the old single field)
with its styles (its own, then those registered for it) and its
variations as built when asked; a dynamic block links to its renderer.
Served with plugins loaded, since a plugin's blocks come from its code.

- const `NAMESPACE` = `array (   'namespace' =>    array (     'type' => 'string',     'description' => 'Block namespace.',     'required' => false,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/block-types (public)`

Every block type, or those of one namespace (?namespace=).

### `inNamespace(Minn\Http\Request $request, string $namespace): Minn\Http\Response`

Route: `GET /wp/v2/block-types/{namespace:[a-zA-Z0-9_-]+} (public)`

One namespace's block types.

### `single(Minn\Http\Request $request, string $namespace, string $name): Minn\Http\Response`

Route: `GET /wp/v2/block-types/{namespace:[a-zA-Z0-9_-]+}/{name:[a-zA-Z0-9_-]+} (public)`

One block type.

### static `fields(Minn\Objects\BlockType $type): array`

A block type's fields in the reference's order, as the route and
WP_REST_Block_Types_Controller show them: its registration (supports
{} when it has none), its styles (its own, then those registered for
it), its variations as built when asked, and the first handle of each
asset kind as the old single field (probe rest-block-controllers).

- `@return array<string, mixed>`

Internals: `items()` (private, line 63), `item()` (private, line 77), `links()` (private, line 137), `requireViewer()` (private, line 152)


## BlocksController

`final readonly class Minn\Rest\BlocksController` · `public/minn/src/Minn/Rest/BlocksController.php`

wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block
posts. The reading and writing is the posts machinery with the post
type's own capabilities and the posts list's parameters; what is this
type's alone is the reading rule: a pattern is read only by one who may
read it (read_post), published or not, so without that the list counts
the patterns and shows none, and a single one is refused.

- const `TYPE` = `'wp_block'`
- const `BASE` = `'blocks'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\PostsController $reads, Minn\Rest\PostsWriteController $writes, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/{base:blocks} (public)`

The blocks, each shown to a caller who may read it.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/blocks/{id:[\d]+} (public; block {id} must exist)`

One block. Editing context is the posts machinery's own gate
(rest_forbidden_context); a view is refused to anyone who may not read
the block, and an unknown one is not found rather than refused.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/blocks (cap publish_posts)`

Creates a block: a pattern's create_posts is publish_posts.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/blocks/{id:[\d]+} (cap edit_post on {id}; block {id} must exist)`

Route: `PUT /wp/v2/blocks/{id:[\d]+} (cap edit_post on {id}; block {id} must exist)`

Route: `PATCH /wp/v2/blocks/{id:[\d]+} (cap edit_post on {id}; block {id} must exist)`

Updates a block.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/blocks/{id:[\d]+} (cap delete_post on {id}; block {id} must exist)`

Trashes or deletes a block.

Internals: `readable()` (private, line 55)


## Caller

`final class Minn\Rest\Caller` · `public/minn/src/Minn/Rest/Caller.php`

Who is making this REST call. Resolved once from the cookie and nonce;
an anonymous or failed caller has id 0 and every capability check fails.

Used by: `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockEditorController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuLists`, `Minn\Rest\MenuObject`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`

```php
__construct(Minn\Http\Request $request, Minn\Auth\Authenticator $authenticator, Minn\Auth\Capabilities $capabilities)
```


### `resolveAs(Minn\Auth\Authenticated $session): void`

Settles the caller as a session already proven elsewhere: an
in-process request a plugin makes through rest_do_request() carries
no nonce, so it runs as whoever the outer request resolved.

### `resolveAnonymous(): void`

Settles the caller as nobody: plugin code (determine_current_user) signed the request out.

### `cookieBound(): bool`

Whether a sign-in cookie vouches for this caller (or failed to, for
want of its nonce): a cookie's nonce vouches for its own user only.

### `recheck(): void`

Has the cookie and nonce checked again on the next question: a plugin's nonce lifetime (nonce_life) is heard only once plugins load.

### `resolveInvalidNonce(): void`

Settles the caller as one whose cookie's nonce does not vouch for the user plugin code named.

### `session(): ?Minn\Auth\Authenticated`

The session, or null for an anonymous or refused caller.

### `id(): int`

The caller's user id, 0 when anonymous.

### `can(string $capability, ?int $postId = NULL): bool`

Whether the caller holds a capability, on a post when given. With
plugins loaded the answer is WordPress's: the same mapping, then
map_meta_cap and user_has_cap, where role editors and lock plugins
have their say.

### `editsAnyRestType(): bool`

Whether the caller can edit posts of some type shown in REST: what
the reference asks before showing statuses, block types and the
active theme. A plugin's types count once plugins are loaded.

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

Internals: `resolve()` (private, line 165)


## Catalogue

`final class Minn\Rest\Catalogue` · `public/minn/src/Minn/Rest/Catalogue.php`

The route table read from the classes alone: every #[Route] under
src/Minn, as rows, with no request, database, or site behind it. This is
what contracts/api/routes.json is written from, so an agent can read
which routes exist, who may call them, and what they take, without
booting the engine. The per-request router builds the same rows for the
handlers it actually registered, which is what the live index serves.

### static `scan(string $sourceDir): array`

Every route declared by a class under the source directory, in file
order, then declaration order.

- `@return list<RouteRow>`

### static `document(array $rows, string $engineVersion): array`

The rows as the JSON catalogue: a version line and one entry per route.

- `@param list<RouteRow> $rows`
- `@return array<string, mixed>`

Internals: `classes()` (private, line 68)


## ClientSchema

`final class Minn\Rest\ClientSchema` · `public/minn/src/Minn/Rest/ClientSchema.php`

A JSON schema trimmed for a client (probe json-schema): only the keywords
a profile allows, in the order given, at every level a sub-schema sits.

A property's boolean required becomes its object's required list (names
whose required is true), kept where the object's own required is not a
list, appended last otherwise, even where the profile drops required
itself; an object's own list wins. A boolean required is always dropped.
Sub-schemas are trimmed where they are objects: a keyword's value, a
list's members, a map's entries; anything else stays as given.

- const `DRAFT_04_FIRST` = `array (   0 => '$schema',   1 => 'id',   2 => '$ref', )`
- const `DRAFT_04_LAST` = `array (   0 => 'required',   1 => 'allOf',   2 => 'not',   3 => 'definitions',   4 => 'dependencies',   5 => 'additionalItems', )`
- const `MAPS` = `array (   0 => 'properties',   1 => 'patternProperties',   2 => 'definitions',   3 => 'dependencies', )` — Keywords whose value maps names to sub-schemas.
- const `SCHEMAS` = `array (   0 => 'items',   1 => 'additionalProperties',   2 => 'additionalItems',   3 => 'not',   4 => 'anyOf',   5 => 'oneOf',   6 => 'allOf', )` — Keywords whose value is a sub-schema or a list of them.

### static `keywords(string $profile): array`

The keywords a profile keeps: draft-04's whole set, the REST API's for
any other profile.

- `@return list<string>`

### static `trim(array $schema, array $allowed): array`

A schema with only the allowed keywords, keyed by keyword (the values
do not matter), trimmed all the way down.

- `@param array<array-key, mixed> $schema`
- `@param array<array-key, mixed> $allowed`
- `@return array<array-key, mixed>`

Internals: `position()` (private, line 73), `member()` (private, line 82), `requiredNames()` (private, line 88)


## CommentListArgs

`final class Minn\Rest\CommentListArgs` · `public/minn/src/Minn/Rest/CommentListArgs.php`

The WP_Comment_Query arguments a comment list request makes, as the
reference makes them before rest_comment_query (probe
rest-comment-lists): each declared parameter the request carries under
its query name, an empty email and search when none is given, the
orderby name mapped to the query's, the found rows counted, the posts
loaded, the date bounds as one clause, the offset the page makes when
none is given, and ids alone for a HEAD request.

- const `MAPPINGS` = `array (   'author' => 'author__in',   'author_email' => 'author_email',   'author_exclude' => 'author__not_in',   'exclude' => 'comment__not_in',   'include' => 'comment__in',   'offset' => 'offset',   'order' => 'order',   'parent' => 'parent__in',   'parent_exclude' => 'parent__not_in',   'per_page' => 'number',   'post' => 'post__in',   'search' => 'search',   'status' => 'status',   'type' => 'type', )`
- const `ORDERBY` = `array (   'date' => 'comment_date',   'date_gmt' => 'comment_date_gmt',   'id' => 'comment_ID',   'include' => 'comment__in',   'post' => 'comment_post_ID',   'parent' => 'comment_parent',   'type' => 'comment_type', )`

Used by: `Minn\Rest\CommentsController`

### static `of(Minn\Objects\RestRequest $wp, array $registered, string $method): array`

The arguments before plugins see them: a parameter maps when the
collection (as rest_comment_collection_params left it) still has it.

- `@param array<string, mixed> $registered`
- `@return array<string, mixed>`


## CommentObject

`final readonly class Minn\Rest\CommentObject` · `public/minn/src/Minn/Rest/CommentObject.php`

The wp/v2 comment object; edit context adds the moderation-desk fields.

Used by: `Minn\Rest\Api`, `Minn\Rest\CommentsController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Comments $comments, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `build(Minn\Content\CommentRecord $c, Minn\Rest\Context $context): array`

A comment in the context asked for, through rest_prepare_comment when a plugin hooks it.

### `url(): Minn\Rest\RestUrl`

The REST URL builder.

Internals: `buildFields()` (private, line 41)


## CommentsController

`final readonly class Minn\Rest\CommentsController` · `public/minn/src/Minn/Rest/CommentsController.php`

wp/v2/comments: the status tabs with pagination headers, single,
signed-in replies, moderation updates, trash, and force delete.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Comments $comments, Minn\Content\Posts $posts, Minn\Rest\CommentObject $object, Minn\Rest\Caller $caller, Minn\Content\Users $users)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/comments (public)`

The comments list as the reference serves it: the request's
WP_Comment_Query through rest_comment_query, its totals (counted again
when a page comes back empty), and the comments the caller may read.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/comments/{id:[\d]+} (public; comment {id} must exist)`

One comment, if the caller may read it: one on a protected post only with the post's password, or for one who may edit the post.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/comments (signed in)`

A signed-in reply; the author fields come from the user.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/comments/{id:[\d]+} (cap moderate_comments; comment {id} must exist)`

Route: `PUT /wp/v2/comments/{id:[\d]+} (cap moderate_comments; comment {id} must exist)`

Route: `PATCH /wp/v2/comments/{id:[\d]+} (cap moderate_comments; comment {id} must exist)`

Status flips and content or author edits, for moderators.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/comments/{id:[\d]+} (cap moderate_comments; comment {id} must exist)`

Trash remembers where the comment came from; force removes it outright.

Internals: `totals()` (private, line 81), `events()` (private, line 166), `allowed()` (private, line 235), `readablePost()` (private, line 276), `readable()` (private, line 285), `plainComment()` (private, line 295), `prepared()` (private, line 318)


## Context

`enum Minn\Rest\Context` · `public/minn/src/Minn/Rest/Context.php`

The view a REST caller asked for. View is the public shape, edit adds the
raw halves and the caller's own action links, embed is the reduced shape
a linked resource carries when it rides inside another response.

Cases: `View` = `'view'`, `Edit` = `'edit'`, `Embed` = `'embed'`

Used by: `Minn\Rest\Api`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuLists`, `Minn\Rest\MenusController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\RevisionsController`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetObject`, `Minn\Rest\WidgetsController`

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

Route: `GET /wp/v2/{base:[a-z0-9_-]+} (declared type {base})`

A declared type's list.

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (declared type {base})`

A declared type's single post.

### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:[a-z0-9_-]+} (declared type {base})`

Creates a post of a declared type.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (declared type {base})`

Route: `PUT /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (declared type {base})`

Route: `PATCH /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (declared type {base})`

Updates a post of a declared type.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (declared type {base})`

Trashes or deletes a post of a declared type.

Internals: `slug()` (private, line 68)


## DeclaredTermsController

`final readonly class Minn\Rest\DeclaredTermsController` · `public/minn/src/Minn/Rest/DeclaredTermsController.php`

wp/v2/{rest_base} for the taxonomies plugin code registers to show in
REST (probe rest-plugin-types), answered as the core taxonomies are.
Registered after the declared post types; a base no such taxonomy names
declines, so a plugin's own route under wp/v2 is not shadowed.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\TermsController $terms)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:[a-z0-9_-]+} (registered taxonomy {base})`

A registered taxonomy's terms.

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (registered taxonomy {base})`

One term of a registered taxonomy.

### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:[a-z0-9_-]+} (registered taxonomy {base})`

Creates a term in a registered taxonomy.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (registered taxonomy {base})`

Route: `PUT /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (registered taxonomy {base})`

Route: `PATCH /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (registered taxonomy {base})`

Updates a term of a registered taxonomy.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+} (registered taxonomy {base})`

Deletes a term of a registered taxonomy.


## Embed

`final class Minn\Rest\Embed` · `public/minn/src/Minn/Rest/Embed.php`

The _embed decoration and the embed context. Every embeddable link in an
object's _links is answered in-process as the same caller with
context=embed and placed under _embedded[rel], an error as the error
itself; a rel whose every embed came back empty is left out, while an
empty list beside a full one stays (wp:term keeps its empty tags list
next to the categories).
Lists have _fields applied per item before the links are read, so a
_fields value without _links embeds nothing there; a single object embeds
first and is filtered afterwards. The embed context is the view object
cut to the reference's key set for that kind of item.

- const `KEYS` = `array (   'post' =>    array (     0 => 'id',     1 => 'date',     2 => 'slug',     3 => 'type',     4 => 'link',     5 => 'title',     6 => 'excerpt',     7 => 'author',     8 => 'featured_media',     9 => '_links',   ),   'media' =>    array (     0 => 'id',     1 => 'date',     2 => 'slug',     3 => 'type',     4 => 'link',     5 => 'title',     6 => 'author',     7 => 'featured_media',     8 => 'caption',     9 => 'alt_text',     10 => 'media_type',     11 => 'mime_type',     12 => 'media_details',     13 => 'source_url',     14 => '_links',   ),   'user' =>    array (     0 => 'id',     1 => 'name',     2 => 'url',     3 => 'description',     4 => 'link',     5 => 'slug',     6 => 'avatar_urls',     7 => '_links',   ),   'term' =>    array (     0 => 'id',     1 => 'link',     2 => 'name',     3 => 'slug',     4 => 'taxonomy',     5 => '_links',   ),   'comment' =>    array (     0 => 'id',     1 => 'parent',     2 => 'author',     3 => 'author_name',     4 => 'author_url',     5 => 'date',     6 => 'content',     7 => 'link',     8 => 'type',     9 => 'author_avatar_urls',     10 => '_links',   ),   'menu-item' =>    array (     0 => 'id',     1 => 'title',     2 => 'status',     3 => 'url',     4 => 'attr_title',     5 => 'description',     6 => 'type',     7 => 'type_label',     8 => 'object',     9 => 'object_id',     10 => 'parent',     11 => 'menu_order',     12 => 'target',     13 => 'classes',     14 => 'xfn',     15 => 'invalid',     16 => '_links',   ), )`
- const `TYPE_KEYS` = `array (   'wp_navigation' =>    array (     0 => 'status',     1 => 'content',   ),   'wp_block' =>    array (     0 => 'wp_pattern_sync_status',   ), )` — The keys a post type shows in embed beyond a post's (probe: each type's list in the embed context).

Used by: `Minn\Rest\Api`, `Minn\Rest\PostObject`, `Minn\Rest\RegisteredFields`, `Minn\Rest\TemplateRevisionsController`

```php
__construct(Minn\Http\Router $router, Minn\Rest\Types $types, Minn\Rest\Taxonomies $taxonomies)
```


### static `requested(array $query): ?array`

Null when _embed is absent; [] for every rel; otherwise the rels asked for. @return ?list<string>

- `@return ?list<string>`

### `decorate(Minn\Http\Request $request, Minn\Http\Response $response): Minn\Http\Response`

Decorates a finished response: the embed context, then _embedded, then the deferred _fields.

### `embedded(Minn\Http\Request $request, array $item, array $rels): array`

A response's data with its embeddable links answered (the runtime's
WP_REST_Server::embed_links): every rel when none is named.

- `@param array<string, mixed> $item @param list<string> $rels`
- `@return array<string, mixed>`

### static `halves(Minn\Content\PostRecord $p, array $object): array`

A post's view object with the nested halves its type's embed shows:
a navigation post's title and content keep their raw halves and the
content its block version, for any caller; a pattern's title, which
has only a raw half, shows empty.

- `@param array<string, mixed> $object`
- `@return array<string, mixed>`

### static `shape(string $type, array $item): array`

An item cut to the embed shape of its object type (a post type, a taxonomy, attachment, user or comment); any other kept whole. @param array<string, mixed> $item @return array<string, mixed>

- `@param array<string, mixed> $item @return array<string, mixed>`

Internals: `decorateItem()` (private, line 104), `answer()` (private, line 148), `target()` (private, line 185), `kind()` (private, line 203), `typeAt()` (private, line 226), `taxonomyBase()` (private, line 231), `context()` (private, line 281)


## EngineRoutes

`final class Minn\Rest\EngineRoutes` · `public/minn/src/Minn/Rest/EngineRoutes.php`

The engine's own REST routes in the reference's regex form, for the
index and for the runtime's server, whose route table plugin code reads
to learn what the site answers (a missing /wp/v2/comments there reads as
"comments are off").

Used by: `Minn\Rest\Api`, `Minn\Rest\RouteCatalogue`, `Minn\Rest\RuntimeEnvelope`

### static `map(Minn\Http\Router $router, array $declaredBases = array ( )): array`

The router's routes as route => methods, the way the index lists them.

- `@param list<string> $declaredBases the rest_base of every declared type`
- `@return array<string, list<string>> route => methods`

### static `forms(string $pattern, array $declaredBases = array ( )): array`

A route attribute pattern as the reference writes routes: `{id:\d+}`
becomes `(?P<id>\d+)`, a bare capture matches one segment, a `{rest*}`
capture the remainder, and a literal constraint (`{base:posts}`, or
the alternation `{base:posts|pages}`) expands to one route per
literal. The declared-type routes are written once as a `{base:[...]}`
catch-all; they list per declared type under its rest_base (as the
reference lists a registered type) and not at all when no type is
declared, so nothing listed answers no-route.

- `@param list<string> $declaredBases`
- `@return list<string>`


## ExistingPost

`final class Minn\Rest\ExistingPost` · `public/minn/src/Minn/Rest/ExistingPost.php` · implements `Minn\Http\Refusal`

A post created with an id names one that exists: refused with
rest_post_exists before the caller is judged, even signed out (probe
patch-security). An id of 0 passes here and is refused later, as an
invalid post id.

Used by: `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\NavigationController`, `Minn\Rest\PostsWriteController`

### static `refuse(Minn\Http\Request $request): void`

Throws rest_post_exists for a non-empty id in the body or the query.


## Fields

`final readonly class Minn\Rest\Fields` · `public/minn/src/Minn/Rest/Fields.php`

The _fields response filter. Dot paths descend ("title.rendered"); the
object's own key order is kept. Applied per item on list responses and to
the whole payload otherwise, which is why _fields on the associative
types response strips every key and yields [] over HTTP, a reference
quirk the engine reproduces by construction.

Used by: `Minn\Admin\SessionsController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockDirectoryController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenuLists`, `Minn\Rest\MenusController`, `Minn\Rest\PatternDirectoryController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\Responses`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`

- readonly `array $paths`
- readonly `bool $deferred`

### static `fromQuery(array $query): ?self`

The _fields selection a query carries, or null for everything.

- `@param array<string, mixed> $query`

### `withLinksForEmbedded(): self`

The same paths plus _links whenever _embedded is among them: the reference's single-object rule under _embed.

### `wants(string $field): bool`

Whether a top-level field is among the selection: named whole, below
one of its paths (title.rendered), or above one (a path into it).

### `apply(array $object): array`

The object with only the selected fields.

### static `select(array $available, array $requested): array`

The fields a response keeps when a caller names some: known fields as
given, dotted paths whose root is known, and `id` whenever it exists.

- `@param list<string> $available`
- `@param list<string> $requested the parsed `_fields` list`
- `@return list<string>`

Internals: `filter()` (private, line 72)


## FontArgs

`final class Minn\Rest\FontArgs` · `public/minn/src/Minn/Rest/FontArgs.php`

The font library's parameters as the reference's REST index lists them
(font families, font faces, font collections), in its order, each read
by Rest\FontsController or Rest\FontCollectionsController. A family's
list and a face's list take the posts list's ids, offset, order and
exact-search switch, ordered by id or by include alone, and only a
family's list takes slugs. Neither takes a search, a status or an
author: the reference ignores them. The ids a path names are listed as
the reference lists them, except that the index there calls a face's
family and id required; here they are optional, because the router
looks for a required argument in the query and the path always carries
them.

- const `CONTEXT` = `array (   'description' => 'Scope under which the request is made; determines fields present in response.',   'type' => 'string',   'enum' =>    array (     0 => 'view',     1 => 'embed',     2 => 'edit',   ),   'default' => 'view',   'required' => false, )`
- const `PAGE` = `array (   'description' => 'Current page of the collection.',   'type' => 'integer',   'default' => 1,   'minimum' => 1,   'required' => false, )`
- const `PER_PAGE` = `array (   'description' => 'Maximum number of items to be returned in result set.',   'type' => 'integer',   'default' => 10,   'minimum' => 1,   'maximum' => 100,   'required' => false, )`
- const `FAMILY_ID` = `array (   'description' => 'The ID for the parent font family of the font face.',   'type' => 'integer',   'required' => false, )`
- const `POST_LIST` = `array (   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'search_semantics' =>    array (     'description' => 'How to interpret the search input.',     'type' => 'string',     'enum' =>      array (       0 => 'exact',     ),     'required' => false,   ),   'offset' =>    array (     'description' => 'Offset the result set by a specific number of items.',     'type' => 'integer',     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'id',     'enum' =>      array (       0 => 'id',       1 => 'include',     ),     'required' => false,   ), )` — A list of font families or font faces past the shared three, in the reference's order.
- const `FAMILIES` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ),   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'search_semantics' =>    array (     'description' => 'How to interpret the search input.',     'type' => 'string',     'enum' =>      array (       0 => 'exact',     ),     'required' => false,   ),   'offset' =>    array (     'description' => 'Offset the result set by a specific number of items.',     'type' => 'integer',     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'id',     'enum' =>      array (       0 => 'id',       1 => 'include',     ),     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to posts with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ), )` — The font families list.
- const `FAMILY` = `array (   'id' =>    array (     'description' => 'Unique identifier for the post.',     'type' => 'integer',     'required' => false,   ),   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ), )` — One font family.
- const `FACES` = `array (   'font_family_id' =>    array (     'description' => 'The ID for the parent font family of the font face.',     'type' => 'integer',     'required' => false,   ),   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ),   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'search_semantics' =>    array (     'description' => 'How to interpret the search input.',     'type' => 'string',     'enum' =>      array (       0 => 'exact',     ),     'required' => false,   ),   'offset' =>    array (     'description' => 'Offset the result set by a specific number of items.',     'type' => 'integer',     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'id',     'enum' =>      array (       0 => 'id',       1 => 'include',     ),     'required' => false,   ), )` — A font family's faces.
- const `FACE` = `array (   'font_family_id' =>    array (     'description' => 'The ID for the parent font family of the font face.',     'type' => 'integer',     'required' => false,   ),   'id' =>    array (     'description' => 'Unique identifier for the font face.',     'type' => 'integer',     'required' => false,   ),   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ), )` — One font face of a family.
- const `FORCE` = `array (   'type' => 'boolean',   'default' => false,   'description' => 'Whether to bypass Trash and force deletion.',   'required' => false, )` — Whether a delete bypasses the trash, as a font route lists it.
- const `FAMILY_DELETE` = `array (   'id' =>    array (     'description' => 'Unique identifier for the post.',     'type' => 'integer',     'required' => false,   ),   'force' =>    array (     'type' => 'boolean',     'default' => false,     'description' => 'Whether to bypass Trash and force deletion.',     'required' => false,   ), )` — A font family deleted.
- const `FACE_DELETE` = `array (   'font_family_id' =>    array (     'description' => 'The ID for the parent font family of the font face.',     'type' => 'integer',     'required' => false,   ),   'id' =>    array (     'description' => 'Unique identifier for the font face.',     'type' => 'integer',     'required' => false,   ),   'force' =>    array (     'type' => 'boolean',     'default' => false,     'description' => 'Whether to bypass Trash and force deletion.',     'required' => false,   ), )` — A font face deleted.
- const `COLLECTIONS` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ),   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ), )` — The font collections list.
- const `COLLECTION` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ), )` — One font collection.

Used by: `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontsController`


## FontCollectionsController

`final readonly class Minn\Rest\FontCollectionsController` · `public/minn/src/Minn/Rest/FontCollectionsController.php`

wp/v2/font-collections (probe: the font library routes on the
reference): the collections registered with the font library, in the
order they were registered, a page at a time, and one by its slug. Only
someone who may edit theme options reads them, refused as
rest_cannot_read. A collection shows its slug, then its name,
description, font families and categories as its data holds them, the
same in every context, through rest_prepare_font_collection. The
reference registers a Google Fonts collection read from wordpress.org;
the engine registers none of its own (wp-api/fonts.php), so the list
holds what plugins register.

- const `REFUSAL` = `'Sorry, you are not allowed to access font collections.'`
- const `FIELDS` = `array (   0 => 'name',   1 => 'description',   2 => 'font_families',   3 => 'categories', )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/font-collections (cap edit_theme_options)`

The registered font collections, a page at a time.

### `single(Minn\Http\Request $request, string $slug): Minn\Http\Response`

Route: `GET /wp/v2/font-collections/{slug:[\/\w-]+} (cap edit_theme_options)`

One registered font collection, by its slug.

Internals: `collections()` (private, line 84), `item()` (private, line 96)


## FontFaceRecord

`final class Minn\Rest\FontFaceRecord` · `public/minn/src/Minn/Rest/FontFaceRecord.php` · implements `Minn\Http\Refusal`

A font face route names a font face: refused as an invalid post id
before the caller is judged, signed out or not, when the id (the
query's, else the path's, as the reference reads a parameter) is not a
wp_font_face post. Its family is judged once the caller has been.

Used by: `Minn\Rest\FontFamilyRecord`, `Minn\Rest\FontsController`

### static `refuse(Minn\Http\Request $request): void`

Throws rest_post_invalid_id unless the face exists.

### static `require(Minn\Http\Request $request, string $pattern, string $type): void`

Throws rest_post_invalid_id unless the id the path ends with (or the query's id) is a post of the type.


## FontFamilyRecord

`final class Minn\Rest\FontFamilyRecord` · `public/minn/src/Minn/Rest/FontFamilyRecord.php` · implements `Minn\Http\Refusal`

A font family route names a font family: refused as an invalid post id
before the caller is judged, signed out or not, when the id is not a
wp_font_family post (a post of any other type, a face included).

Used by: `Minn\Rest\FontsController`

### static `refuse(Minn\Http\Request $request): void`

Throws rest_post_invalid_id unless the family exists.


## FontObject

`final readonly class Minn\Rest\FontObject` · `public/minn/src/Minn/Rest/FontObject.php`

A font family and a font face as wp/v2 shapes them (probe: the font
library routes on the reference): the id, the theme.json version (3,
which nothing stores), and the settings the post holds: a family's
name and slug from its title and slug, then its font family and preview
from its JSON content; a face's font-face properties from its JSON
content. A family lists its published and private faces by id, each a
link that embeds; a face links to its family. The
same fields show in every context, and the fields plugin code registers
join through rest_prepare_wp_font_family and rest_prepare_wp_font_face.
Only someone who may edit theme options sees either, and may use every
method on it.

- const `FAMILY_METHODS` = `array (   0 => 'GET',   1 => 'POST',   2 => 'PUT',   3 => 'PATCH',   4 => 'DELETE', )`
- const `FACE_METHODS` = `array (   0 => 'GET',   1 => 'DELETE', )`
- const `FACE_PROPERTIES` = `array (   0 => 'fontFamily',   1 => 'fontStyle',   2 => 'fontWeight',   3 => 'fontDisplay',   4 => 'src',   5 => 'fontStretch',   6 => 'ascentOverride',   7 => 'descentOverride',   8 => 'fontVariant',   9 => 'fontFeatureSettings',   10 => 'fontVariationSettings',   11 => 'lineGapOverride',   12 => 'sizeAdjust',   13 => 'unicodeRange',   14 => 'preview', )`

Used by: `Minn\Rest\Api`, `Minn\Rest\FontsController`

```php
__construct(Minn\Db $db, Minn\Rest\RestUrl $url)
```


### `family(Minn\Content\PostRecord $post): array`

A font family as wp/v2 shapes it, through rest_prepare_wp_font_family. @return array<string, mixed>

- `@return array<string, mixed>`

### `face(Minn\Content\PostRecord $post): array`

A font face as wp/v2 shapes it, through rest_prepare_wp_font_face. @return array<string, mixed>

- `@return array<string, mixed>`

Internals: `faceIds()` (private, line 75), `familySettings()` (private, line 88), `faceSettings()` (private, line 102)


## FontsController

`final readonly class Minn\Rest\FontsController` · `public/minn/src/Minn/Rest/FontsController.php`

wp/v2/font-families and their font-faces, read and deleted (probe: the
font library routes on the reference). Only someone who may edit theme
options reads or deletes either, refused as rest_cannot_read or
rest_cannot_delete; a family or face that does not
exist is refused before the caller is judged, a face's family only
after. A list is the posts list narrowed to its own parameters: the
statuses the caller may read (published and private), newest id first,
the ids, offset, slugs (families only) and pages as the posts list
reads them, through rest_{type}_query; anything else the request
carries is ignored, a search included.

- const `FAMILIES` = `'Sorry, you are not allowed to access font families.'`
- const `FAMILY` = `'Sorry, you are not allowed to access this font family.'`
- const `FACES` = `'Sorry, you are not allowed to access font faces.'`
- const `FACE` = `'Sorry, you are not allowed to access this font face.'`
- const `DELETE` = `'Sorry, you are not allowed to delete this post.'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Posts $posts, Minn\Rest\FontObject $object)
```


### `families(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/font-families (cap edit_theme_options)`

The font families.

### `family(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/font-families/{id:[\d]+} (cap edit_theme_options)`

One font family.

### `faces(Minn\Http\Request $request, string $fontFamilyId): Minn\Http\Response`

Route: `GET /wp/v2/font-families/{font_family_id:[\d]+}/font-faces (cap edit_theme_options)`

A font family's faces.

### `face(Minn\Http\Request $request, string $fontFamilyId, string $id): Minn\Http\Response`

Route: `GET /wp/v2/font-families/{font_family_id:[\d]+}/font-faces/{id:[\d]+} (cap edit_theme_options)`

One font face of a family.

### `deleteFamily(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/font-families/{id:[\d]+} (cap edit_theme_options)`

A font family deleted for good, its faces with it; there is no trash for either.

### `deleteFace(Minn\Http\Request $request, string $fontFamilyId, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/font-families/{font_family_id:[\d]+}/font-faces/{id:[\d]+} (cap edit_theme_options)`

A font face deleted for good, its file with it.

Internals: `delete()` (private, line 106), `list()` (private, line 125), `totals()` (private, line 153), `parent()` (private, line 172), `found()` (private, line 182), `invalidParent()` (private, line 191)


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

Route: `GET /wp/v2/global-styles/{id:[\/\d+]+} (public; global styles {id} must exist)`

The saved styles by id: anyone who edits posts may read them, editing context needs the theme.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/global-styles/{id:[\/\d+]+} (cap edit_theme_options; global styles {id} must exist)`

Route: `PUT /wp/v2/global-styles/{id:[\/\d+]+} (cap edit_theme_options; global styles {id} must exist)`

Route: `PATCH /wp/v2/global-styles/{id:[\/\d+]+} (cap edit_theme_options; global styles {id} must exist)`

Replaces the title, settings, or styles the body names; what it leaves out stays.

### `variations(Minn\Http\Request $request, string $stylesheet): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/themes/{stylesheet:[\/\s%\w\.\(\)\[\]\@_\-]+}/variations (cap edit_posts)`

The style variations the active theme ships; listed ahead of the theme's own route, whose pattern would take this path too.

### `theme(Minn\Http\Request $request, string $stylesheet): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/themes/{stylesheet:[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?} (cap edit_posts)`

The active theme's settings and styles, the engine's defaults underneath.

### `revisions(Minn\Http\Request $request, string $parent): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/{parent:[\d]+}/revisions (cap edit_theme_options; global styles parent {parent} must exist)`

The revisions of the saved styles, newest first; all of them unless per_page pages them, an offset where the page starts (RevisionCollection::paged).

### `revision(Minn\Http\Request $request, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/{parent:[\d]+}/revisions/{id:[\d]+} (cap edit_theme_options; global styles parent {parent} must exist)`

One revision of the saved styles.

Internals: `refuseIllegalCss()` (private, line 84), `revisionObject()` (private, line 132), `post()` (private, line 138), `requireTheme()` (private, line 144), `requireParent()` (private, line 152), `title()` (private, line 165), `node()` (private, line 174)


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
own router rather than the reference's full schema; and the index of one
namespace at /wp-json/{namespace}, the same routes narrowed.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\RouteCatalogue $catalogue, Minn\Rest\Caller $caller)
```


### `index(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET / (public)`

The REST index: namespaces, routes, and the site's description.

### `namespaceIndex(Minn\Http\Request $request, string $namespace): Minn\Http\Response`

Route: `GET /{namespace:[a-z0-9-]+/(?:v\d+|\d+\.\d+)} (public)`

One namespace's index, as the reference answers /wp-json/wp/v2 (or oembed/1.0): the
namespace, its routes (the namespace root among them), and the link
up to the root index. A namespace the engine does not serve is left
to the runtime, whose plugins may own it. The namespace argument the
index lists takes the path's place when it is sent, as every path
argument does on the reference: one naming no namespace the engine
serves is rest_invalid_namespace.

Internals: `imageSettings()` (private, line 72), `themeLink()` (private, line 93)


## InstalledThemesController

`final readonly class Minn\Rest\InstalledThemesController` · `public/minn/src/Minn/Rest/InstalledThemesController.php`

wp/v2/themes as the reference answers it (probe rest-themes): the
installed themes, or only those with a status (active, inactive), each
with its style.css headers raw and rendered, where it lives, and whether
it is a block theme. The active theme also carries its supports as the
editor reads them, the template types and part areas it may define, and
a link to the theme export; any theme with user styles links to them. Anyone who can edit
a type shown in REST may read the active theme; listing the others takes
switch_themes. Each theme goes through rest_prepare_theme. Served with
plugins loaded, since a theme's supports come from its own code.

- const `STATUS` = `array (   'status' =>    array (     'type' => 'array',     'items' =>      array (       'type' => 'string',       'enum' =>        array (         0 => 'active',         1 => 'inactive',       ),     ),     'description' => 'Limit result set to themes assigned one or more statuses.',     'required' => false,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/themes (public)`

The installed themes the caller may see.

### `single(Minn\Http\Request $request, string $stylesheet): Minn\Http\Response`

Route: `GET /wp/v2/themes/{stylesheet:[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?} (public)`

One theme by its stylesheet.

### `fields(Minn\Objects\Theme $theme, Minn\Objects\RestRequest $request): array`

A theme in the wp/v2 shape, its links included, before
rest_prepare_theme (the runtime's WP_REST_Themes_Controller shapes
through here); the request is what the supports' callbacks are handed.

- `@return array<string, mixed>`

Internals: `item()` (private, line 84), `links()` (private, line 131), `viewsThemes()` (private, line 154), `viewsActive()` (private, line 160), `status()` (private, line 165), `requireRuntime()` (private, line 171)


## ItemRender

`final class Minn\Rest\ItemRender` · `public/minn/src/Minn/Rest/ItemRender.php`

What a post read sets up around its rendering, as the reference does for
the one request: an excerpt_length given (declared on a single post,
read on a list too) answers the excerpt_length filter as the integer PHP
reads it as, so the excerpt is cut there; and a protected post read with
its own password, or in the edit context, has post_password_required
lifted for it, so its excerpt is the text and not the password form.
Each is taken down once the object is built, whatever happened.

- const `LENGTH_PRIORITY` = `20` — The priority the request's own excerpt length answers at, after a theme's usual 10.

Used by: `Minn\Rest\PostsController`

### static `around(Closure $render, mixed $length): mixed`

What $render builds, with the request's excerpt length (null when none was given).

- `@param Closure(): T $render`

### static `opened(Closure $render, Minn\Content\PostRecord $post): mixed`

What $render builds with the post's password lifted, for a reader the
reference lets past it (the right password, or the edit context).

- `@param Closure(): T $render`


## Links

`final class Minn\Rest\Links` · `public/minn/src/Minn/Rest/Links.php`

Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along.

Used by: `Minn\Rest\Api`, `Minn\Rest\BlockDirectoryController`, `Minn\Rest\BlockEditorController`, `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontObject`, `Minn\Rest\IndexController`, `Minn\Rest\TemplateRevisionsController`

### static `item(string $rel, string $href, array $attributes): array`

One link as a response serves it: its attributes, then its href; a
self link's target hints after the href, where the reference adds them.

- `@param array<string, mixed> $attributes`
- `@return array<string, mixed>`

### static `expanded(array $links): array`

An answer's compact links as the reference keeps them on a response:
each link under its full relation (a curie's short name expanded,
the curies themselves dropped), with its href and its other
attributes.

- `@param array<string, mixed> $links`
- `@return list<array{0: string, 1: string, 2: array<string, mixed>}>`

### static `compact(array $links, array $curies): array`

Links with their curies applied, as the reference compacts them.

- `@param array<string, mixed> $links rel => items`
- `@param list<array{name: string, href: string, templated?: bool}> $curies`
- `@return array<string, mixed>`


## LiveSettings

`final readonly class Minn\Rest\LiveSettings` · `public/minn/src/Minn/Rest/LiveSettings.php`

wp/v2/settings with plugins loaded, served from the registered settings
as the reference serves it (probe rest-settings): every setting shown in
REST, core's and a plugin's alike, under its REST name. A read asks
rest_pre_get_setting first, then get_option with the schema's default,
and a value its schema refuses reads as null. A write takes the body's
values its schema accepts (null always), offers each to
rest_pre_update_setting, deletes the option for null (refused when what
is stored does not fit the schema, since null could not restore it) and
otherwise hands it to update_option.

Used by: `Minn\Rest\Api`, `Minn\Rest\SettingsController`

```php
__construct(Minn\Rest\Schema $schema)
```


### `shown(): array`

The settings shown in REST, by REST name, with the arguments the filters are handed.

- `@return array<string, array{name: string, schema: array<string, mixed>, option_name: string}>`

### `payload(): array`

Every shown setting's value. @return array<string, mixed>

- `@return array<string, mixed>`

### `store(array $body): void`

Writes the shown settings a body names, after refusing the values their schemas refuse.

Internals: `refuseInvalid()` (private, line 84)


## MediaController

`final readonly class Minn\Rest\MediaController` · `public/minn/src/Minn/Rest/MediaController.php`

wp/v2/media: list, single, upload on both transports (multipart field
"file", or a raw body with Content-Disposition), field edits, and force
delete with the files.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Posts $posts, Minn\Media\Writer $library, Minn\Rest\MediaObject $object, Minn\Rest\Caller $caller, Minn\Rest\PostsController $reads)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:media} (public)`

The media list: the post lists' own path for attachments (probe rest-media-lists), each item in the media shape.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/media/{id:[\d]+} (public; attachment {id} must exist; edit context: cap edit_post on {id})`

One attachment.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/media (cap upload_files)`

Uploads a file and creates its attachment.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/media/{id:[\d]+} (cap edit_post on {id}; attachment {id} must exist)`

Route: `PUT /wp/v2/media/{id:[\d]+} (cap edit_post on {id}; attachment {id} must exist)`

Route: `PATCH /wp/v2/media/{id:[\d]+} (cap edit_post on {id}; attachment {id} must exist)`

The editable fields the app uses.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/media/{id:[\d]+} (cap delete_post on {id}; attachment {id} must exist)`

Attachments go to the trash only when MEDIA_TRASH (or
rest_attachment_trashable) says so; force removes the row, its meta,
and its files.

Internals: `restDate()` (private, line 54), `storeWithPlugins()` (private, line 132), `insertedWithPlugins()` (private, line 159), `preparedAttachment()` (private, line 200), `finishedWithPlugins()` (private, line 224), `params()` (private, line 240), `attachment()` (private, line 321)


## MediaObject

`final readonly class Minn\Rest\MediaObject` · `public/minn/src/Minn/Rest/MediaObject.php`

The wp/v2 media object, view and edit context.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Posts $posts, Minn\Media\Uploads $uploads, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Rest\Types $types)
```


### `build(Minn\Content\PostRecord $p, Minn\Rest\Context $context): array`

An attachment in the context asked for, as rest_block_hooks_post_types
has it (attachments only when a plugin lists them), through
rest_prepare_attachment when a plugin hooks it.

### `url(): Minn\Rest\RestUrl`

The REST URL builder.

### `links(Minn\Content\PostRecord $p, Minn\Rest\Context $context): array`

The attachment's links in a context: itself (with the methods the
caller may use), its collection and type, its author, replies, the
post it is attached to, and in the edit context the actions the
caller may take; curies when any rel is the API's own.

- `@return array<string, list<array<string, mixed>>>`

Internals: `buildFields()` (private, line 46), `details()` (private, line 166)


## MenuCollectionParams

`final class Minn\Rest\MenuCollectionParams` · `public/minn/src/Minn/Rest/MenuCollectionParams.php` · implements `Minn\Http\RouteParams`

The menus list's parameters as the reference declares them: a flat
taxonomy's term list (the context and Args::TERMS, an offset and no
parent). Plugins change them through rest_nav_menu_collection_params
when the list runs (Rest\MenuLists).

Used by: `Minn\Rest\MenuLists`, `Minn\Rest\MenusController`

### static `for(array $captures): array`

The menus list's parameters; the route has no captures to read.


## MenuItemCollectionParams

`final class Minn\Rest\MenuItemCollectionParams` · `public/minn/src/Minn/Rest/MenuItemCollectionParams.php` · implements `Minn\Http\RouteParams`

The menu items list's parameters as the reference declares them: the
nav_menu_item type's post list (its menus term filter among them),
a hundred to a page in ascending menu order by default, menu_order
among the orderby values and, last, a menu_order filter of its own.

- const `TYPE` = `'nav_menu_item'`

Used by: `Minn\Rest\MenuLists`, `Minn\Rest\MenusController`

### static `for(array $captures): array`

The menu items list's parameters, as the index publishes them and the router judges them.

### static `listed(): array`

The parameters asked afresh, as the list asks for them each time it
runs: rest_nav_menu_item_collection_params runs again.

- `@return array<string, array<string, mixed>>`

Internals: `adjusted()` (private, line 37)


## MenuItemObject

`final readonly class Minn\Rest\MenuItemObject` · `public/minn/src/Minn/Rest/MenuItemObject.php`

The wp/v2/menu-items resource.

- const `TYPE_LABEL` = `array (   'page' => 'Page',   'post' => 'Post',   'category' => 'Category',   'post_tag' => 'Tag',   'custom' => 'Custom Link', )`

Used by: `Minn\Rest\Api`, `Minn\Rest\MenuLists`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `view(Minn\Content\MenuItem $item, Minn\Rest\Context $context): array`

The wp/v2 menu-item shape, through rest_prepare_nav_menu_item when a plugin hooks it.


## MenuItemStatus

`final class Minn\Rest\MenuItemStatus` · `public/minn/src/Minn/Rest/MenuItemStatus.php` · implements `Minn\Http\Refusal`

Menu items listed with a status beyond publish by a caller who may not
edit them: refused as the parameter it is ("Status is forbidden."),
before the caller is judged for the list or its edit context, as the
reference refuses it, signed out too (register 18300).

Used by: `Minn\Rest\MenusController`

### static `refuse(Minn\Http\Request $request): void`

Throws the status refusal, 401 inside for a signed-out caller and 403 for one signed in.


## MenuLists

`final readonly class Minn\Rest\MenuLists` · `public/minn/src/Minn/Rest/MenuLists.php`

The menus and menu items lists, served as the reference serves them: the
menus as the nav_menu taxonomy's term list (its parameters through
rest_nav_menu_collection_params, its query through rest_nav_menu_query,
counted without the page), the items as the nav_menu_item type's post
list with its own parameters (Rest\MenuItemCollectionParams), each in
its own shape.

- const `TAXONOMY` = `'nav_menu'`
- const `ITEM_TYPE` = `'nav_menu_item'`

Used by: `Minn\Rest\Api`, `Minn\Rest\MenusController`

```php
__construct(Minn\Rest\PostsController $posts, Minn\Content\Menus $menus, Minn\Rest\MenuObject $menuObject, Minn\Rest\MenuItemObject $itemObject, Minn\Rest\Caller $caller)
```

- readonly `Minn\Content\Menus $menus`
- readonly `Minn\Rest\MenuObject $menuObject`
- readonly `Minn\Rest\MenuItemObject $itemObject`
- readonly `Minn\Rest\Caller $caller`

### `menus(Minn\Http\Request $request): Minn\Http\Response`

The menus: edit context for one who may edit menus, a post's menus
only for a menu item the caller may read (an unknown post is a bad
parameter), then get_terms, or the post's own terms, as asked.

### `items(Minn\Http\Request $request): Minn\Http\Response`

The menu items, as the post list of their type: every list parameter honoured, each item in its own shape.

Internals: `termsReadable()` (private, line 102)


## MenuObject

`final readonly class Minn\Rest\MenuObject` · `public/minn/src/Minn/Rest/MenuObject.php`

The wp/v2/menus resource: a nav_menu term plus locations and auto_add.

Used by: `Minn\Rest\Api`, `Minn\Rest\MenuLists`

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

- const `NEW_ITEM` = `array (   'menu-id' => 0,   'menu-item-db-id' => 0,   'menu-item-object-id' => 0,   'menu-item-object' => '',   'menu-item-parent-id' => 0,   'menu-item-position' => 1,   'menu-item-type' => 'custom',   'menu-item-title' => '',   'menu-item-url' => '',   'menu-item-description' => '',   'menu-item-attr-title' => '',   'menu-item-target' => '',   'menu-item-classes' => '',   'menu-item-xfn' => '',   'menu-item-status' => 'publish', )` — The arguments a new item starts from, as the reference's REST controller prepares them.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\MenuLists $lists)
```


### `listMenus(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/menus (cap edit_posts; or anyone, when rest_menu_read_access opens it; edit context: cap edit_terms)`

Every classic menu, as the nav_menu taxonomy's term list (Rest\MenuLists).

### `singleMenu(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/menus/{id:[\d]+} (cap edit_posts; menu {id} must exist; or anyone, when rest_menu_read_access opens it; edit context: cap edit_term on {id})`

One menu.

### `createMenu(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/menus (cap edit_theme_options)`

Creates a menu.

### `updateMenu(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/menus/{id:[\d]+} (cap edit_theme_options; menu {id} must exist)`

Route: `PUT /wp/v2/menus/{id:[\d]+} (cap edit_theme_options; menu {id} must exist)`

Route: `PATCH /wp/v2/menus/{id:[\d]+} (cap edit_theme_options; menu {id} must exist)`

Renames or re-describes a menu.

### `deleteMenu(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/menus/{id:[\d]+} (cap edit_theme_options; menu {id} must exist)`

Deletes a menu and its items.

### `listItems(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/menu-items (cap edit_posts; or anyone, when rest_menu_read_access opens it; edit context: cap edit_posts)`

The menu items, as their type's post list (Rest\MenuLists).

### `singleItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/menu-items/{id:[\d]+} (cap edit_posts; menu item {id} must exist; or anyone, when rest_menu_read_access opens it; edit context: cap edit_post on {id})`

One menu item.

### `createItem(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/menu-items (cap edit_theme_options)`

Creates a menu item.

### `updateItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/menu-items/{id:[\d]+} (cap edit_theme_options; menu item {id} must exist)`

Route: `PUT /wp/v2/menu-items/{id:[\d]+} (cap edit_theme_options; menu item {id} must exist)`

Route: `PATCH /wp/v2/menu-items/{id:[\d]+} (cap edit_theme_options; menu item {id} must exist)`

Updates a menu item.

### `deleteItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/menu-items/{id:[\d]+} (cap edit_theme_options; menu item {id} must exist)`

Deletes a menu item.

### `locations(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/menu-locations (cap edit_theme_options; or anyone, when rest_menu_read_access opens it)`

The menu locations the theme and plugins register (none for a block theme without plugins), keyed by name; edit_theme_options to view.

### `oneLocation(Minn\Http\Request $request, string $location): Minn\Http\Response`

Route: `GET /wp/v2/menu-locations/{location:[\w-]+} (cap edit_theme_options; or anyone, when rest_menu_read_access opens it)`

One menu location.

Internals: `locationItem()` (private, line 207), `argsFrom()` (private, line 231), `titleFrom()` (private, line 254), `urlFrom()` (private, line 264), `refuse()` (private, line 274), `plain()` (private, line 284)


## NavigationController

`final readonly class Minn\Rest\NavigationController` · `public/minn/src/Minn/Rest/NavigationController.php`

wp/v2/navigation: the block theme's navigation menus, stored as
wp_navigation posts. The reading and writing is the posts machinery with
this type's own capabilities (every one of them edit_theme_options) and
the posts list's parameters, so only the routes live here.

- const `TYPE` = `'wp_navigation'`
- const `BASE` = `'navigation'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\PostsController $reads, Minn\Rest\PostsWriteController $writes)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/{base:navigation} (public)`

The navigation posts.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/navigation/{id:[\d]+} (public; navigation {id} must exist)`

One navigation post.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/navigation`

Creates a navigation post.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/navigation/{id:[\d]+} (cap edit_post on {id}; navigation {id} must exist)`

Route: `PUT /wp/v2/navigation/{id:[\d]+} (cap edit_post on {id}; navigation {id} must exist)`

Route: `PATCH /wp/v2/navigation/{id:[\d]+} (cap edit_post on {id}; navigation {id} must exist)`

Updates a navigation post.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/navigation/{id:[\d]+} (cap delete_post on {id}; navigation {id} must exist)`

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

Internals: `refusal()` (private, line 82)


## PathArgs

`final class Minn\Rest\PathArgs` · `public/minn/src/Minn/Rest/PathArgs.php`

The arguments a route's path names, as the reference lists them among the
route's args: each capture again, described and typed. Declared, they are
judged like any other argument, and a value of the same name in the query
(or a write's body) takes the capture's place before the caller is judged
(Http\Router), as on the reference: /wp/v2/users/1?id=abc is refused as an
invalid id, and /wp/v2/users/1?id=2 answers user 2. A route lists one only
where the reference lists it (a widget's GET names no id), and in the
reference's order, which a refusal's list follows.

- const `USER_ID` = `array (   'id' =>    array (     'description' => 'Unique identifier for the user.',     'type' => 'integer',     'required' => false,   ), )` — A user's id: wp/v2/users/{id}.
- const `POST_ID` = `array (   'id' =>    array (     'description' => 'Unique identifier for the post.',     'type' => 'integer',     'required' => false,   ), )` — A post's id: wp/v2/{type base}/{id}, media, blocks, navigation, menu items.
- const `COMMENT_ID` = `array (   'id' =>    array (     'description' => 'Unique identifier for the comment.',     'type' => 'integer',     'required' => false,   ), )` — A comment's id: wp/v2/comments/{id}.
- const `GLOBAL_STYLES_ID` = `array (   'id' =>    array (     'description' => 'ID of global styles config.',     'type' => 'integer',     'required' => false,   ), )` — A global styles config's id: wp/v2/global-styles/{id}, read (its writes name none).
- const `TERM_ID` = `array (   'id' =>    array (     'description' => 'Unique identifier for the term.',     'type' => 'integer',     'required' => false,   ), )` — A term's id: wp/v2/{taxonomy base}/{id}, and a menu's.
- const `STATUS` = `array (   'status' =>    array (     'description' => 'An alphanumeric identifier for the status.',     'type' => 'string',     'required' => false,   ), )` — A post status's name: wp/v2/statuses/{status}.
- const `RENDERED_BLOCK` = `array (   'name' =>    array (     'description' => 'Unique registered name for the block.',     'type' => 'string',     'required' => false,   ), )` — The block a render asks for: wp/v2/block-renderer/{name}.
- const `BLOCK_NAMESPACE` = `array (   'namespace' =>    array (     'description' => 'Block namespace.',     'type' => 'string',     'required' => false,   ), )` — A block type's namespace: wp/v2/block-types/{namespace}.
- const `BLOCK_NAME` = `array (   'name' =>    array (     'description' => 'Block name.',     'type' => 'string',     'required' => false,   ), )` — A block type's name within its namespace: wp/v2/block-types/{namespace}/{name}.
- const `STYLESHEET` = `array (   'stylesheet' =>    array (     'description' => 'The theme\'s stylesheet. This uniquely identifies the theme.',     'type' => 'string',     'required' => false,   ), )` — A theme's stylesheet: wp/v2/themes/{stylesheet}.
- const `PLUGIN` = `array (   'plugin' =>    array (     'type' => 'string',     'pattern' => '[^.\\/]+(?:\\/[^.\\/]+)?',     'required' => false,     'bare_refusal' => true,   ), )` — A plugin's file without its .php: wp/v2/plugins/{plugin}. The reference
gives it no description and judges it with a check of its own, whose
refusal is the bare "Invalid parameter.".
- const `SIDEBAR_ID` = `array (   'id' =>    array (     'description' => 'The id of a registered sidebar',     'type' => 'string',     'required' => false,   ), )` — A registered sidebar's id: wp/v2/sidebars/{id}.
- const `WIDGET_TYPE_ID` = `array (   'id' =>    array (     'description' => 'The widget type id.',     'type' => 'string',     'required' => false,   ), )` — A widget type's id: wp/v2/widget-types/{id}.

Used by: `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\DeclaredTermsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`


## PatternDirectoryController

`final readonly class Minn\Rest\PatternDirectoryController` · `public/minn/src/Minn/Rest/PatternDirectoryController.php`

wp/v2/pattern-directory/patterns (Austin, 2026-10-21, decision 1B): the
block editor's pattern directory, asked of wordpress.org through the Minn
update service, with the query the reference sends upstream (its paging
and order, then the search, slugs and offset asked for, the reader's
locale, the WordPress version, then the category and keyword by their
upstream names). An answer is kept an hour in the site transient the
reference keeps it in, named for that query, so either stack reads the
other's. Anyone who may edit posts browses it. Each pattern shows its
title as the directory renders it, its markup, its category and keyword
slugs, its description, viewport width and block types.

- const `REFUSAL` = `'Sorry, you are not allowed to browse the local block pattern directory.'`
- const `ARGS` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ),   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 100,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'minLength' => 1,     'required' => false,   ),   'category' =>    array (     'description' => 'Limit results to those matching a category ID.',     'type' => 'integer',     'minimum' => 1,     'required' => false,   ),   'keyword' =>    array (     'description' => 'Limit results to those matching a keyword ID.',     'type' => 'integer',     'minimum' => 1,     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit results to those matching a pattern (slug).',     'type' => 'array',     'required' => false,   ),   'offset' =>    array (     'description' => 'Offset the result set by a specific number of items.',     'type' => 'integer',     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'date',     'enum' =>      array (       0 => 'author',       1 => 'date',       2 => 'id',       3 => 'include',       4 => 'modified',       5 => 'parent',       6 => 'relevance',       7 => 'slug',       8 => 'include_slugs',       9 => 'title',       10 => 'favorite_count',     ),     'required' => false,   ), )`
- const `FAILED` = `'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="https://wordpress.org/support/forums/">support forums</a>.'`
- const `KEEP` = `3600` — How long an answer is kept, as the reference keeps it.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Http $http = \Minn\Http::__set_state(array(    'within' =>    array (   ),    'fake' => NULL,    'refusal' => NULL, )))
```


### `patterns(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/pattern-directory/patterns (cap edit_posts)`

The directory's patterns for a query, a page at a time.

Internals: `upstreamQuery()` (private, line 83), `fetch()` (private, line 119), `item()` (private, line 138)


## PluginsController

`final readonly class Minn\Rest\PluginsController` · `public/minn/src/Minn/Rest/PluginsController.php`

wp/v2 plugins: what sits in wp-content/plugins, in the reference's
shape. Minn extensions (a minn.json manifest) are listed alongside the
WordPress plugins the folder still holds; the engine runs the former
and only records the latter's stored state. Activation writes the same
options the reference and the extension loader read; files are never
installed or deleted from here.

- const `LIST_ARGS` = `array (   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'status' =>    array (     'description' => 'Limits results to plugins with the given status.',     'type' => 'array',     'items' =>      array (       'type' => 'string',       'enum' =>        array (         0 => 'inactive',         1 => 'active',       ),     ),     'required' => false,   ), )` — Read by list(): the search and the statuses it narrows to.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Extension\Loader $extensions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Ops\Packages $packages)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/plugins (cap activate_plugins)`

The plugins list, narrowed by status and by a search when one is asked.

### `install(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/plugins (cap activate_plugins)`

Installs a directory plugin by slug, optionally activating it; answers 201 with the item.

### `single(Minn\Http\Request $request, string $plugin): Minn\Http\Response`

Route: `GET /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

One plugin; the refusal is named for the one, as on the reference.

### `update(Minn\Http\Request $request, string $plugin): Minn\Http\Response`

Route: `PUT /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Route: `POST /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Route: `PATCH /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Activates or deactivates a plugin.

### `delete(Minn\Http\Request $request, string $plugin): Minn\Http\Response`

Route: `DELETE /wp/v2/plugins/{plugin:[^.\/]+(?:\/[^.\/]+)?} (cap activate_plugins)`

Deletes an inactive plugin.

### static `shape(string $relative, string $path, array $headers, string $status): array`

A WordPress plugin's item from its headers, as the reference's
controller shapes one (the runtime's WP_REST_Plugins_Controller
shapes through here too): the headers translated and cut to the
allowed markup once for the fields and once more, marked up, for the
rendered description.

- `@param array<string, mixed> $headers the plugin's headers, as get_plugins() holds them`
- `@return array<string, mixed>`

Internals: `mentions()` (private, line 79), `items()` (private, line 188), `find()` (private, line 209), `statusOf()` (private, line 223), `manifestFor()` (private, line 235), `extensionItem()` (private, line 245), `pluginItem()` (private, line 273), `links()` (private, line 307), `extensionKey()` (private, line 312), `refuse()` (private, line 319)


## PolicyGate

`final readonly class Minn\Rest\PolicyGate` · `public/minn/src/Minn/Rest/PolicyGate.php`

Judges a route's policy against the caller, with the reference's
refusals: a caller who is not signed in gets the policy's sign-in code
at 401, a bad nonce is always 403 rest_cookie_invalid_nonce, and a
signed-in caller who lacks a capability gets the refusal code at 403.
A subject the policy names is looked up first and answers its 404
before any of those. The edit-context policy, when the request asks
for it, is judged before the route's own, as the reference refuses the
context first (the menus).

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Caller $caller, Minn\Rest\Subjects $subjects, Minn\Rest\Types $types)
```


### `closure(): Closure`

The judge as the router takes it.

### `judge(Minn\Http\Policy $policy, Minn\Http\Request $request, array $captures): void`

Throws the refusal the policy names, or returns.

- `@param array<string, string> $captures`

Internals: `subject()` (private, line 76), `type()` (private, line 96), `taxonomy()` (private, line 127), `opened()` (private, line 159), `capabilities()` (private, line 165), `registered()` (private, line 179), `own()` (private, line 189)


## PostCollectionParams

`final class Minn\Rest\PostCollectionParams` · `public/minn/src/Minn/Rest/PostCollectionParams.php` · implements `Minn\Http\RouteParams`

A post type's collection parameters as the reference builds them for its
list (probe rest-post-lists): the shared four, dates, authors when the
type supports them, ids, menu order and its orderby for page attributes,
parents for a hierarchical type, search columns and semantics, slugs,
statuses (every registered one, and any), each REST taxonomy's term
filter (a list, or a query with children for a hierarchical one and an
operator for the include side) under a relation, stickies for posts, and
formats for a type with post formats. Plugins change them through
rest_{type}_collection_params, asked once a request.

- const `FORMATS` = `array (   0 => 'standard',   1 => 'aside',   2 => 'chat',   3 => 'gallery',   4 => 'link',   5 => 'image',   6 => 'quote',   7 => 'status',   8 => 'video',   9 => 'audio', )`
- const `STATUSES` = `array (   0 => 'publish',   1 => 'future',   2 => 'draft',   3 => 'pending',   4 => 'private',   5 => 'trash',   6 => 'auto-draft',   7 => 'inherit',   8 => 'request-pending',   9 => 'request-confirmed',   10 => 'request-failed',   11 => 'request-completed', )`
- const `IDS` = `array (   'type' => 'array',   'items' =>    array (     'type' => 'integer',   ),   'default' =>    array (   ), )`

Used by: `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\MediaController`, `Minn\Rest\MenuItemCollectionParams`, `Minn\Rest\NavigationController`, `Minn\Rest\PostListArgs`, `Minn\Rest\PostsController`

### static `for(array $captures): array`

The parameters of the list a {base} capture names; none for a base no REST post type has.

### static `ofType(string $name): array`

The parameters of a post type's list, by the type's name. @return array<string, array<string, mixed>>

- `@return array<string, array<string, mixed>>`

### static `listed(string $name): array`

A post type's list parameters asked afresh, as the reference's list
asks for them each time it runs: rest_{type}_collection_params runs
again.

- `@return array<string, array<string, mixed>>`

### static `built(Minn\Objects\PostType $object): array`

A post type's list parameters as built, for the type in any
namespace: before rest_{type}_collection_params and the optional
marks, for the facade's controller class, which adds its callbacks
and runs the filter itself.

- `@return array<string, array<string, mixed>>`

### static `mediaTypes(): array`

The allowed MIME types by media type (the part before the slash), in
the order the site allows them, each once.

- `@return array<string, list<string>>`

Internals: `named()` (private, line 63), `forType()` (private, line 73), `filtered()` (private, line 91), `type()` (private, line 110), `described()` (private, line 141), `build()` (private, line 153), `statuses()` (private, line 228), `taxonomies()` (private, line 239), `termFilter()` (private, line 255)


## PostListArgs

`final class Minn\Rest\PostListArgs` · `public/minn/src/Minn/Rest/PostListArgs.php`

The WP_Query arguments a post list request makes, as the reference makes
them before rest_{type}_query (probe rest-post-lists): each registered
parameter the request carries under its query name, the date bounds,
per_page, stickies (only them, or none of them), an exact search, each
taxonomy's include and exclude under the request's relation, formats
(standard as no format at all), the type, and ids alone for a HEAD
request. Then each through rest_query_var-{name}, the orderby names
mapped to the query's, and stickies left where they fall unless asked.

- const `MAPPINGS` = `array (   'author' => 'author__in',   'author_exclude' => 'author__not_in',   'exclude' => 'post__not_in',   'include' => 'post__in',   'ignore_sticky' => 'ignore_sticky_posts',   'menu_order' => 'menu_order',   'offset' => 'offset',   'order' => 'order',   'orderby' => 'orderby',   'page' => 'paged',   'parent' => 'post_parent__in',   'parent_exclude' => 'post_parent__not_in',   'search' => 's',   'search_columns' => 'search_columns',   'slug' => 'post_name__in',   'status' => 'post_status', )`
- const `DATES` = `array (   0 =>    array (     0 => 'before',     1 => 'before',     2 => 'post_date',   ),   1 =>    array (     0 => 'modified_before',     1 => 'before',     2 => 'post_modified',   ),   2 =>    array (     0 => 'after',     1 => 'after',     2 => 'post_date',   ),   3 =>    array (     0 => 'modified_after',     1 => 'after',     2 => 'post_modified',   ), )`
- const `ORDERBY` = `array (   'id' => 'ID',   'include' => 'post__in',   'slug' => 'post_name',   'include_slugs' => 'post_name__in', )`
- const `IN` = `'post__in'`
- const `NOT_IN` = `'post__not_in'`

Used by: `Minn\Rest\FontsController`, `Minn\Rest\PostsController`

### static `of(Minn\Objects\RestRequest $request, array $registered, string $type): array`

The arguments before plugins see them.

- `@param array<string, mixed> $registered the list's parameters`
- `@return array<string, mixed>`

### static `queryVars(array $args, Minn\Objects\RestRequest $request): array`

The query variables from the arguments plugins left: each through
rest_query_var-{name}, and the list's orderby names as the query's.

- `@param array<string, mixed> $args`
- `@return array<string, mixed>`

Internals: `mimeTypes()` (private, line 102), `sticky()` (private, line 113), `taxonomies()` (private, line 127), `termClause()` (private, line 148), `formats()` (private, line 172)


## PostObject

`final readonly class Minn\Rest\PostObject` · `public/minn/src/Minn/Rest/PostObject.php`

Builds the wp/v2 post and page objects in the reference's shape: the
view context, the edit context (raw+rendered dual fields, editor-only
fields, and cap-gated wp:action-* links), and their _links blocks.

- const `NAVIGATION` = `'wp_navigation'` — The one post type whose REST shape is not post-shaped.
- const `BLOCK` = `'wp_block'` — The other: a pattern carries its content raw, its category, and its sync status.

Used by: `Minn\Rest\Api`, `Minn\Rest\CommentObject`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\RevisionsController`, `Minn\Rest\Services`, `Minn\Rest\UserObject`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `view(Minn\Content\PostRecord $p, ?Minn\Rest\Fields $fields = NULL): array`

The view-context object, through rest_prepare_{type} when a plugin
hooks it; with a _fields selection, the rendered title, content and
excerpt only when it asks for them, as the reference renders only
what is asked.

### `embed(Minn\Content\PostRecord $p, ?Minn\Rest\Fields $fields = NULL): array`

The embed-context object before Embed cuts it to the kind's keys, with the nested halves its type's embed shows (Embed::halves).

### `viewWithPassword(Minn\Content\PostRecord $p, mixed $password, ?Minn\Rest\Fields $fields = NULL): array`

The view-context object for a post read with a password: a protected
post's content and excerpt rendered when the password is its own
(still marked protected), as the reference's can_access_password_content
allows; otherwise the plain view.

### `edit(Minn\Content\PostRecord $p, int $userId, ?Minn\Rest\Fields $fields = NULL): array`

The edit-context object, its raw content given its hooked blocks,
through rest_prepare_{type} when a plugin hooks it; with a _fields
selection, rendered only as far as it asks.

### `permalink(Minn\Content\PostRecord $p): string`

A post's permalink as of now: pretty once live with a slug, ?p= (or ?page_id=) before.

### static `restBase(string $type): string`

The rest_base of a type.

### `links(Minn\Content\PostRecord $p): array`

The _links of a post in the view context.

### `modifiedUnsaved(Minn\Content\PostRecord $p, int $userId): bool`

Whether a live post carries an autosave newer than its saved revision.

### `lockHolder(int $id, int $userId): ?array`

The OTHER user holding a live _edit_lock (150 second window), or null.

### `editLinks(Minn\Content\PostRecord $p, int $userId, ?array $viewLinks = NULL): array`

The view links plus the caller's verbs and cap-gated wp:action-* entries.

### static `date(string $mysql): string`

A MySQL datetime in the reference's ISO form.

Internals: `meta()` (private, line 114), `navigationView()` (private, line 125), `blockView()` (private, line 155), `viewFields()` (private, line 187), `viewTerms()` (private, line 242), `typeFields()` (private, line 258), `classList()` (private, line 287), `format()` (private, line 309), `termLinks()` (private, line 353), `editFields()` (private, line 372), `allow()` (private, line 555), `gmt()` (private, line 574)


## PostsController

`final readonly class Minn\Rest\PostsController` · `public/minn/src/Minn/Rest/PostsController.php`

wp/v2 posts and pages, read side.

Used by: `Minn\Rest\Api`, `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\MediaController`, `Minn\Rest\MenuItemStatus`, `Minn\Rest\MenuLists`, `Minn\Rest\NavigationController`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Rest\PostObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts} (public)`

Route: `GET /wp/v2/{base:pages} (public)`

The posts or pages list.

### `serveList(Minn\Http\Request $request, string $type, ?Closure $shape = NULL, ?Closure $readable = NULL, ?array $registered = NULL): Minn\Http\Response`

The list for any post type as the reference serves it (probe
rest-post-lists): the caller and the statuses judged, the request's
parameters sanitized, the query arguments through rest_{type}_query
and rest_query_var-*, a WP_Query (so pre_get_posts and every query
filter run), then each post the caller may read (or edit, in the edit
context) shaped (by $shape when the type has its own object, as
media does). The totals are the query's, counted again without the
page when a later page came back empty. A media search also matches
file names, as the reference's does. A type whose posts are read by
a rule of its own names it ($readable, by post id: a pattern needs
read_post, published or not), its rows still counted; a type whose
list takes parameters of its own names them ($registered, as menu
items do); a shape that answers null leaves the post out.

- `@param (\Closure(PostRecord, Context): ?array<string, mixed>)|null $shape`
- `@param (\Closure(int): bool)|null $readable`
- `@param array<string, array<string, mixed>>|null $registered`

### static `requestedStatuses(Minn\Http\Request $request): ?array`

The statuses a list request names, null when it names none: a list
given as one (status[]=draft), its members that are not plain values
dropped, or a comma-separated string.

- `@return list<string>|null`

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages}/{id:[\d]+} (public; post {id} must exist; edit context: cap edit_post on {id})`

One post or page.

### `serveSingle(Minn\Http\Request $request, string $type, string $id, ?Closure $readable = NULL): Minn\Http\Response`

One post of any type, with the reference's read rules: the edit
context for one who may edit it, then the password (any given that is
not the post's own is refused, protected or not, before the post's
readability is judged), then the read itself, by the type's own rule
when it has one ($readable, by post id) and otherwise a published post
or one the caller may read. The excerpt is cut to the excerpt_length
asked for, and the right password opens the content and the excerpt.

- `@param (\Closure(int): bool)|null $readable`

### static `withAlternate(Minn\Http\Response $response, Minn\Content\PostRecord $post): Minn\Http\Response`

A viewable type's single post points at its page on the site.

Internals: `shaped()` (private, line 112), `totals()` (private, line 141), `readable()` (private, line 160), `visibleStatuses()` (private, line 186)


## PostsWriteController

`final readonly class Minn\Rest\PostsWriteController` · `public/minn/src/Minn/Rest/PostsWriteController.php`

wp/v2 posts and pages, write side: create, update, trash, and force
delete, each gated by the capability engine and answering with the
edit-context object the reference returns.

- const `LIVE` = `array (   0 => 'publish',   1 => 'future',   2 => 'private', )` — The statuses that make a post live: they need the publish capability and give the post its slug.
- const `FIELD_SUPPORTS` = `array (   'title' => 'title',   'content' => 'editor',   'excerpt' => 'excerpt', )` — What a plugin's type must support for its posts to take a title, content or excerpt: a field its schema lacks is not saved (probe rest-plugin-types).

Used by: `Minn\Rest\Api`, `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\MediaController`, `Minn\Rest\NavigationController`, `Minn\Rest\RevisionsController`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\PostWriter $writer, Minn\Content\Site $site, Minn\Rest\PostObject $object, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts} (cap edit_posts)`

Route: `POST /wp/v2/{base:pages} (cap edit_pages)`

Creates a post or page; one asked for with an id is refused before the caller is judged (patch-security).

### `serveCreate(Minn\Http\Request $request, string $type, string $base): Minn\Http\Response`

Creates a post of any type from the body.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts|pages}/{id:[\d]+} (cap edit_post on {id}; post {id} must exist)`

Route: `PUT /wp/v2/{base:posts|pages}/{id:[\d]+} (cap edit_post on {id}; post {id} must exist)`

Route: `PATCH /wp/v2/{base:posts|pages}/{id:[\d]+} (cap edit_post on {id}; post {id} must exist)`

Updates a post or page.

### `serveUpdate(Minn\Http\Request $request, string $type, string $id): Minn\Http\Response`

Updates a post of any type from the body.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:posts|pages}/{id:[\d]+} (cap delete_post on {id}; post {id} must exist)`

Trashes or deletes a post or page.

### `serveDelete(Minn\Http\Request $request, string $type, string $id): Minn\Http\Response`

Trashes a post of any type, or deletes it with force.

### static `checkMeta(array $body): void`

A body's meta must be an object of keys (probe rest-meta). @param array<string, mixed> $body

- `@param array<string, mixed> $body`

### static `field(mixed $value): string`

A field that may arrive as a scalar or as {raw: ...}.

Internals: `takes()` (private, line 146), `hasParent()` (private, line 152), `hasOrder()` (private, line 158), `events()` (private, line 164), `newColumns()` (private, line 175), `writeNewPost()` (private, line 209), `checkAssignTerms()` (private, line 238), `rememberOld()` (private, line 346), `floatingDate()` (private, line 360), `scheduledIfFuture()` (private, line 374), `askedDate()` (private, line 396), `updatedDate()` (private, line 414), `fieldColumns()` (private, line 427), `statusColumns()` (private, line 471), `checkStickyChange()` (private, line 507), `checkStickyPasswordConflict()` (private, line 519), `validStatus()` (private, line 539), `clean()` (private, line 548)


## RegisteredFields

`final class Minn\Rest\RegisteredFields` · `public/minn/src/Minn/Rest/RegisteredFields.php`

The fields plugin code adds to an object type with register_rest_field,
as the reference serves them (probe rest-fields). A get callback's value
follows the item's own fields, before its links. It is handed the item
so far (without links, cut to the fields asked for, id kept), the
field's name, the request and the object type. A field is left out when
_fields does not name it or its schema's context does not include the
request's. A write runs the update callbacks for the fields its body
names, with the saved object, before rest_after_insert; an error one
returns is the write's answer.

Used by: `Minn\Rest\Embed`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimePrepare`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\PostEvents`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`

### static `add(array $item, string $type, Minn\Objects\RestRequest $request): array`

The item with the type's registered fields added, answering the request. @param array<string, mixed> $item @return array<string, mixed>

- `@param array<string, mixed> $item @return array<string, mixed>`

### static `update(object $object, string $type, Minn\Objects\RestRequest $request): void`

Runs the update callbacks for the fields the request's body names; an error one returns is thrown as the write's answer.

### static `context(Minn\Objects\RestRequest $request, string $type): string`

The context the item is answered in, set on the request as the
reference's controllers set it: a write and a delete answer in edit
(a term's delete in view); a read in the one asked for, or view.

### static `shownIn(string $type, string $context): array`

The names of the fields registered for the type that show in the context. @return list<string>

- `@return list<string>`

Internals: `of()` (private, line 97), `wanted()` (private, line 104), `inContext()` (private, line 114), `refusal()` (private, line 120)


## RegisteredPostFields

`final readonly class Minn\Rest\RegisteredPostFields` · `public/minn/src/Minn/Rest/RegisteredPostFields.php`

The REST object of a post whose type plugin code registered (probe rest-plugin-types), built by what the type supports.

Used by: `Minn\Rest\PostObject`

```php
__construct(Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url)
```


### `view(Minn\Content\PostRecord $p, Minn\Rest\RegisteredType $type, Closure $allow, Closure $gmt): array`

A plugin's type in the view context (probe rest-plugin-types): the
shared fields, then only what the type supports (title, editor,
excerpt, author, thumbnail, page attributes, comments, formats, custom
fields), its parent when hierarchical, each REST taxonomy's term ids
under its REST base, the class list and links.

- `@param Closure(int): list<string> $allow the methods the caller may use on a post`
- `@param Closure(string, string): string $gmt a GMT date as the object writes it`
- `@return array<string, mixed>`

Internals: `links()` (private, line 80)


## RegisteredType

`final readonly class Minn\Rest\RegisteredType` · `public/minn/src/Minn/Rest/RegisteredType.php`

A post type plugin code registered, as its REST object follows it
(probe rest-plugin-types): what it supports, whether it is hierarchical,
and the taxonomies it shows in REST under their REST bases. Only once the
runtime has loaded the plugins that register it.

Used by: `Minn\Rest\PostObject`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredPostFields`, `Minn\Runtime\PostEvents`

- readonly `string $name`

### static `of(string $type): ?self`

The type when plugin code registered it (not a built-in), or null.

### `supports(string $feature): bool`

Whether the type supports the feature (title, editor, author...).

### `hierarchical(): bool`

Whether the type's posts have parents.

### `base(): string`

The REST base the type's routes live under, with its namespace.

### `taxonomies(): array`

The taxonomies the type shows in REST, REST base => taxonomy, in registration order. @return array<string, string>

- `@return array<string, string>`


## RenderedFields

`final class Minn\Rest\RenderedFields` · `public/minn/src/Minn/Rest/RenderedFields.php`

A post's rendered title, content and excerpt as a REST response carries
them. Without plugins they are Minn's own render. With plugins loaded
they pass the filters the reference's controller runs, with the post set
up as it sets it up: the_title over the stored title, the engine's
render of the content then the rest of the_content (shortcodes and every
plugin's callback), and get_the_excerpt then the_excerpt.

Used by: `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\RegisteredPostFields`

### static `title(Minn\Content\PostRecord $p): string`

The rendered title.

### static `content(Minn\Content\PostRecord $p): string`

The rendered content.

### static `excerpt(Minn\Content\PostRecord $p): string`

The rendered excerpt.

### static `setUp(Minn\Content\PostRecord $p): void`

The post made current and set up for a response that renders none of its text, as the reference sets up every item it prepares.

### static `classes(array $classes, int $postId): array`

A post's class list as get_post_class hands it back once plugin code
filters post_class (WooCommerce drops hentry and adds a product's
stock and type): the filter's answer without repeats, in order.

- `@param list<string> $classes`
- `@return list<string>`

Internals: `withPost()` (private, line 56)


## Reply

`final class Minn\Rest\Reply` · `public/minn/src/Minn/Rest/Reply.php`

JSON responses in the reference's shape: its header set, its json_encode
flags (slashes escaped), the _fields filter, and the pagination headers
on lists.

- const `HEADERS` = `array (   'Content-Type' => 'application/json; charset=UTF-8',   'X-Content-Type-Options' => 'nosniff',   'Access-Control-Expose-Headers' => 'X-WP-Total, X-WP-TotalPages, Link',   'Access-Control-Allow-Headers' => 'Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type', )`

Used by: `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\LegacyPackController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\BlockDirectoryController`, `Minn\Rest\BlockEditorController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenuLists`, `Minn\Rest\MenusController`, `Minn\Rest\PatternDirectoryController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`

### static `answer(Minn\Http\Request $request, mixed $data, int $status = 200): Minn\Http\Response`

One item, shaped by the request's own _fields: what nearly every handler ends with.

### static `item(mixed $data, ?Minn\Rest\Fields $fields, int $status = 200): Minn\Http\Response`

One object as a response, the selected fields applied.

### static `alternate(Minn\Http\Response $response, string $permalink): Minn\Http\Response`

A post's answer pointing at its page on the site, as a viewable type's single item does.

### static `list(array $rows, int $total, int $totalPages, ?Minn\Rest\Fields $fields): Minn\Http\Response`

A list response with the total and page-count headers.

- `@param list<array> $rows`

### static `rows(array $rows, ?Minn\Rest\Fields $fields): Minn\Http\Response`

A list response without paging headers, each row's fields selected.

- `@param list<array> $rows`

### static `error(Minn\RestError $error): Minn\Http\Response`

A REST error as the reference's error body.


## Responses

`final class Minn\Rest\Responses` · `public/minn/src/Minn/Rest/Responses.php`

REST answers as the WordPress layer shapes them: any value made a
response, a list of errors made one (its status from the errors' data),
the REST server (made, and rest_api_init told, the first time it is
asked), the fields a request asked for kept, and the status that tells
a signed-out caller from one who may not.

Used by: `Minn\Engine`, `Minn\Rest\BatchController`, `Minn\Rest\RegisteredFields`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`

### static `ensure(mixed $response): mixed`

A value as a REST response (rest_ensure_response): errors and responses as they are; an HTTP response's parts carried over.

### static `fromErrors(mixed $error): Minn\Objects\RestResponse`

A list of errors as a REST response (rest_convert_error_to_response):
the first error the body, the rest as additional_errors; the status
the last one the errors' data name, 500 when none does.

### static `server(): mixed`

The REST server (rest_get_server): made the first time, of the class wp_rest_server_class names, and rest_api_init told.

### static `fieldIncluded(mixed $field, mixed $fields): bool`

Whether a field is among those asked for (rest_is_field_included), a nested one by its parent or child.

### static `filterFields(mixed $response, mixed $server, mixed $request): mixed`

A response with only the fields the request's _fields asks for (rest_filter_response_fields), a list item by item.

### static `authorizationRequiredCode(): int`

The status refusing a caller (rest_authorization_required_code): 403 for one signed in, 401 for one who is not.

Internals: `entries()` (private, line 104)


## RestMeta

`final class Minn\Rest\RestMeta` · `public/minn/src/Minn/Rest/RestMeta.php`

An object's meta field over REST, from the keys registered to show
there (probe rest-meta). Each key answers under its REST name (its own,
or show_in_rest's name): those for every subtype, then the object's
subtype's, left out of a context their schema does not name. A single
key reads its first stored value (its default, or its type's empty value,
when none), a multiple key each one; each value is checked against the
key's schema (null when it does not fit, the empty value of a scalar
type for '') and cast, or handed to the key's prepare callback. A write
goes key by key in registration order: null (or an empty list) deletes,
a value its schema refuses is a 400, and a key the user may not edit is
a 403 (an unchanged single value is left alone first). The errors are
gathered; the first is the answer, with the others beside it.

- const `SCALARS` = `array (   0 => 'string',   1 => 'boolean',   2 => 'integer',   3 => 'number', )`

Used by: `Minn\Rest\CommentObject`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\TermObject`, `Minn\Rest\UserObject`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\PostEvents`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`

### static `read(string $objectType, int $objectId, string $subtype, ?string $context = NULL): array`

The meta field of an object in a context (the one the request answers in, when none is named), keyed by REST name. @return array<string, mixed>

- `@return array<string, mixed>`

### static `value(string $objectType, int $objectId, string $key, array $field, Minn\Objects\RestRequest $request, ?callable $prepare = NULL): mixed`

One field's value for an object: a single key's first stored value (its
default when none), a multiple key's each one, prepared for the response.

- `@param array<string, mixed> $field`

### static `writeFrom(Minn\Objects\RestRequest $request, string $objectType, int $objectId, string $subtype): void`

Applies the meta a write request names, when it names any.

### static `write(string $objectType, int $objectId, string $subtype, array $meta): void`

Applies a request's meta to an object; the gathered refusals are thrown.

### static `errors(string $objectType, int $objectId, string $subtype, array $meta): array`

Applies a request's meta to an object, key by key in registration order; the refusals come back. @return list<Errors>

- `@return list<Errors>`

### static `errorsThrough(array $fields, array $meta, array $steps): array`

The refusals of writing a request's meta over the fields given, each
key deleted, stored or stored as a list through the step of that name
(WP_REST_Meta_Fields::update_value hands its own methods, so a
subclass's are the ones that run); a step's true is no refusal.

- `@param array<array-key, array<string, mixed>> $fields`
- `@param array<array-key, mixed> $meta`
- `@param array{delete: callable, single: callable, multiple: callable} $steps`
- `@return list<Errors>`

### static `fields(string $objectType, string $subtype): array`

The keys registered to show in REST for an object type and subtype,
each with its REST name, whether it is single, its schema (a multiple
key's an array of its own) and its prepare callback; keys of a type
REST does not know are left out.

- `@return array<string, array{name: string, single: bool, schema: array<string, mixed>, prepare_callback: mixed}>`

### static `prepare(mixed $value, array $field, Minn\Objects\RestRequest $request): mixed`

A value for the response: through the field's prepare callback, or checked against its schema (null when it does not fit) and cast. @param array<string, mixed> $field

- `@param array<string, mixed> $field`

### static `store(string $objectType, int $objectId, string $subtype, string $key, array $field, mixed $value): ?Minn\Objects\Errors`

A single key stored: an unchanged value is left alone, then the user must be allowed to edit it. @param array<string, mixed> $field

- `@param array<string, mixed> $field`

### static `storeAll(string $objectType, int $objectId, string $subtype, string $key, array $field, array $values): ?Minn\Objects\Errors`

A multiple key stored as a list, once the user may edit it. @param array<string, mixed> $field @param array<mixed> $values

- `@param array<string, mixed> $field @param array<mixed> $values`

### static `delete(string $objectType, int $objectId, string $key, array $field): ?Minn\Objects\Errors`

A key deleted once the user may delete it (a single key's invalid stored value refuses null). @param array<string, mixed> $field

- `@param array<string, mixed> $field`

### static `nullRefused(string $objectType, int $objectId, string $key, array $field): ?Minn\Objects\Errors`

The refusal of null for a single key whose stored value its schema does not accept, or null. @param array<string, mixed> $field

- `@param array<string, mixed> $field`

### static `deleteKey(string $objectType, int $objectId, string $key, string $name): ?Minn\Objects\Errors`

A key deleted once the user may delete it; refusals name the key by its REST name.

### static `same(string $objectType, string $subtype, string $key, array $field, mixed $stored, mixed $value): bool`

Whether a value sanitizes to what is stored (scalar types compared as the text the store keeps). @param array<string, mixed> $field

- `@param array<string, mixed> $field`

### static `emptyValue(string $type): mixed`

A type's empty value: '' for a string, false, 0, or an empty list.

Internals: `update()` (private, line 174), `replaceAll()` (private, line 214), `refused()` (private, line 274), `failed()` (private, line 280), `nullStored()` (private, line 286), `gathered()` (private, line 293), `request()` (private, line 323)


## RestUrl

`final readonly class Minn\Rest\RestUrl` · `public/minn/src/Minn/Rest/RestUrl.php`

REST URLs in the form the reference emits for the site's permalink mode:
{home}/wp-json/wp/v2/... when pretty, otherwise
{home}/index.php?rest_route=/wp/v2/... with the route value URL-encoded
when query args ride along.

Used by: `Minn\Engine`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockDirectoryController`, `Minn\Rest\BlockEditorController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentObject`, `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontObject`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostObject`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\RevisionsController`, `Minn\Rest\RouteCatalogue`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\Taxonomies`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TermObject`, `Minn\Rest\Types`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetObject`

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


## RevisionCollection

`final class Minn\Rest\RevisionCollection` · `public/minn/src/Minn/Rest/RevisionCollection.php`

A revisions list as the reference answers it (probe of the posts,
pages, patterns, menus, templates and global styles revisions routes):
the include list (which outranks exclude) or the exclude list, the
search (terms, quoted phrases and -exclusions over the title, excerpt
and content, as a post search reads them), the order, then the page. An
empty result is no pages at all. Without per_page every match is one
page. An offset sent (zero too) is where a page of per_page starts, in
the page number's place; one other than zero is refused at or past the
total, and without one a page past the last is refused.

Used by: `Minn\Rest\GlobalStylesController`, `Minn\Rest\RevisionsController`, `Minn\Rest\TemplateRevisionsController`

### static `ofParent(?array $row, int $parent): Minn\Content\PostRecord`

The revision a single route asks for, refused as the reference refuses
it: no revision by that id is an invalid id, one of another parent a
mismatch naming the parent asked for.

- `@param array<string, mixed>|null $row`

### static `filtered(Minn\Http\Request $request, array $rows): array`

The rows the request's filters keep, in its order.

- `@param list<PostRecord> $rows newest first`
- `@return list<PostRecord>`

### static `paged(Minn\Http\Request $request, array $rows): array`

The page the request asks for, with the total and the page count.

- `@param list<T> $rows`
- `@return array{0: list<T>, 1: int, 2: int}`

Internals: `ids()` (private, line 109), `matches()` (private, line 119), `contains()` (private, line 132), `sorted()` (private, line 148), `rank()` (private, line 186)


## RevisionsController

`final readonly class Minn\Rest\RevisionsController` · `public/minn/src/Minn/Rest/RevisionsController.php`

wp/v2 revisions under posts, pages, patterns and navigation menus, and
autosaves under those and menu items (probes rest-revisions,
rest-autosaves). A revision carries the fields its parent's type
supports: a navigation menu's its title and content, a menu item's its
title alone.

- const `TYPES` = `array (   'posts' => 'post',   'pages' => 'page',   'blocks' => 'wp_block',   'navigation' => 'wp_navigation',   'menu-items' => 'nav_menu_item', )` — The parents' REST bases and their post types.
- const `FIELDS` = `array (   'wp_navigation' =>    array (     0 => 'title',     1 => 'content',   ),   'nav_menu_item' =>    array (     0 => 'title',   ), )` — The fields a revision carries by its parent's type; any other carries title, content and excerpt.
- const `DELETE_REFUSAL` = `'Sorry, you are not allowed to delete revisions of this post.'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Revisions $revisions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, ?Minn\Front\Permalinks $permalinks = NULL)
```


### `revisions(Minn\Http\Request $request, string $base, string $parent): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions (cap edit_post on {parent}; post parent {parent} must exist)`

Every revision of the parent, its autosaves among them, filtered, ordered and paged as the request asks (RevisionCollection).

### `revision(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions/{id:[\d]+} (cap edit_post on {parent}; post parent {parent} must exist)`

One revision of a post, page, or block.

### `deleteRevision(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions/{id:[\d]+} (cap delete_post on {parent}; post parent {parent} must exist)`

A revision deleted for good, answered as it was; never trashed. The
reference grants deleting a revision to nobody by default, so a
request that passes the parent's check is refused at the revision's.

### `autosaves(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{id:[\d]+}/autosaves (cap edit_post on {id}; post parent {id} must exist)`

The autosaves of a post, every author's, in the asked context; the edit view names each one's preview.

### `autosave(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{parent:[\d]+}/autosaves/{id:[\d]+} (cap edit_post on {parent}; post parent {parent} must exist)`

The parent's newest autosave, whoever saved it and whichever autosave id is asked for; the edit view names its preview.

### `createAutosave(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{id:[\d]+}/autosaves (cap edit_post on {id}; post {id} must exist)`

One autosave slot per author; the reply carries a preview link.

### `object(Minn\Content\PostRecord|array $r, Minn\Rest\Context $context = Minn\Rest\Context::View): array`

One revision row as wp/v2 serves it (autosaves and revisions alike), in the view, edit or embed shape.

Internals: `selected()` (private, line 81), `draftObject()` (private, line 187), `clean()` (private, line 202), `embedded()` (private, line 250), `withPreviewLink()` (private, line 267), `previewLink()` (private, line 283), `meta()` (private, line 300), `requireParent()` (private, line 316), `parentOf()` (private, line 328)


## RouteArgs

`final class Minn\Rest\RouteArgs` · `public/minn/src/Minn/Rest/RouteArgs.php`

The argument groups a route registers with, filled the way register_rest_route fills them.

### static `normalise(array $args): array`

Route arguments with the shared args folded into each endpoint.

- `@param array<string, mixed> $args a single handler (with 'callback') or a list of handler groups, plus optional shared 'args'`
- `@return array{0: array<int|string, mixed>, 1: bool} the groups, and whether any lacks a permission_callback`


## RouteCatalogue

`final readonly class Minn\Rest\RouteCatalogue` · `public/minn/src/Minn/Rest/RouteCatalogue.php`

The routes the engine serves, described in the reference's shape for the
index and for an OPTIONS request: every route's namespace, its methods
and the parameters it declares, gathered per concrete route from the
attributes so a client learns what really works on it, with the
namespaces they fall under. Routes are keyed as the reference spells them
('/wp/v2/posts/(?P<id>[\d]+)').

- const `SERVER_ROUTES` = `array (   0 => '/batch/v1', )` — The routes the reference's server registers itself, outside any namespace.
- const `FIRST` = `'oembed/1.0'` — The namespace the reference registers before any plugin's.
- const `CORE` = `array (   0 => 'wp/v2',   1 => 'wp-site-health/v1',   2 => 'wp-block-editor/v1',   3 => 'wp-abilities/v1', )` — The core namespaces the reference registers after the plugins', in its order.

Used by: `Minn\Rest\Api`, `Minn\Rest\IndexController`, `Minn\Rest\RuntimeRoutes`

```php
__construct(Minn\Http\Router $router, Minn\Rest\Types $types, Minn\Rest\RestUrl $url)
```


### `all(): array`

Every route the engine serves, the root and each namespace's index
among them, and the namespaces.

- `@return array{namespaces: list<string>, routes: array<string, array<string, mixed>>}`

### static `ordered(array $namespaces): array`

The namespaces in the order the reference's index lists them, which
is the order they were registered in: oEmbed's first, then each
plugin's (Minn Admin's among them) as they came, then core's own.

- `@param list<string> $namespaces`
- `@return list<string>`

### static `namespaceOf(string $route): string`

The namespace a route is listed under: its first two segments when the
second is a version. The routes the reference's server registers
itself, the batch route among them, belong to none, so none is listed
for them and their path answers no namespace index.

### `describing(string $path): ?array`

The first route whose pattern takes the path, as the reference finds
the route an OPTIONS request describes, with its description; null
when none does.

- `@return array{0: string, 1: array<string, mixed>}|null`

Internals: `endpoints()` (private, line 74), `rank()` (private, line 107)


## RouteIndex

`final class Minn\Rest\RouteIndex` · `public/minn/src/Minn/Rest/RouteIndex.php`

The description of one route the REST index publishes: namespace, methods, endpoints with their argument schemas, self link.

- const `SCHEMA_KEYWORDS` = `array (   0 => 'default',   1 => 'enum',   2 => 'description',   3 => 'type',   4 => 'items',   5 => 'minimum',   6 => 'maximum',   7 => 'exclusiveMinimum',   8 => 'exclusiveMaximum',   9 => 'minLength',   10 => 'maxLength',   11 => 'pattern',   12 => 'format',   13 => 'properties',   14 => 'additionalProperties',   15 => 'oneOf',   16 => 'anyOf',   17 => 'minItems',   18 => 'maxItems',   19 => 'uniqueItems', )`

### static `endpointsIn(string $route, array $table, callable $restUrl): array`

One route's endpoints as a route table describes them in the index
(methods, batching, parameters); none for a route the table lacks.

- `@param array{0: array<string, list<array<string, mixed>>>, 1: array<string, array<string, mixed>>} $table the routes and each route's options`
- `@param callable(string): string $restUrl`
- `@return list<array<string, mixed>>`

### static `describe(string $route, array $callbacks, array $options, string $context, callable $restUrl): ?array`

One route as the index describes it, or null when hidden.

- `@param list<array<string, mixed>> $callbacks the route's handler groups`
- `@param array<string, mixed> $options the route's non-numeric options (namespace, schema)`
- `@param callable(string): string $restUrl`
- `@return array<string, mixed>|null null when no endpoint shows in the index`

Internals: `argument()` (private, line 71)


## RouteMatch

`final class Minn\Rest\RouteMatch` · `public/minn/src/Minn/Rest/RouteMatch.php`

Finds the registered handler for a method and path among the runtime's route table.

### static `find(array $namespaces, callable $routesFor, string $method, string $path): Minn\Runtime\Refusal|array`

The handler for a method and path across the registered namespaces, or the refusal.

- `@param list<string> $namespaces the registered namespaces`
- `@param callable(string): array<string, list<array<string, mixed>>> $routesFor the (filtered) routes of one namespace, or all when given ''`
- `@return array{route: string, handler: array<string, mixed>, params: array<string, string>, defaults: array<string, mixed>}|Refusal`

### static `route(array $namespaces, callable $routesFor, string $path): ?array`

The first registered route whose pattern matches a path, whatever the
method, with its handlers; null when none does. An OPTIONS request is
answered from this.

- `@param list<string> $namespaces`
- `@param callable(string): array<string, list<array<string, mixed>>> $routesFor`
- `@return array{route: string, handlers: list<array<string, mixed>>}|null`


## RouteTable

`final class Minn\Rest\RouteTable` · `public/minn/src/Minn/Rest/RouteTable.php`

The registered endpoints in dispatch shape: one handler list per route, methods as a set, non-numeric keys lifted into the route's options.

- const `HANDLER_DEFAULTS` = `array (   'methods' =>    array (   ),   'accept_json' => false,   'accept_raw' => false,   'show_in_index' => true,   'args' =>    array (   ), )`

### static `normalise(array $endpoints): array`

Registered endpoints as a route table with their options.

- `@param array<string, mixed> $endpoints route => a handler or a list of handlers plus options`
- `@return array{0: array<string, list<array<string, mixed>>>, 1: array<string, array<string, mixed>>} the routes, and the options found per route`


## RuntimeEnvelope

`final readonly class Minn\Rest\RuntimeEnvelope` · `public/minn/src/Minn/Rest/RuntimeEnvelope.php` · implements `Minn\Http\Envelope`

The REST server's filters around one of Minn's own routes, as the
reference runs them around every route (contracts/runtime.md, "The REST
server's envelope"): rest_request_before_callbacks is handed the argument
check's error or null and may refuse; the route's permission is the
next refusal; rest_dispatch_request may answer in place of the route;
rest_request_after_callbacks sees whatever came of it and may change it.
A value other than an error from the first filter does not answer: the
route still runs. With nothing hooked on any of the three, the route
answers as it would without plugins, unconverted; and before the runtime
boots (the API is built first) there are no plugins to ask.

- const `FILTERS` = `array (   0 => 'rest_request_before_callbacks',   1 => 'rest_dispatch_request',   2 => 'rest_request_after_callbacks', )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Types $types)
```


### `around(Minn\Http\Matched $matched, Minn\Http\Request $request, ?Minn\RestError $invalid, ?Minn\RestError $refusal, Closure $invoke): Minn\Http\Response`

The route's answer with the server's filters around it, or as the route gives it when no plugin can hear.

Internals: `plain()` (private, line 77), `run()` (private, line 89), `routeName()` (private, line 99), `handler()` (private, line 117), `defaults()` (private, line 132), `typed()` (private, line 150)


## RuntimePrepare

`final class Minn\Rest\RuntimePrepare` · `public/minn/src/Minn/Rest/RuntimePrepare.php`

An item a REST response carries, through the filter the reference runs
as it prepares one (rest_prepare_{type}, rest_prepare_attachment,
rest_prepare_user, rest_prepare_comment, rest_prepare_{taxonomy}): the
item as a response object, its links on the response rather than in its
data, handed with the object it describes and the request; what the
filter leaves is the item. With nothing hooked, or a response handed back
untouched, the item is Minn's own, byte for byte. Fields plugin code
registers for the type are added first, as the reference adds them
before the filter runs.

- const `BLOCK_HOOKS_TYPES` = `array (   0 => 'post',   1 => 'page',   2 => 'wp_block',   3 => 'wp_navigation', )` — The post types whose REST answers and saves get their hooked blocks, before plugins say.

Used by: `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentObject`, `Minn\Rest\Embed`, `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontObject`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenusController`, `Minn\Rest\PostObject`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TermObject`, `Minn\Rest\UserObject`, `Minn\Rest\WidgetObject`, `Minn\Runtime\PostSave`

### static `answering(Minn\Objects\RestRequest $request): void`

Remembers the request a REST call is answering, for the filters its items pass through (on the request's own runtime).

### static `during(Minn\Objects\RestRequest $request, Closure $run): mixed`

Runs an in-process REST call answering its own request (the filters
hand plugins that one), then puts back the request it interrupted.

- `@param \Closure(): T $run`

### static `postItem(string $filter, array $item, int $postId, string $type): array`

A post-shaped item (a post, a page, a pattern, a navigation, an
attachment, any other type) through rest_prepare_{type}, given its
hooked blocks first; the post it describes is looked up only when a
hook needs it.

- `@param array<string, mixed> $item`
- `@return array<string, mixed>`

### static `savedWithHookedBlocks(stdClass $prepared, string $type): void`

A prepared post about to be saved, as rest_block_hooks_post_types has
it: a type it lists has its content's ignored hooked blocks marked, a
navigation's root list put in meta_input, as the reference's
update_ignored_hooked_blocks_postmeta does; asked of every save.

### static `item(string $filter, array $item, Closure $described, ?string $postType = NULL): array`

One item through a rest_prepare filter.

- `@param array<string, mixed> $item`
- `@param \Closure(): mixed $described the object the item describes, fetched only when a plugin listens`
- `@return array<string, mixed>`

Internals: `hookedBlocks()` (private, line 80), `withHookedBlocks()` (private, line 117), `objectType()` (private, line 158)


## RuntimeRoutes

`final class Minn\Rest\RuntimeRoutes` · `public/minn/src/Minn/Rest/RuntimeRoutes.php`

Routes plugin code registered with register_rest_route(), answered
through the runtime's server after the engine's own routes have had
their turn; the runtime's say before the engine answers at all (an
authentication refusal, a pre-dispatch answer, a removed endpoint);
and the runtime's namespaces folded into the index.

- const `DISPATCH_DONE` = `array (   'rest_filter_response_fields' => 10,   'rest_send_allow_header' => 10, )` — rest_post_dispatch's defaults the engine does itself: every answer is cut to its _fields before it is served.
- const `ROUTE_SCOPED` = `array (   '_wp_connectors_rest_settings_dispatch' =>    array (     0 => 10,     1 => '/wp/v2/settings',   ), )` — rest_post_dispatch's defaults that touch one route only: elsewhere they hand the answer back as it came, so they are left out there.
- const `SERVE_DONE` = `array (   '_oembed_rest_pre_serve_request' => 10,   'rest_send_cors_headers' => 10, )` — rest_pre_serve_request's defaults the engine does itself: oEmbed's XML (see oembedXml()).

Used by: `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\FontsController`, `Minn\Rest\MediaController`, `Minn\Rest\MenuLists`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\SearchController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostSave`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`


### static `gate(Minn\Http\Request $request): ?Minn\Http\Response`

What plugin code decides before any route runs, engine routes
included, in the reference's order: rest_authentication_errors may
refuse the request, rest_pre_dispatch may answer it outright, and a
route a rest_endpoints filter removed is no route at all. Null lets
the engine's router proceed.

### static `predispatch(Minn\Http\Request $request): ?Minn\Http\Response`

What plugin code decides before a route runs, for a request inside
another (an embedded link) as for the first: rest_pre_dispatch may
answer it outright, and a route a rest_endpoints filter removed is no
route at all. Null lets the router proceed.

### static `dispatch(Minn\Http\Request $request): ?Minn\Http\Response`

Null when the runtime has no route for the request either.

### static `allowedMethods(string $route, Minn\Objects\RestRequest $wpRequest): array`

The methods of a plugin's route whose handler lets this request through (a handler with no permission callback does). @return list<string>

- `@return list<string>`

### static `targetHints(string $href): ?array`

The methods a self link's caller may use there, as the reference
hints them on every self link it serves (targetHints.allow): the
route the link names, each of its methods whose handler lets this
caller through; null when no route of the runtime's takes the link.
A route the engine answers itself is judged by its policies, as its
Allow header is (probe rest-registry-controllers).

- `@return list<string>|null`

### static `described(string $route, array $entry): array`

An engine route's description with what its core controller says of
the same endpoints (an endpoint listing the same methods): whether it
may be batched, and, for a write or a delete, or a read that declares
none, the parameters the controller publishes when they hold every
one the engine declares (a type's writes list its schema's fields).
A read's own parameters stay the engine's, as do the endpoints and
their methods: nothing the engine does not answer is listed.

- `@param array<string, mixed> $entry`
- `@return array<string, mixed>`

### static `mergeIndex(Minn\Http\Response $response): Minn\Http\Response`

The engine's index plus the namespaces and routes the runtime holds.

### static `adopt(Minn\Http\Request $request, Minn\Objects\RestRequest $wpRequest): void`

An in-process call's own request object stands for the engine's request: the server sets its route's parameters on it, as the reference's dispatch does.

### static `sanitized(Minn\Http\Request $request, array $registered): Minn\Objects\RestRequest`

The request as the list's handler reads it: its parameters with their
defaults, sanitized by their schemas, as plugins' filters see it too.

- `@param array<string, array<string, mixed>> $registered`

### static `wpRequest(Minn\Http\Request $request): Minn\Objects\RestRequest`

The request as the runtime's server reads it, and as a REST filter or action hands it to plugins: the same object each time.

### static `matched(Minn\Http\Request $request, string $route, array $handler): void`

Keeps the route and handler a request matched, for the response plugins see at serving.

- `@param array<string, mixed> $handler`

### static `toWpError(Minn\RestError $error): Minn\Objects\Errors`

An engine refusal as plugins handle one.

### static `toWp(Minn\Http\Response $response): Minn\Objects\RestResponse`

An engine answer as plugins handle one: its data decoded (an empty
object stays one), its links on the response rather than in the data,
its status and headers.

### static `wpHeaders(array $headers, string $route = ''): array`

A route's own headers as the reference's response object holds them:
a list's totals are numbers, but the comments list's stay text.

- `@param array<string, string> $headers`
- `@return array<string, string|int>`

### static `itemResponse(array $item): Minn\Objects\RestResponse`

One item a response carries, as a response object: its data without
_links, the links on the response.

- `@param array<string, mixed> $item`

### static `fromWp(mixed $result): Minn\Http\Response`

Whatever the filters left, as the engine sends it; what they handed back untouched is the engine's own answer, byte for byte.

### static `serve(Minn\Http\Request $request, Minn\Http\Response $response): Minn\Http\Response`

The server's last word on any REST answer, engine or plugin route,
as the reference serves one: rest_post_dispatch may change it,
rest_pre_serve_request may serve it itself (what it prints is the
body), and rest_pre_echo_response may rewrite the data echoed. With
nothing hooked, the answer goes out as it is.

### static `servedAsXml(Minn\Http\Request $request, Minn\Http\Response $response): bool`

Whether the answer went out as oEmbed's XML. The reference prints it
in rest_pre_serve_request ahead of rest_send_cors_headers, so none of
the CORS headers follow it, Vary: Origin among them.

### static `ensure(mixed $result): Minn\Objects\RestResponse`

A callback's return as a response object, an error converted.

### static `server(): Minn\Objects\RestServer`

The runtime's REST server, the one door to it (built, with rest_api_init, on first use).

Internals: `allow()` (private, line 104), `dispatchDone()` (private, line 359), `asksForXml()` (private, line 434), `oembedXml()` (private, line 445), `look()` (private, line 459), `remember()` (private, line 464), `decode()` (private, line 471), `expand()` (private, line 491), `newWpRequest()` (private, line 516), `toResponse()` (private, line 545)


## Schema

`final readonly class Minn\Rest\Schema` · `public/minn/src/Minn/Rest/Schema.php`

JSON-schema handling the way the REST API's argument validation does it:
validate a value against a schema (a Refusal names what failed), sanitise
it into the schema's type, filter a response by context, and the type
tests the schema vocabulary needs. Behaviour pinned by contracts/fixtures/api/rest.json.

- const `TYPES` = `array (   0 => 'array',   1 => 'object',   2 => 'string',   3 => 'number',   4 => 'integer',   5 => 'boolean',   6 => 'null', )`
- const `KEYWORDS` = `array (   0 => 'title',   1 => 'description',   2 => 'default',   3 => 'type',   4 => 'format',   5 => 'enum',   6 => 'items',   7 => 'properties',   8 => 'additionalProperties',   9 => 'patternProperties',   10 => 'minProperties',   11 => 'maxProperties',   12 => 'minimum',   13 => 'maximum',   14 => 'exclusiveMinimum',   15 => 'exclusiveMaximum',   16 => 'multipleOf',   17 => 'minLength',   18 => 'maxLength',   19 => 'pattern',   20 => 'minItems',   21 => 'maxItems',   22 => 'uniqueItems',   23 => 'anyOf',   24 => 'oneOf', )` — Every keyword a route schema may carry, in the reference's order; the endpoint subset drops the three descriptive ones.
- const `ENDPOINT_KEYWORDS` = `array (   0 => 'type',   1 => 'format',   2 => 'enum',   3 => 'items',   4 => 'properties',   5 => 'additionalProperties',   6 => 'patternProperties',   7 => 'minProperties',   8 => 'maxProperties',   9 => 'minimum',   10 => 'maximum',   11 => 'exclusiveMinimum',   12 => 'exclusiveMaximum',   13 => 'multipleOf',   14 => 'minLength',   15 => 'maxLength',   16 => 'pattern',   17 => 'minItems',   18 => 'maxItems',   19 => 'uniqueItems',   20 => 'anyOf',   21 => 'oneOf', )` — An object schema that names its properties forbids the others unless it says otherwise, all the way down.

Used by: `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\BlockRendererController`, `Minn\Rest\ClientSchema`, `Minn\Rest\LiveSettings`, `Minn\Rest\RestMeta`, `Minn\Rest\SchemaChoices`, `Minn\Rest\SchemaValues`, `Minn\Rest\Services`, `Minn\Rest\SiteSchema`, `Minn\Rest\UsersController`

```php
__construct(Closure $email, Closure $number, Closure $format)
```
- `@param Closure(string): bool $email the email test, so the reference's is_email filter applies`
- `@param Closure(int|float): string $number the localised number formatter for messages`
- `@param Closure(string, mixed): mixed $format the sanitiser for a string format (hex-color, email, uri, ...)`


### `validate(mixed $value, array $args, string $param = ''): Minn\Runtime\Refusal|true`

Whether a value satisfies a schema, or the reference's refusal.

### `choices(): Minn\Rest\SchemaChoices`

A schema's anyOf and oneOf: which of their schemas a value matches, and the refusal when not as wanted.

### `validateAs(string $type, mixed $value, array $args, string $param): Minn\Runtime\Refusal|true`

One type's own checks, as the per-type validators run them: a number's
bounds before an integer's wholeness, and no enum or format.

### `validateEnum(mixed $value, array $args, string $param): Minn\Runtime\Refusal|true`

Whether a value is one of the schema's enum, coerced to the schema's type first when it names one.

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

### static `castEmptyObjects(mixed $value, array $schema): mixed`

A value with each empty array the schema calls an object made one
(it encodes as {}), through properties, additional properties, list
items and the value as a whole (probe rest-view-config).

- `@param array<string, mixed> $schema`

### static `closeObjects(array $schema): array`

The schema with additionalProperties closed on every object.

Internals: `validateInteger()` (private, line 110), `validateString()` (private, line 119), `validateNumber()` (private, line 137), `validateArray()` (private, line 149), `firstMissing()` (private, line 179), `validateObject()` (private, line 192), `validateFormat()` (private, line 226), `validateBounds()` (private, line 238), `enum()` (private, line 275), `wrongType()` (private, line 292)


## SchemaChoices

`final readonly class Minn\Rest\SchemaChoices` · `public/minn/src/Minn/Rest/SchemaChoices.php`

anyOf and oneOf the way the REST API's validation judges them (probe
rest-schema-choices): a choice's schemas without a type take the
choice's own; the refusal for matching none is named from the closest
schema, or lists the titles, or is plain; several matches are named by
their titles. Schema::validate() asks here first, then applies the
schema's own keywords.

Used by: `Minn\Rest\Schema`

```php
__construct(Minn\Rest\Schema $schema)
```


### `matchAny(mixed $value, array $args, string $param): Minn\Runtime\Refusal|array`

The first of an anyOf's schemas the value matches, each without a type
taking the choice's own, or the refusal for matching none (probe
rest-media-actions).

- `@return array<string, mixed>|Refusal`

### `matchOne(mixed $value, array $args, string $param, string $settle = 'one'): Minn\Runtime\Refusal|array`

The one of a oneOf's schemas the value matches, each without a type
taking the choice's own; "first" settles for the first match. Matching
none is refused as matchAny() refuses it; matching several names them
when every one has a title, with their positions.

- `@return array<string, mixed>|Refusal`

### `combiningError(mixed $value, string $param, array $errors): Minn\Runtime\Refusal`

The refusal for a value no schema of a choice matched (probe
rest-media-actions). With one schema, its refusal is named. Otherwise
the refusals that say only the value is not the schema's type are set
aside, and one left is named. Of several left, when the first is an
object's, the one whose properties share the most keys with the value
is named (the first of a tie, none when none share). Then, when every
schema has a title, they are listed; else the plain refusal.

- `@param list<array{0: Refusal, 1: array<string, mixed>, 2: int|string}> $errors refusal, schema, position`

### `combiningFailure(string $param, ?string $title, string $reason, mixed $position): Minn\Runtime\Refusal`

The refusal for a value that matched none of a combinator's schemas,
worded from the one that came closest: its title when it has one (an
empty title still reads as one), and that schema's own reason.

Internals: `closestObject()` (private, line 127), `listed()` (private, line 144)


## SchemaData

`final class Minn\Rest\SchemaData` · `public/minn/src/Minn/Rest/SchemaData.php`

REST schemas captured from the reference for the facade's controller
classes (data/rest-schemas.json): an item schema, a collection's
parameters or a route argument, by name. Callbacks are not data, so a
controller adds its own.


### static `of(string $name): array`

One captured schema by name; an empty array when there is none. @return array<string, mixed>

- `@return array<string, mixed>`


## SchemaValues

`final class Minn\Rest\SchemaValues` · `public/minn/src/Minn/Rest/SchemaValues.php`

The value side of JSON Schema, as the reference applies it: what counts
as a boolean, integer, array, or object, the coercions to each, the
comparisons and date, colour, and uuid parsers the formats use, and the
combining walk anyOf and oneOf share. Schema holds the rules; this holds
what they are applied to.

Used by: `Minn\Rest\PostListArgs`, `Minn\Rest\RevisionCollection`, `Minn\Rest\Schema`, `Minn\Rest\Settings`, `Minn\Rest\TemplatesController`, `Minn\Rest\WidgetsController`

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

The first of the schema's types, in the order it lists them, that the value reads as (an empty string is a string when that is allowed); '' when none.

### static `valuesEqual(mixed $a, mixed $b): bool`

Whether two values are equal by the reference's loose rules.

### static `patternProperty(string $property, array $args): ?array`

The schema of the patternProperties entry a property name matches, or null.

### static `matchesPattern(string $pattern, string $value): bool`

Whether a value matches a schema pattern, read as the reference reads one: unanchored, # escaped, UTF-8.

### static `hasUniqueItems(array $items): bool`

Whether no two items are equal once their keys are sorted; types stay apart, so 1 and "1" differ.

### static `intersectKeys(array $first, array $second): array`

The first array's entries whose keys the second has too, at every level where both hold an array.

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

Internals: `objectOf()` (private, line 85)


## SearchController

`final readonly class Minn\Rest\SearchController` · `public/minn/src/Minn/Rest/SearchController.php`

wp/v2 search over published content: id, title, url, type, and the
post type as subtype. Ordered the way the reference orders a search:
a title holding every term first, then a title holding any term, then
an excerpt match, then a content match, newest first within a rank.

- const `MAX_PER_PAGE` = `100`
- const `IDS` = `array (   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ), )` — Read by list(): the ids the results keep to, and those they leave out.
- const `LAST_OFFSET` = `1.8446744073709552E+19` — The largest row offset the database takes; a page past it fails the query, and the reference then refuses the page.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Rest\Types $types, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/search (public)`

Search across post types with the reference's relevance order.

Internals: `item()` (private, line 107), `subtypes()` (private, line 139), `taxonomies()` (private, line 160), `rankExpression()` (private, line 166), `terms()` (private, line 177), `escapeLike()` (private, line 194)


## Services

`final class Minn\Rest\Services` · `public/minn/src/Minn/Rest/Services.php`

The objects one REST request shares, each made once, on first use, from
the database door and the request. Every getter is typed and names its
dependencies in plain constructor calls: there is no autowiring, and
get() knows only the names listed here, so a wrong one fails at the
first call rather than deep in a handler.

- const `NAMED` = `array (   'Minn\\Content\\Users' => 'users',   'Minn\\Content\\Posts' => 'posts',   'Minn\\Content\\Terms' => 'terms',   'Minn\\Content\\Comments' => 'comments',   'Minn\\Content\\Site' => 'site',   'Minn\\Content\\PostWriter' => 'writer',   'Minn\\Front\\Permalinks' => 'permalinks',   'Minn\\Rest\\RestUrl' => 'url',   'Minn\\Auth\\Capabilities' => 'capabilities',   'Minn\\Rest\\Caller' => 'caller',   'Minn\\Rest\\Subjects' => 'subjects',   'Minn\\Extension\\Loader' => 'loader',   'Minn\\Rest\\Types' => 'types',   'Minn\\Rest\\Taxonomies' => 'taxonomies',   'Minn\\Media\\Uploads' => 'uploads',   'Minn\\Content\\Inventory' => 'inventory',   'Minn\\Ops\\Packages' => 'packages',   'Minn\\Admin\\App' => 'app',   'Minn\\Ops\\Logs' => 'logs',   'Minn\\Ops\\Updates' => 'updates',   'Minn\\Content\\Menus' => 'menus',   'Minn\\Content\\Revisions' => 'revisions',   'Minn\\Auth\\Sessions' => 'sessions',   'Minn\\Auth\\ApplicationPasswords' => 'applicationPasswords',   'Minn\\Admin\\Translations' => 'translations',   'Minn\\Admin\\Appearance' => 'appearance',   'Minn\\Admin\\HiddenIntegrations' => 'hiddenIntegrations',   'Minn\\Admin\\ActivityFeed' => 'activityFeed',   'Minn\\Admin\\Dashboard' => 'dashboard',   'Minn\\Admin\\Notifications' => 'notifications',   'Minn\\Ops\\Diagnostics' => 'diagnostics',   'Minn\\Media\\Writer' => 'mediaWriter',   'Minn\\Rest\\Schema' => 'schema',   'Minn\\Rest\\PostObject' => 'postObject',   'Minn\\Rest\\TermObject' => 'termObject',   'Minn\\Rest\\UserObject' => 'userObject',   'Minn\\Rest\\MediaObject' => 'mediaObject',   'Minn\\Rest\\CommentObject' => 'commentObject',   'Minn\\Theme\\UserStyles' => 'userStyles',   'Minn\\Theme\\ThemeStyles' => 'themeStyles', )`

Used by: `Minn\Rest\Api`


### static `forRequest(Minn\Db $db, Minn\Http\Request $request): self`

The services for one request, none made yet.

### `get(string $class): object`

The service registered under a class name. Only the names in NAMED
answer; anything else is a programming error and says so at once.

### `subjects(): Minn\Rest\Subjects`

The record lookups the policy gate asks before it judges a caller.

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

### `packages(): Minn\Ops\Packages`

Installing and removing themes and extensions.

### `logs(): Minn\Ops\Logs`

The debug log reader.

### `updates(): Minn\Ops\Updates`

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

### `releases(): Minn\Ops\Releases`

Minn's own releases, asked of GitHub once a day.

### `engineChangelog(): Minn\Ops\Changelog`

Minn's changelog, read from its repository.

### `adminChangelog(): Minn\Ops\Changelog`

Minn Admin's changelog, read from its repository.

### `coreStatus(): Minn\Ops\CoreStatus`

Minn's version and the release on offer, behind the app's update banner.

### `engineUpdate(): Minn\Ops\EngineUpdate`

The engine replacing itself with a release.

### `notifications(): Minn\Admin\Notifications`

The bell feed.

### `diagnostics(): Minn\Ops\Diagnostics`

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

Internals: `share()` (private, line 471)


## Settings

`final readonly class Minn\Rest\Settings` · `public/minn/src/Minn/Rest/Settings.php`

The registered settings the Settings views read and write, mapped to
the options WordPress stores. Registration order is the payload order.

- const `REGISTRY` = `array (   'blog_public' =>    array (     0 => 'blog_public',     1 => 'int',   ),   'minn_admin_maintenance' =>    array (     0 => 'minn_admin_maintenance',     1 => 'bool',   ),   'users_can_register' =>    array (     0 => 'users_can_register',     1 => 'int',   ),   'default_role' =>    array (     0 => 'default_role',     1 => 'string',   ),   'comment_moderation' =>    array (     0 => 'comment_moderation',     1 => 'int',   ),   'comment_registration' =>    array (     0 => 'comment_registration',     1 => 'int',   ),   'show_avatars' =>    array (     0 => 'show_avatars',     1 => 'int',   ),   'title' =>    array (     0 => 'blogname',     1 => 'string',   ),   'description' =>    array (     0 => 'blogdescription',     1 => 'string',   ),   'url' =>    array (     0 => 'siteurl',     1 => 'string',   ),   'email' =>    array (     0 => 'admin_email',     1 => 'string',   ),   'timezone' =>    array (     0 => 'timezone_string',     1 => 'string',   ),   'date_format' =>    array (     0 => 'date_format',     1 => 'string',   ),   'time_format' =>    array (     0 => 'time_format',     1 => 'string',   ),   'start_of_week' =>    array (     0 => 'start_of_week',     1 => 'int',   ),   'language' =>    array (     0 => 'WPLANG',     1 => 'language',   ),   'use_smilies' =>    array (     0 => 'use_smilies',     1 => 'bool',   ),   'default_category' =>    array (     0 => 'default_category',     1 => 'int',   ),   'default_post_format' =>    array (     0 => 'default_post_format',     1 => 'string',   ),   'posts_per_page' =>    array (     0 => 'posts_per_page',     1 => 'int',   ),   'show_on_front' =>    array (     0 => 'show_on_front',     1 => 'string',   ),   'page_on_front' =>    array (     0 => 'page_on_front',     1 => 'int',   ),   'page_for_posts' =>    array (     0 => 'page_for_posts',     1 => 'int',   ),   'default_ping_status' =>    array (     0 => 'default_ping_status',     1 => 'string',   ),   'default_comment_status' =>    array (     0 => 'default_comment_status',     1 => 'string',   ),   'site_logo' =>    array (     0 => 'site_logo',     1 => 'int_or_null',   ),   'site_icon' =>    array (     0 => 'site_icon',     1 => 'int',   ), )` — setting key => [option name, type]
- const `SCHEMA` = `array (   'blog_public' =>    array (     'title' => '',     'description' => '',     'type' => 'integer',     'required' => false,   ),   'minn_admin_maintenance' =>    array (     'title' => '',     'description' => '',     'type' => 'boolean',     'required' => false,   ),   'users_can_register' =>    array (     'title' => '',     'description' => '',     'type' => 'integer',     'required' => false,   ),   'default_role' =>    array (     'title' => '',     'description' => '',     'type' => 'string',     'required' => false,   ),   'comment_moderation' =>    array (     'title' => '',     'description' => '',     'type' => 'integer',     'required' => false,   ),   'comment_registration' =>    array (     'title' => '',     'description' => '',     'type' => 'integer',     'required' => false,   ),   'show_avatars' =>    array (     'title' => '',     'description' => '',     'type' => 'integer',     'required' => false,   ),   'title' =>    array (     'title' => 'Title',     'description' => 'Site title.',     'type' => 'string',     'required' => false,   ),   'description' =>    array (     'title' => 'Tagline',     'description' => 'Site tagline.',     'type' => 'string',     'required' => false,   ),   'url' =>    array (     'title' => '',     'description' => 'Site URL.',     'type' => 'string',     'format' => 'uri',     'required' => false,   ),   'email' =>    array (     'title' => '',     'description' => 'This address is used for admin purposes, like new user notification.',     'type' => 'string',     'format' => 'email',     'required' => false,   ),   'timezone' =>    array (     'title' => '',     'description' => 'A city in the same timezone as you.',     'type' => 'string',     'required' => false,   ),   'date_format' =>    array (     'title' => '',     'description' => 'A date format for all date strings.',     'type' => 'string',     'required' => false,   ),   'time_format' =>    array (     'title' => '',     'description' => 'A time format for all time strings.',     'type' => 'string',     'required' => false,   ),   'start_of_week' =>    array (     'title' => '',     'description' => 'A day number of the week that the week should start on.',     'type' => 'integer',     'required' => false,   ),   'language' =>    array (     'title' => '',     'description' => 'WordPress locale code.',     'type' => 'string',     'required' => false,   ),   'use_smilies' =>    array (     'title' => '',     'description' => 'Convert emoticons like :-) and :-P to graphics on display.',     'type' => 'boolean',     'required' => false,   ),   'default_category' =>    array (     'title' => '',     'description' => 'Default post category.',     'type' => 'integer',     'required' => false,   ),   'default_post_format' =>    array (     'title' => '',     'description' => 'Default post format.',     'type' => 'string',     'required' => false,   ),   'posts_per_page' =>    array (     'title' => 'Maximum posts per page',     'description' => 'Blog pages show at most.',     'type' => 'integer',     'required' => false,   ),   'show_on_front' =>    array (     'title' => 'Show on front',     'description' => 'What to show on the front page',     'type' => 'string',     'required' => false,   ),   'page_on_front' =>    array (     'title' => 'Page on front',     'description' => 'The ID of the page that should be displayed on the front page',     'type' => 'integer',     'required' => false,   ),   'page_for_posts' =>    array (     'title' => '',     'description' => 'The ID of the page that should display the latest posts',     'type' => 'integer',     'required' => false,   ),   'default_ping_status' =>    array (     'title' => '',     'description' => 'Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.',     'type' => 'string',     'enum' =>      array (       0 => 'open',       1 => 'closed',     ),     'required' => false,   ),   'default_comment_status' =>    array (     'title' => 'Allow comments on new posts',     'description' => 'Allow people to submit comments on new posts.',     'type' => 'string',     'enum' =>      array (       0 => 'open',       1 => 'closed',     ),     'required' => false,   ),   'site_logo' =>    array (     'title' => 'Logo',     'description' => 'Site logo.',     'type' => 'integer',     'required' => false,   ),   'site_icon' =>    array (     'title' => 'Icon',     'description' => 'Site icon.',     'type' => 'integer',     'required' => false,   ), )` — The body of a write, every registered setting by its key, as the reference describes it.

Used by: `Minn\Rest\Api`, `Minn\Rest\SettingsController`

```php
__construct(Minn\Content\Site $site)
```


### `payload(): array`

Every registered setting with its current value.

### `store(array $body): void`

Writes the registered keys in a body, already validated against
SCHEMA, in the stored form; unregistered keys are ignored. Without
plugins loaded there is nobody to tell (LiveSettings writes through
update_option when there is).


## SettingsController

`final readonly class Minn\Rest\SettingsController` · `public/minn/src/Minn/Rest/SettingsController.php`

wp/v2/settings: read and write, both behind manage_options; with plugins loaded, every registered setting (LiveSettings).

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Settings $settings, Minn\Rest\Caller $caller, Minn\Rest\LiveSettings $live)
```


### `settings(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/settings (signed in)`

Route: `POST /wp/v2/settings (signed in)`

Route: `PUT /wp/v2/settings (signed in)`

Route: `PATCH /wp/v2/settings (signed in)`

The site settings: read, or write from the body.


## SidebarsController

`final readonly class Minn\Rest\SidebarsController` · `public/minn/src/Minn/Rest/SidebarsController.php`

wp/v2/sidebars and wp/v2/widget-types as the reference answers them
(probe rest-widgets), for anyone who can edit theme options. A sidebar
is a registered one or the inactive widgets, with its wrapping markup,
the registered widgets it holds, and its status: active when registered
under a classic theme (a block theme renders none). Saving a sidebar's
widgets takes them from any other sidebar and sends the ones it drops to
the inactive widgets. The widget types are the registered widgets by id
base, in id order.

- const `BODY` = `array (   'widgets' =>    array (     'description' => 'Nested widgets.',     'type' => 'array',     'items' =>      array (       'type' =>        array (         0 => 'object',         1 => 'string',       ),     ),     'required' => false,   ), )`
- const `REFUSAL` = `'Sorry, you are not allowed to manage widgets on this site.'`

Used by: `Minn\Rest\Api`, `Minn\Rest\WidgetsController`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `sidebars(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/sidebars (public)`

Every sidebar: those the sidebars option lists, then any other
registered one. Someone who cannot edit theme options sees only the
sidebars shown in REST, and is refused when there are none (probe
rest-sidebar-classes).

### `sidebar(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/sidebars/{id:[\w-]+} (public)`

One sidebar: anyone's to read when it is shown in REST, otherwise for someone who can edit theme options.

### `save(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/sidebars/{id:[\w-]+} (cap edit_theme_options)`

Route: `PUT /wp/v2/sidebars/{id:[\w-]+} (cap edit_theme_options)`

Route: `PATCH /wp/v2/sidebars/{id:[\w-]+} (cap edit_theme_options)`

Saves a sidebar's widgets, in the order given.

### `types(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/widget-types (cap edit_theme_options)`

The registered widget types, by id base.

### `widgetType(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/widget-types/{id:[a-zA-Z0-9_-]+} (cap edit_theme_options)`

One widget type.

### `encode(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/widget-types/{id:[a-zA-Z0-9_-]+}/encode (cap edit_theme_options)`

A widget type's form and preview for settings that are not saved: the
settings sent (encoded with their hash) updated from the form's
fields, the form for them (number -1 unless one is sent), the widget
as the_widget shows it, and the settings encoded again.

### static `ready(): void`

The widgets are registered on init; a runtime that stopped short of it
registers them now. Without the runtime there are no widgets to serve.

Internals: `item()` (private, line 177), `type()` (private, line 203), `widgetTypes()` (private, line 221), `requireSidebar()` (private, line 231)


## SiteHealthController

`final readonly class Minn\Rest\SiteHealthController` · `public/minn/src/Minn/Rest/SiteHealthController.php`

wp-site-health/v1 (probe: the site health routes on the reference): the
namespace, its six asynchronous tests and the directory sizes, each for
someone who may view site health checks and refused to anyone else as
rest_forbidden. What the tests report is the reference's own Site Health
screen (its wording, its loopback and wordpress.org requests, the sizes
of a WordPress install), which the engine does not have
(WP_Site_Health's tests are placeholders), so for someone allowed the
route declines and the answer stays rest_no_route.

- const `REFUSAL` = `'Sorry, you are not allowed to do that.'`

Used by: `Minn\Rest\Api`

### `test(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-site-health/v1/tests/background-updates (cap view_site_health_checks)`

Route: `GET /wp-site-health/v1/tests/loopback-requests (cap view_site_health_checks)`

Route: `GET /wp-site-health/v1/tests/https-status (cap view_site_health_checks)`

Route: `GET /wp-site-health/v1/tests/dotorg-communication (cap view_site_health_checks)`

Route: `GET /wp-site-health/v1/tests/authorization-header (cap view_site_health_checks)`

Route: `GET /wp-site-health/v1/directory-sizes (cap view_site_health_checks)`

Route: `GET /wp-site-health/v1/tests/page-cache (cap view_site_health_checks)`

One of the asynchronous tests, or the directory sizes.


## SiteSchema

`final class Minn\Rest\SiteSchema` · `public/minn/src/Minn/Rest/SiteSchema.php`

The site's JSON-schema handler: one Schema whose email test, number
formatter and string-format sanitizers carry the filters plugins hook
(is_email, number_format_i18n, the sanitizers), and the answers the REST
helpers give with it (true or a list of errors).

Used by: `Minn\Rest\RestMeta`, `Minn\Runtime\AbilityRun`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\ThemeSupports`


### static `get(): Minn\Rest\Schema`

The site's schema handler.

### static `validate(mixed $value, mixed $args, mixed $param = ''): mixed`

Whether a value fits a schema (rest_validate_value_from_schema): true, or the errors saying why not.

### static `sanitize(mixed $value, mixed $args, mixed $param = ''): mixed`

A value made to fit a schema (rest_sanitize_value_from_schema).

### static `result(Minn\Runtime\Refusal|true $result): mixed`

True, or the list of errors a refusal reads as.


## StatusesController

`final readonly class Minn\Rest\StatusesController` · `public/minn/src/Minn/Rest/StatusesController.php`

wp/v2/statuses as the reference answers it (probe rest-statuses): every
post status that is not internal, a plugin's beside core's, by name,
and trash last. A visitor sees the public ones; someone who can edit a
type shown in REST sees them all, and only they may ask for the edit
context of the list.
Each status links to its posts and goes through rest_prepare_status.
Like wp/v2/types, the list is keyed by name, so _fields over the whole
of it keeps nothing.

- const `FIELDS` = `array (   'embed' =>    array (     0 => 'name',     1 => 'slug',   ),   'view' =>    array (     0 => 'name',     1 => 'public',     2 => 'queryable',     3 => 'slug',     4 => 'date_floating',   ),   'edit' =>    array (     0 => 'name',     1 => 'private',     2 => 'protected',     3 => 'public',     4 => 'queryable',     5 => 'show_in_list',     6 => 'slug',     7 => 'date_floating',   ), )` — The fields each context shows, in the reference's order.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/statuses (public)`

The statuses the caller may see.

### `single(Minn\Http\Request $request, string $status): Minn\Http\Response`

Route: `GET /wp/v2/statuses/{status:[\w-]+} (public)`

One status.

Internals: `item()` (private, line 85), `readable()` (private, line 109), `statuses()` (private, line 115), `context()` (private, line 120)


## Subjects

`final readonly class Minn\Rest\Subjects` · `public/minn/src/Minn/Rest/Subjects.php`

Whether the record a route capture names exists, for the policy gate to
ask before it judges the caller. A post kind reads its type from the
{base} capture when the route has one, a term kind its taxonomy; the
fixed kinds name their own. Status is not consulted: a trashed post
exists, and what the caller may do with it is the policy's question.

- const `POST_TYPES` = `array (   'posts' => 'post',   'pages' => 'page',   'blocks' => 'wp_block',   'media' => 'attachment',   'navigation' => 'wp_navigation',   'menu-items' => 'nav_menu_item', )`
- const `TAXONOMIES` = `array (   'categories' => 'category',   'tags' => 'post_tag',   'wp_pattern_category' => 'wp_pattern_category', )`

Used by: `Minn\Rest\PolicyGate`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db)
```


### `exists(Minn\Http\Subject $subject, int $id, array $captures): bool`

Whether the record exists.

- `@param array<string, string> $captures the route's captures, for the {base} a kind reads`

### `postOfType(int $id, string $type): bool`

Whether a post of the type exists.

### `termOf(int $id, string $taxonomy): bool`

Whether a term of the taxonomy exists.

Internals: `post()` (private, line 60), `term()` (private, line 65)


## Taxonomies

`final class Minn\Rest\Taxonomies` · `public/minn/src/Minn/Rest/Taxonomies.php`

The taxonomy registry the wp/v2 surface describes: the core set seeded
from the reference (data/taxonomies.json) with the reference's own
labels, capabilities, and visibility flags.

Used by: `Minn\Admin\StructureController`, `Minn\Rest\Embed`, `Minn\Rest\Services`

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


## TemplateObject

`final readonly class Minn\Rest\TemplateObject` · `public/minn/src/Minn/Rest/TemplateObject.php`

The wp/v2/templates and wp/v2/template-parts resource.

Used by: `Minn\Rest\Api`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TemplatesController`

```php
__construct(Minn\Theme\TemplateIndex $index, Minn\Content\Posts $posts, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `base(string $type): string`

The rest_base of a template type.

### `view(Minn\Theme\TemplateRecord $record, Minn\Rest\Context $context, ?string $as = NULL): array`

The wp/v2 template shape.

Internals: `links()` (private, line 90)


## TemplateRevisionsController

`final readonly class Minn\Rest\TemplateRevisionsController` · `public/minn/src/Minn/Rest/TemplateRevisionsController.php`

wp/v2/templates and wp/v2/template-parts revisions and autosaves: the
revision rows of a template the site saved, each shown as the template
it once was (the template's own shape, its parent's id, a self link and
a parent link). The template is judged before the caller, as on the
reference: one nobody saved is no parent, one only a theme file holds
cannot have revisions; then the caller must be signed in and able to
edit the saved row. The routes are listed ahead of the templates' own,
whose id pattern would take these paths too.

- const `ID` = `'([^\\/:<>\\*\\?"\\|]+(?:\\/[^\\/:<>\\*\\?"\\|]+)?)[\\/\\w%-]+'`
- const `READ_REFUSAL` = `'Sorry, you are not allowed to view revisions of this post.'`
- const `AUTOSAVES_REFUSAL` = `'Sorry, you are not allowed to view autosaves of this post.'`
- const `DELETE_REFUSAL` = `'Sorry, you are not allowed to delete revisions of this post.'`
- const `MANAGE_REFUSAL` = `'Sorry, you are not allowed to access the templates on this site.'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Theme\TemplateIndex $index, Minn\Rest\TemplateObject $object, Minn\Content\Revisions $revisions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `revisions(Minn\Http\Request $request, string $base, string $parent): Minn\Http\Response`

Route: `GET /wp/v2/{base:templates|template-parts}/{parent:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}/revisions (public)`

Every revision of a saved template, its autosaves among them, filtered, ordered and paged as the request asks (RevisionCollection).

### `revision(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:templates|template-parts}/{parent:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}/revisions/{id:[\d]+} (public)`

One revision of a saved template; a revision of another post is a mismatch.

### `deleteRevision(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:templates|template-parts}/{parent:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}/revisions/{id:[\d]+} (public)`

A template's revision deleted for good, answered as it was; never
trashed, so without force it is refused. One of another post is not
this template's to delete.

### `autosaves(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:templates|template-parts}/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}/autosaves (public)`

The autosaves of a saved template, every author's, newest first.

### `autosave(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:templates|template-parts}/{parent:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}/autosaves/{id:[\d]+} (public)`

The template's newest autosave, whoever saved it and whichever autosave id is asked for.

### `createAutosave(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:templates|template-parts}/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}/autosaves (cap edit_posts + edit_theme_options)`

An autosave of a saved template (probe rest-template-autosave): the
caller's own draft is saved in place, and published; any other keeps
one autosave per author, holding only what the body sends (a field it
leaves out is stored empty). The caller is judged before the template.
The reply is what was saved, shown as the template, in the edit context.

Internals: `readable()` (private, line 170), `saved()` (private, line 181), `shaped()` (private, line 201)


## TemplatesController

`final readonly class Minn\Rest\TemplatesController` · `public/minn/src/Minn/Rest/TemplatesController.php`

wp/v2/templates and wp/v2/template-parts: the block theme's templates as
one list, whether they come from the theme's files, from a row the site
saved over one, or from a plugin. Reading needs only edit_posts (the
reference lets an editor or author see the layout); changing anything
needs edit_theme_options.

- const `CREATE_BODY` = `array (   'slug' =>    array (     'description' => 'Unique slug identifying the template.',     'type' => 'string',     'minLength' => 1,     'pattern' => '[a-zA-Z0-9_\\%-]+',     'required' => true,   ), )` — What creating a template must send (the reference's create arguments).
- const `LOOKUP` = `array (   'slug' =>    array (     'description' => 'The slug of the template to get the fallback for',     'type' => 'string',     'required' => true,   ),   'is_custom' =>    array (     'description' => 'Indicates if a template is custom or part of the template hierarchy',     'type' => 'boolean',     'required' => false,   ),   'template_prefix' =>    array (     'description' => 'The template prefix for the created template. This is used to extract the main template type, e.g. in `taxonomy-books` extracts the `taxonomy`',     'type' => 'string',     'required' => false,   ), )`
- const `LIST` = `array (   'wp_id' =>    array (     'description' => 'Limit to the specified post id.',     'type' => 'integer',   ),   'area' =>    array (     'description' => 'Limit to the specified template part area.',     'type' => 'string',   ),   'post_type' =>    array (     'description' => 'Post type to get the templates for.',     'type' => 'string',   ), )` — The list filters both routes take, as the reference declares them.
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

### `lookupTemplate(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/templates/lookup (cap edit_posts)`

The template a slug would use: the first in its hierarchy the theme or the site has.

### `lookupPart(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/template-parts/lookup (cap edit_posts)`

The same lookup under the parts route: it searches templates, not parts, as the reference's does.

### `template(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts)`

One template.

### `part(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts)`

One template part.

### `createTemplate(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/templates (cap edit_posts + edit_theme_options)`

Creates a template.

### `createPart(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/template-parts (cap edit_posts + edit_theme_options)`

Creates a template part.

### `saveTemplate(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Route: `PUT /wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Route: `PATCH /wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Saves a template.

### `savePart(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Route: `PUT /wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Route: `PATCH /wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Saves a template part.

### `deleteTemplate(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Deletes a customised template.

### `deletePart(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+} (cap edit_posts + edit_theme_options)`

Deletes a customised template part.

Internals: `listing()` (private, line 148), `listed()` (private, line 174), `lookup()` (private, line 191), `single()` (private, line 214), `save()` (private, line 223), `create()` (private, line 241), `fields()` (private, line 263), `delete()` (private, line 282), `trashed()` (private, line 299), `record()` (private, line 321), `readable()` (private, line 331), `requireWrite()` (private, line 342), `text()` (private, line 351)


## TermCollectionParams

`final class Minn\Rest\TermCollectionParams` · `public/minn/src/Minn/Rest/TermCollectionParams.php` · implements `Minn\Http\RouteParams`

A taxonomy's term list parameters as the reference declares them (probe
rest-term-lists): the context and Args::TERMS, a hierarchical taxonomy's
with a parent and no offset, a flat one's with an offset and no parent.
Plugins change them through rest_{taxonomy}_collection_params when the
list runs.

Used by: `Minn\Rest\DeclaredTermsController`, `Minn\Rest\TermsController`

### static `for(array $captures): array`

The parameters of the list a {base} capture names; none for a base no REST taxonomy has.


## TermFilters

`final class Minn\Rest\TermFilters` · `public/minn/src/Minn/Rest/TermFilters.php`

A term a REST read answers with, as plugin code filters it on the
reference (probe rest-term-filters): through get_term and
get_{taxonomy}. What a filter changes (a count, a name) is what the
answer shows. Without the runtime, or with nothing hooked, the engine's
own row stands. (Lists run get_terms itself.)

Used by: `Minn\Rest\TermsController`

### static `one(Minn\Content\TermRecord $term, string $taxonomy): Minn\Content\TermRecord`

A term as get_term hands it back.

Internals: `record()` (private, line 32)


## TermListArgs

`final class Minn\Rest\TermListArgs` · `public/minn/src/Minn/Rest/TermListArgs.php`

The get_terms arguments a term list request makes, as the reference
makes them before rest_{taxonomy}_query (probe rest-term-lists): the
taxonomy, each declared parameter the request carries under its query
name (include_slugs ordering as slug__in), the offset (the page's unless
the list takes one and it is given), the parent a hierarchical list
takes, and ids alone for a HEAD request.

- const `MAPPINGS` = `array (   'exclude' => 'exclude',   'include' => 'include',   'order' => 'order',   'orderby' => 'orderby',   'post' => 'post',   'hide_empty' => 'hide_empty',   'per_page' => 'number',   'search' => 'search',   'slug' => 'slug', )`

Used by: `Minn\Rest\MenuLists`, `Minn\Rest\TermsController`

### static `of(Minn\Objects\RestRequest $wp, array $registered, string $taxonomy, string $method): array`

The arguments before plugins see them.

- `@param array<string, mixed> $registered the collection, as rest_{taxonomy}_collection_params left it`
- `@return array<string, mixed>`


## TermObject

`final readonly class Minn\Rest\TermObject` · `public/minn/src/Minn/Rest/TermObject.php`

The wp/v2 category and tag objects.

- const `TAXONOMIES` = `array (   'categories' =>    array (     'taxonomy' => 'category',     'has_parent' => true,     'post_arg' => 'categories',     'post_base' => 'posts',   ),   'tags' =>    array (     'taxonomy' => 'post_tag',     'has_parent' => false,     'post_arg' => 'tags',     'post_base' => 'posts',   ),   'wp_pattern_category' =>    array (     'taxonomy' => 'wp_pattern_category',     'has_parent' => false,     'post_arg' => 'wp_pattern_category',     'post_base' => 'blocks',   ), )`

Used by: `Minn\Rest\Api`, `Minn\Rest\PolicyGate`, `Minn\Rest\Services`, `Minn\Rest\TermCollectionParams`, `Minn\Rest\TermsController`

```php
__construct(Minn\Db $db, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `view(Minn\Content\TermRecord $term, string $restBase): array`

A term as its REST base shows it, through rest_prepare_{taxonomy} when a plugin hooks it.

### `url(): Minn\Rest\RestUrl`

The REST URL builder.

### static `config(string $restBase): array`

The taxonomy behind a rest_base.

- `@return array{taxonomy: string, has_parent: bool, post_arg: string, post_base: string}`

### static `registered(string $restBase): ?array`

A taxonomy plugin code registered to show in REST under wp/v2, by its
REST base (probe rest-plugin-types), as a term route's config: its
terms nest when it is hierarchical, and its posts are those of the
first type it belongs to.

- `@return array{taxonomy: string, has_parent: bool, post_arg: string, post_base: string}|null`

Internals: `viewFields()` (private, line 81), `allowedVerbs()` (private, line 114)


## TermsController

`final readonly class Minn\Rest\TermsController` · `public/minn/src/Minn/Rest/TermsController.php`

wp/v2 categories, tags, and pattern categories: list, single, and the create/update/delete the taxonomy admin drives.

Used by: `Minn\Rest\Api`, `Minn\Rest\DeclaredTermsController`, `Minn\Rest\PolicyGate`

```php
__construct(Minn\Db $db, Minn\Content\Terms $terms, Minn\Content\Site $site, Minn\Rest\TermObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:categories|tags|wp_pattern_category} (public)`

A taxonomy's term list as the reference serves it: the request's
get_terms (wp_get_object_terms for a post's) through
rest_{taxonomy}_collection_params and rest_{taxonomy}_query, counted
by wp_count_terms without the page.

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+} (public; term {id} must exist; edit context: cap manage_categories)`

One category or tag.

### `create(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `POST /wp/v2/{base:categories} (cap manage_categories)`

Route: `POST /wp/v2/{base:tags|wp_pattern_category} (cap edit_posts)`

Tags and pattern categories are open to edit_posts holders; categories need manage_categories.

### `update(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+} (cap manage_categories; term {id} must exist)`

Route: `PUT /wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+} (cap manage_categories; term {id} must exist)`

Route: `PATCH /wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+} (cap manage_categories; term {id} must exist)`

Updates a category or tag.

### `delete(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/{base:categories|tags|wp_pattern_category}/{id:[\d]+} (cap manage_categories; term {id} must exist)`

The default category is capability-denied before the force check.

### static `createCapability(string $taxonomy): string`

What creating a term needs (probe rest-plugin-caps): a plugin's
taxonomy, its edit_terms when hierarchical and its assign_terms when
flat; categories, manage_categories; the others, edit_posts.

Internals: `requireParent()` (private, line 159), `termsReadable()` (private, line 213)


## Types

`final class Minn\Rest\Types` · `public/minn/src/Minn/Rest/Types.php`

The engine's registry of built-in post types, seeded from the observed
contract (src/data/types.json) with _links attached at runtime.

Used by: `Minn\Admin\AdminTypes`, `Minn\Admin\StructureController`, `Minn\Engine`, `Minn\Rest\Api`, `Minn\Rest\BatchController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\MediaObject`, `Minn\Rest\PolicyGate`, `Minn\Rest\RouteCatalogue`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\SearchController`, `Minn\Rest\Services`

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

### `declaredBases(): array`

The rest_base of every type an extension declared or plugin code registered under wp/v2. @return list<string>

- `@return list<string>`

### `routeBases(): array`

Every base the {base} routes serve: the declared post types' and the
taxonomies plugin code registered to show in REST under wp/v2.

- `@return list<string>`

### `isDeclared(string $slug): bool`

Whether the engine serves this type through its {base} routes: an extension declared it, or plugin code registered it to show in REST under wp/v2 (probe rest-plugin-types).

### `searchable(): array`

The types a post search covers: the built-in post and page, the ones
extensions declare, and the public types plugin code shows in REST,
as the reference's post search handler takes them.

- `@return list<string>`

### `restBase(string $slug): string`

The rest_base of a type slug.

Internals: `core()` (private, line 39), `registered()` (private, line 80), `servedRegistered()` (private, line 183)


## UrlDetails

`final class Minn\Rest\UrlDetails` · `public/minn/src/Minn/Rest/UrlDetails.php`

A page's details as the editor's link previews read them (probe
rest-embed-details), from the document's head: everything up to its
closer, the whole page when it has none. The first title, as text with
its tags gone; the first link whose rel names "icon" (not an
apple-touch-icon); the first meta named description; the first meta
whose property is og:image or og:image:url. Twitter tags, og:title and
og:description count for nothing. Each is trimmed. An address with no
host hangs off the page's root whatever its own path, and one with no
scheme takes the page's.

### static `of(string $html, string $url): array`

The page's four details, read from its markup; addresses made whole against the page's own. @return array{title: string, icon: string, description: string, image: string}

- `@return array{title: string, icon: string, description: string, image: string}`

Internals: `read()` (private, line 48), `text()` (private, line 64), `absolute()` (private, line 70)


## UserCollectionParams

`final class Minn\Rest\UserCollectionParams` · `public/minn/src/Minn/Rest/UserCollectionParams.php` · implements `Minn\Http\RouteParams`

The users list's parameters as the reference declares them (probe
rest-user-lists): the context, Args::USERS, and has_published_posts
taking the REST post types by name. Plugins change them through
rest_user_collection_params when the list runs.

Used by: `Minn\Rest\UsersController`

### static `for(array $captures): array`

The list's parameters (the route has no captures).


## UserListArgs

`final class Minn\Rest\UserListArgs` · `public/minn/src/Minn/Rest/UserListArgs.php`

The WP_User_Query arguments a user list request makes, as the reference
makes them before rest_user_query (probe rest-user-lists): each declared
parameter the request carries under its query name (a search wrapped in
wildcards), the offset (the page's when none is given), the orderby name
mapped to the query's, the published authors (every REST post type, for
a reader who may not list users), the authors shorthand, the search
columns (a reader who may not list users searches names and logins
only), and ids alone for a HEAD request.

- const `MAPPINGS` = `array (   'exclude' => 'exclude',   'include' => 'include',   'order' => 'order',   'per_page' => 'number',   'search' => 'search',   'roles' => 'role__in',   'capabilities' => 'capability__in',   'slug' => 'nicename__in', )`
- const `ORDERBY` = `array (   'id' => 'ID',   'include' => 'include',   'name' => 'display_name',   'registered_date' => 'registered',   'slug' => 'user_nicename',   'include_slugs' => 'nicename__in',   'email' => 'user_email',   'url' => 'user_url', )`
- const `COLUMNS` = `array (   'email' => 'user_email',   'name' => 'display_name',   'id' => 'ID',   'username' => 'user_login',   'slug' => 'user_nicename', )`
- const `PUBLIC_COLUMNS` = `array (   0 => 'ID',   1 => 'user_login',   2 => 'user_nicename',   3 => 'display_name', )`

Used by: `Minn\Rest\UsersController`

### static `of(Minn\Objects\RestRequest $wp, array $registered, string $method, array $types, string $reader): array`

The arguments before plugins see them, for a reader who is a 'lister'
(may list users) or 'public'.

- `@param array<string, mixed> $registered the collection, as rest_user_collection_params left it`
- `@param array<string, string> $types the REST post types, by name`
- `@return array<string, mixed>`


## UserObject

`final readonly class Minn\Rest\UserObject` · `public/minn/src/Minn/Rest/UserObject.php`

The wp/v2 user objects: the public view shape and the edit-context shape.

Used by: `Minn\Blocks\Dynamic\LatestComments`, `Minn\Rest\Api`, `Minn\Rest\CommentObject`, `Minn\Rest\Services`, `Minn\Rest\UsersController`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `view(Minn\Content\UserRecord $u): array`

A user as the view context shows one, through rest_prepare_user when a plugin hooks it.

### `edit(Minn\Content\UserRecord $u): array`

A user as the edit context shows one, through rest_prepare_user when a plugin hooks it.

### static `avatarUrls(string $email): array`

Gravatar URLs in the sizes the reference emits (sha256 of the email).

Internals: `viewFields()` (private, line 50), `editFields()` (private, line 79)


## UsersController

`final readonly class Minn\Rest\UsersController` · `public/minn/src/Minn/Rest/UsersController.php`

wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Rest\UserObject $object, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Auth\Roles $roles, Minn\Rest\Schema $schema)
```


### `me(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/users/me (public)`

The signed-in user.

### `updateMe(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/users/me (signed in)`

Route: `PUT /wp/v2/users/me (signed in)`

Route: `PATCH /wp/v2/users/me (signed in)`

Updates the signed-in user; signed out there is no such user (404), and the Allow header leaves the writes out.

### `deleteMe(Minn\Http\Request $request): Minn\Http\Response`

Route: `DELETE /wp/v2/users/me (cap delete_users)`

Deletes the signed-in user as users/{id} deletes any; signed out there is no such user (404).

### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/users (public)`

The users list as the reference serves it: the request's WP_User_Query
through rest_user_collection_params and rest_user_query (a reader who
may not list users sees published authors only), its totals (counted
again without the page when it found none), and the users it found.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/users/{id:[\d]+} (public; user {id} must exist)`

One user.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/users (cap create_users)`

Engine-created users carry real scheme hashes and the full default meta set.

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/users/{id:[\d]+} (cap edit_user on {id}; user {id} must exist)`

Route: `PUT /wp/v2/users/{id:[\d]+} (cap edit_user on {id}; user {id} must exist)`

Route: `PATCH /wp/v2/users/{id:[\d]+} (cap edit_user on {id}; user {id} must exist)`

Updates a user.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/users/{id:[\d]+} (cap delete_users; user {id} must exist)`

reassign is REQUIRED (checked before the user lookup), and so is force.

Internals: `listAllowed()` (private, line 115), `totals()` (private, line 139), `editsAuthoredPosts()` (private, line 176), `hasPublishedContent()` (private, line 187), `validRole()` (private, line 200), `assignableRole()` (private, line 209), `validEmail()` (private, line 218), `loginRefusal()` (private, line 404)


## ViewConfigPatch

`final class Minn\Rest\ViewConfigPatch` · `public/minn/src/Minn/Rest/ViewConfigPatch.php`

A patch to a view configuration (WP_View_Config_Data; probe
rest-view-config), applied as the reference applies it. Only schema
version 1 is taken, and only the documented keys: each is set (its value
replaced), merged (maps merged key by key, lists joined with their
members matched by identity), replaced (merged, but lists replaced
whole) or removed (keys from a map, members from a list by identity).
A null in a patch removes what it names. What a caller got wrong comes
back as problems: the function to name and the words.

- const `KEYS` = `array (   0 => 'default_view',   1 => 'default_layouts',   2 => 'view_list',   3 => 'form', )`
- const `SHAPE` = `'A view configuration patch value must match the shape of the value it patches: a list merges into a list, and an associative array into an associative array.'`


### static `apply(array $config, array $patch, mixed $version, string $mode): array`

A configuration with a patch applied in a mode (set, merge, replace,
remove), and the problems met: [function or null for the mode's own
method, words].

- `@param array<string, mixed> $config`
- `@param array<mixed> $patch`
- `@return array{0: array<string, mixed>, 1: list<array{0: ?string, 1: string}>}`

### static `stripNulls(mixed $value): mixed`

A value with every null in it removed, lists closed up.

### static `identity(mixed $item): ?string`

The identity a list member is matched by: a word itself, a map's id or slug; null for none.

Internals: `merge()` (private, line 104), `mergeList()` (private, line 134), `remove()` (private, line 149)


## WidgetObject

`final readonly class Minn\Rest\WidgetObject` · `public/minn/src/Minn/Rest/WidgetObject.php`

A widget as wp/v2/widgets shows it (probe rest-widgets): its id and base,
its sidebar, its markup as that sidebar wraps it (nothing among the
inactive widgets) and, in the edit context, its settings form and its
settings: serialized and base64 encoded, signed with wp_hash, and raw
when the widget shows its instance in REST. Links to itself, its type
and its sidebar; through rest_prepare_widget.

Used by: `Minn\Rest\Api`, `Minn\Rest\WidgetsController`

```php
__construct(Minn\Rest\RestUrl $url)
```


### `view(string $widgetId, string $sidebarId, Minn\Rest\Context $context): array`

A widget in a sidebar, its form and settings in the edit context. @return array<string, mixed>

- `@return array<string, mixed>`

### static `instance(Minn\Objects\Widget $object, int $number): array`

A widget's settings, encoded and signed, and raw when it shows them. @return array<string, mixed>

- `@return array<string, mixed>`

### static `runUpdate(string $idBase, array $fields): void`

A widget that is not a widget object saved or deleted through the
update callback its control registered, the fields it is given
posted as the form while it runs (probe blocks-rest-widget-doubts).

- `@param array<string, mixed> $fields`

Internals: `form()` (private, line 83)


## WidgetsController

`final readonly class Minn\Rest\WidgetsController` · `public/minn/src/Minn/Rest/WidgetsController.php`

wp/v2/widgets as the reference answers it (probe rest-widgets), for
anyone who can edit theme options: the registered widgets of every
sidebar (or one), a widget created under the next free number for its
type, its settings saved through the widget's own update (raw when the
widget shows its instance in REST, encoded with a matching hash, or from
its form's fields) and registered at once, a widget moved between
sidebars, and a widget deleted, or without force sent to the inactive
widgets.

- const `LIST` = `array (   'sidebar' =>    array (     'description' => 'The sidebar to return widgets for.',     'type' => 'string',     'required' => false,   ), )`
- const `BODY` = `array (   'id_base' =>    array (     'description' => 'The type of the widget. Corresponds to ID in widget-types endpoint.',     'type' => 'string',     'required' => false,   ),   'sidebar' =>    array (     'description' => 'The sidebar the widget belongs to.',     'type' => 'string',     'required' => false,   ),   'instance' =>    array (     'description' => 'Instance settings of the widget, if supported.',     'type' => 'object',     'required' => false,   ),   'form_data' =>    array (     'description' => 'URL-encoded form data from the widget admin form. Used to update a widget that does not support instance. Write only.',     'type' => 'string',     'required' => false,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\WidgetObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/widgets (cap edit_theme_options)`

The registered widgets, sidebar by sidebar.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/widgets/{id:[\w\-]+} (cap edit_theme_options)`

One widget.

### `create(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /wp/v2/widgets (cap edit_theme_options)`

Creates a widget in a sidebar (the inactive widgets unless one is named).

### `update(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /wp/v2/widgets/{id:[\w\-]+} (cap edit_theme_options)`

Route: `PUT /wp/v2/widgets/{id:[\w\-]+} (cap edit_theme_options)`

Route: `PATCH /wp/v2/widgets/{id:[\w\-]+} (cap edit_theme_options)`

Saves a widget's settings and moves it, as the body asks.

### `delete(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /wp/v2/widgets/{id:[\w\-]+} (cap edit_theme_options)`

Deletes a widget with force; without, sends it to the inactive widgets.

Internals: `saveInstance()` (private, line 151), `newInstance()` (private, line 168), `sidebarOf()` (private, line 192)

