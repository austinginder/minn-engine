# `Minn\Rest`

the wp/v2 surface: shapes and controllers

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AbilitiesController`](#abilitiescontroller) | final readonly class | 150 | wp-abilities/v1: what this site can be asked to do, and the doing of it. |
| [`Api`](#api) | final readonly class | 268 | The REST API: wires the controllers for one request and dispatches a |
| [`ApplicationPasswordsController`](#applicationpasswordscontroller) | final readonly class | 186 | wp/v2/users/{id}/application-passwords: list, create, rename, delete, |
| [`ArgCheck`](#argcheck) | final readonly class | 83 | Judges a route's declared arguments against the request before the |
| [`BatchController`](#batchcontroller) | final readonly class | 159 | batch/v1 as the reference answers it (probe rest-batch): up to 25 |
| [`BatchRequest`](#batchrequest) | final class | 32 | The requests a batch payload names, normalised into descriptors the |
| [`BlockRendererController`](#blockrenderercontroller) | final readonly class | 89 | wp/v2/block-renderer as the reference answers it (probe |
| [`BlockTypesController`](#blocktypescontroller) | final readonly class | 117 | wp/v2/block-types as the reference answers it (probe rest-block-types): |
| [`BlocksController`](#blockscontroller) | final readonly class | 69 | wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block |
| [`Caller`](#caller) | final class | 143 | Who is making this REST call. Resolved once from the cookie and nonce; |
| [`Catalogue`](#catalogue) | final class | 70 | The route table read from the classes alone: every #[Route] under |
| [`CommentListArgs`](#commentlistargs) | final class | 35 | The WP_Comment_Query arguments a comment list request makes, as the |
| [`CommentObject`](#commentobject) | final readonly class | 87 | The wp/v2 comment object; edit context adds the moderation-desk fields. |
| [`CommentsController`](#commentscontroller) | final readonly class | 336 | wp/v2/comments: the status tabs with pagination headers, single, |
| [`Context`](#context) | enum | 18 | The view a REST caller asked for. View is the public shape, edit adds the |
| [`DeclaredPostsController`](#declaredpostscontroller) | final readonly class | 56 | wp/v2/{rest_base} for extra post types declared by an active extension. |
| [`DeclaredTermsController`](#declaredtermscontroller) | final readonly class | 43 | wp/v2/{rest_base} for the taxonomies plugin code registers to show in |
| [`Embed`](#embed) | final class | 201 | The _embed decoration and the embed context. Every embeddable link in an |
| [`EngineRoutes`](#engineroutes) | final class | 66 | The engine's own REST routes in the reference's regex form, for the |
| [`Fields`](#fields) | final readonly class | 92 | The _fields response filter. Dot paths descend ("title.rendered"); the |
| [`GlobalStylesController`](#globalstylescontroller) | final readonly class | 138 | wp/v2/global-styles: the site editor's saved styles (one post per |
| [`GlobalStylesObject`](#globalstylesobject) | final readonly class | 87 | The wp/v2/global-styles item, theme, and revision shapes. |
| [`IndexController`](#indexcontroller) | final readonly class | 56 | The API index at /wp-json/: the site facts monitors read (name, url, |
| [`InstalledThemesController`](#installedthemescontroller) | final readonly class | 128 | wp/v2/themes as the reference answers it (probe rest-themes): the |
| [`Links`](#links) | final class | 52 | Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along. |
| [`LiveSettings`](#livesettings) | final readonly class | 77 | wp/v2/settings with plugins loaded, served from the registered settings |
| [`MediaController`](#mediacontroller) | final readonly class | 305 | wp/v2/media: list, single, upload on both transports (multipart field |
| [`MediaObject`](#mediaobject) | final readonly class | 164 | The wp/v2 media object, view and edit context. |
| [`MenuItemObject`](#menuitemobject) | final readonly class | 74 | The wp/v2/menu-items resource. |
| [`MenuObject`](#menuobject) | final readonly class | 37 | The wp/v2/menus resource: a nav_menu term plus locations and auto_add. |
| [`MenusController`](#menuscontroller) | final readonly class | 312 | wp/v2/menus, menu-items, and menu-locations. Viewing needs edit_posts; |
| [`NavigationController`](#navigationcontroller) | final readonly class | 48 | wp/v2/navigation: the block theme's navigation menus, stored as |
| [`OEmbedController`](#oembedcontroller) | final readonly class | 72 | oembed/1.0 as the reference answers it (probe oembed). embed is the |
| [`ParamCheck`](#paramcheck) | final class | 76 | The required / validate / sanitize pass over a request's declared arguments. |
| [`PluginsController`](#pluginscontroller) | final readonly class | 244 | wp/v2 plugins: what sits in wp-content/plugins, in the reference's |
| [`PolicyGate`](#policygate) | final readonly class | 147 | Judges a route's policy against the caller, with the reference's |
| [`PostCollectionParams`](#postcollectionparams) | final class | 211 | A post type's collection parameters as the reference builds them for its |
| [`PostListArgs`](#postlistargs) | final class | 174 | The WP_Query arguments a post list request makes, as the reference makes |
| [`PostObject`](#postobject) | final readonly class | 530 | Builds the wp/v2 post and page objects in the reference's shape: the |
| [`PostsController`](#postscontroller) | final readonly class | 191 | wp/v2 posts and pages, read side. |
| [`PostsWriteController`](#postswritecontroller) | final readonly class | 459 | wp/v2 posts and pages, write side: create, update, trash, and force |
| [`RegisteredFields`](#registeredfields) | final class | 105 | The fields plugin code adds to an object type with register_rest_field, |
| [`RegisteredPostFields`](#registeredpostfields) | final readonly class | 89 | The REST object of a post whose type plugin code registered (probe rest-plugin-types), built by what the type supports. |
| [`RegisteredType`](#registeredtype) | final readonly class | 46 | A post type plugin code registered, as its REST object follows it |
| [`RenderedFields`](#renderedfields) | final class | 65 | A post's rendered title, content and excerpt as a REST response carries |
| [`Reply`](#reply) | final class | 53 | JSON responses in the reference's shape: its header set, its json_encode |
| [`RestMeta`](#restmeta) | final class | 238 | An object's meta field over REST, from the keys registered to show |
| [`RestUrl`](#resturl) | final readonly class | 38 | REST URLs in the form the reference emits for the site's permalink mode: |
| [`RevisionsController`](#revisionscontroller) | final readonly class | 142 | wp/v2 revisions and autosaves under posts, pages, and blocks. |
| [`RouteArgs`](#routeargs) | final class | 36 | The argument groups a route registers with, filled the way register_rest_route fills them. |
| [`RouteCatalogue`](#routecatalogue) | final readonly class | 61 | The routes the engine serves, described in the reference's shape for the |
| [`RouteIndex`](#routeindex) | final class | 61 | The description of one route the REST index publishes: namespace, methods, endpoints with their argument schemas, self link. |
| [`RouteMatch`](#routematch) | final class | 68 | Finds the registered handler for a method and path among the runtime's route table. |
| [`RouteTable`](#routetable) | final class | 38 | The registered endpoints in dispatch shape: one handler list per route, methods as a set, non-numeric keys lifted into the route's options. |
| [`RuntimeEnvelope`](#runtimeenvelope) | final readonly class | 123 | The REST server's filters around one of Minn's own routes, as the |
| [`RuntimePrepare`](#runtimeprepare) | final class | 70 | An item a REST response carries, through the filter the reference runs |
| [`RuntimeRoutes`](#runtimeroutes) | final class | 406 | Routes plugin code registered with register_rest_route(), answered |
| [`Schema`](#schema) | final readonly class | 473 | JSON-schema handling the way the REST API's argument validation does it: |
| [`SchemaValues`](#schemavalues) | final class | 206 | The value side of JSON Schema, as the reference applies it: what counts |
| [`SearchController`](#searchcontroller) | final readonly class | 121 | wp/v2 search over published content: id, title, url, type, and the |
| [`Services`](#services) | final class | 387 | The objects one REST request shares, each made once, on first use, from |
| [`Settings`](#settings) | final readonly class | 113 | The registered settings the Settings views read and write, mapped to |
| [`SettingsController`](#settingscontroller) | final readonly class | 32 | wp/v2/settings: read and write, both behind manage_options; with plugins loaded, every registered setting (LiveSettings). |
| [`SidebarsController`](#sidebarscontroller) | final readonly class | 192 | wp/v2/sidebars and wp/v2/widget-types as the reference answers them |
| [`StatusesController`](#statusescontroller) | final readonly class | 97 | wp/v2/statuses as the reference answers it (probe rest-statuses): every |
| [`Subjects`](#subjects) | final readonly class | 53 | Whether the record a route capture names exists, for the policy gate to |
| [`Taxonomies`](#taxonomies) | final class | 48 | The taxonomy registry the wp/v2 surface describes: the core set seeded |
| [`TaxonomiesController`](#taxonomiescontroller) | final readonly class | 48 | wp/v2 taxonomies: the registry, whole or per type, in view or edit context. |
| [`TemplateObject`](#templateobject) | final readonly class | 100 | The wp/v2/templates and wp/v2/template-parts resource. |
| [`TemplatesController`](#templatescontroller) | final readonly class | 249 | wp/v2/templates and wp/v2/template-parts: the block theme's templates as |
| [`TermCollectionParams`](#termcollectionparams) | final class | 13 | A taxonomy's term list parameters as the reference declares them (probe |
| [`TermFilters`](#termfilters) | final class | 25 | A term a REST read answers with, as plugin code filters it on the |
| [`TermListArgs`](#termlistargs) | final class | 28 | The get_terms arguments a term list request makes, as the reference |
| [`TermObject`](#termobject) | final readonly class | 107 | The wp/v2 category and tag objects. |
| [`TermsController`](#termscontroller) | final readonly class | 203 | wp/v2 categories, tags, and pattern categories: list, single, and the create/update/delete the taxonomy admin drives. |
| [`Types`](#types) | final class | 179 | The engine's registry of built-in post types, seeded from the observed |
| [`TypesController`](#typescontroller) | final readonly class | 70 | wp/v2 types. In the edit context (probe rest-types-edit) a type adds its |
| [`UserCollectionParams`](#usercollectionparams) | final class | 11 | The users list's parameters as the reference declares them (probe |
| [`UserListArgs`](#userlistargs) | final class | 47 | The WP_User_Query arguments a user list request makes, as the reference |
| [`UserObject`](#userobject) | final readonly class | 106 | The wp/v2 user objects: the public view shape and the edit-context shape. |
| [`UsersController`](#userscontroller) | final readonly class | 342 | wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives. |
| [`WidgetObject`](#widgetobject) | final readonly class | 55 | A widget as wp/v2/widgets shows it (probe rest-widgets): its id and base, |
| [`WidgetsController`](#widgetscontroller) | final readonly class | 169 | wp/v2/widgets as the reference answers it (probe rest-widgets), for |

## AbilitiesController

`final readonly class Minn\Rest\AbilitiesController` · `public/minn/src/Minn/Rest/AbilitiesController.php`

wp-abilities/v1: what this site can be asked to do, and the doing of it.

An ability is a named unit of work with an input schema, an output
schema, and a permission callback, registered by core or by a plugin.
The catalogue is what an agent reads first, so the shapes here are the
reference's, captured; the engine's own registry answers them.

Reading the catalogue needs only a session. Running one is the ability's
own decision, through its permission callback, and a read-only ability
runs on GET, since running it changes nothing.

- const `SIGNED_IN` = `array (   0 => 'rest_forbidden',   1 => 'Sorry, you are not allowed to do that.', )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `abilities(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-abilities/v1/abilities (signed in)`

Every registered ability, narrowed to one category when asked.

### `run(Minn\Http\Request $request, string $name): Minn\Http\Response`

Route: `GET /wp-abilities/v1/abilities/{name:[a-zA-Z0-9\-\/]+?}/run (signed in)`

Route: `POST /wp-abilities/v1/abilities/{name:[a-zA-Z0-9\-\/]+?}/run (signed in)`

Runs an ability. A read-only one takes GET and its input from the
query; anything else takes POST and its input from the body. The
ability's own permission callback decides who may.

### `ability(Minn\Http\Request $request, string $name): Minn\Http\Response`

Route: `GET /wp-abilities/v1/abilities/{name:[a-zA-Z0-9\-\/]+} (signed in)`

One ability by name.

### `categories(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-abilities/v1/categories (signed in)`

Every ability category.

### `category_(Minn\Http\Request $request, string $slug): Minn\Http\Response`

Route: `GET /wp-abilities/v1/categories/{slug:[a-z0-9]+(?:-[a-z0-9]+)*} (signed in)`

One category by slug.

Internals: `boot()` (private, line 116), `find()` (private, line 124), `object()` (private, line 139), `category()` (private, line 164)


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
the in-process calls plugin code makes, or as the user plugin code
settled (determine_current_user; 0 for nobody); a user who no longer
exists leaves the caller as the request itself resolves it.

### `handle(string $route): Minn\Http\Response`

Resolves a REST route (from the path or from ?rest_route=) to a response.

### `withDiscovery(Minn\Http\Response $response): Minn\Http\Response`

A REST answer as it leaves over HTTP: pointing at the API's root, unless it carries a Link header of its own (a batch's parts do not).

### `handleEngineOnly(string $route, ?WP_REST_Request $as = NULL): ?Minn\Http\Response`

The engine's own answer to a route, or null when no engine route
takes it; the runtime's table is never consulted. This is what the
runtime's server calls for a core route, so a route the engine
declines cannot bounce between the two. $as is the caller's own
request object, which the route's parameters are set on.

Internals: `controllers()` (private, line 73), `engineResponse()` (private, line 241), `withPageLinks()` (private, line 255), `options()` (private, line 286), `withAllow()` (private, line 299)


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

Internals: `subject()` (private, line 148), `existing()` (private, line 172), `validate()` (private, line 181), `item()` (private, line 191), `when()` (private, line 208)


## ArgCheck

`final readonly class Minn\Rest\ArgCheck` · `public/minn/src/Minn/Rest/ArgCheck.php`

Judges a route's declared arguments against the request before the
policy is judged, the way the reference does: a required parameter that
did not arrive is rest_missing_callback_param; the shared collection
parameters (context, page, per_page, search) are judged first and a
refusal among them is answered alone; then every other invalid one is
listed in rest_invalid_param, in the order the route declares them, with
the schema's own refusal under details. The query is read against the
route's args, the JSON body against its body set, and a JSON body that
does not parse is refused on every route first. An argument its handler
judges (Args::HANDLER_VALIDATES) is left to the handler.

Used by: `Minn\Rest\Api`, `Minn\Rest\BatchController`

```php
__construct(Minn\Rest\Schema $schema)
```


### `closure(): Closure`

The check as the router takes it.

### `check(Minn\Http\Route $route, Minn\Http\Request $request, array $captures = array ( )): void`

Throws the refusal the declared arguments earn, or returns. @param array<string, string> $captures what the path named, for a route whose parameters depend on it

- `@param array<string, string> $captures what the path named, for a route whose parameters depend on it`

Internals: `json()` (private, line 59), `missing()` (private, line 74), `round()` (private, line 88)


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

Internals: `refusal()` (private, line 86), `invalid()` (private, line 104), `claims()` (private, line 131), `batchablePath()` (private, line 141), `subRequest()` (private, line 159), `error()` (private, line 177), `envelope()` (private, line 183)


## BatchRequest

`final class Minn\Rest\BatchRequest` · `public/minn/src/Minn/Rest/BatchRequest.php`

The requests a batch payload names, normalised into descriptors the
caller turns into real request objects. A path's query string is split
off here so each sub-request carries its own query parameters, which is
what lets one round trip stand in for several.

### static `describe(mixed $requests): array`

The batch body's requests in the reference's normalised form.

- `@return list<array{method: string, path: string, query: array<string, mixed>, body: ?array, headers: ?array}>`


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

- const `ARGS` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'edit',     ),     'default' => 'view',     'required' => false,     'handler_validates' => true,   ),   'attributes' =>    array (     'description' => 'Attributes for the block.',     'type' => 'object',     'default' =>      array (     ),     'required' => false,     'handler_validates' => true,   ),   'post_id' =>    array (     'description' => 'ID of the post context.',     'type' => 'integer',     'required' => false,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Schema $schema, Minn\Rest\Caller $caller)
```


### `render(Minn\Http\Request $request, string $name): Minn\Http\Response`

Route: `GET /wp/v2/block-renderer/{name:[a-z0-9-]+/[a-z0-9-]+} (public)`

Route: `POST /wp/v2/block-renderer/{name:[a-z0-9-]+/[a-z0-9-]+} (public)`

The block's markup, as {"rendered": "..."}.

Internals: `refuseInvalid()` (private, line 64), `permit()` (private, line 82), `rendered()` (private, line 96)


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

Internals: `items()` (private, line 61), `item()` (private, line 75), `links()` (private, line 119), `requireViewer()` (private, line 134)


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

Route: `GET /wp/v2/blocks (public)`

The blocks the caller may edit; an empty list for anyone else.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/blocks/{id:[\d]+} (public; block {id} must exist)`

One block. Editing context is the posts machinery's own gate
(rest_forbidden_context); a view is refused to anyone who cannot edit
posts, and a trashed or unknown block is not found rather than refused.

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


## Caller

`final class Minn\Rest\Caller` · `public/minn/src/Minn/Rest/Caller.php`

Who is making this REST call. Resolved once from the cookie and nonce;
an anonymous or failed caller has id 0 and every capability check fails.

Used by: `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\OEmbedController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`

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

Internals: `resolve()` (private, line 157)


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

### static `of(WP_REST_Request $wp, array $registered, string $method): array`

The arguments before plugins see them: a parameter maps when the
collection (as rest_comment_collection_params left it) still has it.

- `@param array<string, mixed> $registered`
- `@return array<string, mixed>`


## CommentObject

`final readonly class Minn\Rest\CommentObject` · `public/minn/src/Minn/Rest/CommentObject.php`

The wp/v2 comment object; edit context adds the moderation-desk fields.

Used by: `Minn\Rest\CommentsController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Comments $comments, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `build(Minn\Content\CommentRecord $c, Minn\Rest\Context $context): array`

A comment in the context asked for, through rest_prepare_comment when a plugin hooks it.

### `url(): Minn\Rest\RestUrl`

The REST URL builder.

Internals: `buildFields()` (private, line 40)


## CommentsController

`final readonly class Minn\Rest\CommentsController` · `public/minn/src/Minn/Rest/CommentsController.php`

wp/v2/comments: the status tabs with pagination headers, single,
signed-in replies, moderation updates, trash, and force delete.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Comments $comments, Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Rest\CommentObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/comments (public)`

The comments list as the reference serves it: the request's
WP_Comment_Query through rest_comment_query, its totals (counted again
when a page comes back empty), and the comments the caller may read.

### `single(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/comments/{id:[\d]+} (public; comment {id} must exist)`

One comment, if the caller may read it.

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

Internals: `totals()` (private, line 76), `events()` (private, line 174), `allowed()` (private, line 256), `readablePost()` (private, line 293), `readable()` (private, line 302), `plainComment()` (private, line 312), `prepared()` (private, line 334), `cleanComment()` (private, line 354)


## Context

`enum Minn\Rest\Context` · `public/minn/src/Minn/Rest/Context.php`

The view a REST caller asked for. View is the public shape, edit adds the
raw halves and the caller's own action links, embed is the reduced shape
a linked resource carries when it rides inside another response.

Cases: `View` = `'view'`, `Edit` = `'edit'`, `Embed` = `'embed'`

Used by: `Minn\Rest\BlocksController`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenusController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\RevisionsController`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetObject`, `Minn\Rest\WidgetsController`

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
context=embed and placed under _embedded[rel]; a rel whose every embed
came back empty or as an error is left out, while an empty list beside a
full one stays (wp:term keeps its empty tags list next to the categories).
Lists have _fields applied per item before the links are read, so a
_fields value without _links embeds nothing there; a single object embeds
first and is filtered afterwards. The embed context is the view object
cut to the reference's key set for that kind of item.

- const `KEYS` = `array (   'post' =>    array (     0 => 'id',     1 => 'date',     2 => 'slug',     3 => 'type',     4 => 'link',     5 => 'title',     6 => 'excerpt',     7 => 'author',     8 => 'featured_media',     9 => '_links',   ),   'media' =>    array (     0 => 'id',     1 => 'date',     2 => 'slug',     3 => 'type',     4 => 'link',     5 => 'title',     6 => 'author',     7 => 'featured_media',     8 => 'caption',     9 => 'alt_text',     10 => 'media_type',     11 => 'mime_type',     12 => 'media_details',     13 => 'source_url',     14 => '_links',   ),   'user' =>    array (     0 => 'id',     1 => 'name',     2 => 'url',     3 => 'description',     4 => 'link',     5 => 'slug',     6 => 'avatar_urls',     7 => '_links',   ),   'term' =>    array (     0 => 'id',     1 => 'link',     2 => 'name',     3 => 'slug',     4 => 'taxonomy',     5 => '_links',   ),   'comment' =>    array (     0 => 'id',     1 => 'parent',     2 => 'author',     3 => 'author_name',     4 => 'author_url',     5 => 'date',     6 => 'content',     7 => 'link',     8 => 'type',     9 => 'author_avatar_urls',     10 => '_links',   ), )`

Used by: `Minn\Rest\Api`, `Minn\Rest\RegisteredFields`

```php
__construct(Minn\Http\Router $router, Minn\Rest\Types $types, Minn\Rest\Taxonomies $taxonomies)
```


### static `requested(array $query): ?array`

Null when _embed is absent; [] for every rel; otherwise the rels asked for. @return ?list<string>

- `@return ?list<string>`

### `decorate(Minn\Http\Request $request, Minn\Http\Response $response): Minn\Http\Response`

Decorates a finished response: the embed context, then _embedded, then the deferred _fields.

### static `shape(string $type, array $item): array`

An item cut to the embed shape of its object type (a post type, a taxonomy, attachment, user or comment); any other kept whole. @param array<string, mixed> $item @return array<string, mixed>

- `@param array<string, mixed> $item @return array<string, mixed>`

Internals: `decorateItem()` (private, line 95), `answer()` (private, line 127), `target()` (private, line 157), `kind()` (private, line 175), `taxonomyBase()` (private, line 191), `context()` (private, line 215)


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


## Fields

`final readonly class Minn\Rest\Fields` · `public/minn/src/Minn/Rest/Fields.php`

The _fields response filter. Dot paths descend ("title.rendered"); the
object's own key order is kept. Applied per item on list responses and to
the whole payload otherwise, which is why _fields on the associative
types response strips every key and yields [] over HTTP, a reference
quirk the engine reproduces by construction.

Used by: `Minn\Admin\SessionsController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`

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

The revisions of the saved styles, newest first; all of them unless per_page pages them.

### `revision(Minn\Http\Request $request, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/global-styles/{parent:[\d]+}/revisions/{id:[\d]+} (cap edit_theme_options; global styles parent {parent} must exist)`

One revision of the saved styles.

Internals: `post()` (private, line 113), `requireTheme()` (private, line 119), `requireParent()` (private, line 127), `title()` (private, line 140), `node()` (private, line 149)


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
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\RouteCatalogue $catalogue)
```


### `index(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET / (public)`

The REST index: namespaces, routes, and the site's description.

### `namespaceIndex(Minn\Http\Request $request, string $namespace): Minn\Http\Response`

Route: `GET /{namespace:[a-z0-9-]+/(?:v\d+|\d+\.\d+)} (public)`

One namespace's index, as the reference answers /wp-json/wp/v2 (or oembed/1.0): the
namespace, its routes (the namespace root among them), and the link
up to the root index. A namespace the engine does not serve is left
to the runtime, whose plugins may own it.


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

Internals: `item()` (private, line 76), `links()` (private, line 110), `viewsThemes()` (private, line 133), `viewsActive()` (private, line 139), `status()` (private, line 144), `requireRuntime()` (private, line 150)


## Links

`final class Minn\Rest\Links` · `public/minn/src/Minn/Rest/Links.php`

Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along.

### static `item(string $rel, string $href, array $attributes): array`

One link as a response serves it: its attributes, then its href; a
self link's target hints after the href, where the reference adds them.

- `@param array<string, mixed> $attributes`
- `@return array<string, mixed>`

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

Internals: `refuseInvalid()` (private, line 81)


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

Attachments cannot be trashed; force removes the row, its meta, and its files.

Internals: `restDate()` (private, line 47), `storeWithPlugins()` (private, line 122), `insertedWithPlugins()` (private, line 149), `preparedAttachment()` (private, line 187), `finishedWithPlugins()` (private, line 209), `params()` (private, line 225), `inserted()` (private, line 231), `attachment()` (private, line 325)


## MediaObject

`final readonly class Minn\Rest\MediaObject` · `public/minn/src/Minn/Rest/MediaObject.php`

The wp/v2 media object, view and edit context.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Posts $posts, Minn\Media\Uploads $uploads, Minn\Front\Permalinks $permalinks, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `build(Minn\Content\PostRecord $p, Minn\Rest\Context $context): array`

An attachment in the context asked for, through rest_prepare_attachment when a plugin hooks it.

### `url(): Minn\Rest\RestUrl`

The REST URL builder.

Internals: `buildFields()` (private, line 44), `details()` (private, line 125), `descriptionHtml()` (private, line 159)


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

Route: `GET /wp/v2/menus/{id:[\d]+} (cap edit_posts; menu {id} must exist)`

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

Route: `GET /wp/v2/menu-items (cap edit_posts)`

The items of a menu.

### `singleItem(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/menu-items/{id:[\d]+} (cap edit_posts; menu item {id} must exist)`

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

Route: `GET /wp/v2/menu-locations (cap edit_theme_options)`

The menu locations the theme and plugins register (none for a block theme without plugins), keyed by name; edit_theme_options to view.

### `oneLocation(Minn\Http\Request $request, string $location): Minn\Http\Response`

Route: `GET /wp/v2/menu-locations/{location:[\w-]+} (cap edit_theme_options)`

One menu location.

Internals: `locationItem()` (private, line 286), `titleFrom()` (private, line 302), `urlFrom()` (private, line 312), `refuse()` (private, line 322), `plain()` (private, line 332)


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

Route: `GET /wp/v2/navigation (public)`

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


## OEmbedController

`final readonly class Minn\Rest\OEmbedController` · `public/minn/src/Minn/Rest/OEmbedController.php`

oembed/1.0 as the reference answers it (probe oembed). embed is the
provider side: one of the site's own addresses as embed data (the post
through url_to_postid and oembed_request_post_id), "Not Found" for
anything else; format=xml is served as XML on the way out
(RuntimeRoutes::serve). proxy is the editor's consumer side, for anyone
who can edit posts: the site's own addresses answered locally, anything
else fetched through WP_oEmbed with the editor's size, its markup
through oembed_result, and kept in a transient for a day
(rest_oembed_ttl) under the request's arguments.

- const `EMBED` = `array (   'url' =>    array (     'description' => 'The URL of the resource for which to fetch oEmbed data.',     'type' => 'string',     'format' => 'uri',     'required' => true,   ),   'format' =>    array (     'default' => 'json',     'required' => false,   ),   'maxwidth' =>    array (     'default' => 600,     'required' => false,   ), )`
- const `PROXY` = `array (   'url' =>    array (     'description' => 'The URL of the resource for which to fetch oEmbed data.',     'type' => 'string',     'format' => 'uri',     'required' => true,   ),   'format' =>    array (     'description' => 'The oEmbed format to use.',     'type' => 'string',     'default' => 'json',     'enum' =>      array (       0 => 'json',       1 => 'xml',     ),     'required' => false,   ),   'maxwidth' =>    array (     'description' => 'The maximum width of the embed frame in pixels.',     'type' => 'integer',     'default' => 600,     'required' => false,   ),   'maxheight' =>    array (     'description' => 'The maximum height of the embed frame in pixels.',     'type' => 'integer',     'required' => false,   ),   'discover' =>    array (     'description' => 'Whether to perform an oEmbed discovery request for unsanctioned providers.',     'type' => 'boolean',     'default' => true,     'required' => false,   ), )`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Caller $caller)
```


### `embed(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /oembed/1.0/embed (public)`

One of the site's own posts as embed data.

### `proxy(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /oembed/1.0/proxy (public)`

Another site's embed, fetched for the editor.

Internals: `requireRuntime()` (private, line 92)


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
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Extension\Loader $extensions, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Ops\Packages $packages, string $contentDir)
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

Internals: `items()` (private, line 147), `find()` (private, line 162), `manifestFor()` (private, line 172), `extensionItem()` (private, line 182), `pluginItem()` (private, line 205), `text()` (private, line 237), `description()` (private, line 243), `uri()` (private, line 262), `links()` (private, line 267), `extensionKey()` (private, line 272)


## PolicyGate

`final readonly class Minn\Rest\PolicyGate` · `public/minn/src/Minn/Rest/PolicyGate.php`

Judges a route's policy against the caller, with the reference's
refusals: a caller who is not signed in gets the policy's sign-in code
at 401, a bad nonce is always 403 rest_cookie_invalid_nonce, and a
signed-in caller who lacks a capability gets the refusal code at 403.
A subject the policy names is looked up first and answers its 404
before any of those. The edit-context policy is judged as well when
the request asks for it.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Caller $caller, Minn\Rest\Subjects $subjects, Minn\Rest\Types $types)
```


### `closure(): Closure`

The judge as the router takes it.

### `judge(Minn\Http\Policy $policy, Minn\Http\Request $request, array $captures): void`

Throws the refusal the policy names, or returns.

- `@param array<string, string> $captures`

Internals: `subject()` (private, line 66), `type()` (private, line 86), `taxonomy()` (private, line 117), `capabilities()` (private, line 142), `own()` (private, line 156)


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

Used by: `Minn\Rest\DeclaredPostsController`, `Minn\Rest\MediaController`, `Minn\Rest\PostListArgs`, `Minn\Rest\PostsController`

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

### static `mediaTypes(): array`

The allowed MIME types by media type (the part before the slash), in
the order the site allows them, each once.

- `@return array<string, list<string>>`

Internals: `forType()` (private, line 58), `filtered()` (private, line 76), `type()` (private, line 95), `build()` (private, line 119), `statuses()` (private, line 194), `taxonomies()` (private, line 205), `termFilter()` (private, line 221)


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

Used by: `Minn\Rest\PostsController`

### static `of(WP_REST_Request $request, array $registered, string $type): array`

The arguments before plugins see them.

- `@param array<string, mixed> $registered the list's parameters`
- `@return array<string, mixed>`

### static `queryVars(array $args, WP_REST_Request $request): array`

The query variables from the arguments plugins left: each through
rest_query_var-{name}, and the list's orderby names as the query's.

- `@param array<string, mixed> $args`
- `@return array<string, mixed>`

Internals: `mimeTypes()` (private, line 101), `sticky()` (private, line 113), `taxonomies()` (private, line 127), `termClause()` (private, line 148), `formats()` (private, line 172)


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


### `view(Minn\Content\PostRecord $p): array`

The view-context object, through rest_prepare_{type} when a plugin hooks it.

### `edit(Minn\Content\PostRecord $p, int $userId): array`

The edit-context object, through rest_prepare_{type} when a plugin hooks it.

### `permalink(Minn\Content\PostRecord $p): string`

A post's permalink as of now: pretty once live with a slug, ?p= (or ?page_id=) before.

### static `restBase(string $type): string`

The rest_base of a type.

### `links(Minn\Content\PostRecord $p): array`

The _links of a post in the view context.

### `permalinkTemplate(Minn\Content\PostRecord $p): string`

The editor's sample permalink: the structure with the name token left
in place (pages: the parent path plus %pagename%), or the query form
when permalinks are plain.

### `modifiedUnsaved(Minn\Content\PostRecord $p, int $userId): bool`

Whether a live post carries an autosave newer than its saved revision.

### `lockHolder(int $id, int $userId): ?array`

The OTHER user holding a live _edit_lock (150 second window), or null.

### `editLinks(Minn\Content\PostRecord $p, int $userId, ?array $viewLinks = NULL): array`

The view links plus the caller's verbs and cap-gated wp:action-* entries.

### static `date(string $mysql): string`

A MySQL datetime in the reference's ISO form.

Internals: `meta()` (private, line 76), `navigationView()` (private, line 87), `blockView()` (private, line 117), `viewFields()` (private, line 144), `viewTerms()` (private, line 187), `typeFields()` (private, line 203), `classList()` (private, line 232), `format()` (private, line 254), `termLinks()` (private, line 298), `editFields()` (private, line 317), `allow()` (private, line 518), `gmt()` (private, line 537)


## PostsController

`final readonly class Minn\Rest\PostsController` · `public/minn/src/Minn/Rest/PostsController.php`

wp/v2 posts and pages, read side.

Used by: `Minn\Rest\Api`, `Minn\Rest\BlocksController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\MediaController`, `Minn\Rest\NavigationController`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Rest\PostObject $object, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request, string $base): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts} (public)`

Route: `GET /wp/v2/{base:pages} (public)`

The posts or pages list.

### `serveList(Minn\Http\Request $request, string $type, ?Closure $shape = NULL): Minn\Http\Response`

The list for any post type as the reference serves it (probe
rest-post-lists): the caller and the statuses judged, the request's
parameters sanitized, the query arguments through rest_{type}_query
and rest_query_var-*, a WP_Query (so pre_get_posts and every query
filter run), then each post the caller may read (or edit, in the edit
context) shaped (by $shape when the type has its own object, as
media does). The totals are the query's, counted again without the
page when a later page came back empty. A media search also matches
file names, as the reference's does.

- `@param (\Closure(PostRecord, Context): array<string, mixed>)|null $shape`

### `single(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages}/{id:[\d]+} (public; post {id} must exist; edit context: cap edit_post on {id})`

One post or page.

### `serveSingle(Minn\Http\Request $request, string $type, string $id): Minn\Http\Response`

One post of any type, with the reference's read rules.

### static `withAlternate(Minn\Http\Response $response, Minn\Content\PostRecord $post): Minn\Http\Response`

A viewable type's single post points at its page on the site.

Internals: `totals()` (private, line 100), `readable()` (private, line 119), `visibleStatuses()` (private, line 142)


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

Route: `POST /wp/v2/{base:posts} (cap edit_posts)`

Route: `POST /wp/v2/{base:pages} (cap edit_pages)`

Creates a post or page.

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

Internals: `hasParent()` (private, line 130), `hasOrder()` (private, line 136), `events()` (private, line 142), `newColumns()` (private, line 153), `writeNewPost()` (private, line 187), `trash()` (private, line 301), `rememberOld()` (private, line 320), `floatingDate()` (private, line 345), `scheduledIfFuture()` (private, line 355), `fieldColumns()` (private, line 374), `statusColumns()` (private, line 417), `checkStickyPasswordConflict()` (private, line 447), `validStatus()` (private, line 467), `clean()` (private, line 476)


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

### static `add(array $item, string $type, WP_REST_Request $request): array`

The item with the type's registered fields added, answering the request. @param array<string, mixed> $item @return array<string, mixed>

- `@param array<string, mixed> $item @return array<string, mixed>`

### static `update(object $object, string $type, WP_REST_Request $request): void`

Runs the update callbacks for the fields the request's body names; an error one returns is thrown as the write's answer.

### static `context(WP_REST_Request $request, string $type): string`

The context the item is answered in, set on the request as the
reference's controllers set it: a write and a delete answer in edit
(a term's delete in view); a read in the one asked for, or view.

### static `shownIn(string $type, string $context): array`

The names of the fields registered for the type that show in the context. @return list<string>

- `@return list<string>`

Internals: `of()` (private, line 93), `wanted()` (private, line 100), `inContext()` (private, line 110), `refusal()` (private, line 116)


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

Internals: `links()` (private, line 79)


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

### static `classes(array $classes, int $postId): array`

A post's class list as get_post_class hands it back once plugin code
filters post_class (WooCommerce drops hentry and adds a product's
stock and type): the filter's answer without repeats, in order.

- `@param list<string> $classes`
- `@return list<string>`

Internals: `withPost()` (private, line 52)


## Reply

`final class Minn\Rest\Reply` · `public/minn/src/Minn/Rest/Reply.php`

JSON responses in the reference's shape: its header set, its json_encode
flags (slashes escaped), the _fields filter, and the pagination headers
on lists.

- const `HEADERS` = `array (   'Content-Type' => 'application/json; charset=UTF-8',   'X-Content-Type-Options' => 'nosniff',   'Access-Control-Expose-Headers' => 'X-WP-Total, X-WP-TotalPages, Link',   'Access-Control-Allow-Headers' => 'Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type', )`

Used by: `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\OEmbedController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`

### static `answer(Minn\Http\Request $request, mixed $data, int $status = 200): Minn\Http\Response`

One item, shaped by the request's own _fields: what nearly every handler ends with.

### static `item(mixed $data, ?Minn\Rest\Fields $fields, int $status = 200): Minn\Http\Response`

One object as a response, the selected fields applied.

### static `alternate(Minn\Http\Response $response, string $permalink): Minn\Http\Response`

A post's answer pointing at its page on the site, as a viewable type's single item does.

### static `list(array $rows, int $total, int $totalPages, ?Minn\Rest\Fields $fields): Minn\Http\Response`

A list response with the total and page-count headers.

- `@param list<array> $rows`

### static `error(Minn\RestError $error): Minn\Http\Response`

A REST error as the reference's error body.


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

### static `writeFrom(WP_REST_Request $request, string $objectType, int $objectId, string $subtype): void`

Applies the meta a write request names, when it names any.

### static `write(string $objectType, int $objectId, string $subtype, array $meta): void`

Applies a request's meta to an object; the gathered refusals are thrown.

### static `fields(string $objectType, string $subtype): array`

The keys registered to show in REST for an object type and subtype,
each with its REST name, whether it is single, its schema (a multiple
key's an array of its own) and its prepare callback; keys of a type
REST does not know are left out.

- `@return array<string, array{name: string, single: bool, schema: array<string, mixed>, prepare_callback: mixed}>`

Internals: `prepare()` (private, line 115), `update()` (private, line 128), `replaceAll()` (private, line 159), `delete()` (private, line 184), `same()` (private, line 199), `refused()` (private, line 209), `failed()` (private, line 215), `nullStored()` (private, line 221), `gathered()` (private, line 228), `emptyValue()` (private, line 246), `request()` (private, line 257)


## RestUrl

`final readonly class Minn\Rest\RestUrl` · `public/minn/src/Minn/Rest/RestUrl.php`

REST URLs in the form the reference emits for the site's permalink mode:
{home}/wp-json/wp/v2/... when pretty, otherwise
{home}/index.php?rest_route=/wp/v2/... with the route value URL-encoded
when query args ride along.

Used by: `Minn\Engine`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentObject`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuObject`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostObject`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\RevisionsController`, `Minn\Rest\RouteCatalogue`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\Taxonomies`, `Minn\Rest\TemplateObject`, `Minn\Rest\TermObject`, `Minn\Rest\Types`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetObject`

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


### `revisions(Minn\Http\Request $request, string $base, string $parent): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks}/{parent:[\d]+}/revisions (cap edit_post on {parent}; post parent {parent} must exist)`

Real revisions, not autosaves.

### `revision(Minn\Http\Request $request, string $base, string $parent, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks}/{parent:[\d]+}/revisions/{id:[\d]+} (cap edit_post on {parent}; post parent {parent} must exist)`

One revision of a post, page, or block.

### `autosaves(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `GET /wp/v2/{base:posts|pages|blocks}/{id:[\d]+}/autosaves (cap edit_post on {id}; post parent {id} must exist)`

The autosaves of a post.

### `createAutosave(Minn\Http\Request $request, string $base, string $id): Minn\Http\Response`

Route: `POST /wp/v2/{base:posts|pages|blocks}/{id:[\d]+}/autosaves (cap edit_post on {id}; post {id} must exist)`

One autosave slot per author; the reply carries a preview link.

### `object(Minn\Content\PostRecord|array $r, Minn\Rest\Context $context = Minn\Rest\Context::View): array`

One revision row as wp/v2 serves it (autosaves and revisions alike).

Internals: `clean()` (private, line 87), `withPreviewLink()` (private, line 127), `meta()` (private, line 139), `requireParent()` (private, line 151)


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

Used by: `Minn\Rest\Api`, `Minn\Rest\IndexController`

```php
__construct(Minn\Http\Router $router, Minn\Rest\Types $types, Minn\Rest\RestUrl $url)
```


### `all(): array`

Every route the engine serves, the root and each namespace's index
among them, and the namespaces.

- `@return array{namespaces: list<string>, routes: array<string, array<string, mixed>>}`

### `describing(string $path): ?array`

The first route whose pattern takes the path, as the reference finds
the route an OPTIONS request describes; null when none does.

- `@return array<string, mixed>|null`


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

Internals: `plain()` (private, line 74), `run()` (private, line 86), `routeName()` (private, line 96), `handler()` (private, line 114), `defaults()` (private, line 129), `typed()` (private, line 147)


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

Used by: `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentObject`, `Minn\Rest\Embed`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenusController`, `Minn\Rest\PostObject`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TermObject`, `Minn\Rest\UserObject`, `Minn\Rest\WidgetObject`

### static `answering(WP_REST_Request $request): void`

Remembers the request a REST call is answering, for the filters its items pass through (on the request's own runtime).

### static `during(WP_REST_Request $request, Closure $run): mixed`

Runs an in-process REST call answering its own request (the filters
hand plugins that one), then puts back the request it interrupted.

- `@param \Closure(): T $run`

### static `item(string $filter, array $item, Closure $described): array`

One item through a rest_prepare filter.

- `@param array<string, mixed> $item`
- `@param \Closure(): mixed $described the object the item describes, fetched only when a plugin listens`
- `@return array<string, mixed>`

Internals: `objectType()` (private, line 79)


## RuntimeRoutes

`final class Minn\Rest\RuntimeRoutes` · `public/minn/src/Minn/Rest/RuntimeRoutes.php`

Routes plugin code registered with register_rest_route(), answered
through the runtime's server after the engine's own routes have had
their turn; the runtime's say before the engine answers at all (an
authentication refusal, a pre-dispatch answer, a removed endpoint);
and the runtime's namespaces folded into the index.

- const `DISPATCH_DONE` = `array (   'rest_filter_response_fields' => 10, )` — rest_post_dispatch's defaults the engine does itself: every answer is cut to its _fields before it is served.
- const `SERVE_DONE` = `array (   '_oembed_rest_pre_serve_request' => 10, )` — rest_pre_serve_request's defaults the engine does itself: oEmbed's XML (see oembedXml()).

Used by: `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\MediaController`, `Minn\Rest\OEmbedController`, `Minn\Rest\PostsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostSave`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`


### static `gate(Minn\Http\Request $request): ?Minn\Http\Response`

What plugin code decides before any route runs, engine routes
included, in the reference's order: rest_authentication_errors may
refuse the request, rest_pre_dispatch may answer it outright, and a
route a rest_endpoints filter removed is no route at all. Null lets
the engine's router proceed.

### static `dispatch(Minn\Http\Request $request): ?Minn\Http\Response`

Null when the runtime has no route for the request either.

### static `allowedMethods(string $route, WP_REST_Request $wpRequest): array`

The methods of a plugin's route whose handler lets this request through (a handler with no permission callback does). @return list<string>

- `@return list<string>`

### static `mergeIndex(Minn\Http\Response $response): Minn\Http\Response`

The engine's index plus the namespaces and routes the runtime holds.

### static `adopt(Minn\Http\Request $request, WP_REST_Request $wpRequest): void`

An in-process call's own request object stands for the engine's request: the server sets its route's parameters on it, as the reference's dispatch does.

### static `sanitized(Minn\Http\Request $request, array $registered): WP_REST_Request`

The request as the list's handler reads it: its parameters with their
defaults, sanitized by their schemas, as plugins' filters see it too.

- `@param array<string, array<string, mixed>> $registered`

### static `wpRequest(Minn\Http\Request $request): WP_REST_Request`

The request as the runtime's server reads it, and as a REST filter or action hands it to plugins: the same object each time.

### static `matched(Minn\Http\Request $request, string $route, array $handler): void`

Keeps the route and handler a request matched, for the response plugins see at serving.

- `@param array<string, mixed> $handler`

### static `toWpError(Minn\RestError $error): WP_Error`

An engine refusal as plugins handle one.

### static `toWp(Minn\Http\Response $response): WP_REST_Response`

An engine answer as plugins handle one: its data decoded (an empty
object stays one), its links on the response rather than in the data,
its status and headers.

### static `wpHeaders(array $headers, string $route = ''): array`

A route's own headers as the reference's response object holds them:
a list's totals are numbers, but the comments list's stay text.

- `@param array<string, string> $headers`
- `@return array<string, string|int>`

### static `itemResponse(array $item): WP_REST_Response`

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

### static `ensure(mixed $result): WP_REST_Response`

A callback's return as a response object, an error converted.

Internals: `allow()` (private, line 83), `oembedXml()` (private, line 321), `look()` (private, line 335), `remember()` (private, line 340), `decode()` (private, line 347), `expand()` (private, line 367), `newWpRequest()` (private, line 393), `toResponse()` (private, line 416)


## Schema

`final readonly class Minn\Rest\Schema` · `public/minn/src/Minn/Rest/Schema.php`

JSON-schema handling the way the REST API's argument validation does it:
validate a value against a schema (a Refusal names what failed), sanitise
it into the schema's type, filter a response by context, and the type
tests the schema vocabulary needs. Behaviour pinned by contracts/fixtures/api/rest.json.

- const `TYPES` = `array (   0 => 'array',   1 => 'object',   2 => 'string',   3 => 'number',   4 => 'integer',   5 => 'boolean',   6 => 'null', )`
- const `KEYWORDS` = `array (   0 => 'title',   1 => 'description',   2 => 'default',   3 => 'type',   4 => 'format',   5 => 'enum',   6 => 'items',   7 => 'properties',   8 => 'additionalProperties',   9 => 'patternProperties',   10 => 'minProperties',   11 => 'maxProperties',   12 => 'minimum',   13 => 'maximum',   14 => 'exclusiveMinimum',   15 => 'exclusiveMaximum',   16 => 'multipleOf',   17 => 'minLength',   18 => 'maxLength',   19 => 'pattern',   20 => 'minItems',   21 => 'maxItems',   22 => 'uniqueItems',   23 => 'anyOf',   24 => 'oneOf', )` — Every keyword a route schema may carry, in the reference's order; the endpoint subset drops the three descriptive ones.
- const `ENDPOINT_KEYWORDS` = `array (   0 => 'type',   1 => 'format',   2 => 'enum',   3 => 'items',   4 => 'properties',   5 => 'additionalProperties',   6 => 'patternProperties',   7 => 'minProperties',   8 => 'maxProperties',   9 => 'minimum',   10 => 'maximum',   11 => 'exclusiveMinimum',   12 => 'exclusiveMaximum',   13 => 'multipleOf',   14 => 'minLength',   15 => 'maxLength',   16 => 'pattern',   17 => 'minItems',   18 => 'maxItems',   19 => 'uniqueItems',   20 => 'anyOf',   21 => 'oneOf', )` — An object schema that names its properties forbids the others unless it says otherwise, all the way down.

Used by: `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\BlockRendererController`, `Minn\Rest\LiveSettings`, `Minn\Rest\SchemaValues`, `Minn\Rest\Services`

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

Internals: `validateComposite()` (private, line 102), `validateString()` (private, line 128), `validateNumber()` (private, line 146), `validateArray()` (private, line 158), `validateObject()` (private, line 184), `validateFormat()` (private, line 221), `validateBounds()` (private, line 233), `enum()` (private, line 270), `wrongType()` (private, line 287)


## SchemaValues

`final class Minn\Rest\SchemaValues` · `public/minn/src/Minn/Rest/SchemaValues.php`

The value side of JSON Schema, as the reference applies it: what counts
as a boolean, integer, array, or object, the coercions to each, the
comparisons and date, colour, and uuid parsers the formats use, and the
combining walk anyOf and oneOf share. Schema holds the rules; this holds
what they are applied to.

Used by: `Minn\Rest\Schema`, `Minn\Rest\Settings`

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

Route: `GET /wp/v2/search (public)`

Search across post types with the reference's relevance order.

Internals: `item()` (private, line 76), `subtypes()` (private, line 99), `rankExpression()` (private, line 114), `terms()` (private, line 125), `escapeLike()` (private, line 142)


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

Internals: `share()` (private, line 438)


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

Used by: `Minn\Rest\Api`, `Minn\Rest\WidgetsController`

```php
__construct(Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `sidebars(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/sidebars (cap edit_theme_options)`

Every sidebar: those the sidebars option lists, then any other registered one.

### `sidebar(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /wp/v2/sidebars/{id:[\w-]+} (cap edit_theme_options)`

One sidebar.

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

Internals: `item()` (private, line 160), `type()` (private, line 186), `widgetTypes()` (private, line 203), `requireSidebar()` (private, line 213)


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

Internals: `item()` (private, line 84), `readable()` (private, line 108), `statuses()` (private, line 114), `context()` (private, line 119)


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

Route: `GET /wp/v2/taxonomies (public)`

A whole-payload reply like types: _fields filters the map, not its members.

### `single(Minn\Http\Request $request, string $taxonomy): Minn\Http\Response`

Route: `GET /wp/v2/taxonomies/{taxonomy:[\w-]+} (public)`

One taxonomy.

Internals: `context()` (private, line 56)


## TemplateObject

`final readonly class Minn\Rest\TemplateObject` · `public/minn/src/Minn/Rest/TemplateObject.php`

The wp/v2/templates and wp/v2/template-parts resource.

Used by: `Minn\Rest\Api`, `Minn\Rest\TemplatesController`

```php
__construct(Minn\Theme\TemplateIndex $index, Minn\Content\Posts $posts, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller)
```


### `base(string $type): string`

The rest_base of a template type.

### `view(Minn\Theme\TemplateRecord $record, Minn\Rest\Context $context, ?string $as = NULL): array`

The wp/v2 template shape.

Internals: `links()` (private, line 86)


## TemplatesController

`final readonly class Minn\Rest\TemplatesController` · `public/minn/src/Minn/Rest/TemplatesController.php`

wp/v2/templates and wp/v2/template-parts: the block theme's templates as
one list, whether they come from the theme's files, from a row the site
saved over one, or from a plugin. Reading needs only edit_posts (the
reference lets an editor or author see the layout); changing anything
needs edit_theme_options.

- const `LOOKUP` = `array (   'slug' =>    array (     'description' => 'The slug of the template to get the fallback for',     'type' => 'string',     'required' => true,   ),   'is_custom' =>    array (     'description' => 'Indicates if a template is custom or part of the template hierarchy',     'type' => 'boolean',     'required' => false,   ),   'template_prefix' =>    array (     'description' => 'The template prefix for the created template. This is used to extract the main template type, e.g. in `taxonomy-books` extracts the `taxonomy`',     'type' => 'string',     'required' => false,   ), )`
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

Internals: `listing()` (private, line 120), `lookup()` (private, line 144), `single()` (private, line 162), `save()` (private, line 171), `delete()` (private, line 198), `trashed()` (private, line 215), `record()` (private, line 237), `readable()` (private, line 247), `requireWrite()` (private, line 258), `text()` (private, line 267)


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

Internals: `record()` (private, line 30)


## TermListArgs

`final class Minn\Rest\TermListArgs` · `public/minn/src/Minn/Rest/TermListArgs.php`

The get_terms arguments a term list request makes, as the reference
makes them before rest_{taxonomy}_query (probe rest-term-lists): the
taxonomy, each declared parameter the request carries under its query
name (include_slugs ordering as slug__in), the offset (the page's unless
the list takes one and it is given), the parent a hierarchical list
takes, and ids alone for a HEAD request.

- const `MAPPINGS` = `array (   'exclude' => 'exclude',   'include' => 'include',   'order' => 'order',   'orderby' => 'orderby',   'post' => 'post',   'hide_empty' => 'hide_empty',   'per_page' => 'number',   'search' => 'search',   'slug' => 'slug', )`

Used by: `Minn\Rest\TermsController`

### static `of(WP_REST_Request $wp, array $registered, string $taxonomy, string $method): array`

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

Internals: `viewFields()` (private, line 80), `allowedVerbs()` (private, line 113)


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

Internals: `requireParent()` (private, line 182)


## Types

`final class Minn\Rest\Types` · `public/minn/src/Minn/Rest/Types.php`

The engine's registry of built-in post types, seeded from the observed
contract (src/data/types.json) with _links attached at runtime.

Used by: `Minn\Admin\AdminTypes`, `Minn\Admin\StructureController`, `Minn\Engine`, `Minn\Rest\Api`, `Minn\Rest\BatchController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\PolicyGate`, `Minn\Rest\RouteCatalogue`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\TypesController`

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

### `isRegistered(string $slug): bool`

Whether plugin code registered the type (not a built-in, not an extension's).

### `restBase(string $slug): string`

The rest_base of a type slug.

Internals: `core()` (private, line 37), `registered()` (private, line 78), `servedRegistered()` (private, line 172)


## TypesController

`final readonly class Minn\Rest\TypesController` · `public/minn/src/Minn/Rest/TypesController.php`

wp/v2 types. In the edit context (probe rest-types-edit) a type adds its
capabilities, visibility, viewability, labels and supports, for a caller
who may edit its posts: the list leaves out the types the caller may
not, and refuses a caller who may edit none; one type refuses outright.

- const `EDIT_REFUSAL` = `'Sorry, you are not allowed to edit posts in this post type.'`

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Rest\Types $types)
```


### `list(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp/v2/types (public)`

Deliberately a whole-payload reply: _fields strips every type key, yielding [].

### `single(Minn\Http\Request $request, string $type): Minn\Http\Response`

Route: `GET /wp/v2/types/{type:[\w-]+} (public)`

One post type.

Internals: `embedded()` (private, line 63), `mayEdit()` (private, line 68), `withEditFields()` (private, line 75)


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

### static `of(WP_REST_Request $wp, array $registered, string $method, array $types, string $reader): array`

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
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Rest\UserObject $object, Minn\Rest\RestUrl $url, Minn\Rest\Caller $caller, Minn\Auth\Roles $roles)
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

Internals: `listAllowed()` (private, line 105), `totals()` (private, line 129), `hasPublishedContent()` (private, line 164), `validRole()` (private, line 173), `validEmail()` (private, line 181), `loginRefusal()` (private, line 359)


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

### static `instance(WP_Widget $object, int $number): array`

A widget's settings, encoded and signed, and raw when it shows them. @return array<string, mixed>

- `@return array<string, mixed>`

Internals: `form()` (private, line 63)


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

Internals: `saveInstance()` (private, line 147), `newInstance()` (private, line 164), `sidebarOf()` (private, line 188)

