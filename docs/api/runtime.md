# `Minn\Runtime`

the WordPress runtime plugins load against

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Abilities`](#abilities) | final class | 226 | The abilities registry behind WP_Abilities_Registry, |
| [`AbilityRun`](#abilityrun) | final class | 28 | The checks of an ability's run, as the reference makes them (probes |
| [`AccountFlows`](#accountflows) | final class | 149 | The account functions a sign-in page and plugins call, with the |
| [`AdminActionController`](#adminactioncontroller) | final readonly class | 138 | The wp-admin pages a plugin answers itself. The admin is Minn Admin, so |
| [`AjaxController`](#ajaxcontroller) | final readonly class | 86 | admin-ajax.php, the endpoint plugins post their front-end work to: a form |
| [`AllowedOptions`](#allowedoptions) | final class | 26 | The settings-page allowlist plugins extend: option group => the option |
| [`ApplicationPasswordEvents`](#applicationpasswordevents) | final class | 46 | Application password changes made over REST, as the reference makes them |
| [`ApplicationPasswordRecords`](#applicationpasswordrecords) | final class | 103 | A user's application passwords as WordPress's API keeps them |
| [`ApplicationPasswordSignIn`](#applicationpasswordsignin) | final class | 85 | wp_authenticate_application_password as the reference answers it (probe |
| [`ArchiveLinks`](#archivelinks) | final class | 41 | A post type's archive address and its feed, as the reference gives them |
| [`AssetEdits`](#assetedits) | final class | 42 | What a plugin did to $wp_scripts->registered or $wp_styles->registered |
| [`AssetQueue`](#assetqueue) | final class | 201 | The scripts and styles a request queues, as WordPress's asset functions |
| [`AssetView`](#assetview) | final class | 101 | A registry's view in the shape plugins read and edit it: the public |
| [`Assets`](#assets) | final class | 453 | The registry behind wp_register_/wp_enqueue_ for scripts and styles: |
| [`AttachmentImages`](#attachmentimages) | final class | 475 | An attachment's image as WordPress's media functions make it: the |
| [`AttachmentModel`](#attachmentmodel) | final class | 96 | An attachment as the media modal's scripts read it |
| [`Attachments`](#attachments) | final class | 266 | Attachments as WordPress's media functions keep them: the uploads folder |
| [`Autosaves`](#autosaves) | final class | 71 | An autosave as the reference's REST autosave makes it (probes |
| [`AvMetadata`](#avmetadata) | final class | 252 | An audio or video file's metadata as WordPress's media readers keep it |
| [`Avatar`](#avatar) | final class | 62 | Avatars the way get_avatar_data and get_avatar decide them: the argument |
| [`BlockFilters`](#blockfilters) | final class | 188 | The block-level filters plugin code hooks (pre_render_block, |
| [`BlockHooks`](#blockhooks) | final class | 219 | The Block Hooks API on the engine's own front end: a plugin asks for its |
| [`BlockMetadata`](#blockmetadata) | final class | 138 | block.json to the settings a block type registers with: the property |
| [`BlockQueries`](#blockqueries) | final class | 62 | The query arguments the query and comments blocks run, as WordPress's |
| [`BlockTemplates`](#blocktemplates) | final class | 67 | Block templates plugins register at runtime, by their namespaced name |
| [`BlockWidget`](#blockwidget) | final class | 30 | A block widget's legacy class name. Every widget the block editor saves |
| [`Bookmarks`](#bookmarks) | final class | 37 | The site's links as the link functions find them (get_bookmarks): the |
| [`BrowserGlobals`](#browserglobals) | final class | 45 | The browser and web server a request names, as the reference's globals |
| [`CommentChecks`](#commentchecks) | final class | 297 | What WordPress checks of a comment before and after it is stored: the |
| [`CommentCloser`](#commentcloser) | final readonly class | 31 | The Discussion setting that closes comments on old posts. Observed on the |
| [`CommentEvents`](#commentevents) | final readonly class | 259 | What the reference's REST comments controller tells plugins, for the |
| [`CommentFeedQuery`](#commentfeedquery) | final class | 50 | The comments a comments feed's main query carries, as the reference |
| [`CommentForm`](#commentform) | final class | 107 | The comment form's submission with plugins loaded |
| [`CommentPages`](#commentpages) | final class | 115 | Which page of a post's comments a comment falls on, and the link that |
| [`CommentQuery`](#commentquery) | final readonly class | 28 | The approval breakdown wp_count_comments reports (comment lists run through WP_Comment_Query and Minn\Runtime\CommentQueryRunner). |
| [`CommentQueryRunner`](#commentqueryrunner) | final class | 131 | WP_Comment_Query as the reference runs it (probe wp-comment-query-sql): |
| [`CommentQueryWhere`](#commentquerywhere) | final class | 196 | WP_Comment_Query's WHERE pieces and the posts join, in the reference's |
| [`CommentReads`](#commentreads) | final class | 49 | What WordPress's comment functions tell about comments: the comments a |
| [`CommentTags`](#commenttags) | final class | 60 | What the comment template tags tell about a comment (the one being read |
| [`CommentThreads`](#commentthreads) | final class | 68 | A threaded or flat comment query's descendants as the reference fills |
| [`CommentWrites`](#commentwrites) | final class | 364 | Comments written as WordPress's comment functions write them: a comment |
| [`Connectors`](#connectors) | final class | 221 | The connectors registry: the external services a site talks to (AI |
| [`Constants`](#constants) | final class | 119 | The constants plugin code expects: the fixed set from data/constants.json |
| [`CronEvents`](#cronevents) | final class | 297 | Scheduled events as WordPress keeps them in the cron option: the list |
| [`CronTable`](#crontable) | final class | 133 | The cron option's shape, operated on as data: timestamp => hook => key => |
| [`CurrentUser`](#currentuser) | final class | 19 | Who the request is, as the reference settles it before init: the user |
| [`DbDelta`](#dbdelta) | final readonly class | 127 | dbDelta as the reference does it: a CREATE TABLE statement creates the |
| [`DebugMode`](#debugmode) | final class | 104 | PHP's error settings from the debug constants, as the reference's boot |
| [`Deferrals`](#deferrals) | final class | 66 | The switches an importer flips for the length of a request (probe |
| [`DeprecatedUserMeta`](#deprecatedusermeta) | final class | 79 | update_usermeta() and delete_usermeta(), deprecated since 3.0.0 and kept |
| [`DieInput`](#dieinput) | final class | 34 | wp_die's message, title and arguments made whole, as the die handlers |
| [`DocumentTitle`](#documenttitle) | final class | 106 | The page's title as WordPress's title functions make it from the main |
| [`EarlyFilters`](#earlyfilters) | final class | 20 | Filters that run before the runtime exists, over the hooks added that |
| [`EarlyRequest`](#earlyrequest) | final class | 50 | The request as WordPress's earliest functions see it, before the runtime |
| [`EditLinks`](#editlinks) | final class | 99 | The admin addresses WordPress's link functions give for editing: a |
| [`FileHeaders`](#fileheaders) | final class | 30 | The headers at the top of a theme, plugin or translation file, as |
| [`FileNames`](#filenames) | final class | 119 | Files as WordPress's file functions name and remove them: a name made |
| [`FileTypeCheck`](#filetypecheck) | final class | 77 | A file's type from its content as much as its name, as the reference's |
| [`FileUpload`](#fileupload) | final class | 106 | A file a plugin hands to wp_handle_upload or wp_handle_sideload, taken in |
| [`Handoff`](#handoff) | final class | 554 | What the engine asks of the layer it runs under, where a plugin may have |
| [`Heartbeat`](#heartbeat) | final class | 30 | The heartbeat a signed-in page beats through admin-ajax.php, answered as |
| [`Hooks`](#hooks) | final class | 426 | The hook registry plugin code registers into and the engine fires. |
| [`Interactivity`](#interactivity) | final class | 534 | Server-side directive processing for the Interactivity API: the state and |
| [`KsesRules`](#ksesrules) | final class | 114 | The HTML WordPress's kses functions let through: the elements and |
| [`LegacyPack`](#legacypack) | final class | 310 | WordPress's own browser files, for the plugins whose screens are built on |
| [`MainQuery`](#mainquery) | final class | 23 | The query the request answers ($wp_query), as WordPress keeps it: the |
| [`MenuEvents`](#menuevents) | final readonly class | 257 | Menus and their items saved as the reference saves them, telling |
| [`MenuItemMarks`](#menuitemmarks) | final class | 203 | The classes and current flags the reference gives a menu's items for the |
| [`Meta`](#meta) | final readonly class | 244 | The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need. |
| [`MetaData`](#metadata) | final class | 401 | Metadata as WordPress's meta functions read and write it, for posts, |
| [`MetaKeys`](#metakeys) | final class | 187 | The meta keys code registers, kept where the reference keeps them |
| [`MetaTypes`](#metatypes) | final class | 21 | Meta types a plugin brought, by the table it named on $wpdb as |
| [`NavMenu`](#navmenu) | final class | 133 | wp_nav_menu()'s menu: the one the arguments name, its items marked for |
| [`NavMenuItems`](#navmenuitems) | final class | 161 | Classic menu items as the reference serves them to walkers and plugins |
| [`NavigationFallback`](#navigationfallback) | final class | 104 | The navigation menu a navigation block falls back to |
| [`Notices`](#notices) | final class | 96 | The debugging notices plugins and the engine raise: a deprecated function, |
| [`OEmbed`](#oembed) | final class | 131 | oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes. |
| [`ObjectCache`](#objectcache) | final class | 63 | The per-request object cache behind wp_cache_*: groups of keys, nothing persistent. |
| [`ObjectTerms`](#objectterms) | final class | 30 | wp_get_object_terms's handling of taxonomies registered with their own |
| [`OptionLock`](#optionlock) | final class | 23 | A lock the reference keeps as an option row (the revision upgrade's |
| [`OptionSanitizer`](#optionsanitizer) | final class | 114 | A core option's value cleaned as the reference's sanitize_option cleans |
| [`OptionWrites`](#optionwrites) | final class | 133 | Options written the way add_option(), update_option() and delete_option() |
| [`Options`](#options) | final class | 530 | Options as plugin code sees them, kept where the reference keeps them: |
| [`OptionsGroup`](#optionsgroup) | final class | 57 | wp_cache's options group, where option reads are kept as the reference |
| [`OutputFloor`](#outputfloor) | final class | 23 | The output buffers the engine reads its pages from. The reference prints |
| [`Pages`](#pages) | final class | 136 | get_pages() as the reference shapes it: its arguments as a post query |
| [`Patterns`](#patterns) | final class | 161 | The block pattern, pattern category, and block style registries as data. |
| [`PlaceholderTrace`](#placeholdertrace) | final class | 37 | Records every call into a generated placeholder while a site opts in by |
| [`Placeholders`](#placeholders) | final class | 71 | The printf placeholders plugin code hands wpdb::prepare, filled the way |
| [`PluginActivation`](#pluginactivation) | final class | 70 | Switching a plugin on and off the way the reference's activate_plugin |
| [`PluginCall`](#plugincall) | final class | 12 | Calls code a plugin, theme or drop-in registered (a hook callback, a |
| [`PluginFiles`](#pluginfiles) | final class | 122 | A plugin's files as WordPress's plugin functions know them: where a |
| [`PluginRemoval`](#pluginremoval) | final class | 140 | Deleting plugins as the reference's delete_plugins does it: each in turn |
| [`PluginRequirements`](#pluginrequirements) | final class | 66 | Whether a plugin can be switched on here, as the reference's |
| [`PluginUpdates`](#pluginupdates) | final class | 65 | The update offers the site's own plugins publish. A plugin that hosts |
| [`Plugins`](#plugins) | final class | 274 | Loads the site's plugins into the runtime the way the reference does: |
| [`PostCaches`](#postcaches) | final class | 27 | A post's cached rows let go as WordPress lets them go: its row, its meta |
| [`PostData`](#postdata) | final class | 98 | The loop's view of a post, as the reference's generate_postdata and |
| [`PostEvents`](#postevents) | final readonly class | 165 | What the reference's REST controllers tell plugins about a post they |
| [`PostFields`](#postfields) | final class | 89 | A post's fields put in a context as WordPress's sanitize_post_field puts |
| [`PostInsert`](#postinsert) | final readonly class | 213 | The decisions behind wp_insert_post: which columns a postarr fills, when |
| [`PostLinks`](#postlinks) | final class | 223 | Post addresses as the reference's link functions build them (probe |
| [`PostLocks`](#postlocks) | final class | 24 | A post's edit lock (_edit_lock, "time:user"): who holds it while it is |
| [`PostLookup`](#postlookup) | final readonly class | 153 | The post reads plugin code asks for by shape: a page by title, revisions, counts. |
| [`PostQuery`](#postquery) | final class | 425 | WP_Query::get_posts as the reference runs it (probe wp-query-sql): the |
| [`PostQueryParts`](#postqueryparts) | final class | 56 | The pieces of one WP_Query run as the reference builds them and hands |
| [`PostQueryResults`](#postqueryresults) | final class | 113 | What WP_Query does with its posts once it has them, as the reference does |
| [`PostQueryStatus`](#postquerystatus) | final class | 165 | WP_Query's post type and status clause as the reference writes it (probe |
| [`PostQueryTax`](#postquerytax) | final class | 193 | The taxonomy side of WP_Query as the reference runs it (probe |
| [`PostQueryWhere`](#postquerywhere) | final class | 264 | The WHERE fragments WP_Query writes from its variables, in the reference's |
| [`PostReads`](#postreads) | final class | 357 | What WordPress's post functions tell about posts: a page's path and a |
| [`PostRevisions`](#postrevisions) | final class | 231 | A post's revisions as the reference's wp_save_post_revision keeps them |
| [`PostSave`](#postsave) | final class | 230 | A REST save's columns through the filters the reference's save runs |
| [`PostTags`](#posttags) | final class | 216 | What the post template tags tell about a post (the one being read when |
| [`PostWrites`](#postwrites) | final class | 573 | Posts written as WordPress's post functions write them: a post stored |
| [`QueriedObject`](#queriedobject) | final readonly class | 70 | Which object a query is "about", read from its flags and variables: a term |
| [`QueryAnswers`](#queryanswers) | final class | 23 | Answers for queries the engine writes itself and already knows the result |
| [`QueryArgs`](#queryargs) | final class | 78 | Query arguments added to and taken from an address the way WordPress's |
| [`QueryFlags`](#queryflags) | final readonly class | 202 | The conditional flags a set of query variables implies (is_single, is_archive, |
| [`RecipientLocale`](#recipientlocale) | final class | 30 | An email written in its reader's language, as the reference writes each: |
| [`Records`](#records) | final class | 140 | Posts, terms and comments as WordPress hands them to plugins: found by |
| [`Recovery`](#recovery) | final readonly class | 241 | Recovery from a fatal in someone else's code. When a plugin or theme |
| [`Refusal`](#refusal) | final readonly class | 6 | A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error. |
| [`Registered`](#registered) | final class | 188 | What is registered, looked up the way WordPress's lookups answer: post |
| [`RegisteredSettings`](#registeredsettings) | final class | 108 | Settings as register_setting keeps them (probe rest-settings): the |
| [`Registry`](#registry) | final class | 523 | Post types, taxonomies, and statuses as plugin code registers and reads |
| [`RequestHeaders`](#requestheaders) | final class | 106 | What WordPress's request functions say and send: the protocol the |
| [`RewriteRules`](#rewriterules) | final class | 390 | The rewrite rules WordPress makes from its structures, as the reference |
| [`RewriteStructs`](#rewritestructs) | final class | 113 | The rewrite object's state and the structures it builds links and rules |
| [`Runtime`](#runtime) | final class | 488 | The WordPress runtime the engine offers plugin code: the procedural |
| [`SafeUrls`](#safeurls) | final class | 22 | Whether an address is one the site may fetch (wp_http_validate_url): an |
| [`ScriptLoading`](#scriptloading) | final class | 82 | How a script may load, as the reference decides it (probe |
| [`ScriptModules`](#scriptmodules) | final class | 393 | The script modules registry: registrations with typed dependencies, the |
| [`ScriptPack`](#scriptpack) | final class | 146 | The site-supplied script pack: the `wp-*` JavaScript packages the engine |
| [`Shortcodes`](#shortcodes) | final class | 333 | The shortcode registry plugin code fills with add_shortcode, and the |
| [`SiteInfo`](#siteinfo) | final class | 63 | What WordPress tells about the site (get_bloginfo): its name and tagline, |
| [`SiteOptions`](#siteoptions) | final class | 76 | The network's options, as a single site keeps them: options stored not |
| [`SiteTime`](#sitetime) | final class | 90 | Time as the site keeps it: its timezone (the named one, else the UTC |
| [`SiteUrls`](#siteurls) | final class | 148 | The site's own addresses as WordPress builds them: the home and site |
| [`StoredObjects`](#storedobjects) | final class | 140 | The classes a stored blob may name and come back as. The serialized |
| [`SymbolGap`](#symbolgap) | final readonly class | 196 | The part of the reference's interface the runtime does not answer: names in |
| [`SymbolTable`](#symboltable) | final class | 69 | What a folder's PHP names, collected while its tokens are read: the |
| [`Symbols`](#symbols) | final class | 524 | A static read of what a plugin's PHP calls: global functions and classes |
| [`TagEditor`](#tageditor) | final class | 152 | Edits one start tag's attributes in place the way the reference's tag |
| [`TaxTerms`](#taxterms) | final class | 97 | The terms a taxonomy query's clause names, found as the reference finds |
| [`TemplateFiles`](#templatefiles) | final class | 254 | The theme templates WordPress's template functions name: the index and |
| [`TermEvents`](#termevents) | final readonly class | 112 | What the reference's REST terms controller tells plugins, for the |
| [`TermFields`](#termfields) | final class | 65 | A term's fields in a context, as the reference's sanitize_term_field |
| [`TermLookup`](#termlookup) | final class | 464 | Terms looked up as WordPress's taxonomy functions find them: a list by |
| [`TermOrder`](#termorder) | final class | 61 | A term query's ORDER BY as the reference writes it (probe |
| [`TermQuery`](#termquery) | final readonly class | 382 | Term reads in the shapes plugin code asks for: get_terms() arguments to |
| [`TermQueryRunner`](#termqueryrunner) | final class | 299 | WP_Term_Query::get_terms as the reference runs it (probe |
| [`TermQueryTree`](#termquerytree) | final class | 147 | What a term query does with its rows, as the reference does it (probe |
| [`TermSave`](#termsave) | final class | 207 | wp_insert_term and wp_update_term in the reference's order (probe |
| [`TermTags`](#termtags) | final class | 364 | What WordPress's template tags tell about a post's terms: its terms of a |
| [`TermWriter`](#termwriter) | final readonly class | 176 | The decisions behind wp_delete_term and the object-term relationships: |
| [`TermWrites`](#termwrites) | final class | 335 | Terms written as WordPress's taxonomy functions write them: a term made, |
| [`ThemeMods`](#thememods) | final class | 57 | The active theme's settings (theme_mods_{stylesheet}) as WordPress keeps |
| [`ThemeSupports`](#themesupports) | final class | 263 | What a theme supports, as add_theme_support keeps it (probe rest-themes): |
| [`ThemeSwitch`](#themeswitch) | final class | 145 | Switching the theme as the reference does it, in two halves. The switch |
| [`Themes`](#themes) | final class | 125 | The site's themes as WordPress names and finds them: the active |
| [`TimeSince`](#timesince) | final class | 12 | How long ago something was, as human_time_diff words it: the span |
| [`Transients`](#transients) | final class | 142 | Values kept for a while, as WordPress keeps them in options: the site's |
| [`Translations`](#translations) | final class | 174 | The translations a request loads, as WordPress's l10n functions load |
| [`TreeWalk`](#treewalk) | final class | 182 | The Walker contract's traversal: elements keyed by the walker's |
| [`TypeProps`](#typeprops) | final class | 152 | A post type's or taxonomy's properties from the arguments it was built |
| [`UpdateCounts`](#updatecounts) | final class | 67 | The updates waiting, as wp_get_update_data counts them for the user |
| [`Upgrade`](#upgrade) | final class | 390 | What WP_Upgrader does with a package (probe upgrader), for the facade's |
| [`UserCounts`](#usercounts) | final class | 75 | The user counts WordPress's user functions give: how many users hold each |
| [`UserEvents`](#userevents) | final readonly class | 102 | What the reference's REST users controller tells plugins, for the |
| [`UserInsert`](#userinsert) | final readonly class | 118 | The decisions behind wp_insert_user: what a new account needs, which email |
| [`UserOrder`](#userorder) | final class | 43 | A user query's ORDER BY keys as the reference writes them (probe |
| [`UserQueryRoles`](#userqueryroles) | final class | 91 | A user query's roles and capabilities as the meta clauses the reference |
| [`UserQueryRunner`](#userqueryrunner) | final class | 232 | WP_User_Query as the reference runs it (probe wp-user-query-sql): the |
| [`UserSave`](#usersave) | final class | 74 | An account's fields through the filters the reference's wp_insert_user |
| [`UserStylesPost`](#userstylespost) | final class | 51 | A theme's user styles post as WordPress's theme.json resolver finds and |
| [`UserWrites`](#userwrites) | final class | 372 | Accounts written as WordPress's user functions write them: an account |
| [`VersionCheck`](#versioncheck) | final class | 27 | Whether the WordPress version Minn reports and the PHP it runs on meet a |
| [`WidgetAreas`](#widgetareas) | final class | 331 | The widgets helper functions as the reference answers them (probe |
| [`WpdbFields`](#wpdbfields) | final class | 404 | The database object's writers and the charset work behind them (probe |
| [`WpdbReads`](#wpdbreads) | final class | 221 | What the database object plugins hold as $wpdb does when it runs and reads |
| [`WpdbSetup`](#wpdbsetup) | final class | 139 | How the database object plugins hold as $wpdb is set up (probe |

## Abilities

`final class Minn\Runtime\Abilities` · `public/minn/src/Minn/Runtime/Abilities.php`

The abilities registry behind WP_Abilities_Registry,
WP_Ability_Categories_Registry and the wp_*_ability facade, as the
reference keeps it (probes abilities-registry, abilities-classes). The
categories init and then the abilities init fire once each, lazily, on
the first lookup of their kind, each handed its registry. The registries
themselves take a registration at any time; only the wp_register_*
functions insist on the init action. A registration is checked as the
reference checks it (name shape, duplicates, a known category, then the
properties the ability and category classes check) and refused with its
notice. An ability's meta gains the default annotations, show_in_rest
(public's value when unset) and public.

- const `STATE` = `'abilities'`
- const `CATEGORIES` = `'WP_Ability_Categories_Registry'`
- const `ABILITIES` = `'WP_Abilities_Registry'`
- const `NAME` = `'/^[a-z0-9-]+\\/[a-z0-9-]+$/'`
- const `SLUG` = `'/^[a-z0-9]+(?:-[a-z0-9]+)*$/'`
- const `ANNOTATIONS` = `array (   'readonly' => NULL,   'destructive' => NULL,   'idempotent' => NULL, )`

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

### static `abilityRefusal(array $args): ?string`

Why an ability's properties are refused, as WP_Ability::prepare_properties says it, or null. @param array<string, mixed> $args

- `@param array<string, mixed> $args`

### static `categoryRefusal(array $args): ?string`

Why a category's properties are refused, as WP_Ability_Category::prepare_properties says it, or null. @param array<string, mixed> $args

- `@param array<string, mixed> $args`

### static `meta(array $meta): array`

An ability's meta with the reference's defaults: the three annotations
first, show_in_rest (public's value when unset), then public.

- `@return array<string, mixed>`

Internals: `state()` (private, line 30), `save()` (private, line 36), `forget()` (private, line 232)


## AbilityRun

`final class Minn\Runtime\AbilityRun` · `public/minn/src/Minn/Runtime/AbilityRun.php`

The checks of an ability's run, as the reference makes them (probes
abilities-registry, abilities-classes); WP_Ability::execute strings them:
input left out takes the input schema's default, input given to an ability
without an input schema is refused, input that its schema refuses is
ability_invalid_input; a permission callback that does not answer true is
ability_invalid_permissions (an error it returns is reported as a notice);
then wp_before_execute_ability, the callback
(an error it returns is the answer), the output checked against the
output schema (ability_invalid_output), and wp_after_execute_ability.

### static `input(string $name, array $schema, mixed $input): Minn\Objects\Errors|true`

True, or why the input does not fit.

### static `allowed(string $name, mixed $answer): Minn\Objects\Errors|true`

True, or the error a permission answer other than true becomes (an error it returns is reported as a notice).

### static `output(string $name, array $schema, mixed $output): Minn\Objects\Errors|true`

True, or why the output does not fit the output schema.


## AccountFlows

`final class Minn\Runtime\AccountFlows` · `public/minn/src/Minn/Runtime/AccountFlows.php`

The account functions a sign-in page and plugins call, with the
reference's checks, words, hooks and mail (probe account-flows): asking
for a password reset (retrieve_password), resetting one
(reset_password), registering (register_new_user), and telling the site
and the new user about a registration (wp_new_user_notification).

- const `NO_ACCOUNT` = `'<strong>Error:</strong> There is no account with that username or email address.'`

Used by: `Minn\Login\LoginController`

### static `retrievePassword(string $login): Minn\Objects\Errors|bool`

A reset link mailed to the account a login or email names: true, or
the refusal (no name, no such account, a plugin's error, reset not
allowed, or the mail failing).

### static `resetPassword(Minn\Objects\User $user, string $password): void`

A new password for a user, announced before (password_reset) and after (after_password_reset).

### static `registerNewUser(string $login, string $email): Minn\Objects\Errors|int`

A new account for a login and an email, with a generated password and
the nag to change it: its id, or every refusal at once (the name
empty, illegal, taken or barred; the email empty, malformed or taken;
whatever register_post and registration_errors add).

### static `newUserNotification(int $userId, mixed $deprecated, string $notify): void`

A new account announced: to the site's address (unless only the user
is told), then to the user with a link to set their password (unless
only the site is, or the old call shape asks for nobody).

Internals: `resetLink()` (private, line 165)


## AdminActionController

`final readonly class Minn\Runtime\AdminActionController` · `public/minn/src/Minn/Runtime/AdminActionController.php`

The wp-admin pages a plugin answers itself. The admin is Minn Admin, so
the engine draws no wp-admin screen, but a page builder opens its editor
from one: Elementor's "Edit with Elementor" is post.php?post=N&action=elementor,
whose handler (admin_action_elementor) prints a whole page of its own and
ends the request before any screen is drawn.

A request to a wp-admin page that names an action boots as an admin
request, as the reference's does. The admin cookie (scoped to /wp-admin)
is asked for, for the user the request is read as; without it the
visitor signs in and comes back. admin_init fires, then the action's
handlers. When no plugin handles the action, or its handlers return, the
request lands in Minn Admin, where every other wp-admin address goes.

One page is an endpoint instead: the media modal's uploader posts its files
to async-upload.php (action upload-attachment), which the reference answers
as an ajax request, in plain text. After the page's action the upload runs
the handlers registered for the upload-attachment ajax action, as
admin-ajax.php would: WordPress's own answer comes with the Minn Legacy Pack,
the plugin that brings the modal.

- const `PAGE` = `'#^/wp-admin/[a-z0-9-]+\\.php$#'`
- const `UPLOAD` = `'/wp-admin/async-upload.php'`
- const `POST` = `'/wp-admin/admin-post.php'`

Used by: `Minn\Engine`, `Minn\Runtime\AjaxController`, `Minn\Runtime\EarlyRequest`

```php
__construct(Minn\Auth\Cookie $cookie, Minn\Front\Permalinks $permalinks)
```


### static `claims(Minn\Http\Request $request): bool`

Whether a request is for a wp-admin page with an action to run, which the runtime boots as an admin request.

### static `uploads(Minn\Http\Request $request): bool`

Whether a request is the media modal's upload, which the reference answers as an ajax request (DOING_AJAX).

### `dispatch(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-admin/{page:[a-z0-9-]+\.php} (public)`

Runs the handlers a plugin hooked for the action, or sends the visitor on.

Internals: `post()` (private, line 124), `upload()` (private, line 157), `action()` (private, line 171)


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

Used by: `Minn\Engine`, `Minn\Runtime\AdminActionController`, `Minn\Runtime\Constants`, `Minn\Runtime\EarlyRequest`

### static `claims(Minn\Http\Request $request): bool`

Whether a request is for the endpoint, which the runtime boots as an admin request.

### static `doing(Minn\Http\Request $request): bool`

Whether the reference answers a request as an ajax one (DOING_AJAX): this endpoint, and the media modal's uploads.

### `dispatch(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-admin/admin-ajax.php (public)`

Runs the handlers registered for the action the request names.

Internals: `action()` (private, line 89), `allowedOrigin()` (private, line 96), `registerCore()` (private, line 106)


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

### static `create(int $userId, array $body, Minn\Objects\RestRequest $request): Minn\Objects\Errors|array`

A new password: its record and its plain text in groups of four, or
the reference's refusal.

- `@param array<string, mixed> $body`
- `@return array{0: array<string, mixed>, 1: string}|Errors`

### static `update(int $userId, string $uuid, array $body, Minn\Objects\RestRequest $request): Minn\Objects\Errors|array`

A password renamed (when the body names it): its record as it stands.

- `@param array<string, mixed> $body`
- `@return array<string, mixed>|Errors`

Internals: `prepared()` (private, line 61)


## ApplicationPasswordRecords

`final class Minn\Runtime\ApplicationPasswordRecords` · `public/minn/src/Minn/Runtime/ApplicationPasswordRecords.php`

A user's application passwords as WordPress's API keeps them
(WP_Application_Passwords), over the engine's store
(Minn\Auth\ApplicationPasswords, the reference's format): made, found,
renamed, used, removed one by one or all together, with the reference's
answers and actions.

- const `IN_USE` = `'using_application_passwords'` — The site option saying any password was made.

Used by: `Minn\Rest\ApplicationPasswordsController`, `Minn\Runtime\ApplicationPasswordEvents`, `Minn\Runtime\ApplicationPasswordSignIn`

### static `inUse(): bool`

Whether any user has made an application password (is_in_use).

### static `create(mixed $userId, mixed $args = array ( )): mixed`

A new password for the user (create_new_application_password): [the
password, its record], or the reference's error for a nameless one.

### static `all(mixed $userId): array`

A user's passwords (get_user_application_passwords).

- `@return list<array<string, mixed>>`

### static `find(mixed $userId, mixed $uuid): ?array`

One of a user's passwords by its uuid (get_user_application_password), or null.

### static `nameExists(mixed $userId, mixed $name): bool`

Whether one of a user's passwords has the name (application_name_exists_for_user).

### static `update(mixed $userId, mixed $uuid, mixed $update = array ( )): mixed`

A password renamed, the only field the reference changes (update_application_password): true, or its not-found error.

### static `recordUsage(mixed $userId, mixed $uuid): mixed`

A use recorded, when and from where (record_application_password_usage): true, or the not-found error.

### static `delete(mixed $userId, mixed $uuid): mixed`

A password removed (delete_application_password): true, or the not-found error.

### static `deleteAll(mixed $userId): int`

Every password of the user removed, each told to wp_delete_application_password (delete_all_application_passwords); how many went.

Internals: `notFound()` (private, line 113), `store()` (private, line 118)


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

Internals: `refusal()` (private, line 96)


## ArchiveLinks

`final class Minn\Runtime\ArchiveLinks` · `public/minn/src/Minn/Runtime/ArchiveLinks.php`

A post type's archive address and its feed, as the reference gives them
(probe archive-links). The posts' archive is the posts page when the
front is a page with one, else the bare home; a type with an archive
lives under its named archive or its rewrite slug (after the
structure's front unless it declines it), or at ?post_type= without
rewrites or pretty permalinks; any other type has none (false, no
filter). Its feed is the archive's /feed/ (/feed/{type}/ for one not the
default) when the type's rewrite takes feeds, else ?feed= with the type
spelled out.

Used by: `Minn\Runtime\NavMenuItems`, `Minn\Runtime\TermTags`

### static `archive(string $postType): string|false`

get_post_type_archive_link.

### static `feed(string $postType, string $feed): string|false`

get_post_type_archive_feed_link.


## AssetEdits

`final class Minn\Runtime\AssetEdits` · `public/minn/src/Minn/Runtime/AssetEdits.php`

What a plugin did to $wp_scripts->registered or $wp_styles->registered
directly, as wp_default_scripts callbacks do (a dependency dropped, a
source swapped, an entry unset or added), handed to the registry: each
handle as the view showed it last against the view as the plugin left
it.

Used by: `Minn\Runtime\AssetView`

### static `apply(Minn\Runtime\Assets $assets, array $edited, array $synced, ?string $only = NULL): void`

The edits applied, for every handle or only the one named. The view
changes with each edit the registry takes, so the edited view is read
from the copy handed in.

- `@param array<string, mixed> $edited the view as the plugin left it`
- `@param array<string, array{0: mixed, 1: list<string>, 2: mixed, 3: mixed, 4: mixed}> $synced each handle as the view last showed it (source, dependencies, version, args, data)`


## AssetQueue

`final class Minn\Runtime\AssetQueue` · `public/minn/src/Minn/Runtime/AssetQueue.php`

The scripts and styles a request queues, as WordPress's asset functions
queue them: each kind's registry kept for the request, a handle queued
(with the notice for one queued before init), and a script's element,
from a file or inline, through the attribute filters.

Used by: `Minn\Blocks\Renderer`, `Minn\Content\Blocks`, `Minn\Content\MediaShortcodes`, `Minn\Front\PostEmbed`, `Minn\Runtime\Runtime`, `Minn\Runtime\ScriptModules`


### static `follow(string $kind, mixed $global): Minn\Runtime\Assets`

The registry the page's functions work on: the one held by the object
in the global a plugin may replace ($wp_scripts, $wp_styles), as the
reference's functions follow that global. Elementor's editor starts
from a new registry of each kind, leaving every other plugin's assets
out; from then on that one is the page's, the engine's own printing
included. Without such an object, the request's own.

### static `of(mixed $registry): ?Minn\Runtime\Assets`

The engine queue a registry object holds (a WP_Scripts or WP_Styles), null for anything else.

### static `forFunctions(string $kind, mixed $global): Minn\Runtime\Assets`

The registry a page's script or style function works on (follow), a script one used first (used).

### static `snapshot(): array`

The scripts, styles and script modules queued now, to compare with once a block has rendered.

- `@return array{0: list<string>, 1: list<string>, 2: list<string>}`

### static `dequeueSince(array $before): void`

Takes off the queues what joined them since the snapshot: what a block that rendered nothing enqueued, as
the reference takes it off.

- `@param array{0: list<string>, 1: list<string>, 2: list<string>} $before`

### static `settleBlock(array $before, string $html, string $name, Closure $enqueue, mixed $type = NULL): void`

After a block has rendered: when a named block rendered nothing, whitespace aside, and its render enqueued
something or its own assets are not all queued yet, enqueue_empty_block_content_assets is asked; unless it
keeps them, what the render enqueued is taken off the queues again and the block's own are not added.
Otherwise $enqueue adds its own. The text between blocks (no name) is never asked about, as on the reference.

- `@param array{0: list<string>, 1: list<string>, 2: list<string>} $before`

### static `assets(string $kind): Minn\Runtime\Assets`

The request's registry of one kind of asset, script or style, made the first time it is asked for.

### static `warnTooEarly(mixed $function, mixed $handle = ''): void`

The notice (doing it wrong, 3.3.0) for an asset function run before
init and before any of the enqueue actions
(_wp_scripts_maybe_doing_it_wrong), naming the handle when there is one.

### static `linedUp(Minn\Runtime\Assets $scripts, array $toDo): mixed`

The scripts lined up for printing, as print_scripts_array leaves them.
WordPress's own scripts among them that the engine has no file for are
noted for the Minn Legacy Pack (LegacyPack::noteScripts).

- `@param list<string> $toDo`

### static `enqueueScript(string $handle): void`

A registered script queued (wp_enqueue_script with a handle alone).

### static `used(): void`

The page's script registry used, as rendering a block or queuing a
script uses the reference's (it is made then, and what it is told as it
is made, it is told now): the facade's callback runs once and is gone.

### static `onFirstUse(?Closure $first): void`

What the page's script registry is told on its first use (the facade's wp_scripts()); null for nothing.

### static `enqueueStyle(string $handle): void`

A registered style queued (wp_enqueue_style with a handle alone).

### static `tag(mixed $attributes): string`

A script file's element (wp_get_script_tag), its attributes through wp_script_attributes first.

### static `inlineTag(mixed $data, mixed $attributes = array ( )): string`

Inline code's element (wp_get_inline_script_tag): the code trimmed between newlines, wp_inline_script_attributes asked.

Internals: `pending()` (private, line 105)


## AssetView

`final class Minn\Runtime\AssetView` · `public/minn/src/Minn/Runtime/AssetView.php`

A registry's view in the shape plugins read and edit it: the public
arrays of WordPress's dependency classes ($wp_scripts->registered, ->queue,
->done), kept current from Runtime\Assets one handle at a time. What a
plugin edits in the view is handed back (Runtime\AssetEdits) before the
registry changes or reads that handle, and for every handle before the
view is rebuilt or printed from. Rebuilding the whole view at each change
cost a page that registers hundreds of handles tens of milliseconds.

```php
__construct(Minn\Runtime\Assets $assets, object $owner)
```


### `sync(): void`

Rebuilds the whole view from the registry, after handing back what a plugin edited in it.

### `push(): void`

Hands back what a plugin edited in place (every handle, the queue, the printed list).

Internals: `refresh()` (private, line 54), `show()` (private, line 64), `showLists()` (private, line 81), `handBack()` (private, line 89), `reconcile()` (private, line 104)


## Assets

`final class Minn\Runtime\Assets` · `public/minn/src/Minn/Runtime/Assets.php`

The registry behind wp_register_/wp_enqueue_ for scripts and styles:
handles, sources, dependencies, versions, inline additions, localized
data, and the head/footer split. Printing follows the reference's tag
shapes as far as captured; see contracts/runtime.md.

Used by: `Minn\Media\MediaModal`, `Minn\Media\Player`, `Minn\Runtime\AssetEdits`, `Minn\Runtime\AssetQueue`, `Minn\Runtime\AssetView`, `Minn\Runtime\LegacyPack`, `Minn\Runtime\ScriptLoading`

```php
__construct(string $kind)
```


### `watch(Closure $listener, ?Closure $before = NULL): void`

A listener told after every change which handle changed (null when
only the queue or the printed list did), so a plugin-facing view keeps
that handle current without rebuilding the rest; and one told before a
handle is changed or read, so what a plugin edited in that view for it
lands first.

### `setQueue(array $queue): void`

The queue as a plugin left it after editing the view directly.

### `register(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $extra): bool`

Registers an asset under a handle.

### `revise(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $extra): bool`

An existing handle's source, dependencies, version and arguments replaced, its data, inline code and place in the queue kept.

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

### `unshipped(string $handle): bool`

Whether a handle is one the reference registers itself that nothing here registered: the engine carries no file for it.

### `dependencies(string $handle): array`

A handle's dependencies: as registered, or as the reference registers it. @return list<string>

- `@return list<string>`

### `dependents(string $handle): array`

The registered handles that name a handle among their dependencies. @return list<string>

- `@return list<string>`

### `reaches(array $from, string $handle): bool`

Whether a handle is a dependency, however deep, of any of the given
handles.

- `@param list<string> $from`

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

### `toPrint(): array`

Every queued handle not yet printed, dependencies first. @return list<string>

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

Internals: `settle()` (private, line 54), `changed()` (private, line 61), `defaults()` (private, line 216), `held()` (private, line 267), `ordered()` (private, line 366)


## AttachmentImages

`final class Minn\Runtime\AttachmentImages` · `public/minn/src/Minn/Runtime/AttachmentImages.php`

An attachment's image as WordPress's media functions make it: the
registered sizes, a size fitted to a box, the file for a size (or an
icon), the srcset and sizes pair, the loading attributes an image earns
where it sits, the img element itself, and the site icon.

Used by: `Minn\Blocks\ImageTags`, `Minn\Front\CustomLogo`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMenus`, `Minn\Rest\IndexController`, `Minn\Runtime\AttachmentModel`


### static `sizes(): array`

The registered sizes (_minn_image_sizes): the four from the options,
then the ones add_image_size added.

- `@return array<string, array<string, mixed>>`

### static `additionalSizes(): array`

The sizes plugins and themes added (wp_get_additional_image_sizes),
kept where they read them: $_wp_additional_image_sizes.

- `@return array<string, mixed>`

### static `constrain(mixed $width, mixed $height, mixed $maxWidth = 0, mixed $maxHeight = 0): mixed`

Dimensions fitted inside a box (wp_constrain_dimensions), through
wp_constrain_dimensions when there is a box.

- `@return array{0: int, 1: int}`

### static `matchesRatio(mixed $sourceWidth, mixed $sourceHeight, mixed $targetWidth, mixed $targetHeight): bool`

Whether two sizes share a ratio (wp_image_matches_ratio): the wider one, fitted into the other's box, comes within a pixel of it.

### static `constrainForEditor(mixed $width, mixed $height, mixed $size = 'medium', mixed $context = NULL): mixed`

Dimensions fitted for the editor to a named or given size
(image_constrain_size_for_editor), within editor_max_image_size.

- `@return array{0: int, 1: int}`

### static `intermediate(mixed $postId, mixed $size = 'thumbnail'): mixed`

An attachment's file for a size (image_get_intermediate_size), as image_get_intermediate_size leaves it; false for none.

### static `downsize(mixed $id, mixed $size = 'medium'): mixed`

An image attachment at a size (image_downsize): its address, width,
height and whether that is a resized copy, unless image_downsize
answers first; false for no image.

### static `src(mixed $attachmentId, mixed $size = 'thumbnail', mixed $icon = false): mixed`

An attachment's image at a size, or its icon when asked (wp_get_attachment_image_src), as wp_get_attachment_image_src leaves it.

### static `sizeFromMeta(mixed $sizeName, mixed $imageMeta): mixed`

A named size's dimensions from an image's metadata
(_wp_get_image_size_from_meta): full for the original; false for one
it lacks.

### static `srcset(mixed $sizeArray, mixed $imageSrc, mixed $imageMeta, mixed $attachmentId = 0): mixed`

The srcset of an image's sizes with its ratio (wp_calculate_image_srcset), as wp_calculate_image_srcset leaves the sources; false for none.

### static `subsizes(): array`

The sizes get_intermediate_image_sizes names (its
intermediate_image_sizes filter heard), each with its width, height
and crop: a name no size is registered under is read from its
options, and one of neither width nor height is left out (probe
media-template-doubts).

- `@return array<string, array{width: int, height: int, crop: bool|array<mixed>}>`

### static `browserProcessing(string $scheme, string $host): bool`

Whether images are processed in the browser before upload (probe
rest-attachments-controller): in a secure context, which is HTTPS or
a host of localhost or under .localhost (its port aside; 127.0.0.1
is not one), through wp_client_side_media_processing_enabled.

### static `sizesAttribute(mixed $size, mixed $imageSrc = NULL, mixed $imageMeta = NULL, mixed $attachmentId = 0): mixed`

The sizes attribute for an image's width (wp_calculate_image_sizes), as wp_calculate_image_sizes leaves it; false without a width.

### static `startsWithAuto(mixed $sizes): bool`

Whether a sizes list starts with auto, alone or before a comma, in any case (wp_sizes_attribute_includes_valid_auto).

### static `dimensionAttributes(mixed $width, mixed $height): string`

The width and height attributes for an image (image_hwstring), each when set.

### static `lazyLoading(mixed $tagName, mixed $context): mixed`

Whether lazy loading is on for a tag in a context (wp_lazy_loading_enabled): images and iframes, as wp_lazy_loading_enabled leaves it.

### static `loadingAttributes(mixed $tagName, mixed $attr, mixed $context): mixed`

The loading attributes an image or iframe earns
(wp_get_loading_optimization_attributes), unless a plugin answers
first: decoding async for an image; with both dimensions set, the
caller's own priority and loading, else where it sits.

### static `earnedPriority(string $tagName, array $attr): array`

Fetch priority high for an image large enough to deserve it, while no
other image has taken it.

- `@param array<string, mixed> $attr`
- `@return array<string, string>`

### static `imageAttributes(int $attachmentId, array $attr, string $src, int $width, int $height, string $context = 'wp_get_attachment_image'): array`

The loading attributes and the srcset and sizes pair an attachment's
image carries unless the caller set them.

- `@param array<string, mixed> $attr`
- `@return array<string, mixed>`

### static `image(mixed $attachmentId, mixed $size = 'thumbnail', mixed $icon = false, mixed $attr = ''): mixed`

An attachment's img element at a size (wp_get_attachment_image): its
class, alt text, loading attributes and srcset, through
wp_get_attachment_image_attributes and wp_get_attachment_image; empty
for none.

### static `siteIconUrl(mixed $size = 512, mixed $url = '', mixed $blogId = 0): mixed`

The site icon's address at a size (get_site_icon_url), the address given when the site has none; as get_site_icon_url leaves it.

### static `hasSiteIcon(mixed $blogId = 0): bool`

Whether the site has an icon (has_site_icon).

### static `omitThreshold(mixed $force = false): int`

How many images in a page's content load eagerly
(wp_omit_loading_attr_threshold): 3, as the filter leaves it, asked
once and then kept unless forced (probe: the loading threshold).

Internals: `asked()` (private, line 431), `byPlace()` (private, line 480)


## AttachmentModel

`final class Minn\Runtime\AttachmentModel` · `public/minn/src/Minn/Runtime/AttachmentModel.php`

An attachment as the media modal's scripts read it
(wp_prepare_attachment_for_js): its fields, its file and its sizes, and
what the visitor may do with it. The nonces the modal saves, deletes and
edits with, the links to edit the attachment and its author, and the post
it was uploaded to are there only for a visitor allowed each, as the
reference gives them. The model passes through wp_prepare_attachment_for_js.

- const `FIELDS` = `array (   0 => 'id',   1 => 'title',   2 => 'filename',   3 => 'url',   4 => 'link',   5 => 'alt',   6 => 'author',   7 => 'description',   8 => 'caption',   9 => 'name',   10 => 'status',   11 => 'uploadedTo',   12 => 'date',   13 => 'modified',   14 => 'menuOrder',   15 => 'mime',   16 => 'type',   17 => 'subtype',   18 => 'icon',   19 => 'dateFormatted',   20 => 'nonces',   21 => 'editLink',   22 => 'meta', )` — The model's first fields, in the order the reference sends them.

### static `forJs(mixed $attachment): mixed`

The model, or null for anything that is not an attachment.

Internals: `people()` (private, line 104)


## Attachments

`final class Minn\Runtime\Attachments` · `public/minn/src/Minn/Runtime/Attachments.php`

Attachments as WordPress's media functions keep them: the uploads folder
and its address, an attachment's file (stored relative to the folder),
its address and metadata, and what kind of file it is; each as its
filter leaves it.

Used by: `Minn\Content\MediaShortcodes`, `Minn\Front\EmbedCard`, `Minn\Front\PostEmbed`, `Minn\Media\Icons`, `Minn\Media\MediaModal`, `Minn\Rest\MediaController`, `Minn\Runtime\AttachmentImages`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\FileNames`, `Minn\Runtime\FileUpload`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TermTags`

### static `uploadDir(mixed $time = NULL): mixed`

The uploads folder and its address for a time, the month's folder too
when uploads go by month (_wp_upload_dir, wp_get_upload_dir), as
upload_dir leaves them.

- `@return array<string, mixed>`

### static `uploadDirMade(mixed $time = NULL, mixed $createDir = true, mixed $refreshCache = false): mixed`

The uploads folder made on disk when asked (wp_upload_dir), and returned.

### static `makeDir(mixed $target): bool`

A folder made with its parents (wp_mkdir_p): whether it is there and may be written to.

### static `relativePath(mixed $path): mixed`

A path under the uploads folder made relative to it (_wp_relative_upload_path), as _wp_relative_upload_path leaves it.

### static `file(mixed $attachmentId, mixed $unfiltered = false): mixed`

An attachment's file on disk (get_attached_file), the uploads folder before a relative one; through get_attached_file unless asked not to be.

### static `setFile(mixed $attachmentId, mixed $file): mixed`

An attachment's file recorded (update_attached_file), relative to the uploads folder; an empty one removes the record.

### static `url(mixed $attachmentId = 0): mixed`

An attachment's address (wp_get_attachment_url): its file under the
uploads address, else its guid; false for no attachment or no address.

### static `metadata(mixed $attachmentId = 0, mixed $unfiltered = false): mixed`

An attachment's metadata (wp_get_attachment_metadata), the post being
read's for none; one stored in a shape the meta reader refuses is
parsed from its row; false for none; through
wp_get_attachment_metadata unless asked not to be.

### static `setMetadata(mixed $attachmentId, mixed $data): mixed`

An attachment's metadata stored (wp_update_attachment_metadata) as wp_update_attachment_metadata leaves it; an empty one removed.

### static `is(mixed $type, mixed $post = NULL): bool`

Whether an attachment is a kind of file (wp_attachment_is): image, audio, video, or a MIME type or extension.

### static `isImage(mixed $post = NULL): bool`

Whether an attachment is an image (wp_attachment_is_image).

### static `audioExtensions(): mixed`

The audio file extensions the player takes (wp_get_audio_extensions), as wp_audio_extensions leaves them.

### static `videoExtensions(): mixed`

The video file extensions the player takes (wp_get_video_extensions), as wp_video_extensions leaves them.

### static `insert(mixed $args, mixed $file = false, mixed $parentPostId = 0, mixed $wpError = false, mixed $fireAfterHooks = true): mixed`

An attachment stored (wp_insert_attachment): the arguments over a file
and no parent, the parent given, saved as a post of type attachment.

### static `delete(mixed $postId, mixed $force = false): mixed`

An attachment deleted (wp_delete_attachment): to the trash first
unless forced or the media trash is off; unless pre_delete_attachment
answers, its terms, comments and meta removed, the row between
delete_post and deleted_post, then its files. The post, or null.

### static `deleteFiles(mixed $postId, mixed $meta, mixed $backupSizes, mixed $file): bool`

An attachment's files deleted, its sizes and backups too (wp_delete_attachment_files); false when any was missing or outside the uploads folder.

### static `readImageMetadata(mixed $file): mixed`

A photo's own description from its IPTC and EXIF (wp_read_image_metadata),
through wp_read_image_metadata_types and wp_read_image_metadata; false
when there is no such file.

Internals: `fileUrl()` (private, line 127)


## Autosaves

`final class Minn\Runtime\Autosaves` · `public/minn/src/Minn/Runtime/Autosaves.php`

An autosave as the reference's REST autosave makes it (probes
rest-autosaves, hook-inputs-2; tests/tools/side-effects.php): the
author's own draft is saved in place through wp_update_post; anyone
else's post gets the author's one autosave slot, made through
wp_insert_post and _wp_put_post_revision, or updated through
wp_update_post after wp_creating_autosave. A slot already there is left
alone when every field given matches the post. An autosave request
(save) defines DOING_AUTOSAVE for the rest of the request, so plugins'
save hooks can tell, and no revision is saved while it is; a slot saved
on its own (create_post_autosave) defines nothing.

Used by: `Minn\Rest\RevisionsController`, `Minn\Rest\TemplateRevisionsController`

### static `save(Minn\Objects\Post $post, int $userId, array $fields): Minn\Objects\Errors|int`

Saves the given revision fields (post_title, post_content,
post_excerpt) as the user's autosave of the post: the post's own id
when the draft itself was saved, the slot's otherwise, or the save's
refusal.

- `@param array<string, string> $fields`

### static `saveSlot(Minn\Objects\Post $post, int $userId, array $fields): Minn\Objects\Errors|int`

Saves the given revision fields in the user's autosave slot of the
post (create_post_autosave): the slot's id, or the save's refusal.

- `@param array<string, string> $fields`

### static `inPlace(Minn\Objects\Post $post, int $userId): bool`

Whether an autosave saves the post itself: the author's own draft (or auto-draft), while no one else holds its lock.

Internals: `slot()` (private, line 74), `matchesPost()` (private, line 85)


## AvMetadata

`final class Minn\Runtime\AvMetadata` · `public/minn/src/Minn/Runtime/AvMetadata.php`

An audio or video file's metadata as WordPress's media readers keep it
(wp_read_audio_metadata, wp_read_video_metadata; probe media-meta), from
the engine's own analysis (Media\Av): an audio file's stream described
whole, a video's size, format and codec with its audio beside, the
playing time rounded and as a clock, when it was made, its ID3 tags and
cover, each through the reader's filter.

### static `audio(mixed $file): mixed`

An audio file's metadata (wp_read_audio_metadata); false when there is no such file.

### static `video(mixed $file): mixed`

A video file's metadata (wp_read_video_metadata); false when there is no such file.

### static `withTags(array $metadata, mixed $data): array`

Metadata with an analysis's ID3 tags added (wp_add_id3_tag_data): the
first value under each name of the ID3v2 tag, or of the ID3v1 tag when
there is no ID3v2 one, cleaned as post content; the length is left
out, and a terms-of-use notice the tag cut short is mended.

- `@param array<string, mixed> $metadata`
- `@return array<string, mixed>`

### static `withCover(mixed $attachmentId, string $file, array $metadata, string $kind): array`

An audio or video attachment's metadata with its cover kept as an
attachment of its own, as wp_generate_attachment_metadata keeps it:
where the attachment type or the theme supports thumbnails, the
cover (found again by its digest, or written beside the file as
"name-ext-image") becomes the attachment's thumbnail; the cover's
bytes leave the metadata either way.

- `@param array<string, mixed> $metadata`
- `@return array<string, mixed>`

### static `creationTimestamp(mixed $data): int|false`

When an analysed file was made (wp_get_media_creation_timestamp): a
Windows Media file's creation date, a Matroska or WebM file's creation
time (false when it is no date) or its date, or the time the first box
of an MPEG-4 or QuickTime movie gives; false for any other.

Internals: `coverType()` (private, line 141), `cover()` (private, line 152), `common()` (private, line 206), `finished()` (private, line 231), `withoutStreams()` (private, line 254), `cleaned()` (private, line 261)


## Avatar

`final class Minn\Runtime\Avatar` · `public/minn/src/Minn/Runtime/Avatar.php`

Avatars the way get_avatar_data and get_avatar decide them: the argument
defaults, the email an id, user, post, or comment names, the Gravatar URL,
and the <img> attributes. Behaviour pinned by contracts/fixtures/api/functions.json.

Used by: `Minn\Blocks\Dynamic\Theme\Comments`

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

- const `NATIVE_DATA_DONE` = `array (   'block_core_gallery_data_id_backcompatibility' => 10, )` — The data filters the engine's renderer does itself for a block it
renders natively: a gallery's image ids. Element styles and style
variations are left to their own filters, which name the classes in
the block's className as plugins' later filters read it (probe
block-class-stages).
- const `NATIVE_RENDER_DONE` = `array ( )` — The render filters it does itself: none. The element, layout, style
variation and paragraph classes reach the markup at their own filters,
as a plugin's earlier filters see it without them (probe
block-class-stages).

Used by: `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Renderer`


### static `active(): bool`

Whether any block filter is registered.

### static `toArray(Minn\Blocks\Block $block): array`

A block as the parsed array plugins receive.

- `@return array<string, mixed> the parsed-array shape plugin code reads`

### static `fromArray(array $parsed): Minn\Blocks\Block`

A block from the parsed array plugins hand back.

### static `data(array $parsed, ?object $parent, array $done = array ( )): mixed`

A parsed block through render_block_data, without the callbacks done
already (NATIVE_DATA_DONE for a block the engine's renderer renders).

- `@param array<string, mixed> $parsed`
- `@param array<string, int|array{0: int, 1: string}> $done`

### static `rendered(string $html, array $parsed, ?object $instance, array $done = array ( )): string`

A rendered block through render_block and its per-name filter, without
the callbacks done already (NATIVE_RENDER_DONE, with its stand-ins, for
a block the engine's renderer renders).

- `@param array<string, mixed> $parsed`
- `@param array<string, int|array{0: int, 1: string}> $done`

### static `before(Minn\Blocks\Block $block, ?Minn\Objects\RenderedBlock $parent, array $done = array ( )): Minn\Blocks\Block|string`

A block about to render, begun as the reference begins it (probe
block-instances): one with no parent block (a document's, a part's, a
pattern's) is asked pre_render_block, render_block_data and
render_block_context with none, then made a WP_Block with the post's
context; an inner one is made a WP_Block from what its parent gives its
inner blocks first (its type asked get_block_type_uses_context), then
asked the three with its parent (the context filter handed the
context its type uses), and adopts what they made of it.
A short-circuit from pre_render_block, or the block as
render_block_data left it.

- `@param array<string, int|array{0: int, 1: string}> $done the data filters done already`

### static `made(Minn\Blocks\Block $block, ?Minn\Objects\RenderedBlock $parent): Minn\Blocks\Block`

A block begun as the reference's block list makes one its parent
renders without asking the block filters first (a navigation's own
items): its WP_Block, with what the parent gives its inner blocks.

### static `begun(Minn\Blocks\Block $block): array`

The WP_Block a block began as and the context render_block_context gave it.

- `@return array{0: RenderedBlock|null, 1: array<string, mixed>|null}`

### static `after(Minn\Blocks\Block $block, string $html, array $done = array ( )): string`

A rendered block through render_block and its per-name filter, with
the WP_Block it began as.

- `@param array<string, int|array{0: int, 1: string}> $done the render filters done already`

Internals: `begin()` (private, line 186), `context()` (private, line 195)


## BlockHooks

`final class Minn\Runtime\BlockHooks` · `public/minn/src/Minn/Runtime/BlockHooks.php`

The Block Hooks API on the engine's own front end: a plugin asks for its
block to be inserted next to an anchor block in a template part or a
pattern (WooCommerce puts the mini-cart after the navigation in a
header), and the markup the renderer reads carries those insertions.

The facade owns the traversal (wp-api/blocks.php); this is the seam the
theme blocks call, and it stays inert until a plugin actually hooks
something, so a site without such a plugin parses nothing extra.

Used by: `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\Structure`


### static `withSingleInstances(array $blocks, Closure $serialize): string`

Content's blocks written out by $serialize under the single-instance
rule (apply_block_hooks_to_content): a hooked type that does not
support multiple goes in at the first anchor position that names it
only, and nowhere when the content has one already, however deep
(probe taxonomy-blocks-user-doubts).

- `@param list<array<string, mixed>> $blocks the parsed content`

### static `nextPosition(): void`

The traversal moves on to the next anchor position (each before, after, first_child and last_child the visitors ask).

### static `hooked(): array`

The blocks hooked to each anchor, by position (get_hooked_blocks), as
the registered block types' block_hooks name them.

- `@return array<string, array<string, list<string>>>`

### static `active(): bool`

Whether block hooks are registered (the runtime's functions are there only once it is up).

### static `insert(array $anchor, string $position, array $hooked, mixed $context): string`

The blocks hooked to an anchor at a position (insert_hooked_blocks),
each as the filters leave it, serialized; a type a filter suppresses,
or one the anchor lists as ignored, goes in nothing (probe
post-block-hooks).

- `@param array<string, mixed> $anchor`
- `@param array<string, array<string, list<string>>> $hooked`

### static `markIgnored(array $anchor, string $position, array $hooked, mixed $context): string`

The anchor's ignoredHookedBlocks with the types hooked to it at a
position added (set_ignored_hooked_blocks_metadata): those a filter
leaves standing, the list keeping the keys it had (probe
post-block-hooks). Nothing is inserted: the markup is empty.

- `@param array<string, mixed> $anchor`
- `@param array<string, array<string, list<string>>> $hooked`

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

### static `forPost(string $content, int $postId): string`

A post's blocks with the blocks hooked to them, as the reference reads
a navigation's or a synced pattern's
(apply_block_hooks_to_content_from_post_object): the hooked blocks the
post says to leave out left out; as stored with no WordPress layer to
ask.

Internals: `filtered()` (private, line 131), `admitted()` (private, line 202), `names()` (private, line 225)


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
- const `I18N` = `array (   'title' => 'block title',   'description' => 'block description',   'keywords' =>    array (     0 => 'block keyword',   ),   'styles' =>    array (     0 =>      array (       'label' => 'block style label',     ),   ),   'variations' =>    array (     0 =>      array (       'title' => 'block variation title',       'description' => 'block variation description',       'keywords' =>        array (         0 => 'block variation keyword',       ),     ),   ), )` — The block.json strings a text domain translates, each with its context (get_block_metadata_i18n_schema).
- const `FIELD_HANDLES` = `array (   'editorScript' => 'editor-script',   'editorStyle' => 'editor-style',   'script' => 'script',   'style' => 'style',   'viewScript' => 'view-script',   'viewScriptModule' => 'view-script-module',   'viewStyle' => 'view-style', )`

### static `translated(array $metadata): array`

A block.json's strings in its text domain, each asked in its context
(title as "block title", keywords as "block keyword"...); a block
without a text domain is left as it is (probe taxonomy-blocks-user-doubts).

- `@param array<string, mixed> $metadata`
- `@return array<string, mixed>`

### static `settings(array $metadata, Closure $scriptHandle, Closure $styleHandle, Closure $moduleId, Closure $render): array`

Block settings from a block.json, the way the reference maps its properties.

- `@param Closure(array, string, int): (string|false) $scriptHandle registers one script entry, answering its handle`
- `@param Closure(array, string, int): (string|false) $styleHandle registers one style entry`
- `@param Closure(array, string, int): (string|false) $moduleId registers one view script module entry`
- `@param Closure(string): ?Closure $render the render callback for a template path, or null when the file is missing`
- `@return array<string, mixed>`

### static `assetHandle(string $block, string $field, int $index = 0): string`

The script or style handle a block.json field registers under; core blocks keep the `wp-block-` spelling.

Internals: `translatedValue()` (private, line 47), `handles()` (private, line 122)


## BlockQueries

`final class Minn\Runtime\BlockQueries` · `public/minn/src/Minn/Runtime/BlockQueries.php`

The query arguments the query and comments blocks run, as WordPress's
block functions build them from a block's context: a query block's post
query, and the comment query a post's comments block shows.

- const `FORMATS` = `array (   0 => 'standard',   1 => 'aside',   2 => 'chat',   3 => 'gallery',   4 => 'link',   5 => 'image',   6 => 'quote',   7 => 'status',   8 => 'video',   9 => 'audio', )` — The post formats a query block may ask for, by slug.

Used by: `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`

### static `posts(mixed $block, mixed $page): mixed`

A query block's post query for a page (build_query_vars_from_query_block),
through query_loop_block_query_vars when the block has a query context.

- `@return array<string, mixed>`

### static `comments(mixed $block): array`

The comment query a comments block shows (build_comment_query_vars_from_block):
approved comments oldest first, the reader's own held ones too, the
post's, threaded and paged as the discussion settings say.

- `@return array<string, mixed>`


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


## Bookmarks

`final class Minn\Runtime\Bookmarks` · `public/minn/src/Minn/Runtime/Bookmarks.php`

The site's links as the link functions find them (get_bookmarks): the
Links repository asked with the caller's arguments, each row an object,
through the get_bookmarks filter.

- const `DEFAULTS` = `array (   'orderby' => 'name',   'order' => 'ASC',   'limit' => -1,   'category' => '',   'category_name' => '',   'hide_invisible' => 1,   'show_updated' => 0,   'include' => '',   'exclude' => '',   'search' => '', )`

Used by: `Minn\Front\LinksOpml`

### static `find(mixed $args = ''): mixed`

The links asked for (get_bookmarks): a category by name narrows them to
that category, or to none when no link category has the name; a list
of links to include sets any category aside.


## BrowserGlobals

`final class Minn\Runtime\BrowserGlobals` · `public/minn/src/Minn/Runtime/BrowserGlobals.php`

The browser and web server a request names, as the reference's globals
have them from boot ($is_chrome, $is_safari, $is_IE, $is_apache,
$is_nginx, …; probe vars-matrix): one browser at most, read from the
user agent in the reference's order (Lynx, Edge, Opera, Chrome, Safari,
Internet Explorer on a Mac then elsewhere, Gecko, Netscape 4), an
iPhone being a mobile Safari; the server from SERVER_SOFTWARE, IIS 7 and
up apart. Chrome Frame's switch is the facade's (use_google_chrome_frame).

- const `NAMES` = `array (   0 => 'is_lynx',   1 => 'is_gecko',   2 => 'is_winIE',   3 => 'is_macIE',   4 => 'is_opera',   5 => 'is_NS4',   6 => 'is_safari',   7 => 'is_chrome',   8 => 'is_iphone',   9 => 'is_IE',   10 => 'is_edge',   11 => 'is_apache',   12 => 'is_IIS',   13 => 'is_iis7',   14 => 'is_nginx',   15 => 'is_caddy', )` — The globals, in the order the reference sets them.

### static `detect(string $agent, string $software): array`

Each global's value for this user agent and server software.

- `@return array<string, bool>`

Internals: `browser()` (private, line 44)


## CommentChecks

`final class Minn\Runtime\CommentChecks` · `public/minn/src/Minn/Runtime/CommentChecks.php`

What WordPress checks of a comment before and after it is stored: the
lengths the table allows its fields, each field through its pre_comment_
filter, the status a stored comment has in words, and whether a new
post of a type starts open to comments or pings.

Used by: `Minn\Content\Site`, `Minn\Front\CommentPostController`, `Minn\Runtime\BlockQueries`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentPages`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\PostWrites`

### static `fieldMaxLengths(): mixed`

How long each comment form field may be
(wp_get_comment_fields_max_lengths), as the table allows, through
wp_get_comment_fields_max_lengths.

- `@return array<string, int>`

### static `maxLengths(mixed $data): mixed`

Whether a comment fits its columns (wp_check_comment_data_max_lengths): true, or the error naming the first field too long.

### static `filter(mixed $data): array`

Each field through its pre_comment_ filter (wp_filter_comment), in the
reference's order (the user first, the email last), marked filtered.

- `@return array<string, mixed>`

### static `status(mixed $commentId): mixed`

A comment's status (wp_get_comment_status): approved, unapproved, spam or trash; false for an unknown comment or status.

### static `defaultStatus(mixed $type = 'post', mixed $commentType = 'comment'): mixed`

Whether a new post of a type starts open (get_default_comment_status):
a page starts closed; another type follows the site's default when it
supports comments (or trackbacks, for pings).

### static `passes(mixed $author, mixed $email, mixed $url, mixed $comment, mixed $userIp, mixed $userAgent, mixed $commentType): bool`

Whether a comment needs no moderation (check_comment): never while
moderation is on; the links counted in the comment as comment_text
shows it (comment_max_links_url may recount) against comment_max_links;
the moderation words; and, when asked, an earlier approved comment from
the same author (a pingback from one of the site's own posts, as
wp_auto_approve_ping decides; a trackback never).

### static `disallowed(mixed $author, mixed $email, mixed $url, mixed $comment, mixed $userIp, mixed $userAgent): bool`

Whether a line of disallowed_keys appears in the comment's fields
(wp_check_comment_disallowed_list): a word match, or an address's
start; the comment read without its tags and entities too.

### static `approval(mixed $data): mixed`

A comment's approval as its data asks (wp_check_comment_data): the
post's author and a moderator are approved; anyone else's comment
passes check_comment or is held, and the disallowed words send it to
the trash (spam with the trash off); pre_comment_approved has the last
word. The data may be slashed.

### static `allow(mixed $commentdata, mixed $wpError = false): mixed`

1, 0, "spam" or "trash" for a comment that may be stored
(wp_allow_comment), in the reference's order: a duplicate, which
duplicate_comment_id may name or clear, refuses with 409;
check_comment_flood and wp_is_comment_flood refuse a flood with 429;
then the approval. The error when asked for one, else the stop page,
for a refusal.

### static `create(mixed $commentdata, mixed $wpError = false): mixed`

A comment from outside stored the way the comment form stores one
(wp_new_comment): preprocess_comment; the ids, user, parent, address,
agent and dates filled in; wp_allow_comment; the pre_comment_
filters; the approval judged again on what they left; the comment
stored; comment_post. The id, or the error when asked for one (false
without).

### static `currentCommenter(): mixed`

The commenter remembered by the comment_author_ cookies (wp_get_current_commenter), as wp_get_current_commenter leaves them.

### static `unapprovedEmail(): string`

The address of the reader whose held comment the page may show
(wp_get_unapproved_comment_author_email): from a held comment's link
(its hash right, under ten minutes old), or else from the commenter's
cookie.

Internals: `filledIn()` (private, line 280), `refused()` (private, line 304), `comments()` (private, line 313)


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

### `closes(string $postType, string $postDateGmt, array $types, int $now): bool`

Whether the setting closes a post of $postType published at
$postDateGmt: the type is one of $types, the setting is on, and the
post is older than the days allowed. The posts a single query hands
over (_close_comments_for_old_posts) take their types from the
close_comments_for_post_types filter, which is honoured there.

- `@param list<string> $types`


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

### `restCreate(array $prepared, Minn\Http\Request $request, string $type = 'comment'): int`

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

Internals: `preprocessed()` (private, line 146), `requireContent()` (private, line 161), `field()` (private, line 171), `requireLengths()` (private, line 177), `refusal()` (private, line 186)


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

### static `listing(Minn\Objects\Query $query, Minn\Runtime\PostQueryParts $parts, object $wpdb): void`

A listing's comments, then its posts narrowed to theirs: the posts query's join and where as they stand after posts_join.

### static `single(Minn\Objects\Query $query, object $wpdb): void`

A single post's comments, once the query has found the post.

Internals: `request()` (private, line 51), `keep()` (private, line 62)


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

Used by: `Minn\Front\CommentPostController`

### static `submit(array $form): Minn\Objects\Comment|Minn\Objects\Errors`

The stored comment, or the refusal the form answers with.

- `@param array<string, mixed> $form the posted fields, unslashed`

Internals: `store()` (private, line 78), `postRefusal()` (private, line 96), `fieldRefusal()` (private, line 125)


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

Used by: `Minn\Runtime\CommentTags`

### static `pageOf(mixed $commentId, array $args): ?int`

The page a comment is on, through get_page_of_comment (handed the
arguments as settled and as given); a threaded reply answers with
its top-level comment's page. Null for a comment that is not there.

- `@param array<string, mixed> $args`

### static `linkPage(?Minn\Objects\Comment $comment, array $args): mixed`

The page a comment's link names (before get_comment_link): the one
asked for, the loop's, or the comment's own; '' for the default page
when the oldest comments come first.

- `@param array<string, mixed> $args`

### static `shown(mixed $cpage): mixed`

The comments page being shown (the cpage query variable) as the list's
links and reply links name it: '' for the default page when the
oldest comments come first.

### static `link(string $permalink, mixed $page): string`

A post's link opened at a page of its comments, as pretty or plain links write it.

Internals: `olderQuery()` (private, line 66)


## CommentQuery

`final readonly class Minn\Runtime\CommentQuery` · `public/minn/src/Minn/Runtime/CommentQuery.php`

The approval breakdown wp_count_comments reports (comment lists run through WP_Comment_Query and Minn\Runtime\CommentQueryRunner).

Used by: `Minn\Runtime\CommentReads`

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


### `run(Minn\Objects\CommentsQuery $query): mixed`

Runs the query, as WP_Comment_Query::get_comments() does: a count, ids, or comments.

### `ids(Minn\Objects\CommentsQuery $query, array $meta): array|int`

The comment IDs for the query's vars (get_comment_ids), or the count
when it counts; the request is written, and the found rows read when
the request counts them and found some.

- `@param array{join?: string, where?: string} $meta`
- `@return list<int>|int`

Internals: `request()` (private, line 91), `comments()` (private, line 123)


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


### `pieces(Minn\Objects\CommentsQuery $query, array $meta): array`

The WHERE pieces (joined with AND by the caller) and the posts join
('' when no post field is asked after).

- `@param array{join?: string, where?: string} $meta the meta query's SQL, when it has clauses`
- `@return array{0: list<string>, 1: string}`

### static `searchSql(string $search, array $columns): string`

A LIKE test over the columns (get_search_sql), as an AND clause. @param list<string> $columns

- `@param list<string> $columns`

Internals: `approved()` (private, line 77), `post()` (private, line 97), `types()` (private, line 112), `parentAndUser()` (private, line 140), `search()` (private, line 153), `posts()` (private, line 176), `given()` (private, line 196), `listOf()` (private, line 202), `bare()` (private, line 211)


## CommentReads

`final class Minn\Runtime\CommentReads` · `public/minn/src/Minn/Runtime/CommentReads.php`

What WordPress's comment functions tell about comments: the comments a
query finds, how many a post or the site has in each status, and when an
approved comment last arrived.

Used by: `Minn\Front\ToolbarMenus`, `Minn\Rest\CommentObject`, `Minn\Runtime\BlockQueries`, `Minn\Theme\FeedHeaders`

### static `query(mixed $args = ''): mixed`

The comments a query finds (get_comments): comments, ids or a count, as the comment query answers.

### static `counts(mixed $postId = 0): object`

How many comments a post or the site has in each status (wp_count_comments), unless wp_count_comments answers first.

### static `table(mixed $postId = 0): array`

The comments counted from the table by status, no filter asked
(get_comment_count): a post's, or the site's for 0.

- `@return array<string, int>`

### static `lastModified(mixed $timezone = 'server'): mixed`

When an approved comment last arrived (get_lastcommentmodified), in
GMT ("gmt", and the server's own, the GMT date moved by the server's
offset in the database, with microseconds) or the site's time
("blog"); false with none.


## CommentTags

`final class Minn\Runtime\CommentTags` · `public/minn/src/Minn/Runtime/CommentTags.php`

What the comment template tags tell about a comment (the one being read
when none is named): when it was written and its author's address, each
through the filter plugins hook there.

Used by: `Minn\Front\CommentPostController`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTemplates`, `Minn\Front\Permalinks`

### static `link(mixed $comment = NULL, mixed $args = array ( )): mixed`

A comment's address, at the page of comments it is on (get_comment_link), as get_comment_link leaves it.

### static `postLink(mixed $post = 0): mixed`

A post's address at its comments, or at the form when it has none (get_comments_link), as get_comments_link leaves it.

### static `author(mixed $commentId = 0): mixed`

A comment's author (get_comment_author), "Anonymous" for none, as get_comment_author leaves it.

### static `authorRss(): mixed`

The author of the comment being read, for a feed (get_comment_author_rss), as comment_author_rss leaves it.

### static `time(mixed $format = '', mixed $gmt = false, mixed $translate = true, mixed $commentId = 0): mixed`

When a comment was written (get_comment_time): in the format given or
the site's time format, local or UTC, names translated unless asked
not to be; empty for no comment.

### static `authorUrl(mixed $commentId = 0): mixed`

A comment author's address, escaped (get_comment_author_url); empty when none was given.


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

### `fill(Minn\Objects\CommentsQuery $query, array $top): array`

The query's top-level comments with their descendants filled in.

- `@param list<Comment> $top`
- `@return array<int, Comment>`

### static `flatten(array $children, array $args): array`

A comment's children, then each one's own, depth first, as a list.

- `@param array<array-key, mixed> $children`
- `@param array<string, mixed> $args get_children()'s arguments, handed down`
- `@return list<Comment>`


## CommentWrites

`final class Minn\Runtime\CommentWrites` · `public/minn/src/Minn/Runtime/CommentWrites.php`

Comments written as WordPress's comment functions write them: a comment
stored and changed, its status set (to the trash or spam with the status
it had kept aside, and back), deleted, and a post's count of approved
comments kept; each step with the actions and filters the reference
fires in its order, and the cached rows let go.

- const `DEFAULTS` = `array (   'comment_post_ID' => 0,   'comment_author' => '',   'comment_author_email' => '',   'comment_author_url' => '',   'comment_author_IP' => '',   'comment_date' => '',   'comment_date_gmt' => '',   'comment_content' => '',   'comment_karma' => 0,   'comment_approved' => '1',   'comment_agent' => '',   'comment_type' => '',   'comment_parent' => 0,   'user_id' => 0, )` — The columns a stored comment has, each with what a new comment takes when it gives none (an integer column is read as one).

Used by: `Minn\Runtime\Attachments`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\Deferrals`, `Minn\Runtime\PostWrites`

### static `insert(mixed $commentdata): int|false`

A comment stored (wp_insert_comment): its id, the post's count kept when it is approved, then wp_insert_comment told.

### static `updateCount(mixed $postId, mixed $deferred = false): bool`

A post's approved comments counted (wp_update_comment_count): put off
while counting is deferred; asked with $deferred, the posts put off
are counted now and the answer is true.

### static `updateCountNow(mixed $postId): bool`

A post's approved comments counted now (wp_update_comment_count_now),
unless pre_wp_update_comment_count_now supplies the count; plugins
told the old and new counts, then the post edited.

### static `update(mixed $commentarr, mixed $wpError = false): mixed`

A comment changed (wp_update_comment), from slashed data: the stored
comment (slashed) under the change, through the field filters as it
is, then unslashed and its content through comment_save_pre, then
wp_update_comment_data (handed the data, the slashed stored comment
and the filtered slashed change); the columns written, the post's
count, edit_comment with the columns, and the status transition. 0
when nothing changed.

### static `setStatus(mixed $commentId, mixed $status, mixed $wpError = false): mixed`

A comment's status set (wp_set_comment_status): hold, approve, spam or
trash; false for an unknown comment or status. A comment that has the
status already is not written: false, or the update error when one is
asked for, and nobody told.

### static `statusWord(mixed $stored): string`

A stored comment status in the words the transition actions use: approved, unapproved, spam, trash.

### static `transitionStatus(mixed $newStatus, mixed $oldStatus, mixed $comment): void`

What plugins are told when a comment changes status (wp_transition_comment_status); one that keeps its status hears only comment_{status}_{type}.

### static `cleanCache(mixed $ids): void`

Comments' cached rows and meta let go (clean_comment_cache), plugins told about each.

### static `delete(mixed $commentId, mixed $force = false): bool`

A comment deleted (wp_delete_comment): to the trash first unless
forced, it is there already or the trash is off; its replies moved up
to its parent; the post recounted when it was approved.

### static `batchUpdateType(): void`

One batch of the update giving comments stored without a type the
type "comment" (_wp_batch_update_comment_type, on
wp_update_comment_type_batch): under its lock (Runtime\OptionLock),
the newest wp_update_comment_type_batch_size of them (100), the next
batch scheduled first; a batch that finds none marks the update
finished. While another batch holds the lock, only a try five
minutes on is scheduled.

### static `trash(mixed $commentId): bool`

A comment to the trash (wp_trash_comment), or deleted when the trash is off.

### static `untrash(mixed $commentId): bool`

A comment back from the trash (wp_untrash_comment).

### static `spam(mixed $commentId): bool`

A comment marked spam (wp_spam_comment).

### static `unspam(mixed $commentId): bool`

A comment back from spam (wp_unspam_comment).

### static `trashPostComments(mixed $post = NULL): mixed`

A post's comments to the trash with it (wp_trash_post_comments): each
becomes post-trashed, its status kept on the post to give back; how
many changed (one set aside with its post already does not count).

### static `untrashPostComments(mixed $post = NULL): mixed`

A post back from the trash gives its comments their statuses back (wp_untrash_post_comments).

Internals: `setAside()` (private, line 343), `restore()` (private, line 363), `store()` (private, line 381)


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

### `registerAiProviders(Closure $loaded): void`

The reference's three AI providers. Whether a provider's plugin is
active is asked of the caller's closure, by the connector's id, when
asked (probe connector-defaults).

- `@param Closure(string): bool $loaded`

### `registerAkismet(Closure $loaded): void`

Akismet, the spam filter connector the reference adds after the AI
providers. Whether its plugin is active is asked of the caller's closure.

- `@param Closure(): bool $loaded`

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

- const `INITIAL` = `array (   0 => 'WPINC',   1 => 'KB_IN_BYTES',   2 => 'MB_IN_BYTES',   3 => 'GB_IN_BYTES',   4 => 'TB_IN_BYTES',   5 => 'PB_IN_BYTES',   6 => 'EB_IN_BYTES',   7 => 'ZB_IN_BYTES',   8 => 'YB_IN_BYTES',   9 => 'MEDIA_TRASH',   10 => 'WP_FEATURE_BETTER_PASSWORDS',   11 => 'MINUTE_IN_SECONDS',   12 => 'HOUR_IN_SECONDS',   13 => 'DAY_IN_SECONDS',   14 => 'WEEK_IN_SECONDS',   15 => 'MONTH_IN_SECONDS',   16 => 'YEAR_IN_SECONDS', )` — The fixed constants the reference has defined before a page cache's advanced-cache.php runs.

Used by: `Minn\Runtime\Runtime`

### static `initial(string $absPath, string $engineDir): void`

The constants the reference defines first (wp_initial_constants), before
a page cache's advanced-cache.php runs: the units, the content folder,
the memory limits and the debug and cache switches, wherever
wp-config.php left one undefined.

### static `define(Minn\Runtime\Runtime $runtime): void`

Defines the constants the reference defines at boot.

Internals: `maxMemoryLimit()` (private, line 120)


## CronEvents

`final class Minn\Runtime\CronEvents` · `public/minn/src/Minn/Runtime/CronEvents.php`

Scheduled events as WordPress keeps them in the cron option: the list
read (an old one upgraded) and stored, the schedules, single and
recurring events added (a near duplicate refused), rescheduled, removed
one by one, by hook or by arguments, looked up, and the due ones run;
each step with the filters that may answer first.

Used by: `Minn\Admin\SiteHealthTests`, `Minn\Cli\CronCommand`, `Minn\Cli\MinnCommand`, `Minn\Engine`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\PostWrites`

### static `all(): array`

The scheduled events by timestamp (_get_cron_array), an old list
upgraded first; empty when there is none.

- `@return array<int|string, mixed>`

### static `store(mixed $cron, mixed $wpError = false): mixed`

The events stored (_set_cron_array): whether they were, or the error saying they could not be when asked for one.

### static `schedules(): array`

The schedules events recur on (wp_get_schedules): hourly, twice daily,
daily and weekly, after the ones cron_schedules adds.

- `@return array<string, mixed>`

### static `scheduleSingle(mixed $timestamp, mixed $hook, mixed $args = array ( ), mixed $wpError = false): mixed`

An event run once (wp_schedule_single_event): refused near a duplicate (ten minutes either side) or when a plugin says no.

### static `schedule(mixed $timestamp, mixed $recurrence, mixed $hook, mixed $args = array ( ), mixed $wpError = false): mixed`

A recurring event (wp_schedule_event): refused for an unknown schedule or when a plugin says no.

### static `reschedule(mixed $timestamp, mixed $recurrence, mixed $hook, mixed $args = array ( ), mixed $wpError = false): mixed`

A recurring event moved to its next run (wp_reschedule_event): the
interval its schedule names, else the one the event carries.

### static `unschedule(mixed $timestamp, mixed $hook, mixed $args = array ( ), mixed $wpError = false): mixed`

One event removed (wp_unschedule_event).

### static `unscheduleHook(mixed $hook, mixed $wpError = false): mixed`

Every event of a hook removed, whatever its arguments (wp_unschedule_hook): how many, 0 for none.

### static `clearHook(mixed $hook, mixed $args = array ( ), mixed $wpError = false, mixed ...$loose): mixed`

Every event of a hook with these arguments removed
(wp_clear_scheduled_hook): how many, or the errors merged into one;
arguments given loose (an old call) are taken as the list.

### static `event(mixed $hook, mixed $args = array ( ), mixed $timestamp = NULL): mixed`

An event by hook and arguments, the next one or the one at a time (wp_get_scheduled_event), or false.

### static `next(mixed $hook, mixed $args = array ( )): mixed`

When an event next runs (wp_next_scheduled), as wp_next_scheduled leaves it; false for none.

### static `ready(): mixed`

The events due now (wp_get_ready_cron_jobs).

- `@return array<int|string, mixed>`

### static `run(): mixed`

The due events run (wp_cron): each recurring one rescheduled first,
each removed, then its hook fired with its arguments; how many ran.
A failure to reschedule or remove is logged and told as
cron_reschedule_event_error or cron_unschedule_event_error.

### static `upgrade(mixed $cron): array`

An old event list keyed by hook alone, keyed again by its arguments'
hash and stored (_upgrade_cron_array).

- `@return array<int|string, mixed>`

Internals: `refuse()` (private, line 268), `failed()` (private, line 274)


## CronTable

`final class Minn\Runtime\CronTable` · `public/minn/src/Minn/Runtime/CronTable.php`

The cron option's shape, operated on as data: timestamp => hook => key =>
entry, kept in natural timestamp order. The key is the reference's own
(a hash of the serialized argument list), so both stacks read one table.

Used by: `Minn\Cron\Cron`, `Minn\Ops\Diagnostics`, `Minn\Runtime\CronEvents`

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

Used by: `Minn\Front\ToolbarMenus`, `Minn\Runtime\AdminActionController`, `Minn\Runtime\Attachments`, `Minn\Runtime\BlockQueries`, `Minn\Runtime\CommentPages`, `Minn\Runtime\EditLinks`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\MetaData`, `Minn\Runtime\Plugins`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostSave`, `Minn\Runtime\PostWrites`, `Minn\Runtime\UserCounts`, `Minn\Runtime\UserWrites`

### static `id(): int`

The request's user id, as a number (get_current_user_id casts it).

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

Internals: `definitions()` (private, line 82)


## DebugMode

`final class Minn\Runtime\DebugMode` · `public/minn/src/Minn/Runtime/DebugMode.php`

PHP's error settings from the debug constants, as the reference's boot
sets them (wp_debug_mode(); contracts/fixtures/debug-mode.json holds the
oracle's answer for each combination, and the unit suite replays it).
WP_DEBUG reports every level, or only errors and warnings. With it on,
WP_DEBUG_DISPLAY turns display on or off (null leaves PHP's own setting)
and WP_DEBUG_LOG sends the log to a file. A request that answers a
machine never displays errors.

- const `QUIET` = `4983` — What PHP reports while WP_DEBUG is off: errors and warnings, no notices, strict or deprecation messages.
- const `DEFAULTS` = `array (   'WP_DEBUG' => false,   'WP_DEBUG_DISPLAY' => true,   'WP_DEBUG_LOG' => false, )` — The debug constants, each with the value it has when wp-config.php leaves it out.
- const `REQUESTS` = `array (   0 => 'XMLRPC_REQUEST',   1 => 'REST_REQUEST',   2 => 'MS_FILES_REQUEST',   3 => 'WP_INSTALLING',   4 => 'DOING_AJAX', )` — The request constants answersMachine() reads.

Used by: `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Http\Failure`

### static `settings(array $constants, string $contentDir): array`

The settings the debug constants ask for: the error level, and the ini
values to set (none for what they leave alone).

- `@param array<string, mixed> $constants the debug constants defined, by name; a missing one reads as its default`
- `@return array{level: int, ini: array<string, string>}`

### static `logPath(mixed $setting, string $contentDir): ?string`

Where WP_DEBUG_LOG sends the log. True, 1 or "true" in any case mean
debug.log in the content folder; any other string but "" and "0" is
the path itself, "false" included; anything else keeps no log.

### static `answersMachine(array $constants, array $server): bool`

Whether what the boot knows marks a request that answers a machine,
whose errors must never print into the answer: XML-RPC, REST or
ms-files declared at all, an install or ajax declared true, or a
request that asks for or sends JSON (a JSONP or XML one is not). The
facade's wp_debug_mode() asks the same through wp_installing(),
wp_doing_ajax() and wp_is_json_request(), filters included.

- `@param array<string, mixed> $constants the request constants defined, by name`
- `@param array<string, mixed> $server the request's server variables`

### static `defined(): array`

The debug and request constants defined right now, by name.

- `@return array<string, mixed>`

### static `apply(array $settings): void`

Sets PHP's error level and ini values to what settings() asked for.

- `@param array{level: int, ini: array<string, string>} $settings`


## Deferrals

`final class Minn\Runtime\Deferrals` · `public/minn/src/Minn/Runtime/Deferrals.php`

The switches an importer flips for the length of a request (probe
plugin-helpers): term and comment counts deferred until the switch goes
off again, when what was put off is counted at once, and cache additions
suspended so a bulk read does not fill the cache. Each answers what it
is now; a value that is not a boolean asks without changing it. Kept in
the request's state, so a worker's next request starts with them off.

Used by: `Minn\Runtime\CommentWrites`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TermWrites`

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


## DeprecatedUserMeta

`final class Minn\Runtime\DeprecatedUserMeta` · `public/minn/src/Minn/Runtime/DeprecatedUserMeta.php`

update_usermeta() and delete_usermeta(), deprecated since 3.0.0 and kept
for the plugins that still call them (probes hook-inputs,
nav-pomo-rest-doubts): they work on every row of a key, and their actions
name the key's first row and carry the value as stored.

### static `update(mixed $userId, mixed $metaKey, mixed $metaValue): bool`

A user's meta key set to one value on every row it has (a new row when
it has none; an empty value deletes the key). update_usermeta fires
before a change, updated_usermeta or added_usermeta after the caches
are cleaned; false when the first row already holds the value.

### static `delete(mixed $userId, mixed $metaKey, mixed $metaValue = ''): bool`

A user's meta key deleted: every row, or those holding the value when
one is given (an array serialized, a string trimmed). While the key has
a row, delete_usermeta and deleted_usermeta fire with its first row and
that value, whether or not a row matched; true unless there is no user.

Internals: `key()` (private, line 82), `clean()` (private, line 88)


## DieInput

`final class Minn\Runtime\DieInput` · `public/minn/src/Minn/Runtime/DieInput.php`

wp_die's message, title and arguments made whole, as the die handlers
read them on the reference (probes functions-remaining,
functions-formatting-doubts): an error's first message is the message and
every other one, each with its code's data, an additional error; the
first error's status is the response unless the caller names one, and of
its data only wp_die's own arguments carry over; the caller's code wins
over the error's. A missing response is 500, a missing title the
translated "WordPress &rsaquo; Error", a missing code wp_die, and the
charset the site's unless the caller names one, in its usual spelling.

- const `DEFAULTS` = `array (   'response' => 0,   'code' => '',   'exit' => true,   'back_link' => false,   'link_url' => '',   'link_text' => '',   'text_direction' => 'ltr',   'charset' => 'utf-8',   'additional_errors' =>    array (   ), )`

### static `process(mixed $message, ?array $errors, mixed $title, mixed $args, string $siteCharset, Closure $charset): array`

The message, title and arguments.

- `@param list<array{code: int|string, message: string, data: mixed}>|null $errors the error's messages in order, or null for a plain message`
- `@param Closure(string): string $charset a charset's usual spelling`
- `@return array{0: mixed, 1: mixed, 2: array<string, mixed>}`


## DocumentTitle

`final class Minn\Runtime\DocumentTitle` · `public/minn/src/Minn/Runtime/DocumentTitle.php`

The page's title as WordPress's title functions make it from the main
query: the document title and its parts (what is shown, the page number,
the site or its tagline), and the title tags of a post type archive, a
term archive and a single post, printed or returned.

Used by: `Minn\Front\FeedTags`

### static `get(): string`

The document title (wp_get_document_title), through the title filters, ready to print inside <title>.

### static `parts(): array`

The document title's parts from the main query's state: the title of
what is shown, the page number past the first, then the tagline on
the front page or the site's name elsewhere.

- `@return array<string, mixed>`

### static `postTypeArchive(mixed $prefix = '', mixed $display = true): mixed`

On a post type archive, the type's plural name after a prefix (post_type_archive_title), printed or returned; null elsewhere.

### static `term(mixed $prefix = '', mixed $display = true): mixed`

The queried term's name after a prefix (single_term_title), printed or returned; null off a term archive.

### static `post(mixed $prefix = '', mixed $display = true): mixed`

The queried post's title after a prefix (single_post_title), printed or returned; null when no post is queried.

### static `formatArchive(string $termSlug): ?string`

A post format archive's title: the format's plural (Asides, Galleries),
translated in its own context; null for the standard format and any
other term (the plain "Archives" then).

Internals: `shown()` (private, line 104)


## EarlyFilters

`final class Minn\Runtime\EarlyFilters` · `public/minn/src/Minn/Runtime/EarlyFilters.php`

Filters that run before the runtime exists, over the hooks added that
early: WP-CLI's add_wp_hook writes them into $wp_filter as plain arrays
(tag => priority => id => function and accepted_args). The reference has
its hook API by then; the engine reads the same arrays directly.

Used by: `Minn\Front\Maintenance`, `Minn\Runtime\Runtime`

### static `apply(string $tag, mixed $value, mixed ...$args): mixed`

The value through every early callback on the tag, lowest priority first; as given when there are none.


## EarlyRequest

`final class Minn\Runtime\EarlyRequest` · `public/minn/src/Minn/Runtime/EarlyRequest.php`

The request as WordPress's earliest functions see it, before the runtime
is up: a page cache's advanced-cache.php runs then, as the reference runs
it, ahead of the database, the options and the plugins. What the
reference knows at that point comes from the request and from the
constants its entry files define first (WP_ADMIN for an admin page,
DOING_AJAX for admin-ajax.php, DOING_CRON for wp-cron.php), which the
engine defines from the same rules it boots by (declare()).

Used by: `Minn\Engine`, `Minn\Runtime\Runtime`

### static `declare(Minn\Http\Request $request): void`

Defines the request's kind as the reference's entry file would have before anything loaded.

### static `markThemed(Minn\Http\Request $request): void`

WordPress's index.php says its requests use themes before any plugin
loads; its own scripts (sign-in, the admin, cron, XML-RPC) do not, and
neither does Minn Admin (probe load-remaining).

### static `isAdmin(): bool`

Whether this is an admin request (is_admin): what the entry file said.

### static `secure(): bool`

Whether the request came over HTTPS (is_ssl), as the server reports it.

### static `installing(): bool`

Whether WordPress is installing (wp_installing): what WP_INSTALLING says.


## EditLinks

`final class Minn\Runtime\EditLinks` · `public/minn/src/Minn/Runtime/EditLinks.php`

The admin addresses WordPress's link functions give for editing: a
post, a term, a user and one's own profile (null or empty for someone
who may not), a user's dashboard, and a post's short link; each as its
filter leaves it.

Used by: `Minn\Front\ToolbarMenus`, `Minn\Runtime\AttachmentModel`

### static `post(mixed $post = 0, mixed $context = 'display'): mixed`

The address a post is edited at (get_edit_post_link), null for a post the user may not edit or of no known type.

### static `term(mixed $term, mixed $taxonomy = '', mixed $objectType = ''): mixed`

The address a term is edited at (get_edit_term_link), null for one the user may not edit.

### static `user(mixed $userId = NULL): mixed`

The address a user is edited at (get_edit_user_link): one's own profile for oneself; empty for one the user may not edit.

### static `profile(mixed $userId = 0, mixed $scheme = 'admin'): mixed`

The address a user's profile is edited at (get_edit_profile_url), as edit_profile_url leaves it.

### static `dashboard(mixed $userId = 0, mixed $path = '', mixed $scheme = 'admin'): mixed`

A user's dashboard address, at a path in it (get_dashboard_url), as user_dashboard_url leaves it.

### static `shortlink(mixed $id = 0, mixed $context = 'post', mixed $allowSlugs = true): mixed`

A post's short address (wp_get_shortlink), unless pre_get_shortlink
answers first: the front page's home address, a public post's ?p=,
else empty; the query's post when asked for the query's.

Internals: `me()` (private, line 109)


## FileHeaders

`final class Minn\Runtime\FileHeaders` · `public/minn/src/Minn/Runtime/FileHeaders.php`

The headers at the top of a theme, plugin or translation file, as
WordPress's get_file_data reads them: the first eight kilobytes, each
header by its label (the extra ones extra_{context}_headers adds first).

Used by: `Minn\Runtime\LegacyPack`, `Minn\Runtime\PluginFiles`, `Minn\Runtime\PluginRequirements`, `Minn\Runtime\Plugins`, `Minn\Runtime\Translations`

### static `read(mixed $file, mixed $defaultHeaders, mixed $context = ''): array`

A file's headers by field (get_file_data), empty for a header it does
not carry.

- `@return array<string, string>`


## FileNames

`final class Minn\Runtime\FileNames` · `public/minn/src/Minn/Runtime/FileNames.php`

Files as WordPress's file functions name and remove them: a name made
safe to store, a name no file in the folder has, a file's type from its
content as much as its name, and a file deleted (only inside the folder
it should be in).

- const `SPECIAL_CHARACTERS` = `array (   0 => '?',   1 => '[',   2 => ']',   3 => '/',   4 => '\\',   5 => '=',   6 => '<',   7 => '>',   8 => ':',   9 => ';',   10 => ',',   11 => '\'',   12 => '"',   13 => '&',   14 => '$',   15 => '#',   16 => '*',   17 => '(',   18 => ')',   19 => '|',   20 => '~',   21 => '`',   22 => '!',   23 => '{',   24 => '}',   25 => '%',   26 => '+',   27 => '’',   28 => '«',   29 => '»',   30 => '”',   31 => '“',   32 => '' . "\0" . '', )` — The characters a file name loses, handed to sanitize_file_name_chars in this order.

Used by: `Minn\Runtime\Attachments`, `Minn\Runtime\FileUpload`

### static `sanitize(mixed $filename): mixed`

A file name safe to store (sanitize_file_name): accents transliterated,
the special characters out, spaces and runs of dashes as one dash, no
dots or dashes at the ends; an inner part that looks like an extension
but is no type the site allows gets an underscore; a name that is only
an extension becomes unnamed-file.{ext}; as sanitize_file_name leaves it.

### static `neutralizeInnerExtensions(string $filename): string`

An inner part of a file name that looks like an extension (two to five letters, maybe a digit) and is no allowed type, given an underscore.

### static `unique(mixed $dir, mixed $filename, mixed $callback = NULL): mixed`

A clean name no file in the folder has (wp_unique_filename): the name
sanitized, its extension lowercased, a callback's name when one is
given, else -1, -2... before the extension; as wp_unique_filename
leaves it.

### static `checkUpload(mixed $file, mixed $filename, mixed $mimes = NULL): mixed`

A file's extension and type from its content as much as its name
(wp_check_filetype_and_ext), with a corrected name when an image
carried the wrong extension; as wp_check_filetype_and_ext leaves them.

### static `delete(mixed $file): bool`

A file deleted (wp_delete_file), as wp_delete_file names it; whether it went.

### static `deleteFromDirectory(mixed $file, mixed $directory): bool`

A file deleted only when it is inside the folder (wp_delete_file_from_directory); false when it is not, or not there.


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

Used by: `Minn\Runtime\FileNames`

### static `check(string $file, string $filename, ?array $mimes, array $images): array`

ext, type, proper_filename, and the content's own type (real_mime).

- `@param array<string, string>|null $mimes`
- `@param array<string, string> $images getimagesize_mimes_to_exts`
- `@return array{ext: string|false, type: string|false, proper_filename: string|false, real_mime: string|false}`

Internals: `imageMime()` (private, line 70), `contentFits()` (private, line 76)


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

Used by: `Minn\Rest\FontWritesController`, `Minn\Rest\MediaController`

### static `handle(array $file, array|false $overrides, ?string $time, string $action): array`

The stored file (file, url, type) or ['error' => message].

- `@param array<string, mixed> $file name, type, tmp_name, size, error`
- `@param array<string, mixed>|false $overrides`
- `@return array<string, mixed>`

Internals: `refusal()` (private, line 78), `move()` (private, line 109)


## Handoff

`final class Minn\Runtime\Handoff` · `public/minn/src/Minn/Runtime/Handoff.php`

What the engine asks of the layer it runs under, where a plugin may have
the last word. WordPress lets a plugin define its own version of the
replaceable functions (the current user, users by id or field, hashes,
nonces, passwords, mail, avatars, redirects, signing in) before the
defaults load; its user records are that layer's objects, and it ends a
request with a message its own way (handlers plugins choose); the
WordPress layer binds each answer here (the replaceable ones once the
plugins have had their chance), so a plugin's version answers the engine
as it answers WordPress. Arguments pass as given, so each answer's own
defaults hold.

Used by: `Minn\Auth\AuthCookies`, `Minn\Auth\Can`, `Minn\Auth\Cookie`, `Minn\Auth\Salts`, `Minn\Auth\SignIn`, `Minn\Blocks\Bindings`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Content\PasswordGate`, `Minn\Content\PostWriter`, `Minn\Content\Users`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Front\EntryScriptsController`, `Minn\Front\FeedTags`, `Minn\Front\PostEmbed`, `Minn\Front\SitemapRequest`, `Minn\Front\ToolbarMenus`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Login\LoginNotices`, `Minn\Mail\Mailer`, `Minn\Media\MediaModal`, `Minn\Ops\DirectoryApi`, `Minn\Ops\FilesystemCredentials`, `Minn\Ops\UpgraderRun`, `Minn\Rest\BatchController`, `Minn\Rest\BlockEditorController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\CommentsController`, `Minn\Rest\FontObject`, `Minn\Rest\FontWritesController`, `Minn\Rest\FontsController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenuItemObject`, `Minn\Rest\MenuItemStatus`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RenderedFields`, `Minn\Rest\Responses`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SidebarsController`, `Minn\Rest\SiteSchema`, `Minn\Rest\TemplatesController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetObject`, `Minn\Rest\WidgetsController`, `Minn\Runtime\Abilities`, `Minn\Runtime\AbilityRun`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\AdminActionController`, `Minn\Runtime\AjaxController`, `Minn\Runtime\ApplicationPasswordRecords`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\AssetView`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\BlockFilters`, `Minn\Runtime\BlockHooks`, `Minn\Runtime\BlockQueries`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentPages`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\CommentQueryWhere`, `Minn\Runtime\CommentReads`, `Minn\Runtime\CommentThreads`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\CronEvents`, `Minn\Runtime\CurrentUser`, `Minn\Runtime\DeprecatedUserMeta`, `Minn\Runtime\EditLinks`, `Minn\Runtime\Heartbeat`, `Minn\Runtime\MainQuery`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\Meta`, `Minn\Runtime\MetaData`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenu`, `Minn\Runtime\NavigationFallback`, `Minn\Runtime\OptionWrites`, `Minn\Runtime\Pages`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\Plugins`, `Minn\Runtime\PostCaches`, `Minn\Runtime\PostData`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostInsert`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostQueryWhere`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Runtime\QueryArgs`, `Minn\Runtime\RecipientLocale`, `Minn\Runtime\Records`, `Minn\Runtime\Registry`, `Minn\Runtime\RewriteRules`, `Minn\Runtime\Runtime`, `Minn\Runtime\TaxTerms`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermTags`, `Minn\Runtime\TermWrites`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\Themes`, `Minn\Runtime\Translations`, `Minn\Runtime\Upgrade`, `Minn\Runtime\UserCounts`, `Minn\Runtime\UserEvents`, `Minn\Runtime\UserOrder`, `Minn\Runtime\UserQueryRunner`, `Minn\Runtime\UserSave`, `Minn\Runtime\UserStylesPost`, `Minn\Runtime\UserWrites`, `Minn\Runtime\WidgetAreas`, `Minn\Runtime\WpdbFields`, `Minn\Runtime\WpdbSetup`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\EmbedRenderer`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`, `Minn\Theme\ThemeJsonData`


### static `bind(array $answers): void`

Binds answers by name: currentUser, signedIn, user, userBy,
becomeUser, cacheUsers, authenticate, setPassword, password, hashPassword, hash,
createNonce, verifyNonce, mail, avatar, postClass, redirect, safeRedirect, stop, escSql, cacheGet, cacheSet, cacheDelete, and the record
factories newError, newPost, postInstance, newTerm, newComment,
commentInstance, newUser, newDependency, and the query factories newQuery,
newCommentQuery, newUserQuery, newTermQuery, newMetaQuery, newDateQuery,
and the REST ones newRestRequest, restRequestFromUrl, newRestResponse,
restController, newTheme, and the empty registered-type objects
blankPostType and blankTaxonomy, a user's sessionTokens, the locale's
newLocale and newTextdomainRegistry, newUpgrader and newUpgraderSkin, the
blocks' newBlock, blockChildContext, adoptBlock, newBlockList, newBlockTemplate, newTagProcessor and
newThemeJsonData, newSitemapStylesheet, and the registries blockTypes,
blockPatterns, blockStyles, blockBindings, abilities and
abilityCategories.

- `@param array<string, Closure> $answers`

### static `currentUser(): mixed`

The user the request is (wp_get_current_user).

### static `signedIn(): bool`

Whether someone is signed in (is_user_logged_in).

### static `user(mixed $id): mixed`

A user by id, or false (get_userdata).

### static `userBy(mixed $field, mixed $value): mixed`

A user by a field (id, ID, slug, email or login), or false (get_user_by).

### static `becomeUser(mixed ...$args): mixed`

Makes a user the request's (wp_set_current_user): the user's record.

### static `cacheUsers(mixed $ids): void`

Reads users and their meta into the cache together (cache_users).

### static `authenticate(mixed $username, mixed $password): mixed`

Signs in by name and password (wp_authenticate): the user, or an error.

### static `setPassword(mixed $password, mixed $userId): mixed`

Sets a user's password (wp_set_password).

### static `password(mixed ...$args): mixed`

A random password (wp_generate_password).

### static `hashPassword(mixed $password): mixed`

A password hashed for storing (wp_hash_password).

### static `hash(mixed ...$args): mixed`

A hash of the data keyed with the site's salt (wp_hash).

### static `createNonce(mixed ...$args): mixed`

A nonce for an action (wp_create_nonce).

### static `verifyNonce(mixed ...$args): mixed`

Whether a nonce holds for an action: 1 or 2 by its age, or false (wp_verify_nonce).

### static `mail(mixed ...$args): mixed`

Sends an email (wp_mail): whether it was accepted for delivery.

### static `postClass(mixed ...$args): mixed`

A post's classes, through post_class (get_post_class).

### static `excerpt(mixed ...$args): mixed`

A post's excerpt, through get_the_excerpt (and the excerpt_length and excerpt_more it asks).

### static `postThumbnail(mixed ...$args): mixed`

A post's featured image as an img tag, or '' (get_the_post_thumbnail).

### static `commentTag(string $tag, mixed ...$args): mixed`

A comment block's template tag, as its render callback asks it: the
comment's author, author URL, text through comment_text, date, link,
reply link, classes, or whether the post takes comments.

### static `avatar(mixed ...$args): mixed`

A user's or address's avatar image, or false (get_avatar).

### static `redirect(mixed ...$args): mixed`

Sends the browser elsewhere (wp_redirect): whether it was sent.

### static `safeRedirect(mixed ...$args): mixed`

Sends the browser elsewhere on the site's own hosts, else to the admin (wp_safe_redirect).

### static `stop(mixed ...$args): void`

Ends the request with a message (wp_die), as the handler plugins choose draws it.

### static `cacheGet(mixed $key, mixed $group = '', mixed $force = false, mixed $found = NULL): mixed`

A value from the object cache (wp_cache_get), $found told whether it was there.

### static `cacheSet(mixed ...$args): mixed`

A value stored in the object cache (wp_cache_set).

### static `cacheDelete(mixed ...$args): mixed`

A value removed from the object cache (wp_cache_delete).

### static `escSql(mixed $data): mixed`

A value, or each in a list, escaped for SQL by the database object plugins query with (esc_sql).

### static `newError(mixed ...$args): Minn\Objects\Errors`

A list of errors, the first given (new WP_Error).

### static `newPost(mixed $post): Minn\Objects\Post`

A post object made from a row or another post (new WP_Post).

### static `postInstance(mixed $id): mixed`

The post object for an id, or false (WP_Post::get_instance).

### static `newDependency(mixed ...$args): Minn\Objects\Dependency`

A registered script or style as plugins see it in a registry's view (new _WP_Dependency).

### static `newTerm(mixed $term): Minn\Objects\Term`

A term object made from a row (new WP_Term).

### static `newComment(mixed $comment): Minn\Objects\Comment`

A comment object made from a row (new WP_Comment).

### static `navigationFallback(): ?array`

The menu a navigation naming none falls back to, as a row, made when there is none (WP_Navigation_Fallback::get_fallback).

### static `commentInstance(mixed $id): mixed`

The comment object for an id, or false (WP_Comment::get_instance).

### static `newUser(mixed ...$args): Minn\Objects\User`

A user object by id (or login, with no id), nobody for 0 (new WP_User).

### static `newQuery(mixed ...$args): Minn\Objects\Query`

A posts query, run at once when given its variables (new WP_Query).

### static `newCommentQuery(mixed ...$args): Minn\Objects\CommentsQuery`

A comments query, run at once when given its variables (new WP_Comment_Query).

### static `newUserQuery(mixed ...$args): Minn\Objects\UsersQuery`

A users query, run at once when given its variables (new WP_User_Query).

### static `newTermQuery(mixed ...$args): Minn\Objects\TermsQuery`

A terms query, run at once when given its variables (new WP_Term_Query).

### static `newMetaQuery(mixed ...$args): object`

A meta query from its clauses (new WP_Meta_Query).

### static `newDateQuery(mixed ...$args): object`

A date query from its clauses and column (new WP_Date_Query).

### static `newRestRequest(mixed ...$args): Minn\Objects\RestRequest`

A REST request for a method and route (new WP_REST_Request).

### static `restRequestFromUrl(mixed $url): mixed`

A REST request for an address on this site's API, or false (WP_REST_Request::from_url).

### static `newRestResponse(mixed ...$args): Minn\Objects\RestResponse`

A REST response with data, a status and headers (new WP_REST_Response).

### static `restController(string $kind, mixed ...$args): object`

One of the REST controllers a filter is handed, by kind: menus, menu-items or menu-locations.

### static `blankPostType(): Minn\Objects\PostType`

A post type's object with nothing set yet, for the registry to fill (WP_Post_Type, built without its constructor).

### static `blankTaxonomy(): Minn\Objects\Taxonomy`

A taxonomy's object with nothing set yet, for the registry to fill (WP_Taxonomy, built without its constructor).

### static `sessionTokens(mixed $userId): Minn\Objects\SessionTokens`

A user's sessions (WP_Session_Tokens::get_instance), through the manager session_token_manager names.

### static `newLocale(): Minn\Objects\SiteLocale`

The site's locale: its names, number format and direction (new WP_Locale).

### static `newTextdomainRegistry(): object`

Where each text domain's translations are found (new WP_Textdomain_Registry).

### static `newUpgrader(mixed $kind, mixed $skin): Minn\Objects\Upgrader`

An upgrader for plugins, or for themes when the kind is "theme", reporting through the skin.

### static `newUpgraderSkin(mixed ...$args): Minn\Objects\UpgraderSkin`

The skin an upgrader reports through when no screen shows it (new WP_Ajax_Upgrader_Skin).

### static `newBlock(mixed ...$args): Minn\Objects\RenderedBlock`

A block to render, with its context (new WP_Block).

### static `blockChildContext(Minn\Objects\RenderedBlock $block): array`

The context a block's inner blocks are made with: its own and what its type provides (WP_Block's, for its WP_Block_List).

### static `adoptBlock(Minn\Objects\RenderedBlock $block, array $parsed, array $context): void`

A block made inside its parent, given what render_block_data and render_block_context made of it (WP_Block's own, as its parent renders it).

### static `newBlockList(mixed ...$args): object`

Blocks to render as one list (new WP_Block_List).

### static `newBlockTemplate(mixed ...$args): object`

An empty block template record (new WP_Block_Template).

### static `newTagProcessor(mixed ...$args): object`

A cursor over HTML's tags and attributes (new WP_HTML_Tag_Processor).

### static `newThemeJsonData(mixed ...$args): Minn\Objects\ThemeJsonLayer`

One origin's theme.json data for its filter (new WP_Theme_JSON_Data).

### static `newSitemapStylesheet(mixed ...$args): object`

The stylesheet a sitemap is shown with (new WP_Sitemaps_Stylesheet).

### static `blockTypes(mixed ...$args): object`

The registered block types (WP_Block_Type_Registry::get_instance).

### static `blockPatterns(mixed ...$args): object`

The registered block patterns (WP_Block_Patterns_Registry::get_instance).

### static `blockStyles(mixed ...$args): object`

The registered block styles (WP_Block_Styles_Registry::get_instance).

### static `blockBindings(mixed ...$args): object`

The registered block binding sources (WP_Block_Bindings_Registry::get_instance).

### static `abilities(mixed ...$args): object`

The registered abilities (WP_Abilities_Registry::get_instance).

### static `abilityCategories(mixed ...$args): object`

The registered ability categories (WP_Ability_Categories_Registry::get_instance).

### static `bound(string $name): bool`

Whether the layer has bound an answer by that name yet (it binds them as it loads).

### static `roles(): object`

The site's roles object (wp_roles), made the first time it is asked for.

### static `resetKses(): void`

The content filters set again for the current user (kses_remove_filters, then kses_init_filters), by the names plugins remove them by.

### static `loadTemplate(mixed ...$args): void`

A theme template file run where templates run (load_template): in the layer's globals, the query's variables in scope.

### static `translationController(): object`

The object that loads translation files and looks translations up (WP_Translation_Controller::get_instance).

### static `rewrite(): object`

The rewrite object ($wp_rewrite), with the request's environment ($wp), made the first time they are asked for.

### static `widgetFactory(): object`

The widget factory ($wp_widget_factory) the registered widgets live in.

### static `sitemaps(): object`

The sitemap server, started the first time it is asked for (wp_sitemaps_get_server).

### static `newMenuWalker(): object`

The walker a menu is printed with when its arguments name none (new Walker_Nav_Menu).

### static `attachmentMetadata(mixed $attachmentId, mixed $file): mixed`

An attachment's metadata made from its file by the layer's image editors (wp_generate_attachment_metadata): sizes, previews, the file's size.

### static `uploadBits(mixed $name, mixed $bits): mixed`

A file of these bytes written into this month's uploads folder by the layer (wp_upload_bits): its path, address and type, or the error.

### static `registerInitMeta(): void`

The meta core and Minn Admin register on init, registered by the layer that holds their callbacks by name.

### static `queryTemplate(mixed ...$args): mixed`

The theme file for a template type, through the hierarchy and its filters (get_query_template); a block theme's canvas when it has a block template.

### static `lazyloadTermMeta(array $ids): void`

Terms queued so the first read of one's meta loads them all (wp_lazyload_term_meta). @param list<int> $ids

- `@param list<int> $ids`

### static `convertSmilies(mixed $text): mixed`

Text smilies as their emoji or images (convert_smilies).

### static `blockTemplates(mixed ...$args): mixed`

The block templates a query finds, the theme's and the ones plugins registered (get_block_templates).

### static `blockHooks(mixed ...$args): mixed`

Content with its hooked blocks inserted by the callback named (apply_block_hooks_to_content).

### static `blockHooksForPost(mixed $content, mixed $post, mixed $callback, mixed $ignoredAtRoot): mixed`

A post's content with its hooked blocks inserted
(apply_block_hooks_to_content_from_post_object), a navigation's root
ignored list filled in.

### static `newTheme(mixed ...$args): Minn\Objects\Theme`

A theme by its folder and root (new WP_Theme).

Internals: `answer()` (private, line 592)


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

Used by: `Minn\Runtime\PluginActivation`, `Minn\Runtime\Runtime`


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

### `has(string $hook, callable|array|string|false $callback = false, string|int|false $priority = false): int|bool`

With a callback: its lowest priority, or false; without: whether anything is registered.
A callback and a whole-number priority ask whether it is hooked at that one; a priority as a
string answers as none does (probe hook-priority).

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

### `doAction(string $hook, array $args): void`

Runs an action plugin code fires (do_action): a first argument that is a
list of one object (PHP 4's array( &$this )) hands its callbacks the
object itself, as the reference does (MailPoet fires its automation
hook so), then as action().

- `@param list<mixed> $args`

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

### `filterDeprecated(string $hook, array $args, mixed $version, mixed $replacement = '', mixed $message = ''): mixed`

apply_filters_deprecated: the value as given while nothing is hooked;
otherwise the deprecation notice, then the filter as filterRefArray
runs it.

- `@param array<array-key, mixed> $args the value first`

### `actionDeprecated(string $hook, array $args, mixed $version, mixed $replacement = '', mixed $message = ''): void`

do_action_deprecated: nothing while nothing is hooked; otherwise the
deprecation notice, then the action as actionRefArray runs it.

- `@param array<array-key, mixed> $args`

### `actionsDone(string $hook): int`

How often an action has run.

### `filtersDone(string $hook): int`

How often a filter has run.

### `current(): string|false`

The hook running now, or false.

### `doing(?string $hook): bool`

Whether a hook, or any hook, is running.

### `names(string $prefix): array`

The hooks with callbacks whose names start with a prefix, the prefix
left off (the admin actions plugins answer: "admin_action_").

- `@return list<string>`

### `registered(): array`

Every hook with callbacks, by name.

- `@return array<string, array<int, list<callable>>> a read-only view for diagnostics`

### `callAll(array $args): void`

The 'all' callbacks run with the arguments given, the hook's name first (_wp_call_all_hook). @param list<mixed> $args

- `@param list<mixed> $args`

Internals: `run()` (private, line 351), `nextPriority()` (private, line 400), `fireAll()` (private, line 420), `id()` (private, line 432)


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


### `whenRouterRegion(Closure $callback): void`

What to run when processing meets a router region.

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

Internals: `namespace()` (private, line 144), `tokenize()` (private, line 155), `walk()` (private, line 215), `applyDirectives()` (private, line 277), `bind()` (private, line 339), `interactiveNamespace()` (private, line 365), `pushContext()` (private, line 378), `evaluate()` (private, line 395), `expandEach()` (private, line 436), `markChildren()` (private, line 470), `attributeMap()` (private, line 502), `relative()` (private, line 515), `escape()` (private, line 520), `camel()` (private, line 525), `follow()` (private, line 536)


## KsesRules

`final class Minn\Runtime\KsesRules` · `public/minn/src/Minn/Runtime/KsesRules.php`

The HTML WordPress's kses functions let through: the elements and
attributes allowed in a context (data/kses.json, as wp_kses_allowed_html
leaves them), content filtered to them, one attribute judged, and the
null bytes taken out.

Used by: `Minn\Blocks\Bindings`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Runtime\Attachments`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\OptionSanitizer`, `Minn\Runtime\PluginFiles`, `Minn\Runtime\PostData`, `Minn\Runtime\Shortcodes`, `Minn\Theme\ThemeHeaders`, `Minn\Widgets\WidgetTemplates`


### static `table(): array`

The tables the contexts start from: post, data (the comment and
description rules) and entities.

- `@return array<string, mixed>`

### static `allowedHtml(mixed $context = ''): mixed`

The elements and attributes allowed in a context
(wp_kses_allowed_html): post, a user's description, strip, entities,
the comment rules for any other, or an explicit list; as
wp_kses_allowed_html leaves them.

### static `filter(mixed $content, mixed $allowedHtml, mixed $allowedProtocols = array ( )): string`

Content filtered to the HTML allowed (wp_kses), with the site's
protocols unless others are given. A context given as another scalar
is read as text, as wp_kses_data's current_filter() is false outside
a filter.

### static `galleryTags(array $atts): array`

A gallery's item, caption and icon tags (gallery_shortcode): each
through tag_escape, and one a post may not hold (wp_kses_allowed_html,
asked once) replaced by the classic list's dl, dd and dt.

- `@param array<string, mixed> $atts`
- `@return array{itemtag: string, captiontag: string, icontag: string}`

### static `post(mixed $data): string`

Content filtered to what a post may hold (wp_kses_post).

### static `oneAttribute(mixed $attr, mixed $element): string`

One attribute judged as a post's element would carry it (wp_kses_one_attr).

### static `withoutNull(mixed $content, mixed $options = NULL): string`

Content without its null bytes (wp_kses_no_null), or without any control characters when slash_zero is kept.

### static `policy(array|string $allowedHtml, array $allowedProtocols, ?string $content = NULL): Minn\Support\KsesPolicy`

A caller's allowed HTML in any of its shapes, with the protocols and
URI attributes in force. For content, the URI attributes are asked
only when an opening tag of an allowed element that may carry
attributes is there, as the reference asks them of each such tag;
otherwise none are consulted.

- `@param array<string, mixed>|string $allowedHtml`
- `@param array<int, string> $allowedProtocols`


## LegacyPack

`final class Minn\Runtime\LegacyPack` · `public/minn/src/Minn/Runtime/LegacyPack.php`

WordPress's own browser files, for the plugins whose screens are built on
them: a page builder's editor loads WordPress's media library, its classic
editor and the libraries under them, by the addresses WordPress serves
them from (/wp-includes/js/..., /wp-admin/css/...), and some of that code
names those addresses itself.

The engine carries none of them. They are GPL, and they come with the
Minn Legacy Pack, a plugin the site installs like any other (the way a
site installs the script pack, ScriptPack), which keeps them under the
same paths and registers their handles. While the pack is active the
engine answers those addresses from it, ahead of loading any plugin, so a
page that loads a hundred of them stays fast. A file the engine serves
itself (its jQuery, its embed scripts) is not in the pack and still comes
from the engine.

The pack is published on GitHub and delivered through the Minn update
service like any plugin that publishes its own releases. Where a site runs
a plugin whose editor is known to need it (EDITORS, and what plugins add
through minn_legacy_pack_editors) and the pack is not active, Minn Admin
offers to install it, and the wp-admin doorway sends a click on that
editor to the offer rather than to an editor that cannot load.

- const `PLUGIN` = `'minn-legacy-pack/minn-legacy-pack.php'` — The pack, as the active plugins list names it.
- const `REPOSITORY` = `'austinginder/minn-legacy-pack'` — Where the pack is published, as its Update URI names it.
- const `EDITORS` = `array (   'elementor/elementor.php' =>    array (     'name' => 'Elementor',     'action' => 'elementor',   ),   'brizy/brizy.php' =>    array (     'name' => 'Brizy',     'action' => 'in-front-editor',   ),   'bb-plugin/fl-builder.php' =>    array (     'name' => 'Beaver Builder',     'query' => 'fl_builder',   ),   'beaver-builder-lite-version/fl-builder.php' =>    array (     'name' => 'Beaver Builder',     'query' => 'fl_builder',   ),   'bricks' =>    array (     'name' => 'Bricks',     'query' => 'bricks=run',     'theme' => true,   ), )` — The editors proven to open on Minn with the pack and not without it,
by the plugin (its file) or theme (its folder, with `theme`) that
brings them: the editor's name, and what opens it, a wp-admin action
or a query on the page it edits (`name`, or `name=value` for that
value only).
- const `WANTED` = `'minn_legacy_pack_wanted'` — Where noteScripts keeps the plugins and themes whose public pages need the pack.
- const `KEPT_FOR` = `2592000` — How long a plugin noteScripts saw needing the pack is still offered it without being seen again.
- const `NOT_CARRIED` = `'#^(?:prototype|scriptaculous(?:-[a-z]+)?|wp-tinymce(?:-[a-z]+)?)$#'` — WordPress's own scripts the pack does not carry either (nor does the engine): a page needing them is not helped by it.
- const `PATH` = `'#^/(?:wp-includes/(?:js|css|images|fonts)|wp-admin/(?:js|css|images))/[A-Za-z0-9._/-]+$#'`
- const `TYPES` = `array (   'js' => 'application/javascript',   'css' => 'text/css',   'png' => 'image/png',   'gif' => 'image/gif',   'jpg' => 'image/jpeg',   'jpeg' => 'image/jpeg',   'svg' => 'image/svg+xml',   'webp' => 'image/webp',   'ico' => 'image/x-icon',   'woff' => 'font/woff',   'woff2' => 'font/woff2',   'ttf' => 'font/ttf',   'eot' => 'application/vnd.ms-fontobject',   'json' => 'application/json',   'html' => 'text/html; charset=UTF-8',   'txt' => 'text/plain; charset=UTF-8', )`

Used by: `Minn\Admin\BootPayload`, `Minn\Admin\LegacyPackController`, `Minn\Engine`, `Minn\Front\FrontController`, `Minn\Runtime\AdminActionController`, `Minn\Runtime\AssetQueue`

### static `active(): bool`

Whether the pack is active on the site the runtime serves.

### static `status(): array`

The pack's state for the app: whether it is installed and active; the
editors of the active plugins and theme that need it, each with the
action or query that opens it ('' for the one it has not); and the
active plugins and theme whose scripts were seen needing it on the
public site (noteScripts), with the scripts they needed and a page
that showed it.

- `@return array{installed: bool, active: bool, editors: list<array{plugin: string, theme: string, name: string, action: string, query: string}>, wanted: list<array{plugin: string, theme: string, name: string, handles: list<string>, page: string, seen: int}>}`

### static `noteScripts(Minn\Runtime\Assets $scripts, array $lined): void`

Notes, on a public page while the pack is not active, the plugins and
the theme whose scripts lined up for printing need one of WordPress's
own scripts that the engine carries no file for and the pack does
(WooCommerce's variation form needs wp-util, its block cart
wp-element and the rest). Such a script prints and fails in the
visitor's browser. Each is kept with the scripts it needed and a page
that showed it, at most once a day, for Minn Admin to offer the pack.

- `@param list<string> $lined the handles lined up for printing`

### static `wantedFor(string $action): ?array`

The editor a wp-admin action opens that needs the pack while it is not active, or null. @return array{plugin: string, theme: string, name: string, action: string, query: string}|null

- `@return array{plugin: string, theme: string, name: string, action: string, query: string}|null`

### static `wantedOn(array $query): ?array`

The editor a page's query opens that needs the pack while it is not
active (Beaver Builder's ?fl_builder, Bricks' ?bricks=run), or null.

- `@param array<string, mixed> $query`
- `@return array{plugin: string, theme: string, name: string, action: string, query: string}|null`

### static `offer(array $editor, Minn\Front\Permalinks $permalinks, Minn\Http\Request $request): Minn\Http\Response`

Minn Admin's offer to install the pack for an editor, with the way
back to the page that opens it: `?legacy-pack=<action or query>&return=<page>`.

- `@param array{action: string, query: string} $editor`

### static `claims(string $path): bool`

Whether a path is one of the addresses WordPress serves its browser files from.

### static `file(string $contentDir, string $path, array $activePlugins): ?string`

The file in the active pack a path names, or null: the pack is not
active, the file is not in it, or its type is not one a browser loads.

- `@param list<mixed> $activePlugins the active_plugins option`

### static `response(string $file): Minn\Http\Response`

The file as the answer to the request for it.

Internals: `owner()` (private, line 177), `editors()` (private, line 196), `seenWanting()` (private, line 224), `wantedWhere()` (private, line 298)


## MainQuery

`final class Minn\Runtime\MainQuery` · `public/minn/src/Minn/Runtime/MainQuery.php`

The query the request answers ($wp_query), as WordPress keeps it: the
main query ($wp_the_query) unless a plugin swapped in another, an empty
one made when there is none yet.

Used by: `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Front\CustomLogo`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\SitemapController`, `Minn\Front\SitemapRequest`, `Minn\Runtime\AttachmentImages`, `Minn\Runtime\BlockQueries`, `Minn\Runtime\CommentPages`, `Minn\Runtime\DocumentTitle`, `Minn\Runtime\EditLinks`, `Minn\Runtime\LegacyPack`, `Minn\Runtime\PostData`, `Minn\Runtime\TemplateFiles`, `Minn\Theme\ClassicContent`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\PageRenderer`

### static `current(): Minn\Objects\Query`

The query being answered: $wp_query, else the main one, made empty when there is none.

### static `searchQuery(mixed $escaped = true): mixed`

The search terms the query answers (get_search_query), as get_search_query leaves them, escaped for an attribute unless asked not to be.


## MenuEvents

`final readonly class Minn\Runtime\MenuEvents` · `public/minn/src/Minn/Runtime/MenuEvents.php`

Menus and their items saved as the reference saves them, telling
plugins what it tells them (probe rest-menu-save). A menu is a nav_menu
term: made or changed through wp_insert_term / wp_update_term (the term
actions), then wp_create_nav_menu or wp_update_nav_menu; deleted, its
items go through wp_delete_post, the term through wp_delete_term, then
wp_delete_nav_menu. An item is written by the engine, with the post
save actions around it, then wp_add_nav_menu_item (a new one) and
wp_update_nav_menu_item, handed the reference's menu-item-* arguments.

- const `ITEM_DEFAULTS` = `array (   'menu-item-db-id' => 0,   'menu-item-object-id' => 0,   'menu-item-object' => '',   'menu-item-parent-id' => 0,   'menu-item-position' => 0,   'menu-item-type' => 'custom',   'menu-item-title' => '',   'menu-item-url' => '',   'menu-item-description' => '',   'menu-item-attr-title' => '',   'menu-item-target' => '',   'menu-item-classes' => '',   'menu-item-xfn' => '',   'menu-item-status' => '',   'menu-item-post-date' => '',   'menu-item-post-date-gmt' => '', )` — The arguments an item is saved with, and their defaults, as the reference lists them.

```php
__construct(Minn\Content\Menus $menus, Minn\Content\Posts $posts)
```


### `saveMenu(int $menuId, array $data): Minn\Objects\Errors|int`

wp_update_nav_menu_object: a menu made (menu id 0) or changed, its id
or the refusal. The name is checked first, as the reference checks it.

- `@param array<string, mixed> $data menu-name, description`

### `autoAddPage(Minn\Objects\Post $post, string $newStatus, string $oldStatus): void`

_wp_auto_add_pages_to_menu: a top-level page just published joins each
menu set to add new pages (nav_menu_options auto_add) that does not
hold it already, through wp_update_nav_menu_item.

### `deleteMenu(int $menuId): mixed`

wp_delete_nav_menu: the items through wp_delete_post, the term through wp_delete_term (what it gave, unless true), the locations let go, then wp_delete_nav_menu.

### `saveItem(int $menuId, int $itemId, array $data, string $afterInsert = 'now'): Minn\Objects\Errors|int`

wp_update_nav_menu_item: an item made (id 0) or changed in a menu, its
id or the refusal; the post save actions around the write, then the
item actions, and wp_after_insert_post "now" or (a REST save, which
fires it after its own actions) "later".

- `@param array<string, mixed> $data menu-item-* arguments`

### `restMenu(int $menuId, array $fields, Minn\Http\Request $request): int`

A menu saved over REST, as the reference's menus controller saves one:
the request's fields (name, description, those given) through
rest_pre_insert_nav_menu, then saveMenu(), then rest_insert_nav_menu
and rest_after_insert_nav_menu.

- `@param array<string, mixed> $fields name, description`

### `restDeleteMenu(int $menuId, array $previous, Minn\Http\Request $request): void`

A menu deleted over REST: deleteMenu(), then rest_delete_nav_menu with the menu as it was and the response. @param array<string, mixed> $previous

- `@param array<string, mixed> $previous`

### `restItem(int $itemId, array $args, Minn\Http\Request $request): int`

An item saved over REST, as the reference's menu-items controller saves
one: its menu-id and menu-item-* arguments through
rest_pre_insert_nav_menu_item, then saveItem(), then
rest_insert_nav_menu_item, rest_after_insert_nav_menu_item and
wp_after_insert_post.

- `@param array<string, mixed> $args menu-id and menu-item-* arguments, the item's own filled in for an update`

### `restDeleteItem(int $itemId, array $previous, Minn\Http\Request $request): void`

An item deleted over REST: wp_delete_post, then rest_delete_nav_menu_item with the item as it was and the response. @param array<string, mixed> $previous

- `@param array<string, mixed> $previous`

### `savedArgs(int $itemId): array`

An item's own saved values as menu-item-* arguments, which a REST
update starts from: the reference's save resets to its default any
argument it isn't handed.

- `@return array<string, mixed>`

Internals: `holds()` (private, line 92), `prepared()` (private, line 214), `fields()` (private, line 261)


## MenuItemMarks

`final class Minn\Runtime\MenuItemMarks` · `public/minn/src/Minn/Runtime/MenuItemMarks.php`

The classes and current flags the reference gives a menu's items for the
page in view (_wp_menu_item_classes_by_context, probe plugin-queue4), in
two passes over the items.

First, each item: its own classes, then menu-item and its type and
object; menu-item-home and menu-item-privacy-policy for those pages. On a
single post of a flat type, the items of the post's terms in hierarchical
taxonomies become its menu parents. Otherwise the current item (the post
or term in view, the posts page on the blog, the post type archive in
view, or a custom address equal to the request's) is marked, with the
page compat tokens; its menu ancestors, its menu parent and its object's
parent are noted. A custom item pointing home is the home item on every
view. The posts page reads as a parent on any view but a page.

Then each item again: an item for a post or term above the one in view
is its ancestor (current-{type}-ancestor), the noted items take
current-menu-ancestor and current-menu-parent, an item whose object is a
noted parent object current-{object}-parent, and a page item the compat
tokens of both.

Used by: `Minn\Runtime\NavMenu`, `Minn\Runtime\NavigationFallback`

### static `apply(array $items): array`

The items, each given its classes and its current, current_item_parent
and current_item_ancestor flags for what the main query has in view.

- `@param list<object> $items`
- `@return list<object>`

Internals: `own()` (private, line 67), `related()` (private, line 115), `isCurrent()` (private, line 147), `view()` (private, line 165), `postTerms()` (private, line 204), `ancestors()` (private, line 222), `currentUrl()` (private, line 227)


## Meta

`final readonly class Minn\Runtime\Meta` · `public/minn/src/Minn/Runtime/Meta.php`

The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need.

- const `TABLES` = `array (   'post' =>    array (     0 => 'postmeta',     1 => 'post_id',     2 => 'meta_id',   ),   'user' =>    array (     0 => 'usermeta',     1 => 'user_id',     2 => 'umeta_id',   ),   'term' =>    array (     0 => 'termmeta',     1 => 'term_id',     2 => 'meta_id',   ),   'comment' =>    array (     0 => 'commentmeta',     1 => 'comment_id',     2 => 'meta_id',   ), )`

Used by: `Minn\Runtime\DeprecatedUserMeta`, `Minn\Runtime\MetaData`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TermWrites`, `Minn\Runtime\UserWrites`

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

### `keys(string $type): array`

Every meta key an object type uses, once each, in key order. @return list<string>

- `@return list<string>`

### `listing(string $type, int $objectId): array`

Every row of an object's meta by key, then id, as the post meta box
lists them: the key, the value, the row's id and the object's, all as
stored (strings).

- `@return list<array<string, string>>`

### `rowsOf(string $type, int $objectId): array`

Every row of an object's meta in id order, for removing them one by one. @return list<array{meta_id: int, meta_key: string, meta_value: string}>

- `@return list<array{meta_id: int, meta_key: string, meta_value: string}>`

### `byId(string $type, int $metaId): ?array`

One meta row by its id, under the table's own column names, values as stored; null when there is none. @return array<string, string>|null

- `@return array<string, string>|null`

### `columns(string $type): array`

The column holding the object's id, and the one holding the row's: post_id and meta_id, user_id and umeta_id. @return array{0: string, 1: string}

- `@return array{0: string, 1: string}`

### `rewrite(string $type, int $metaId, string $key, string $stored): bool`

Sets one row's key and stored value; false when the database door refused the text.

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

### `updateRows(string $type, array $ids, string $stored): bool`

Sets the value of the given meta rows; false when the database door refused the text.

### `find(string $type, ?int $objectId, string $key, ?string $stored): array`

The rows a delete would take: by key, for one object or all, optionally only a stored value. @return list<array{meta_id: int, object_id: int}>

- `@return list<array{meta_id: int, object_id: int}>`

### `objectsBelow(string $type, string $key, int $bound): array`

The objects whose value under the key is a number below the bound, lowest id first. @return list<int>

- `@return list<int>`

### `deleteRows(string $type, array $ids): void`

Deletes the given meta rows.

- `@param list<int> $ids`

Internals: `spec()` (private, line 20)


## MetaData

`final class Minn\Runtime\MetaData` · `public/minn/src/Minn/Runtime/MetaData.php`

Metadata as WordPress's meta functions read and write it, for posts,
terms, comments, users and any type a plugin's table names: read through
get_{type}_metadata and the object cache (a value its default when unset),
written and removed with the filters that may answer first and the actions
around each change (post meta also told as postmeta), values sanitized and
stored serialized.

Used by: `Minn\Content\PostWriter`, `Minn\Content\Users`, `Minn\Front\CustomLogo`, `Minn\Front\FeedTags`, `Minn\Rest\FontWritesController`, `Minn\Rest\MediaController`, `Minn\Rest\RestMeta`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\AttachmentImages`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\Attachments`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostFields`, `Minn\Runtime\PostLocks`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermWrites`, `Minn\Runtime\Translations`, `Minn\Runtime\UserWrites`

### static `get(mixed $type, mixed $objectId, mixed $key = '', mixed $single = false): mixed`

A value, a key's values, or every key's (get_metadata), the default when the key is unset.

### static `raw(mixed $type, mixed $objectId, mixed $key = '', mixed $single = false): mixed`

What is stored (get_metadata_raw): false for no type or object, null
for an unknown type or an unset key; get_{type}_metadata may answer
first; else the object's meta, read once into the cache.

### static `fallback(mixed $type, mixed $objectId, mixed $key, mixed $single = false): mixed`

An unset key's value (get_metadata_default): empty, or an empty list, as default_{type}_metadata leaves it.

### static `exists(mixed $type, mixed $objectId, mixed $key): bool`

Whether a key is set for an object (metadata_exists), get_{type}_metadata answering first.

### static `add(mixed $type, mixed $objectId, mixed $key, mixed $value, mixed $unique = false): mixed`

A value added (add_metadata): its id, or false; refused when unique and the key is already set.

### static `update(mixed $type, mixed $objectId, mixed $key, mixed $value, mixed $previous = ''): mixed`

A value changed (update_metadata): every row of the key, or only those
holding the previous value given; a key not stored yet is added from
the value as it was handed in (and the new row's id answers).

### static `delete(mixed $type, mixed $objectId, mixed $key, mixed $value = '', mixed $all = false): bool`

Values removed (delete_metadata): the key's, or only those holding the value given; for every object when all are asked.

### static `isProtected(mixed $key, mixed $type = ''): mixed`

Whether a key is private to code (is_protected_meta): one starting with an underscore, as the filter leaves it.

### static `sanitize(mixed $key, mixed $value, mixed $objectType, mixed $subtype = ''): mixed`

A value as its key's sanitizer leaves it (sanitize_meta), the subtype's own sanitizer first when one is hooked.

### static `table(string $type): ?array`

A meta type's table, or null when there is none: the built-in types,
and a plugin's type by the table it set on $wpdb as "{type}meta".

- `@return list<string>|null`

### static `userOption(mixed $option, mixed $user = 0, mixed $deprecated = ''): mixed`

A user's option (get_user_option): the site's prefixed key first, then
the bare one; false for nobody or neither; as get_user_option_{option}
leaves it.

### static `byMid(mixed $type, mixed $metaId): mixed`

One meta row by its id (get_metadata_by_mid): an object under the
table's own column names, the value decoded, unless
get_{type}_metadata_by_mid answers first; false when there is none.

### static `updateByMid(mixed $type, mixed $metaId, mixed $value, mixed $key = false): bool`

One meta row rewritten by its id (update_metadata_by_mid), its key too
when one is given, with the update actions for that row, unless
update_{type}_metadata_by_mid answers first.

### static `deleteByMid(mixed $type, mixed $metaId): bool`

One meta row removed by its id (delete_metadata_by_mid), with the
delete actions for that row (posts and comments also told by their
older names, with the row id alone), unless
delete_{type}_metadata_by_mid answers first.

### static `ofPost(mixed $postId, mixed $key = '', mixed $single = false): mixed`

A post's meta (get_post_meta).

### static `updatePost(mixed $postId, mixed $key, mixed $value, mixed $previous = ''): mixed`

A post's meta changed (update_post_meta).

### static `updateUser(mixed $userId, mixed $key, mixed $value, mixed $previous = ''): mixed`

A user's meta changed (update_user_meta).

### static `postCustom(mixed $postId = 0): mixed`

Every key of a post's meta (get_post_custom), the post being read's when none is named.

### static `primeCache(mixed $type, mixed $objectIds): mixed`

Objects' meta read into the cache together (update_meta_cache): their
column asked of sanitize_key, then update_{type}_metadata_cache, which
a plugin may answer first; false for no type or no ids.

### static `primeCheck(string $type, array $ids): mixed`

What update_{type}_metadata_cache answers before objects' meta is read
into the cache, as update_meta_cache asks it (the column through
sanitize_key first): null to read it; any other answer stands for the
read.

- `@param list<int> $ids`

Internals: `column()` (private, line 395), `store()` (private, line 400), `announce()` (private, line 406), `rows()` (private, line 414)


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

Used by: `Minn\Auth\Can`, `Minn\Rest\RestMeta`, `Minn\Runtime\MetaData`

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

Internals: `refusal()` (private, line 187), `defaultFits()` (private, line 205)


## MetaTypes

`final class Minn\Runtime\MetaTypes` · `public/minn/src/Minn/Runtime/MetaTypes.php`

Meta types a plugin brought, by the table it named on $wpdb as
"{$type}meta" (WooCommerce's order items keep theirs in
woocommerce_order_itemmeta). The reference reads any such table with
"{$type}_id" for the object and meta_id for the row; Meta does the same.

Used by: `Minn\Runtime\Meta`, `Minn\Runtime\MetaData`


### static `register(string $type, string $table): bool`

Registers a meta type by its full table name; refused unless both are plain identifiers.

### static `table(string $type): ?string`

The full table name of a registered meta type, or null.


## NavMenu

`final class Minn\Runtime\NavMenu` · `public/minn/src/Minn/Runtime/NavMenu.php`

wp_nav_menu()'s menu: the one the arguments name, its items marked for
the page in view (MenuItemMarks), walked, wrapped in the container.

Used by: `Minn\Rest\MenusController`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\NavigationFallback`, `Minn\Runtime\ThemeSwitch`, `Minn\Widgets\WidgetForms`


### static `menus(mixed $args = array ( )): mixed`

Every menu (wp_get_nav_menus): the nav_menu terms by name, empty ones too, as wp_get_nav_menus leaves them.

### static `menu(mixed $menu): mixed`

A menu by its term, id, slug or name (wp_get_nav_menu_object), or false, as the filter leaves it.

### static `items(mixed $menu, mixed $args = array ( )): mixed`

A menu's items (wp_get_nav_menu_items), or false for no menu.

### static `walk(mixed $items, mixed $depth, mixed $args): mixed`

A menu's items printed through the walker its arguments name, else the default (walk_nav_menu_tree).

### static `locations(): array`

The menu set for each of the theme's locations (get_nav_menu_locations).

- `@return array<string, mixed>`

### static `build(object $args): string|false|null`

The whole menu for wp_nav_menu(): resolve, fetch, decorate, walk,
wrap, filter. Null when there is no menu (the caller's fallback
runs); false when the items filtered away to nothing.

Internals: `menuForArgs()` (private, line 96), `wrapId()` (private, line 114), `container()` (private, line 129)


## NavMenuItems

`final class Minn\Runtime\NavMenuItems` · `public/minn/src/Minn/Runtime/NavMenuItems.php`

Classic menu items as the reference serves them to walkers and plugins
(probe nav-menu-items). wp_setup_nav_menu_item dresses a nav_menu_item
post with the fields walkers read: its _menu_item_* meta (ids as
strings), kept when already set; then by its kind the label, address
and title of what it points at (a post's title through the_title, every
time, the item's own title winning when it has one; a post or term
gone, or a post trashed, marks it _invalid; a post not published is
labelled by its status); its title attribute and description through
their filters. A post or term of another kind is dressed as a would-be
item, as the admin's boxes use it: id 0, its own title, address and
parent. wp_get_nav_menu_items reads a menu's items through get_posts,
sets each up, drops the invalid ones outside the admin, and for ARRAY_A
output sorts them by the output key and numbers that key from 1, all
before its filter.

Used by: `Minn\Rest\MenuItemObject`, `Minn\Runtime\NavMenu`

### static `prepare(mixed $item): mixed`

A menu item made ready to print (wp_setup_nav_menu_item), unless pre_wp_setup_nav_menu_item answers first.

### static `setUp(mixed $item): mixed`

The item dressed, whatever it was, then the filter (what wp_setup_nav_menu_item does past its pre filter).

### static `forMenu(Minn\Objects\Term $menu, array $args): array`

wp_get_nav_menu_items for a menu found.

- `@param array<string, mixed> $args`
- `@return array<int, object>`

Internals: `menuItem()` (private, line 75), `pointsAtPost()` (private, line 98), `pointsAtTerm()` (private, line 115), `pointsAtArchive()` (private, line 129), `custom()` (private, line 141), `post()` (private, line 149), `term()` (private, line 168)


## NavigationFallback

`final class Minn\Runtime\NavigationFallback` · `public/minn/src/Minn/Runtime/NavigationFallback.php`

The navigation menu a navigation block falls back to
(WP_Navigation_Fallback, and the block_core_navigation_* helpers the
reference keeps as deprecated): the newest published navigation menu, else
one made from the site's classic menu, else a default listing the pages
(probes navigation-fallback, nav-pomo-rest-doubts).

### static `fallback(): mixed`

The navigation menu to fall back to: the newest published one; else,
unless wp_navigation_should_create_fallback says not, one made from the
classic menu or, without one, the default, then the newest again.

### static `classicMenu(): mixed`

The classic menu a navigation falls back to: the one in the primary
location, else the one slugged primary, else the newest; null without menus.

### static `classicMenuBlocks(object $menu, ?callable $parse = NULL): string`

A classic menu's items as serialized navigation blocks, parsed by the callable given (else here).

### static `menuItemBlocks(mixed $items, mixed $byParent, ?callable $parse = NULL): array`

Menu items as navigation blocks: a link each, a submenu for an item with
children (its inner blocks serialized as its content), the children
parsed by the callable given (else here).

- `@return list<array<string, mixed>>`

### static `newest(): mixed`

The newest published navigation menu, through the query's filters, or null.

### static `fromClassicMenu(): mixed`

The classic menu made a published navigation menu: the insert's answer, or null without a classic menu or with an empty one.

### static `createDefault(): mixed`

A published navigation menu titled Navigation, listing the site's pages while the page list block is registered.


## Notices

`final class Minn\Runtime\Notices` · `public/minn/src/Minn/Runtime/Notices.php`

The debugging notices plugins and the engine raise: a deprecated function,
argument, hook, file, class or constructor, and a function called
incorrectly. Each is heard by its action first; then, only under WP_DEBUG
and while its own filter still says so, it is raised as a PHP notice worded
as the reference words it. Every raised message passes
wp_trigger_error_always_run, wp_trigger_error_trigger_error and
wp_trigger_error_run, and keeps only links (http and https), line breaks,
code and emphasis. Values reach the hooks exactly as they were given.

- const `MARKUP` = `array (   'a' =>    array (     0 => 'href',   ),   'br' =>    array (   ),   'code' =>    array (   ),   'em' =>    array (   ),   'strong' =>    array (   ), )` — The markup a raised notice keeps (each element with its attributes), and the protocols its links may use.
- const `PROTOCOLS` = `array (   0 => 'http',   1 => 'https', )`
- const `DEPRECATIONS` = `array (   'function' =>    array (     0 => 'deprecated_function_run',     1 => 'deprecated_function_trigger_error',     2 => 'Function %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.',     3 => 'Function %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.',     4 => false,   ),   'argument' =>    array (     0 => 'deprecated_argument_run',     1 => 'deprecated_argument_trigger_error',     2 => 'Function %1$s was called with an argument that is <strong>deprecated</strong> since version %2$s! %3$s',     3 => 'Function %1$s was called with an argument that is <strong>deprecated</strong> since version %2$s with no alternative available.',     4 => false,   ),   'hook' =>    array (     0 => 'deprecated_hook_run',     1 => 'deprecated_hook_trigger_error',     2 => 'Hook %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.',     3 => 'Hook %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.',     4 => true,   ),   'file' =>    array (     0 => 'deprecated_file_included',     1 => 'deprecated_file_trigger_error',     2 => 'File %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.',     3 => 'File %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.',     4 => true,   ),   'class' =>    array (     0 => 'deprecated_class_run',     1 => 'deprecated_class_trigger_error',     2 => 'Class %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.',     3 => 'Class %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.',     4 => false,   ), )` — Each kind of deprecation: the action that hears it, the filter that may
silence it, its wording with an alternative and without one, and
whether a closing note follows the wording (and is heard after the
version).

Used by: `Minn\Admin\AdminNotice`, `Minn\Query\DateSql`, `Minn\Runtime\Abilities`, `Minn\Runtime\AbilityRun`, `Minn\Runtime\AssetQueue`, `Minn\Runtime\AttachmentImages`, `Minn\Runtime\DeprecatedUserMeta`, `Minn\Runtime\Hooks`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\PostQuery`, `Minn\Runtime\RegisteredSettings`, `Minn\Runtime\Shortcodes`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermLookup`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\Translations`, `Minn\Runtime\UserQueryRunner`, `Minn\Runtime\WpdbReads`

### static `wanted(string $filter, mixed ...$args): bool`

Whether a notice is raised: only under WP_DEBUG, and only while its filter (handed true and anything else given) still says so.

### static `deprecated(string $kind, mixed $name, mixed $version, mixed $alternative = '', mixed $note = ''): void`

A deprecation of one kind (function, argument, hook, file or class): its
name, the version that deprecated it, the alternative (for an argument,
the message), and for a hook or a file a note added after the wording.

### static `constructor(mixed $class, mixed $version, mixed $parent = ''): void`

A class still constructed through its old PHP 4 style constructor, in its parent class when one is named.

### static `doingItWrong(mixed $function, mixed $message, mixed $version): void`

A function called incorrectly, with why, and the version that added the message.

### static `message(mixed $function, mixed $message, mixed $level): ?string`

A notice's message as it would be raised, or null when it is not:
wp_trigger_error_always_run hears every one; under WP_DEBUG, and unless
wp_trigger_error_trigger_error silences it, wp_trigger_error_run hears it
and it is cleaned to the markup a notice keeps.

Internals: `notify()` (private, line 108)


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

### static `discovered(string $html, array $linktypes): ?string`

The provider endpoint a page names in its head: the first link of each
oEmbed type (the linktypes map type to json or xml), JSON before XML,
its address entity-decoded. Null when it names none.

- `@param array<string, string> $linktypes`

### static `autoembed(string $content, callable $callback): string`

URLs standing alone on a line, or alone in a paragraph, handed to the
callback (WP_Embed::autoembed): a newline inside a tag (one split over
lines, or an attribute value) is kept from the line match, so a URL
there stays as written.

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

### `sizes(): array`

Each group's size, its entries serialized, in bytes, in the order the
groups were first written: what WP_Object_Cache::stats prints.

- `@return array<string, int>`

### `flushGroup(string $group): bool`

Empties one group.


## ObjectTerms

`final class Minn\Runtime\ObjectTerms` · `public/minn/src/Minn/Runtime/ObjectTerms.php`

wp_get_object_terms's handling of taxonomies registered with their own
query arguments, as the reference handles them: among several, each such
taxonomy is asked on its own with its arguments over the caller's; alone,
its arguments join the caller's.

Used by: `Minn\Runtime\TermLookup`

### static `byTaxonomyArgs(array $objectIds, array $taxonomies, array $args): array`

The terms asked for apart, the taxonomies left (keys as they were), and the arguments for those.

- `@param list<int> $objectIds`
- `@param array<int, string> $taxonomies`
- `@param array<string, mixed> $args`
- `@return array{0: list<mixed>, 1: array<int, string>, 2: array<string, mixed>}`


## OptionLock

`final class Minn\Runtime\OptionLock` · `public/minn/src/Minn/Runtime/OptionLock.php`

A lock the reference keeps as an option row (the revision upgrade's
"revision-upgrade-{id}", the comment type batch's
"update_comment_type.lock"; tests/tools/side-effects.php): inserted only
when no row holds the name, its value the time it was taken; one held
for less than an hour keeps others out, an older one is taken over. The
holder writes the time again through update_option, and deletes the row
when done.

- const `HELD_SECONDS` = `3600` — How long a held lock keeps others out, in seconds.

Used by: `Minn\Runtime\CommentWrites`, `Minn\Runtime\PostRevisions`

### static `take(string $name, string $autoload): bool`

Takes the lock, inserting its row with the autoload value the reference gives it; false while another holds it.

### static `release(string $name): void`

Lets the lock go.


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

Used by: `Minn\Runtime\OptionWrites`

### static `clean(string $option, mixed $value): mixed`

The value as sanitize_option leaves it, through sanitize_option_{$option}.

Internals: `rule()` (private, line 53), `lists()` (private, line 73), `email()` (private, line 89), `perPage()` (private, line 96), `structure()` (private, line 104), `words()` (private, line 112), `domains()` (private, line 119), `languages()` (private, line 126)


## OptionWrites

`final class Minn\Runtime\OptionWrites` · `public/minn/src/Minn/Runtime/OptionWrites.php`

Options written the way add_option(), update_option() and delete_option()
write them: the name trimmed, alloptions and notoptions stopped, the
guarded ones refused, the value sanitized, and each change heard before
and after by the actions plugins hook.

Used by: `Minn\Ops\FilesystemCredentials`, `Minn\Rest\LiveSettings`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\CronEvents`, `Minn\Runtime\OptionLock`, `Minn\Runtime\PluginActivation`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\PostWrites`, `Minn\Runtime\RewriteStructs`, `Minn\Runtime\SiteOptions`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermWrites`, `Minn\Runtime\ThemeMods`, `Minn\Runtime\ThemeSwitch`, `Minn\Runtime\Transients`, `Minn\Runtime\Upgrade`, `Minn\Runtime\WidgetAreas`

### static `add(mixed $option, mixed $value = '', mixed $autoload = NULL): bool`

A new option (add_option): refused when it already reads as anything
but its default; its autoload flag decided when not given.

### static `update(mixed $option, mixed $value, mixed $autoload = NULL): bool`

An option changed (update_option): the new value through its filters;
an unchanged one writes nothing and tells nobody; one still at its
default is added.

### static `delete(mixed $option): bool`

An option removed (delete_option): false when it was not there.

### static `protect(mixed $option): void`

Stops the request at alloptions or notoptions, which nothing may write (wp_protect_special_option).

### static `autoloadFlag(mixed $option, mixed $value, mixed $serialized, mixed $autoload): mixed`

The autoload flag an option is stored with (wp_determine_option_autoload_value):
"on" for on, yes or true, "off" for off, no or false; anything else is
decided by wp_default_autoload_value (auto-on, auto-off) or left "auto".

### static `setAutoload(mixed $options): array`

Options' autoload flags set (wp_set_option_autoload_values): per option,
whether its flag changed. Only false, no, off, auto-off and 0 mean off.

- `@return array<array-key, bool>`


## Options

`final class Minn\Runtime\Options` · `public/minn/src/Minn/Runtime/Options.php`

Options as plugin code sees them, kept where the reference keeps them:
wp_cache's options group (OptionsGroup) holds the autoloaded list, the
names known unset, and a key per option not autoloaded, so a plugin that
reads, writes or drops those keys is heard. get_option() and
wp_load_alloptions() pass the list through pre_wp_load_alloptions and
alloptions. A value written this request is held as it was written (an
int stays an int, true stays true), arrays and objects as stored. Each
stored text is decoded once while it stays the same, except one holding
an object, decoded afresh on every read as the reference does: a plugin
that changes the object it was handed and saves it must find the stored
copy still different, or its change is never written.

- const `ALL` = `'alloptions'`
- const `MISSING` = `'notoptions'`
- const `GUARDED` = `array (   0 => 'minn_runtime_symbols',   1 => 'minn_recovery_strikes',   2 => 'minn_cron_lock', )` — The options the engine keeps for itself: the symbol-gate cache a plugin
could otherwise rewrite to pass its own gate, the recovery strikes, the
cron lock, and the sign-in throttle rows. The facade refuses to write
them; the engine writes them through this class directly.
- const `GUARDED_PREFIX` = `'minn_login_throttle_'`
- const `ON_DEMAND` = `array (   0 => 'minn_admin_changelog',   1 => 'minn_changelog',   2 => 'minn_release',   3 => 'minn_plugin_info',   4 => 'minn_updates',   5 => '_transient_minn_admin_uploads_size',   6 => '_transient_timeout_minn_admin_uploads_size',   7 => 'minn_admin_uploads_size_lock', )` — The engine's own caches, read on demand and written with autoload off
(Site::setOption). Rows an older engine wrote autoloaded are set right
the first time a loaded list holds them.
- const `AUTOLOAD_VALUES` = `array (   0 => 'yes',   1 => 'on',   2 => 'auto-on',   3 => 'auto', )` — The autoload column values that load an option with every request, in the reference's order.
- const `EVERY_REQUEST` = `array (   0 => 'minn_runtime_symbols',   1 => 'paused_plugins',   2 => 'WPLANG',   3 => 'https_migration_required',   4 => 'minn_admin_post_types',   5 => 'minn_admin_taxonomies',   6 => 'can_compress_scripts',   7 => 'site_logo',   8 => 'minn_admin_maintenance', )` — Options not autoloaded that are read on every request (the symbol
survey, recovery's paused plugins, the site language, the HTTPS
migration flag, the post types and taxonomies Minn Admin keeps and
registers as it loads, whether scripts may be compressed, which every
page that prints them asks, the site logo a block theme's header asks
for, and Minn Admin's maintenance switch the front door checks): read
together as a request begins, as one read rather than one each.

Used by: `Minn\Admin\AdminPlugin`, `Minn\Cli\OptionCommand`, `Minn\Content\PostWriter`, `Minn\Content\Users`, `Minn\Engine`, `Minn\Ops\Directory`, `Minn\Runtime\AjaxController`, `Minn\Runtime\MetaData`, `Minn\Runtime\OptionWrites`, `Minn\Runtime\Runtime`, `Minn\Runtime\Symbols`

```php
__construct(Minn\Db $db)
```


### `isFor(Minn\Db $db): bool`

Whether this store reads through the given connection.

### static `guarded(string $name): bool`

Whether plugin code is refused a write to this option.

### `autoloaded(bool $force = false): mixed`

The autoloaded list as wp_load_alloptions() gives it:
pre_wp_load_alloptions may answer first (told whether the cache was
forced), and then alloptions is not heard; otherwise the list the
group holds, through alloptions, which may add or change entries
without the group keeping them.

### `filtered(string $name, mixed ...$default): mixed`

An option as get_option() reads it, through the filters plugins hook:
pre_option_{name}, then pre_option, may answer first; then the
autoloaded list is read through its filters on every read; an unset
option is its default (false when none is given) through
default_option_{name}, which is told whether one was given; a set one
goes through option_{name}.

### `raw(string $name): ?string`

An option's stored text, as Db::option() gives it, or null when it is
unset: from the list or the group, a value written this request in
its stored form (true as "1", a list serialized).

### `get(string $name): mixed`

An option's value, decoded, or null when unset; home and siteurl answer to wp-config.php's constants.

### `prime(array $names): void`

Reads several options at once (wp_prime_option_caches): the ones not
held yet in one query, each kept under its own key or noted unset, as
a read of each one alone would leave them.

- `@param list<string> $names`

### `exists(string $name): bool`

Whether an option exists.

### `knownMissing(string $name): bool`

Whether a read this request already found the option unset (the reference's notoptions).

### `add(string $name, mixed $value, string $autoload = 'auto'): bool`

Writes a new option. add_option has already decided it is new (its
value is still the default); a row that is there anyway is written
over, as the reference's add does, and false comes back when it
already held this value. An autoloaded one joins the list.

### `upsert(string $name, string $stored, string $autoload): bool`

Writes an option row whether or not one exists, as the reference does
for every add: two requests that both find a transient missing and
both add it must not fail on the unique name, so the second write lands
over the first. True when the row was inserted or changed, false when
the same value was already there.

### `update(string $name, mixed $value, ?string $autoload = NULL): bool`

False when the value is unchanged, as the reference reports. An
autoload flag given is stored with the value, and the value moves
between the autoloaded list and a key of its own when the flag says
so.

### `autoloadOf(string $name): ?string`

The autoload flag a stored option carries, or null when there is no such row.

### `markAutoload(string $name): bool`

Flips the autoload column; false when the option is missing or already so. The list is read again, the option's own key dropped.

### `unmarkAutoload(string $name): bool`

Stops an option loading on every request; false when it already did not, or is unset. It leaves the list.

### `claim(string $name, string $value, string $autoload): bool`

Inserts an option row only when no row holds the name yet, past every
cache: a lock (Runtime\OptionLock). Whether this call made the row.

### `delete(string $name): bool`

Removes an option: out of the list or its own key, and known unset.

### `expiredTransientNames(int $now, string $prefix = '_transient_timeout_'): array`

The names of transients whose expiry has passed. The timeout row is the
one that knows, so the sweep reads those and hands back the bare names.

- `@return list<string>`

### `forget(string $name): void`

Brings the group back in step with an option the engine wrote behind
the store (its own rows, the CLI): the row is read again, into the list
when it autoloads, and nothing else written this request is lost.

### static `toStorage(mixed $value): string`

What the reference stores (maybe_serialize): arrays and objects
serialized, a string that looks serialized serialized again so it
reads back as that string, other scalars as their string form.

### static `fromStorage(string $raw): mixed`

A stored option value decoded the way the reference reads it.

Internals: `listed()` (private, line 100), `missing()` (private, line 125), `lookup()` (private, line 142), `decode()` (private, line 167), `remember()` (private, line 384), `found()` (private, line 395), `holdsObject()` (private, line 405), `switchAutoload()` (private, line 436)


## OptionsGroup

`final class Minn\Runtime\OptionsGroup` · `public/minn/src/Minn/Runtime/OptionsGroup.php`

wp_cache's options group, where option reads are kept as the reference
keeps them: the autoloaded list under alloptions, the names a read found
unset under notoptions, and one key per option not autoloaded. The
engine's own object cache holds it, or an object-cache.php drop-in once
the facade hands over its functions.

A drop-in's cache outlives the request, and the engine writes some
options behind the store (its own state, the CLI), so a key left by an
earlier request is never trusted: each is read from the database the
first time a request asks for it, then kept in step for the rest of it.

- const `GROUP` = `'options'`

Used by: `Minn\Runtime\Options`


### static `useExternal(Closure $get, Closure $set, Closure $delete): void`

Hands the group to an object-cache.php drop-in's functions.

### `get(string $key, ?bool $found = NULL): mixed`

A key's value, found only when this request may trust it.

### `set(string $key, mixed $value): void`

Stores a key; a drop-in is written only when it holds something else.

### `delete(string $key): void`

Removes a key.


## OutputFloor

`final class Minn\Runtime\OutputFloor` · `public/minn/src/Minn/Runtime/OutputFloor.php`

The output buffers the engine reads its pages from. The reference prints
as it goes, so a plugin that ends every buffer mid-page
(wp_ob_end_flush_all) only sends what it has early; the engine builds the
page in its own buffers and sends it whole, so ending those would send
part of the page out ahead of its headers and lose it from the rest.
Each capture holds its level while it is open, and such a plugin ends
only the buffers above the innermost one (suite output-flush).

Used by: `Minn\Front\FeedController`, `Minn\Front\PrintedResponse`, `Minn\Runtime\Runtime`, `Minn\Theme\TemplateBuffer`


### static `hold(): void`

Marks the buffer just opened as one the engine reads.

### static `release(): void`

Lets the innermost capture go.

### static `level(): int`

The level buffers may be ended down to: the innermost capture's, or none.


## Pages

`final class Minn\Runtime\Pages` · `public/minn/src/Minn/Runtime/Pages.php`

get_pages() as the reference shapes it: its arguments as a post query
(WP_Query, so its filters shape it), the tree order of the result, then
the get_pages filter. wp_list_pages, wp_dropdown_pages and the
navigation's page list take their pages from here.

- const `DEFAULTS` = `array (   'child_of' => 0,   'sort_order' => 'ASC',   'sort_column' => 'post_title',   'hierarchical' => 1,   'exclude' =>    array (   ),   'include' =>    array (   ),   'meta_key' => '',   'meta_value' => '',   'authors' => '',   'parent' => -1,   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => 0,   'post_type' => 'page',   'post_status' => 'publish', )`
- const `COLUMNS` = `array (   'post_title' => 'title',   'menu_order' => 'menu_order',   'post_date' => 'date',   'post_modified' => 'modified',   'ID' => 'ID',   'post_author' => 'author',   'post_name' => 'name',   'post_parent' => 'parent', )`

Used by: `Minn\Blocks\Dynamic\Theme\Navigation`

### static `get(array $args): array`

The pages get_pages() answers for parsed arguments.

- `@param array<string, mixed> $args`
- `@return array<int, \WP_Post>`

### static `queryArgs(array $parsed, array $include, array $exclude): array`

The post query the get_pages() arguments amount to, shaped as the
reference hands it to get_pages_query_args (probe filter-inputs-2): the
order by the columns as named, then (unless pages are picked by id)
the excluded ones and the meta pair, the number or -1 and the offset,
the type, the statuses as a list and the cache flags; then the picked
pages, the authors and the parent, each when given.

- `@param list<int> $include`
- `@param list<int> $exclude`

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


### static `deadEnd(string $symbol): void`

Logs a call to a method of a real class that Minn answers with nothing by
design (an admin-screen helper): compat-status counts the method as a
dead end, where a hit() marks one still owed.

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

Used by: `Minn\Runtime\WpdbReads`

### static `fill(string $query, array $args, callable $escape): ?string`

Fills a query's placeholders from the arguments; null when one has no argument.

- `@param list<mixed> $args`
- `@param callable(string): string $escape the connection's string escape`

### static `prepare(string $query, array $args, callable $escape): array`

fill() with what wpdb::prepare reports alongside: how many placeholders
the query has (%% not among them) and the type of each argument it
could not use (an array or an object fills as '').

- `@param list<mixed> $args`
- `@param callable(string): string $escape the connection's string escape`
- `@return array{query: ?string, placeholders: int, unsupported: list<string>}`

Internals: `filled()` (private, line 50), `quoted()` (private, line 78), `text()` (private, line 83)


## PluginActivation

`final class Minn\Runtime\PluginActivation` · `public/minn/src/Minn/Runtime/PluginActivation.php`

Switching a plugin on and off the way the reference's activate_plugin
and deactivate_plugins do (probe plugin-activation), for the facade and
for the engine's own REST routes and WP-CLI verbs alike. Activating
checks the plugin's requirements, then loads its main file through the
runtime's gate (so its activation hook is registered, silent or not),
fires activate_plugin and activate_<file> (which runs that hook), writes
active_plugins sorted through update_option, and fires activated_plugin;
whatever the plugin printed meanwhile makes the answer unexpected_output,
with the plugin left active, as the reference leaves it. Silent skips the
actions, not the load. A plugin the gate will not load (it needs
functions or classes the runtime lacks) is refused rather than recorded
active with code that could never run its activation hook. Deactivating
fires deactivate_plugin, deactivate_<file> and deactivated_plugin for
each plugin, then writes the list once.

Used by: `Minn\Admin\LegacyPackController`, `Minn\Content\PluginState`

### static `activate(string $plugin, ?Minn\Runtime\Hooks $announce, ?Closure $networkStore = NULL): ?Minn\Runtime\Refusal`

Activates one plugin ("dir/file.php"), announcing it to the hooks
given, or to nobody (silent); null when it is active afterwards with
nothing to report.

### static `deactivate(array $plugins, ?Minn\Runtime\Hooks $announce): void`

Deactivates plugins ("dir/file.php" each), announcing each to the hooks
given, or to nobody (silent); those not active are passed over.

- `@param list<string> $plugins`

Internals: `active()` (private, line 87)


## PluginCall

`final class Minn\Runtime\PluginCall` · `public/minn/src/Minn/Runtime/PluginCall.php`

Calls code a plugin, theme or drop-in registered (a hook callback, a
shortcode, a widget, a REST permission or field callback), as WordPress
calls it. PHP judges a call's argument types by the file the call is made
from: the engine's own files declare strict types, so a callback typed
`int $post_id` handed the string "3494" would throw a TypeError there,
where WordPress's untyped files let PHP convert it (Elementor's heartbeat
passes the post ID it was posted, a string, to just such a callback).
Every call into extension code is made from here, the one engine file
that leaves strict types off.

Used by: `Minn\Rest\RegisteredFields`, `Minn\Rest\RestMeta`, `Minn\Rest\RouteIndex`, `Minn\Rest\RouteSchemas`, `Minn\Rest\RuntimeRoutes`, `Minn\Runtime\FileNames`, `Minn\Runtime\Hooks`, `Minn\Runtime\Shortcodes`, `Minn\Runtime\TermWrites`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\WidgetAreas`

### static `run(callable $callback, array $args = array ( )): mixed`

Runs a callback extension code registered, with the arguments given.

- `@param list<mixed> $args`


## PluginFiles

`final class Minn\Runtime\PluginFiles` · `public/minn/src/Minn/Runtime/PluginFiles.php`

A plugin's files as WordPress's plugin functions know them: where a
plugin folder really is (a symlinked one noted), a plugin file's name
under the plugins folder, its headers as read and as the plugin screens
show them, and whether it can be uninstalled.

- const `HEADERS` = `array (   'Name' => 'Plugin Name',   'PluginURI' => 'Plugin URI',   'Version' => 'Version',   'Description' => 'Description',   'Author' => 'Author',   'AuthorURI' => 'Author URI',   'TextDomain' => 'Text Domain',   'DomainPath' => 'Domain Path',   'Network' => 'Network',   'RequiresWP' => 'Requires at least',   'RequiresPHP' => 'Requires PHP',   'UpdateURI' => 'Update URI',   'RequiresPlugins' => 'Requires Plugins', )` — The headers a plugin's main file may carry, by field.

Used by: `Minn\Rest\PluginsController`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\PluginRequirements`

### static `registerRealpath(mixed $file): bool`

Where a plugin's folder really is, noted under the folder as given
(wp_register_plugin_realpath); a file not on disk notes an empty
place. The plugins and must-use folders themselves are refused.

### static `basename(mixed $file): string`

A plugin file's name under the plugins or must-use folder (plugin_basename), a symlinked folder read as noted.

### static `data(mixed $pluginFile, mixed $markup = true, mixed $translate = true): array`

A plugin's headers (get_plugin_data), marked up and translated unless
asked not to be; a plugin in its own folder that names no text domain
uses the folder's name.

- `@return array<string, mixed>`

### static `shown(mixed $pluginFile, mixed $data, mixed $markup = true, mixed $translate = true): array`

The headers as the plugin screens show them
(_get_plugin_data_markup_translate). Translated: the plugin's domain
loaded from its folder unless it is already, then the name, addresses,
version, description and author through it. Always: the text cut to a,
abbr, acronym, code, em and strong, the addresses escaped. Marked up:
the title and author linked, the description texturized with its "By"
line.

- `@param array<string, mixed> $data`
- `@return array<string, mixed>`

### static `uninstallable(mixed $plugin): bool`

Whether a plugin can be uninstalled (is_uninstallable_plugin): it registered an uninstall callback, or ships an uninstall.php.


## PluginRemoval

`final class Minn\Runtime\PluginRemoval` · `public/minn/src/Minn/Runtime/PluginRemoval.php`

Deleting plugins as the reference's delete_plugins does it: each in turn
is uninstalled first when it can be (its uninstall.php, or the callback
it registered), then delete_plugin, its folder (or file) removed,
deleted_plugin, and its installed translations removed with it; the
updates offered for the deleted ones are forgotten. A plugin already
gone counts as deleted. Only what lies inside the plugins folder is
ever removed, and a symlinked folder is unlinked, never followed.

Used by: `Minn\Rest\PluginsController`

### static `delete(array $plugins): Minn\Objects\Errors|bool`

delete_plugins: true, false for none asked, or the error naming those left behind.

- `@param array<int, string> $plugins plugin files, relative to the plugins folder`

### static `uninstall(string $plugin): ?bool`

uninstall_plugin: pre_uninstall_plugin, then the plugin's uninstall.php
(WP_UNINSTALL_PLUGIN naming it) or the callback it registered (heard on
uninstall_<file>, with the plugin loaded); either is forgotten once run.

Internals: `deleteOne()` (private, line 86), `removeFiles()` (private, line 101), `removeTranslations()` (private, line 122), `forget()` (private, line 139), `forgetUpdates()` (private, line 148)


## PluginRequirements

`final class Minn\Runtime\PluginRequirements` · `public/minn/src/Minn/Runtime/PluginRequirements.php`

Whether a plugin can be switched on here, as the reference's
validate_plugin_requirements answers (probe plugin-activation): the
plugin header's "Requires at least" against the WordPress version the
site speaks, its "Requires PHP" against the running PHP, and every slug
in its "Requires Plugins" installed and active (a readme.txt is not
read). A refusal carries the reference's code, its HTML message word for
word, and for missing plugins the slugs found inactive (slug => name) and
not installed (slug => slug). The slugs are taken sorted (one that is not
a lower-case slug is passed over), and named in that order.

Used by: `Minn\Runtime\PluginActivation`

### static `check(string $plugin): ?Minn\Runtime\Refusal`

Null when the plugin may be activated, otherwise why not.

Internals: `dependencies()` (private, line 48), `installed()` (private, line 77)


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

Used by: `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Extension\Loader`, `Minn\Runtime\PluginActivation`, `Minn\Theme\ClassicRenderer`


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

### static `loadNow(string $plugin, Minn\Runtime\Runtime $runtime): ?string`

Loads one plugin's main file now, as activating it does: the reference
includes the file (once) before activate_plugin fires, so the plugin's
activation hook is in place to hear activate_<file>, and announces no
plugin_loaded for it. The same gate as the boot decides, its folder
walked again first; null once the plugin is running, otherwise why the
gate refused it.

### static `isLoaded(string $plugin): bool`

True when the named plugin file is running as code this request.

Internals: `boot()` (private, line 67), `loadThemeFunctions()` (private, line 157), `rememberThemeDomain()` (private, line 180), `includeFile()` (private, line 243), `registerRealpath()` (private, line 274), `isolatedInclude()` (private, line 287)


## PostCaches

`final class Minn\Runtime\PostCaches` · `public/minn/src/Minn/Runtime/PostCaches.php`

A post's cached rows let go as WordPress lets them go: its row, its meta
and the terms read with it, then plugins told (a page told twice); held
back while cache invalidation is suspended.

Used by: `Minn\Runtime\Attachments`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostWrites`

### static `clean(mixed $post): void`

A post's caches let go (clean_post_cache).


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

Used by: `Minn\Rest\BlockRendererController`, `Minn\Rest\RenderedFields`

### static `setup(mixed $post): mixed`

A post made the one being read (setup_postdata): through the query
being answered when there is one (which tells the_post), else its
globals set here; false for no post.

### static `globals(Minn\Objects\Post $post): void`

The loop's globals for a post: its id, author, dates, pages and whether more shows.

### static `generate(Minn\Objects\Post $post, ?Minn\Objects\Query $query = NULL): array`

id, authordata, currentday, currentmonth, page, pages, multipage,
more, numpages. The page comes from the query given (the main query
otherwise); more is set on a single, a page or a feed, or past the
first page of a post split into pages (probe query-compat).

- `@return array<string, mixed>`

### static `content(?string $moreLinkText, Minn\Objects\Post $post, array $elements): string`

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

Used by: `Minn\Rest\FontWritesController`, `Minn\Rest\FontsController`, `Minn\Rest\MediaController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\TemplateRevisionsController`, `Minn\Runtime\MenuEvents`

### `deleteForGood(int $id): mixed`

A post deleted for good through the reference's own deletion, so its
hooks fire and its children (revisions, autosaves) go with it: the
one path every permanent delete takes. Deleting needs the facade, so
unlike the events it runs only under a booted runtime, which the
engine's write routes are. Answers what the deletion answered.

### `trash(int $id): mixed`

A post (an attachment too) sent to the trash through the reference's own trashing, as deleteForGood deletes.

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

### `attachmentGenerated(int $id, string $file): void`

A new attachment's metadata made by the runtime, as the reference's
REST upload makes it: wp_generate_attachment_metadata over the stored
file (the sizes cut with the attachment's id, the metadata stored as
each one lands, so an optimiser hooked there finds it), then
wp_update_attachment_metadata. Every upload has some: an image its
sizes, a PDF its previews, any file its size (probe hook-inputs-2).

### `restInserted(int $id, Minn\Http\Request $request, ?Minn\Content\PostRecord $before): void`

rest_insert_{type}, before the request's own terms and fields are applied; a post with no $before is a new one.

### `restAfterInsert(int $id, Minn\Http\Request $request, ?Minn\Content\PostRecord $before): void`

rest_after_insert_{type}, once the request's terms and fields are in; a post with no $before is a new one.

### `afterInsert(int $id, ?Minn\Content\PostRecord $before): void`

wp_after_insert_post, last; the revision of an update is saved from it.

### `restDeleted(Minn\Content\PostRecord $post, array $data, Minn\Http\Request $request): void`

rest_delete_{type}, after a trash or a delete, with the post as it was answered and the response. @param array<string, mixed> $data

- `@param array<string, mixed> $data`

Internals: `rest()` (private, line 162), `wpPost()` (private, line 184)


## PostFields

`final class Minn\Runtime\PostFields` · `public/minn/src/Minn/Runtime/PostFields.php`

A post's fields put in a context as WordPress's sanitize_post_field puts
them: raw as stored; for the database through the pre_ filters; for
editing through the edit_ filters and made safe for a form; for display
through the field's own filter, escaped for an attribute or a script when
asked. The integer columns stay integers in every context.

Used by: `Minn\Runtime\PostReads`, `Minn\Runtime\PostSave`

### static `value(mixed $field, mixed $value, mixed $postId, mixed $context = 'display'): mixed`

One field in a context (sanitize_post_field).

### static `sanitize(mixed $post, mixed $context = 'display'): mixed`

Every field of a post object or array in a context (sanitize_post), unless it is in that context already.

### static `formatToEdit(mixed $content, mixed $richText = false): mixed`

Text made ready for an edit field (format_to_edit): through format_to_edit, encoded for a textarea unless rich.

### static `richEdit(): bool`

Whether the user edits in the rich editor (user_can_richedit): their preference, and a browser that can.

Internals: `contextual()` (private, line 74)


## PostInsert

`final readonly class Minn\Runtime\PostInsert` · `public/minn/src/Minn/Runtime/PostInsert.php`

The decisions behind wp_insert_post: which columns a postarr fills, when
the post counts as empty, the status a publish request lands in, the dates
and the slug, the categories a new post gets. The rows are written by
Content\PostWriter; the facade fires the hooks around each step.

- const `COLUMNS` = `array (   0 => 'post_author',   1 => 'post_date',   2 => 'post_date_gmt',   3 => 'post_content',   4 => 'post_title',   5 => 'post_excerpt',   6 => 'post_status',   7 => 'comment_status',   8 => 'ping_status',   9 => 'post_password',   10 => 'post_name',   11 => 'to_ping',   12 => 'pinged',   13 => 'post_modified',   14 => 'post_modified_gmt',   15 => 'post_content_filtered',   16 => 'post_parent',   17 => 'guid',   18 => 'menu_order',   19 => 'post_type',   20 => 'post_mime_type', )`

Used by: `Minn\Runtime\PostWrites`

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

### `persist(array $columns, ?int $existingId): int`

Writes the resolved columns. @return int the post id

### static `unwritten(array $columns, ?int $existingId): Minn\Objects\Errors`

The error for a post the database door would not write, its text not
what the table holds (probe refused-writes): an insert's or an
update's (a post that already has an id), naming the first such column.

- `@param array<string, mixed> $columns`

### `fillGuid(int $id, array $columns, Closure $guid): void`

Gives a new post written without a guid its permalink as one, once its slug, terms and meta are in.

### static `categories(array $postarr, int $id, string $type, string $status, bool $update, array $taxonomies): ?array`

The categories a saved post hands wp_set_post_categories: none when
its type has no categories (null); an update that names none keeps
the ones it has; otherwise the ones given, each empty value dropped.
None left is the default category for a post that is not an
auto-draft, else an empty list (which wp_set_post_categories decides).

- `@param array<string, mixed> $postarr`
- `@param list<string> $taxonomies the type's taxonomies`
- `@return array<mixed>|null`

### static `categoryIds(mixed $categories, mixed $status, mixed $type, mixed $append): array`

The category ids wp_set_post_categories sets, and whether they add to
the post's: none given (null or an empty list) is the default
category in place of the post's, for a post of a type that takes one
(posts and the types default_category_post_types adds) that is not an
auto-draft; a lone blank string is nothing to set (null ids); else
each value as an id, any that is none dropped.

- `@return array{0: list<int>|null, 1: bool}`

Internals: `type()` (private, line 227)


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

Used by: `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\Permalinks`, `Minn\Front\PostEmbed`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\CommentTags`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostData`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TermTags`

### static `permalink(mixed $post = 0, mixed $leavename = false): mixed`

A post's address by its kind (get_permalink): a page's, an
attachment's, a plugin type's or a post's; false for no post. A post
filtered for "sample" is shown as it would be published.

### static `pageLink(mixed $post = 0, mixed $leavename = false, mixed $sample = false): mixed`

A page's address (get_page_link): the home address for the front page; then page_link.

### static `pageAddress(mixed $post = 0, mixed $leavename = false, mixed $sample = false): mixed`

A page's own address, front page or not (_get_page_link); no such page is the plain address with no id.

### static `attachmentLink(mixed $post = NULL, mixed $leavename = false): mixed`

An attachment's address (get_attachment_link), or false.

### static `customLink(mixed $post = 0, mixed $leavename = false, mixed $sample = false): mixed`

A plugin type's post address (get_post_permalink), or false.

### static `post(Minn\Objects\Post $post, int $flags): string`

A post's address (not a page, attachment or plugin type).

### static `page(Minn\Objects\Post $post, int $flags): string`

A page's own address, before page_link: ?page_id= while unpublished, its tag when the name is left, else its path.

### static `attachment(Minn\Objects\Post $post, int $flags): string`

An attachment's address: under a parent it belongs to, else its own slug, else ?attachment_id=.

### static `custom(Minn\Objects\Post $post, int $flags): string`

A plugin type's address by its pattern, by its query var, or by the type and id plainly.

### static `sampleFor(mixed $post, mixed $title = NULL, mixed $name = NULL): array`

The address an editor shows with the slug to edit, and the slug
(get_sample_permalink); two empty strings for no post.

- `@return array{0: string, 1: string}`

### static `sample(Minn\Objects\Post $post, ?string $title, ?string $name): array`

The address an editor shows with the slug to edit: the post as if
published, its name made unique, the address with the name left,
a page's parents written in.

- `@return array{0: string, 1: string}`

Internals: `flags()` (private, line 91), `tokens()` (private, line 110), `category()` (private, line 128), `plain()` (private, line 199)


## PostLocks

`final class Minn\Runtime\PostLocks` · `public/minn/src/Minn/Runtime/PostLocks.php`

A post's edit lock (_edit_lock, "time:user"): who holds it while it is
fresh, for the editor and the autosave that must not write under it.

Used by: `Minn\Runtime\Autosaves`

### static `holder(int $postId, int $askingId): int`

The user who holds a post's lock other than the one asking
(wp_check_post_lock): the lock's user, or the post's last editor when
it names none, while it is newer than the window
(wp_check_post_lock_window, 150 seconds); 0 otherwise.


## PostLookup

`final readonly class Minn\Runtime\PostLookup` · `public/minn/src/Minn/Runtime/PostLookup.php`

The post reads plugin code asks for by shape: a page by title, revisions, counts.

Used by: `Minn\Runtime\PostRevisions`, `Minn\Runtime\UserCounts`, `Minn\Runtime\UserWrites`

```php
__construct(Minn\Db $db)
```


### `idWhere(array $columns): ?int`

The first post whose columns hold the values given, each as stored
(post_exists); null for none.

- `@param array<string, string> $columns column => value; only post_title, post_content, post_date, post_type and post_status`

### `idByTitle(string $title, array $types): ?int`

The id of a post with this title among the types, or null.

- `@param list<string> $types`

### `revisionsOf(int $postId): array`

Revision rows newest first. @return list<array<string, mixed>>

- `@return list<array<string, mixed>>`

### `countByStatus(string $type, ?int $privateOf = NULL): array`

How many posts of a type there are per status.

- `@return array<string, int> status => count`

### `countAttachments(): array`

How many attachments there are per mime type.

- `@return array<string, int> mime type => count, plus 'trash'`

### `countByAuthor(int $userId, array $types, array $statuses): int`

How many posts an author has among the types and statuses.

- `@param list<string> $types @param list<string> $statuses`

### `countsByAuthors(array $userIds, array $types, array $statuses): array`

Post counts for several authors at once, as stored (strings), for
those that have any.

- `@param list<int> $userIds`
- `@param list<string> $types`
- `@param list<string> $statuses`
- `@return array<int, string>`

### `publishingAuthors(string $type, int $limit): int`

How many authors (up to a limit) have published posts of a type, for is_multi_author.

### `countsByAuthor(array $types, array $statuses, int $viewer): array`

Post counts by author for wp_list_authors: the types' posts in the
statuses given, and the private ones of the viewer (an id, or -1 for
none) beside them.

- `@param list<string> $types @param list<string> $statuses`
- `@return array<int, int>`

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
A lookup the engine can answer from what it has read (QueryAnswers) runs
every filter and no SQL, unless a plugin changes it.

- const `SHAPING` = `array (   0 => 'parse_query',   1 => 'parse_tax_query',   2 => 'posts_search',   3 => 'posts_search_orderby',   4 => 'posts_where',   5 => 'posts_join',   6 => 'posts_where_paged',   7 => 'posts_groupby',   8 => 'posts_join_paged',   9 => 'posts_orderby',   10 => 'posts_distinct',   11 => 'post_limits',   12 => 'posts_fields',   13 => 'posts_clauses',   14 => 'posts_where_request',   15 => 'posts_groupby_request',   16 => 'posts_join_request',   17 => 'posts_orderby_request',   18 => 'posts_distinct_request',   19 => 'posts_fields_request',   20 => 'post_limits_request',   21 => 'posts_clauses_request',   22 => 'posts_request',   23 => 'posts_pre_query',   24 => 'split_the_query',   25 => 'posts_request_ids', )` — The hooks that shape the request: an engine answer stands only while none of them has a listener.

Used by: `Minn\Runtime\Runtime`

```php
__construct(Minn\Db $db)
```


### `run(Minn\Objects\Query $query, object $wpdb, mixed $urlSearch): mixed`

Runs a query object's variables and fills it in: the posts (or ids, or
id => parent), the request, the counts. Returns what get_posts returns.

### `search(Minn\Objects\Query $query, array $q, mixed $urlSearch): string`

A search's WHERE fragment, settling the search variables (the terms,
how many the string split into, the title matches its order ranks by).

- `@param array<string, mixed> $q`

### `stopwords(): array`

The search stopwords, translated and filtered. @return list<string>

- `@return list<string>`

Internals: `prepare()` (private, line 64), `defaults()` (private, line 86), `pageSize()` (private, line 135), `clauses()` (private, line 165), `taxonomies()` (private, line 199), `searchOrder()` (private, line 264), `filtered()` (private, line 282), `through()` (private, line 305), `paging()` (private, line 315), `execute()` (private, line 331), `idsOnly()` (private, line 366), `select()` (private, line 388), `answered()` (private, line 415), `foundPosts()` (private, line 430)


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
- `?Minn\Objects\PostType $typeObject`
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
__construct(Minn\Objects\Query $query, Minn\Runtime\PostQueryParts $parts)
```


### `settle(array $q): void`

Settles the posts a query found: status check, stickies, the_posts, then the count and the current post. @param array<string, mixed> $q

- `@param array<string, mixed> $q`

Internals: `singleStatus()` (private, line 46), `visible()` (private, line 68), `stickies()` (private, line 96)


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
__construct(Minn\Objects\Query $query, Minn\Runtime\PostQueryParts $parts)
```


### `settleType(): void`

Settles the post type the caps come from: a list of several types has
none (its caps are named for multiple_post_type), any other names one.

### `append(array $q): void`

Appends the type and status clause. @param array<string, mixed> $q

- `@param array<string, mixed> $q`

Internals: `typeWhere()` (private, line 65), `cap()` (private, line 91), `named()` (private, line 98), `listing()` (private, line 142), `privateStatuses()` (private, line 171)


## PostQueryTax

`final class Minn\Runtime\PostQueryTax` · `public/minn/src/Minn/Runtime/PostQueryTax.php`

The taxonomy side of WP_Query as the reference runs it (probe
wp-query-sql): the clauses its variables make (tax_query, taxonomy and
term, each taxonomy's query var, cat and the category__ lists, tag and the
tag__ lists), settled back into the variables; the post types a taxonomy
archive with no type searches; and the cat, category_name, tag_id,
taxonomy and term variables set from the terms queried.

Used by: `Minn\Runtime\PostQuery`

### static `clauses(Minn\Objects\Query $query, array $q): array`

The tax query a set of variables makes, settling the variables it reads.

- `@param array<string, mixed> $q`
- `@return list<array<string, mixed>>|array<string, mixed>`

### static `postTypes(array $taxonomies): array|string`

The post types a taxonomy archive with no type searches: every
searchable type the queried taxonomies are registered for, one as a
string, several sorted, none as `any`.

- `@param list<string> $taxonomies`
- `@return string|list<string>`

### static `compat(Minn\Objects\Query $query, array $q, array $queried): void`

The compatibility variables the queried terms set: taxonomy and term
(or term_id) from the first taxonomy other than category and post_tag
when none is set, and cat, category_name and tag_id from those two.

- `@param array<string, array{terms?: list<mixed>, field?: string}> $queried`

Internals: `queryVar()` (private, line 57), `cat()` (private, line 72), `categoryLists()` (private, line 89), `tag()` (private, line 112), `tagLists()` (private, line 132)


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
__construct(Minn\Objects\Query $query, Minn\Runtime\PostQueryParts $parts, string $table)
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

Internals: `nameList()` (private, line 90), `typeQueryVar()` (private, line 100), `pagename()` (private, line 122), `ids()` (private, line 161), `authorName()` (private, line 236), `commentCount()` (private, line 249)


## PostReads

`final class Minn\Runtime\PostReads` · `public/minn/src/Minn/Runtime/PostReads.php`

What WordPress's post functions tell about posts: a page's path and a
page by its path, a post's type, status and fields, whether it can be
viewed, when posts last changed, posts by query arguments and a post's
children and categories, the SQL that limits a query to what an author
may read, and the caches primed for a list of posts.

Used by: `Minn\Content\MediaShortcodes`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\PostEmbed`, `Minn\Front\RuleTable`, `Minn\Rest\BlockRendererController`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostInsert`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryWhere`, `Minn\Runtime\PostSave`, `Minn\Runtime\PostWrites`, `Minn\Runtime\RewriteRules`, `Minn\Runtime\UserOrder`, `Minn\Theme\FeedHeaders`

### static `pageUri(mixed $page = 0): mixed`

A page's path from its ancestors' slugs (get_page_uri), or false.

### static `pageByPath(mixed $path, mixed $output = 'OBJECT', mixed $type = 'page'): mixed`

A page by its path (get_page_by_path): one type looks among attachments too; a list looks among exactly its types.

### static `type(mixed $post = NULL): mixed`

A post's type (get_post_type), or false.

### static `status(mixed $post = NULL): mixed`

A post's status (get_post_status): an attachment's is its parent's (a trashed parent's from before the trash); false for none.

### static `field(mixed $field, mixed $post = NULL, mixed $context = 'display'): mixed`

One of a post's fields in a context (get_post_field), empty when there is none.

### static `publiclyViewable(mixed $post = NULL): bool`

Whether a post can be viewed by anyone (is_post_publicly_viewable): its type and its status both viewable.

### static `lastTime(mixed $timezone, mixed $field, mixed $type = 'any'): mixed`

When a public post was last published or changed (_get_last_post_time),
in GMT, the site's time or the server's (with microseconds); false for
none or a question it cannot answer.

### static `lastDate(mixed $timezone = 'server', mixed $type = 'any'): mixed`

When a public post was last published (get_lastpostdate), as get_lastpostdate leaves it.

### static `lastModified(mixed $timezone = 'server', mixed $type = 'any'): mixed`

When a public post last changed or was published, the later (get_lastpostmodified), pre_get_lastpostmodified answering first.

### static `query(mixed $args = NULL): mixed`

Posts by query arguments (get_posts): five published posts by date
unless asked otherwise, numberposts read as posts_per_page, category,
include and exclude read as the query's own, stickies ignored, no rows
counted.

- `@return array<int, mixed>`

### static `children(mixed $args = '', mixed $output = 'OBJECT'): array`

A post's children (get_children), the post being read's when none is
named, keyed by id, as objects or arrays.

- `@return array<int, mixed>`

### static `categories(mixed $postId = 0, mixed $args = array ( )): mixed`

A post's categories (wp_get_post_categories), their ids unless asked otherwise; empty on an error.

### static `mimeTypeWhere(mixed $types, mixed $tableAlias = ''): string`

The WHERE clause for MIME types (wp_post_mime_type_where), a wildcard or a list.

### static `byAuthorSql(mixed $type, mixed $full = true, mixed $author = NULL, mixed $publicOnly = false): string`

The SQL limiting posts to what may be read (get_posts_by_author_sql): an
author's own, the published, and the private ones the reader may read.

### static `primeAuthors(mixed $posts): void`

The posts' authors read into the caches together (update_post_author_caches), through cache_users, which a plugin may replace.

### static `prime(mixed $ids, mixed $updateTermCache = true, mixed $updateMetaCache = true): void`

Posts read into the caches together (_prime_post_caches): rows, and their meta and terms unless asked not to.

### static `updateCaches(mixed $posts, mixed $type = 'post', mixed $updateTermCache = true, mixed $updateMetaCache = true): void`

A list of posts kept in the caches (update_post_caches): whole posts kept as they are, then primed.

### static `idFromUrl(mixed $url): int`

The post an address names (url_to_postid): a ?p= or ?page_id= id, else
with pretty permalinks a page by its path, a post whose own address it
is, or an attachment by its own; 0 for none.

Internals: `filledArguments()` (private, line 157), `chosenPosts()` (private, line 175), `termsPrimed()` (private, line 290), `idList()` (private, line 370), `store()` (private, line 375)


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

- const `FIELDS` = `array (   'post_title' => 'Title',   'post_content' => 'Content',   'post_excerpt' => 'Excerpt', )` — The revision fields' defaults: the title, the content and the excerpt.

Used by: `Minn\Runtime\Autosaves`, `Minn\Runtime\PostWrites`

### static `all(mixed $post = 0, mixed $args = NULL): array`

A post's revisions (wp_get_post_revisions), newest first unless asked
otherwise, keyed by id (or a list of ids); none while its revisions are
switched off, unless check_enabled is false.

- `@return array<int, mixed>`

### static `toKeep(mixed $post): int`

How many revisions a post keeps (wp_revisions_to_keep): WP_POST_REVISIONS
(all, -1, when unset or true), none for a type without revision
support, then wp_revisions_to_keep and wp_{type}_revisions_to_keep.

### static `enabled(mixed $post): bool`

Whether a post keeps any revisions (wp_revisions_enabled).

### static `delete(mixed $revision): mixed`

A revision deleted (wp_delete_post_revision); anything but a revision is left alone, and nothing comes back.

### static `fields(mixed $post = array ( ), mixed $deprecated = false): array`

The fields a revision keeps (_wp_post_revision_fields), through
_wp_post_revision_fields; the filtered list is what the next call
starts from, as on the reference.

- `@return array<string, mixed>`

### static `data(mixed $post = array ( ), mixed $autosave = false): array`

A revision's row (_wp_post_revision_data): the post's revision fields,
its parent, inherit, revision, its name, and the post's modified time
as its date.

- `@return array<string, mixed>`

### static `source(mixed $post): array`

A post as a revision's row is made from: its own fields, as an (array)
cast gives them.

- `@return array<string, mixed>`

### static `upgrade(Minn\Objects\Post $post, array $revisions): bool`

Revisions named before 3.6 brought up to date
(_wp_upgrade_revisions_of_post; probe revision-more), under the
post's lock (Runtime\OptionLock), let go when the renaming is done.
Newest first, each old revision takes its versioned name when its
name has the "{id}-revision" or "{id}-autosave" shape, and the author
of the old revision before it (the oldest keeps its own). Unless a
revision was already up to date, the post is then saved as a revision
as a save would save it. False while another upgrade holds the lock.

- `@param list<object> $revisions newest first`

### static `save(Minn\Objects\Post $post): ?int`

The new revision's id, or null when none was called for.

### static `put(array $data): ?int`

The revision (or autosave) row _wp_post_revision_data made, written
through wp_insert_post, then _wp_put_post_revision; its id.

- `@param array<string, mixed> $data`

Internals: `version()` (private, line 177), `versionedName()` (private, line 183), `changed()` (private, line 211), `prune()` (private, line 240)


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
- const `PREPARED` = `array (   'post_title' => 'title',   'post_content' => 'content',   'post_excerpt' => 'excerpt',   'post_type' => NULL,   'post_status' => 'status',   'post_date' => 'date',   'post_date_gmt' => 'date',   'edit_date' => 'date',   'post_name' => 'slug',   'post_author' => 'author',   'post_password' => 'password',   'post_parent' => 'parent',   'menu_order' => 'menu_order',   'comment_status' => 'comment_status',   'ping_status' => 'ping_status', )` — The prepared post's columns in the reference's order, each with the REST field that sends it (null: always there).
- const `UNSLUGGED` = `array (   0 => 'draft',   1 => 'pending',   2 => 'auto-draft', )`
- const `PARENT` = `'post_parent'`
- const `NEW_POSTARR` = `array (   0 => 'ID',   1 => 'post_author',   2 => 'post_date',   3 => 'post_date_gmt',   4 => 'post_content',   5 => 'post_content_filtered',   6 => 'post_title',   7 => 'post_excerpt',   8 => 'post_status',   9 => 'post_type',   10 => 'comment_status',   11 => 'ping_status',   12 => 'post_password',   13 => 'to_ping',   14 => 'pinged',   15 => 'post_parent',   16 => 'menu_order',   17 => 'guid',   18 => 'import_id', )` — The new post array wp_insert_post_parent is handed beside the whole one.
- const `DEFAULTS` = `array (   'post_author' => 0,   'post_content' => '',   'post_content_filtered' => '',   'post_title' => '',   'post_excerpt' => '',   'post_status' => 'draft',   'post_type' => 'post',   'comment_status' => '',   'ping_status' => '',   'post_password' => '',   'to_ping' => '',   'pinged' => '',   'post_parent' => 0,   'menu_order' => 0,   'guid' => '',   'import_id' => 0,   'context' => '',   'post_date' => '',   'post_date_gmt' => '', )` — wp_insert_post's defaults, in its order (the order its filters run in).

Used by: `Minn\Rest\PostsWriteController`, `Minn\Runtime\PostWrites`

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

Internals: `stored()` (private, line 92), `prepared()` (private, line 202), `changed()` (private, line 245)


## PostTags

`final class Minn\Runtime\PostTags` · `public/minn/src/Minn/Runtime/PostTags.php`

What the post template tags tell about a post (the one being read when
none is named): its id and title, whether a password stands before it,
whether comments and pings are open, how many comments it has, and its
featured image; each through the filter plugins hook there.

Used by: `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Content\MediaShortcodes`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\PostEmbed`, `Minn\Runtime\Attachments`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentTags`, `Minn\Runtime\DocumentTitle`, `Minn\Runtime\MetaData`, `Minn\Runtime\PostData`, `Minn\Theme\FrontLifecycle`

### static `time(mixed $format = 'U', mixed $gmt = false, mixed $post = NULL, mixed $translate = false): mixed`

When a post was published (get_post_time): a timestamp for "U" or
"G", else the date in the format, local or UTC (a GMT date not set
yet worked out from the local one), names translated when asked;
false for no post.

### static `modifiedTime(mixed $format = 'U', mixed $gmt = false, mixed $post = NULL, mixed $translate = false): mixed`

When a post last changed (get_post_modified_time), as get_post_time tells when it was published.

### static `authorMeta(mixed $field = '', mixed $userId = false): mixed`

One of the author's fields (get_the_author_meta): the post's author
being read unless a user is named; ID as an integer, the user_ columns
by their short names, empty for none; as get_the_author_{field}
leaves it.

### static `authorPostsUrl(mixed $authorId, mixed $nicename = ''): mixed`

An author's archive address (get_author_posts_url): /author/{nicename} with pretty permalinks, its slash as user_trailingslashit leaves it, else ?author=; as author_link leaves it.

### static `titleAttribute(mixed $args = ''): mixed`

A post's title made safe for an attribute (the_title_attribute), its
tags gone with those of before and after; printed unless echo is
false, null for no title.

### static `date(mixed $format = '', mixed $post = NULL): mixed`

When a post was published, in the format given or the site's date format (get_the_date), as get_the_date leaves it; false for no post.

### static `passwordForm(mixed $post = 0): mixed`

The password form (get_the_password_form), as the content shows it
before its paragraphs are made; a reader the post's own page sent back
with a password cookie set reads why above the field; as
the_password_form leaves it.

### static `id(): mixed`

The id of the post being read (get_the_ID), or false.

### static `title(mixed $post = 0): mixed`

A post's title (get_the_title): outside the admin a protected or
private one says so (protected_title_format, private_title_format);
then the_title. Empty for no post.

### static `passwordRequired(mixed $post = NULL): mixed`

Whether a password stands before the post's content for this reader (post_password_required).

### static `commentsOpen(mixed $post = NULL): bool`

Whether comments are open on a post (comments_open).

### static `pingsOpen(mixed $post = NULL): bool`

Whether pings are open on a post (pings_open).

### static `thumbnailId(mixed $post = NULL): mixed`

The id of a post's featured image (get_post_thumbnail_id), 0 for none, false for no post.

### static `hasThumbnail(mixed $post = NULL): bool`

Whether a post has a featured image (has_post_thumbnail).

### static `commentsNumber(mixed $post = 0): mixed`

How many comments a post has (get_comments_number), 0 for none.

Internals: `moment()` (private, line 56)


## PostWrites

`final class Minn\Runtime\PostWrites` · `public/minn/src/Minn/Runtime/PostWrites.php`

Posts written as WordPress's post functions write them: a post stored
and changed (its columns through their filters, then its terms, meta and
template), published, sent to the trash and back, deleted with what
hangs off it, its terms and categories set, made sticky or not; each step
with the actions the reference fires in its order, and the cached rows
let go.

Used by: `Minn\Cron\Cron`, `Minn\Rest\FontWritesController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\MediaController`, `Minn\Rest\TemplateRevisionsController`, `Minn\Runtime\Attachments`, `Minn\Runtime\Autosaves`, `Minn\Runtime\NavigationFallback`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\UserStylesPost`, `Minn\Runtime\UserWrites`, `Minn\Theme\TemplateWriter`

### static `insert(mixed $postarr, mixed $wpError = false, mixed $fireAfterHooks = true): mixed`

A post stored or changed (wp_insert_post): its id, 0 or the error
when it cannot be; the revision is saved from wp_after_insert_post
when the after hooks fire.

### static `update(mixed $postarr = array ( ), mixed $wpError = false, mixed $fireAfterHooks = true): mixed`

A post changed (wp_update_post): the stored post under the given
fields, as the reference merges them; an attachment saved as one.

### static `delete(mixed $postId = 0, mixed $force = false): mixed`

A post deleted (wp_delete_post): a post or page to the trash first
unless forced or the trash is off; an attachment as one; otherwise
unstuck, its terms, children, revisions, comments and meta taken
away, then the row. The post, or what pre_delete_post answered.

### static `trash(mixed $postId = 0): mixed`

A post to the trash (wp_trash_post): its status, time and slug kept for the way back; the post as it was, or false.

### static `untrash(mixed $postId = 0): mixed`

A post back from the trash (wp_untrash_post): to draft unless
wp_untrash_post_status says otherwise (an attachment to inherit), its
slug given back; the post as it was, or false.

### static `afterInsert(mixed $post, mixed $update, mixed $postBefore): void`

What plugins hear once a post and all it carries are saved (wp_after_insert_post).

### static `publish(mixed $post): void`

A post published now (wp_publish_post), a floating date settled, with the save's actions.

### static `publishFuture(mixed $post): void`

The publish_future_post event's work (check_and_publish_future_post): a scheduled post whose time has come goes out; one early is scheduled again.

### static `transitionStatus(mixed $newStatus, mixed $oldStatus, mixed $post): void`

The status change announced (wp_transition_post_status). The named
hooks fire only for names that exist, so a stored status cannot forge
another action's name: {old}_to_{new} when the new status is
registered and the old one is too (or is "new"), {new}_{type} when the
new status and the post type are registered.

### static `setCategories(mixed $postId = 0, mixed $categories = array ( ), mixed $append = false): mixed`

A post's categories set (wp_set_post_categories), as
PostInsert::categoryIds decides them; a lone blank changes nothing.

### static `setTerms(mixed $postId = 0, mixed $terms = '', mixed $taxonomy = 'post_tag', mixed $append = false): mixed`

A post's terms of a taxonomy set (wp_set_post_terms): a comma list split, a hierarchical taxonomy's terms by id.

### static `setTags(mixed $postId = 0, mixed $tags = '', mixed $append = false): mixed`

A post's tags set (wp_set_post_tags).

### static `stick(mixed $postId): void`

A post made sticky (stick_post), then post_stuck; nothing when it already is.

### static `unstick(mixed $postId): void`

A post no longer sticky (unstick_post), then post_unstuck; nothing when it was not.

### static `uniqueSlug(mixed $slug, mixed $postId, mixed $status, mixed $type, mixed $parent): mixed`

A slug no other post holds (wp_unique_post_slug), through the reference's slug filters; drafts, pending posts and revisions keep theirs.

### static `suffixTrashed(mixed $postName, mixed $postId = 0): void`

Every trashed post holding a slug (but the one named) given the __trashed suffix, so a live post may take it (wp_add_trashed_suffix_to_post_name_for_trashed_posts).

### static `suffixTrashedPost(mixed $post): mixed`

A post's slug given the __trashed suffix once, the slug it wanted kept (wp_add_trashed_suffix_to_post_name_for_post); the slug it now has.

### static `beforeSave(array $data, int $postId = 0): void`

What plugins hear before a post's row is written: pre_post_insert for a new post, pre_post_update for one that exists. @param array<string, mixed> $data

- `@param array<string, mixed> $data`

### static `saved(int $postId, mixed $update, ?Minn\Objects\Post $postBefore): ?Minn\Objects\Post`

What plugins hear once a post's row and its own terms are written, in
the reference's order: the caches let go, the status transition, the
edit actions for an update, the save actions.

### static `deleteMeta(int $postId): void`

Each meta row of a post removed by its id, with the hooks of each.

Internals: `slugFromId()` (private, line 382), `stickies()` (private, line 393), `freeSlug()` (private, line 400), `templateRefusal()` (private, line 424), `inputs()` (private, line 441), `template()` (private, line 475), `attachmentSaved()` (private, line 497), `trashSaved()` (private, line 539), `remove()` (private, line 562), `inserter()` (private, line 578), `writer()` (private, line 591)


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


## QueryAnswers

`final class Minn\Runtime\QueryAnswers` · `public/minn/src/Minn/Runtime/QueryAnswers.php`

Answers for queries the engine writes itself and already knows the result
of, from what it has read once this request: a template part's lookup
from the theme's saved templates, the theme's term from the same read. The
query still runs every filter the reference runs; only the database is
spared, and only while no plugin has changed the query (its variables, a
clause, the request), so a lookup a plugin steers runs as written.

Used by: `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Runtime\PostQuery`, `Minn\Runtime\TaxTerms`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryRunner`


### static `expect(object $query, Closure $answer): void`

Marks a query object as one the engine can answer, before it runs.

### static `take(object $query): ?Closure`

The answer a query object was given, taken so it answers once; null for any other query.


## QueryArgs

`final class Minn\Runtime\QueryArgs` · `public/minn/src/Minn/Runtime/QueryArgs.php`

Query arguments added to and taken from an address the way WordPress's
add_query_arg() and remove_query_arg() do, the current request's address
when none is given, and an action's nonce added to one.

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\MediaShortcodes`, `Minn\Front\CommentPostController`, `Minn\Front\FeedTags`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMenus`, `Minn\Rest\Api`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\CommentPages`, `Minn\Runtime\EditLinks`, `Minn\Runtime\PostLinks`, `Minn\Runtime\SiteUrls`

### static `add(mixed ...$args): string`

An address with arguments added (add_query_arg): a key and value, or a
list of them, then the address (the request's when false or left
out). Values are URL-encoded; an empty one leaves its key bare, a
false or null one takes the key out.

### static `versioned(string $src, string $version): string`

A script's or style's address with its version, as the dependency
printers add it: the same address add_query_arg would give, its
query read without wp_parse_str's filter (probe: printing a
versioned script fires none on the reference).

### static `remove(mixed $key, mixed $query = false): mixed`

An address with an argument, or each of a list, taken out (remove_query_arg); an empty list gives back what was given.

### static `withNonce(mixed $actionUrl, mixed $action = -1, mixed $name = '_wpnonce'): string`

An address carrying a nonce for the action (wp_nonce_url), escaped for HTML.

### static `requestUri(): string`

The request's address as PHP was given it; a runtime built without one gives its request's path and query.

Internals: `with()` (private, line 57)


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

### static `derive(array $vars, Minn\Runtime\Registry $registry, callable $option, array $asked): self`

The is_* flags the query vars amount to.

- `@param array<string, mixed> $vars the filled query variables`
- `@param callable(string): mixed $option a filtered option read`
- `@param array<string, mixed> $asked the query as it was given, before filling (a search is one that named s)`

### static `customTaxonomyVar(array $vars, Minn\Runtime\Registry $registry): ?array`

The first registered taxonomy (other than the two built-in ones) whose
query variable carries a value, as [taxonomy name, query var].

- `@return array{0: string, 1: string}|null`

Internals: `sanitize()` (private, line 125), `archiveFlags()` (private, line 160)


## RecipientLocale

`final class Minn\Runtime\RecipientLocale` · `public/minn/src/Minn/Runtime/RecipientLocale.php`

An email written in its reader's language, as the reference writes each:
the send run inside switch_to_user_locale for the account it goes to (the
site's locale when the address belongs to none) and the locale restored
after. The facade binds how a switch is made (WP_Locale_Switcher).

Used by: `Minn\Runtime\AccountFlows`, `Minn\Runtime\UserWrites`


### static `bind(Closure $switcher): void`

How a switch is made (the facade's switch_to_user_locale and restore_previous_locale).

### static `forUser(int $userId, Closure $send): mixed`

What a send answers, run in a user's language (the site's for 0).

### static `forAddress(string $email, Closure $send): mixed`

What a send answers, run in the language of the account an address belongs to.

### static `forSite(Closure $send): mixed`

What a send to the site's address (admin_email) answers, run in its account's language.


## Records

`final class Minn\Runtime\Records` · `public/minn/src/Minn/Runtime/Records.php`

Posts, terms and comments as WordPress hands them to plugins: found by
id (or the one being read), made from a row or an object, and given back
as the object, an associative array (ARRAY_A) or a list (ARRAY_N); and
whether a value is a list of errors.

Used by: `Minn\Blocks\Dynamic\Theme\NavigationParts`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Cli\CronCommand`, `Minn\Content\MediaShortcodes`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\PostEmbed`, `Minn\Media\Icons`, `Minn\Rest\BatchController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\CommentObject`, `Minn\Rest\MenuLists`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\RenderedFields`, `Minn\Rest\RestMeta`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TermFilters`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Runtime\AbilityRun`, `Minn\Runtime\AttachmentImages`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\Attachments`, `Minn\Runtime\BlockFilters`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentFeedQuery`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentPages`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\CommentTags`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\CronEvents`, `Minn\Runtime\EditLinks`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenu`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostCaches`, `Minn\Runtime\PostData`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryWhere`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostSave`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermTags`, `Minn\Runtime\TermWrites`, `Minn\Runtime\UserStylesPost`, `Minn\Runtime\UserWrites`, `Minn\Runtime\WpdbFields`

### static `post(mixed $post = NULL, mixed $output = 'OBJECT', mixed $filter = 'raw'): mixed`

A post (get_post): the one being read when none is named; a post
object as given; one made from a row or any other object (one without
an ID is a post of none); none for an array; else by id, a copy of the
cached one (what one plugin changes is not what the next reader sees).
Then put in the context asked for.

### static `term(mixed $term, mixed $taxonomy = '', mixed $output = 'OBJECT', mixed $filter = 'raw'): mixed`

A term (get_term): an error for none or an unknown taxonomy; a term
object as given, one made from an object, else by id (null when there
is none). get_term and get_{taxonomy} filter the raw term, told its own
taxonomy; then it is put in the context asked for, on a copy of a term
the caller handed in.

### static `comment(mixed $comment = NULL, mixed $output = 'OBJECT'): mixed`

A comment (get_comment): the one being read for any empty id; a
comment object as given; one made from an object with a comment_ID;
else by id, its fields text as the database hands them over (null when
there is none), the row read once a request as a comment query keeps
it. Then get_comment.

### static `isError(mixed $thing): bool`

Whether a value is a list of errors (is_wp_error); one that is is told to is_wp_error_instance.

Internals: `termNumbers()` (private, line 144), `shaped()` (private, line 153)


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

Used by: `Minn\Cli\MinnCommand`, `Minn\Engine`, `Minn\Front\ToolbarMenus`, `Minn\Runtime\Options`, `Minn\Runtime\Plugins`

```php
__construct(Minn\Content\Site $site, string $contentDir)
```


### static `inMode(): bool`

Whether the site is in recovery mode (wp_is_recovery_mode): never, as the engine keeps no recovery-mode session; its own recovery pauses the failing plugin instead.

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

### `paused(string $kind): array`

The extensions of one kind ('plugin' or 'theme') recovery has paused.

- `@return array<string, array<string, mixed>>`

### `resumeKind(string $kind): int`

Lets every extension of one kind load again. Returns how many were released.

### `resume(string $kind, string $name): bool`

Lets an extension load again. Returns false when it was not paused.

### `resumeAll(): int`

Lets everything load again, every strike forgotten as resume() forgets one's. Returns how many were released.

Internals: `pluginFile()` (private, line 97), `activePlugins()` (private, line 111), `strikes()` (private, line 168), `forgetStrikes()` (private, line 175), `read()` (private, line 251), `write()` (private, line 259)


## Refusal

`final readonly class Minn\Runtime\Refusal` · `public/minn/src/Minn/Runtime/Refusal.php`

A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error.

Used by: `Minn\Blocks\BlockName`, `Minn\Content\Menus`, `Minn\Content\PluginState`, `Minn\Ops\Unzip`, `Minn\Rest\ArgCheck`, `Minn\Rest\BlockRendererController`, `Minn\Rest\FontSettings`, `Minn\Rest\MenusController`, `Minn\Rest\ParamCheck`, `Minn\Rest\PluginsController`, `Minn\Rest\RouteMatch`, `Minn\Rest\Schema`, `Minn\Rest\SchemaChoices`, `Minn\Rest\SiteSchema`, `Minn\Runtime\Connectors`, `Minn\Runtime\Patterns`, `Minn\Runtime\PluginActivation`, `Minn\Runtime\PluginRequirements`, `Minn\Runtime\UserInsert`, `Minn\Runtime\UserSave`, `Minn\Runtime\UserWrites`

```php
__construct(string $code, string $message, mixed $data = NULL)
```

- readonly `string $code`
- readonly `string $message`
- readonly `mixed $data`


## Registered

`final class Minn\Runtime\Registered` · `public/minn/src/Minn/Runtime/Registered.php`

What is registered, looked up the way WordPress's lookups answer: post
types, post statuses and taxonomies by name, or listed by their flags (as
names or the objects); whether one exists, is hierarchical or viewable;
and the features a post type supports. A name that is not text or a
number finds nothing.

Used by: `Minn\Auth\Capabilities`, `Minn\Auth\RegisteredCaps`, `Minn\Content\PostSlugs`, `Minn\Front\ToolbarMenus`, `Minn\Rest\BatchController`, `Minn\Rest\Caller`, `Minn\Rest\Embed`, `Minn\Rest\MenuLists`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostCollectionParams`, `Minn\Rest\PostListArgs`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredFields`, `Minn\Rest\RegisteredType`, `Minn\Rest\SearchController`, `Minn\Rest\StatusesController`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\Types`, `Minn\Rest\UserCollectionParams`, `Minn\Rest\UsersController`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\Attachments`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\BlockQueries`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentForm`, `Minn\Runtime\DocumentTitle`, `Minn\Runtime\EditLinks`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\ObjectTerms`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostQueryTax`, `Minn\Runtime\PostQueryWhere`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostSave`, `Minn\Runtime\PostWrites`, `Minn\Runtime\Records`, `Minn\Runtime\RewriteRules`, `Minn\Runtime\TaxTerms`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermTags`, `Minn\Runtime\TermWrites`, `Minn\Runtime\UserCounts`, `Minn\Runtime\UserQueryRunner`, `Minn\Theme\FrontLifecycle`, `Minn\Widgets\WidgetForms`

### static `postType(mixed $type): ?object`

A post type's object (get_post_type_object), or null.

### static `postTypes(mixed $args = array ( ), mixed $output = 'names', mixed $operator = 'and'): array`

The post types whose flags match (get_post_types): all of them (and),
any (or) or none (not), as names or as the shared objects, so a flag a
plugin changed on one is what the list reads.

- `@return array<string, mixed>`

### static `postTypeExists(mixed $type): bool`

Whether a post type is registered (post_type_exists).

### static `supports(mixed $type, mixed $feature): bool`

Whether a post type supports a feature (post_type_supports).

### static `hierarchicalType(mixed $type): bool`

Whether a post type is hierarchical (is_post_type_hierarchical).

### static `viewableType(mixed $type): bool`

Whether a post type, by name or its object, can be viewed on the site (is_post_type_viewable), as the filter leaves it.

### static `status(mixed $status): ?object`

A post status's object (get_post_status_object), or null.

### static `statuses(mixed $args = array ( ), mixed $output = 'names', mixed $operator = 'and'): array`

The post statuses whose flags match (get_post_stati), as names or objects.

- `@return array<string, mixed>`

### static `viewableStatus(mixed $status): bool`

Whether posts in a status, by name or its object, can be viewed on the
site (is_post_status_viewable): never an internal or protected one;
then the filter.

### static `taxonomy(mixed $taxonomy): object|false`

A taxonomy's object (get_taxonomy), or false.

### static `taxonomies(mixed $args = array ( ), mixed $output = 'names', mixed $operator = 'and'): array`

The taxonomies whose flags match (get_taxonomies), as names or objects.

- `@return array<string, mixed>`

### static `taxonomyExists(mixed $taxonomy): bool`

Whether a taxonomy is registered (taxonomy_exists).

### static `hierarchicalTaxonomy(mixed $taxonomy): bool`

Whether a taxonomy is hierarchical (is_taxonomy_hierarchical).

### static `viewableTaxonomy(mixed $taxonomy): bool`

Whether a taxonomy, by name or its object, can be queried on the site (is_taxonomy_viewable).

### static `objectInTaxonomy(mixed $objectType, mixed $taxonomy): bool`

Whether a taxonomy is registered for an object type (is_object_in_taxonomy).

### static `objectTaxonomies(mixed $objectType, mixed $output = 'names'): array`

The taxonomies registered for any of the object types given
(get_object_taxonomies): a list of names, or the objects by name.

- `@return array<array-key, mixed>`

### static `forAttachments(mixed $output = 'names'): array`

The taxonomies registered for attachments, of any file type
(get_taxonomies_for_attachments): a list of names, or the objects by name.

- `@return array<array-key, mixed>`

Internals: `listed()` (private, line 198)


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
- const `CORE` = `array (   'blogname' =>    array (     0 => 'general',     1 => 'string',     2 => 'Title',     3 => 'Site title.',     4 =>      array (       'name' => 'title',     ),   ),   'blogdescription' =>    array (     0 => 'general',     1 => 'string',     2 => 'Tagline',     3 => 'Site tagline.',     4 =>      array (       'name' => 'description',     ),   ),   'siteurl' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'Site URL.',     4 =>      array (       'name' => 'url',       'schema' =>        array (         'format' => 'uri',       ),     ),   ),   'admin_email' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'This address is used for admin purposes, like new user notification.',     4 =>      array (       'name' => 'email',       'schema' =>        array (         'format' => 'email',       ),     ),   ),   'timezone_string' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'A city in the same timezone as you.',     4 =>      array (       'name' => 'timezone',     ),   ),   'date_format' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'A date format for all date strings.',     4 => true,   ),   'time_format' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'A time format for all time strings.',     4 => true,   ),   'start_of_week' =>    array (     0 => 'general',     1 => 'integer',     2 => '',     3 => 'A day number of the week that the week should start on.',     4 => true,   ),   'WPLANG' =>    array (     0 => 'general',     1 => 'string',     2 => '',     3 => 'WordPress locale code.',     4 =>      array (       'name' => 'language',     ),     5 => 'en_US',   ),   'use_smilies' =>    array (     0 => 'writing',     1 => 'boolean',     2 => '',     3 => 'Convert emoticons like :-) and :-P to graphics on display.',     4 => true,     5 => true,   ),   'default_category' =>    array (     0 => 'writing',     1 => 'integer',     2 => '',     3 => 'Default post category.',     4 => true,   ),   'default_post_format' =>    array (     0 => 'writing',     1 => 'string',     2 => '',     3 => 'Default post format.',     4 => true,   ),   'posts_per_page' =>    array (     0 => 'reading',     1 => 'integer',     2 => 'Maximum posts per page',     3 => 'Blog pages show at most.',     4 => true,     5 => 10,   ),   'show_on_front' =>    array (     0 => 'reading',     1 => 'string',     2 => 'Show on front',     3 => 'What to show on the front page',     4 => true,   ),   'page_on_front' =>    array (     0 => 'reading',     1 => 'integer',     2 => 'Page on front',     3 => 'The ID of the page that should be displayed on the front page',     4 => true,   ),   'page_for_posts' =>    array (     0 => 'reading',     1 => 'integer',     2 => '',     3 => 'The ID of the page that should display the latest posts',     4 => true,   ),   'default_ping_status' =>    array (     0 => 'discussion',     1 => 'string',     2 => '',     3 => 'Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.',     4 =>      array (       'schema' =>        array (         'enum' =>          array (           0 => 'open',           1 => 'closed',         ),       ),     ),   ),   'default_comment_status' =>    array (     0 => 'discussion',     1 => 'string',     2 => 'Allow comments on new posts',     3 => 'Allow people to submit comments on new posts.',     4 =>      array (       'schema' =>        array (         'enum' =>          array (           0 => 'open',           1 => 'closed',         ),       ),     ),   ), )` — Core's settings: name => [group, type, label, description, show_in_rest, default when it has one].

Used by: `Minn\Rest\LiveSettings`, `Minn\Runtime\OptionSanitizer`

### static `addError(mixed $setting, mixed $code, mixed $message, mixed $type = 'error'): void`

A settings error noted for the settings screen to show (add_settings_error).

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

Used by: `Minn\Front\Permalinks`, `Minn\Rest\StatusesController`, `Minn\Runtime\QueriedObject`, `Minn\Runtime\QueryFlags`, `Minn\Runtime\Runtime`, `Minn\Runtime\TypeProps`

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

### `shareObjects(): void`

Makes the one object for each registered type and taxonomy and binds
$wp_post_types and $wp_taxonomies to them, as the reference shares
them: a plugin changing one (relabelling Posts) is heard by every
reader (probe type-object-sharing). Called once the facade classes
exist; later registrations make their own.

### `postTypeObject(string $name): ?Minn\Objects\PostType`

The one object for a registered post type, or null.

### `postTypeObjects(): array`

The objects of every registered post type, in registration order.

- `@return array<string, PostType>`

### `taxonomyObject(string $name): ?Minn\Objects\Taxonomy`

The one object for a registered taxonomy, or null.

### `taxonomyObjects(): array`

The objects of every registered taxonomy, in registration order.

- `@return array<string, Taxonomy>`

### `statuses(): array`

Every post status.

- `@return array<string, array<string, mixed>>`

### `status(string $name): ?array`

One post status, or null.

### `translateBuiltIns(): void`

The built-in types', taxonomies' and statuses' labels in the site's
language, as the reference registers them again on init: each label
asks for its own message (data/registry-labels.json, captured from the
reference), and a type's single-item template and a taxonomy's
archive template are named after the singular name. Rows and the
shared objects both take them; what a plugin set before init gives
way, as it does there.

### `adoptPostType(Minn\Objects\PostType $object): array`

Takes a built post type as the registered one: the object
register_post_type made is the one every reader shares, and its
properties are the type's row. Registering again puts a new object in
the old one's place, as on the reference.

- `@return array<string, mixed>`

### `labels(array $labels, ?string $label, string $base, string $name): array`

A type's labels from its own (probe post-remaining): the labels of its
base type (page for a hierarchical type, post for any other) under the
type's own. A missing
name comes from the type's label, a missing singular name from the name,
the menu name from the name, all items from the menu name, archives from
all items, and the admin bar name from the singular name or else the
type's name; a type with a singular name also names its single-item
template.

- `@param array<string, mixed> $labels`
- `@return array<string, mixed>`

### `taxonomyLabels(array $labels, ?string $label, string $base, string $name): array`

A taxonomy's labels from its own (probe taxonomy-remaining): derived as a
type's are, under the category labels for a hierarchical taxonomy or the
tag labels for any other; one that names no template takes "%s
Archives" with the singular name it ends up with.

- `@param array<string, mixed> $labels`
- `@return array<string, mixed>`

### static `capabilities(array|string $capabilityType, array $overrides, bool $mapMetaCap): array`

A type's capability names (probe post-remaining): the eight primitive
ones, the six more a mapped type uses, the type's own names merged over
them, and create_posts last, the edit capability unless named.

- `@param array<string, string> $overrides`
- `@return array<string, string>`

### `unregisterPostType(string $name): bool`

Forgets a non-builtin post type.

### `addSupport(string $type, string $feature, array $args): void`

Adds a feature to a type, registered or not: the reference keeps
features apart from the type objects, so declaring one does not make
the type exist. A feature with arguments keeps all of them.

- `@param list<mixed> $args`

### `featuredTypes(): array`

The types holding features, registered or not (attachment:audio), in
the order their first feature came (get_post_types_by_support walks these).

- `@return list<string>`

### `removeSupport(string $type, string $feature): void`

Removes a feature from a post type.

### `removeSupports(string $type): void`

Removes every feature of a post type.

### `supports(string $type): array`

The features a post type supports.

- `@return array<string, mixed> feature => true or the arguments given`

### `defaultLabels(string $plain, string $hierarchical): array`

The default labels of a family as label => [the plain one's, the
hierarchical one's]: post and page for types, tag and category for
taxonomies, from the built-in data (WP_Post_Type::get_default_labels).

- `@return array<string, array{0: mixed, 1: mixed}>`

### `adoptTaxonomy(Minn\Objects\Taxonomy $object): array`

Takes a built taxonomy as the registered one, the object every reader
shares, its properties the taxonomy's row.

- `@return array<string, mixed>`

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

Internals: `rowOf()` (private, line 240), `objectFrom()` (private, line 262), `derivedLabels()` (private, line 325)


## RequestHeaders

`final class Minn\Runtime\RequestHeaders` · `public/minn/src/Minn/Runtime/RequestHeaders.php`

What WordPress's request functions say and send: the protocol the
request came over, a status line and the no-cache headers sent as plugin
code sends them, the origin a request names and whether it is allowed,
and whether the browser is a phone's.

Used by: `Minn\Front\SitemapRequest`, `Minn\Front\ToolbarMarkup`, `Minn\Media\MediaModal`, `Minn\Runtime\AjaxController`, `Minn\Runtime\PostTags`, `Minn\Theme\FrontLifecycle`

### static `protocol(): string`

The protocol the request came over (wp_get_server_protocol): HTTP/1.1, 2, 2.0 or 3 as the server says, anything else HTTP/1.0.

### static `status(mixed $code, mixed $description = ''): void`

A status line sent (status_header), through status_header; nothing for
a code without a reason phrase, outside a web request, or once the
head is out.

### static `fromServer(array $server): array`

A request's headers from server variables (WP_REST_Server::get_headers):
the HTTP_ ones without their prefix, CONTENT_TYPE, CONTENT_LENGTH and
CONTENT_MD5 as named, and REDIRECT_HTTP_AUTHORIZATION as AUTHORIZATION
when there is no HTTP_AUTHORIZATION.

- `@param array<array-key, mixed> $server`
- `@return array<string, mixed>`

### static `noCache(): void`

The no-cache headers sent now (nocache_headers); one set to false (Last-Modified) is taken off instead.

### static `origin(): mixed`

The origin a request names (get_http_origin) as PHP sees it, else the request's own, as http_origin leaves it.

### static `allowedOrigins(): mixed`

The origins allowed to call (get_allowed_http_origins): the home and
site hosts over http and https, repeats dropped before
allowed_http_origins adds its own.

- `@return list<string>`

### static `allowedOrigin(mixed $origin = NULL): mixed`

The origin when it is one allowed (is_allowed_http_origin), '' otherwise, as allowed_http_origin leaves it.

### static `rawReferer(): mixed`

The referer as given (wp_get_raw_referer): the form's _wp_http_referer when it has one, else the Referer header, unslashed; false when neither.

### static `isMobile(): mixed`

Whether the browser is a phone's or a tablet's (wp_is_mobile), as wp_is_mobile leaves it.


## RewriteRules

`final class Minn\Runtime\RewriteRules` · `public/minn/src/Minn/Runtime/RewriteRules.php`

The rewrite rules WordPress makes from its structures, as the reference
makes them (probes rewrite-generate, rewrite-rules), so plugins that
read, filter or add to them see the same list.

A structure is walked a directory at a time (one at a time only when
asked), each level that names something getting its rules: its feeds
and embed, its pages, its comment pages (for posts and pages), a static
front page's comment pages at the root, the endpoints placed there, and
the level itself; deepest level first. The level that names a post (a
name, an id, a page path, or a plugin post type's own tag) is the post's:
its embed and trackback, its feeds, pages and comment pages, its
endpoints and its attachments', then the post with its page number,
between the rules for attachments under it (by /attachment/ and, but for
a hierarchical type, directly). A tag nobody registered stays as written.

The full list is the plugins' top rules (each permastruct's rules join
them as they are made, and stay), the fixed files, then root, comments,
search, author and dates, then pages and posts (posts first unless the
structure begins ambiguously), then the plugins' bottom rules; each
section through its filter, the whole through generate_rewrite_rules and
rewrite_rules_array.

- const `TOP` = `array (   '^wp-json/?$' => 'index.php?rest_route=/',   '^wp-json/(.*)?' => 'index.php?rest_route=/$matches[1]',   '^index.php/wp-json/?$' => 'index.php?rest_route=/',   '^index.php/wp-json/(.*)?' => 'index.php?rest_route=/$matches[1]',   '^wp-sitemap\\.xml$' => 'index.php?sitemap=index',   '^wp-sitemap\\.xsl$' => 'index.php?sitemap-stylesheet=sitemap',   '^wp-sitemap-index\\.xsl$' => 'index.php?sitemap-stylesheet=index',   '^wp-sitemap-([a-z]+?)-([a-z\\d_-]+?)-(\\d+?)\\.xml$' => 'index.php?sitemap=$matches[1]&sitemap-subtype=$matches[2]&paged=$matches[3]',   '^wp-sitemap-([a-z]+?)-(\\d+?)\\.xml$' => 'index.php?sitemap=$matches[1]&paged=$matches[2]',   '^minn-admin(/.*)?$' => 'index.php?minn_admin=1', )` — The top rules the global object starts with: the REST routes, the sitemaps and Minn Admin's own address.
- const `DEFAULTS` = `array (   'ep_mask' => 0,   'paged' => true,   'feed' => true,   'forcomments' => false,   'walk_dirs' => true,   'endpoints' => true, )`
- const `POST_BY_TIME` = `array (   0 => '%year%',   1 => '%monthnum%',   2 => '%day%',   3 => '%hour%',   4 => '%minute%',   5 => '%second%', )` — The six date and time tags that together name a post.

Used by: `Minn\Runtime\RewriteStructs`, `Minn\Runtime\ThemeSwitch`, `Minn\Theme\FrontLifecycle`

### static `flush(mixed $hard = true): void`

The rewrite rules built again and stored (flush_rewrite_rules), the server's file too when hard.

### static `numericSlugs(mixed $vars = array ( )): mixed`

A date archive's query vars read as the post they name
(wp_resolve_numeric_slug_conflicts). Under a structure with %postname%
first, second or third, the year, month or day in that place is a slug;
when a post has it, was dated in the year (and month) the address gives,
and what follows the slug is one of its pages, the vars become that
post's name and page (0 for none). Anything else stays a date archive.

- `@return array<string, mixed>`

### static `generate(Minn\Objects\Rewrite $rewrite, array $args): array`

generate_rewrite_rules: the rules for one structure.

- `@param array{struct: string, ep_mask?: int, paged?: bool, feed?: bool, forcomments?: bool, walk_dirs?: bool, endpoints?: bool} $args`
- `@return array<string, string>`

### static `all(Minn\Objects\Rewrite $rewrite): array`

rewrite_rules: every rule, assembled and filtered.

- `@return array<string, string>`

### static `unfiltered(Minn\Objects\Rewrite $rewrite): array`

The rules as the engine itself would make them, in their stored form
and with no plugin's filter or action on them: what the stored rules
are when no plugin changed them. The site's rewrite is left as it is.

- `@return array<string, string>`

### static `builtins(Minn\Objects\Rewrite $rewrite): void`

The global object's top rules (REST, sitemaps, Minn Admin) and the
built-in taxonomies' tags and patterns, set once on the site's
rewrite under the front of the structure then; a structure set later
leaves them, and a plugin's, as they were (probe registry-rewrites).

### static `typeArchive(Minn\Objects\Rewrite $rewrite, string $name, array $type): array`

A post type's archive rules, which join the top rules as it registers
(probe cpt-rules): its archive slug (the rewrite slug when the archive
is just true) under the front of the structure, or the root when the
type opts out of the front; the archive, its two feed rules when the
type has feeds, its pages when it has those.

- `@param array<string, mixed> $type the registered type`
- `@return array<string, string>`

### static `verbosePages(string $structure): bool`

Whether a structure begins with a tag that could be a page's path too (use_verbose_page_rules).

Internals: `assemble()` (private, line 176), `queries()` (private, line 263), `namesPost()` (private, line 275), `dirMask()` (private, line 291), `plainLevel()` (private, line 308), `postLevel()` (private, line 325), `attachments()` (private, line 351), `feeds()` (private, line 372), `pagesAndComments()` (private, line 387), `endpoints()` (private, line 410)


## RewriteStructs

`final class Minn\Runtime\RewriteStructs` · `public/minn/src/Minn/Runtime/RewriteStructs.php`

The rewrite object's state and the structures it builds links and rules
from (probe db-mail-rewrite-doubts): init reads the permalink structure
and clears the bottom, external and endpoint rules while the top rules
stay; each structure (date, author, search, page, feed, comment feed) is
the one already set on the object when there is one, else made once and
kept; year and month come from the date structure with its empty steps
folded; a removed tag leaves its place empty.

- const `MADE` = `array (   'author' =>    array (     0 => 'author_structure',     1 => 'front',     2 =>      array (       0 => 'author_base',     ),     3 => '/%author%',   ),   'search' =>    array (     0 => 'search_structure',     1 => 'root',     2 =>      array (       0 => 'search_base',     ),     3 => '/%search%',   ),   'page' =>    array (     0 => 'page_structure',     1 => 'root',     2 =>      array (     ),     3 => '%pagename%',   ),   'feed' =>    array (     0 => 'feed_structure',     1 => 'root',     2 =>      array (       0 => 'feed_base',     ),     3 => '/%feed%',   ),   'comment_feed' =>    array (     0 => 'comment_feed_structure',     1 => 'root',     2 =>      array (       0 => 'comments_base',       1 => 'feed_base',     ),     3 => '/%feed%',   ), )` — The structure each getter keeps, by name, and how it is made from the front or the root.

### static `init(Minn\Objects\Rewrite $rewrite): void`

The object set up from the stored permalink structure (WP_Rewrite::init).

### static `date(Minn\Objects\Rewrite $rewrite): string|false`

The date structure: the one set, else the structure's own order of
year, month and day (under date/ when a post id is among its first
three tags, as it would read as a date); false without permalinks.

### static `dateWithout(Minn\Objects\Rewrite $rewrite, array $tags): string|false`

The date structure without the given tags, its empty steps folded into one slash.

### static `made(Minn\Objects\Rewrite $rewrite, string $name): string|false`

One of the named structures (author, search, page, feed, comment_feed): the one set, else made once and kept; false without permalinks.

### static `removeTag(Minn\Objects\Rewrite $rewrite, mixed $tag): void`

A rewrite tag removed, its place in each list left empty.

### static `permastruct(Minn\Objects\Rewrite $rewrite, mixed $struct, array $legacy): array`

A permanent structure's arguments: an array as given, or the old
signature's with_front and ep_mask, over the defaults; the structure
under the front or the root.

- `@param list<mixed> $legacy the arguments after the structure, as given`
- `@return array<string, mixed>`

### static `setBase(Minn\Objects\Rewrite $rewrite, string $option, mixed $value): void`

Sets a base option and starts the object over, only when the value changes.


## Runtime

`final class Minn\Runtime\Runtime` · `public/minn/src/Minn/Runtime/Runtime.php`

The WordPress runtime the engine offers plugin code: the procedural
facade under minn/wp-api/ plus the services it delegates to. One per
request; the facade reaches it through these statics.

- const `CONTENT_DONE` = `array (   'apply_block_hooks_to_content_from_post_object' => 8,   'do_blocks' =>    array (     0 => 9,     1 => '_minn_do_added_blocks',   ),   'wptexturize' => 10,   'wpautop' => 10,   'shortcode_unautop' => 10,   'wp_filter_content_tags' =>    array (     0 => 12,     1 => '_minn_content_img_tag',   ), )` — The the_content defaults the engine's own rendering has already done:
blocks, texturize, paragraphs, block hooks, and the image attributes.
Shortcodes run in their place in the chain (do_shortcode at 11), so a
shortcode a plugin's filter adds before then runs as on the reference,
and block markup a filter adds before do_blocks is rendered then. What
the engine has not done (an attachment's own link, smilies, the capital
P, insecure home addresses) runs with the plugins' own callbacks.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\LegacyPackController`, `Minn\Admin\PackagesController`, `Minn\Admin\SiteHealthTests`, `Minn\Admin\ThemesController`, `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticator`, `Minn\Auth\Can`, `Minn\Auth\Capabilities`, `Minn\Auth\LoginThrottle`, `Minn\Auth\Nonce`, `Minn\Auth\PasswordReset`, `Minn\Auth\RegisteredCaps`, `Minn\Auth\Salts`, `Minn\Auth\SignIn`, `Minn\Blocks\Bindings`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\NavigationParts`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Elements`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Layout`, `Minn\Blocks\Parser`, `Minn\Blocks\RenderState`, `Minn\Blocks\Renderer`, `Minn\Cli\CronCommand`, `Minn\Cli\Runtime`, `Minn\Content\Blocks`, `Minn\Content\MediaShortcodes`, `Minn\Content\PasswordGate`, `Minn\Content\PluginState`, `Minn\Content\PostSlugs`, `Minn\Content\PostWriter`, `Minn\Content\Reader`, `Minn\Content\Site`, `Minn\Content\Terms`, `Minn\Content\Texturize`, `Minn\Content\Users`, `Minn\Cron\Cron`, `Minn\Db`, `Minn\Engine`, `Minn\Extension\Extensions`, `Minn\Front\CommentPostController`, `Minn\Front\CustomLogo`, `Minn\Front\EmbedCard`, `Minn\Front\EntryScriptsController`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\FrontController`, `Minn\Front\LinksOpml`, `Minn\Front\Permalinks`, `Minn\Front\PostEmbed`, `Minn\Front\ProbeController`, `Minn\Front\QueryMoves`, `Minn\Front\RequestParse`, `Minn\Front\Resolver`, `Minn\Front\RuleRoutes`, `Minn\Front\SingleQueries`, `Minn\Front\SitemapRequest`, `Minn\Front\ToolbarMarkup`, `Minn\Front\ToolbarMenus`, `Minn\Http\NoCache`, `Minn\I18n\Gettext`, `Minn\I18n\Numbers`, `Minn\I18n\SiteLanguage`, `Minn\I18n\TranslationFiles`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Login\LoginNotices`, `Minn\Mail\Mailer`, `Minn\Media\FileTypes`, `Minn\Media\Icons`, `Minn\Media\MediaModal`, `Minn\Media\Player`, `Minn\Ops\AutoUpdates`, `Minn\Ops\FilesystemCredentials`, `Minn\Ops\Updates`, `Minn\Ops\UpgraderRun`, `Minn\Query\DateSql`, `Minn\Query\TaxSql`, `Minn\Rest\Api`, `Minn\Rest\BatchController`, `Minn\Rest\BlockDirectoryController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\Caller`, `Minn\Rest\CommentObject`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\FontCollectionsController`, `Minn\Rest\FontWritesController`, `Minn\Rest\FontsController`, `Minn\Rest\IndexController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\ItemRender`, `Minn\Rest\LiveSettings`, `Minn\Rest\MediaController`, `Minn\Rest\MenuLists`, `Minn\Rest\MenusController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostCollectionParams`, `Minn\Rest\PostListArgs`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredType`, `Minn\Rest\RenderedFields`, `Minn\Rest\Responses`, `Minn\Rest\RestMeta`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TemplateRevisionsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermFilters`, `Minn\Rest\TermsController`, `Minn\Rest\Types`, `Minn\Rest\UserCollectionParams`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`, `Minn\Runtime\Abilities`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\AdminActionController`, `Minn\Runtime\AjaxController`, `Minn\Runtime\ApplicationPasswordEvents`, `Minn\Runtime\ApplicationPasswordRecords`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\AssetQueue`, `Minn\Runtime\AttachmentImages`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\Attachments`, `Minn\Runtime\Autosaves`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\BlockFilters`, `Minn\Runtime\BlockHooks`, `Minn\Runtime\BlockQueries`, `Minn\Runtime\Bookmarks`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentFeedQuery`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentPages`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\CommentReads`, `Minn\Runtime\CommentTags`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\Constants`, `Minn\Runtime\CronEvents`, `Minn\Runtime\CurrentUser`, `Minn\Runtime\Deferrals`, `Minn\Runtime\DeprecatedUserMeta`, `Minn\Runtime\DocumentTitle`, `Minn\Runtime\EditLinks`, `Minn\Runtime\FileHeaders`, `Minn\Runtime\FileNames`, `Minn\Runtime\FileUpload`, `Minn\Runtime\Heartbeat`, `Minn\Runtime\Interactivity`, `Minn\Runtime\KsesRules`, `Minn\Runtime\LegacyPack`, `Minn\Runtime\MainQuery`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\MetaData`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenu`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\NavigationFallback`, `Minn\Runtime\Notices`, `Minn\Runtime\OptionLock`, `Minn\Runtime\OptionSanitizer`, `Minn\Runtime\OptionWrites`, `Minn\Runtime\Options`, `Minn\Runtime\OptionsGroup`, `Minn\Runtime\Pages`, `Minn\Runtime\Patterns`, `Minn\Runtime\PlaceholderTrace`, `Minn\Runtime\PluginActivation`, `Minn\Runtime\PluginFiles`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\PluginRequirements`, `Minn\Runtime\PluginUpdates`, `Minn\Runtime\Plugins`, `Minn\Runtime\PostCaches`, `Minn\Runtime\PostData`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostFields`, `Minn\Runtime\PostInsert`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostLocks`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryWhere`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostSave`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Runtime\QueryArgs`, `Minn\Runtime\RecipientLocale`, `Minn\Runtime\Records`, `Minn\Runtime\Registered`, `Minn\Runtime\RegisteredSettings`, `Minn\Runtime\Registry`, `Minn\Runtime\RequestHeaders`, `Minn\Runtime\RewriteRules`, `Minn\Runtime\RewriteStructs`, `Minn\Runtime\SafeUrls`, `Minn\Runtime\ScriptModules`, `Minn\Runtime\Shortcodes`, `Minn\Runtime\SiteInfo`, `Minn\Runtime\SiteOptions`, `Minn\Runtime\SiteTime`, `Minn\Runtime\SiteUrls`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermFields`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermOrder`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermTags`, `Minn\Runtime\TermWriter`, `Minn\Runtime\TermWrites`, `Minn\Runtime\ThemeMods`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\ThemeSwitch`, `Minn\Runtime\Themes`, `Minn\Runtime\TimeSince`, `Minn\Runtime\Transients`, `Minn\Runtime\Translations`, `Minn\Runtime\UpdateCounts`, `Minn\Runtime\Upgrade`, `Minn\Runtime\UserCounts`, `Minn\Runtime\UserEvents`, `Minn\Runtime\UserQueryRunner`, `Minn\Runtime\UserSave`, `Minn\Runtime\UserWrites`, `Minn\Runtime\VersionCheck`, `Minn\Runtime\WidgetAreas`, `Minn\Runtime\WpdbFields`, `Minn\Runtime\WpdbReads`, `Minn\Runtime\WpdbSetup`, `Minn\Support\Escape`, `Minn\Support\Kses`, `Minn\Support\KsesProtocols`, `Minn\Support\Sanitize`, `Minn\Support\Text`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\EmbedRenderer`, `Minn\Theme\FeedHeaders`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\GlobalStyles`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\StyleLayers`, `Minn\Theme\TemplateBuffer`, `Minn\Theme\Templates`, `Minn\Theme\Theme`, `Minn\Theme\ThemeJsonData`, `Minn\Widgets\TextWidgets`

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

### static `storeOptions(Minn\Db $db): void`

The options store for a connection, made as soon as the request has
one (Engine::answer) and kept by the boot that follows: the reads made
while routing come from the same autoloaded list as everything after,
and Db::option() reads through it from then on.

### static `installing(): bool`

Whether this request is installing (wp_installing()); false before a runtime boots.

### `installingFlag(mixed $isInstalling = NULL): bool`

Whether this request is installing (wp_installing); given a value, it sets the flag and answers what it was.

### static `optionWritten(string $name): void`

Tells the option store about a row the engine wrote behind it; nothing to do when no store was made.

### static `capture(string $action, array $args = array ( )): string`

Runs an action and returns what it printed. Plugin callbacks may open
output buffers of their own during the action (a page post-processor
started in wp_head) or close one they think is theirs (the same plugin
in wp_footer); a sentinel buffer under the capture keeps the output
either way: extra buffers are flushed through their handlers into the
capture, and a capture closed early lands in the sentinel.

### static `classicContentFilter(string $content): string`

Classic content (no block markup) through the whole of the_content, as
the reference runs it: handed as written, core's defaults (embeds,
texturize, paragraphs, shortcodes, images) in their places, so a filter
before them is handed the stored text and what it adds is formatted
with the rest (runtime suite, "plain text a filter adds").

### static `blockContentFilter(string $content, ?Closure $rendered = NULL): string`

Block content through the_content as the reference runs it: handed as
written, so a filter before do_blocks is handed the stored block
markup; the engine's renderer in do_blocks's place, rendering what those
filters added with the rest and never parsing its own output again
(comment text a comments block prints stays text); then the rest of
the_content, the defaults the renderer has done left out. $rendered,
when given, takes the rendering at once (the engine's extension seams).

- `@param \Closure(string): string|null $rendered`

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

### static `newScriptModules(): Minn\Runtime\ScriptModules`

A script modules registry of its own, as each new WP_Script_Modules keeps (probe script-modules-printing).

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

### `remember(string $key, callable $make): mixed`

A per-request state value, made once by the given callable the first time it is asked for (a facade class's single instance).

### `isSecure(): bool`

Whether the request is over HTTPS.

### `contentDir(): string`

wp-content under the site root.

### static `facadeLoaded(): bool`

Whether the facade's functions and classes are defined yet.

### static `loadFacade(string $engineDir): void`

Defines the facade functions once; safe to call again.

### static `loadPluggables(): void`

The pluggable functions, once the plugins have had their chance to
define their own: each is defined only where no plugin did. The plugin
loader calls this before plugins_loaded; a boot that loads no plugins
calls it straight away.

### static `reset(): void`

Fresh per-request state, for suites.

Internals: `loadObjectCacheDropin()` (private, line 465)


## SafeUrls

`final class Minn\Runtime\SafeUrls` · `public/minn/src/Minn/Runtime/SafeUrls.php`

Whether an address is one the site may fetch (wp_http_validate_url): an
http or https address that survives the protocol check unchanged, whose
host is not a private or reserved one unless it is the site's own or
http_request_host_is_external lets it through, on a safe port
(http_allowed_safe_ports). The address comes back as it was, or false.

Used by: `Minn\Rest\FontSettings`

### static `validated(mixed $url): string|false`

The address when it may be fetched, else false.


## ScriptLoading

`final class Minn\Runtime\ScriptLoading` · `public/minn/src/Minn/Runtime/ScriptLoading.php`

How a script may load, as the reference decides it (probe
dependencies-classes). A script keeps its own strategy (defer or async)
unless it has inline code after it, which makes it blocking, or an
enqueued dependent (however deep, through dependents that are themselves
enqueued) loads more strictly: blocking before defer before async. Its
fetch priority rises to the highest among those dependents: low, then
auto, then high. A delayed script that cannot be delayed (inline code
after it) waits for the footer when nothing enqueued depends on it. The
values a script may be given are checked here too.

- const `STRATEGIES` = `array (   '' => 0,   'defer' => 1,   'async' => 2, )`
- const `PRIORITIES` = `array (   'low' => 0,   'auto' => 1,   'high' => 2, )`

```php
__construct(Minn\Runtime\Assets $assets)
```


### static `refusal(string $handle, string $key, mixed $value): ?array`

Why a strategy or fetch priority value is refused, as the notice the
reference gives; null when the value is fine (empty is fine).

- `@return array{0: string, 1: string}|null the message and the version`

### `strategy(string $handle, array $checked = array ( )): string`

The strategy a script loads with: 'defer', 'async', or '' for blocking. @param array<string, true> $checked

- `@param array<string, true> $checked`

### `heldForFooter(string $handle): bool`

Whether the head pass holds a script for the footer: one that asked to
be delayed but has inline code after it, with nothing enqueued that
depends on it.

### `priority(string $handle): array`

A script's own fetch priority and the one it loads with. @return array{0: string, 1: string}

- `@return array{0: string, 1: string}`

Internals: `raised()` (private, line 78), `ownPriority()` (private, line 88), `enqueuedDependents()` (private, line 95)


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

### `markedForEnqueue(): array`

The queued modules that are registered, in queue order, each as
registered(): what the reference's get_marked_for_enqueue() answers,
a module whose dependency is missing included.

- `@return array<string, array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}>`

### `dependencyRecords(array $ids, array $types): array`

The registered dependencies of some modules, following the given
import types, each as registered() (get_dependencies()).

- `@param list<string> $ids`
- `@param list<string> $types`
- `@return array<string, array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}>`

### `source(string $id): string`

A registered module's address as the page loads it (get_src()); '' for one not registered.

### `printImportMap(): string`

The import map script tag.

### `printPreloads(): string`

The modulepreload links: the queued modules' static dependencies not
queued themselves, each after its own (depth first, in queue order).

### `printHead(): string`

The head's module tags.

### `printFooter(): string`

The footer's module tags.

### `printData(): string`

The script-module-data tags.

### `printA11y(): string`

The a11y module's tag, once.

Internals: `placeIn()` (private, line 123), `printTags()` (private, line 267), `tellMissing()` (private, line 296), `dependenciesFirst()` (private, line 317), `marked()` (private, line 338), `complete()` (private, line 350), `dependencies()` (private, line 373), `urlOf()` (private, line 394), `attr()` (private, line 399), `wrong()` (private, line 404)


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

Used by: `Minn\Cli\MinnCommand`, `Minn\Runtime\LegacyPack`

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

- const `ATTRIBUTES` = `'/([\\w-]+)\\s*=\\s*"([^"]*)"(?:\\s|$)|([\\w-]+)\\s*=\\s*\'([^\']*)\'(?:\\s|$)|([\\w-]+)\\s*=\\s*([^\\s\'"]+)(?:\\s|$)|"([^"]*)"(?:\\s|$)|\'([^\']*)\'(?:\\s|$)|(\\S+)(?:\\s|$)/'` — The attribute pattern shortcode_parse_atts reads by (get_shortcode_atts_regex).
- const `SET_ASIDE` = `array (   '[' => '&#91;',   ']' => '&#93;', )`
- const `RESTORE` = `array (   '&#91;' => '[',   '&#93;' => ']', )`

Used by: `Minn\Content\MediaShortcodes`, `Minn\Runtime\Runtime`


### static `atts(mixed $pairs, mixed $atts, mixed $shortcode = ''): mixed`

A shortcode's attributes over its defaults (shortcode_atts): only the
names the defaults have, the given value else the default; through
shortcode_atts_{shortcode} when the shortcode is named.

- `@return array<string, mixed>`

### `tags(): array`

The registry itself, by reference, so the $shortcode_tags global plugin
code reads and copies is this array and not a snapshot of it.

- `@return array<string, mixed>`

### `add(string $tag, mixed $callback): void`

Registers a shortcode. Its callback is kept as given, callable or not
yet: a plugin may name a class it declares further down its file, and
the reference only asks whether the callback can be called when the
shortcode runs.

### `register(mixed $tag, mixed $callback): void`

A shortcode added as add_shortcode() adds it: an empty name, or one
with a space or a reserved character, is refused with a notice.

### `remove(string $tag): void`

Forgets a shortcode.

### `removeAll(): void`

Forgets every shortcode.

### `has(string $tag): bool`

Whether a shortcode is registered.

### `all(): array`

Every shortcode with its callback.

- `@return array<string, mixed> every registered tag and its handler, to restore after a narrowed run`

### `restore(array $tags): void`

Replaces the registry, after a save-and-restore.

- `@param array<string, mixed> $tags`

### `names(): array`

Every shortcode name.

- `@return list<string>`

### `unautop(mixed $text): mixed`

Text with each shortcode that stands alone in a paragraph let out of it (shortcode_unautop); as given with none registered.

### `pattern(?array $tags = NULL): ?string`

The regex matching the registered shortcodes, or null for none.

### `apply(string $content, string $tags = 'run'): string`

Content with its shortcodes run (do_shortcode, probe shortcode-run):
only the registered names the text holds; inside HTML tags first
(inTags: run, or with 'escape' as with ignore_html, left alone),
then in the text, each through run(); the brackets set aside as
entities come back at the end. Images rendered meanwhile see the
do_shortcode context.

### `run(array $m): string`

One matched shortcode (do_shortcode_tag): [[tag]] gives the tag as
written; otherwise pre_do_shortcode_tag may answer, or the callback
runs with the attributes, the content ("" when it closes itself) and
the name, and do_shortcode_tag sees the output in its brackets.

- `@param array<int, string> $m the pattern's match`

### `inTags(string $content, string $tags, array $names): string`

Shortcodes inside HTML tags (do_shortcodes_in_html_tags), run before
the text: a quoted attribute value's shortcode runs and the attribute
is judged again by kses, kept when anything is left; an unquoted one
(or one standing as a name or as the tag) runs as written. Comments,
CDATA and, with 'escape', every tag are left alone. Brackets left in a
tag (and entities already written for them) are set aside so the text
pass cannot reach them.

- `@param list<string> $names`

### `strip(string $content): string`

Content with its shortcodes taken out (strip_shortcodes): the names
strip_shortcodes_tagnames leaves, never inside a tag; [[tag]] gives
the tag as written.

### static `stripped(array $m): string`

One matched shortcode taken out (strip_shortcode_tag): its outer brackets stay, [[tag]] gives the tag. @param array<int, string> $m

- `@param array<int, string> $m`

### static `parse(string $text): array`

Shortcode attribute text as an array (shortcode_parse_atts): no-break
and zero-width spaces read as spaces; name=value pairs (names lower
case) and bare values in order, each a token followed by whitespace
or the end, backslash escapes undone; a value holding "<" that is not
whole tags becomes "".

- `@return array<int|string, string>`

Internals: `present()` (private, line 311), `attributesRun()` (private, line 325)


## SiteInfo

`final class Minn\Runtime\SiteInfo` · `public/minn/src/Minn/Runtime/SiteInfo.php`

What WordPress tells about the site (get_bloginfo): its name and tagline,
its addresses and feeds', the theme's stylesheet and folders, the admin
address, the charset and content type, the version, the language and
its direction; and a feed's address.

Used by: `Minn\Front\CustomLogo`, `Minn\Front\EmbedCard`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\LinksOpml`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMenus`, `Minn\Runtime\DocumentTitle`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\HeadLinks`

### static `get(mixed $show = '', mixed $filter = 'raw'): mixed`

One fact about the site (get_bloginfo), its name when none is asked;
for display through bloginfo_url (an address) or bloginfo.

### static `rss(mixed $show = ''): mixed`

One fact about the site for a feed (get_bloginfo_rss): escaped whole, tags included, through get_bloginfo_rss.

### static `feedLink(mixed $feed = ''): mixed`

A feed's address (get_feed_link): the default feed bare, through
feed_link handed '' for it; its ?feed= form without pretty permalinks.


## SiteOptions

`final class Minn\Runtime\SiteOptions` · `public/minn/src/Minn/Runtime/SiteOptions.php`

The network's options, as a single site keeps them: options stored not
to autoload, with the network layer's own hooks around them, told the
network asked for (the current one, 1, when none is).

Used by: `Minn\Runtime\ApplicationPasswordRecords`, `Minn\Runtime\Transients`

### static `get(mixed $option, mixed $default = false, mixed $networkId = NULL): mixed`

A network option (get_network_option, get_site_option):
pre_site_option_{name} and pre_site_option may answer first,
default_site_option_{name} sets its default, site_option_{name} has
the last word.

### static `add(mixed $option, mixed $value, mixed $networkId = NULL): bool`

A new network option (add_network_option, add_site_option), stored not to autoload.

### static `update(mixed $option, mixed $value, mixed $networkId = NULL): bool`

A network option changed (update_network_option, update_site_option); one not there yet is added.

### static `delete(mixed $option, mixed $networkId = NULL): bool`

A network option removed (delete_network_option, delete_site_option).

Internals: `network()` (private, line 85)


## SiteTime

`final class Minn\Runtime\SiteTime` · `public/minn/src/Minn/Runtime/SiteTime.php`

Time as the site keeps it: its timezone (the named one, else the UTC
offset), now in that zone or in UTC, a moment written in the site's
language, and a stored date read back in a format.

Used by: `Minn\Front\FeedTemplates`, `Minn\Front\Sitemaps`, `Minn\Query\DateSql`, `Minn\Runtime\AttachmentModel`, `Minn\Runtime\Attachments`, `Minn\Runtime\CommentChecks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentTags`, `Minn\Runtime\CommentWrites`, `Minn\Runtime\PostData`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Theme\FeedHeaders`

### static `zoneName(): string`

The site's timezone as text (wp_timezone_string): its name, else its offset as +HH:MM.

### static `zone(): DateTimeZone`

The site's timezone (wp_timezone).

### static `now(mixed $type, mixed $gmt = false): mixed`

Now (current_time): a timestamp for "timestamp" or "U" (the site's
offset added unless in UTC), a database date for "mysql", else the
format given, in the site's zone or UTC.

### static `toGmt(mixed $date, mixed $format = 'Y-m-d H:i:s'): string`

A date in the site's zone written in UTC (get_gmt_from_date); the epoch for one that is not a date.

### static `fromGmt(mixed $date, mixed $format = 'Y-m-d H:i:s'): string`

A date in UTC written in the site's zone (get_date_from_gmt); the epoch for one that is not a date.

### static `format(mixed $format, mixed $timestamp = NULL, mixed $timezone = NULL): mixed`

A moment written in a format, its names in the site's language (wp_date), as the filter leaves it.

### static `fromStored(mixed $format, mixed $date, mixed $translate = true): mixed`

A stored date in a format (mysql2date): false for none or one that is
not a date; "G" and "U" are timestamps (G with the site's offset); the
names translated unless asked not to be.


## SiteUrls

`final class Minn\Runtime\SiteUrls` · `public/minn/src/Minn/Runtime/SiteUrls.php`

The site's own addresses as WordPress builds them: the home and site
addresses, the admin's, wp-includes', the sign-in and sign-out pages' (each with its
scheme settled and its filter run), whether the request came over HTTPS,
the switch that forces the admin onto it, and the trailing slash the
permalink structure asks for.

Used by: `Minn\Front\CustomLogo`, `Minn\Front\EmbedCard`, `Minn\Front\EntryScriptsController`, `Minn\Front\FeedTags`, `Minn\Front\PostEmbed`, `Minn\Front\RuleTable`, `Minn\Front\ToolbarMenus`, `Minn\Login\LoginController`, `Minn\Media\Icons`, `Minn\Media\MediaModal`, `Minn\Media\Player`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\CommentPages`, `Minn\Runtime\EditLinks`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\PluginRequirements`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostTags`, `Minn\Runtime\RequestHeaders`, `Minn\Runtime\SafeUrls`, `Minn\Runtime\SiteInfo`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermTags`, `Minn\Runtime\Themes`, `Minn\Runtime\UserWrites`, `Minn\Widgets\WidgetForms`


### static `secure(): bool`

Whether this request came over HTTPS (is_ssl): the engine's request when there is one, else the server variables.

### static `forceSslAdmin(mixed $force = NULL): bool`

Whether the admin is forced onto HTTPS (force_ssl_admin, FORCE_SSL_ADMIN); given a value, it sets the switch and answers what it was.

### static `withScheme(mixed $url, mixed $scheme = NULL): string`

An address in a scheme (set_url_scheme): http, https or relative when
asked for; https for the admin, sign-in and RPC ones over HTTPS or with
the admin forced; otherwise the request's. Then set_url_scheme.

### static `home(mixed $path = '', mixed $scheme = NULL, mixed $blogId = NULL): string`

The home address with a path (get_home_url, home_url): in the request's scheme unless http, https or relative is asked for.

### static `site(mixed $path = '', mixed $scheme = NULL, mixed $blogId = NULL): string`

The site (WordPress) address with a path (get_site_url, site_url).

### static `registration(): mixed`

Where registration starts (wp_registration_url): the sign-in page's register action, through register_url.

### static `admin(mixed $path = '', mixed $scheme = 'admin', mixed $blogId = NULL): string`

An address in the admin (get_admin_url, admin_url).

### static `selfAdmin(mixed $path = '', mixed $scheme = 'admin'): string`

An address in the current admin (self_admin_url): the site's admin on a single site.

### static `content(mixed $path = ''): mixed`

An address in wp-content (content_url).

### static `customize(mixed $stylesheet = ''): string`

The Customizer's address, a theme to preview named (wp_customize_url), escaped.

### static `includes(mixed $path = '', mixed $scheme = NULL): string`

An address in wp-includes (includes_url).

### static `login(mixed $redirect = '', mixed $forceReauth = false): string`

The sign-in page (wp_login_url), told where to go after and whether to ask again, as login_url leaves it.

### static `logout(mixed $redirect = ''): string`

The sign-out address (wp_logout_url), its nonce after the action and where to go after, as logout_url leaves it.

### static `trailing(mixed $url, mixed $type = ''): string`

An address ending as the permalink structure asks (user_trailingslashit):
with a slash when the rewrite object (or the structure) uses them,
without one otherwise; then user_trailingslashit.

Internals: `joined()` (private, line 155), `tail()` (private, line 161)


## StoredObjects

`final class Minn\Runtime\StoredObjects` · `public/minn/src/Minn/Runtime/StoredObjects.php`

The classes a stored blob may name and come back as. The serialized
reader never instantiates by itself; the facade registers one factory per
value class it owns (WP_Post, WP_Term, WP_Comment), and any other class
comes back as itself only when it is plain data: a class that exists,
written in PHP, that runs nothing as it is revived or let go (no
__wakeup, __unserialize, __destruct or __set, not Serializable), so no stored
blob can make code run (PHP object injection). Plugins keep settings in
such objects (WooCommerce PayPal Payments does) and read them back typed,
as the reference revives them. Anything else stays a stdClass of its
properties.

- const `WRAPPERS` = `array (   'arrayobject' => 'ArrayObject',   'arrayiterator' => 'ArrayIterator',   'recursivearrayiterator' => 'RecursiveArrayIterator', )` — PHP's array wrappers a stored record may name, by lower-cased class name.

Used by: `Minn\Rest\SidebarsController`, `Minn\Rest\WidgetsController`, `Minn\Runtime\MetaData`, `Minn\Runtime\Options`


### static `maybeDecode(mixed $data): mixed`

A stored value read back (maybe_unserialize): serialized data decoded,
its objects revived by the registered factories; anything else, or
data that does not decode, as given.

### static `register(string $class, Closure $factory): void`

Registers what a record naming this class becomes.

### static `reviver(): Closure`

The reviver the serialized reader takes: PHP's array wrappers rebuilt, a factory's object, a plain data object, or the properties as they are.

### static `knows(string $class): bool`

Whether a factory is registered for the class.

### static `reset(): void`

Forgets every factory, for suites.

Internals: `plain()` (private, line 73), `owner()` (private, line 112), `arrayWrapper()` (private, line 135)


## SymbolGap

`final readonly class Minn\Runtime\SymbolGap` · `public/minn/src/Minn/Runtime/SymbolGap.php`

The part of the reference's interface the runtime does not answer: names in
data/api-names.json that no facade file defines. It is the whole input the
symbol gate needs, so it can be exported to JSON and carried to a machine
that holds plugin source but no engine, which is how the catalogue-wide
compatibility scan runs.

Used by: `Minn\Cli\Preflight`, `Minn\Runtime\Symbols`

- readonly `array $functions`
- readonly `array $classes`
- readonly `array $known`
- readonly `array $pluggable`

### static `ofLoadedFacade(string $engineDir): self`

Reads the reference's interface and subtracts everything the loaded facade
defines. A plugin may define a name as it loads, so each call asks again,
but only about the names still lacking: a name once defined stays defined,
and asking about all four thousand for every plugin was a cost of its own.

### static `ofShippedFacade(string $engineDir): self`

The gap read from the engine's own files without loading them, for a
machine that has the engine but runs no site with it yet (preflight): a
name counts as defined when a facade file declares it.

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

Internals: `typeExists()` (private, line 67), `declaredIn()` (private, line 105)


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
provides, this decides whether a plugin loads at all.

The read is cached in the minn_runtime_symbols option, keeping only the
reference's own names (all a verdict reads). An entry is trusted while
the folder's fingerprint (its own time and its top-level files' newest)
holds, for up to FULL_CHECK_EVERY seconds; then the whole folder is
walked again, as an update replaces it. Installing, updating or
activating a plugin walks its folder at once (recheck()). Walking every
folder on every request cost hundreds of milliseconds on a site with
many plugins.

- const `MAX_FILES` = `10000` — The most PHP files a folder's walk reads; a folder past it is not loaded, as its needs are not all known.
WPML is 7,080 (64 MB at its peak, once, on activation); the next of the 215 most-run plugins is under 4,800.
- const `SKIP_DIRS` = `array (   0 => 'node_modules',   1 => 'tests',   2 => 'test',   3 => '.git', )`
- const `READER` = `4` — Bumped whenever the token reader changes, so every cached scan is made again.
- const `FULL_CHECK_EVERY` = `300` — How long a cached entry is trusted on its fingerprint alone before the folder is walked again.
- const `GIVE_UP_AFTER` = `120` — A walk begun this long ago that never finished ran out of time or memory: its folder is held back instead of walked again on every request.

Used by: `Minn\Cli\Preflight`, `Minn\Runtime\Plugins`, `Minn\Runtime\Upgrade`


### static `missing(string $dir, Minn\Runtime\Options $options): array`

What a plugin folder needs that the runtime lacks, cached by mtime.

- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}`

### static `recheck(string $dir, Minn\Runtime\Options $options): void`

The folder is walked again at its next check instead of trusted on its
fingerprint: an install, an update or an activation never waits out
the window. The walk still keeps the cached read when nothing changed.

### static `redeclaresIn(string $file): array`

The functions one file declares in the global scope, unguarded, that the
loaded runtime already defines: including that file would not compile.

- `@return list<string>`

### static `missingAgainst(string $dir, Minn\Runtime\SymbolGap $gap): array`

The same read against an exported gap instead of the running engine, so a
folder can be judged with no database, no options, and no facade loaded.

- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}`

### static `survey(string $dir, Minn\Runtime\SymbolGap $gap, array $deadEnds): array`

What a folder needs and what it reaches that Minn answers with nothing,
in one read, for a machine judging a site before the engine runs it
(preflight): the gate's verdict against the gap given, and the dead
ends it calls (the wp-admin screens, the editors, the Customizer,
XML-RPC, what was left out), by area. A call the folder guards or
answers itself is not counted, as the gate counts it.

- `@param array{functions?: array<string, string>, classes?: array<string, string>} $deadEnds name => area, as data/deadend-symbols.json holds them`
- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool, deadEnds: array<string, list<string>>}`

Internals: `key()` (private, line 99), `keep()` (private, line 106), `fingerprint()` (private, line 118), `namesStamp()` (private, line 131), `reduced()` (private, line 143), `verdict()` (private, line 229), `phpFiles()` (private, line 263), `scan()` (private, line 297), `scanTokens()` (private, line 323), `inGuardedNamespace()` (private, line 412), `noteName()` (private, line 431), `resolve()` (private, line 469), `readImports()` (private, line 486), `significant()` (private, line 533)


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

true sets a bare boolean attribute; a URL attribute (href, src, ...) keeps only a URL whose scheme is allowed, as the reference's set_attribute keeps it.

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

Internals: `classes()` (private, line 104), `setClasses()` (private, line 111), `splice()` (private, line 152)


## TaxTerms

`final class Minn\Runtime\TaxTerms` · `public/minn/src/Minn/Runtime/TaxTerms.php`

The terms a taxonomy query's clause names, found as the reference finds
them (probe tax-terms). The clause's terms are made unique (their keys
kept). Under a hierarchical taxonomy that includes children they are
turned into term ids, each followed by its children (the children first,
then the term, repeats kept). Then they are turned into term_taxonomy_ids.
Each turn is one terms query in no order for every term at once: as many
as there are terms, or all of them in a hierarchical taxonomy. No turn
runs when the terms are already in the field wanted. An AND clause fails
when a turn finds fewer terms than it has. The engine answers a turn
from what it has just read, without the database: one by a name it knows
(the theme's own term), and the second turn when the first found terms
with no children; a later lookup by a slug a turn found is answered too.

### static `ids(array $given, string $operator): ?array`

A clause's term_taxonomy_ids for its SQL (get_sql_for_clause), or null
when an AND clause names a term there is not.

- `@param array<string, mixed> $given the clause: taxonomy, field, terms, include_children`
- `@return list<int>|null`

### static `transform(mixed $clause, mixed $to): void`

A clause's terms turned into another field, as a plugin asks
(transform_query): an error in the clause's place when an AND clause
names a term there is not.

- `@param array<string, mixed>|object $clause`

Internals: `turn()` (private, line 80), `missing()` (private, line 116)


## TemplateFiles

`final class Minn\Runtime\TemplateFiles` · `public/minn/src/Minn/Runtime/TemplateFiles.php`

The theme templates WordPress's template functions name: the index and
embed templates found through the hierarchy, the slugs a block template
stands for, the template types and part areas core knows, and a block
template made from the theme's file or from a saved post, as the
reference makes one (probe template-part-lookup): the theme's names and
folders read on the way, the area a theme declares checked against the
areas core knows, and the markup passed through the block hooks.

- const `PLAIN_TITLES` = `array (   0 => 'attachment',   1 => 'privacy-policy', )` — Template types named without a context.
- const `FORMAT_SLUG` = `'taxonomy-post_format-post-format-'` — The prefix of the post format archives' template slugs.

Used by: `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\TemplatesController`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\EmbedRenderer`

### static `index(): mixed`

The theme's index template (get_index_template).

### static `embed(): mixed`

The template an embed is shown with (get_embed_template): embed-{type}-{format}.php,
embed-{type}.php, then embed.php.

### static `hierarchy(mixed $slug, mixed $isCustom = false, mixed $templatePrefix = ''): array`

The template slugs a slug stands for, most specific first
(get_template_hierarchy).

- `@return list<string>`

### static `defaultTypes(): mixed`

The template types core knows (get_default_block_template_types), as
default_template_types leaves them: each named and described in the
site's language, then one per post format.

### static `partAreas(): mixed`

The template part areas core knows (get_allowed_block_template_part_areas), each named in the site's language, as default_wp_template_part_areas leaves them.

### static `partArea(mixed $type): mixed`

A template part's area as the areas core knows it
(_filter_block_template_part_area): one it does not know becomes
uncategorized, with a notice.

### static `templateIndex(): ?Minn\Theme\TemplateIndex`

The active theme's templates, saved and in its files; null with no block theme booted.

### static `file(mixed $type, mixed $slug): ?array`

One theme template file, described (_get_block_template_file), after
the theme's names and folders are read: its slug, path, theme and type;
a part's area, the one theme.json declares checked against the areas
core knows (_filter_block_template_part_area); a custom template's
title and post types. Null for no such file, or a type that is neither.

- `@return array<string, mixed>|null`

### static `fromFile(mixed $file, mixed $type): object`

A template built from a file as file() describes one
(_build_block_template_result_from_file): the default template types'
title and description for a template they name (which is not custom),
a part's area, a custom template's post types, and the markup through
the block hooks, the theme named on every template part in it.

- `@param array<string, mixed>|object $file`

### static `saved(string $type, string $theme, string $slug): ?object`

The template or part a site saved for a theme (any theme, by its
wp_theme term) under a slug, built as fromPost() builds it, block
hooks applied; null when none is saved (probe taxonomy-blocks-user-doubts).

### static `fileTemplate(mixed $id, mixed $type = 'wp_template'): mixed`

A theme file's template by id, past whatever the site saved over it
(get_block_file_template): pre_get_block_file_template may answer
first; only the active theme's files are found; the template, or null,
through get_block_file_template.

### static `fromPost(mixed $post): mixed`

A saved template or part as a template (_build_block_template_result_from_post),
under the theme its wp_theme term names; an error when it names none.
A part's area is its wp_template_part_area term's, none without one.
Its origin and suggestion meta and whether the theme has the file are
read as the reference reads them; its markup goes through the block
hooks, a part's with the hooked blocks its post says to leave out.

Internals: `hookedPart()` (private, line 270)


## TermEvents

`final readonly class Minn\Runtime\TermEvents` · `public/minn/src/Minn/Runtime/TermEvents.php`

What the reference's REST terms controller tells plugins, for the
engine's own: with plugins loaded a term is saved as that controller
saves one (rest_pre_insert_{taxonomy}, then the runtime's wp_insert_term
or wp_update_term, and wp_delete_term), and the REST actions follow.
Without a booted runtime each write is the engine's own.

Used by: `Minn\Rest\TermsController`, `Minn\Runtime\MenuEvents`

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

Internals: `prepared()` (private, line 75), `refusal()` (private, line 95)


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

Used by: `Minn\Front\FeedTags`, `Minn\Runtime\PostQueryTax`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\TermSave`

### static `field(string $field, mixed $value, int $termId, string|false $taxonomy, string $context): mixed`

One field's value in a context; a lookup with no taxonomy names false, and its filters get false.

### static `term(object|array $term, string $taxonomy, string $context): object|array`

A term (object or array) with each of its fields in the context, and
its filter set to the context; the term's own id goes to the filters.

Internals: `filtered()` (private, line 66), `saving()` (private, line 77)


## TermLookup

`final class Minn\Runtime\TermLookup` · `public/minn/src/Minn/Runtime/TermLookup.php`

Terms looked up as WordPress's taxonomy functions find them: a list by
query arguments, one by a field, whether one exists, a taxonomy's
hierarchy, an object's terms, a term's address and one of its fields, its
children and ancestors, how many there are, and the caches cleaned after
a change; each with the filters plugins hook there.

Used by: `Minn\Front\LinksOpml`, `Minn\Rest\MenuLists`, `Minn\Rest\TermsController`, `Minn\Runtime\Bookmarks`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\NavMenu`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\ObjectTerms`, `Minn\Runtime\PostQueryTax`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TaxTerms`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermTags`, `Minn\Runtime\TermWrites`, `Minn\Theme\Templates`

### static `terms(mixed $args = array ( ), mixed $deprecated = '', ?Closure $answer = NULL): mixed`

Terms by query arguments (get_terms), or the old ($taxonomy, $args)
shape; an error for an unknown taxonomy; get_terms filters the list
unless suppressed (a count never). The engine may hand an answer for
the query it writes (QueryAnswers).

### static `categories(mixed $args = ''): mixed`

Categories, or another taxonomy's terms (get_categories): the terms
query through get_categories_taxonomy, the old type => link argument
noted as deprecated and read as the link categories, each term given
the old category fields; none when the query is refused.

### static `by(mixed $field, mixed $value, mixed $taxonomy = '', mixed $output = 'OBJECT', mixed $filter = 'raw'): mixed`

A term by slug, name, id or term_taxonomy_id (get_term_by), or false.
As the reference finds one (probe term-lookup): by id through get_term;
by the other three through a terms query of one result in no order
(the taxonomy left out for a term_taxonomy_id), and the term it finds
through get_term again; a slug or name needs a taxonomy that exists and
a value. The theme's own term, once its templates are read, and a slug
a taxonomy query has just found are answered without the database.

### static `knownByName(string $taxonomy, string $name, ?array $row): void`

A taxonomy's term by name as the engine has read it elsewhere (its row,
or none when there is no such term), kept so get_term_by answers its
lookup for that name without the database.

- `@param array<string, mixed>|null $row`

### static `exists(mixed $term, mixed $taxonomy = '', mixed $parent = NULL): mixed`

Whether a term exists (term_exists): its id (and term_taxonomy_id when a
taxonomy is named), or null. Lookup arguments a plugin changes through
term_exists_default_query_args are run as a terms query, by slug then
by name.

### static `hierarchy(mixed $taxonomy): array`

A hierarchical taxonomy's parents and their children (_get_term_hierarchy),
built from the terms and kept in {taxonomy}_children when not kept yet.

- `@return array<int, list<int>>`

### static `descendants(mixed $termId, mixed $terms, mixed $taxonomy, mixed $ancestors = array ( )): mixed`

Terms below a term among those given (_get_term_children), following
only the terms the stored hierarchy says have children; ids for ids.

### static `children(mixed $termId, mixed $taxonomy): mixed`

Every term below a term (get_term_children), an error for an unknown
taxonomy. As the reference walks the stored hierarchy (probe
tax-terms): a term's own children, then those of each child that has
any, the hierarchy read again for each term walked.

### static `objectTerms(mixed $objectIds, mixed $taxonomies, mixed $args = array ( ), ?Closure $answer = NULL): mixed`

The terms of objects in taxonomies (wp_get_object_terms), as
wp_get_object_terms_args shapes the query and get_object_terms and
wp_get_object_terms leave the list; the engine may hand an answer for
its terms query.

### static `field(mixed $field, mixed $term, mixed $taxonomy = '', mixed $context = 'display'): mixed`

One of a term's fields in a context (get_term_field): empty when the term has no such field.

### static `count(mixed $args = array ( ), mixed $deprecated = ''): mixed`

How many terms match (wp_count_terms), the old ($taxonomy, $args) shape
read too; empty terms counted.

### static `link(mixed $term, mixed $taxonomy = ''): mixed`

A term's address (get_term_link): by its taxonomy's link structure
through pre_term_link, a changed one filled with its slug (its
ancestors' first when the taxonomy nests), by query without one; then
tag_link or category_link, and term_link. An error for no term.

### static `ancestors(mixed $objectId = 0, mixed $objectType = '', mixed $resourceType = ''): mixed`

An object's ancestors (get_ancestors): a term's parents up the
taxonomy, or a post's up its type; as get_ancestors leaves them.

- `@return array<int, int>`

### static `postAncestors(mixed $post): array`

A post's parents up to the top (get_post_ancestors), a loop broken.

- `@return list<int>`

### static `cleanCache(mixed $ids, mixed $taxonomy = '', mixed $cleanTaxonomy = true): void`

Terms forgotten after a change (clean_term_cache): each term's cache,
its taxonomy's (unless asked not to), then clean_term_cache; with no
taxonomy named, each term's own.

### static `cleanTaxonomy(mixed $taxonomy): void`

A taxonomy's caches cleaned (clean_taxonomy_cache): its hierarchy rebuilt, the terms' last change moved.

### static `queryLink(Minn\Objects\Term $term): string`

A term's address by query: ?cat= for a category, the taxonomy's query var, else taxonomy and term.

Internals: `below()` (private, line 259), `lookup()` (private, line 472), `query()` (private, line 483)


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

### static `clause(Minn\Objects\TermsQuery $query, string $raw): string`

The ORDER BY body for an orderby value, filtered as the reference filters it.

### static `meta(Minn\Objects\TermsQuery $query, string $orderby): string`

A meta key, meta_value[_num] or a named meta clause as SQL (parse_orderby_meta), or '' when the query has no such clause.


## TermQuery

`final readonly class Minn\Runtime\TermQuery` · `public/minn/src/Minn/Runtime/TermQuery.php`

Term reads in the shapes plugin code asks for: get_terms() arguments to
rows, the tree filters (child_of, exclude_tree), the fields shapes, and
the single-term lookups. Behaviour pinned by contracts/fixtures/api/content.json.

- const `DEFAULTS` = `array (   'taxonomy' => NULL,   'object_ids' => NULL,   'orderby' => 'name',   'order' => 'ASC',   'hide_empty' => true,   'include' =>    array (   ),   'exclude' =>    array (   ),   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => '',   'fields' => 'all',   'count' => false,   'name' => '',   'slug' => '',   'term_taxonomy_id' => '',   'hierarchical' => true,   'search' => '',   'name__like' => '',   'description__like' => '',   'pad_counts' => false,   'get' => '',   'child_of' => 0,   'parent' => '',   'childless' => false,   'cache_domain' => 'core',   'update_term_meta_cache' => true,   'meta_query' => '',   'meta_key' => '',   'meta_value' => '', )`
- const `COLUMNS` = `'t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count'`

Used by: `Minn\Content\Posts`, `Minn\Runtime\Records`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermWriter`, `Minn\Runtime\TermWrites`

```php
__construct(Minn\Db $db, Closure $slug)
```
- `@param Closure(string): string $slug the slug sanitiser, so the reference's filters apply`


### `row(int $termId, ?string $taxonomy): ?array`

A term row joined with its taxonomy row, by id (and taxonomy when known). @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### static `keep(array $row): void`

A term row read elsewhere (a list of posts' terms, read together), kept so row() answers from it. @param array<string, mixed> $row

- `@param array<string, mixed> $row`

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

Internals: `readRow()` (private, line 40), `idList()` (private, line 226), `descendants()` (private, line 233), `where()` (private, line 252), `metaClauses()` (private, line 326), `ids()` (private, line 384), `like()` (private, line 393)


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
A request's rows are kept for the rest of the request, as the reference
keeps them in its object cache, until a term table is written; a lookup
the engine can answer from what it has read (QueryAnswers) runs no SQL.

- const `SHAPING` = `array (   0 => 'get_terms_defaults',   1 => 'parse_term_query',   2 => 'get_terms_args',   3 => 'get_terms_orderby',   4 => 'list_terms_exclusions',   5 => 'get_terms_fields',   6 => 'terms_clauses',   7 => 'terms_pre_query', )` — The filters that shape the request: an engine answer stands only while none of them has a listener.


### `run(Minn\Objects\TermsQuery $query, object $wpdb): mixed`

Runs a term query object's variables: its terms in the shape the
fields ask for, a count, or what a plugin answered first.

Internals: `answered()` (private, line 93), `kept()` (private, line 113), `lists()` (private, line 126), `hierarchical()` (private, line 138), `settle()` (private, line 152), `inHierarchy()` (private, line 169), `order()` (private, line 180), `clauses()` (private, line 191), `exclusions()` (private, line 218), `names()` (private, line 239), `likes()` (private, line 259), `select()` (private, line 276), `limits()` (private, line 305), `meta()` (private, line 315)


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
- `@return array<array-key, Term>`

### `settle(array $terms, array $args): array`

The terms after the tree has had its say: descendants only, padded
counts, empty branches dropped, the page cut.

- `@param array<array-key, Term> $terms`
- `@param array<string, mixed> $args`
- `@return array<array-key, Term>`

### static `pad(array $terms, string $taxonomy): void`

Each term's count raised to the published posts in it or under it, as
_pad_term_counts does for a hierarchical taxonomy.

- `@param array<array-key, Term> $terms`

### static `format(array $terms, string $fields): array`

The terms in the shape the fields ask for.

- `@param array<array-key, Term> $terms`
- `@return array<array-key, mixed>`

Internals: `hasPosts()` (private, line 126)


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

Used by: `Minn\Runtime\TermWrites`

### static `insert(mixed $term, string $taxonomy, array $args): Minn\Objects\Errors|array`

A new term's ids, or why not.

- `@param array<string, mixed> $args`
- `@return array{term_id: int, term_taxonomy_id: int}|Errors`

### static `update(int $termId, string $taxonomy, array $changes): Minn\Objects\Errors|array`

A changed term's ids, or why not.

- `@param array<string, mixed> $changes`
- `@return array{term_id: int, term_taxonomy_id: int}|Errors`

Internals: `write()` (private, line 141), `nameTaken()` (private, line 174), `updatedSlug()` (private, line 198), `slugFromId()` (private, line 217), `row()` (private, line 230)


## TermTags

`final class Minn\Runtime\TermTags` · `public/minn/src/Minn/Runtime/TermTags.php`

What WordPress's template tags tell about a post's terms: its terms of a
taxonomy (as a list primed them, else read), its categories with their
old field names, its tags, its format, and a category's address and the
chain of its parents.

- const `CATEGORY_FIELDS` = `array (   'cat_ID' => 'term_id',   'category_count' => 'count',   'category_description' => 'description',   'cat_name' => 'name',   'category_nicename' => 'slug',   'category_parent' => 'parent', )` — A category's old field names, each with the term field it mirrors, in the order they are added.

Used by: `Minn\Front\FeedTags`, `Minn\Rest\RegisteredPostFields`, `Minn\Runtime\PostLinks`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\TermLookup`

### static `cached(mixed $id, mixed $taxonomy): mixed`

The terms of a post that a list primed or the object cache holds
(get_object_term_cache), each through get_term; false when nothing
holds them.

### static `formatStrings(): array`

The post formats' names (get_post_format_strings), in the site's
language, by slug.

- `@return array<string, string>`

### static `ofPost(mixed $post, mixed $taxonomy): mixed`

A post's terms of a taxonomy (get_the_terms), as get_the_terms leaves them; false for none or no post.

### static `categories(mixed $postId = false): mixed`

A post's categories (get_the_category), each with its old field names, as get_the_categories leaves them.

### static `tags(mixed $post = 0): mixed`

A post's tags (get_the_tags), as get_the_tags leaves them.

### static `format(mixed $post = NULL): mixed`

A post's format (get_post_format): its post_format term's slug without the prefix; false for none or a type without formats.

### static `categoryFields(mixed $category): void`

A category's old field names beside its term fields (_make_cat_compat), on a term object or a term array alike.

### static `categoryLink(mixed $category): mixed`

A category's address (get_category_link), empty for none.

### static `categoryParents(mixed $categoryId, mixed $link = false, mixed $separator = '/', mixed $nicename = false, mixed $deprecated = array ( )): mixed`

A category's parents and itself, outermost first (get_category_parents),
as names or slugs, linked or not, with the separator after each; the
error for an unknown category.

### static `cacheObjects(mixed $objectIds, mixed $objectType): void`

Each taxonomy's term ids of the objects not cached yet, kept in
{taxonomy}_relationships (update_object_term_cache); an object cached
there keeps its entry, and one without terms gets an empty list.

### static `cleanObjects(mixed $objectIds, mixed $objectType): void`

The objects' term caches let go (clean_object_term_cache): each of the
type's taxonomies' relationships, the terms' last_changed moved, then
plugins told; nothing at all while cache invalidation is suspended.

### static `attachmentTaxonomies(mixed $attachment, mixed $output = 'names'): array`

The taxonomies an attachment carries (get_attachment_taxonomies): those
registered for attachments, for its file's extension, its MIME type,
and each half of it (attachment:image, attachment:image/png...).

### static `taxonomies(mixed $post = 0, mixed $args = array ( )): array`

A post's taxonomies as sentences of term links (get_the_taxonomies):
a taxonomy's own template and term_template win over the ones given;
the terms list through wp_sprintf's %l; an attachment's file types
count; a taxonomy without terms is left out.

- `@return array<string, string>`

### static `objectInTerm(mixed $objectId, mixed $taxonomy, mixed $terms = NULL): mixed`

Whether an object has one of the terms (is_object_in_term): an integer
is an id, a digit string an id, a name or a slug, any other string a
name or a slug; no terms asked means any term of the taxonomy. An
error for no object (0) or an unknown taxonomy.

### static `cloudCountText(int $count, mixed $tag, array $args): string`

The tag cloud's count in words for a tag's label: topic_count_text (a
nooped plural) first, then the text callback (default_topic_count_text
counts as none), then the old single_text and multiple_text, else
"%s item" / "%s items" (probe taxonomy-blocks-user-doubts).

### static `allLink(string $taxonomy): string`

The category list's "all" link for a taxonomy: the archive of its
first type when none of its types is a post, else the posts page or
the home page (probe taxonomy-blocks-user-doubts).

### static `bindingValue(array $sourceArgs, mixed $block): mixed`

A term's field for the core/term-data binding
(_block_bindings_term_data_get_value): a navigation link or submenu by
its own id and type ("tag" is post_tag), any other block by the termId
and taxonomy in its context; id, count and parent as text, the name,
slug, description or link. Null for no term, or a term of a hidden
taxonomy to someone who may not edit it (probe taxonomy-blocks-user-doubts).


## TermWriter

`final readonly class Minn\Runtime\TermWriter` · `public/minn/src/Minn/Runtime/TermWriter.php`

The decisions behind wp_delete_term and the object-term relationships:
which relationships to add and remove (saves are Runtime\TermSave). The rows themselves come
from Content\Terms; the lifecycle actions fire from here in the
reference's order. Behaviour pinned by contracts/fixtures/api/content.json.

Used by: `Minn\Runtime\TermWrites`

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
it those terms are recounted quietly.

- `@param list<int> $keep term_taxonomy ids`
- `@param list<int> $old the object's current term_taxonomy ids in the taxonomy`
- `@param (Closure(list<int>): void)|null $count`

### `unrelate(int $objectId, array $ttIds, string $taxonomy, ?Closure $count = NULL): bool`

Removes the given relationships between delete_term_relationships and
deleted_term_relationships, then recounts those terms; true when any
row went.

- `@param list<int> $ttIds`
- `@param (Closure(list<int>): void)|null $count`

### `publishedCount(int $ttId, array $statuses = array (   0 => 'publish', )): int`

How many objects in the counted statuses a term holds, as the stored
count keeps it (published ones unless a plugin widens the list).

- `@param list<string> $statuses`

### `split(int $termId, int $ttId): ?array`

Gives a taxonomy row that shares its term with another taxonomy a
term of its own (the same name, slug and group), and moves its
children under it. Null when no other taxonomy holds the term.

- `@return array{0: int, 1: string, 2: list<int>}|null the new term id, the taxonomy, the children moved`

### `rowTermId(int $ttId): ?int`

The term id a taxonomy row belongs to; null for no such row.

### `rowCount(int $termId): int`

How many taxonomy rows hold a term id, whether their taxonomies are registered or not.

### `anyShared(): bool`

Whether any term id is still held by more than one taxonomy row.

### `relationshipCount(int $ttId): int`

Every object filed under a term, whatever it is: the count a taxonomy of links or other non-posts keeps.

### `storeCount(int $ttId, int $count): void`

Stores a term's count.

### `termIdsOf(array $ttIds): array`

The term ids behind term_taxonomy ids, as stored (strings), in the order given. @param list<int> $ttIds @return list<string>

- `@param list<int> $ttIds @return list<string>`

Internals: `ttIdOf()` (private, line 190)


## TermWrites

`final class Minn\Runtime\TermWrites` · `public/minn/src/Minn/Runtime/TermWrites.php`

Terms written as WordPress's taxonomy functions write them: a term made,
changed and deleted (its children moved up and its default kept), a slug
no other term holds, an object's terms set, and the terms' counts kept
through the taxonomy's own callback; each step with the actions the
reference fires in its order.

Used by: `Minn\Runtime\Attachments`, `Minn\Runtime\Deferrals`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostWrites`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermSave`

### static `insert(mixed $term, mixed $taxonomy, mixed $args = array ( )): mixed`

A new term (wp_insert_term), its fields through their filters in the reference's order (Runtime\TermSave).

### static `update(mixed $termId, mixed $taxonomy, mixed $args = array ( )): mixed`

A term changed (wp_update_term), its fields through their filters in the reference's order (Runtime\TermSave).

### static `delete(mixed $term, mixed $taxonomy, mixed $args = array ( )): mixed`

A term deleted (wp_delete_term): a taxonomy's default term stays (0);
its children move up to its parent, its meta goes row by row, objects
left without a category fall back to the default one; false for an
unknown term or taxonomy.

### static `uniqueSlug(mixed $slug, mixed $term): mixed`

A slug no other term of the taxonomy holds (wp_unique_term_slug): one
taken in the taxonomy gets its parents' slugs added, nearest first,
until it is free (wp_unique_term_slug_is_bad_slug decides whether it
was taken), then "-2", "-3" past any term holding it in any taxonomy.

### static `splitShared(mixed $termId, mixed $ttId, mixed $record = true): int`

A term two taxonomies share, split (_split_shared_term): the taxonomy
row named gets a term of its own, its children follow, split_shared_term
says so, the split is recorded for wp_get_split_term() when asked, and
once no shared term is left finished_splitting_shared_terms is set. A
row held by one taxonomy keeps its term, and a row of another term
gives that term's id (probe taxonomy-blocks-user-doubts).

### static `isShared(mixed $termId): bool`

Whether a term id is held by more than one taxonomy row, registered or not (wp_term_is_shared); never once splitting has finished.

### static `parentWithoutLoops(mixed $parent, mixed $termId, mixed $taxonomy): int`

The default on wp_update_term_parent (wp_check_term_hierarchy_for_loops):
a parent at or under the term itself becomes 0; a loop already above
the parent is broken, each term in it moved to the top level, and the
parent kept (probe taxonomy-blocks-user-doubts).

### static `updateCount(mixed $terms, mixed $taxonomy, mixed $deferred = false): bool`

Terms counted (wp_update_term_count): put off while counting is
deferred; asked with $deferred, what was put off is counted now.

### static `updateCountNow(mixed $terms, mixed $taxonomy): bool`

Terms counted now by term_taxonomy id (wp_update_term_count_now)
through the taxonomy's count callback, then their caches let go. With
no callback of its own, a taxonomy of post types counts published
posts; one of other objects counts them all.

### static `countPublished(mixed $terms, mixed $taxonomy): void`

The published objects behind each term_taxonomy id counted (_update_post_term_count); which statuses count is the plugins' to widen.

### static `countAll(mixed $terms, mixed $taxonomy): void`

Every object behind each term_taxonomy id counted, posts or not (_update_generic_term_count).

### static `setObjectTerms(mixed $objectId, mixed $terms, mixed $taxonomy, mixed $append = false): mixed`

An object's terms set (wp_set_object_terms), a term named but not
there yet made; added to the ones it had when asked to append. The
term_taxonomy ids, or the error making one gave.

### static `removeObjectTerms(mixed $objectId, mixed $terms, mixed $taxonomy): mixed`

Terms taken off an object (wp_remove_object_terms), the ones left counted again; the error for an unknown taxonomy.

### static `deleteObjectRelationships(mixed $objectId, mixed $taxonomies): void`

Every term of the taxonomies taken off an object (wp_delete_object_term_relationships).

Internals: `slugUnderParents()` (private, line 316), `moveChildren()` (private, line 335), `query()` (private, line 345), `writer()` (private, line 350)


## ThemeMods

`final class Minn\Runtime\ThemeMods` · `public/minn/src/Minn/Runtime/ThemeMods.php`

The active theme's settings (theme_mods_{stylesheet}) as WordPress keeps
them: read through theme_mod_{name}, a default with %s naming the
theme's folders, written through pre_set_theme_mod_{name}, and removed one
by one or all at once.

Used by: `Minn\Front\CustomLogo`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\NavMenu`, `Minn\Runtime\ThemeSwitch`, `Minn\Theme\ClassicRenderer`

### static `all(): array`

Every setting of the active theme (get_theme_mods).

- `@return array<array-key, mixed>`

### static `get(mixed $name, mixed $default = false): mixed`

One setting (get_theme_mod), else the default (a %s in it names the parent's and the theme's folder addresses).

### static `set(mixed $name, mixed $value): bool`

A setting written (set_theme_mod), through pre_set_theme_mod_{name}.

### static `remove(mixed $name): void`

A setting removed (remove_theme_mod); the last one takes the option with it.

### static `removeAll(): void`

Every setting removed (remove_theme_mods).


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

Used by: `Minn\Admin\SiteController`, `Minn\Front\CustomLogo`, `Minn\Front\ToolbarMenus`, `Minn\Rest\InstalledThemesController`, `Minn\Runtime\AvMetadata`, `Minn\Runtime\Runtime`, `Minn\Theme\ClassicRenderer`

### static `get(mixed $feature, mixed ...$args): mixed`

A feature's support (get_theme_support): false when the theme has none;
with an argument, that part of it (one of a list, or a key's value).

### static `current(mixed $feature, mixed ...$args): mixed`

Whether the theme supports a feature (current_theme_supports): for the
features with parts, the part asked; otherwise
current_theme_supports-{feature} has the last word.

### static `all(): array`

Every feature the theme has declared, as stored. @return array<string, mixed>

- `@return array<string, mixed>`

### static `add(string $feature, array $args): bool`

add_theme_support: false when refused. @param list<mixed> $args

- `@param list<mixed> $args`

### static `remove(string $feature): bool`

remove_theme_support: false when the theme did not support it.

### static `classicEditorSettings(): array`

get_classic_theme_supports_block_editor_settings: the editor settings a
classic theme's supports amount to, the custom-value switches, and its
palette, sizes and gradients when it declares them.

- `@return array<string, mixed>`

### static `blockThemeDefaults(): void`

_add_default_theme_supports: what every block theme supports before its own setup.

### static `editorDefaults(string $which): void`

wp_enable_block_templates and wp_setup_widgets_block_editor: a block theme's templates, and the block widget editor for any theme.

### static `justInTime(): void`

_custom_header_background_just_in_time: the header's and background's defaults filled in once loaded.

### static `register(string $feature, array $args): Minn\Objects\Errors|true`

register_theme_feature: true, or why the feature cannot be registered.

### static `features(): array`

Core's features, then those registered since. @return array<string, array<string, mixed>>

- `@return array<string, array<string, mixed>>`

### static `forRest(Minn\Objects\RestRequest $request): array`

The active theme's supports as wp/v2/themes shows them: each feature shown in REST, shaped by its schema. @return array<string, mixed>

- `@return array<string, mixed>`

### static `prepared(mixed $support, array $config, string $feature, Minn\Objects\RestRequest $request): mixed`

One feature's support as the REST API shows it (the runtime's
WP_REST_Themes_Controller::prepare_theme_support): a feature that is
not variadic shown by its first value, through its prepare_callback
or else cut to its schema.

- `@param array<string, mixed> $config the feature as it was registered`

Internals: `withConstants()` (private, line 115), `unwrapped()` (private, line 285)


## ThemeSwitch

`final class Minn\Runtime\ThemeSwitch` · `public/minn/src/Minn/Runtime/ThemeSwitch.php`

Switching the theme as the reference does it, in two halves. The switch
(switch_theme): the leaving theme keeps its widgets and its menu
locations are remembered, the three theme options move, the switch is
recorded (theme_switched), the arriving theme's mods start with the
classic sidebars, only its mods stay autoloaded, then switch_theme. The
next load, on init (check_theme_switched): after_switch_theme with the
theme that left, whose defaults map the menu locations across
(_wp_menus_changed) and look the widgets over, then the rules flushed
and the record cleared. Deleting a theme fires delete_theme and
deleted_theme around its folder going, and forgets the update check.

- const `SLUG_GROUPS` = `array (   0 =>    array (     0 => 'primary',     1 => 'menu-1',     2 => 'main',     3 => 'header',     4 => 'navigation',     5 => 'top',   ),   1 =>    array (     0 => 'secondary',     1 => 'menu-2',     2 => 'footer',     3 => 'subsidiary',     4 => 'bottom',   ),   2 =>    array (     0 => 'social',   ), )` — The menu location names, by role, a theme's locations are matched across by.

Used by: `Minn\Admin\PackagesController`, `Minn\Admin\ThemesController`

### static `switchTo(string $stylesheet): void`

switch_theme.

### static `checkSwitched(): void`

check_theme_switched: on the load after a switch, after_switch_theme with the theme that left.

### static `menusChanged(): void`

_wp_menus_changed: the leaving theme's menu locations mapped onto the arriving theme's.

### static `mapLocations(array $new, mixed $old): array`

wp_map_nav_menu_locations: with one location on each side, it is
kept; otherwise a location of the same name keeps its menu, then by
role, each name of a role in turn hands the next unused location of
that role (with a menu) to the arriving locations that name it.

- `@param array<string, int> $new`

### static `delete(string $stylesheet, ?callable $remove = NULL): bool`

delete_theme: the folder removed between delete_theme and deleted_theme
(by $remove when the caller removes it its own way); false for no name.

- `@param (callable(string): bool)|null $remove`

Internals: `removeFolder()` (private, line 139), `nextOfGroup()` (private, line 149), `names()` (private, line 162)


## Themes

`final class Minn\Runtime\Themes` · `public/minn/src/Minn/Runtime/Themes.php`

The site's themes as WordPress names and finds them: the active
stylesheet and template (as their filters leave them), the themes root on
disk and its address, each active theme's folder and its address, a theme
by its folder, every installed theme, and the extra roots plugins
register.

Used by: `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Rest\InstalledThemesController`, `Minn\Runtime\PostTags`, `Minn\Runtime\PostWrites`, `Minn\Runtime\Runtime`, `Minn\Runtime\SiteInfo`, `Minn\Runtime\TemplateFiles`, `Minn\Runtime\ThemeMods`, `Minn\Runtime\ThemeSwitch`, `Minn\Runtime\Upgrade`, `Minn\Runtime\UserStylesPost`

### static `stylesheet(): mixed`

The active theme's folder (get_stylesheet).

### static `template(): mixed`

The active parent theme's folder (get_template).

### static `root(mixed $stylesheetOrTemplate = ''): mixed`

Where themes live on disk (get_theme_root).

### static `rootUri(mixed $stylesheetOrTemplate = '', mixed $themeRoot = ''): mixed`

The themes root's address (get_theme_root_uri).

### static `stylesheetDirectory(): mixed`

The active theme's folder on disk (get_stylesheet_directory).

### static `templateDirectory(): mixed`

The active parent theme's folder on disk (get_template_directory).

### static `stylesheetDirectoryUri(): mixed`

The active theme's folder address (get_stylesheet_directory_uri).

### static `isBlockTheme(): bool`

Whether the current theme is a block theme (wp_is_block_theme): the booted one's answer, or, for a stylesheet a filter swapped in, that theme's own.

### static `stylesheetUri(): mixed`

The active theme's stylesheet address (get_stylesheet_uri), as stylesheet_uri leaves it.

### static `templateDirectoryUri(): mixed`

The active parent theme's folder address (get_template_directory_uri).

### static `theme(mixed $stylesheet = '', mixed $themeRoot = ''): Minn\Objects\Theme`

A theme by its folder, the active one when none is named (wp_get_theme).

### static `all(mixed $args = array ( )): array`

Every installed theme by its folder (wp_get_themes).

- `@return array<string, Theme>`

### static `registerDirectory(mixed $directory): bool`

A further themes root (register_theme_directory), under wp-content when not found as given; false when it is nowhere.


## TimeSince

`final class Minn\Runtime\TimeSince` · `public/minn/src/Minn/Runtime/TimeSince.php`

How long ago something was, as human_time_diff words it: the span
between two times in its largest whole unit ("3 days"), through
human_time_diff.

Used by: `Minn\Rest\BlockDirectoryController`

### static `between(int $from, int $to = 0): string`

The span from one time to another (now when none is given).


## Transients

`final class Minn\Runtime\Transients` · `public/minn/src/Minn/Runtime/Transients.php`

Values kept for a while, as WordPress keeps them in options: the site's
transients (_transient_{name}, with _transient_timeout_{name} when they
expire) and the network's (_site_transient_{name}), each read, set and
removed through the filters and actions plugins hook. An expired one reads
as false and is removed as it is read.

Used by: `Minn\Ops\Updates`, `Minn\Rest\PatternDirectoryController`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\ThemeSwitch`, `Minn\Runtime\UpdateCounts`

### static `get(mixed $transient): mixed`

A transient's value, or false (get_transient).

### static `set(mixed $transient, mixed $value, mixed $expiration = 0): bool`

A transient set (set_transient): one that expires is stored not to
autoload, its timeout beside it; setting an expiry on one that had
none stores it again that way.

### static `delete(mixed $transient): bool`

A transient removed (delete_transient), its timeout with it.

### static `getSite(mixed $transient): mixed`

A network transient's value, or false (get_site_transient); the update offers never expire by their timeout.

### static `setSite(mixed $transient, mixed $value, mixed $expiration = 0): bool`

A network transient set (set_site_transient), its timeout beside it when it expires.

### static `deleteSite(mixed $transient): bool`

A network transient removed (delete_site_transient), its timeout with it.


## Translations

`final class Minn\Runtime\Translations` · `public/minn/src/Minn/Runtime/Translations.php`

The translations a request loads, as WordPress's l10n functions load
them: the locale in force, a text domain loaded from its file (or its
folder named for later) and unloaded, the default domain, a plugin's,
a user's locale, and the translations installed on disk; each step with
the filters that may answer first.

Used by: `Minn\I18n\SiteLanguage`, `Minn\Login\LoginController`, `Minn\Media\Player`, `Minn\Rest\BlockDirectoryController`, `Minn\Rest\PatternDirectoryController`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\PluginFiles`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\Plugins`, `Minn\Theme\ThemeHeaders`

### static `folderFiles(string $path): mixed`

The translation files in a folder (WP_Textdomain_Registry::get_language_files_from_path):
the folder as pre_get_language_files_from_path hears it, trailing
slash and all, may answer; else its .mo and .l10n.php files, listed
once a request in the translation_files cache group.

### static `forgetFolders(mixed $hookExtra): void`

After an upgrade that installed translations, the listings of the
folders they went to are forgotten (WP_Textdomain_Registry::invalidate_mo_files_cache):
the plugins or themes language folder, else the language folder itself.

### static `locale(): string`

The locale in force (determine_locale): a switched one, else pre_determine_locale, else the configured one through determine_locale.

### static `load(mixed $domain, mixed $mofile, mixed $locale = NULL): bool`

A text domain loaded from a translation file (load_textdomain), the
PHP file beside the .mo first unless translation_file_format says mo;
whether one loaded. Its locale becomes the one translations are looked
up in, and its folder where its script translations are looked for.

### static `unload(mixed $domain, mixed $reloadable = false): bool`

A text domain unloaded (unload_textdomain): whether it had files; one that did stays closed unless it may load again.

### static `fromFolder(string $domain, string $folder): bool`

A domain's folder named for later (load_plugin_textdomain and the
rest): nothing is read and no locale asked until the domain's first
translation, which reads the folder's file for the locale then (probe
text-domain-timing).

### static `loadDefault(mixed $locale = NULL): bool`

The default domain loaded again for a locale, the one in force unless named (load_default_textdomain).

### static `loadPlugin(mixed $domain, mixed $deprecated = false, mixed $relativePath = false): bool`

A plugin's domain named to load from its folder
(load_plugin_textdomain): a path under the plugins folder when one is
given; else the deprecated folder under the site's root, told as
deprecated since 2.7.0; else the plugins folder itself.

### static `userLocale(mixed $user = 0): string`

A user's locale (get_user_locale), the signed-in user's for 0; the site's when the user has none or does not exist.

### static `installed(mixed $type): array`

The translations installed (wp_get_installed_translations) for core,
plugins or themes: by text domain and locale, each with its file's
headers; a .po without its .mo is not one.

- `@return array<string, array<string, array<string, string>>>`


## TreeWalk

`final class Minn\Runtime\TreeWalk` · `public/minn/src/Minn/Runtime/TreeWalk.php`

The Walker contract's traversal: elements keyed by the walker's
db_fields parent/id pair, displayed depth-first through the walker's
four element methods, whole or a page of top-level elements at a time
(probe comment-walker). The facade Walker delegates here; subclasses
override the element methods (or display_element itself, as
Walker_Comment does to keep replies past the depth limit at their
parent's level) and the dispatch stays virtual.

### static `walk(object $walker, array $elements, int $maxDepth, array $args): string`

Walks a tree with a Walker the way the reference does.

- `@param list<object> $elements`

### static `page(object $walker, array $elements, int $maxDepth, int $page, int $perPage, array $args): string`

One page of top-level elements, each with its children (paged_walk);
no paging when the page is below one or the size negative. Sets the
walker's max_pages. Elements whose parent is not in the list are
orphans, printed at the top level after the last page's elements;
the replies of earlier pages' elements are not orphans.

- `@param list<object> $elements`

### static `roots(object $walker, array $elements): int`

How many of the elements have no parent. @param list<object> $elements

- `@param list<object> $elements`

### static `forget(object $walker, mixed $element, array $children): void`

An element's children, and theirs through the walker's unset_children,
taken out of the children list.

- `@param array<int|string, list<object>> $children`

### static `element(object $walker, mixed $element, array $children, int $maxDepth, int $depth, array $args, string $output): void`

Walks one element and its children; replies past the depth limit stay
in the list for the walker (or the orphans) to place.

- `@param array<int|string, mixed> $children`

Internals: `buckets()` (private, line 165), `alone()` (private, line 182), `orphans()` (private, line 189)


## TypeProps

`final class Minn\Runtime\TypeProps` · `public/minn/src/Minn/Runtime/TypeProps.php`

A post type's or taxonomy's properties from the arguments it was built
with, as WP_Post_Type::set_props and WP_Taxonomy::set_props fill them
(probe type-object-methods): the reference's defaults where an argument
is missing, a given value kept as given.

### static `postType(string $name, array $args): array`

A post type's properties from its arguments (probe type-object-methods):
the reference's defaults where an argument is missing, a given value
kept as given. The labels are the type's own; get_post_type_labels
makes them whole on the object.

- `@param array<string, mixed> $args`
- `@return array<string, mixed>`

### static `taxonomy(string $name, array $objectTypes, array $args): array`

A taxonomy's properties from the types it belongs to and its
arguments (probe type-object-methods): the reference's defaults where
an argument is missing, a given value kept as given. The meta box is
the category checklist for a hierarchical taxonomy and the tag box for
any other; its input is read as checkboxes for the checklist and as
text otherwise. A default term gets a name, slug and description.

- `@param list<mixed> $objectTypes`
- `@param array<string, mixed> $args`
- `@return array<string, mixed>`

Internals: `typeRewrite()` (private, line 81), `taxonomyRewrite()` (private, line 155)


## UpdateCounts

`final class Minn\Runtime\UpdateCounts` · `public/minn/src/Minn/Runtime/UpdateCounts.php`

The updates waiting, as wp_get_update_data counts them for the user
(probe admin-bar): plugins and themes from their update transients,
WordPress itself unless it is current, translations; each only for a
user who may update it. WordPress counts its own update only where
wp-admin's update functions are loaded; the engine always has them, so
it counts it everywhere.

Used by: `Minn\Front\ToolbarMenus`

### static `data(): mixed`

The updates waiting, counted for what the user may update, with their title (wp_get_update_data), as wp_get_update_data leaves them.

### static `translations(): array`

The translation updates waiting (wp_get_translation_updates): none, as
the engine fetches no language packs on its own.

- `@return list<object>`

### static `core(mixed $options = array ( )): mixed`

The core releases offered (get_core_updates), each marked not dismissed; false when the offer holds none.

### static `forCurrentUser(): array`

The counts and their title (before the wp_get_update_data filter),
and the titles one by one.

- `@return array{0: array{counts: array<string, int>, title: string}, 1: array<string, string>}`


## Upgrade

`final class Minn\Runtime\Upgrade` · `public/minn/src/Minn/Runtime/Upgrade.php`

What WP_Upgrader does with a package (probe upgrader), for the facade's
upgraders and so for everything that installs or updates a plugin or a
theme. run() is the whole pass: the options through
upgrader_package_options, the package downloaded and unpacked into
wp-content/upgrade/, put in place by install(), the result through
upgrader_install_package_result and told to the skin, and, for a single
item, upgrader_process_complete. The steps a subclass may replace
(fs_connect, download_package, unpack_package, install_package,
clear_destination) are called on the upgrader itself, as the reference
calls them, so a plugin's own upgrader class is honoured.

An update names the folder it replaces (hook_extra temp_backup); that
copy is moved to wp-content/upgrade-temp-backup/<dir>/<slug> before the
destination is cleared, moved back when the new one fails, and removed
once the pass is over. The reference keeps it for a weekly event to
clear and puts it back at shutdown; Minn keeps nothing and restores at
once.

- const `RUN` = `array (   'package' => '',   'destination' => '',   'clear_destination' => false,   'clear_working' => true,   'abort_if_destination_exists' => true,   'is_multi' => false,   'hook_extra' =>    array (   ), )` — The run options in the reference's order, which upgrader_package_options sees.

### static `run(Minn\Objects\Upgrader $upgrader, array $options): mixed`

WP_Upgrader::run; false when the filesystem cannot be reached.

### static `bulk(Minn\Objects\Upgrader $upgrader, array $items, array $offers, array $how): array|false`

The bulk pass of Plugin_Upgrader and Theme_Upgrader: the skin opened,
maintenance mode on when an active item is replaced, each item with an
offer run as one of many (one without is up to date, answered true),
the pass stopped when the filesystem refuses, then maintenance off, the
update cache cleaned, upgrader_process_complete told the whole list,
and the skin closed. False when the filesystem cannot be reached.

- `@param list<string> $items plugin files or theme folders`
- `@param array<string, mixed> $offers item => the offer (its package)`
- `@param array{directories: list<string>, maintenance: bool, prepare: \Closure(string): array{0: string, 1: array}, clean: \Closure(): void, complete: array} $how`
- `@return array<string, mixed>|false`

### static `install(Minn\Objects\Upgrader $upgrader, array $args): Minn\Objects\Errors|array`

WP_Upgrader::install_package: the unpacked folder chosen (its one
folder, or the working folder when it holds several things), offered
to upgrader_pre_install and upgrader_source_selection, the destination
cleared or refused when something is there, the folder moved in (and
walked by the symbol gate at its next check), and the result offered
to upgrader_post_install.

### static `unzip(mixed $file, mixed $to): mixed`

An archive unpacked into a folder through the site's filesystem (unzip_file): true, or the error why not.

### static `unpack(Minn\Objects\Upgrader $upgrader, string $package): Minn\Objects\Errors|string`

WP_Upgrader::unpack_package, without the package's deletion (the
caller's): the upgrade folder emptied of what earlier passes left,
the package unzipped into a folder named for it, and an archive that
will not open refused in the upgrader's words with the reader's reason.

### static `clear(Minn\Objects\Upgrader $upgrader, string $remote): Minn\Objects\Errors|bool`

WP_Upgrader::clear_destination: whatever is at the destination removed; true when nothing is left there.

### static `maintenanceOn(Minn\Objects\Upgrader $upgrader): void`

Maintenance mode on, as a bulk update of active plugins turns it on: the .maintenance file the front door reads.

### static `forgetUpdateEmail(string $key): void`

An update's own line taken out of the stored list of updates still to
be mailed about (auto_plugin_theme_update_emails), once it is done
(probe db-mail-rewrite-doubts).

### static `lastCoreUpdateFailed(string $offered, string $current, mixed $failure): bool`

Whether a failed core update holds the next one back: a critical
failure does, and so does the same offer failing from the same version
when it is not to be retried.

### static `maintenanceOff(Minn\Objects\Upgrader $upgrader): void`

Maintenance mode off; said only when it was on.

Internals: `destinationFor()` (private, line 283), `clearOrRefuse()` (private, line 292), `move()` (private, line 313), `copyInto()` (private, line 324), `report()` (private, line 338), `setAside()` (private, line 356), `putBack()` (private, line 369), `forget()` (private, line 381), `backedUp()` (private, line 390), `listing()` (private, line 407)


## UserCounts

`final class Minn\Runtime\UserCounts` · `public/minn/src/Minn/Runtime/UserCounts.php`

The user counts WordPress's user functions give: how many users hold each
role (count_users), and how many posts several users wrote
(count_many_users_posts), as the reference was measured counting them
(probes user-remaining, taxonomy-blocks-user-doubts).

### static `users(mixed $strategy = 'time', mixed $siteId = NULL): mixed`

How many users there are and how many hold each role (count_users).
Only a user with a capabilities row counts; "none" is a user whose
row names nothing. The time strategy lists the registered roles that
have users, in their registered order, then none; any other strategy
lists none first, then every role a row names (registered or not), as
met user by user. pre_count_users may answer first.

### static `manyUsersPosts(mixed $users, mixed $postType = 'post', mixed $publicOnly = false): mixed`

Post counts for several users (count_many_users_posts), as stored, 0
for none: published posts, and private ones too unless public only,
the viewer's own or every author's for a viewer who may read the
type's private posts. pre_count_many_users_posts may answer first.

- `@return array<int|string, int|string>`


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

Internals: `prepared()` (private, line 117)


## UserInsert

`final readonly class Minn\Runtime\UserInsert` · `public/minn/src/Minn/Runtime/UserInsert.php`

The decisions behind wp_insert_user: what a new account needs, which email
and login collide, the resolved profile fields, and which columns an
update changes. The sanitisers and lookups arrive as closures so the
reference's filters apply. Behaviour pinned by contracts/fixtures/api/functions.json.

- const `META_KEYS` = `array (   0 => 'nickname',   1 => 'first_name',   2 => 'last_name',   3 => 'description',   4 => 'rich_editing',   5 => 'syntax_highlighting',   6 => 'comment_shortcuts',   7 => 'admin_color',   8 => 'use_ssl',   9 => 'show_admin_bar_front',   10 => 'locale', )`

Used by: `Minn\Runtime\UserWrites`

```php
__construct(Closure $sanitizeLogin, Closure $sanitizeSlug, Closure $isEmail, Closure $loginTaken, Closure $emailOwner)
```
- `@param Closure(string): string $sanitizeLogin`
- `@param Closure(string): string $sanitizeSlug`
- `@param Closure(string): bool $isEmail`
- `@param Closure(string): bool $loginTaken`
- `@param Closure(string): int $emailOwner 0 when nobody has it`


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

### static `clause(Minn\Objects\UsersQuery $query, string $orderby, object $wpdb): string`

The column or expression one orderby key stands for.

Internals: `postCount()` (private, line 53)


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

- const `DEFAULTS` = `array (   'blog_id' => 1,   'role' => '',   'role__in' =>    array (   ),   'role__not_in' =>    array (   ),   'capability' => '',   'capability__in' =>    array (   ),   'capability__not_in' =>    array (   ),   'meta_key' => '',   'meta_value' => '',   'meta_compare' => '',   'include' =>    array (   ),   'exclude' =>    array (   ),   'search' => '',   'search_columns' =>    array (   ),   'orderby' => 'login',   'order' => 'ASC',   'offset' => '',   'number' => '',   'paged' => 1,   'count_total' => true,   'fields' => 'all',   'who' => '',   'has_published_posts' => NULL,   'nicename' => '',   'nicename__in' =>    array (   ),   'nicename__not_in' =>    array (   ),   'login' => '',   'login__in' =>    array (   ),   'login__not_in' =>    array (   ),   'cache_results' => true, )` — A user query's variables before its arguments are laid over them, in the order the user query hooks receive them.
- const `COLUMNS` = `array (   0 => 'id',   1 => 'user_login',   2 => 'user_pass',   3 => 'user_nicename',   4 => 'user_email',   5 => 'user_url',   6 => 'user_registered',   7 => 'user_activation_key',   8 => 'user_status',   9 => 'display_name', )`
- const `SEARCHABLE` = `array (   0 => 'ID',   1 => 'user_login',   2 => 'user_email',   3 => 'user_url',   4 => 'user_nicename',   5 => 'display_name', )`
- const `EMAIL` = `'user_email'`
- const `URL` = `'user_url'`

```php
__construct(object $wpdb)
```


### static `fillVars(mixed $args): array`

A users query's variables with their defaults filled in
(WP_User_Query::fill_query_vars), on this one site.

- `@return array<string, mixed>`

### `prepare(Minn\Objects\UsersQuery $query): void`

Builds the query's pieces from its variables, as prepare_query does.

### static `searchSql(string $search, array $columns, string|false $wild): string`

A search over columns (get_search_sql): ID matched exactly, the rest
by LIKE with wildcards at the leading or trailing end, or both.

- `@param list<string> $columns`

### `query(Minn\Objects\UsersQuery $query): void`

Runs the query: users_pre_query may answer it; the count follows; the results take the fields' shape.

Internals: `fields()` (private, line 82), `published()` (private, line 97), `names()` (private, line 108), `meta()` (private, line 125), `order()` (private, line 158), `search()` (private, line 184)


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

Used by: `Minn\Runtime\UserWrites`

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


## UserStylesPost

`final class Minn\Runtime\UserStylesPost` · `public/minn/src/Minn/Runtime/UserStylesPost.php`

A theme's user styles post as WordPress's theme.json resolver finds and
makes it (probe global-styles-lookup): the newest wp_global_styles post
tied to the theme by its wp_theme term, through WP_Query (so plugins hear
the query and may steer it), made when asked and missing.

Used by: `Minn\Rest\InstalledThemesController`

### static `data(mixed $theme, mixed $createPost = false, mixed $statuses = array (   0 => 'publish', )): array`

A theme's user styles post as an array
(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles): the
newest of the statuses given, by the theme's name. When asked to make
one and there is none, the post is made for that theme, any theme,
its term given as tax_input (so set only for a user who may assign
it). [] when there is none.

- `@return array<string, mixed>`

### static `id(): int`

The active theme's user styles post id (WP_Theme_JSON_Resolver::get_user_global_styles_post_id), the post made when it is missing.


## UserWrites

`final class Minn\Runtime\UserWrites` · `public/minn/src/Minn/Runtime/UserWrites.php`

Accounts written as WordPress's user functions write them: an account
made, changed (its row, profile meta and role, with the notices a
changed password or email sends) and deleted (its posts deleted or
handed on), a reset key issued, the lookups that guard a new account,
and the cached rows let go; each step with the actions and filters the
reference fires in its order.

- const `ROW_FIELDS` = `array (   'user_login' => true,   'user_nicename' => true,   'user_url' => true,   'user_email' => true,   'display_name' => true, )` — The fields an account's row takes from its filtered fields.

Used by: `Minn\Front\ToolbarMenus`, `Minn\Rest\UsersController`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\DeprecatedUserMeta`, `Minn\Runtime\UserEvents`, `Minn\Runtime\UserSave`

### static `validUsername(mixed $username): bool`

Whether a username survives strict sanitising unchanged and is not empty (validate_username), as validate_username leaves it.

### static `usernameExists(mixed $username): mixed`

The id of the account with the login (username_exists), or false, as username_exists leaves it.

### static `emailExists(mixed $email): mixed`

The id of the account with the email (email_exists), or false, as email_exists leaves it.

### static `isMember(mixed $userId = 0, mixed $blogId = 0): bool`

Whether the user (the signed-in one when none is named) belongs to the site (is_user_member_of_blog).

### static `appPasswordsAvailable(): bool`

Whether the site offers application passwords (wp_is_application_passwords_available): over HTTPS or on a local site, as the filter leaves it.

### static `appPasswordsAvailableFor(mixed $user): bool`

Whether a user may sign in by application password (wp_is_application_passwords_available_for_user): the site allows them, then the filter.

### static `resetKey(mixed $user): mixed`

A fresh reset key (get_password_reset_key), its hash stored under the
time it was issued through the account's save; the refusal when the
account may not reset.

### static `create(mixed $username, mixed $password, mixed $email = ''): mixed`

A new account (wp_create_user) from a login, password and email.

### static `insert(mixed $userdata): mixed`

An account made or changed (wp_insert_user) from slashed fields, as
the reference takes them: each field through its pre_user_ filter
first, slashed, in the reference's order; the row's fields then
unslashed, the profile meta unslashed as it is stored, and the
password the one given (hashed for a new account, stored as given on
an update). Its id, or the error saying why not.

### static `update(mixed $userdata): mixed`

An account changed (wp_update_user): the stored account and its
profile under the given fields, as the reference merges them, then
the notices a changed password or email sends.

### static `delete(mixed $id, mixed $reassign = NULL): bool`

An account deleted (wp_delete_user): its posts deleted, or handed to
the account named; each meta key removed with its own actions, then
the row; false for an unknown account.

### static `cleanCache(mixed $user): void`

A user's cached rows and meta let go (clean_user_cache), plugins told.

### static `row(mixed $user): ?Minn\Content\UserRecord`

An account's row from an id, a login, a user object, or an object with an ID.

Internals: `asArray()` (private, line 262), `insertAccount()` (private, line 282), `filteredRow()` (private, line 310), `updateProfile()` (private, line 333), `changeNotices()` (private, line 376)


## VersionCheck

`final class Minn\Runtime\VersionCheck` · `public/minn/src/Minn/Runtime/VersionCheck.php`

Whether the WordPress version Minn reports and the PHP it runs on meet a
requirement (a plugin's "Requires at least" and "Requires PHP"), and where
to read about updating PHP.

Used by: `Minn\Runtime\PluginRequirements`

### static `wordpress(mixed $required): bool`

Whether the reported WordPress version is at least the one required (is_wp_version_compatible); nothing required always is.

### static `php(mixed $required): bool`

Whether PHP is at least the version required (is_php_version_compatible); nothing required always is.

### static `updatePhpUrl(): string`

The page about updating PHP (wp_get_update_php_url): the filter's, unless it gives no address.

### static `defaultUpdatePhpUrl(): string`

The page about updating PHP before any filter (wp_get_default_update_php_url), in the site's language.


## WidgetAreas

`final class Minn\Runtime\WidgetAreas` · `public/minn/src/Minn/Runtime/WidgetAreas.php`

The widgets helper functions as the reference answers them (probe
widget-helpers): a widget id split into its base and number, a sidebar
by id (the inactive widgets are a sidebar too, with a name and nothing
else), finding and moving a widget between sidebars, one widget rendered
in its sidebar as dynamic_sidebar renders each (an unregistered widget
renders nothing), and the sidebars with every registered one present;
the stored sidebars read and written, and which are registered.

- const `DEFAULT_WIDGETS` = `array (   0 => 'WP_Widget_Pages',   1 => 'WP_Widget_Calendar',   2 => 'WP_Widget_Archives',   3 => 'WP_Widget_Media_Audio',   4 => 'WP_Widget_Media_Image',   5 => 'WP_Widget_Media_Gallery',   6 => 'WP_Widget_Media_Video',   7 => 'WP_Widget_Meta',   8 => 'WP_Widget_Search',   9 => 'WP_Widget_Text',   10 => 'WP_Widget_Categories',   11 => 'WP_Widget_Recent_Posts',   12 => 'WP_Widget_Recent_Comments',   13 => 'WP_Widget_RSS',   14 => 'WP_Widget_Tag_Cloud',   15 => 'WP_Nav_Menu_Widget',   16 => 'WP_Widget_Custom_HTML',   17 => 'WP_Widget_Block', )` — The widgets the engine provides.
- const `INACTIVE` = `'wp_inactive_widgets'`
- const `MAPPING_GROUPS` = `array (   0 =>    array (     0 => 'sidebar',     1 => 'primary',     2 => 'main',     3 => 'right',   ),   1 =>    array (     0 => 'second',     1 => 'left',   ),   2 =>    array (     0 => 'sidebar-2',     1 => 'footer',     2 => 'bottom',   ),   3 =>    array (     0 => 'header',     1 => 'top',   ), )` — The id fragments a theme switch matches sidebars by, group by group (probe sidebars-mapping).

Used by: `Minn\Rest\SidebarsController`, `Minn\Rest\WidgetObject`, `Minn\Rest\WidgetsController`, `Minn\Runtime\ThemeSwitch`

### static `registerDefaults(): void`

The widgets the engine provides registered, then widgets_init for
plugin and theme code (wp_widgets_init); the Links widget only while
the link manager is on. The block widget is the one every modern
sidebar is made of, so it is registered before the action runs.

### static `show(mixed $widget, mixed $instance = array ( ), mixed $args = array ( )): void`

A registered widget shown outside a sidebar (the_widget), in a div with its class unless the arguments say otherwise.

### static `stored(mixed $deprecated = true): mixed`

The stored sidebars and their widgets (wp_get_sidebars_widgets), as
sidebars_widgets leaves them.

- `@return array<string, mixed>`

### static `convertSettings(string $base, mixed $settings): array`

A widget's settings in the numbered form (wp_convert_widget_settings,
probe blocks-rest-widget-doubts): settings that are empty or not keyed
by number are a single widget's, made number 2, and the sidebars this
request holds name it by its new id; every result is marked a
multi-widget's. Nothing is stored.

- `@return array<int|string, mixed>`

### static `convertStoredSettings(string $base, string $option, mixed $settings): array`

The same conversion as the admin makes it: a single widget's new id
goes into the stored sidebars (not this request's), and the settings
are stored under their option either way.

- `@return array<int|string, mixed>`

### static `store(mixed $sidebars): void`

The sidebars stored (wp_set_sidebars_widgets), at array version 3.

### static `current(mixed $themeChanged = false): array`

The sidebars with every registered one present and the widgets that
lost theirs inactive (retrieve_widgets), stored; before widgets_init,
the stored ones with the registered areas filled in empty.

- `@return array<string, mixed>`

### static `isRegistered(mixed $id): bool`

Whether a sidebar is registered (is_registered_sidebar).

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

### static `retrieve(array $stored, array $areas, array $registered): array`

retrieve_widgets (probe widgets-remaining): the inactive list, then every
registered sidebar, holding only widgets that are registered. The
inactive list takes, in order, the registered widgets no list mentions
(the -1 placeholders aside, in the order they were registered), the
widgets already inactive, and the widgets of sidebars no longer
registered, which go.

- `@param array<string, mixed> $stored the stored lists, sidebar => widget ids`
- `@param list<string> $areas the registered sidebars`
- `@param list<string> $registered the registered widgets`
- `@return array<string, array<int, string>>`

### static `map(array $existing, array $areas, array $stored, array $registered): array`

The old theme's widget lists fitted to the new theme's sidebars
(probe sidebars-mapping). One old sidebar and one new map straight
across. Otherwise a sidebar of the same id keeps its widgets; then,
group by group, the new sidebars whose ids hold one of the group's
fragments take, in order, the old ones that do (a new sidebar may take
from several groups); what is left goes after the inactive widgets.
When nothing is inactive then, the stored inactive widgets that are
registered and placed nowhere come back, keys kept.

- `@param array<string, mixed> $existing the old theme's lists`
- `@param list<string> $areas the new theme's sidebars`
- `@param array<array-key, mixed> $stored the theme's stored inactive widgets`
- `@param list<string> $registered the registered widgets`
- `@return array<string, array<array-key, mixed>>`

Internals: `numbered()` (private, line 111), `renamed()` (private, line 121), `inGroup()` (private, line 326), `unmentioned()` (private, line 343)


## WpdbFields

`final class Minn\Runtime\WpdbFields` · `public/minn/src/Minn/Runtime/WpdbFields.php`

The database object's writers and the charset work behind them (probe
db-mail-rewrite-doubts): an insert, replace, update or delete prepares one
statement holding every value (a null written as NULL in it), and an
insert or replace starts with insert_id at 0; the fields are checked
against their columns, every field that would not fit named in the error;
a table's columns are kept as the server's rows; a table that cannot be
read gives the charset failure; text in a charset other than UTF-8,
latin1 or binary is converted by the server; and check_safe_collation
passes ASCII, the statements that read no rows, latin1 tables and tables
whose text columns use only a binary or general UTF-8 collation.

- const `SAFE_COLLATION` = `'/^utf8(?:mb[34])?_(?:bin|general_ci)$/i'`

Used by: `Minn\Runtime\WpdbReads`

### static `insert(object $db, mixed $table, mixed $data, mixed $format, mixed $type): int|bool`

Inserts or replaces a row from processed fields (wpdb::_insert_replace_helper): rows written, or false.

### static `update(object $db, mixed $table, mixed $data, mixed $where, mixed $format, mixed $whereFormat): int|bool`

Updates the rows a where list matches (wpdb::update): rows written, or false.

### static `delete(object $db, mixed $table, mixed $where, mixed $whereFormat): int|bool`

Deletes the rows a where list matches (wpdb::delete): rows deleted, or false.

### static `process(object $db, mixed $table, mixed $data, mixed $format): array|false`

The fields with their formats, column charsets and lengths, or false:
when a column cannot be read, or when a value would not fit unchanged,
last_error naming every such field (wpdb::process_fields).

### static `charsets(object $db, mixed $data, mixed $table): array|false`

Each field with its column's charset, or false when the table cannot be read (wpdb::process_field_charsets).

### static `lengths(object $db, mixed $data, mixed $table): array|false`

Each field with its column's length, or false when the table cannot be read (wpdb::process_field_lengths).

### static `strip(object $db, mixed $data): mixed`

Each text value cut to what its column keeps (wpdb::strip_invalid_text):
malformed bytes dropped for UTF-8, four-byte characters too for utf8,
latin1 and binary kept, then cut to the column's length; text in any
other charset goes through the server's conversion, in one query.

### static `stripQuery(object $db, mixed $query): mixed`

A query with what its table (the connection, when it names none) cannot hold dropped (wpdb::strip_invalid_text_from_query).

### static `safeCollation(object $db, mixed $query): bool`

Whether a query can skip the text check (wpdb::check_safe_collation).

### static `tableCharset(object $db, mixed $table): mixed`

The charset a table's text columns share, kept for the request
(wpdb::get_table_charset); pre_get_table_charset may answer first, and
a table the server cannot describe is the charset failure.

### static `columnCharset(object $db, mixed $table, mixed $column): mixed`

A column's charset: false for one that holds no text, the table's for a column it does not have (wpdb::get_col_charset).

### static `columnLength(object $db, mixed $table, mixed $column): mixed`

The most a text or binary column holds, false for any other column, the charset failure for a table that cannot be read (wpdb::get_col_length).

### static `queryCharset(object $db, string $query): string|false`

The charset a query's text is held to: its table's, the connection's when it names none or its table cannot be read.

Internals: `columns()` (private, line 261), `sharedCharset()` (private, line 288), `charsetOf()` (private, line 314), `unreadable()` (private, line 320), `cut()` (private, line 326), `convert()` (private, line 346), `marks()` (private, line 381), `conditions()` (private, line 402), `send()` (private, line 418)


## WpdbReads

`final class Minn\Runtime\WpdbReads` · `public/minn/src/Minn/Runtime/WpdbReads.php`

What the database object plugins hold as $wpdb does when it runs and reads
(probe db-mail-rewrite-doubts): a query is heard by the query filter while
the last one's state is still there, and is not run at all while the
object is not ready; the readers ask check_safe_collation before running
one, give null for an empty value, and give nothing when asked to read
without a query (get_row, get_results) or in an output they do not know;
prepare's notices; and print_error, which keeps every error in
$EZSQL_ERROR and logs it with its caller. Members the object keeps
protected are reached in its own scope.

- const `LOG_LINE` = `'WordPress database error %1$s for query %2$s'`

### static `query(object $db, mixed $query): int|bool`

Runs a query through the object, as wpdb::query: the number of rows read or written, true, or false.

### static `value(object $db, mixed $query, mixed $x, mixed $y): mixed`

One value of the last result (or of a query run first): null for an empty one.

### static `row(object $db, mixed $query, mixed $output, mixed $y): mixed`

One row of a query, as an object or an array; null with no query, or in an output it does not know.

### static `column(object $db, mixed $query, mixed $x): array`

One column of the last result (or of a query run first), each value read
through the object's own get_var.

- `@return list<mixed>`

### static `rows(object $db, mixed $query, mixed $output): ?array`

The rows of a query in an output shape; null with no query, or in an output it does not know.

### static `prepare(object $db, mixed $query, array $args): ?string`

A query with its placeholders filled (Placeholders), every literal % as
the placeholder escape query() takes back out (wpdb::prepare). One
argument that is a list stands for the arguments. Reported through
doing_it_wrong: an argument it cannot use, a query with no % at all, a
count that does not match (too few arguments empty the query), and a
list of other than one value for a query with one placeholder (null).

- `@param list<mixed> $args`

### static `printError(object $db, mixed $str): string`

Reports a database error: kept in $EZSQL_ERROR, then (unless errors are
suppressed) logged with the calls that led to it. The markup to print
while errors are shown, else ''.

Internals: `ask()` (private, line 188), `run()` (private, line 203), `keep()` (private, line 222), `isObjectOutput()` (private, line 238)


## WpdbSetup

`final class Minn\Runtime\WpdbSetup` · `public/minn/src/Minn/Runtime/WpdbSetup.php`

How the database object plugins hold as $wpdb is set up (probe
db-mail-rewrite-doubts): it connects through its own db_connect, which
sets the connection's names with prepare; set_prefix answers with the
prefix it had, names the old tables too, and with set_table_names off
changes only the base prefix; set_blog_id names one site's tables from
the base prefix; CUSTOM_USER_TABLE and CUSTOM_USER_META_TABLE name the
user tables; set_sql_mode reads the session's modes without a query of
its own; a database that cannot be selected leaves the object not ready.
One site only: the engine is never a network.

### static `construct(object $db, mixed $user, mixed $password, mixed $name, mixed $host): void`

Sets the object up over the engine's connection (wpdb::__construct).

### static `connect(object $db): bool`

Takes the engine's connection and sets its names (wpdb::db_connect): true.

### static `charset(object $db, mixed $dbh, mixed $charset, mixed $collate): void`

Sets a connection's charset and its names, with the collation when there is one (wpdb::set_charset).

### static `prefix(object $db, mixed $prefix, mixed $setTableNames): mixed`

Sets the table prefix (wpdb::set_prefix): the base prefix it had (the
new one the first time), or invalid_db_prefix for a prefix that is not
letters, digits and underscores.

### static `blog(object $db, mixed $blogId, mixed $networkId): int`

Switches the site the object answers for (wpdb::set_blog_id): the id it had.

### static `tables(object $db, mixed $scope, mixed $prefix): array`

The tables of a scope (wpdb::tables), by name to prefixed name unless
the prefix is not wanted; the user tables are the custom ones where
wp-config names them.

### static `select(object $db, mixed $name, mixed $dbh): void`

Selects a database; one that cannot be selected leaves the object not ready (wpdb::select).

### static `sqlMode(object $db, mixed $modes): void`

Sets the session's SQL modes (wpdb::set_sql_mode): the given ones, or the
session's own read from the connection, less the modes
incompatible_sql_modes names.

Internals: `name()` (private, line 152)

