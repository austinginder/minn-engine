# `Minn\Runtime`

the WordPress runtime plugins load against

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Abilities`](#abilities) | final class | 207 | The abilities registry behind the wp_*_ability facade, as the reference |
| [`AbilityRun`](#abilityrun) | final class | 41 | Running an ability as the reference runs one (probe abilities-registry): |
| [`AccountFlows`](#accountflows) | final class | 140 | The account functions a sign-in page and plugins call, with the |
| [`AjaxController`](#ajaxcontroller) | final readonly class | 80 | admin-ajax.php, the endpoint plugins post their front-end work to: a form |
| [`AllowedOptions`](#allowedoptions) | final class | 26 | The settings-page allowlist plugins extend: option group => the option |
| [`ApplicationPasswordEvents`](#applicationpasswordevents) | final class | 46 | Application password changes made over REST, as the reference makes them |
| [`ApplicationPasswordSignIn`](#applicationpasswordsignin) | final class | 80 | wp_authenticate_application_password as the reference answers it (probe |
| [`Assets`](#assets) | final class | 400 | The registry behind wp_register_/wp_enqueue_ for scripts and styles: |
| [`Avatar`](#avatar) | final class | 62 | Avatars the way get_avatar_data and get_avatar decide them: the argument |
| [`BlockFilters`](#blockfilters) | final class | 82 | The block-level filters plugin code hooks (pre_render_block, |
| [`BlockHooks`](#blockhooks) | final class | 69 | The Block Hooks API on the engine's own front end: a plugin asks for its |
| [`BlockMetadata`](#blockmetadata) | final class | 95 | block.json to the settings a block type registers with: the property |
| [`BlockTemplates`](#blocktemplates) | final class | 66 | Block templates plugins register at runtime, by their namespaced name |
| [`BlockWidget`](#blockwidget) | final class | 30 | A block widget's legacy class name. Every widget the block editor saves |
| [`CommentCloser`](#commentcloser) | final readonly class | 22 | The Discussion setting that closes comments on old posts. Observed on the |
| [`CommentEvents`](#commentevents) | final readonly class | 253 | What the reference's REST comments controller tells plugins, for the |
| [`CommentFeedQuery`](#commentfeedquery) | final class | 50 | The comments a comments feed's main query carries, as the reference |
| [`CommentForm`](#commentform) | final class | 107 | The comment form's submission with plugins loaded |
| [`CommentPages`](#commentpages) | final class | 100 | Which page of a post's comments a comment falls on, and the link that |
| [`CommentQuery`](#commentquery) | final readonly class | 28 | The approval breakdown wp_count_comments reports (comment lists run through WP_Comment_Query and Minn\Runtime\CommentQueryRunner). |
| [`CommentQueryRunner`](#commentqueryrunner) | final class | 105 | WP_Comment_Query as the reference runs it (probe wp-comment-query-sql): |
| [`CommentQueryWhere`](#commentquerywhere) | final class | 190 | WP_Comment_Query's WHERE pieces and the posts join, in the reference's |
| [`CommentThreads`](#commentthreads) | final class | 68 | A threaded or flat comment query's descendants as the reference fills |
| [`Connectors`](#connectors) | final class | 212 | The connectors registry: the external services a site talks to (AI |
| [`Constants`](#constants) | final class | 83 | The constants plugin code expects: the fixed set from data/constants.json |
| [`CronTable`](#crontable) | final class | 131 | The cron option's shape, operated on as data: timestamp => hook => key => |
| [`CurrentUser`](#currentuser) | final class | 13 | Who the request is, as the reference settles it before init: the user |
| [`DbDelta`](#dbdelta) | final readonly class | 125 | dbDelta as the reference does it: a CREATE TABLE statement creates the |
| [`Deferrals`](#deferrals) | final class | 66 | The switches an importer flips for the length of a request (probe |
| [`EarlyFilters`](#earlyfilters) | final class | 20 | Filters that run before the runtime exists, over the hooks added that |
| [`FileTypeCheck`](#filetypecheck) | final class | 78 | A file's type from its content as much as its name, as the reference's |
| [`FileUpload`](#fileupload) | final class | 105 | A file a plugin hands to wp_handle_upload or wp_handle_sideload, taken in |
| [`Heartbeat`](#heartbeat) | final class | 30 | The heartbeat a signed-in page beats through admin-ajax.php, answered as |
| [`Hooks`](#hooks) | final class | 338 | The hook registry plugin code registers into and the engine fires. |
| [`Interactivity`](#interactivity) | final class | 509 | Server-side directive processing for the Interactivity API: the state and |
| [`MainQuery`](#mainquery) | final class | 34 | The query variables the reference's main query would carry for a URL the |
| [`Meta`](#meta) | final readonly class | 210 | The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need. |
| [`MetaKeys`](#metakeys) | final class | 183 | The meta keys code registers, kept where the reference keeps them |
| [`MetaTypes`](#metatypes) | final class | 21 | Meta types a plugin brought, by the table it named on $wpdb as |
| [`NavMenu`](#navmenu) | final class | 303 | Nav-menu item decoration for wp_nav_menu(): the reference's class tokens |
| [`OEmbed`](#oembed) | final class | 92 | oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes. |
| [`ObjectCache`](#objectcache) | final class | 52 | The per-request object cache behind wp_cache_*: groups of keys, nothing persistent. |
| [`ObjectTerms`](#objectterms) | final class | 30 | wp_get_object_terms's handling of taxonomies registered with their own |
| [`OptionSanitizer`](#optionsanitizer) | final class | 114 | A core option's value cleaned as the reference's sanitize_option cleans |
| [`Options`](#options) | final class | 232 | Options as plugin code sees them: PHP values, decoded from the stored |
| [`PackageDownload`](#packagedownload) | final class | 32 | The publisher's say over its own download. Before fetching an update |
| [`PageMenu`](#pagemenu) | final class | 40 | The page-list menu a classic theme falls back to when no menu is |
| [`Pages`](#pages) | final class | 116 | get_pages() as the reference shapes it: its arguments as a post query, and the tree order of the result. |
| [`Patterns`](#patterns) | final class | 161 | The block pattern, pattern category, and block style registries as data. |
| [`PlaceholderTrace`](#placeholdertrace) | final class | 27 | Records every call into a generated placeholder while a site opts in by |
| [`Placeholders`](#placeholders) | final class | 49 | The printf placeholders plugin code hands wpdb::prepare, filled the way |
| [`PluginUpdates`](#pluginupdates) | final class | 65 | The update offers the site's own plugins publish. A plugin that hosts |
| [`Plugins`](#plugins) | final class | 238 | Loads the site's plugins into the runtime the way the reference does: |
| [`PostData`](#postdata) | final class | 65 | The loop's view of a post, as the reference's generate_postdata and |
| [`PostEvents`](#postevents) | final readonly class | 181 | What the reference's REST controllers tell plugins about a post they |
| [`PostInsert`](#postinsert) | final readonly class | 168 | The decisions behind wp_insert_post: which columns a postarr fills, when |
| [`PostLinks`](#postlinks) | final class | 151 | Post addresses as the reference's link functions build them (probe |
| [`PostLookup`](#postlookup) | final readonly class | 85 | The post reads plugin code asks for by shape: a page by title, revisions, counts. |
| [`PostQuery`](#postquery) | final class | 377 | WP_Query::get_posts as the reference runs it (probe wp-query-sql): the |
| [`PostQueryParts`](#postqueryparts) | final class | 56 | The pieces of one WP_Query run as the reference builds them and hands |
| [`PostQueryResults`](#postqueryresults) | final class | 111 | What WP_Query does with its posts once it has them, as the reference does |
| [`PostQueryStatus`](#postquerystatus) | final class | 165 | WP_Query's post type and status clause as the reference writes it (probe |
| [`PostQueryTax`](#postquerytax) | final class | 193 | The taxonomy side of WP_Query as the reference runs it (probe |
| [`PostQueryWhere`](#postquerywhere) | final class | 263 | The WHERE fragments WP_Query writes from its variables, in the reference's |
| [`PostRevisions`](#postrevisions) | final class | 63 | A post's revisions as the reference's wp_save_post_revision keeps them |
| [`PostSave`](#postsave) | final class | 210 | A REST save's columns through the filters the reference's save runs |
| [`QueriedObject`](#queriedobject) | final readonly class | 70 | Which object a query is "about", read from its flags and variables: a term |
| [`QueryFlags`](#queryflags) | final readonly class | 166 | The conditional flags a set of query variables implies (is_single, is_archive, |
| [`Recovery`](#recovery) | final readonly class | 214 | Recovery from a fatal in someone else's code. When a plugin or theme |
| [`Refusal`](#refusal) | final readonly class | 6 | A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error. |
| [`RegisteredSettings`](#registeredsettings) | final class | 104 | Settings as register_setting keeps them (probe rest-settings): the |
| [`Registry`](#registry) | final class | 392 | Post types, taxonomies, and statuses as plugin code registers and reads |
| [`Runtime`](#runtime) | final class | 369 | The WordPress runtime the engine offers plugin code: the procedural |
| [`ScriptModules`](#scriptmodules) | final class | 302 | The script modules registry: registrations with typed dependencies, the |
| [`ScriptPack`](#scriptpack) | final class | 146 | The site-supplied script pack: the `wp-*` JavaScript packages the engine |
| [`Shortcodes`](#shortcodes) | final class | 143 | The shortcode registry plugin code fills with add_shortcode, and the |
| [`StoredObjects`](#storedobjects) | final class | 64 | The classes a stored blob may name and come back as. The serialized |
| [`SymbolGap`](#symbolgap) | final readonly class | 94 | The part of the reference's interface the runtime does not answer: names in |
| [`SymbolTable`](#symboltable) | final class | 69 | What a folder's PHP names, collected while its tokens are read: the |
| [`Symbols`](#symbols) | final class | 275 | A static read of what a plugin's PHP calls: global functions and classes |
| [`TagEditor`](#tageditor) | final class | 149 | Edits one start tag's attributes in place the way the reference's tag |
| [`TermEvents`](#termevents) | final readonly class | 114 | What the reference's REST terms controller tells plugins, for the |
| [`TermFields`](#termfields) | final class | 65 | A term's fields in a context, as the reference's sanitize_term_field |
| [`TermOrder`](#termorder) | final class | 61 | A term query's ORDER BY as the reference writes it (probe |
| [`TermQuery`](#termquery) | final readonly class | 370 | Term reads in the shapes plugin code asks for: get_terms() arguments to |
| [`TermQueryRunner`](#termqueryrunner) | final class | 255 | WP_Term_Query::get_terms as the reference runs it (probe |
| [`TermQueryTree`](#termquerytree) | final class | 146 | What a term query does with its rows, as the reference does it (probe |
| [`TermSave`](#termsave) | final class | 199 | wp_insert_term and wp_update_term in the reference's order (probe |
| [`TermWriter`](#termwriter) | final readonly class | 119 | The decisions behind wp_delete_term and the object-term relationships: |
| [`ThemeSupports`](#themesupports) | final class | 159 | What a theme supports, as add_theme_support keeps it (probe rest-themes): |
| [`TreeWalk`](#treewalk) | final class | 74 | The Walker contract's traversal: elements keyed by the walker's |
| [`UpdateCounts`](#updatecounts) | final class | 31 | The updates waiting, as wp_get_update_data counts them for the user |
| [`UserEvents`](#userevents) | final readonly class | 102 | What the reference's REST users controller tells plugins, for the |
| [`UserInsert`](#userinsert) | final readonly class | 115 | The decisions behind wp_insert_user: what a new account needs, which email |
| [`UserOrder`](#userorder) | final class | 43 | A user query's ORDER BY keys as the reference writes them (probe |
| [`UserQueryRoles`](#userqueryroles) | final class | 91 | A user query's roles and capabilities as the meta clauses the reference |
| [`UserQueryRunner`](#userqueryrunner) | final class | 207 | WP_User_Query as the reference runs it (probe wp-user-query-sql): the |
| [`UserSave`](#usersave) | final class | 70 | An account's fields through the filters the reference's wp_insert_user |
| [`WidgetAreas`](#widgetareas) | final class | 84 | The widgets helper functions as the reference answers them (probe |

## Abilities

`final class Minn\Runtime\Abilities` · `public/minn/src/Minn/Runtime/Abilities.php`

The abilities registry behind the wp_*_ability facade, as the reference
keeps it (probe abilities-registry). Categories register only on
wp_abilities_api_categories_init and abilities only on
wp_abilities_api_init; each action fires once, lazily, on the first
lookup of its kind (abilities after categories). A registration is
checked as the reference checks it (slug or name shape, duplicates,
required fields, a known category) and refused with its notice. An
ability's meta gains the default annotations, show_in_rest and public.

- const `STATE` = `'abilities'`
- const `CATEGORIES` = `'WP_Ability_Categories_Registry'`
- const `ABILITIES` = `'WP_Abilities_Registry'`
- const `NAME` = `'/^[a-z0-9-]+\\/[a-z0-9-]+$/'`
- const `SLUG` = `'/^[a-z0-9]+(?:-[a-z0-9]+)*$/'`

Used by: `Minn\Rest\AbilitiesController`

### static `initializeCategories(): void`

Fires the categories action once.

### static `initialize(): void`

Fires the categories action, then the abilities action, each once.

### static `registerCategory(string $slug, array $args): ?array`

Registers a category; its row, or null with the reference's notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `register(string $name, array $args): ?array`

Registers an ability; its row, or null with the reference's notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `unregister(string $name): ?array`

Removes an ability; its row, or null with the reference's notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `unregisterCategory(string $slug): ?array`

Removes a category; its row, or null with the reference's notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `find(string $name): ?array`

One ability, or null with the reference's notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `ability(string $name): ?array`

One ability, or null, asked after without a notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `findCategory(string $slug): ?array`

One category, or null with the reference's notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `category(string $slug): ?array`

One category, or null, asked after without a notice. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `all(): array`

Every ability, by name. @return array<string, array>

- `@return array<string, array>`

### static `allCategories(): array`

Every category, by slug. @return array<string, array>

- `@return array<string, array>`

### static `isReadOnly(string $name): bool`

Whether an ability is marked read-only, which decides the method its run endpoint takes.

Internals: `state()` (private, line 26), `save()` (private, line 32), `refusal()` (private, line 185), `meta()` (private, line 201), `forget()` (private, line 210)


## AbilityRun

`final class Minn\Runtime\AbilityRun` · `public/minn/src/Minn/Runtime/AbilityRun.php`

Running an ability as the reference runs one (probe abilities-registry):
input given to an ability without an input schema is refused, input that
its schema refuses is ability_invalid_input; a permission callback that
does not answer true is ability_invalid_permissions (an error it returns
is reported as a notice); then wp_before_execute_ability, the callback
(an error it returns is the answer), the output checked against the
output schema (ability_invalid_output), and wp_after_execute_ability.

### static `input(string $name, array $schema, mixed $input): WP_Error|true`

True, or why the input does not fit.

### static `execute(WP_Ability $ability, mixed $callback, mixed $input): mixed`

The ability's answer, or the error that stopped it.


## AccountFlows

`final class Minn\Runtime\AccountFlows` · `public/minn/src/Minn/Runtime/AccountFlows.php`

The account functions a sign-in page and plugins call, with the
reference's checks, words, hooks and mail (probe account-flows): asking
for a password reset (retrieve_password), resetting one
(reset_password), registering (register_new_user), and telling the site
and the new user about a registration (wp_new_user_notification).

- const `NO_ACCOUNT` = `'<strong>Error:</strong> There is no account with that username or email address.'`

### static `retrievePassword(string $login): WP_Error|bool`

A reset link mailed to the account a login or email names: true, or
the refusal (no name, no such account, a plugin's error, reset not
allowed, or the mail failing).

### static `resetPassword(WP_User $user, string $password): void`

A new password for a user, announced before (password_reset) and after (after_password_reset).

### static `registerNewUser(string $login, string $email): WP_Error|int`

A new account for a login and an email, with a generated password and
the nag to change it: its id, or every refusal at once (the name
empty, illegal, taken or barred; the email empty, malformed or taken;
whatever register_post and registration_errors add).

### static `newUserNotification(int $userId, mixed $deprecated, string $notify): void`

A new account announced: to the site's address (unless only the user
is told), then to the user with a link to set their password (unless
only the site is, or the old call shape asks for nobody).

Internals: `resetLink()` (private, line 151)


## AjaxController

`final readonly class Minn\Runtime\AjaxController` · `public/minn/src/Minn/Runtime/AjaxController.php`

admin-ajax.php, the endpoint plugins post their front-end work to: a form
submitted without a reload, a spam check, a background job. The action a
request names runs the handlers registered as wp_ajax_{action} for a
signed-in visitor and wp_ajax_nopriv_{action} for anyone else, answered as
the reference answers them (contracts/runtime.md "admin-ajax.php").

It is an admin request: is_admin() is true while the plugins load and
admin_init fires before the handler. A handler usually ends the request
itself (wp_send_json, wp_die, exit), so the endpoint's headers go out
before it runs; one that returns is followed by the reference's "0".

- const `PATH` = `'/wp-admin/admin-ajax.php'`
- const `CORE` = `array (   'wp_ajax_nopriv_' =>    array (     'heartbeat' => 'wp_ajax_nopriv_heartbeat',   ),   'wp_ajax_' =>    array (     'rest-nonce' => 'wp_ajax_rest_nonce',     'heartbeat' => 'wp_ajax_heartbeat',   ), )` — The core actions the endpoint answers itself, under the prefix for who is asking: the action => the handler.
- const `ADMIN_FILTERS` = `array (   0 =>    array (     0 => 'wp_refresh_nonces',     1 => 'wp_refresh_heartbeat_nonces',   ), )` — The filters the reference's admin includes add once plugins have loaded: the hook, the callback.

Used by: `Minn\Engine`, `Minn\Runtime\Constants`

### static `claims(Minn\Http\Request $request): bool`

Whether a request is for the endpoint, which the runtime boots as an admin request.

### `dispatch(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-admin/admin-ajax.php (public)`

Runs the handlers registered for the action the request names.

Internals: `action()` (private, line 82), `allowedOrigin()` (private, line 89), `registerCore()` (private, line 99)


## AllowedOptions

`final class Minn\Runtime\AllowedOptions` · `public/minn/src/Minn/Runtime/AllowedOptions.php`

The settings-page allowlist plugins extend: option group => the option
names a settings form in that group may save. The engine renders no
settings pages, so the list is only recorded, never enforced.

### static `merge(array $add, array $into): array`

The allowlist with each new name appended to its group once, groups
created as needed. A group handed as anything but a list adds nothing.

- `@param array<string, mixed> $add`
- `@param array<string, list<string>> $into`
- `@return array<string, list<string>>`


## ApplicationPasswordEvents

`final class Minn\Runtime\ApplicationPasswordEvents` · `public/minn/src/Minn/Runtime/ApplicationPasswordEvents.php`

Application password changes made over REST, as the reference makes them
(probe rest-application-password-hooks): the prepared item (the name,
and an app id a new one is given) through
rest_pre_insert_application_password, then WP_Application_Passwords
(which tells plugins through wp_create_, wp_update_ and
wp_delete_application_password), then rest_after_insert_application_password.

Used by: `Minn\Rest\ApplicationPasswordsController`

### static `create(int $userId, array $body, WP_REST_Request $request): WP_Error|array`

A new password: its record and its plain text in groups of four, or
the reference's refusal.

- `@param array<string, mixed> $body`
- `@return array{0: array<string, mixed>, 1: string}|\WP_Error`

### static `update(int $userId, string $uuid, array $body, WP_REST_Request $request): WP_Error|array`

A password renamed (when the body names it): its record as it stands.

- `@param array<string, mixed> $body`
- `@return array<string, mixed>|\WP_Error`

Internals: `prepared()` (private, line 58)


## ApplicationPasswordSignIn

`final class Minn\Runtime\ApplicationPasswordSignIn` · `public/minn/src/Minn/Runtime/ApplicationPasswordSignIn.php`

wp_authenticate_application_password as the reference answers it (probe
application-passwords-api): a user found already stands; outside an API
request (application_password_is_api_request) nothing is tried; then the
user by login or email, the feature's availability (for the site, for
the user), and each of the user's passwords, a match passing through
wp_authenticate_application_password_errors, recorded, and announced
(application_password_did_authenticate); every refusal announced
through application_password_failed_authentication.

### static `authenticate(mixed $input, string $username, string $password): mixed`

The signed-in user, the input unchanged, or the refusal.

### static `validate(mixed $input): mixed`

determine_current_user's application password step: an earlier
answer stands; else the user the REST request's application password
signed in (the engine checked the password before plugins loaded),
heard again as the reference hears it: the feature's availability,
wp_authenticate_application_password_errors, then
application_password_did_authenticate; a refusal is announced and
leaves the request signed out.

Internals: `refusal()` (private, line 86)


## Assets

`final class Minn\Runtime\Assets` · `public/minn/src/Minn/Runtime/Assets.php`

The registry behind wp_register_/wp_enqueue_ for scripts and styles:
handles, sources, dependencies, versions, inline additions, localized
data, and the head/footer split. Printing follows the reference's tag
shapes as far as captured; see contracts/runtime.md.

```php
__construct(string $kind)
```


### `watch(Closure $listener): void`

A listener called after every change, so a plugin-facing view can stay current.

### `setQueue(array $queue): void`

The queue as a plugin left it after editing the view directly.

### `register(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $extra): bool`

Registers an asset under a handle.

### `externalHosts(string $ownHost): array`

The hosts the queued assets load from, other than the site's own.

- `@return list<string> hosts of enqueued sources (dependencies first) away from the given host, for dns-prefetch hints`

### `deregister(string $handle): void`

Forgets an asset.

### `enqueue(string $handle): void`

Queues an asset for printing (probe placeholders-admin). One that is
neither registered nor among the reference's own handles waits, and
joins the queue when it is registered; one of the reference's own the
engine ships no file for is queued as the reference would queue it.

### `dequeue(string $handle): void`

Removes an asset from the queue, or from the handles waiting for registration.

### `registered(string $handle): bool`

Whether a handle is registered, by the site or as one the reference registers itself (data/default-assets.json).

### `enqueued(string $handle): bool`

Queued directly, or pulled in as the dependency of something queued,
however deep. The reference answers the same way, and plugin code
leans on it: WooCommerce only attaches its settings blob when it
finds `wc-settings` "enqueued", and nothing queues that handle by
name, it only ever rides in as a dependency.

### `doneList(): array`

The handles printed so far.

- `@return list<string>`

### `setDone(array $done): void`

The printed handles as a plugin left them after editing the view directly.

### `done(string $handle): bool`

Whether a handle has been printed.

### `addInline(string $handle, string $code, string $position): bool`

Attaches inline code to an asset.

### `addData(string $handle, string $key, mixed $value): bool`

Attaches a data key to an asset.

### `setTranslations(string $handle, string $domain, string $path): bool`

Names the text domain and folder a script's translations come from, and makes the script depend on wp-i18n.

### `data(string $handle, string $key): mixed`

A data key of an asset, or false.

### `localize(string $handle, string $name, array $data): bool`

Attaches a localized object to a script.

### `toPrint(?bool $footer = NULL): array`

Every queued handle not yet printed, dependencies first, filtered to the group (footer or not).

### `toPrintHandles(array $handles): array`

Named handles and everything they depend on, in printing order, whether
or not they were queued; what already printed is left out.

- `@param list<string> $handles`
- `@return list<string>`

### `withPath(): array`

Enqueued handles that name a file on disk, mapped to that path. A style
registered with a `path` datum is saying it can be inlined; whether it
is small enough to be worth inlining is the caller's decision.

- `@return array<string, string> handle => path`

### `unsource(string $handle): void`

Drops a handle's source so it prints as markup rather than a link.

### `markDone(string $handle): void`

Records a handle as printed.

### `item(string $handle): ?array`

One registered asset, or null.

### `items(): array`

Every registered asset.

- `@return array<string, array<string, mixed>>`

### `queue(): array`

The handles queued.

- `@return list<string>`

### `kind(): string`

Whether these are scripts or styles.

Internals: `changed()` (private, line 45), `depsOf()` (private, line 135), `defaults()` (private, line 141), `held()` (private, line 212), `ordered()` (private, line 314)


## Avatar

`final class Minn\Runtime\Avatar` · `public/minn/src/Minn/Runtime/Avatar.php`

Avatars the way get_avatar_data and get_avatar decide them: the argument
defaults, the email an id, user, post, or comment names, the Gravatar URL,
and the <img> attributes. Behaviour pinned by contracts/fixtures/api/functions.json.

### static `dataArgs(array $args): array`

The data arguments normalised: sizes to integers, the default token, the rating lowercased.

### static `hash(string $email): string`

The Gravatar hash an email yields; '' for no email.

### static `hashFromAddress(string $address): ?string`

A hash given as an md5.gravatar.com address, else null.

### static `urlArgs(array $args): array`

The Gravatar URL's query arguments for the data arguments. @return array<string, string|int|false>

- `@return array<string, string|int|false>`

### static `classes(int $size, bool $isDefault, mixed $extra): array`

The <img> classes: the size class, a default marker, the caller's own. @return list<string>

- `@return list<string>`

### static `extraAttributes(string $extra, mixed $loading, mixed $decoding): string`

The extra attribute string with loading and decoding added when the caller did not set them.


## BlockFilters

`final class Minn\Runtime\BlockFilters` · `public/minn/src/Minn/Runtime/BlockFilters.php`

The block-level filters plugin code hooks (pre_render_block,
render_block_data, render_block, render_block_{name}), applied around the
engine's own renderer so a plugin sees every block the page renders, not
only the ones it registered.

Used by: `Minn\Blocks\Renderer`


### static `active(): bool`

Whether any block filter is registered.

### static `toArray(Minn\Blocks\Block $block): array`

A block as the parsed array plugins receive.

- `@return array<string, mixed> the parsed-array shape plugin code reads`

### static `fromArray(array $parsed): Minn\Blocks\Block`

A block from the parsed array plugins hand back.

### static `before(Minn\Blocks\Block $block): Minn\Blocks\Block|string`

A short-circuit from pre_render_block, or the block as render_block_data left it.

### static `after(Minn\Blocks\Block $block, string $html): string`

A rendered block through render_block and its per-name filter.

Internals: `context()` (private, line 88)


## BlockHooks

`final class Minn\Runtime\BlockHooks` · `public/minn/src/Minn/Runtime/BlockHooks.php`

The Block Hooks API on the engine's own front end: a plugin asks for its
block to be inserted next to an anchor block in a template part or a
pattern (WooCommerce puts the mini-cart after the navigation in a
header), and the markup the renderer reads carries those insertions.

The facade owns the traversal (wp-api/blocks.php); this is the seam the
theme blocks call, and it stays inert until a plugin actually hooks
something, so a site without such a plugin parses nothing extra.

Used by: `Minn\Blocks\Dynamic\Theme\Structure`

### static `active(): bool`

Whether block hooks are registered and the runtime is up.

### static `forPart(string $markup, string $slug, string $area): string`

A template part's markup with its hooked blocks inserted. The context
is the part itself: a plugin reads its area to tell a header from a
footer, and its content to see whether its block is already there.

### static `registeredPattern(string $slug): ?string`

A plugin-registered pattern's content, hooks already applied by the
registry, for a slug the theme does not carry. Null when the runtime
is not up or nothing registered that name.

### static `forPattern(string $markup, string $slug, array $blockTypes, array $categories): string`

A theme pattern's markup with its hooked blocks inserted, with the
pattern array as the context (blockTypes and categories are how a
plugin recognises a header pattern).

- `@param list<string> $blockTypes`
- `@param list<string> $categories`


## BlockMetadata

`final class Minn\Runtime\BlockMetadata` · `public/minn/src/Minn/Runtime/BlockMetadata.php`

block.json to the settings a block type registers with: the property
renames, the script and style handles (registered through the closures,
one per entry), the view script modules, the block hooks positions, and
the render template as a callback. Behaviour pinned by contracts/fixtures/api/blocks.json.

- const `PROPERTIES` = `array (   'apiVersion' => 'api_version',   'name' => 'name',   'title' => 'title',   'category' => 'category',   'parent' => 'parent',   'ancestor' => 'ancestor',   'icon' => 'icon',   'description' => 'description',   'keywords' => 'keywords',   'attributes' => 'attributes',   'providesContext' => 'provides_context',   'usesContext' => 'uses_context',   'selectors' => 'selectors',   'supports' => 'supports',   'styles' => 'styles',   'variations' => 'variations',   'example' => 'example',   'allowedBlocks' => 'allowed_blocks', )`
- const `SCRIPTS` = `array (   'editorScript' => 'editor_script_handles',   'script' => 'script_handles',   'viewScript' => 'view_script_handles', )`
- const `STYLES` = `array (   'editorStyle' => 'editor_style_handles',   'style' => 'style_handles',   'viewStyle' => 'view_style_handles', )`
- const `POSITIONS` = `array (   'before' => 'before',   'after' => 'after',   'firstChild' => 'first_child',   'lastChild' => 'last_child', )`
- const `FIELD_HANDLES` = `array (   'editorScript' => 'editor-script',   'editorStyle' => 'editor-style',   'script' => 'script',   'style' => 'style',   'viewScript' => 'view-script',   'viewScriptModule' => 'view-script-module',   'viewStyle' => 'view-style', )`

### static `settings(array $metadata, Closure $scriptHandle, Closure $styleHandle, Closure $moduleId, Closure $render): array`

Block settings from a block.json, the way the reference maps its properties.

- `@param Closure(array, string, int): (string|false) $scriptHandle registers one script entry, answering its handle`
- `@param Closure(array, string, int): (string|false) $styleHandle registers one style entry`
- `@param Closure(array, string, int): (string|false) $moduleId registers one view script module entry`
- `@param Closure(string): ?Closure $render the render callback for a template path, or null when the file is missing`
- `@return array<string, mixed>`

### static `assetHandle(string $block, string $field, int $index = 0): string`

The script or style handle a block.json field registers under; core blocks keep the `wp-block-` spelling.

Internals: `handles()` (private, line 78)


## BlockTemplates

`final class Minn\Runtime\BlockTemplates` · `public/minn/src/Minn/Runtime/BlockTemplates.php`

Block templates plugins register at runtime, by their namespaced name
("plugin//slug"). A theme file or a saved template of the same slug
wins; otherwise the registered content renders for that slug.

Used by: `Minn\Runtime\Runtime`, `Minn\Theme\TemplateIndex`, `Minn\Theme\Templates`


### `register(string $name, array $args): array|string`

The registered row, or the refusal code the reference reports.

### `unregister(string $name): ?array`

Forgets a registered template; the row, or null.

### `all(): array`

Every registered template.

- `@return array<string, array<string, mixed>> by registered name`

### `get(string $name): ?array`

One registered template, or null.

### `bySlug(string $slug): ?array`

A registered template by slug, or null.


## BlockWidget

`final class Minn\Runtime\BlockWidget` · `public/minn/src/Minn/Runtime/BlockWidget.php`

A block widget's legacy class name. Every widget the block editor saves
is one block of markup, and a classic theme styles it by the widget
class the equivalent legacy widget used, so the wrapper carries a
second class named after the FIRST block in the content. A block with
no legacy equivalent adds nothing.

- const `BASE_CLASS` = `'widget_block'`
- const `LEGACY_CLASSES` = `array (   'core/paragraph' => 'widget_text',   'core/search' => 'widget_search',   'core/html' => 'widget_custom_html',   'core/archives' => 'widget_archive',   'core/latest-posts' => 'widget_recent_entries',   'core/latest-comments' => 'widget_recent_comments',   'core/tag-cloud' => 'widget_tag_cloud',   'core/categories' => 'widget_categories',   'core/calendar' => 'widget_calendar',   'core/rss' => 'widget_rss', )` — First block name => the legacy widget class a theme styles.

### static `classNameFor(array $blocks): string`

The legacy widget class a block widget maps to.

- `@param list<array<string, mixed>> $blocks the parsed content`


## CommentCloser

`final readonly class Minn\Runtime\CommentCloser` · `public/minn/src/Minn/Runtime/CommentCloser.php`

The Discussion setting that closes comments on old posts. Observed on the
reference: only the "post" type closes (the types filter changes nothing),
status is not consulted, the age is whole days on post_date_gmt and must
exceed the setting (a fifteen-day-old post stays open at fifteen), and zero
days switches the rule off.

```php
__construct(bool $enabled, int $days)
```


### `open(bool $open, Minn\Content\PostRecord|array|null $post, int $now): bool`

Whether comments stay open on a post under the close-after-days setting.


## CommentEvents

`final readonly class Minn\Runtime\CommentEvents` · `public/minn/src/Minn/Runtime/CommentEvents.php`

What the reference's REST comments controller tells plugins, for the
engine's own: with plugins loaded every change goes through the
runtime's comment functions (wp_insert_comment, wp_update_comment,
wp_set_comment_status, wp_trash_comment, wp_delete_comment), which fire
the reference's actions in its order, and the REST actions follow.
Without a booted runtime the rows are written as before and nothing is
told.

Used by: `Minn\Rest\CommentsController`

```php
__construct(Minn\Content\Comments $comments)
```


### `live(): bool`

Whether plugins are loaded to be told anything.

### `allow(string $address, string $email, string $dateGmt): void`

The check a new comment passes on the reference before it is written: check_comment_flood, with the address, the email and the GMT date.

### `restCreate(array $prepared, Minn\Http\Request $request): int`

A comment created over REST with plugins loaded, as the reference's
controller creates one (probe rest-comment-save): the request's
fields through rest_preprocess_comment, the signed-in author filled
in, the content check (allow_empty_comment), the date, the length
check, wp_allow_comment (a duplicate is a 409, a flood a 400),
rest_pre_insert_comment, then wp_insert_comment over
wp_filter_comment; a refusal is its REST error.

- `@param array<string, mixed> $prepared the request's fields as the controller prepares them`

### `restUpdate(Minn\Content\CommentRecord $comment, array $prepared, ?string $status, Minn\Http\Request $request): void`

A comment changed over REST with plugins loaded: the request's fields
through rest_preprocess_comment, the content and length checks,
wp_update_comment, then the status, as the reference's controller
changes one.

- `@param array<string, mixed> $prepared`

### static `changeStatus(int $id, string $asked): void`

A REST status asked for, as the reference's controller changes it:
nothing when it is already so; approve and hold through
wp_set_comment_status with that word, spam, unspam, trash and untrash
through their own functions.

### `insert(array $columns): int`

Writes a new comment and returns its id; an approved one is counted on its post. @param array<string, mixed> $columns

- `@param array<string, mixed> $columns`

### `update(Minn\Content\CommentRecord $comment, array $columns, ?string $status): void`

An edit through REST: the fields, as wp_update_comment writes them
(which tells plugins even when nothing changed), then the status
through wp_set_comment_status when it moves.

- `@param array<string, string> $columns`

### `trash(Minn\Content\CommentRecord $comment): void`

Moves a comment to the trash, keeping its status and the time for the way back.

### `delete(Minn\Content\CommentRecord $comment): void`

Removes a comment for good.

### `restSaved(int $id, Minn\Http\Request $request, ?Minn\Content\CommentRecord $before): void`

rest_insert_comment, then rest_after_insert_comment, with the comment as it stands and the request; no $before is a new comment.

### `restDeleted(Minn\Content\CommentRecord $comment, array $data, Minn\Http\Request $request): void`

rest_delete_comment, after a trash or a delete, with the comment as it was and the response. @param array<string, mixed> $data

- `@param array<string, mixed> $data`

Internals: `preprocessed()` (private, line 138), `requireContent()` (private, line 153), `field()` (private, line 163), `requireLengths()` (private, line 169), `refusal()` (private, line 178)


## CommentFeedQuery

`final class Minn\Runtime\CommentFeedQuery` · `public/minn/src/Minn/Runtime/CommentFeedQuery.php`

The comments a comments feed's main query carries, as the reference
finds them (probe comment-feed-query). For a listing (the site's, an
archive's, a search's) they come before its posts, which are then
narrowed to the posts those comments are on; for a single post they come
after it. Either way the comment_feed_* filters shape the query (handed
the WP_Query), only approved comments that are not notes count, newest
first, as many as a feed carries.

Used by: `Minn\Runtime\PostQuery`

### static `listing(WP_Query $query, Minn\Runtime\PostQueryParts $parts, object $wpdb): void`

A listing's comments, then its posts narrowed to theirs: the posts query's join and where as they stand after posts_join.

### static `single(WP_Query $query, object $wpdb): void`

A single post's comments, once the query has found the post.

Internals: `request()` (private, line 49), `keep()` (private, line 60)


## CommentForm

`final class Minn\Runtime\CommentForm` · `public/minn/src/Minn/Runtime/CommentForm.php`

The comment form's submission with plugins loaded
(wp_handle_comment_submission), in the order the reference runs it
(contracts/runtime.md "The comment form"): a reply to a comment still
held is refused; the post is looked at, each refusal with its action
(comment_id_not_found, comment_closed, comment_on_trash, comment_on_draft,
comment_on_password_protected) and pre_comment_on_post when it takes
comments; the signed-in user's own name and addresses replace the form's,
or a site that requires sign-in refuses; the required fields, an empty
comment (allow_empty_comment) and the column lengths are checked; then
wp_new_comment stores it, where preprocess_comment, the duplicate and
flood checks and pre_comment_approved have their say. A refusal is a
WP_Error whose data is the status the form answers with; none means a
blank page.

### static `submit(array $form): WP_Comment|WP_Error`

The stored comment, or the refusal the form answers with.

- `@param array<string, mixed> $form the posted fields, unslashed`

Internals: `store()` (private, line 71), `postRefusal()` (private, line 89), `fieldRefusal()` (private, line 118)


## CommentPages

`final class Minn\Runtime\CommentPages` · `public/minn/src/Minn/Runtime/CommentPages.php`

Which page of a post's comments a comment falls on, and the link that
opens it there, as the reference works them out (probe comment-pages):
the page size from the arguments, the comments_per_page query variable
or the option (no pages at all when comments are not paged); a reply
takes its top-level comment's page while comments are threaded; a page
is the count of older top-level comments over the page size. The
default page loses its number when the oldest comments come first.

- const `DEFAULTS` = `array (   'type' => 'all',   'page' => '',   'per_page' => '',   'max_depth' => '', )`

### static `pageOf(mixed $commentId, array $args): ?int`

The page a comment is on, through get_page_of_comment (handed the
arguments as settled and as given); a threaded reply answers with
its top-level comment's page. Null for a comment that is not there.

- `@param array<string, mixed> $args`

### static `linkPage(?WP_Comment $comment, array $args): mixed`

The page a comment's link names (before get_comment_link): the one
asked for, the loop's, or the comment's own; '' for the default page
when the oldest comments come first.

- `@param array<string, mixed> $args`

### static `link(string $permalink, mixed $page): string`

A post's link opened at a page of its comments, as pretty or plain links write it.

Internals: `olderQuery()` (private, line 58)


## CommentQuery

`final readonly class Minn\Runtime\CommentQuery` · `public/minn/src/Minn/Runtime/CommentQuery.php`

The approval breakdown wp_count_comments reports (comment lists run through WP_Comment_Query and Minn\Runtime\CommentQueryRunner).

```php
__construct(Minn\Db $db)
```


### `breakdown(int $postId): array`

The counts wp_count_comments reports, for one post or the site. @return array<string, int>

- `@return array<string, int>`


## CommentQueryRunner

`final class Minn\Runtime\CommentQueryRunner` · `public/minn/src/Minn/Runtime/CommentQueryRunner.php`

WP_Comment_Query as the reference runs it (probe wp-comment-query-sql):
the variables filled and handed to pre_get_comments, the meta query's
SQL, comments_pre_query (which may answer), the clauses (the date query
among them) handed to comments_clauses, the request, found_comments_query
when a limited query counts what it found, then the ids, the count, or
the comments through the_comments, threaded or flattened when asked.
Results are not cached between queries (the reference keeps them in the
object cache by last_changed).

Used by: `Minn\Runtime\CommentQuery`

```php
__construct(object $wpdb)
```


### `run(WP_Comment_Query $query): mixed`

Runs the query, as WP_Comment_Query::get_comments() does: a count, ids, or comments.

Internals: `request()` (private, line 69), `comments()` (private, line 101)


## CommentQueryWhere

`final class Minn\Runtime\CommentQueryWhere` · `public/minn/src/Minn/Runtime/CommentQueryWhere.php`

WP_Comment_Query's WHERE pieces and the posts join, in the reference's
order (probe wp-comment-query-sql): the approval statuses (with the
readers whose held comments show), the post, the id lists, the author's
email and url, karma, the types (notes left out unless asked for), the
parent (0 for a threaded or flat query that names none), the user, the
search, the post's own fields, the author lists, then the meta and date
queries.

- const `ID_LISTS` = `array (   'comment__in' => '{c}.comment_ID IN',   'comment__not_in' => '{c}.comment_ID NOT IN',   'parent__in' => 'comment_parent IN',   'parent__not_in' => 'comment_parent NOT IN',   'post__in' => 'comment_post_ID IN',   'post__not_in' => 'comment_post_ID NOT IN', )`
- const `AUTHOR_LISTS` = `array (   'author__in' => 'user_id IN',   'author__not_in' => 'user_id NOT IN',   'post_author__in' => 'post_author IN',   'post_author__not_in' => 'post_author NOT IN', )`
- const `POST_FIELDS` = `array (   0 => 'post_author',   1 => 'post_name',   2 => 'post_parent',   3 => 'post_status',   4 => 'post_type', )`
- const `SEARCHED` = `array (   0 => 'comment_author',   1 => 'comment_author_email',   2 => 'comment_author_url',   3 => 'comment_author_IP',   4 => 'comment_content', )`
- const `ALL` = `'( comment_approved = \'0\' OR comment_approved = \'1\' )'`

Used by: `Minn\Runtime\CommentQueryRunner`

```php
__construct(object $wpdb)
```


### `pieces(WP_Comment_Query $query, array $meta): array`

The WHERE pieces (joined with AND by the caller) and the posts join
('' when no post field is asked after).

- `@param array{join?: string, where?: string} $meta the meta query's SQL, when it has clauses`
- `@return array{0: list<string>, 1: string}`

Internals: `approved()` (private, line 75), `post()` (private, line 95), `types()` (private, line 110), `parentAndUser()` (private, line 138), `search()` (private, line 151), `posts()` (private, line 168), `given()` (private, line 188), `listOf()` (private, line 194), `bare()` (private, line 203)


## CommentThreads

`final class Minn\Runtime\CommentThreads` · `public/minn/src/Minn/Runtime/CommentThreads.php`

A threaded or flat comment query's descendants as the reference fills
them (probe wp-comment-query-sql): one more comment query a level, with
the query's own variables but the level's parents, no parent, no limit
and no count; a threaded query hangs each reply under its parent, marks
every comment's children as known and keeps the top level by id, a flat
one lists the top level and then each level in its query's order.

- const `LEVEL` = `array (   'parent' => '',   'hierarchical' => false,   'number' => 0,   'offset' => 0,   'no_found_rows' => true, )`

Used by: `Minn\Runtime\CommentQueryRunner`

### `fill(WP_Comment_Query $query, array $top): array`

The query's top-level comments with their descendants filled in.

- `@param list<\WP_Comment> $top`
- `@return array<int, \WP_Comment>`

### static `flatten(array $children, array $args): array`

A comment's children, then each one's own, depth first, as a list.

- `@param array<array-key, mixed> $children`
- `@param array<string, mixed> $args get_children()'s arguments, handed down`
- `@return list<\WP_Comment>`


## Connectors

`final class Minn\Runtime\Connectors` · `public/minn/src/Minn/Runtime/Connectors.php`

The connectors registry: the external services a site talks to (AI
providers, spam filters, cloud services) and how each authenticates.
Rows are normalised on the way in, the way the reference keeps them;
the facade class hands them back to plugin code.

- const `Methods` = `array (   0 => 'api_key',   1 => 'application_password',   2 => 'none', )`
- const `AuthenticationKeys` = `array (   0 => 'method',   1 => 'credentials_url',   2 => 'setting_name',   3 => 'constant_name',   4 => 'env_var_name', )`
- const `MaskCap` = `16` — The reference stops adding bullets after sixteen, whatever the key's length.


### `register(string $id, array $args): ?Minn\Runtime\Refusal`

Registers a connector, or the refusal.

### `unregister(string $id): ?array`

Forgets a connector; its row, or null.

- `@return array<string, mixed>|null the row that was registered, null when there was none`

### `all(): array`

Every connector.

- `@return array<string, array<string, mixed>>`

### `get(string $id): ?array`

One connector, or null.

### `has(string $id): bool`

Whether a connector is registered.

### `registerDefaults(Closure $pluginActive): void`

The reference's three AI providers and Akismet. Activation is
checked through the caller's closure, so the plugin list is read
when asked.

- `@param Closure(string): bool $pluginActive`

### static `mask(string $key): string`

Keys of four characters or fewer are shown whole; longer ones keep their last four behind at most sixteen bullets.

### static `keySource(string $setting, string $envVar, string $constant, Closure $option): string`

Where a key comes from, in the reference's precedence: the environment,
then a constant, then the stored option; empty values do not count.

- `@param Closure(string): mixed $option`

### static `parseCredentials(string $value): array`

"user:password" split at the first colon, both halves trimmed; anything else is empty credentials.

### static `sanitizeCredentials(mixed $value, Closure $clean): array`

Stored credentials are an array of two text fields; a string, even a
"user:password" one, is not accepted from storage.

- `@param Closure(string): string $clean`

### static `credentials(array $auth, Closure $option, Closure $clean): array`

The credentials a connector authenticates with, from the same three
sources as a key, plus where they came from.

- `@param Closure(string): mixed $option`
- `@param Closure(string): string $clean`

Internals: `normalise()` (private, line 56)


## Constants

`final class Minn\Runtime\Constants` · `public/minn/src/Minn/Runtime/Constants.php`

The constants plugin code expects: the fixed set from data/constants.json
(captured from the reference) and the per-site ones computed here. Nothing
already defined is touched, so wp-config.php keeps the last word.

Used by: `Minn\Runtime\Runtime`

### static `define(Minn\Runtime\Runtime $runtime): void`

Defines the constants the reference defines at boot.

Internals: `maxMemoryLimit()` (private, line 82)


## CronTable

`final class Minn\Runtime\CronTable` · `public/minn/src/Minn/Runtime/CronTable.php`

The cron option's shape, operated on as data: timestamp => hook => key =>
entry, kept in natural timestamp order. The key is the reference's own
(a hash of the serialized argument list), so both stacks read one table.

Used by: `Minn\Cron\Cron`, `Minn\Ops\Diagnostics`

### static `key(array $args): string`

The key an event's arguments hash to.

- `@param array<int, array<string, array<string, array<string, mixed>>>> $crons`

### static `fromBlob(?string $blob): array`

The table read from the option's stored blob: the version marker
dropped, timestamps in order, an unreadable or absent blob empty.

- `@return array<int, array<string, array<string, array<string, mixed>>>>`

### static `hasNear(array $crons, int $timestamp, string $hook, string $key, int $window): bool`

Whether the same hook and arguments are already scheduled within the window around the timestamp.

### static `insert(array $crons, int $timestamp, string $hook, string $key, array $entry): array`

The table with an event added at a timestamp.

### static `remove(array $crons, int $timestamp, string $hook, string $key): array`

The table with one event removed.

### static `removeHook(array $crons, string $hook): array`

The table with every event of a hook removed.

- `@return array{0: array, 1: int} the table without the hook, and how many entries went`

### static `timestampsFor(array $crons, string $hook, string $key): array`

When a hook and key are scheduled.

- `@return list<int> every timestamp the hook and arguments are scheduled at`

### static `find(array $crons, string $hook, string $key, ?int $timestamp): ?array`

The entry for the hook and arguments at a timestamp, or at the next one when no timestamp is given, as [timestamp, entry].

### static `nextRun(int $timestamp, int $interval, int $now): int`

The next run of a recurring event: one interval from now, aligned to the original timestamp when it is in the past.

### static `due(array $crons, int $now): array`

The timestamps at or before now, in order.


## CurrentUser

`final class Minn\Runtime\CurrentUser` · `public/minn/src/Minn/Runtime/CurrentUser.php`

Who the request is, as the reference settles it before init: the user
determine_current_user names (the engine's own session, through
wp_validate_auth_cookie, when no plugin hooks it), which the request's
reader then follows. A plugin that signs in by its own token or cookie
(JWT, OAuth, single sign-on) is heard; one that names nobody signs the
request out.

Used by: `Minn\Runtime\Plugins`

### static `settle(Minn\Runtime\Runtime $runtime): int`

Settles the request's user and reader; returns the user's id (0 for nobody).


## DbDelta

`final readonly class Minn\Runtime\DbDelta` · `public/minn/src/Minn/Runtime/DbDelta.php`

dbDelta as the reference does it: a CREATE TABLE statement creates the
table when it is missing, otherwise the columns and keys the statement
has and the table lacks are added. Nothing is ever dropped or altered.

```php
__construct(Minn\Db $db)
```


### static `creates(array $queries): array`

The CREATE TABLE statements in a batch, keyed by table name. @param list<string> $queries @return array<string, string>

- `@param list<string> $queries @return array<string, string>`

### `apply(array $creates, bool $execute): array`

Applies CREATE TABLE statements as dbDelta does; the statements run.

- `@param array<string, string> $creates @return array<string, string> what was (or would be) done, by table or table.column`

### `tables(): array`

Every table in the database.

- `@return list<string>`

### `columns(string $table): array`

A table's columns with their definitions.

- `@return array<string, array<string, mixed>> by column name`

### `indexNames(string $table): array`

A table's index names.

- `@return list<string> lowercase key names`

### `run(string $ddl): void`

Runs one DDL statement.

Internals: `definitions()` (private, line 80)


## Deferrals

`final class Minn\Runtime\Deferrals` · `public/minn/src/Minn/Runtime/Deferrals.php`

The switches an importer flips for the length of a request (probe
plugin-helpers): term and comment counts deferred until the switch goes
off again, when what was put off is counted at once, and cache additions
suspended so a bulk read does not fill the cache. Each answers what it
is now; a value that is not a boolean asks without changing it. Kept in
the request's state, so a worker's next request starts with them off.

### static `terms(mixed $defer): bool`

wp_defer_term_counting: on, off, or asked; off counts what was put off.

### static `comments(mixed $defer): bool`

wp_defer_comment_counting: on, off, or asked; off counts what was put off.

### static `cacheAddition(mixed $suspend): bool`

wp_suspend_cache_addition: on, off, or asked.

### static `putOffTerms(array $terms, string $taxonomy): bool`

Puts terms' recount off while counting is deferred; false when it is not (count now). @param list<int> $terms

- `@param list<int> $terms`

### static `putOffComments(int $post): bool`

Puts a post's comment recount off while counting is deferred; false when it is not (count now).

Internals: `flip()` (private, line 73)


## EarlyFilters

`final class Minn\Runtime\EarlyFilters` · `public/minn/src/Minn/Runtime/EarlyFilters.php`

Filters that run before the runtime exists, over the hooks added that
early: WP-CLI's add_wp_hook writes them into $wp_filter as plain arrays
(tag => priority => id => function and accepted_args). The reference has
its hook API by then; the engine reads the same arrays directly.

Used by: `Minn\Front\Maintenance`

### static `apply(string $tag, mixed $value, mixed ...$args): mixed`

The value through every early callback on the tag, lowest priority first; as given when there are none.


## FileTypeCheck

`final class Minn\Runtime\FileTypeCheck` · `public/minn/src/Minn/Runtime/FileTypeCheck.php`

A file's type from its content as much as its name, as the reference's
wp_check_filetype_and_ext decides it (probe upload-filters): an image the
server can measure is what its bytes say, and a name with the wrong image
extension is corrected (a.png holding a JPEG becomes a.jpg); anything
claiming to be such an image and not being one has no type; any other
type must match what the content is, plain text standing for a few text
types and an office or archive container for the document types; and
the type must be one the site allows.

- const `IMAGES` = `array (   'image/jpeg' => 'jpg',   'image/png' => 'png',   'image/gif' => 'gif',   'image/bmp' => 'bmp',   'image/tiff' => 'tif',   'image/webp' => 'webp',   'image/avif' => 'avif',   'image/heic' => 'heic', )` — The image types measured by their bytes, and the extension each takes.
- const `TEXT_TYPES` = `array (   0 => 'text/plain',   1 => 'text/csv',   2 => 'application/csv',   3 => 'text/richtext',   4 => 'text/tsv',   5 => 'text/vtt', )`
- const `CONTAINERS` = `array (   0 => 'application/octet-stream',   1 => 'application/encrypted',   2 => 'application/CDFV2-encrypted',   3 => 'application/zip', )`

### static `check(string $file, string $filename, ?array $mimes, array $images): array`

ext, type, proper_filename, and the content's own type (real_mime).

- `@param array<string, string>|null $mimes`
- `@param array<string, string> $images getimagesize_mimes_to_exts`
- `@return array{ext: string|false, type: string|false, proper_filename: string|false, real_mime: string|false}`

Internals: `imageMime()` (private, line 68), `contentFits()` (private, line 75)


## FileUpload

`final class Minn\Runtime\FileUpload` · `public/minn/src/Minn/Runtime/FileUpload.php`

A file a plugin hands to wp_handle_upload or wp_handle_sideload, taken in
the reference's order (probe upload-filters, contracts/runtime.md
"Uploads"): the action's prefilter, where a plugin sanitizes the file or
refuses it with an error; its overrides; the upload's own errors, an
empty file and, for a form upload, the form and the upload itself; the
type, from the file's content as much as its name
(wp_check_filetype_and_ext), refused unless the site allows it or the
user may upload anything; the uploads folder; a unique, clean name
(wp_unique_filename); pre_move_uploaded_file, where a plugin may move it
itself; the move; and wp_handle_upload over the result.

- const `UPLOAD_ERRORS` = `array (   1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',   2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',   3 => 'The uploaded file was only partially uploaded.',   4 => 'No file was uploaded.',   6 => 'Missing a temporary folder.',   7 => 'Failed to write file to disk.',   8 => 'A PHP extension stopped the file upload.', )`

### static `handle(array $file, array|false $overrides, ?string $time, string $action): array`

The stored file (file, url, type) or ['error' => message].

- `@param array<string, mixed> $file name, type, tmp_name, size, error`
- `@param array<string, mixed>|false $overrides`
- `@return array<string, mixed>`

Internals: `refusal()` (private, line 76), `move()` (private, line 106)


## Heartbeat

`final class Minn\Runtime\Heartbeat` · `public/minn/src/Minn/Runtime/Heartbeat.php`

The heartbeat a signed-in page beats through admin-ajax.php, answered as
the reference answers it (suite ajax): the heartbeat nonce checked, and
when it is stale or wrong, wp_refresh_nonces asked for fresh ones (a
wrong one ends the beat there, marked expired); otherwise what the page
sent goes through heartbeat_received, the answer through heartbeat_send,
and heartbeat_tick hears it before the server's time is added.

### static `answer(array $post): array`

The answer for a beat, from the posted fields (unslashed).

- `@param array<string, mixed> $post`
- `@return array<string, mixed>`


## Hooks

`final class Minn\Runtime\Hooks` · `public/minn/src/Minn/Runtime/Hooks.php`

The hook registry plugin code registers into and the engine fires.
Semantics come from contracts/fixtures/api/hooks.json: callbacks run
by ascending priority then insertion order; a callback added at a
higher priority during a run takes part in that run, one added at the
current or a lower priority waits for the next; a callback removed
before its turn is skipped; the "all" hook sees every firing with the
hook name first and every argument regardless of its accepted count.

Used by: `Minn\Runtime\Runtime`


### `onNew(Closure $observer): void`

Called with each hook name the first time a callback registers under it.

### `storage(): array`

The live registry, for a hook object to share by reference.

### `actionCounters(): array`

How often each action, or each filter, has run, by hook.

### `filterCounters(): array`

How often each filter has run, by hook, by reference for the facade's global.

### `stackRef(): array`

The hooks running now, outermost first, by reference for the facade\'s globals.

### `currentPriority(string $hook): int|false`

The priority a running hook is at, or false when it is idle.

### `add(string $hook, callable|array|string $callback, string|int $priority = 10, int $accepted = 1): bool`

Adds a callback to a hook at a priority.

### `remove(string $hook, callable|array|string $callback, string|int $priority = 10): bool`

Removes a callback from a hook.

### `removeAll(string $hook, string|int|false $priority = false): bool`

Every callback of a hook, or only those at one priority. Always true, as observed.

### `has(string $hook, callable|array|string|false $callback = false): int|bool`

With a callback: its lowest priority, or false; without: whether anything is registered.

### `hasBeyond(string $hook, array $done): bool`

Whether anything is hooked besides the callbacks the engine does the
work of itself (named function => priority), as filterWithout skips them.

- `@param array<string, int> $done`

### `filter(string $hook, array $args): mixed`

Runs a filter and returns the value.

- `@param list<mixed> $args the value first`

### `filterWithout(string $hook, array $args, array $done): mixed`

Runs a filter without the callbacks a caller has already done the work
of, named function => priority: the engine renders post content through
its own pipeline, then runs the_content for everything else hooked
there. A callback a plugin removed is simply not there to skip. A
skipped callback can name a stand-in, [priority, function], that runs
in its place: what of its work the engine has left to do.

- `@param list<mixed> $args the value first`
- `@param array<string, int|array{0: int, 1: string}> $done`

### `action(string $hook, array $args): void`

Runs an action. With no arguments its callbacks still get one, an
empty string, as do_action() gives them in the reference (a callback
that requires a parameter is not short of one).

- `@param list<mixed> $args`

### `actionRef(string $hook, array $args): void`

Runs an action with exactly the arguments given, none included
(do_action_ref_array).

- `@param list<mixed> $args`

### `actionRefArray(string $hook, array $args): void`

do_action_ref_array: the action's callbacks get the arguments, and an
'all' callback gets them as the one array they were passed in, as the
reference hands them on.

- `@param list<mixed> $args`

### `filterRefArray(string $hook, array $args): mixed`

apply_filters_ref_array: as filter(), with an 'all' callback handed
the arguments as one array.

- `@param list<mixed> $args`

### `actionsDone(string $hook): int`

How often an action has run.

### `filtersDone(string $hook): int`

How often a filter has run.

### `current(): string|false`

The hook running now, or false.

### `doing(?string $hook): bool`

Whether a hook, or any hook, is running.

### `registered(): array`

Every hook with callbacks, by name.

- `@return array<string, array<int, list<callable>>> a read-only view for diagnostics`

Internals: `run()` (private, line 273), `nextPriority()` (private, line 318), `fireAll()` (private, line 330), `id()` (private, line 344)


## Interactivity

`final class Minn\Runtime\Interactivity` · `public/minn/src/Minn/Runtime/Interactivity.php`

Server-side directive processing for the Interactivity API: the state and
config stores, and the pass that resolves data-wp-bind, data-wp-class,
data-wp-style, data-wp-text, and data-wp-each against state and context
before the markup leaves the server. Behaviour pinned by the interactivity
probe fixture.

- const `VOID` = `array (   0 => 'area',   1 => 'base',   2 => 'br',   3 => 'col',   4 => 'embed',   5 => 'hr',   6 => 'img',   7 => 'input',   8 => 'link',   9 => 'meta',   10 => 'source',   11 => 'track',   12 => 'wbr', )`
- const `RAW` = `array (   0 => 'script',   1 => 'style',   2 => 'textarea',   3 => 'title', )`
- const `TAG` = `'/<(\\/?)([a-zA-Z][^\\s\\/>]*)((?:\\s+[^\\s=\\/>]+(?:\\s*=\\s*(?:"[^"]*"|\'[^\']*\'|[^\\s"\'>]+))?)*)\\s*(\\/?)>/'`
- const `UNRESOLVED` = `'' . "\0" . 'unresolved'`
- const `ATTR` = `'/\\s+([^\\s=\\/>]+)(?:\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s"\'>]+))?/'`

Used by: `Minn\Runtime\Runtime`


### `state(?string $namespace, array $state = array ( )): array`

Reads or extends a namespace's state.

- `@return array<string, mixed>`

### `allState(): array`

Every namespace's state, for the client.

- `@return array<string, array<string, mixed>> every namespace's state, for the client`

### `allConfig(): array`

Every namespace's config, for the client.

- `@return array<string, array<string, mixed>>`

### `config(string $namespace, array $config = array ( )): array`

Reads or extends a namespace's config.

- `@return array<string, mixed>`

### `context(?string $namespace = NULL): array`

The current directive context of a namespace.

- `@return array<string, mixed>`

### `element(): ?array`

The element whose directives are being evaluated.

- `@return array<string, mixed>|null`

### `process(string $html): string`

HTML with its directives resolved on the server.

Internals: `namespace()` (private, line 133), `tokenize()` (private, line 144), `walk()` (private, line 203), `applyDirectives()` (private, line 265), `bind()` (private, line 323), `interactiveNamespace()` (private, line 349), `pushContext()` (private, line 362), `evaluate()` (private, line 379), `expandEach()` (private, line 429), `markChildren()` (private, line 463), `attributeMap()` (private, line 495), `relative()` (private, line 508), `escape()` (private, line 513), `camel()` (private, line 518)


## MainQuery

`final class Minn\Runtime\MainQuery` · `public/minn/src/Minn/Runtime/MainQuery.php`

The query variables the reference's main query would carry for a URL the
engine has resolved, so plugin code reading is_page(), get_queried_object(),
or get_search_query() during a front-end render sees the same page.

Used by: `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`

### static `vars(Minn\Front\Resolution $resolution): array`

The query vars a resolution amounts to.

- `@return array<string, mixed>`


## Meta

`final readonly class Minn\Runtime\Meta` · `public/minn/src/Minn/Runtime/Meta.php`

The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need.

- const `TABLES` = `array (   'post' =>    array (     0 => 'postmeta',     1 => 'post_id',     2 => 'meta_id',   ),   'user' =>    array (     0 => 'usermeta',     1 => 'user_id',     2 => 'umeta_id',   ),   'term' =>    array (     0 => 'termmeta',     1 => 'term_id',     2 => 'meta_id',   ),   'comment' =>    array (     0 => 'commentmeta',     1 => 'comment_id',     2 => 'meta_id',   ), )`

```php
__construct(Minn\Db $db)
```


### static `knows(string $type): bool`

Whether an object type has a meta table.

### static `clausesFromQueryVars(array $queryVars): array`

The meta query in flat query vars (meta_key, meta_value, the compare
and type variants) as one clause, AND'd ahead of an explicit
meta_query as its own group, as WP_Meta_Query::parse_query_vars reads
them. An empty meta_value is no value.

- `@param array<string, mixed> $queryVars`
- `@return array<array-key, mixed>`

### `all(string $type, int $objectId): array`

Every row of an object's meta, values as stored, grouped by key in id order. @return array<string, list<string>>

- `@return array<string, list<string>>`

### `prime(string $type, array $objectIds): array`

update_meta_cache: each object's meta as all() gives it, the cached
ones from the object cache and the rest read in one query and cached;
an object with none has an empty list.

- `@param list<int> $objectIds`
- `@return array<int, array<string, list<string>>>`

### `rowsOf(string $type, int $objectId): array`

Every row of an object's meta in id order, for removing them one by one. @return list<array{meta_id: int, meta_key: string, meta_value: string}>

- `@return list<array{meta_id: int, meta_key: string, meta_value: string}>`

### `byId(string $type, int $metaId): ?array`

One meta row by its id, under the table's own column names, values as stored; null when there is none. @return array<string, string>|null

- `@return array<string, string>|null`

### `columns(string $type): array`

The column holding the object's id, and the one holding the row's: post_id and meta_id, user_id and umeta_id. @return array{0: string, 1: string}

- `@return array{0: string, 1: string}`

### `rewrite(string $type, int $metaId, string $key, string $stored): void`

Sets one row's key and stored value.

### `add(string $type, int $objectId, string $key, string $stored): int`

Inserts a meta row and returns its id.

### `matching(string $type, int $objectId, string $key): array`

The rows of one key on one object, in id order. @return list<array{meta_id: int, meta_value: string}>

- `@return list<array{meta_id: int, meta_value: string}>`

### static `idsToUpdate(array $rows, string $stored, ?string $previous): array`

Which of a key's rows an update touches: none when no previous value was
named and the first row already holds the value, all of them when no
previous value was named, otherwise only the rows holding it.

- `@param list<array<string, mixed>> $rows`
- `@return list<int>`

### `updateRows(string $type, array $ids, string $stored): void`

Sets the value of the given meta rows.

### `find(string $type, ?int $objectId, string $key, ?string $stored): array`

The rows a delete would take: by key, for one object or all, optionally only a stored value. @return list<array{meta_id: int, object_id: int}>

- `@return list<array{meta_id: int, object_id: int}>`

### `deleteRows(string $type, array $ids): void`

Deletes the given meta rows.

- `@param list<int> $ids`

Internals: `spec()` (private, line 19)


## MetaKeys

`final class Minn\Runtime\MetaKeys` · `public/minn/src/Minn/Runtime/MetaKeys.php`

The meta keys code registers, kept where the reference keeps them
($wp_meta_keys: object type => subtype => key => arguments), with what a
registration wires up (probe meta-api). A key's sanitize and auth
callbacks hang on their filters (with _for_{subtype} for a subtype's
key); a key with no auth callback gets __return_true, or __return_false
when protected. A default is served through filter_default_metadata. A
registration is refused (with the reference's notice) for an array shown
in REST without its items' schema, revisions where the type or subtype
has none, and, once its callbacks are hooked, a default its schema does
not accept. The old form (callbacks as plain arguments) hooks them and
returns false.

- const `DEFAULTS` = `array (   'object_subtype' => '',   'type' => 'string',   'label' => '',   'description' => '',   'default' => '',   'single' => false,   'sanitize_callback' => NULL,   'auth_callback' => NULL,   'show_in_rest' => false,   'revisions_enabled' => false, )`

Used by: `Minn\Rest\RestMeta`

### static `register(string $objectType, string $key, mixed $args, mixed $deprecated): bool`

Registers a key; true when it joins the registry.

### static `unregister(string $objectType, string $key, string $subtype): bool`

Takes a key out of the registry, and its callbacks off their filters.

### static `of(string $objectType, string $subtype = ''): array`

The keys registered for an object type and subtype ('' for every subtype), in registration order. @return array<string, array<string, mixed>>

- `@return array<string, array<string, mixed>>`

### static `forObject(string $objectType, string $subtype): array`

The keys that apply to one object: those for every subtype, then its subtype's. @return array<string, array<string, mixed>>

- `@return array<string, array<string, mixed>>`

### static `defaultValue(mixed $value, int $objectId, string $key, bool $single, string $objectType): mixed`

The registered default for a key as filter_default_metadata answers:
the one for every subtype, else the object's subtype's; the value
handed in when neither registered one.

### static `subtype(string $objectType, int $objectId): string`

The object's subtype: a post's type, a term's taxonomy, comment or user while they exist; '' otherwise.

### static `capabilities(string $capability, int $userId, array $args): ?array`

A meta capability (edit, add or delete a post's, comment's, term's or
user's meta) as the reference maps it: what editing the object needs,
then the capability itself when the key is not allowed (protected, or
refused by its auth filter); nothing for an object that does not
exist. Null for any other capability.

- `@param list<mixed> $args the object id, then the key`
- `@return list<string>|null`

Internals: `refusal()` (private, line 176), `defaultFits()` (private, line 194)


## MetaTypes

`final class Minn\Runtime\MetaTypes` · `public/minn/src/Minn/Runtime/MetaTypes.php`

Meta types a plugin brought, by the table it named on $wpdb as
"{$type}meta" (WooCommerce's order items keep theirs in
woocommerce_order_itemmeta). The reference reads any such table with
"{$type}_id" for the object and meta_id for the row; Meta does the same.

Used by: `Minn\Runtime\Meta`


### static `register(string $type, string $table): bool`

Registers a meta type by its full table name; refused unless both are plain identifiers.

### static `table(string $type): ?string`

The full table name of a registered meta type, or null.


## NavMenu

`final class Minn\Runtime\NavMenu` · `public/minn/src/Minn/Runtime/NavMenu.php`

Nav-menu item decoration for wp_nav_menu(): the reference's class tokens
(menu-item, the type and object tokens, menu-item-home) and the current
markers (current-menu-item with its page compat tokens, the parent and
ancestor chain). The facade's wp_nav_menu() fetches and sorts the items;
this marks them against the standing main query.


### static `build(object $args): string|false|null`

The whole menu for wp_nav_menu(): resolve, fetch, decorate, walk,
wrap, filter. Null when there is no menu (the caller's fallback
runs); false when the items filtered away to nothing.

### static `decorate(array $items): array`

Menu items with the classes and flags the reference adds for the current page.

- `@param list<object> $items`
- `@return list<object>`

Internals: `menuForArgs()` (private, line 52), `wrapId()` (private, line 70), `container()` (private, line 85), `singularContext()` (private, line 156), `markQueriedAncestry()` (private, line 199), `isCurrent()` (private, line 222), `markAncestors()` (private, line 258), `currentUrl()` (private, line 308)


## OEmbed

`final class Minn\Runtime\OEmbed` · `public/minn/src/Minn/Runtime/OEmbed.php`

oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes.

### static `providerFor(array $providers, string $url): ?string`

The endpoint of the provider whose mask matches a URL, or null.

- `@param array<string, array{0: string, 1: bool}> $providers mask => [endpoint with {format}, mask is a regex]`

### static `parseJson(string $body): ?array`

An oEmbed JSON body as an array, or null.

- `@return array<string, mixed>|null`

### static `parseXml(string $body): ?array`

An oEmbed XML body as an array, or null.

- `@return array<string, mixed>|null`

### static `html(array $data, string $url, Closure $escUrl, Closure $escAttr, Closure $escHtml): ?string`

The embed HTML for an oEmbed response, or null.

- `@param array<string, mixed> $data the oEmbed payload`
- `@param Closure(string): string $escUrl @param Closure(string): string $escAttr @param Closure(string): string $escHtml`

### static `stripNewlines(string $html): string`

Drops newlines from embed markup while leaving the inside of <pre> blocks untouched.


## ObjectCache

`final class Minn\Runtime\ObjectCache` · `public/minn/src/Minn/Runtime/ObjectCache.php`

The per-request object cache behind wp_cache_*: groups of keys, nothing persistent.

Used by: `Minn\Runtime\Runtime`


### `get(string $key, string $group, ?bool $found = NULL): mixed`

A cached value, or false.

### `set(string $key, mixed $value, string $group): bool`

Stores a value.

### `add(string $key, mixed $value, string $group): bool`

Stores a value only when the key is empty.

### `delete(string $key, string $group): bool`

Removes a key.

### `flush(): bool`

Empties the cache.

### `flushGroup(string $group): bool`

Empties one group.


## ObjectTerms

`final class Minn\Runtime\ObjectTerms` · `public/minn/src/Minn/Runtime/ObjectTerms.php`

wp_get_object_terms's handling of taxonomies registered with their own
query arguments, as the reference handles them: among several, each such
taxonomy is asked on its own with its arguments over the caller's; alone,
its arguments join the caller's.

### static `byTaxonomyArgs(array $objectIds, array $taxonomies, array $args): array`

The terms asked for apart, the taxonomies left (keys as they were), and the arguments for those.

- `@param list<int> $objectIds`
- `@param array<int, string> $taxonomies`
- `@param array<string, mixed> $args`
- `@return array{0: list<mixed>, 1: array<int, string>, 2: array<string, mixed>}`


## OptionSanitizer

`final class Minn\Runtime\OptionSanitizer` · `public/minn/src/Minn/Runtime/OptionSanitizer.php`

A core option's value cleaned as the reference's sanitize_option cleans
it (probe sanitize-option), before sanitize_option_{$option}: counts as
whole numbers, the site's name and tagline escaped, formats and mail
settings stripped of markup, addresses checked and kept as they were
(with a settings error) when they are not addresses, word lists split
and trimmed, the timezone and language checked against what exists. Any
other option is left to its filter, where register_setting puts a
plugin's sanitize_callback.

- const `COUNTS` = `array (   0 => 'thumbnail_size_w',   1 => 'thumbnail_size_h',   2 => 'medium_size_w',   3 => 'medium_size_h',   4 => 'medium_large_size_w',   5 => 'medium_large_size_h',   6 => 'large_size_w',   7 => 'large_size_h',   8 => 'mailserver_port',   9 => 'comment_max_links',   10 => 'page_on_front',   11 => 'page_for_posts',   12 => 'rss_excerpt_length',   13 => 'default_category',   14 => 'default_email_category',   15 => 'default_link_category',   16 => 'close_comments_days_old',   17 => 'comments_per_page',   18 => 'thread_comments_depth',   19 => 'users_can_register',   20 => 'start_of_week',   21 => 'site_icon',   22 => 'fileupload_maxk', )`
- const `STRIPPED` = `array (   0 => 'date_format',   1 => 'time_format',   2 => 'mailserver_url',   3 => 'mailserver_login',   4 => 'mailserver_pass',   5 => 'upload_path', )`
- const `EMAIL` = `'The email address entered did not appear to be a valid email address. Please enter a valid email address.'`
- const `ERRORS` = `array (   'siteurl' => 'The WordPress address you entered did not appear to be a valid URL. Please enter a valid URL.',   'home' => 'The Site address you entered did not appear to be a valid URL. Please enter a valid URL.',   'timezone_string' => 'The timezone you have entered is not valid. Please select a valid timezone.',   'permalink_structure' => 'A structure tag is required when using custom permalinks. <a href="https://wordpress.org/documentation/article/customize-permalinks/#choosing-your-permalink-structure">Learn more</a>', )`

### static `clean(string $option, mixed $value): mixed`

The value as sanitize_option leaves it, through sanitize_option_{$option}.

Internals: `rule()` (private, line 49), `lists()` (private, line 69), `email()` (private, line 85), `perPage()` (private, line 92), `structure()` (private, line 100), `words()` (private, line 108), `domains()` (private, line 115), `languages()` (private, line 122)


## Options

`final class Minn\Runtime\Options` · `public/minn/src/Minn/Runtime/Options.php`

Options as plugin code sees them: PHP values, decoded from the stored
blob by the engine's own reader, cached for the request so a value written
and read again in one request keeps its PHP type (an int stays an int,
false stays false) exactly as the reference shows. A value holding an
object is kept as its stored text and decoded afresh on every read, as
the reference does: a plugin that changes the object it was handed and
saves it must find the stored copy still different, or its change is
never written.

- const `GUARDED` = `array (   0 => 'minn_runtime_symbols',   1 => 'minn_recovery_strikes',   2 => 'minn_cron_lock', )` — The options the engine keeps for itself: the symbol-gate cache a plugin
could otherwise rewrite to pass its own gate, the recovery strikes, the
cron lock, and the sign-in throttle rows. The facade refuses to write
them; the engine writes them through this class directly.
- const `GUARDED_PREFIX` = `'minn_login_throttle_'`
- const `AUTOLOAD_VALUES` = `array (   0 => 'yes',   1 => 'on',   2 => 'auto-on',   3 => 'auto', )` — The autoload column values that load an option with every request, in the reference's order.

Used by: `Minn\Cli\OptionCommand`, `Minn\Runtime\AjaxController`, `Minn\Runtime\Runtime`, `Minn\Runtime\Symbols`

```php
__construct(Minn\Db $db)
```


### static `guarded(string $name): bool`

Whether plugin code is refused a write to this option.

### `autoloaded(): array`

Every autoloaded option as stored. @return array<string, string>

- `@return array<string, string>`

### `get(string $name): mixed`

An option's value, decoded, or null when unset.

### `exists(string $name): bool`

Whether an option exists.

### `knownMissing(string $name): bool`

Whether a read this request already found the option unset (the reference's notoptions).

### `add(string $name, mixed $value, string $autoload = 'auto'): bool`

Writes a new option. add_option has already decided it is new (its
value is still the default); a row that is there anyway is written
over, as the reference's add does, and false comes back when it
already held this value.

### `upsert(string $name, string $stored, string $autoload): bool`

Writes an option row whether or not one exists, as the reference does
for every add: two requests that both find a transient missing and
both add it must not fail on the unique name, so the second write lands
over the first. True when the row was inserted or changed, false when
the same value was already there.

### `update(string $name, mixed $value, ?string $autoload = NULL): bool`

False when the value is unchanged, as the reference reports.

### `markAutoload(string $name): bool`

Flips the autoload column; false when the option is missing or already so.

### `unmarkAutoload(string $name): bool`

Stops an option loading on every request; false when it already did not, or is unset.

### `delete(string $name): bool`

Removes an option.

### `expiredTransientNames(int $now, string $prefix = '_transient_timeout_'): array`

The names of transients whose expiry has passed. The timeout row is the
one that knows, so the sweep reads those and hands back the bare names.

- `@return list<string>`

### `forget(string $name): void`

Drops an option from the cache.

### static `toStorage(mixed $value): string`

What the reference stores: arrays and objects serialized, scalars as their string form.

### static `fromStorage(string $raw): mixed`

A stored option value decoded the way the reference reads it.

Internals: `remember()` (private, line 147), `holdsObject()` (private, line 160), `switchAutoload()` (private, line 178)


## PackageDownload

`final class Minn\Runtime\PackageDownload` · `public/minn/src/Minn/Runtime/PackageDownload.php`

The publisher's say over its own download. Before fetching an update
package, the reference asks `upgrader_pre_download`: a plugin that hosts
itself answers there, and answering is how it proves the archive is the
one it published. Three answers, which are the reference's:

a file path  the publisher fetched and verified the package itself
a refusal    the publisher rejects this download, with its reason
nothing      nobody vouched for it

The engine asks the same question, and installs a package from outside
the wordpress.org directory only on the first answer. That is stricter
than the reference, which downloads whatever the offer names; here an
archive nobody vouched for is never unpacked over a plugin folder.

- const `HOOK` = `'upgrader_pre_download'`

Used by: `Minn\Ops\Updates`

### static `verified(string $package, array $hookExtra): Minn\Runtime\Refusal|string|null`

The publisher's verified copy of the package, its refusal, or null
when nothing answered.

- `@param array<string, string> $hookExtra what is being updated, as the reference passes it ('plugin' or 'theme')`

Internals: `refusal()` (private, line 45)


## PageMenu

`final class Minn\Runtime\PageMenu` · `public/minn/src/Minn/Runtime/PageMenu.php`

The page-list menu a classic theme falls back to when no menu is
assigned to a location. Storefront's header is one of these, so its
markup is what the theme's CSS binds to.

Shapes captured from the reference: the optional home item carries no
class (the attribute is written but left empty), a page item carries
`page_item page-item-{id}` plus `current_page_item` for the page being
viewed, and the whole list is wrapped in the caller's before/after.

### static `items(array $pages, ?array $home, string $linkBefore = '', string $linkAfter = ''): string`

The page menu's list items.

- `@param list<array{id: int, title: string, url: string, current: bool}> $pages`
- `@param array{label: string, url: string, current: bool}|null $home`

### static `home(mixed $showHome, string $url, bool $current, string $defaultLabel = 'Home'): ?array`

The home item a `show_home` argument asks for: true (or 1) means the
default label, a string is the label itself, anything falsy means no
item at all.

- `@return array{label: string, url: string, current: bool}|null`


## Pages

`final class Minn\Runtime\Pages` · `public/minn/src/Minn/Runtime/Pages.php`

get_pages() as the reference shapes it: its arguments as a post query, and the tree order of the result.

- const `DEFAULTS` = `array (   'child_of' => 0,   'sort_order' => 'ASC',   'sort_column' => 'post_title',   'hierarchical' => 1,   'exclude' =>    array (   ),   'include' =>    array (   ),   'meta_key' => '',   'meta_value' => '',   'authors' => '',   'parent' => -1,   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => 0,   'post_type' => 'page',   'post_status' => 'publish', )`
- const `COLUMNS` = `array (   'post_title' => 'title',   'menu_order' => 'menu_order',   'post_date' => 'date',   'post_modified' => 'modified',   'ID' => 'ID',   'post_author' => 'author',   'post_name' => 'name',   'post_parent' => 'parent', )`

### static `queryArgs(array $parsed, array $include, array $exclude): array`

The post query arguments the get_pages() arguments amount to. @param list<int> $include @param list<int> $exclude

- `@param list<int> $include @param list<int> $exclude`

### static `arrange(array $pages, array $parsed): array`

The pages as the reference leaves them: with a hierarchy (unless a
parent is named or pages are picked by id) or a child_of, only what
descends from that page (the root by default), each parent followed
by its subtree; then any branch left out, its places left empty.

- `@param list<object> $pages objects with ID and post_parent`
- `@return array<int, object>`

### static `children(array $pages, int $parent): array`

What descends from a page within the list, depth first, siblings in
list order (get_page_children); a page whose parent is not reached is
left out.

- `@param list<object> $pages`
- `@return list<object>`

### static `descendants(array $pages, int $parent): array`

Every page under one ancestor, in list order. @param list<object> $pages @return list<object>

- `@param list<object> $pages @return list<object>`


## Patterns

`final class Minn\Runtime\Patterns` · `public/minn/src/Minn/Runtime/Patterns.php`

The block pattern, pattern category, and block style registries as data.
Entries registered after init are remembered separately, because the
editor asks for those on their own.


### `registerPattern(mixed $name, mixed $properties): ?Minn\Runtime\Refusal`

Registers a block pattern, or the refusal.

### `unregisterPattern(string $name): bool`

Forgets a pattern.

### `pattern(string $name): ?array`

One pattern, or null.

### `patterns(): array`

Every pattern.

- `@return list<array<string, mixed>>`

### `patternsAfterInit(): array`

The patterns registered after init, which the editor lists separately.

### `registerCategory(mixed $name, mixed $properties): ?Minn\Runtime\Refusal`

Registers a pattern category, or the refusal.

### `unregisterCategory(string $name): bool`

Forgets a category.

### `category(string $name): ?array`

One category, or null.

### `categories(): array`

Every category.

- `@return list<array<string, mixed>>`

### `categoriesAfterInit(): array`

The categories registered after init.

### `registerStyle(mixed $blocks, mixed $properties): ?Minn\Runtime\Refusal`

Registers a block style, or the refusal.

- `@param string|list<string> $blocks`

### `unregisterStyle(string $block, string $style): bool`

Forgets a block style.

### `style(string $block, string $style): ?array`

One block style, or null.

### `styles(?string $block = NULL): array`

Every block style, or one block's.

- `@return array<string, array<string, array<string, mixed>>>`


## PlaceholderTrace

`final class Minn\Runtime\PlaceholderTrace` · `public/minn/src/Minn/Runtime/PlaceholderTrace.php`

Records every call into a generated placeholder while a site opts in by
having wp-content/minn-placeholder-trace.log on disk: one line per call
with the symbol, the plugin file that called it, and the request. The
log says which placeholders deserve behaviour; an absent file costs one
stat per request.


### static `hit(string $symbol): void`

Logs a placeholder symbol being called, when the trace file exists.


## Placeholders

`final class Minn\Runtime\Placeholders` · `public/minn/src/Minn/Runtime/Placeholders.php`

The printf placeholders plugin code hands wpdb::prepare, filled the way
the reference fills them: a bare %s is escaped and quoted; a numbered
(%1$s), padded (%5s) or precise (%.2f) one is formatted and escaped but
never quoted, so a plugin can name a table with it; %d and %f cast; %i
backticks an identifier; %% is a literal. Numbered placeholders address
the argument list directly while unnumbered ones count from the first
on their own, as vsprintf does. A placeholder with no argument behind it
empties the whole query, as the reference does.

- const `SPEC` = `'/%(?:(\\d+)\\$)?([-+ 0]*)(\\d*)(?:\\.(\\d+))?([sdfFi%])/'`

### static `fill(string $query, array $args, callable $escape): ?string`

Fills a query's placeholders from the arguments; null when one has no argument.

- `@param list<mixed> $args`
- `@param callable(string): string $escape the connection's string escape`

Internals: `quoted()` (private, line 56), `text()` (private, line 61)


## PluginUpdates

`final class Minn\Runtime\PluginUpdates` · `public/minn/src/Minn/Runtime/PluginUpdates.php`

The update offers the site's own plugins publish. A plugin that hosts
itself, or sells itself, is not in the wordpress.org directory and never
appears in the directory's answer: it publishes its offer by filtering
the update transient WordPress reads, which is the only place anyone
learns of it. The engine asks the same question of the runtime, in the
shape the reference asks it, so a self-hosted plugin is offered its
update here exactly as it would be on WordPress.

The transient carries `checked` (every installed plugin and its version),
and a plugin's updater returns early when that is empty, so the installed
versions are what make the question answerable.

- const `HOOK` = `'site_transient_update_plugins'`

Used by: `Minn\Ops\Updates`

### static `supplied(array $installed): array`

What the site's plugins offer for themselves, empty when no runtime
is booted or nothing filters the transient.

- `@param array<string, string> $installed plugin file => installed version`
- `@return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}`

### static `fromFiltered(mixed $filtered, array $installed): array`

The filtered transient as the state's two buckets: offers under
`plugins`, plugins that answered "current" under `no_update`, both
as arrays, and only for files this site actually has.

- `@param array<string, string> $installed`
- `@return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}`

Internals: `bucket()` (private, line 68)


## Plugins

`final class Minn\Runtime\Plugins` · `public/minn/src/Minn/Runtime/Plugins.php`

Loads the site's plugins into the runtime the way the reference does:
mu-plugins first, then active_plugins in stored order, each file
included once. A plugin loads only when the static symbol read finds
nothing the runtime lacks; otherwise it is reported and skipped so the
site keeps rendering. The lifecycle actions fire between the phases.

- const `MINN_ADMIN` = `'minn-admin/minn-admin.php'` — Minn Admin loads as code for its adapters (surfaces, licenses,
connectors, custom CSS, the plugin routes the engine has no
controller for), but the engine owns the shell, the front bar, the
sign-in flow, and maintenance: those hooks come off right after the
include so the two never print twice or disagree.
- const `MINN_ADMIN_HOOKS` = `array (   0 =>    array (     0 => 'template_redirect',     1 =>      array (       0 => 'Minn_Admin',       1 => 'maybe_render_app',     ),     2 => 0,   ),   1 =>    array (     0 => 'template_redirect',     1 =>      array (       0 => 'Minn_Admin',       1 => 'maybe_maintenance_mode',     ),     2 => 1,   ),   2 =>    array (     0 => 'rest_authentication_errors',     1 =>      array (       0 => 'Minn_Admin',       1 => 'maintenance_rest',     ),     2 => 20,   ),   3 =>    array (     0 => 'login_redirect',     1 =>      array (       0 => 'Minn_Admin',       1 => 'login_redirect',     ),     2 => 20,   ),   4 =>    array (     0 => 'show_admin_bar',     1 =>      array (       0 => 'Minn_Admin',       1 => 'enforce_toolbar_policy',     ),     2 => 99,   ),   5 =>    array (     0 => 'show_admin_bar',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'suppress_core_bar',     ),     2 => 100,   ),   6 =>    array (     0 => 'wp_enqueue_scripts',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'enqueue',     ),     2 => 10,   ),   7 =>    array (     0 => 'wp_footer',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'render',     ),     2 => 10,   ),   8 =>    array (     0 => 'body_class',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'body_class',     ),     2 => 10,   ), )`

Used by: `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Extension\Loader`, `Minn\Theme\ClassicRenderer`


### static `load(Minn\Runtime\Runtime $runtime): void`

Loads the active plugins as code and fires the boot hooks. Recovery
is armed for exactly this window: a failure here is one every
visitor would hit, so it may be recorded against its extension.

### static `loadTheme(Minn\Runtime\Runtime $runtime): void`

The theme's setup without the plugins: setup_theme, the theme's
functions.php, after_setup_theme, then wp_loaded. The probe runtime
ends its boot with this, so a theme's supports are what a request's
would be and a late declaration is judged as one; it skips init,
whose work the probes compare on their own.

### static `loaded(): array`

The plugins that loaded.

- `@return list<string>`

### static `skipped(): array`

The plugins the symbol gate refused, with what they lacked.

- `@return array<string, array<string, mixed>> plugin file => why it did not load`

### static `isLoaded(string $plugin): bool`

True when the named plugin file is running as code this request.

Internals: `boot()` (private, line 67), `loadThemeFunctions()` (private, line 148), `rememberThemeDomain()` (private, line 171), `includeFile()` (private, line 205), `registerRealpath()` (private, line 236), `isolatedInclude()` (private, line 251)


## PostData

`final class Minn\Runtime\PostData` · `public/minn/src/Minn/Runtime/PostData.php`

The loop's view of a post, as the reference's generate_postdata and
get_the_content give it (probes the-content, excerpt): the content split
into pages at <!--nextpage--> (the line breaks beside the marker go with
it) and handed to content_pagination, the page the query asks for, and
"more" when the main query shows a single post, a page or a feed; then
the content of that page, cut at the more tag with a more link (or run
on past it when "more" is set), the teaser dropped when asked or marked
<!--noteaser-->.

### static `generate(WP_Post $post): array`

id, authordata, currentday, currentmonth, page, pages, multipage,
more, numpages.

- `@return array<string, mixed>`

### static `content(?string $moreLinkText, WP_Post $post, array $elements): string`

The content get_the_content gives: the asked page, cut at the more tag
unless "more" is set.

- `@param array<string, mixed> $elements as generate() gives them, with strip_teaser when the teaser should go`


## PostEvents

`final readonly class Minn\Runtime\PostEvents` · `public/minn/src/Minn/Runtime/PostEvents.php`

What the reference's REST controllers tell plugins about a post they
write, for the engine's own controllers: the same actions in the same
order with the same arguments, fired through the facade's lifecycle
functions so the facade's own writes say the same (contracts/runtime.md
"Writes tell plugins"). Without a booted runtime every method does
nothing, so a write with no plugins loaded is exactly what it was.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\PostsWriteController`

### `live(): bool`

Whether plugins are loaded to be told anything.

### `beforeSave(array $columns, ?Minn\Content\PostRecord $existing): void`

Before the row is written: pre_post_insert for a new post, pre_post_update for one that exists. @param array<string, mixed> $columns

- `@param array<string, mixed> $columns`

### `saved(int $id, ?Minn\Content\PostRecord $before): void`

Once the row is written: the caches, the status transition, the edit actions of an update, the save actions.

### `ensureCategory(Minn\Content\PostWriter $writer, int $id): void`

A post keeps a category through every save, the default when it has
none: through wp_set_post_categories with plugins loaded (which tells
them, set_object_terms included), quietly without.

### `applyTerms(Minn\Content\PostWriter $writer, int $id, array $body, string $type = ''): void`

The terms a REST body names, set through wp_set_object_terms with
plugins loaded, quietly without: a plugin's type takes its own REST
taxonomies under their REST bases (probe rest-plugin-types).

- `@param array<string, mixed> $body`

### `applyMeta(Minn\Content\PostWriter $writer, int $id, array $body, string $type): void`

The meta a REST body names: through the registered keys with plugins
loaded (probe rest-meta; a plugin's type only when it supports custom
fields), core's own written directly without.

- `@param array<string, mixed> $body`

### `attachedFile(Minn\Media\Writer $library, int $id, string $relative): void`

The file a new attachment holds: through add_post_meta with plugins loaded, written directly without.

### `attachmentAdded(int $id): void`

add_attachment, once a new attachment's row and file are in.

### `attachmentEdited(int $id, Minn\Content\PostRecord $before): void`

edit_attachment and attachment_updated, after an attachment's row changed.

### `attachmentGenerated(int $id, string $file, string $mime): void`

A new attachment's metadata made by the runtime, as the reference's
REST upload makes it: wp_generate_attachment_metadata over the stored
file (the sizes cut with the attachment's id, the metadata stored as
each one lands, so an optimiser hooked there finds it), then
wp_update_attachment_metadata. Only for the images the engine sizes,
as without plugins.

### `altText(Minn\Media\Writer $library, int $id, string $alt): void`

An attachment's alt text: through update_post_meta with plugins loaded, written directly without.

### `restInserted(int $id, Minn\Http\Request $request, ?Minn\Content\PostRecord $before): void`

rest_insert_{type}, before the request's own terms and fields are applied; a post with no $before is a new one.

### `restAfterInsert(int $id, Minn\Http\Request $request, ?Minn\Content\PostRecord $before): void`

rest_after_insert_{type}, once the request's terms and fields are in; a post with no $before is a new one.

### `afterInsert(int $id, ?Minn\Content\PostRecord $before): void`

wp_after_insert_post, last; the revision of an update is saved from it.

### `restDeleted(Minn\Content\PostRecord $post, array $data, Minn\Http\Request $request): void`

rest_delete_{type}, after a trash or a delete, with the post as it was answered and the response. @param array<string, mixed> $data

- `@param array<string, mixed> $data`

Internals: `rest()` (private, line 179), `wpPost()` (private, line 201)


## PostInsert

`final readonly class Minn\Runtime\PostInsert` · `public/minn/src/Minn/Runtime/PostInsert.php`

The decisions behind wp_insert_post: which columns a postarr fills, when
the post counts as empty, the status a publish request lands in, the dates
and the slug, the categories a new post gets. The rows are written by
Content\PostWriter; the facade fires the hooks around each step.

- const `COLUMNS` = `array (   0 => 'post_author',   1 => 'post_date',   2 => 'post_date_gmt',   3 => 'post_content',   4 => 'post_title',   5 => 'post_excerpt',   6 => 'post_status',   7 => 'comment_status',   8 => 'ping_status',   9 => 'post_password',   10 => 'post_name',   11 => 'to_ping',   12 => 'pinged',   13 => 'post_modified',   14 => 'post_modified_gmt',   15 => 'post_content_filtered',   16 => 'post_parent',   17 => 'guid',   18 => 'menu_order',   19 => 'post_type',   20 => 'post_mime_type', )`

```php
__construct(Minn\Content\PostWriter $writer, int $userId, Closure $discussion, Closure $supports, Closure $canPublish, Closure $gmtFromDate, Closure $now)
```
- `@param Closure(string, string): string $discussion a new post's comment or ping status by type, as get_default_comment_status has it`
- `@param Closure(string, string): bool $supports whether a post type supports a feature`
- `@param Closure(string): bool $canPublish whether the current user may publish the type`
- `@param Closure(string): string $gmtFromDate the site-local date as GMT`
- `@param Closure(bool): string $now the current local (or GMT) MySQL time`


### `columns(array $postarr, ?array $existing): array`

The columns the posts table takes, filled from a postarr, with a new post's defaults. @param array<string, mixed>|null $existing @return array<string, string>

- `@param array<string, mixed>|null $existing @return array<string, string>`

### `isEmpty(array $columns, ?array $existing): bool`

A post with nothing in title, content, and excerpt is empty when its type supports all three. @param array<string, mixed>|null $existing

- `@param array<string, mixed>|null $existing`

### `resolve(array $columns, ?array $existing): array`

The columns as they will be written: the status a request lands in
(attachments inherit, unprivileged publishes pend, a future date
schedules), the dates, the slug (sanitized; not yet free), and the
integer columns.

- `@param array<string, mixed>|null $existing`
- `@return array<string, string>`

### `persist(array $columns, ?int $existingId, Closure $guid): int`

Writes the resolved columns; a new post without a guid gets the ?p= form. @return int the post id

### static `categories(array $postarr, string $type, string $status, bool $update, array $taxonomies, int $default): ?array`

The categories a saved post should carry: the ones given, or the
default for a new post of a type that has categories; null when
nothing should change.

- `@param list<string> $taxonomies the type's taxonomies`
- `@return list<int>|null`

Internals: `type()` (private, line 179)


## PostLinks

`final class Minn\Runtime\PostLinks` · `public/minn/src/Minn/Runtime/PostLinks.php`

Post addresses as the reference's link functions build them (probe
permalinks): a post through pre_post_link (the structure) and post_link;
a page from get_page_uri through _get_page_link and page_link; an
attachment under its parent's address, or its own; a plugin's type by
its address pattern (the permastruct its rewrite registered) through
post_type_link, by its query var without one. Plain (?p=, ?page_id=,
?post_type=) unless its status is public, or private and readable by
whoever asks, or it is a sample: a draft, a scheduled or trashed post, a
revision, a private one a visitor asks for. "Leaving the name" keeps the slug as its placeholder
(%postname%, %pagename%, %{type}%) for an editor to fill in, and
get_sample_permalink shows a draft as it would be published.

- const `LEAVE_NAME` = `1` — The slug kept as its placeholder.
- const `SAMPLE` = `2` — The address an editor previews: a draft as if published.
- const `UNPUBLISHED` = `array (   0 => 'draft',   1 => 'pending',   2 => 'auto-draft',   3 => 'future', )`

### static `post(WP_Post $post, int $flags): string`

A post's address (not a page, attachment or plugin type).

### static `page(WP_Post $post, int $flags): string`

A page's own address, before page_link: ?page_id= while unpublished, its tag when the name is left, else its path.

### static `attachment(WP_Post $post, int $flags): string`

An attachment's address: under a parent it belongs to, else its own slug, else ?attachment_id=.

### static `custom(WP_Post $post, int $flags): string`

A plugin type's address by its pattern, by its query var, or by the type and id plainly.

### static `sample(WP_Post $post, ?string $title, ?string $name): array`

The address an editor shows with the slug to edit: the post as if
published, its name made unique, the address with the name left,
a page's parents written in.

- `@return array{0: string, 1: string}`

Internals: `tokens()` (private, line 44), `category()` (private, line 62), `plain()` (private, line 133)


## PostLookup

`final readonly class Minn\Runtime\PostLookup` · `public/minn/src/Minn/Runtime/PostLookup.php`

The post reads plugin code asks for by shape: a page by title, revisions, counts.

```php
__construct(Minn\Db $db)
```


### `idByTitle(string $title, array $types): ?int`

The id of a post with this title among the types, or null.

- `@param list<string> $types`

### `revisionsOf(int $postId): array`

Revision rows newest first. @return list<array<string, mixed>>

- `@return list<array<string, mixed>>`

### `countByStatus(string $type): array`

How many posts of a type there are per status.

- `@return array<string, int> status => count`

### `countAttachments(): array`

How many attachments there are per mime type.

- `@return array<string, int> mime type => count, plus 'trash'`

### `countByAuthor(int $userId, array $types, array $statuses): int`

How many posts an author has among the types and statuses.

- `@param list<string> $types @param list<string> $statuses`

### `idsByAuthor(int $userId): array`

Every post id of an author.

- `@return list<int>`

### `attachmentIdByFile(string $path): ?int`

The attachment whose stored file path is the given one.


## PostQuery

`final class Minn\Runtime\PostQuery` · `public/minn/src/Minn/Runtime/PostQuery.php`

WP_Query::get_posts as the reference runs it (probe wp-query-sql): the
variables parsed and handed to pre_get_posts, the clauses written piece by
piece, every filter a plugin may change them through in the reference's
order (suppress_filters keeps the ones it keeps), the request split into
an id query when that is cheaper, the count, a single post's status check
and preview, sticky posts on the home listing, and the results filters.

Used by: `Minn\Runtime\Runtime`

```php
__construct(Minn\Db $db)
```


### `run(WP_Query $query, object $wpdb, mixed $urlSearch): mixed`

Runs a query object's variables and fills it in: the posts (or ids, or
id => parent), the request, the counts. Returns what get_posts returns.

### `search(WP_Query $query, array $q, mixed $urlSearch): string`

A search's WHERE fragment, settling the search variables (the terms,
how many the string split into, the title matches its order ranks by).

- `@param array<string, mixed> $q`

### `stopwords(): array`

The search stopwords, translated and filtered. @return list<string>

- `@return list<string>`

Internals: `prepare()` (private, line 50), `defaults()` (private, line 68), `pageSize()` (private, line 110), `clauses()` (private, line 139), `taxonomies()` (private, line 173), `searchOrder()` (private, line 238), `filtered()` (private, line 256), `through()` (private, line 279), `paging()` (private, line 289), `execute()` (private, line 305), `idsOnly()` (private, line 335), `select()` (private, line 357), `foundPosts()` (private, line 378)


## PostQueryParts

`final class Minn\Runtime\PostQueryParts` · `public/minn/src/Minn/Runtime/PostQueryParts.php`

The pieces of one WP_Query run as the reference builds them and hands
them to filters. The seven clause pieces stay untyped: a filter may hand
back anything, and the request is written from whatever it handed back.

- const `PIECES` = `array (   0 => 'where',   1 => 'groupby',   2 => 'join',   3 => 'orderby',   4 => 'distinct',   5 => 'fields',   6 => 'limits', )` — The pieces posts_clauses and posts_clauses_request see, in their order.

Used by: `Minn\Runtime\CommentFeedQuery`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostQueryWhere`

```php
__construct(string $table)
```

- `mixed $where`
- `mixed $groupby`
- `mixed $join`
- `mixed $orderby`
- `mixed $distinct`
- `mixed $fields`
- `mixed $limits`
- `mixed $search`
- `string $whichauthor`
- `string $whichmimetype`
- `bool $statusJoin` — Whether an attachment's status follows its parent's (a taxonomy archive's attachments).
- `int $page`
- `mixed $postType` — The post type the query settles on: a name, a list, `any`, or empty.
- `?WP_Post_Type $typeObject`
- `string $typeCap`
- `array $statuses` — The statuses asked for by name, which a single post's status check lets through. @var list<string>
- readonly `string $table`

### `pieces(): array`

The seven pieces by name. @return array<string, mixed>

- `@return array<string, mixed>`

### `take(array $clauses): void`

Takes back the pieces a clauses filter returned; one it dropped becomes empty. @param array<array-key, mixed> $clauses

- `@param array<array-key, mixed> $clauses`

### `request(string $foundRows, mixed $fields): string`

The SELECT as the reference writes it, line breaks and all, for the given field list.


## PostQueryResults

`final class Minn\Runtime\PostQueryResults` · `public/minn/src/Minn/Runtime/PostQueryResults.php`

What WP_Query does with its posts once it has them, as the reference does
it (probe wp-query-sql): a single post whose status is not public is
dropped unless the reader may see it (a draft its editor may preview, with
the_preview); sticky posts move to the front of the home listing's first
page, the ones it did not find fetched by a nested query; the_posts; and
the post objects, count and current post set.

Used by: `Minn\Runtime\PostQuery`

```php
__construct(WP_Query $query, Minn\Runtime\PostQueryParts $parts)
```


### `settle(array $q): void`

Settles the posts a query found: status check, stickies, the_posts, then the count and the current post. @param array<string, mixed> $q

- `@param array<string, mixed> $q`

Internals: `singleStatus()` (private, line 44), `visible()` (private, line 66), `stickies()` (private, line 94)


## PostQueryStatus

`final class Minn\Runtime\PostQueryStatus` · `public/minn/src/Minn/Runtime/PostQueryStatus.php`

WP_Query's post type and status clause as the reference writes it (probe
wp-query-sql). Statuses asked for by name: the type clause, then the
statuses (any, less those excluded from search; private kept apart when
the query asks for readable posts; the user's own when they may not read
or edit others'; an attachment's parent's status for a taxonomy archive).
None asked for, on a listing: each queried type, sorted, with its public
statuses, the admin list's protected ones in the admin, and private ones
the signed-in user may read. A single post: the type clause alone.

Used by: `Minn\Runtime\PostQuery`

```php
__construct(WP_Query $query, Minn\Runtime\PostQueryParts $parts)
```


### `settleType(): void`

Settles the post type the caps come from: a list of several types has
none (its caps are named for multiple_post_type), any other names one.

### `append(array $q): void`

Appends the type and status clause. @param array<string, mixed> $q

- `@param array<string, mixed> $q`

Internals: `typeWhere()` (private, line 62), `cap()` (private, line 88), `named()` (private, line 95), `listing()` (private, line 139), `privateStatuses()` (private, line 168)


## PostQueryTax

`final class Minn\Runtime\PostQueryTax` · `public/minn/src/Minn/Runtime/PostQueryTax.php`

The taxonomy side of WP_Query as the reference runs it (probe
wp-query-sql): the clauses its variables make (tax_query, taxonomy and
term, each taxonomy's query var, cat and the category__ lists, tag and the
tag__ lists), settled back into the variables; the post types a taxonomy
archive with no type searches; and the cat, category_name, tag_id,
taxonomy and term variables set from the terms queried.

Used by: `Minn\Runtime\PostQuery`

### static `clauses(WP_Query $query, array $q): array`

The tax query a set of variables makes, settling the variables it reads.

- `@param array<string, mixed> $q`
- `@return list<array<string, mixed>>|array<string, mixed>`

### static `postTypes(array $taxonomies): array|string`

The post types a taxonomy archive with no type searches: every
searchable type the queried taxonomies are registered for, one as a
string, several sorted, none as `any`.

- `@param list<string> $taxonomies`
- `@return string|list<string>`

### static `compat(WP_Query $query, array $q, array $queried): void`

The compatibility variables the queried terms set: taxonomy and term
(or term_id) from the first taxonomy other than category and post_tag
when none is set, and cat, category_name and tag_id from those two.

- `@param array<string, array{terms?: list<mixed>, field?: string}> $queried`

Internals: `queryVar()` (private, line 53), `cat()` (private, line 68), `categoryLists()` (private, line 85), `tag()` (private, line 108), `tagLists()` (private, line 128)


## PostQueryWhere

`final class Minn\Runtime\PostQueryWhere` · `public/minn/src/Minn/Runtime/PostQueryWhere.php`

The WHERE fragments WP_Query writes from its variables, in the reference's
order and spacing (probe wp-query-sql): menu order, the legacy m date and
the date variables, a slug or a page path, ids, parents, a page id (which
replaces everything before it), authors, comment counts, mime types,
passwords, and comment and ping status. Each appends to the parts it is
handed and settles the variables it reads, as the reference leaves them.

- const `LEGACY_DATE` = `array (   4 => 'MONTH',   6 => 'DAYOFMONTH',   8 => 'HOUR',   10 => 'MINUTE',   12 => 'SECOND', )`

Used by: `Minn\Runtime\PostQuery`

```php
__construct(WP_Query $query, Minn\Runtime\PostQueryParts $parts, string $table)
```


### `dates(array $q): void`

Menu order, the m date, the date variables and the date_query. @param array<string, mixed> $q

- `@param array<string, mixed> $q`

### `names(array $q): void`

The post a slug, a page path, an attachment slug, a name list, an id or
an id list names, and the parent; a post type's query var stands for
the name (the page path, for a hierarchical type).

- `@param array<string, mixed> $q`

### `authors(array $q): void`

Authors (an author list's negatives become author__not_in, which wins
over author__in), an author slug, a comment count, and mime types. The
slug and mime clauses wait to follow the search.

- `@param array<string, mixed> $q`

### `access(array $q): void`

A password, or whether there is one, and comment and ping status. @param array<string, mixed> $q

- `@param array<string, mixed> $q`

Internals: `nameList()` (private, line 87), `typeQueryVar()` (private, line 97), `pagename()` (private, line 119), `ids()` (private, line 158), `authorName()` (private, line 232), `commentCount()` (private, line 245)


## PostRevisions

`final class Minn\Runtime\PostRevisions` · `public/minn/src/Minn/Runtime/PostRevisions.php`

A post's revisions as the reference's wp_save_post_revision keeps them
(probe revisions): only for a type that supports revisions and while
wp_revisions_to_keep allows any; compared with the latest revision
(wp_save_post_revision_check_for_changes, the revision fields with their
whitespace evened out, wp_save_post_revision_post_has_changed, whose
default also compares the revisioned meta); saved through wp_insert_post
and _wp_put_post_revision (whose default copies the revisioned meta);
then the oldest dropped past the limit, after
wp_save_post_revision_revisions_before_deletion.

### static `save(WP_Post $post): ?int`

The new revision's id, or null when none was called for.

### static `put(WP_Post $post): ?int`

The revision written through wp_insert_post, then _wp_put_post_revision; its id.

Internals: `changed()` (private, line 42), `prune()` (private, line 67)


## PostSave

`final class Minn\Runtime\PostSave` · `public/minn/src/Minn/Runtime/PostSave.php`

A REST save's columns through the filters the reference's save runs
before it writes, in its order (probe post-insert-filters,
contracts/runtime.md "Saves plugins can change"): rest_pre_insert_{type}
over the prepared post; every column through its db context
(pre_post_* and *_save_pre, where kses and the custom-CSS strip sit) on
slashed text; wp_insert_post_empty_content, which refuses a post with no
content, title or excerpt; wp_insert_post_parent; for a post that is live,
the slug, made again from the filtered title when the request gave none,
through pre_wp_unique_post_slug, the bad-slug check and
wp_unique_post_slug; and wp_insert_post_data over the row. What a filter
changes is written. Without plugins loaded the columns are as given.

- const `DATA` = `array (   0 => 'post_author',   1 => 'post_date',   2 => 'post_date_gmt',   3 => 'post_content',   4 => 'post_content_filtered',   5 => 'post_title',   6 => 'post_excerpt',   7 => 'post_status',   8 => 'post_type',   9 => 'comment_status',   10 => 'ping_status',   11 => 'post_password',   12 => 'post_name',   13 => 'to_ping',   14 => 'pinged',   15 => 'post_modified',   16 => 'post_modified_gmt',   17 => 'post_parent',   18 => 'menu_order',   19 => 'post_mime_type',   20 => 'guid', )` — The row wp_insert_post_data is handed, in its keys.
- const `FIELDS` = `array (   'title' => 'post_title',   'content' => 'post_content',   'excerpt' => 'post_excerpt',   'status' => 'post_status',   'date' => 'post_date',   'date_gmt' => 'post_date_gmt',   'slug' => 'post_name',   'password' => 'post_password',   'author' => 'post_author',   'parent' => 'post_parent',   'menu_order' => 'menu_order',   'comment_status' => 'comment_status',   'ping_status' => 'ping_status', )` — A REST field and the column it fills in the prepared post.
- const `UNSLUGGED` = `array (   0 => 'draft',   1 => 'pending',   2 => 'auto-draft', )`
- const `PARENT` = `'post_parent'`
- const `NEW_POSTARR` = `array (   0 => 'ID',   1 => 'post_author',   2 => 'post_date',   3 => 'post_date_gmt',   4 => 'post_content',   5 => 'post_content_filtered',   6 => 'post_title',   7 => 'post_excerpt',   8 => 'post_status',   9 => 'post_type',   10 => 'comment_status',   11 => 'ping_status',   12 => 'post_password',   13 => 'to_ping',   14 => 'pinged',   15 => 'post_parent',   16 => 'menu_order',   17 => 'guid',   18 => 'import_id', )` — The new post array wp_insert_post_parent is handed beside the whole one.
- const `DEFAULTS` = `array (   'post_author' => 0,   'post_content' => '',   'post_content_filtered' => '',   'post_title' => '',   'post_excerpt' => '',   'post_status' => 'draft',   'post_type' => 'post',   'comment_status' => '',   'ping_status' => '',   'post_password' => '',   'to_ping' => '',   'pinged' => '',   'post_parent' => 0,   'menu_order' => 0,   'guid' => '',   'import_id' => 0,   'context' => '',   'post_date' => '',   'post_date_gmt' => '', )` — wp_insert_post's defaults, in its order (the order its filters run in).

Used by: `Minn\Rest\PostsWriteController`

### static `filter(array $columns, ?Minn\Content\PostRecord $before, array $body, Minn\Http\Request $request, string $type, Minn\Content\PostSlugs $slugs): array`

The columns to write after the filters.

- `@param array<string, mixed> $columns what the engine settled: a new post's whole row, or an update's changes`
- `@param array<string, mixed> $body the request's fields`
- `@return array<string, mixed>`

### static `sanitized(array $postarr): array`

wp_insert_post's array with its defaults filled in (ID 0 for a new
post), through sanitize_post's db context: slashed in, slashed out.

- `@param array<string, mixed> $postarr`
- `@return array<string, mixed>`

### static `refusesEmpty(array $sanitized, string $type): bool`

wp_insert_post_empty_content over whether a type with an editor, a title and an excerpt has none of them. @param array<string, mixed> $sanitized

- `@param array<string, mixed> $sanitized`

### static `parent(array $sanitized, int $id): int`

The parent through wp_insert_post_parent (whose default refuses a loop). @param array<string, mixed> $sanitized

- `@param array<string, mixed> $sanitized`

### static `slugFilters(string $slug, int $id, string $status, string $type, int $parent, Minn\Content\PostSlugs $slugs): string`

A live post's slug through the reference's three filters:
pre_wp_unique_post_slug may settle it; otherwise it is made free in
its type's scope, the bad-slug filter judging the slug asked for when
nothing else has taken it; and wp_unique_post_slug has the last word.

### static `guidAndSlug(array $columns, int $postId, string $type, Minn\Content\PostSlugs $slugs): array`

The row's guid and slug as wp_insert_post settles them (probe
insert-defaults): an update keeps the guid it had, in its display
form, ignoring a new one asked for; a live post's slug goes through
the slug filters.

- `@param array<string, string> $columns`
- `@return array<string, string>`

### static `keepsSlug(string $status, string $type): bool`

Whether a save leaves the slug as it is: a draft, a pending post, an auto-draft, a revision, a personal data request.

### static `data(array $row, array $sanitized, array $unsanitized, int $postId): array`

The row through wp_insert_post_data (wp_insert_attachment_data for an
attachment), handed slashed as the reference hands it and unslashed
back.

- `@param array<string, mixed> $row the row, unslashed`
- `@param array<string, mixed> $sanitized`
- `@param array<string, mixed> $unsanitized`
- `@return array<string, mixed>`

Internals: `prepared()` (private, line 187), `changed()` (private, line 222)


## QueriedObject

`final readonly class Minn\Runtime\QueriedObject` · `public/minn/src/Minn/Runtime/QueriedObject.php`

Which object a query is "about", read from its flags and variables: a term
(by id or slug), a post type, the posts page, the current post, or an
author. The caller materialises the record; this only decides where to look.

- readonly `string $kind`
- readonly `string $field`
- readonly `string|int $value`
- readonly `string $taxonomy`

### static `locate(array $vars, array $flags, Minn\Runtime\Registry $registry, callable $option): self`

The queried object the query vars and flags point at.

- `@param array<string, mixed> $vars`
- `@param array<string, bool> $flags the query's is_* flags`
- `@param callable(string): mixed $option a filtered option read`

Internals: `taxonomyTerm()` (private, line 62)


## QueryFlags

`final readonly class Minn\Runtime\QueryFlags` · `public/minn/src/Minn/Runtime/QueryFlags.php`

The conditional flags a set of query variables implies (is_single, is_archive,
is_home, ...), derived the way the reference's parse step derives them, plus
the variables after the integer casts that step applies.

- const `INTEGER_VARS` = `array (   0 => 'p',   1 => 'page_id',   2 => 'attachment_id',   3 => 'year',   4 => 'monthnum',   5 => 'day',   6 => 'w',   7 => 'paged', )`

Used by: `Minn\Runtime\QueriedObject`

- readonly `array $vars`
- readonly `array $flags`

### static `fill(array $vars, Minn\Runtime\Registry $registry): array`

The variables with every one the reference fills given its empty
value: the template's keys up to search_columns (the ones after it are
get_posts' own, settled when the query runs).

- `@param array<string, mixed> $vars`
- `@return array<string, mixed>`

### static `derive(array $vars, Minn\Runtime\Registry $registry, callable $option): self`

The is_* flags the query vars amount to.

- `@param array<string, mixed> $vars the filled query variables`
- `@param callable(string): mixed $option a filtered option read`

### static `customTaxonomyVar(array $vars, Minn\Runtime\Registry $registry): ?array`

The first registered taxonomy (other than the two built-in ones) whose
query variable carries a value, as [taxonomy name, query var].

- `@return array{0: string, 1: string}|null`

Internals: `sanitize()` (private, line 108), `archiveFlags()` (private, line 138)


## Recovery

`final readonly class Minn\Runtime\Recovery` · `public/minn/src/Minn/Runtime/Recovery.php`

Recovery from a fatal in someone else's code. When a plugin or theme
kills the boot of a request, the file it died in names it; a second
such failure inside ten minutes records it as paused, and the next
request loads without it: the site comes back on its own instead of
staying down until a human reads the log. One failure is only noted:
a pause is a site-wide decision, and a single request can be a
crafted one. Failures after boot (a route, a template) never pause
anything; see Failure::armRecovery().

The paused list is stored where WordPress stores it, in the same shape
(`paused_plugins` keyed by plugin file, `paused_themes` by stylesheet,
each holding type, file, line and message), so a site that ejects back
to WordPress finds the pause it left with. The strikes live in the
engine's own `minn_recovery_strikes` option.

- const `PLUGINS_OPTION` = `'paused_plugins'`
- const `THEMES_OPTION` = `'paused_themes'`
- const `STRIKES_OPTION` = `'minn_recovery_strikes'`
- const `WINDOW` = `600` — Seconds inside which a second boot failure pauses the extension.

Used by: `Minn\Cli\MinnCommand`, `Minn\Engine`, `Minn\Runtime\Plugins`

```php
__construct(Minn\Content\Site $site, string $contentDir)
```


### `blame(string $file, array $realpaths = array ( ), ?array $active = NULL): ?array`

The extension a file belongs to: a plugin as the active `folder/file.php`
whose folder holds the file (or the bare file of a single-file plugin),
a theme as its slug. The name is the one the loader checks against the
paused list, so a fatal deep inside a plugin's subfolders pauses the
plugin and not a folder nothing is called by. Anything outside the
plugin and theme folders belongs to nobody, and is never paused: a
fatal in the engine or in core is not a plugin's fault and pausing
something would not fix it. A symlinked plugin reports its real
location; the realpath map (real folder to the folder under plugins/)
brings it home first.

- `@param array<string, string> $realpaths`
- `@param list<string>|null $active the active plugin files; read from the site when omitted`
- `@return array{kind: 'plugin'|'theme', name: string}|null`

### `pause(array $blamed, array $error): bool`

Records an extension as paused. Returns false when it was already
paused, so a caller can tell a fresh failure from a repeat and only
notify once.

- `@param array{kind: string, name: string} $blamed`
- `@param array{type: int, file: string, line: int, message: string} $error`

### `strike(array $blamed): bool`

Notes a boot failure against an extension. True when it is the
second inside the window, which is when the caller pauses it; the
first is only remembered. Strikes older than the window are dropped
as they are read, so the option never grows.

- `@param array{kind: string, name: string} $blamed`

### `pausedPlugins(): array`

The plugins recovery mode has paused.

- `@return array<string, array<string, mixed>>`

### `pausedThemes(): array`

The themes recovery mode has paused.

- `@return array<string, array<string, mixed>>`

### `resume(string $kind, string $name): bool`

Lets an extension load again. Returns false when it was not paused.

### `resumeAll(): int`

Lets everything load again. Returns how many were released.

Internals: `pluginFile()` (private, line 90), `activePlugins()` (private, line 104), `strikes()` (private, line 161), `forgetStrikes()` (private, line 168), `read()` (private, line 223), `write()` (private, line 231)


## Refusal

`final readonly class Minn\Runtime\Refusal` · `public/minn/src/Minn/Runtime/Refusal.php`

A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error.

Used by: `Minn\Blocks\BlockName`, `Minn\Content\Menus`, `Minn\Ops\Updates`, `Minn\Rest\ArgCheck`, `Minn\Rest\BlockRendererController`, `Minn\Rest\MenusController`, `Minn\Rest\ParamCheck`, `Minn\Rest\RouteMatch`, `Minn\Rest\Schema`, `Minn\Runtime\Connectors`, `Minn\Runtime\PackageDownload`, `Minn\Runtime\Patterns`, `Minn\Runtime\UserInsert`, `Minn\Runtime\UserSave`

```php
__construct(string $code, string $message, mixed $data = NULL)
```

- readonly `string $code`
- readonly `string $message`
- readonly `mixed $data`


## RegisteredSettings

`final class Minn\Runtime\RegisteredSettings` · `public/minn/src/Minn/Runtime/RegisteredSettings.php`

Settings as register_setting keeps them (probe rest-settings): the
arguments a plugin passes, filtered through register_setting_args and
laid over the defaults (a string, in its group, unlabelled, undescribed,
unsanitized, not shown in REST); the sanitize callback put on
sanitize_option_{$name} with the value alone; a registered default
answering get_option through filter_default_option; register_setting
fired once kept. unregister_setting takes all of it back. Core's own
settings (the site's title, tagline, addresses, formats, reading and
discussion defaults) are registered as the REST server starts.

- const `KEY` = `'registered_settings'`
- const `ARRAY_ITEMS` = `'When registering an "array" setting to show in the REST API, you must specify the schema for each array item in "show_in_rest.schema.items".'`
- const `CORE` = `array (   'blogname' =>    array (     0 => 'general',     1 => 'string',     2 => 'Title',     3 => 'Site title.',     4 =>      array (       'name' => 'title',     ),   ),   'blogdescription' =>    array (     0 => 'general',     1 => 'string',     2 => 'Tagline',     3 => 'Site tagline.',     4 =>      array (       'name' => 'description',     ),   ),   'siteurl' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'Site URL.',     4 =>      array (       'name' => 'url',       'schema' =>        array (         'format' => 'uri',       ),     ),   ),   'admin_email' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'This address is used for admin purposes, like new user notification.',     4 =>      array (       'name' => 'email',       'schema' =>        array (         'format' => 'email',       ),     ),   ),   'timezone_string' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'A city in the same timezone as you.',     4 =>      array (       'name' => 'timezone',     ),   ),   'date_format' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'A date format for all date strings.',     4 => true,   ),   'time_format' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'A time format for all time strings.',     4 => true,   ),   'start_of_week' =>    array (     0 => 'general',     1 => 'integer',     2 => '',     3 => 'A day number of the week that the week should start on.',     4 => true,   ),   'WPLANG' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'WordPress locale code.',     4 =>      array (       'name' => 'language',     ),     5 => 'en_US',   ),   'use_smilies' =>    array (     0 => 'writing',     1 => 'boolean',     2 => '',     3 => 'Convert emoticons like :-) and :-P to graphics on display.',     4 => true,     5 => true,   ),   'default_category' =>    array (     0 => 'writing',     1 => 'integer',     2 => '',     3 => 'Default post category.',     4 => true,   ),   'default_post_format' =>    array (     0 => 'writing',     1 => 'string',     2 => '',     3 => 'Default post format.',     4 => true,   ),   'posts_per_page' =>    array (     0 => 'reading',     1 => 'integer',     2 => 'Maximum posts per page',     3 => 'Blog pages show at most.',     4 => true,     5 => 10,   ),   'show_on_front' =>    array (     0 => 'reading',     1 => 'string',     2 => 'Show on front',     3 => 'What to show on the front page',     4 => true,   ),   'page_on_front' =>    array (     0 => 'reading',     1 => 'integer',     2 => 'Page on front',     3 => 'The ID of the page that should be displayed on the front page',     4 => true,   ),   'page_for_posts' =>    array (     0 => 'reading',     1 => 'integer',     2 => '',     3 => 'The ID of the page that should display the latest posts',     4 => true,   ),   'default_ping_status' =>    array (     0 => 'discussion',     1 => 'string',     2 => '',     3 => 'Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.',     4 =>      array (       'schema' =>        array (         'enum' =>          array (           0 => 'open',           1 => 'closed',         ),       ),     ),   ),   'default_comment_status' =>    array (     0 => 'discussion',     1 => 'string',     2 => 'Allow comments on new posts',     3 => 'Allow people to submit comments on new posts.',     4 =>      array (       'schema' =>        array (         'enum' =>          array (           0 => 'open',           1 => 'closed',         ),       ),     ),   ),   'site_logo' =>    array (     0 => 'general',     1 => 'integer',     2 => 'Logo',     3 => 'Site logo.',     4 =>      array (       'name' => 'site_logo',     ),   ),   'site_icon' =>    array (     0 => 'general',     1 => 'integer',     2 => 'Icon',     3 => 'Site icon.',     4 => true,   ), )` — Core's settings: name => [group, type, label, description, show_in_rest, default when it has one].

### static `all(): array`

Every registered setting by option name, in registration order. @return array<string, array<string, mixed>>

- `@return array<string, array<string, mixed>>`

### static `register(string $group, string $name, mixed $args): void`

register_setting: $args an array, or (as it once was) the sanitize callback alone.

### static `unregister(string $group, string $name, mixed $deprecated = ''): void`

unregister_setting: the setting, its sanitize callback and its default gone, unregister_setting fired.

### static `defaultOf(string $option, mixed $default): mixed`

A setting's registered default, or $default when it registered none.

### static `registerCore(): void`

register_initial_settings: core's settings, each through register_setting.


## Registry

`final class Minn\Runtime\Registry` · `public/minn/src/Minn/Runtime/Registry.php`

Post types, taxonomies, and statuses as plugin code registers and reads
them. The built-in set is data/registry.json, captured from the
reference; registrations derive their defaults the way the content
probe observed (contracts/fixtures/api/content.json).

Used by: `Minn\Front\Permalinks`, `Minn\Rest\StatusesController`, `Minn\Runtime\QueriedObject`, `Minn\Runtime\QueryFlags`, `Minn\Runtime\Runtime`

```php
__construct(string $engineDir)
```

- readonly `array $queryVars`
- readonly `array $publicQueryVars`

### `postTypes(): array`

Every post type.

- `@return array<string, array<string, mixed>>`

### `postType(string $name): ?array`

One post type, or null.

### `taxonomies(): array`

Every taxonomy.

- `@return array<string, array<string, mixed>>`

### `taxonomy(string $name): ?array`

One taxonomy, or null.

### `statuses(): array`

Every post status.

- `@return array<string, array<string, mixed>>`

### `status(string $name): ?array`

One post status, or null.

### `registerPostType(string $name, array $args): array`

Registers a post type with the reference's defaults filled in.

- `@param array<string, mixed> $args`

### `unregisterPostType(string $name): bool`

Forgets a non-builtin post type.

### `addSupport(string $type, string $feature, array $args): void`

Support declared before the type registers waits aside (the reference
keeps features apart from the type objects, so declaring one does not
make the type exist) and merges in when register_post_type arrives.

### `removeSupport(string $type, string $feature): void`

Removes a feature from a post type.

### `supports(string $type): array`

The features a post type supports.

- `@return array<string, mixed> the features a type supports, registered or declared ahead`

### `registerTaxonomy(string $name, array $objectTypes, array $args): array`

Registers a taxonomy with the reference's defaults filled in.

- `@param list<string> $objectTypes @param array<string, mixed> $args`

### static `settlesRewrites(): bool`

Whether a registered rewrite settles into its full form: with pretty permalinks or in the admin; otherwise it stays as given.

### `unregisterTaxonomy(string $name): bool`

Forgets a non-builtin taxonomy.

### `addObjectType(string $taxonomy, string $type): bool`

Attaches a taxonomy to a post type.

### `removeObjectType(string $taxonomy, string $type): bool`

Detaches a taxonomy from a post type.

### `registerStatus(string $name, array $args): array`

Registers a post status.

- `@param array<string, mixed> $args`

Internals: `supportsFrom()` (private, line 183), `capabilities()` (private, line 197)


## Runtime

`final class Minn\Runtime\Runtime` · `public/minn/src/Minn/Runtime/Runtime.php`

The WordPress runtime the engine offers plugin code: the procedural
facade under minn/wp-api/ plus the services it delegates to. One per
request; the facade reaches it through these statics.

- const `CONTENT_DONE` = `array (   'apply_block_hooks_to_content_from_post_object' => 8,   'do_blocks' => 9,   'wptexturize' => 10,   'wpautop' => 10,   'shortcode_unautop' => 10,   'prepend_attachment' => 10,   'do_shortcode' => 11,   'wp_filter_content_tags' =>    array (     0 => 12,     1 => '_minn_content_img_tag',   ), )` — The the_content defaults the engine's own rendering has already done:
blocks, texturize, paragraphs, shortcodes, block hooks, and the image
attributes. What it has not (smilies, the capital P, insecure home
addresses) runs with the plugins' own callbacks.

Used by: `Minn\Admin\BootPayload`, `Minn\Auth\Authenticator`, `Minn\Auth\Capabilities`, `Minn\Auth\RegisteredCaps`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\ImageTags`, `Minn\Blocks\RenderState`, `Minn\Cli\Runtime`, `Minn\Content\Blocks`, `Minn\Content\PostSlugs`, `Minn\Content\Reader`, `Minn\Content\Site`, `Minn\Content\Terms`, `Minn\Cron\Cron`, `Minn\Db`, `Minn\Engine`, `Minn\Extension\Extensions`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\Feeds`, `Minn\Front\FrontController`, `Minn\Front\Permalinks`, `Minn\Front\PluginRules`, `Minn\Front\Resolver`, `Minn\Front\ToolbarMenus`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Mail\Mailer`, `Minn\Media\Icons`, `Minn\Media\Images`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\Caller`, `Minn\Rest\CommentObject`, `Minn\Rest\Embed`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MediaObject`, `Minn\Rest\MenusController`, `Minn\Rest\OEmbedController`, `Minn\Rest\PostCollectionParams`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\RegisteredType`, `Minn\Rest\RenderedFields`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermFilters`, `Minn\Rest\TermObject`, `Minn\Rest\Types`, `Minn\Rest\TypesController`, `Minn\Rest\UserCollectionParams`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`, `Minn\Runtime\Abilities`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\AjaxController`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\BlockFilters`, `Minn\Runtime\BlockHooks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\Constants`, `Minn\Runtime\CurrentUser`, `Minn\Runtime\Deferrals`, `Minn\Runtime\FileUpload`, `Minn\Runtime\Interactivity`, `Minn\Runtime\NavMenu`, `Minn\Runtime\PackageDownload`, `Minn\Runtime\Patterns`, `Minn\Runtime\PlaceholderTrace`, `Minn\Runtime\PluginUpdates`, `Minn\Runtime\Plugins`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostSave`, `Minn\Runtime\RegisteredSettings`, `Minn\Runtime\Registry`, `Minn\Runtime\ScriptModules`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermWriter`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\UserEvents`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`

```php
__construct(Minn\Context $context, bool $isAdmin = false)
```
The runtime for one request. Everything about the request itself
comes from the context; the fields below it are the same values,
kept as properties because plugin code reaches for them by name.

- readonly `Minn\Db $db` — The database door this request answers through.
- readonly `Minn\Content\Site $site` — The site's options.
- readonly `?Minn\Http\Request $request` — The request being answered, absent on the command line.
- `Minn\Content\Reader $reader` — Who reads this request; settled again (identify()) once plugins have said who the user is.
- readonly `Minn\Auth\Capabilities $capabilities` — The capability engine.
- readonly `string $engineDir` — The minn/ folder: the engine's own files.
- readonly `string $absPath` — The site root with a trailing slash.
- readonly `string $version` — The WordPress release whose contracts the runtime speaks.
- readonly `Minn\Context $context`
- readonly `bool $isAdmin`

### `identify(Minn\Content\Reader $reader): void`

The request's reader from now on: the user plugin code named through determine_current_user (Runtime\CurrentUser).

### `useSeams(Minn\Extension\SeamRunner $seams): void`

Holds the extension seams this request registered, so nothing static has to.

### `seams(): ?Minn\Extension\SeamRunner`

The extension seams, or null before the front has registered any (REST and the CLI never do).

### `renderState(): Minn\Blocks\RenderState`

The render state for this request, made on first use: one set of counters for everything rendered.

### `useRenderState(Minn\Blocks\RenderState $state): void`

Makes a render state this request's, so a renderer that brought its own is the one the leaves read.

### `blockRenderer(): Minn\Blocks\Renderer`

The block renderer for this request, made on first use over this request's own database door.

### static `boot(self $runtime): self`

Makes this request's runtime the one the facade sees and defines the
facade.

Deliberately does NOT start the facade's registries empty. A process
answering a second request would then have a fresh hook table that no
plugin can fill again: plugin files register their hooks as they are
included, and an include happens once per process. Until a plugin's
registrations can be replayed, a second boot inherits the first's
registries on purpose, which is why a worker runtime is not yet
something the engine claims (see contracts/runtime.md).

### static `current(): self`

The booted runtime; throws when there is none.

### static `booted(): bool`

Whether the runtime is up.

### static `hooks(): Minn\Runtime\Hooks`

The hook registry.

### static `options(): Minn\Runtime\Options`

The options store.

### static `capture(string $action, array $args = array ( )): string`

Runs an action and returns what it printed. Plugin callbacks may open
output buffers of their own during the action (a page post-processor
started in wp_head) or close one they think is theirs (the same plugin
in wp_footer); a sentinel buffer under the capture keeps the output
either way: extra buffers are flushed through their handlers into the
capture, and a capture closed early lands in the sentinel.

### static `contentFilter(string $content): string`

Content the engine rendered itself, through the_content for everything else hooked there.

### static `cache(): Minn\Runtime\ObjectCache`

The object cache.

### static `textDomains(): Minn\I18n\TextDomains`

The text domains loaded for this request.

### static `locales(): Minn\I18n\LocaleStack`

The locales this request switched into.

### static `shortcodes(): Minn\Runtime\Shortcodes`

The shortcode registry.

### static `scriptModules(): Minn\Runtime\ScriptModules`

The script modules registry.

### static `interactivity(): Minn\Runtime\Interactivity`

The interactivity API.

### static `blockTemplates(): Minn\Runtime\BlockTemplates`

The registered block templates.

### static `registry(): Minn\Runtime\Registry`

The post types, taxonomies, and statuses.

### static `postQuery(): Minn\Runtime\PostQuery`

A fresh post query over the runtime's registry.

### `get(string $key, mixed $default = NULL): mixed`

A per-request state value.

### `set(string $key, mixed $value): void`

Sets a per-request state value.

### `isSecure(): bool`

Whether the request is over HTTPS.

### `contentDir(): string`

wp-content under the site root.

### static `loadFacade(string $engineDir): void`

Defines the facade functions once; safe to call again.

### static `loadPluggables(): void`

The pluggable functions, once the plugins have had their chance to
define their own: each is defined only where no plugin did. The plugin
loader calls this before plugins_loaded; a boot that loads no plugins
calls it straight away.

### static `reset(): void`

Fresh per-request state, for suites.

Internals: `loadObjectCacheDropin()` (private, line 350)


## ScriptModules

`final class Minn\Runtime\ScriptModules` · `public/minn/src/Minn/Runtime/ScriptModules.php`

The script modules registry: registrations with typed dependencies, the
queue, and the four things a page prints from it (the import map, module
preloads, the module tags split head/footer, and per-module JSON data).
Behaviour pinned by the script-modules probe fixture.

- const `PRIORITIES` = `array (   0 => 'high',   1 => 'low',   2 => 'auto', )`

Used by: `Minn\Runtime\Runtime`

```php
__construct(Closure $url, Closure $data)
```
- `@param Closure(string, string|false|null): string $url turns a src and version into the printed URL`
- `@param Closure(string, array<string, mixed>): array<string, mixed> $data applies the module data filter`


### `register(string $id, string $src, array $deps, string|false|null $version, array $args): void`

Registers a module by id.

- `@param list<string|array{id: string, import?: string}> $deps`

### `enqueue(string $id, string $src, array $deps, string|false|null $version, array $args): void`

Queues a module, registering it when a source is given.

### `dequeue(string $id): void`

Removes a module from the queue.

### `deregister(string $id): void`

Forgets a module.

### `setFetchpriority(string $id, string $priority): bool`

Sets a module's fetch priority.

### `moveToFooter(string $id): bool`

Moves a module to the footer or the head.

### `moveToHead(string $id): bool`

Prints a module in the head; false when it is not registered.

### `queue(): array`

The module ids queued.

- `@return list<string>`

### `registered(string $id): ?array`

One registered module, or null.

- `@return array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}|null`

### `printImportMap(): string`

The import map script tag.

### `printPreloads(): string`

The modulepreload links.

### `printHead(): string`

The head's module tags.

### `printFooter(): string`

The footer's module tags.

### `printData(): string`

The script-module-data tags.

### `printA11y(): string`

The a11y module's tag, once.

Internals: `placeIn()` (private, line 127), `printTags()` (private, line 226), `marked()` (private, line 250), `complete()` (private, line 262), `dependencies()` (private, line 284), `urlOf()` (private, line 302), `attr()` (private, line 307), `wrong()` (private, line 312)


## ScriptPack

`final class Minn\Runtime\ScriptPack` · `public/minn/src/Minn/Runtime/ScriptPack.php`

The site-supplied script pack: the `wp-*` JavaScript packages the engine
does not reimplement, which a few plugins' front ends need (WooCommerce's
block cart and checkout are the reason it exists).

These packages are GPL, so the engine never ships them: the SITE installs
them into its own `wp-content`, the way it installs a language pack, and
the engine registers the handles only when they are actually there. A
site owner adding GPL files to a site that already runs GPL plugins is
not the engine distributing GPL; nothing under `minn/` changes.

The engine's own MIT packages under `minn/assets/wp` always win: the pack
fills the gaps around them, it does not replace them.

- const `RELATIVE_DIR` = `'minn-packages/wp-scripts'` — Under the site's wp-content, so backups and migrations carry it.
- const `MANIFEST` = `'script-loader-packages.php'`
- const `VENDOR` = `array (   'lodash' =>    array (   ),   'moment' =>    array (   ),   'react' =>    array (   ),   'react-dom' =>    array (     0 => 'react',   ),   'react-jsx-runtime' =>    array (     0 => 'react',   ),   'regenerator-runtime' =>    array (   ),   'wp-polyfill' =>    array (   ),   'wp-polyfill-dom-rect' =>    array (   ),   'wp-polyfill-element-closest' =>    array (   ),   'wp-polyfill-fetch' =>    array (   ),   'wp-polyfill-formdata' =>    array (   ),   'wp-polyfill-inert' =>    array (   ),   'wp-polyfill-node-contains' =>    array (   ),   'wp-polyfill-object-fit' =>    array (   ),   'wp-polyfill-url' =>    array (   ), )` — The vendor handles WordPress registers outside the packages manifest
(captured from the reference). `moment` matters as much as `react`:
one unregistered handle anywhere in a dependency tree drops every
script above it, which is how a missing moment silently cost the
whole WooCommerce cart bundle.

Used by: `Minn\Cli\MinnCommand`

### static `dir(string $contentDir): string`

Where the script pack lives under wp-content.

### static `installed(string $contentDir): bool`

Whether the script pack is on disk.

### static `handles(string $contentDir): array`

Every handle the pack can register: the packages manifest keyed by
`name.js` becomes `wp-name`, plus the three vendor handles. A handle
whose file is missing from the pack is skipped rather than
registered against a 404.

- `@return array<string, array{file: string, deps: list<string>, ver: string}>`

### static `installFromTree(string $contentDir, string $tree): array`

Copies the packages out of a WordPress tree (or an unpacked core
download) into the site's pack directory. Only the built JavaScript
and the manifest are taken, and only from the paths they live at, so
a wrong source folder copies nothing rather than something odd.

- `@return array{files: int, dir: string}`

### static `remove(string $contentDir): bool`

Removes the pack; the engine's own packages keep working without it.

Internals: `stamp()` (private, line 162)


## Shortcodes

`final class Minn\Runtime\Shortcodes` · `public/minn/src/Minn/Runtime/Shortcodes.php`

The shortcode registry plugin code fills with add_shortcode, and the
expansion do_shortcode performs: [tag attrs], [tag attrs/], [tag]…[/tag]
(the first closing tag wins; content is not expanded again), [[tag]] as
the literal, unregistered tags left as written.

Used by: `Minn\Runtime\Runtime`


### `tags(): array`

The registry itself, by reference, so the $shortcode_tags global plugin
code reads and copies is this array and not a snapshot of it.

- `@return array<string, callable>`

### `add(string $tag, callable $callback): void`

Registers a shortcode.

### `remove(string $tag): void`

Forgets a shortcode.

### `removeAll(): void`

Forgets every shortcode.

### `has(string $tag): bool`

Whether a shortcode is registered.

### `all(): array`

Every shortcode with its callback.

- `@return array<string, callable> every registered tag and its handler, to restore after a narrowed run`

### `restore(array $tags): void`

Replaces the registry, after a save-and-restore.

- `@param array<string, callable> $tags`

### `names(): array`

Every shortcode name.

- `@return list<string>`

### `pattern(?array $tags = NULL): ?string`

The regex matching the registered shortcodes, or null for none.

### `apply(string $content): string`

Content with the shortcodes run.

### `strip(string $content): string`

Content with the shortcodes removed.

### static `parse(string $text): array`

Shortcode attribute text as an array.

- `@return array<int|string, string> named attributes; bare words and quoted values keyed by position`


## StoredObjects

`final class Minn\Runtime\StoredObjects` · `public/minn/src/Minn/Runtime/StoredObjects.php`

The classes a stored blob may name and come back as. The serialized
reader never instantiates; the facade registers one factory per value
class it owns (WP_Post, WP_Term, WP_Comment) and a record naming any
other class stays a stdClass of its properties. That is what lets a
transient the reference wrote, holding post objects, read back typed.

- const `WRAPPERS` = `array (   'arrayobject' => 'ArrayObject',   'arrayiterator' => 'ArrayIterator',   'recursivearrayiterator' => 'RecursiveArrayIterator', )` — PHP's array wrappers a stored record may name, by lower-cased class name.

Used by: `Minn\Runtime\Options`


### static `register(string $class, Closure $factory): void`

Registers what a record naming this class becomes.

### static `reviver(): Closure`

The reviver the serialized reader takes: PHP's array wrappers rebuilt, a factory's object, or the properties as they are.

### static `knows(string $class): bool`

Whether a factory is registered for the class.

### static `reset(): void`

Forgets every factory, for suites.

Internals: `arrayWrapper()` (private, line 52)


## SymbolGap

`final readonly class Minn\Runtime\SymbolGap` · `public/minn/src/Minn/Runtime/SymbolGap.php`

The part of the reference's interface the runtime does not answer: names in
data/api-names.json that no facade file defines. It is the whole input the
symbol gate needs, so it can be exported to JSON and carried to a machine
that holds plugin source but no engine, which is how the catalogue-wide
compatibility scan runs.

Used by: `Minn\Runtime\Symbols`

- readonly `array $functions`
- readonly `array $classes`
- readonly `array $known`
- readonly `array $pluggable`

### static `ofLoadedFacade(string $engineDir): self`

Reads the reference's interface and subtracts everything the loaded facade
defines. Recomputed on every call because a plugin may define a name as it
loads; only the file read is cached.

### static `fromFile(string $path): self`

The gap read from its JSON file.

### `lacksFunction(string $name): bool`

Whether the runtime lacks a function.

### `defines(string $name): bool`

Whether the runtime already defines a function of the reference's
interface, so a plugin declaring it again without a guard would fail
to compile. The pluggable functions are not counted: the runtime
defines those after the plugins load, each only where no plugin did.

### `lacksClass(string $name): bool`

Whether the runtime lacks a class.

### `json(): string`

The gap as JSON.


## SymbolTable

`final class Minn\Runtime\SymbolTable` · `public/minn/src/Minn/Runtime/SymbolTable.php`

What a folder's PHP names, collected while its tokens are read: the
functions it calls, the classes it references, and what it declares or
guards itself, so the gate can subtract those before judging it.

Used by: `Minn\Runtime\Symbols`


### `call(string $name): void`

Notes a function called.

### `classRef(string $name): void`

Notes a class referenced.

### `declare(string $function): void`

Notes a function the folder declares, a method included: a call by that name is not a need.

### `declareGlobal(string $function): void`

Notes a function declared in the global scope, which the runtime must not already define.

### `declareClass(string $class): void`

Notes a class the folder declares.

### `guard(string $name): void`

A name an existence check protects: function_exists, class_exists, defined, and the rest.

### `toArray(bool $truncated): array`

The table as the gate reads it.

- `@return array{calls: list<string>, classes: list<string>, declared: array<string, true>, declaredGlobal: array<string, true>, declaredClasses: array<string, true>, guarded: array<string, true>, truncated: bool}`


## Symbols

`final class Minn\Runtime\Symbols` · `public/minn/src/Minn/Runtime/Symbols.php`

A static read of what a plugin's PHP calls: global functions and classes
it uses but does not itself declare, minus the ones it guards with
function_exists() or class_exists(). Checked against what the runtime
provides, this decides whether a plugin loads at all. The read is cached
in the minn_runtime_symbols option keyed by the plugin folder's newest
modification time.

- const `MAX_FILES` = `6000`
- const `SKIP_DIRS` = `array (   0 => 'node_modules',   1 => 'tests',   2 => 'test',   3 => '.git', )`
- const `READER` = `3` — Bumped whenever the token reader changes, so every cached scan is made again.

Used by: `Minn\Runtime\Plugins`

### static `missing(string $dir, Minn\Runtime\Options $options): array`

What a plugin folder needs that the runtime lacks, cached by mtime.

- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}`

### static `redeclaresIn(string $file): array`

The functions one file declares in the global scope, unguarded, that the
loaded runtime already defines: including that file would not compile.

- `@return list<string>`

### static `missingAgainst(string $dir, Minn\Runtime\SymbolGap $gap): array`

The same read against an exported gap instead of the running engine, so a
folder can be judged with no database, no options, and no facade loaded.

- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}`

Internals: `verdict()` (private, line 80), `phpFiles()` (private, line 114), `scan()` (private, line 148), `scanTokens()` (private, line 167), `noteName()` (private, line 242), `significant()` (private, line 277)


## TagEditor

`final class Minn\Runtime\TagEditor` · `public/minn/src/Minn/Runtime/TagEditor.php`

Edits one start tag's attributes in place the way the reference's tag
processor does: a replaced value keeps its position, a new attribute goes
right after the tag name (after any earlier insertion), and a removal takes
only the attribute's own text.

Used by: `Minn\Runtime\Interactivity`

```php
__construct(string $tag, array $attrs)
```
- `@param list<array{name: string, value: ?string, start: int, end: int}> $attrs offsets relative to the tag`


### `get(string $name): ?string`

An attribute's value, or null.

### `has(string $name): bool`

Whether the tag has an attribute.

### `set(string $name, string|bool $value): void`

true sets a bare boolean attribute.

### `remove(string $name): void`

Removes an attribute.

### `addClass(string $class): void`

Adds or removes a class.

### `removeClass(string $class): void`

Removes a class, and the attribute when it was the last one.

### `setStyle(string $property, ?string $value): void`

Sets or removes one inline style property.

### `html(): string`

The tag as edited.

Internals: `classes()` (private, line 99), `setClasses()` (private, line 106), `splice()` (private, line 147)


## TermEvents

`final readonly class Minn\Runtime\TermEvents` · `public/minn/src/Minn/Runtime/TermEvents.php`

What the reference's REST terms controller tells plugins, for the
engine's own: with plugins loaded a term is saved as that controller
saves one (rest_pre_insert_{taxonomy}, then the runtime's wp_insert_term
or wp_update_term, and wp_delete_term), and the REST actions follow.
Without a booted runtime each write is the engine's own.

Used by: `Minn\Rest\TermsController`

### `live(): bool`

Whether plugins are loaded to be told anything.

### `restCreate(string $taxonomy, array $body, Minn\Http\Request $request): int`

A term created over REST with plugins loaded, as the reference's
controller creates one (probe rest-term-save): the request's fields
as the prepared term, through rest_pre_insert_{taxonomy}, into
wp_insert_term; a refusal is its REST error.

- `@param array<string, mixed> $body`

### `restUpdate(int $termId, string $taxonomy, array $body, Minn\Http\Request $request): void`

A term changed over REST with plugins loaded: the fields the request
sends, through rest_pre_insert_{taxonomy}, into wp_update_term (when
any are left); a refusal is its REST error.

- `@param array<string, mixed> $body`

### `delete(int $termId, string $taxonomy, array $data, Minn\Http\Request $request, Closure $quietly): void`

Deletes a term, then tells plugins over REST with the term as it was and the response.

- `@param array<string, mixed> $data the response`
- `@param Closure(): void $quietly the engine's own delete`

### `restSaved(int $termId, string $taxonomy, Minn\Http\Request $request, string $verb): void`

rest_insert_{taxonomy}, then rest_after_insert_{taxonomy}, with the term as it stands and the request.

Internals: `prepared()` (private, line 74), `refusal()` (private, line 94)


## TermFields

`final class Minn\Runtime\TermFields` · `public/minn/src/Minn/Runtime/TermFields.php`

A term's fields in a context, as the reference's sanitize_term_field
treats them (probe term-sanitize): the numeric fields are whole numbers,
never below zero, in every context, and raw stops there. edit runs
edit_term_{field} and edit_{taxonomy}_{field}; db runs pre_term_{field}
and pre_{taxonomy}_{field}, where the saving defaults live, and a slug
also pre_category_nicename; rss runs term_{field}_rss and
{taxonomy}_{field}_rss; any other context runs term_{field} and
{taxonomy}_{field} with the context named. Then a text field is escaped
for where it goes: a form (edit), an attribute, a script (js).

- const `FIELDS` = `array (   0 => 'term_id',   1 => 'name',   2 => 'description',   3 => 'slug',   4 => 'count',   5 => 'parent',   6 => 'term_group',   7 => 'term_taxonomy_id',   8 => 'object_id', )` — The fields sanitize_term runs, in its order.
- const `NUMBERS` = `array (   0 => 'parent',   1 => 'term_id',   2 => 'count',   3 => 'term_group',   4 => 'term_taxonomy_id',   5 => 'object_id', )`
- const `ESCAPES` = `array (   'edit' => 'esc_html',   'attribute' => 'esc_attr',   'js' => 'esc_js', )`

### static `field(string $field, mixed $value, int $termId, string|false $taxonomy, string $context): mixed`

One field's value in a context; a lookup with no taxonomy names false, and its filters get false.

### static `term(object|array $term, string $taxonomy, string $context): object|array`

A term (object or array) with each of its fields in the context, and
its filter set to the context; the term's own id goes to the filters.

Internals: `filtered()` (private, line 66), `saving()` (private, line 77)


## TermOrder

`final class Minn\Runtime\TermOrder` · `public/minn/src/Minn/Runtime/TermOrder.php`

A term query's ORDER BY as the reference writes it (probe
wp-term-query-sql): term and taxonomy columns, the relationship's order,
the order include or slug lists give, none, or the name; then
get_terms_orderby, and after it a meta key or meta_value[_num] when the
query has meta clauses.

- const `TERM_COLUMNS` = `array (   0 => 'term_id',   1 => 'name',   2 => 'slug',   3 => 'term_group', )`
- const `TAXONOMY_COLUMNS` = `array (   0 => 'count',   1 => 'parent',   2 => 'taxonomy',   3 => 'term_taxonomy_id',   4 => 'description', )`

Used by: `Minn\Runtime\TermQueryRunner`

### static `direction(mixed $order): string`

ASC when asked for, DESC for anything else.

### static `clause(WP_Term_Query $query, string $raw): string`

The ORDER BY body for an orderby value, filtered as the reference filters it.

Internals: `meta()` (private, line 54)


## TermQuery

`final readonly class Minn\Runtime\TermQuery` · `public/minn/src/Minn/Runtime/TermQuery.php`

Term reads in the shapes plugin code asks for: get_terms() arguments to
rows, the tree filters (child_of, exclude_tree), the fields shapes, and
the single-term lookups. Behaviour pinned by contracts/fixtures/api/content.json.

- const `DEFAULTS` = `array (   'taxonomy' => NULL,   'object_ids' => NULL,   'orderby' => 'name',   'order' => 'ASC',   'hide_empty' => true,   'include' =>    array (   ),   'exclude' =>    array (   ),   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => '',   'fields' => 'all',   'count' => false,   'name' => '',   'slug' => '',   'term_taxonomy_id' => '',   'hierarchical' => true,   'search' => '',   'name__like' => '',   'description__like' => '',   'pad_counts' => false,   'get' => '',   'child_of' => 0,   'parent' => '',   'childless' => false,   'cache_domain' => 'core',   'update_term_meta_cache' => true,   'meta_query' => '',   'meta_key' => '',   'meta_value' => '', )`
- const `COLUMNS` = `'t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count'`

Used by: `Minn\Runtime\TermWriter`

```php
__construct(Minn\Db $db, Closure $slug)
```
- `@param Closure(string): string $slug the slug sanitiser, so the reference's filters apply`


### `row(int $termId, ?string $taxonomy): ?array`

A term row joined with its taxonomy row, by id (and taxonomy when known). @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### `find(string $field, mixed $value, ?string $taxonomy): ?array`

The term id and taxonomy a field value names; null when nothing matches
or the field is not one a term can be found by.

- `@return array{term_id: int, taxonomy: string}|null`

### `exists(string|int $term, ?string $taxonomy, ?int $parent): ?array`

Whether a term (by id, or by slug or name) exists, optionally under a
taxonomy and parent; the ids when it does.

- `@return array{term_id: int, term_taxonomy_id: int}|null`

### `lookup(string $field, string $value, ?string $taxonomy, ?int $parent): ?array`

The term whose slug or name (as stored) is exactly the value,
optionally in a taxonomy and under a parent (0 for the top level).

- `@return array{term_id: int, term_taxonomy_id: int}|null`

### `hasChildren(int $parent, ?array $taxonomies): bool`

Whether any term in the taxonomies (any taxonomy for null) sits
under the parent: the reference answers a lookup under a childless
parent with nothing before it looks.

- `@param list<string>|null $taxonomies`

### `slugTaken(string $slug, int $exceptTermId): bool`

Whether a term other than the one named holds the slug, in any taxonomy.

### `normalise(array $args): array`

get_terms() arguments normalised: the "get all" shortcut, integer lists, sanitised slugs.

### `rows(array $args, ?array $taxonomies): array`

The term rows the arguments match, ordered and paged, with the tree
filters applied.

- `@param list<string>|null $taxonomies`
- `@return list<array<string, mixed>>`

### `hierarchy(string $taxonomy): array`

Every parent in a taxonomy that has children, mapped to its children's
ids. A taxonomy nobody nested reads as an empty map.

- `@return array<int, list<int>>`

### `children(int $termId, string $taxonomy): array`

Every term id under a term, however deep. @return list<int>

- `@return list<int>`

### `objectsIn(array $termIds, array $taxonomies, string $order): array`

The object ids attached to any of the terms in any of the taxonomies. @param list<int> $termIds @param list<string> $taxonomies @return list<int>

- `@param list<int> $termIds @param list<string> $taxonomies @return list<int>`

Internals: `idList()` (private, line 213), `descendants()` (private, line 220), `where()` (private, line 239), `metaClauses()` (private, line 313), `ids()` (private, line 371), `like()` (private, line 380)


## TermQueryRunner

`final class Minn\Runtime\TermQueryRunner` · `public/minn/src/Minn/Runtime/TermQueryRunner.php`

WP_Term_Query::get_terms as the reference runs it (probe
wp-term-query-sql): the variables parsed and handed to pre_get_terms,
get_terms_args, the WHERE pieces (taxonomies, inclusions, exclusions with
excluded trees and childless terms through list_terms_exclusions, names,
slugs, term taxonomy ids, likes, objects, parent, counts, search, meta),
the order through get_terms_orderby, the fields through get_terms_fields,
all of it through terms_clauses, terms_pre_query, and the results shaped
by TermQueryTree (children, padded counts, the empty hidden, the page).


### `run(WP_Term_Query $query, object $wpdb): mixed`

Runs a term query object's variables: its terms in the shape the
fields ask for, a count, or what a plugin answered first.

Internals: `lists()` (private, line 75), `hierarchical()` (private, line 87), `settle()` (private, line 101), `inHierarchy()` (private, line 118), `order()` (private, line 129), `clauses()` (private, line 140), `exclusions()` (private, line 167), `names()` (private, line 188), `likes()` (private, line 207), `select()` (private, line 224), `limits()` (private, line 253), `meta()` (private, line 263)


## TermQueryTree

`final class Minn\Runtime\TermQueryTree` · `public/minn/src/Minn/Runtime/TermQueryTree.php`

What a term query does with its rows, as the reference does it (probe
wp-term-query-sql): each row a term through get_term (with the object id
a relationship query carries); only child_of's descendants; counts padded
with the children's posts; an empty term kept only while one of its
children has posts (the rest dropped where they stood, keys and all); a
tree's page cut once the tree is walked; then the shape the fields ask
for.

Used by: `Minn\Runtime\TermQueryRunner`

```php
__construct(array $taxonomies)
```
- `@param list<string> $taxonomies`


### static `populate(array $rows): array`

The terms the rows name, keyed as the rows were.

- `@param array<array-key, object> $rows`
- `@return array<array-key, \WP_Term>`

### `settle(array $terms, array $args): array`

The terms after the tree has had its say: descendants only, padded
counts, empty branches dropped, the page cut.

- `@param array<array-key, \WP_Term> $terms`
- `@param array<string, mixed> $args`
- `@return array<array-key, \WP_Term>`

### static `pad(array $terms, string $taxonomy): void`

Each term's count raised to the published posts in it or under it, as
_pad_term_counts does for a hierarchical taxonomy.

- `@param array<array-key, \WP_Term> $terms`

### static `format(array $terms, string $fields): array`

The terms in the shape the fields ask for.

- `@param array<array-key, \WP_Term> $terms`
- `@return array<array-key, mixed>`

Internals: `hasPosts()` (private, line 123)


## TermSave

`final class Minn\Runtime\TermSave` · `public/minn/src/Minn/Runtime/TermSave.php`

wp_insert_term and wp_update_term in the reference's order (probe
term-insert-filters, contracts/runtime.md "Terms plugins can change").
An insert asks pre_insert_term, refuses an empty name or a missing
parent, runs every field through its db filters (sanitize_term), refuses
a name the parent (or, for tags, the taxonomy) already has, makes the
slug unique, hands the row to wp_insert_term_data, writes it (a slug left
empty becomes the term's id), puts it in the taxonomy, lets a plugin
name a duplicate to keep instead, and tells plugins. An update merges
the stored term (slashed, so a backslash in it survives) under the
changes, runs the same filters, asks wp_update_term_parent (where a
parent that would put the term under itself becomes 0), and refuses a
slug a sibling holds.

- const `DEFAULTS` = `array (   'alias_of' => '',   'description' => '',   'parent' => 0,   'slug' => '', )`

### static `insert(mixed $term, string $taxonomy, array $args): WP_Error|array`

A new term's ids, or why not.

- `@param array<string, mixed> $args`
- `@return array{term_id: int, term_taxonomy_id: int}|\WP_Error`

### static `update(int $termId, string $taxonomy, array $changes): WP_Error|array`

A changed term's ids, or why not.

- `@param array<string, mixed> $changes`
- `@return array{term_id: int, term_taxonomy_id: int}|\WP_Error`

Internals: `write()` (private, line 127), `nameTaken()` (private, line 160), `updatedSlug()` (private, line 184), `slugFromId()` (private, line 203), `row()` (private, line 216)


## TermWriter

`final readonly class Minn\Runtime\TermWriter` · `public/minn/src/Minn/Runtime/TermWriter.php`

The decisions behind wp_delete_term and the object-term relationships:
which relationships to add and remove (saves are Runtime\TermSave). The rows themselves come
from Content\Terms; the lifecycle actions fire from here in the
reference's order. Behaviour pinned by contracts/fixtures/api/content.json.

```php
__construct(Minn\Db $db, Minn\Content\Terms $terms, Minn\Runtime\TermQuery $query, Minn\Content\PostWriter $posts)
```


### `delete(array $row, string $taxonomy, bool $hierarchical, int $default): array`

Deletes a term; objects left without a category fall back to the
default one. Returns the object ids the term was attached to.

- `@param array<string, mixed> $row`
- `@return list<int>`

### `relate(int $objectId, array $keep, array $old, string $taxonomy, ?Closure $count = NULL): void`

Makes an object's relationships in a taxonomy exactly $keep (or $old
plus $keep when appending), in the reference's order: each new
relationship between add_term_relationship and
added_term_relationship, the counts of those terms, then the ones
that went, between delete_term_relationships and
deleted_term_relationships, and their counts. $count recounts a list
of term_taxonomy ids and tells plugins (wp_update_term_count); without
it the taxonomy is recounted quietly.

- `@param list<int> $keep term_taxonomy ids`
- `@param list<int> $old the object's current term_taxonomy ids in the taxonomy`
- `@param (Closure(list<int>): void)|null $count`

### `unrelate(int $objectId, array $ttIds, string $taxonomy, ?Closure $count = NULL): bool`

Removes the given relationships between delete_term_relationships and
deleted_term_relationships, then recounts those terms; true when any
row went.

- `@param list<int> $ttIds`
- `@param (Closure(list<int>): void)|null $count`

### `publishedCount(int $ttId): int`

How many published objects a term holds, as the stored count keeps it.

### `storeCount(int $ttId, int $count): void`

Stores a term's count.

### `termIdsOf(array $ttIds): array`

The term ids behind term_taxonomy ids, as stored (strings), in the order given. @param list<int> $ttIds @return list<string>

- `@param list<int> $ttIds @return list<string>`

Internals: `ttIdOf()` (private, line 133)


## ThemeSupports

`final class Minn\Runtime\ThemeSupports` · `public/minn/src/Minn/Runtime/ThemeSupports.php`

What a theme supports, as add_theme_support keeps it (probe rest-themes):
a bare feature is true, anything else its arguments. html5 lists add up
(repeats and all), post thumbnails stay on for every type once on, post
formats keep only real formats, the custom logo fills in its defaults at
once (flexible both ways when asked for bare), and the custom header and
background get theirs as WordPress finishes loading, a header with no
width or height made flexible that way. title-tag declared after loading
is refused. A block theme supports thumbnails, responsive embeds, editor
styles, HTML5 markup and feed links before its own setup runs.

The features themselves (data/theme-features.json, and any a theme
registers) say how each is shown in REST: its schema, or the default
when the theme does not support it.

- const `KEY` = `'theme_supports'`
- const `FEATURES` = `'theme_features'`
- const `LOGO` = `array (   'width' => NULL,   'height' => NULL,   'flex-width' => false,   'flex-height' => false,   'header-text' => '',   'unlink-homepage-logo' => false, )`
- const `HEADER` = `array (   'default-image' => '',   'random-default' => false,   'width' => 0,   'height' => 0,   'flex-height' => false,   'flex-width' => false,   'default-text-color' => '',   'header-text' => true,   'uploads' => true,   'wp-head-callback' => '',   'admin-head-callback' => '',   'admin-preview-callback' => '',   'video' => false,   'video-active-callback' => 'is_front_page', )`
- const `BACKGROUND` = `array (   'default-image' => '',   'default-preset' => 'default',   'default-position-x' => 'left',   'default-position-y' => 'top',   'default-size' => 'auto',   'default-repeat' => 'repeat',   'default-attachment' => 'scroll',   'default-color' => '',   'wp-head-callback' => '_custom_background_cb',   'admin-head-callback' => '',   'admin-preview-callback' => '', )`
- const `FORMATS` = `array (   0 => 'aside',   1 => 'chat',   2 => 'gallery',   3 => 'link',   4 => 'image',   5 => 'quote',   6 => 'status',   7 => 'video',   8 => 'audio', )`
- const `TYPES` = `array (   0 => 'boolean',   1 => 'integer',   2 => 'number',   3 => 'string',   4 => 'array',   5 => 'object', )`

Used by: `Minn\Rest\InstalledThemesController`

### static `all(): array`

Every feature the theme has declared, as stored. @return array<string, mixed>

- `@return array<string, mixed>`

### static `add(string $feature, array $args): bool`

add_theme_support: false when refused. @param list<mixed> $args

- `@param list<mixed> $args`

### static `remove(string $feature): bool`

remove_theme_support: false when the theme did not support it.

### static `blockThemeDefaults(): void`

_add_default_theme_supports: what every block theme supports before its own setup.

### static `editorDefaults(string $which): void`

wp_enable_block_templates and wp_setup_widgets_block_editor: a block theme's templates, and the block widget editor for any theme.

### static `justInTime(): void`

_custom_header_background_just_in_time: the header's and background's defaults filled in once loaded.

### static `register(string $feature, array $args): WP_Error|true`

register_theme_feature: true, or why the feature cannot be registered.

### static `features(): array`

Core's features, then those registered since. @return array<string, array<string, mixed>>

- `@return array<string, array<string, mixed>>`

### static `forRest(WP_REST_Request $request): array`

The active theme's supports as wp/v2/themes shows them: each feature shown in REST, shaped by its schema. @return array<string, mixed>

- `@return array<string, mixed>`


## TreeWalk

`final class Minn\Runtime\TreeWalk` · `public/minn/src/Minn/Runtime/TreeWalk.php`

The Walker contract's traversal: elements keyed by the walker's
db_fields parent/id pair, displayed depth-first through the walker's
four element methods. The facade Walker delegates here; subclasses
override the element methods (or display_element itself) and the
dispatch stays virtual.

### static `walk(object $walker, array $elements, int $maxDepth, array $args): string`

Walks a tree with a Walker the way the reference does.

- `@param list<object> $elements`

### static `element(object $walker, mixed $element, array $children, int $maxDepth, int $depth, array $args, string $output): void`

Walks one element and its children.

- `@param array<int|string, mixed> $children`


## UpdateCounts

`final class Minn\Runtime\UpdateCounts` · `public/minn/src/Minn/Runtime/UpdateCounts.php`

The updates waiting, as wp_get_update_data counts them for the user
(probe admin-bar): plugins and themes from their update transients,
WordPress itself unless it is current, translations; each only for a
user who may update it. WordPress counts its own update only where
wp-admin's update functions are loaded; the engine always has them, so
it counts it everywhere.

### static `forCurrentUser(): array`

The counts and their title (before the wp_get_update_data filter),
and the titles one by one.

- `@return array{0: array{counts: array<string, int>, title: string}, 1: array<string, string>}`


## UserEvents

`final readonly class Minn\Runtime\UserEvents` · `public/minn/src/Minn/Runtime/UserEvents.php`

What the reference's REST users controller tells plugins, for the
engine's own: with plugins loaded an account is written through the
runtime's wp_insert_user, wp_update_user and wp_delete_user
(wp_set_password, each profile meta, the role, clean_user_cache,
user_register and the count, profile_update, delete_user and
deleted_user), and the REST actions follow. Without a booted runtime each
write is the engine's own, given as a closure.

Used by: `Minn\Rest\UsersController`

### `live(): bool`

Whether plugins are loaded to be told anything.

### `create(array $userdata, string $role, Minn\Http\Request $request, Closure $quietly): int`

Creates an account the way the REST controller does: wp_insert_user
with no role, rest_insert_user, then the role added, then
rest_after_insert_user. Returns the new id.

- `@param array<string, mixed> $userdata wp_insert_user's fields`
- `@param Closure(): int $quietly the engine's own write`

### `update(int $id, array $userdata, ?string $role, Minn\Http\Request $request, Closure $quietly): void`

Updates an account: wp_update_user with the changed fields, the role
when one is given, then the two REST actions.

- `@param array<string, mixed> $userdata the fields the request changes`
- `@param Closure(): void $quietly the engine's own write`

### `delete(int $id, ?int $reassign, array $data, Minn\Http\Request $request, Closure $quietly): void`

Deletes an account, its posts going to $reassign (or with it when
none), then rest_delete_user with the account as it was.

- `@param array<string, mixed> $data the response`
- `@param Closure(): void $quietly the engine's own delete`

Internals: `prepared()` (private, line 115)


## UserInsert

`final readonly class Minn\Runtime\UserInsert` · `public/minn/src/Minn/Runtime/UserInsert.php`

The decisions behind wp_insert_user: what a new account needs, which email
and login collide, the resolved profile fields, and which columns an
update changes. The sanitisers and lookups arrive as closures so the
reference's filters apply. Behaviour pinned by contracts/fixtures/api/functions.json.

- const `META_KEYS` = `array (   0 => 'nickname',   1 => 'first_name',   2 => 'last_name',   3 => 'description',   4 => 'rich_editing',   5 => 'syntax_highlighting',   6 => 'comment_shortcuts',   7 => 'admin_color',   8 => 'use_ssl',   9 => 'show_admin_bar_front',   10 => 'locale', )`

```php
__construct(Closure $sanitizeLogin, Closure $sanitizeSlug, Closure $isEmail, Closure $loginTaken, Closure $emailOwner, Closure $roleExists)
```
- `@param Closure(string): string $sanitizeLogin`
- `@param Closure(string): string $sanitizeSlug`
- `@param Closure(string): bool $isEmail`
- `@param Closure(string): bool $loginTaken`
- `@param Closure(string): int $emailOwner 0 when nobody has it`
- `@param Closure(string): bool $roleExists`


### `resolve(array $userdata, ?array $existing): Minn\Runtime\Refusal|array`

The validated, resolved fields, or the refusal the reference gives.

- `@param array<string, mixed> $userdata`
- `@param array<string, mixed>|null $existing the current row on an update`
- `@return array{login: string, email: string, nicename: ?string, display_name: string, role: ?string}|Refusal`

### static `account(array $userdata, array $resolved, string $defaultRole): array`

The fields a new account is created from. @param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved @return array<string, mixed>

- `@param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved @return array<string, mixed>`

### static `changes(array $userdata, array $existing, array $resolved, Closure $url, Closure $hash): array`

The columns an update actually changes.

- `@param array<string, mixed> $existing`
- `@param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved`
- `@param Closure(string): string $url the URL sanitiser`
- `@param Closure(string): string $hash the password hasher`
- `@return array<string, string>`


## UserOrder

`final class Minn\Runtime\UserOrder` · `public/minn/src/Minn/Runtime/UserOrder.php`

A user query's ORDER BY keys as the reference writes them (probe
wp-user-query-sql): the user columns by their short or full names, the
display name, a post count (its subquery joined in), the id, the meta
key or meta value, the order include or a name list gives, or a named
meta clause; '' for a key it does not know.

- const `SHORT` = `array (   0 => 'login',   1 => 'nicename',   2 => 'email',   3 => 'url',   4 => 'registered', )`
- const `FULL` = `array (   0 => 'user_login',   1 => 'user_nicename',   2 => 'user_email',   3 => 'user_url',   4 => 'user_registered', )`

Used by: `Minn\Runtime\UserQueryRunner`

### static `direction(mixed $order): string`

ASC when asked for, DESC for anything else.

### static `clause(WP_User_Query $query, string $orderby, object $wpdb): string`

The column or expression one orderby key stands for.

Internals: `postCount()` (private, line 50)


## UserQueryRoles

`final class Minn\Runtime\UserQueryRoles` · `public/minn/src/Minn/Runtime/UserQueryRoles.php`

A user query's roles and capabilities as the meta clauses the reference
writes for them (probe wp-user-query-sql): a capability matches its own
name or any role granting it, capability__in and __not_in widen the role
lists, and each role is a LIKE on the site's capabilities meta (NOT LIKE
to leave it out).

Used by: `Minn\Runtime\UserQueryRunner`

```php
__construct(string $capabilitiesKey)
```


### `clauses(array $qv, array $queries, array $roles): array`

The meta query's clauses once the roles and capabilities join them.

- `@param array<string, mixed> $qv`
- `@param array<array-key, mixed> $queries the meta query's own clauses`
- `@param array<string, array{capabilities: array<string, bool>}> $roles the site's roles`
- `@return array<array-key, mixed>`

Internals: `lists()` (private, line 67), `firstOf()` (private, line 89), `like()` (private, line 100)


## UserQueryRunner

`final class Minn\Runtime\UserQueryRunner` · `public/minn/src/Minn/Runtime/UserQueryRunner.php`

WP_User_Query as the reference runs it (probe wp-user-query-sql): the
variables filled and handed to pre_get_users; the fields (a column list,
ids, the count), published authors, nicenames and logins, the meta query
with the roles and capabilities joined in, the order (post counts joined
in, include and list orders kept), the limit, a search over the columns
its shape suggests (through user_search_columns), ids in or out, the date
query, then pre_user_query; the query (users_pre_query may answer it),
found_users_query, and the results shaped as the fields ask.

- const `COLUMNS` = `array (   0 => 'id',   1 => 'user_login',   2 => 'user_pass',   3 => 'user_nicename',   4 => 'user_email',   5 => 'user_url',   6 => 'user_registered',   7 => 'user_activation_key',   8 => 'user_status',   9 => 'display_name', )`
- const `SEARCHABLE` = `array (   0 => 'ID',   1 => 'user_login',   2 => 'user_email',   3 => 'user_url',   4 => 'user_nicename',   5 => 'display_name', )`
- const `EMAIL` = `'user_email'`
- const `URL` = `'user_url'`

```php
__construct(object $wpdb)
```


### `prepare(WP_User_Query $query): void`

Builds the query's pieces from its variables, as prepare_query does.

### `query(WP_User_Query $query): void`

Runs the query: users_pre_query may answer it; the count follows; the results take the fields' shape.

Internals: `fields()` (private, line 65), `published()` (private, line 80), `names()` (private, line 91), `meta()` (private, line 108), `order()` (private, line 141), `search()` (private, line 167)


## UserSave

`final class Minn\Runtime\UserSave` · `public/minn/src/Minn/Runtime/UserSave.php`

An account's fields through the filters the reference's wp_insert_user
runs, in its order (probe user-insert-filters, contracts/runtime.md
"Accounts plugins can change"): the login (pre_user_login), refused when
illegal_user_logins names it; the nicename, made from the login for a
new account (pre_user_nicename); the email and the URL; the nickname
(the login when none), first and last names; the display name, made as
the reference makes it when none is given; the description. Each
pre_user_* filter carries the reference's sanitisers. Then
wp_pre_insert_user_data over the row and insert_user_meta over the meta.

### static `fields(array $userdata, ?array $existing): Minn\Runtime\Refusal|array`

The fields after their filters, or the refusal for an illegal login.

- `@param array<string, mixed> $userdata`
- `@param array<string, mixed>|null $existing the stored account on an update`
- `@return array<string, mixed>|Refusal`

### static `data(array $row, int $userId, array $userdata): array`

wp_pre_insert_user_data over the row an insert or update writes: the
row, whether it updates, the account's id (null for a new one), what
the caller gave.

- `@param array<string, mixed> $row`
- `@param array<string, mixed> $userdata`
- `@return array<string, mixed>`

### static `meta(array $meta, int $userId, array $userdata): array`

insert_user_meta over the profile meta, then insert_custom_user_meta
over the meta_input the caller gave beyond it; the two merged. An
update is one whose userdata names an ID.

- `@param array<string, mixed> $meta`
- `@param array<string, mixed> $userdata`
- `@return array<string, mixed>`


## WidgetAreas

`final class Minn\Runtime\WidgetAreas` · `public/minn/src/Minn/Runtime/WidgetAreas.php`

The widgets helper functions as the reference answers them (probe
widget-helpers): a widget id split into its base and number, a sidebar
by id (the inactive widgets are a sidebar too, with a name and nothing
else), finding and moving a widget between sidebars, one widget rendered
in its sidebar as dynamic_sidebar renders each (an unregistered widget
renders nothing), and the sidebars with every registered one present.

- const `INACTIVE` = `'wp_inactive_widgets'`

Used by: `Minn\Rest\SidebarsController`, `Minn\Rest\WidgetObject`, `Minn\Rest\WidgetsController`

### static `parse(string $id): array`

wp_parse_widget_id: ['id_base' => ..., 'number' => n], or the id alone as its base. @return array{id_base: string, number?: int}

- `@return array{id_base: string, number?: int}`

### static `sidebar(string $id): ?array`

wp_get_sidebar: a registered sidebar, the inactive widgets, or null. @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `find(string $widgetId): ?string`

wp_find_widgets_sidebar: the sidebar holding a widget, or null.

### static `assign(string $widgetId, string $sidebarId): void`

wp_assign_widget_to_sidebar: out of every sidebar, then onto the end of one ('' leaves it in none).

### static `render(string $widgetId, string $sidebarId): string`

wp_render_widget: a registered widget as its sidebar wraps it, through dynamic_sidebar_params; '' otherwise.

### static `control(string $widgetId): ?string`

wp_render_widget_control: a registered widget's settings form, or null.

### static `retrieve(): array`

retrieve_widgets: the sidebars, every registered one present (empty when it holds none). @return array<string, list<string>>

- `@return array<string, list<string>>`

