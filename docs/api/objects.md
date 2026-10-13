# `Minn\Objects`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comment`](#comment) | interface | 3 | A comment as the WordPress layer hands it to plugins (WP_Comment). The |
| [`Errors`](#errors) | interface | 3 | A list of errors as the WordPress layer hands it to plugins (WP_Error): |
| [`Post`](#post) | interface | 3 | A post as the WordPress layer hands it to plugins (WP_Post). The engine |
| [`Term`](#term) | interface | 3 | A term as the WordPress layer hands it to plugins (WP_Term). The engine |
| [`User`](#user) | interface | 3 | A user as the WordPress layer hands it to plugins (WP_User). The engine |

## Comment

`interface Minn\Objects\Comment` · `public/minn/src/Minn/Objects/Comment.php`

A comment as the WordPress layer hands it to plugins (WP_Comment). The
engine knows these objects by this mark and makes them through
Runtime\Handoff, so it never names the WordPress layer's class.

Used by: `Minn\Rest\CommentsController`, `Minn\Runtime\CommentForm`, `Minn\Runtime\CommentPages`, `Minn\Runtime\CommentQueryRunner`, `Minn\Runtime\CommentThreads`, `Minn\Runtime\Handoff`, `Minn\Runtime\MetaKeys`


## Errors

`interface Minn\Objects\Errors` · `public/minn/src/Minn/Objects/Errors.php`

A list of errors as the WordPress layer hands it to plugins (WP_Error):
codes with their messages and data. The engine knows these objects by this
mark and makes them through Runtime\Handoff, so it never names the
WordPress layer's class.

Used by: `Minn\Front\CommentPostController`, `Minn\Login\LoginController`, `Minn\Login\LoginHooks`, `Minn\Login\LoginNotices`, `Minn\Ops\DirectoryApi`, `Minn\Ops\UpgraderRun`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BatchController`, `Minn\Rest\MediaController`, `Minn\Rest\PluginsController`, `Minn\Rest\RegisteredFields`, `Minn\Rest\RestMeta`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Runtime\AbilityRun`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ApplicationPasswordEvents`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\CommentForm`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\PluginRemoval`, `Minn\Runtime\PostSave`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermSave`, `Minn\Runtime\ThemeSupports`, `Minn\Runtime\Upgrade`, `Minn\Runtime\UserEvents`


## Post

`interface Minn\Objects\Post` · `public/minn/src/Minn/Objects/Post.php`

A post as the WordPress layer hands it to plugins (WP_Post). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Content\MediaShortcodes`, `Minn\Content\PasswordGate`, `Minn\Front\FeedTags`, `Minn\Front\FeedTemplates`, `Minn\Front\PostEmbed`, `Minn\Front\RuleTable`, `Minn\Media\Icons`, `Minn\Rest\BlockRendererController`, `Minn\Rest\PostsController`, `Minn\Rest\RenderedFields`, `Minn\Rest\TermsController`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\PostData`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostLinks`, `Minn\Runtime\PostRevisions`, `Minn\Theme\FrontLifecycle`, `Minn\Theme\QueryClasses`


## Term

`interface Minn\Objects\Term` · `public/minn/src/Minn/Objects/Term.php`

A term as the WordPress layer hands it to plugins (WP_Term). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Rest\TermFilters`, `Minn\Rest\TermsController`, `Minn\Runtime\Handoff`, `Minn\Runtime\MenuItemMarks`, `Minn\Runtime\MetaKeys`, `Minn\Runtime\NavMenuItems`, `Minn\Runtime\PostLinks`, `Minn\Runtime\TermQueryTree`, `Minn\Runtime\TermSave`, `Minn\Theme\QueryClasses`


## User

`interface Minn\Objects\User` · `public/minn/src/Minn/Objects/User.php`

A user as the WordPress layer hands it to plugins (WP_User). The engine
knows these objects by this mark and makes them through Runtime\Handoff,
so it never names the WordPress layer's class.

Used by: `Minn\Auth\Can`, `Minn\Front\PostEmbed`, `Minn\Front\ToolbarMenus`, `Minn\Login\LoginHooks`, `Minn\Rest\UsersController`, `Minn\Runtime\AccountFlows`, `Minn\Runtime\ApplicationPasswordSignIn`, `Minn\Runtime\CommentForm`, `Minn\Runtime\Handoff`, `Minn\Runtime\MetaKeys`, `Minn\Theme\QueryClasses`

