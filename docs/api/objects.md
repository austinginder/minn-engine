# `Minn\Objects`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comment`](#comment) | interface | 3 | A comment as the WordPress layer hands it to plugins (WP_Comment). The |
| [`CommentsQuery`](#commentsquery) | interface | 3 | A comments query as the WordPress layer hands it to plugins |
| [`Errors`](#errors) | interface | 3 | A list of errors as the WordPress layer hands it to plugins (WP_Error): |
| [`HttpResponse`](#httpresponse) | interface | 3 | An HTTP response as the WordPress layer hands it to plugins |
| [`Post`](#post) | interface | 3 | A post as the WordPress layer hands it to plugins (WP_Post). The engine |
| [`Query`](#query) | interface | 3 | A posts query as the WordPress layer hands it to plugins (WP_Query): its |
| [`RestRequest`](#restrequest) | interface | 3 | A REST request as the WordPress layer hands it to plugins |
| [`RestResponse`](#restresponse) | interface | 3 | A REST response as the WordPress layer hands it to plugins |
| [`RestServer`](#restserver) | interface | 3 | The REST server as the WordPress layer hands it to plugins |
| [`Term`](#term) | interface | 3 | A term as the WordPress layer hands it to plugins (WP_Term). The engine |
| [`TermsQuery`](#termsquery) | interface | 3 | A terms query as the WordPress layer hands it to plugins (WP_Term_Query). |
| [`Theme`](#theme) | interface | 3 | A theme as the WordPress layer hands it to plugins (WP_Theme): its |
| [`User`](#user) | interface | 3 | A user as the WordPress layer hands it to plugins (WP_User). The engine |
| [`UsersQuery`](#usersquery) | interface | 3 | A users query as the WordPress layer hands it to plugins (WP_User_Query). |

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

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\MediaShortcodes`, `Minn\Content\PasswordGate`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\PostEmbed`, `Minn\Front\RuleTable`, `Minn\Media\Icons`, `Minn\Rest\BlockRendererController`, `Minn\Rest\PostsController`, `Minn\Rest\RenderedFields`, `Minn\Rest\TermsController`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\PostData`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostReads`, `Minn\Runtime\PostRevisions`, `Minn\Runtime\Records`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\QueryClasses`


## Query

`interface Minn\Objects\Query` · `public/minn/src/Minn/Objects/Query.php`

A posts query as the WordPress layer hands it to plugins (WP_Query): its
variables, flags, posts and loop. The engine knows these objects by this
mark and makes them through Runtime\Handoff, so it never names the
WordPress layer's class.

Used by: `Minn\Front\FeedTags`, `Minn\Front\Renderer`, `Minn\Rest\PostsController`, `Minn\Runtime\CommentFeedQuery`, `Minn\Runtime\Handoff`, `Minn\Runtime\MainQuery`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\PostData`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostQueryResults`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostQueryTax`, `Minn\Runtime\PostQueryWhere`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\QueryClasses`


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


## Term

`interface Minn\Objects\Term` · `public/minn/src/Minn/Objects/Term.php`

A term as the WordPress layer hands it to plugins (WP_Term). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Rest\TermFilters`, `Minn\Rest\TermsController`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenu`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostLinks`, `Minn\Runtime\Records`, `Minn\Runtime\TermLookup`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Theme\QueryClasses`


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


## User

`interface Minn\Objects\User` · `public/minn/src/Minn/Objects/User.php`

A user as the WordPress layer hands it to plugins (WP_User). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Auth\Can`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMenus`, `Minn\Login\LoginHooks`, `Minn\Rest\UsersController`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\CommentForm`, `Minn\Runtime\Handoff`, `Minn\Runtime\MetaKeys`, `Minn\Theme\QueryClasses`


## UsersQuery

`interface Minn\Objects\UsersQuery` · `public/minn/src/Minn/Objects/UsersQuery.php`

A users query as the WordPress layer hands it to plugins (WP_User_Query).
The engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Rest\UsersController`, `Minn\Runtime\Handoff`, `Minn\Runtime\UserOrder`, `Minn\Runtime\UserQueryRunner`

