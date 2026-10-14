# `Minn\Objects`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`BlockType`](#blocktype) | interface | 3 | A registered block type as the WordPress layer hands it to plugins |
| [`Comment`](#comment) | interface | 3 | A comment as the WordPress layer hands it to plugins (WP_Comment). The |
| [`CommentsQuery`](#commentsquery) | interface | 3 | A comments query as the WordPress layer hands it to plugins |
| [`Dependency`](#dependency) | interface | 3 | A registered script or style as the WordPress layer hands it to plugins |
| [`Environment`](#environment) | interface | 3 | The request's environment as the WordPress layer hands it to plugins |
| [`Errors`](#errors) | interface | 3 | A list of errors as the WordPress layer hands it to plugins (WP_Error): |
| [`HttpResponse`](#httpresponse) | interface | 3 | An HTTP response as the WordPress layer hands it to plugins |
| [`Post`](#post) | interface | 3 | A post as the WordPress layer hands it to plugins (WP_Post). The engine |
| [`PostType`](#posttype) | interface | 3 | A registered post type as the WordPress layer hands it to plugins |
| [`Query`](#query) | interface | 3 | A posts query as the WordPress layer hands it to plugins (WP_Query): its |
| [`RenderedBlock`](#renderedblock) | interface | 3 | A block being rendered as the WordPress layer hands it to plugins |
| [`RestRequest`](#restrequest) | interface | 3 | A REST request as the WordPress layer hands it to plugins |
| [`RestResponse`](#restresponse) | interface | 3 | A REST response as the WordPress layer hands it to plugins |
| [`RestServer`](#restserver) | interface | 3 | The REST server as the WordPress layer hands it to plugins |
| [`Rewrite`](#rewrite) | interface | 3 | The rewrite object as the WordPress layer hands it to plugins |
| [`SessionTokens`](#sessiontokens) | interface | 3 | A user's sessions as the WordPress layer hands them to plugins |
| [`SiteLocale`](#sitelocale) | interface | 3 | The site's locale as the WordPress layer hands it to plugins |
| [`SitemapProvider`](#sitemapprovider) | interface | 3 | A sitemap provider as the WordPress layer hands it to plugins |
| [`SitemapServer`](#sitemapserver) | interface | 3 | The sitemaps as the WordPress layer hands them to plugins (WP_Sitemaps): |
| [`Taxonomy`](#taxonomy) | interface | 3 | A registered taxonomy as the WordPress layer hands it to plugins |
| [`Term`](#term) | interface | 3 | A term as the WordPress layer hands it to plugins (WP_Term). The engine |
| [`TermsQuery`](#termsquery) | interface | 3 | A terms query as the WordPress layer hands it to plugins (WP_Term_Query). |
| [`Theme`](#theme) | interface | 3 | A theme as the WordPress layer hands it to plugins (WP_Theme): its |
| [`ThemeJsonLayer`](#themejsonlayer) | interface | 3 | One origin's theme.json data as the WordPress layer hands it to the |
| [`Toolbar`](#toolbar) | interface | 3 | The toolbar as the WordPress layer hands it to plugins (WP_Admin_Bar): |
| [`Upgrader`](#upgrader) | interface | 3 | An upgrader as the WordPress layer hands it to plugins (WP_Upgrader and |
| [`UpgraderSkin`](#upgraderskin) | interface | 3 | What an upgrader reports through (WP_Upgrader_Skin and its kinds): the |
| [`User`](#user) | interface | 3 | A user as the WordPress layer hands it to plugins (WP_User). The engine |
| [`UsersQuery`](#usersquery) | interface | 3 | A users query as the WordPress layer hands it to plugins (WP_User_Query). |
| [`Widget`](#widget) | interface | 3 | A widget as the WordPress layer hands it to plugins (WP_Widget): its id |

## BlockType

`interface Minn\Objects\BlockType` · `public/minn/src/Minn/Objects/BlockType.php`

A registered block type as the WordPress layer hands it to plugins
(WP_Block_Type): its attributes, supports, styles and render callback.
The engine knows these objects by this mark and finds them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Runtime\Handoff`


## Comment

`interface Minn\Objects\Comment` · `public/minn/src/Minn/Objects/Comment.php`

A comment as the WordPress layer hands it to plugins (WP_Comment). The
engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Rest\CommentsController`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentPages`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\CommentThreads`, `Minn\Runtime\Handoff`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\Records`


## CommentsQuery

`interface Minn\Objects\CommentsQuery` · `public/minn/src/Minn/Objects/CommentsQuery.php`

A comments query as the WordPress layer hands it to plugins
(WP_Comment_Query). The engine knows these objects by this mark and makes
them through Runtime\Handoff, so it never names the WordPress layer's
class.

Used by: `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Rest\CommentsController`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\CommentQueryWhere`, `Minn\Runtime\CommentThreads`, `Minn\Runtime\Handoff`


## Dependency

`interface Minn\Objects\Dependency` · `public/minn/src/Minn/Objects/Dependency.php`

A registered script or style as the WordPress layer hands it to plugins
(_WP_Dependency): its handle, source, dependencies, version and extra
data. The engine knows these objects by this mark, so it never names the
WordPress layer's class.

Used by: `Minn\Runtime\AssetEdits`


## Environment

`interface Minn\Objects\Environment` · `public/minn/src/Minn/Objects/Environment.php`

The request's environment as the WordPress layer hands it to plugins
(WP, the $wp global): the query variables parsed from the request and
the headers sent. The engine knows it by this mark, so it never names
the WordPress layer's class.

Used by: `Minn\Theme\FrontLifecycle`


## Errors

`interface Minn\Objects\Errors` · `public/minn/src/Minn/Objects/Errors.php`

A list of errors as the WordPress layer hands it to plugins (WP_Error):
codes with their messages and data. The engine knows these objects by this
mark and makes them through Runtime\Handoff, so it never names the
WordPress layer's class.

Used by: `Minn\Front\CommentPostController`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Login\LoginNotices`, `Minn\Ops\DirectoryApi`, `Minn\Ops\UpgraderRun`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\MediaController`, `Minn\Rest\PluginsController`, `Minn\Rest\RegisteredFields`, `Minn\Rest\Responses`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Runtime\AbilityRun`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ApplicationPasswordEvents`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentForm`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\PostSave`, `Minn\Runtime\Records`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermSave`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\Upgrade`, `Minn\Runtime\UserEvents`


## HttpResponse

`interface Minn\Objects\HttpResponse` · `public/minn/src/Minn/Objects/HttpResponse.php`

An HTTP response as the WordPress layer hands it to plugins
(WP_HTTP_Response): its data, status and headers. The engine knows these
objects by this mark and makes them through Runtime\Handoff, so it never
names the WordPress layer's class.

Used by: `Minn\Rest\Responses`


## Post

`interface Minn\Objects\Post` · `public/minn/src/Minn/Objects/Post.php`

A post as the WordPress layer hands it to plugins (WP_Post). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\MediaShortcodes`, `Minn\Content\PasswordGate`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\PostEmbed`, `Minn\Front\RuleTable`, `Minn\Media\Icons`, `Minn\Rest\BlockRendererController`, `Minn\Rest\PostsController`, `Minn\Rest\RenderedFields`, `Minn\Rest\TermsController`, `Minn\Runtime\DocumentTitle`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\PostData`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\PostWrites`, `Minn\Runtime\Records`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\QueryClasses`


## PostType

`interface Minn\Objects\PostType` · `public/minn/src/Minn/Objects/PostType.php`

A registered post type as the WordPress layer hands it to plugins
(WP_Post_Type): its labels, capabilities and settings. The engine knows
these objects by this mark and makes them through Runtime\Handoff, so it
never names the WordPress layer's class.

Used by: `Minn\Auth\RegisteredCaps`, `Minn\Rest\PostCollectionParams`, `Minn\Rest\RegisteredType`, `Minn\Runtime\Handoff`, `Minn\Runtime\PostQueryParts`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\Registry`


## Query

`interface Minn\Objects\Query` · `public/minn/src/Minn/Objects/Query.php`

A posts query as the WordPress layer hands it to plugins (WP_Query): its
variables, flags, posts and loop. The engine knows these objects by this
mark and makes them through Runtime\Handoff, so it never names the
WordPress layer's class.

Used by: `Minn\Front\FeedTags`, `Minn\Front\Renderer`, `Minn\Rest\PostsController`, `Minn\Runtime\CommentFeedQuery`, `Minn\Runtime\Handoff`, `Minn\Runtime\MainQuery`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\PostData`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostQueryTax`, `Minn\Runtime\PostQueryWhere`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\QueryClasses`


## RenderedBlock

`interface Minn\Objects\RenderedBlock` · `public/minn/src/Minn/Objects/RenderedBlock.php`

A block being rendered as the WordPress layer hands it to plugins
(WP_Block): its parsed block, attributes, context and inner blocks. The
engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Runtime\Handoff`


## RestRequest

`interface Minn\Objects\RestRequest` · `public/minn/src/Minn/Objects/RestRequest.php`

A REST request as the WordPress layer hands it to plugins
(WP_REST_Request): its route, method, parameters, headers and body. The
engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Rest\Api`, `Minn\Rest\CommentListArgs`, `Minn\Rest\CommentsController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\PostListArgs`, `Minn\Rest\RegisteredFields`, `Minn\Rest\Responses`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\TermListArgs`, `Minn\Rest\UserListArgs`, `Minn\Rest\UsersController`, `Minn\Runtime\ApplicationPasswordEvents`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\Handoff`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\UserEvents`


## RestResponse

`interface Minn\Objects\RestResponse` · `public/minn/src/Minn/Objects/RestResponse.php`

A REST response as the WordPress layer hands it to plugins
(WP_REST_Response): its data, status, headers and links. The engine knows
these objects by this mark and makes them through Runtime\Handoff, so it
never names the WordPress layer's class.

Used by: `Minn\Rest\Responses`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimeRoutes`, `Minn\Runtime\Handoff`


## RestServer

`interface Minn\Objects\RestServer` · `public/minn/src/Minn/Objects/RestServer.php`

The REST server as the WordPress layer hands it to plugins
(WP_REST_Server). The engine knows these objects by this mark and makes
them through Runtime\Handoff, so it never names the WordPress layer's
class.

Used by: `Minn\Rest\Responses`, `Minn\Rest\RuntimeRoutes`


## Rewrite

`interface Minn\Objects\Rewrite` · `public/minn/src/Minn/Objects/Rewrite.php`

The rewrite object as the WordPress layer hands it to plugins
(WP_Rewrite): the permalink structures, the extra rules and endpoints
plugins add, and the rules built from them. The engine knows it by this
mark, so it never names the WordPress layer's class.

Used by: `Minn\Front\RuleTable`, `Minn\Runtime\ArchiveLinks`, `Minn\Runtime\CommentPages`, `Minn\Runtime\RewriteRules`


## SessionTokens

`interface Minn\Objects\SessionTokens` · `public/minn/src/Minn/Objects/SessionTokens.php`

A user's sessions as the WordPress layer hands them to plugins
(WP_Session_Tokens, or the manager session_token_manager names): made,
read, verified and ended. The engine knows them by this mark and asks
for them through Runtime\Handoff, so it never names the WordPress
layer's class.

Used by: `Minn\Runtime\Handoff`


## SiteLocale

`interface Minn\Objects\SiteLocale` · `public/minn/src/Minn/Objects/SiteLocale.php`

The site's locale as the WordPress layer hands it to plugins
(WP_Locale): the names of weekdays and months, the number format and the
text direction. The engine knows it by this mark and makes it through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\I18n\SiteLanguage`, `Minn\Runtime\Handoff`


## SitemapProvider

`interface Minn\Objects\SitemapProvider` · `public/minn/src/Minn/Objects/SitemapProvider.php`

A sitemap provider as the WordPress layer hands it to plugins
(WP_Sitemaps_Provider and its kinds): the object subtypes it lists and
their URLs. The engine knows these objects by this mark, so it never
names the WordPress layer's class.

Used by: `Minn\Front\SitemapRequest`


## SitemapServer

`interface Minn\Objects\SitemapServer` · `public/minn/src/Minn/Objects/SitemapServer.php`

The sitemaps as the WordPress layer hands them to plugins (WP_Sitemaps):
the providers registered and the index and stylesheets they serve. The
engine knows them by this mark, so it never names the WordPress layer's
class.

Used by: `Minn\Front\SitemapRequest`


## Taxonomy

`interface Minn\Objects\Taxonomy` · `public/minn/src/Minn/Objects/Taxonomy.php`

A registered taxonomy as the WordPress layer hands it to plugins
(WP_Taxonomy): its labels, capabilities and settings. The engine knows
these objects by this mark and makes them through Runtime\Handoff, so it
never names the WordPress layer's class.

Used by: `Minn\Auth\RegisteredCaps`, `Minn\Runtime\Handoff`, `Minn\Runtime\Registry`


## Term

`interface Minn\Objects\Term` · `public/minn/src/Minn/Objects/Term.php`

A term as the WordPress layer hands it to plugins (WP_Term). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Rest\TermFilters`, `Minn\Rest\TermsController`, `Minn\Runtime\DocumentTitle`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenu`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostLinks`, `Minn\Runtime\Records`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Runtime\TermTags`, `Minn\Runtime\TermWrites`, `Minn\Theme\QueryClasses`


## TermsQuery

`interface Minn\Objects\TermsQuery` · `public/minn/src/Minn/Objects/TermsQuery.php`

A terms query as the WordPress layer hands it to plugins (WP_Term_Query).
The engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Runtime\Handoff`, `Minn\Runtime\TermOrder`, `Minn\Runtime\TermQueryRunner`


## Theme

`interface Minn\Objects\Theme` · `public/minn/src/Minn/Objects/Theme.php`

A theme as the WordPress layer hands it to plugins (WP_Theme): its
headers, folders, templates and files. The engine knows these objects by
this mark and makes them through Runtime\Handoff, so it never names the
WordPress layer's class.

Used by: `Minn\Rest\Api`, `Minn\Rest\InstalledThemesController`, `Minn\Runtime\Handoff`, `Minn\Runtime\Themes`


## ThemeJsonLayer

`interface Minn\Objects\ThemeJsonLayer` · `public/minn/src/Minn/Objects/ThemeJsonLayer.php`

One origin's theme.json data as the WordPress layer hands it to the
wp_theme_json_data_ filters (WP_Theme_JSON_Data), which may change it.
The engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Runtime\Handoff`, `Minn\Theme\ThemeJsonData`


## Toolbar

`interface Minn\Objects\Toolbar` · `public/minn/src/Minn/Objects/Toolbar.php`

The toolbar as the WordPress layer hands it to plugins (WP_Admin_Bar):
the nodes and groups a menu callback adds. The engine knows it by this
mark, so it never names the WordPress layer's class.

Used by: `Minn\Front\ToolbarMenus`


## Upgrader

`interface Minn\Objects\Upgrader` · `public/minn/src/Minn/Objects/Upgrader.php`

An upgrader as the WordPress layer hands it to plugins (WP_Upgrader and
the plugin and theme upgraders): it fetches, unpacks and installs a
package. The engine knows these objects by this mark and makes them
through Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Ops\UpgraderRun`, `Minn\Runtime\Handoff`, `Minn\Runtime\Upgrade`


## UpgraderSkin

`interface Minn\Objects\UpgraderSkin` · `public/minn/src/Minn/Objects/UpgraderSkin.php`

What an upgrader reports through (WP_Upgrader_Skin and its kinds): the
feedback and errors of an install or update. The engine knows these
objects by this mark and makes them through Runtime\Handoff, so it never
names the WordPress layer's class.

Used by: `Minn\Ops\UpgraderRun`, `Minn\Runtime\Handoff`


## User

`interface Minn\Objects\User` · `public/minn/src/Minn/Objects/User.php`

A user as the WordPress layer hands it to plugins (WP_User). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Auth\Can`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMenus`, `Minn\Login\LoginHooks`, `Minn\Rest\UsersController`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\CommentForm`, `Minn\Runtime\Handoff`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\PostTags`, `Minn\Runtime\Translations`, `Minn\Runtime\UserWrites`, `Minn\Theme\QueryClasses`


## UsersQuery

`interface Minn\Objects\UsersQuery` · `public/minn/src/Minn/Objects/UsersQuery.php`

A users query as the WordPress layer hands it to plugins (WP_User_Query).
The engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Rest\UsersController`, `Minn\Runtime\Handoff`, `Minn\Runtime\UserOrder`, `Minn\Runtime\UserQueryRunner`


## Widget

`interface Minn\Objects\Widget` · `public/minn/src/Minn/Objects/Widget.php`

A widget as the WordPress layer hands it to plugins (WP_Widget): its id
base, name, options and the form and update methods a widget class
writes. The engine knows these objects by this mark, so it never names
the WordPress layer's class.

Used by: `Minn\Rest\SidebarsController`, `Minn\Rest\WidgetObject`, `Minn\Rest\WidgetsController`, `Minn\Widgets\WidgetForms`

