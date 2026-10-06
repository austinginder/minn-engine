# `Minn\Http`

request, response, routing, and the outgoing client

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Access`](#access) | enum | 15 | Who a route is for. The six answers every route gives, so the |
| [`Args`](#args) | final class | 192 | The parameters a route accepts, as the reference describes them in the |
| [`CertificateName`](#certificatename) | final class | 56 | Whether a TLS certificate names a host: a wildcard only as a whole first |
| [`CookieText`](#cookietext) | final class | 94 | Set-Cookie text and the matching rules a cookie jar applies: parsing a |
| [`Destination`](#destination) | final readonly class | 97 | Where Minn\Http lets a request go. Only http and https; when hosts are |
| [`Download`](#download) | final class | 32 | A file the engine fetches for itself (a package, a language pack), over |
| [`Envelope`](#envelope) | interface | 9 | What runs around a matched route once the router knows the route takes |
| [`Exchange`](#exchange) | final readonly class | 75 | What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error. |
| [`Failure`](#failure) | final class | 176 | What the public sees when the engine cannot answer: a plain page with no |
| [`Fake`](#fake) | final class | 57 | Answers requests in place of the network while a test runs, and keeps |
| [`Ipv6`](#ipv6) | final class | 62 | IPv6 addresses as text: written out in full (eight groups, or six and an |
| [`IriParts`](#iriparts) | final readonly class | 138 | An IRI (a URL that may carry non-ASCII text) split into its parts and |
| [`Kernel`](#kernel) | final readonly class | 36 | The edge. Turns a request into a response through the router and turns |
| [`Location`](#location) | final class | 32 | Where a redirect leads: a Location header made absolute against the URL that sent it. |
| [`Matched`](#matched) | final readonly class | 15 | A route the router matched to a request and whose policy it judged: the |
| [`Method`](#method) | enum | 33 |  |
| [`Outbound`](#outbound) | final readonly class | 46 | One outgoing HTTP request, normalised: the transport needs nothing else. |
| [`Policy`](#policy) | final readonly class | 105 | What a route requires of its caller, as data on the route: the router |
| [`Punycode`](#punycode) | final class | 103 | Internationalized host names in ASCII: each label that is not ASCII is |
| [`RawResponse`](#rawresponse) | final class | 82 | An HTTP response as text, the way the Requests library hands it from |
| [`Request`](#request) | final readonly class | 139 | An immutable picture of the incoming request. Built once from the PHP |
| [`RequestFailed`](#requestfailed) | final class | 7 | Thrown by Exchange::throw() when no response arrived or it was not a 2xx; the exchange rides along. |
| [`RequestsNames`](#requestsnames) | final class | 18 | The Requests library's PSR-0 class names (Requests_Exception_HTTP_404, |
| [`Response`](#response) | final readonly class | 111 | What a handler returns. Nothing is written to the client until the |
| [`Route`](#route) | final readonly class | 63 | Declares a handler method as a route. The policy lives here, as |
| [`RouteMiss`](#routemiss) | final class | 3 | A handler declining a request its pattern matched: the router swallows |
| [`RouteRow`](#routerow) | final readonly class | 61 | One line of the route table: what a route is, who it is for, and what it |
| [`Router`](#router) | final class | 206 | Matches a request to a #[Route] on one of the registered handler |
| [`Subject`](#subject) | enum | 46 | The record a route capture names, so a policy can have it looked up |
| [`Transport`](#transport) | final class | 138 | The engine's outgoing HTTP transport over curl: it sends exactly what an |
| [`TrustedProxies`](#trustedproxies) | final readonly class | 104 | Which addresses in front of the engine may speak for the client. |

## Access

`enum Minn\Http\Access` · `public/minn/src/Minn/Http/Access.php`

Who a route is for. The six answers every route gives, so the
authorization surface of the engine reads as a list of these.

Cases: `Public`, `SignedIn`, `Cap`, `Floor`, `Own`, `Type`

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Policy`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\AjaxController`


## Args

`final class Minn\Http\Args` · `public/minn/src/Minn/Http/Args.php`

The parameters a route accepts, as the reference describes them in the
REST index: name => {description, type, ...}. The descriptions, types,
enums and bounds were captured from the reference, so a client that
reads either index is told the same thing, and the router refuses a
value the way the reference refuses it, before it judges the caller.

A route declares only what it really reads. An argument published here
that the handler ignores would be worse than none at all, because a
client reading the index would build a request around it, so each set
below names the code that consumes it. The sets keep the reference's
order, because a refusal lists the invalid parameters in that order.
`_fields` and `_embed` are read everywhere and declared nowhere, as the
reference lists neither and validates neither.

- const `SHARED` = `array (   0 => 'context',   1 => 'page',   2 => 'per_page',   3 => 'search', )` — The shared collection parameters, judged first: the reference answers
a refusal among these alone, and reads the route's own parameters
only once these pass.
- const `HANDLER_VALIDATES` = `'handler_validates'` — The key an argument carries when its handler judges it (a status the caller may not read is refused before the enum is).
- const `CONTEXT` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ), )` — Read by Rest\Context::of(): which view of a resource is wanted.
- const `FORCE` = `array (   'force' =>    array (     'description' => 'Whether to bypass Trash and force deletion.',     'type' => 'boolean',     'default' => false,     'required' => false,   ), )` — Read by Content\PostWriter: whether a delete bypasses the trash.
- const `POSTS` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'author' =>    array (     'description' => 'Limit result set to posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'author_exclude' =>    array (     'description' => 'Ensure result set excludes posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'date',     'enum' =>      array (       0 => 'author',       1 => 'date',       2 => 'id',       3 => 'include',       4 => 'modified',       5 => 'parent',       6 => 'relevance',       7 => 'slug',       8 => 'include_slugs',       9 => 'title',     ),     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to posts with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ),   'status' =>    array (     'default' => 'publish',     'description' => 'Limit result set to posts assigned one or more statuses.',     'type' => 'array',     'items' =>      array (       'enum' =>        array (         0 => 'publish',         1 => 'future',         2 => 'draft',         3 => 'pending',         4 => 'private',         5 => 'trash',         6 => 'auto-draft',         7 => 'inherit',         8 => 'request-pending',         9 => 'request-confirmed',         10 => 'request-failed',         11 => 'request-completed',         12 => 'any',       ),       'type' => 'string',     ),     'required' => false,     'handler_validates' => true,   ),   'categories' =>    array (     'description' => 'Limit result set to items with specific terms assigned in the categories taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'categories_exclude' =>    array (     'description' => 'Limit result set to items except those with specific terms assigned in the categories taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'tags' =>    array (     'description' => 'Limit result set to items with specific terms assigned in the tags taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'tags_exclude' =>    array (     'description' => 'Limit result set to items except those with specific terms assigned in the tags taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ), )` — Read by Rest\ListQuery::fromRequest() and Rest\PostsController::serveList(): the post collection.
The term filters take the id list only; the reference also takes a taxonomy query object,
which the engine does not.
- const `PAGES` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'author' =>    array (     'description' => 'Limit result set to posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'author_exclude' =>    array (     'description' => 'Ensure result set excludes posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'menu_order' =>    array (     'description' => 'Limit result set to posts with a specific menu_order value.',     'type' => 'integer',     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'date',     'enum' =>      array (       0 => 'author',       1 => 'date',       2 => 'id',       3 => 'include',       4 => 'modified',       5 => 'parent',       6 => 'relevance',       7 => 'slug',       8 => 'include_slugs',       9 => 'title',       10 => 'menu_order',     ),     'required' => false,   ),   'parent' =>    array (     'description' => 'Limit result set to items with particular parent IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'parent_exclude' =>    array (     'description' => 'Limit result set to all items except those of a particular parent ID.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to posts with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ),   'status' =>    array (     'default' => 'publish',     'description' => 'Limit result set to posts assigned one or more statuses.',     'type' => 'array',     'items' =>      array (       'enum' =>        array (         0 => 'publish',         1 => 'future',         2 => 'draft',         3 => 'pending',         4 => 'private',         5 => 'trash',         6 => 'auto-draft',         7 => 'inherit',         8 => 'request-pending',         9 => 'request-confirmed',         10 => 'request-failed',         11 => 'request-completed',         12 => 'any',       ),       'type' => 'string',     ),     'required' => false,     'handler_validates' => true,   ), )` — Read by Rest\ListQuery::fromRequest() and Rest\PostsController::serveList(): the page collection.
- const `MEDIA` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'after' =>    array (     'description' => 'Limit response to posts published after a given ISO8601 compliant date.',     'type' => 'string',     'format' => 'date-time',     'required' => false,   ),   'author' =>    array (     'description' => 'Limit result set to posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'author_exclude' =>    array (     'description' => 'Ensure result set excludes posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'before' =>    array (     'description' => 'Limit response to posts published before a given ISO8601 compliant date.',     'type' => 'string',     'format' => 'date-time',     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'date',     'enum' =>      array (       0 => 'author',       1 => 'date',       2 => 'id',       3 => 'include',       4 => 'modified',       5 => 'parent',       6 => 'relevance',       7 => 'slug',       8 => 'include_slugs',       9 => 'title',     ),     'required' => false,   ),   'parent' =>    array (     'description' => 'Limit result set to items with particular parent IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'parent_exclude' =>    array (     'description' => 'Limit result set to all items except those of a particular parent ID.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to posts with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ),   'media_type' =>    array (     'default' => NULL,     'description' => 'Limit result set to attachments of a particular media type or media types.',     'type' => 'array',     'items' =>      array (       'type' => 'string',       'enum' =>        array (         0 => 'image',         1 => 'video',         2 => 'text',         3 => 'application',         4 => 'audio',       ),     ),     'required' => false,   ),   'mime_type' =>    array (     'default' => NULL,     'description' => 'Limit result set to attachments of a particular MIME type or MIME types.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ), )` — Read by Rest\ListQuery::fromRequest() and Rest\MediaController::libraryClauses(): the media library.
- const `USERS` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'order' =>    array (     'default' => 'asc',     'description' => 'Order sort attribute ascending or descending.',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'type' => 'string',     'required' => false,   ),   'orderby' =>    array (     'default' => 'name',     'description' => 'Sort collection by user attribute.',     'enum' =>      array (       0 => 'id',       1 => 'include',       2 => 'name',       3 => 'registered_date',       4 => 'slug',       5 => 'include_slugs',       6 => 'email',       7 => 'url',     ),     'type' => 'string',     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to users with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ), )` — Read by Rest\UsersController::list(): the user collection.
- const `TERMS` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'asc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by term attribute.',     'type' => 'string',     'default' => 'name',     'enum' =>      array (       0 => 'id',       1 => 'include',       2 => 'name',       3 => 'slug',       4 => 'include_slugs',       5 => 'term_group',       6 => 'description',       7 => 'count',     ),     'required' => false,   ),   'post' =>    array (     'description' => 'Limit result set to terms assigned to a specific post.',     'type' => 'integer',     'default' => NULL,     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to terms with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ), )` — Read by Rest\TermsController::list(): a term collection (categories, tags, pattern categories).
- const `COMMENTS` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'after' =>    array (     'description' => 'Limit response to comments published after a given ISO8601 compliant date.',     'type' => 'string',     'format' => 'date-time',     'required' => false,   ),   'author' =>    array (     'description' => 'Limit result set to comments assigned to specific user IDs. Requires authorization.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'author_exclude' =>    array (     'description' => 'Ensure result set excludes comments assigned to specific user IDs. Requires authorization.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'author_email' =>    array (     'default' => NULL,     'description' => 'Limit result set to that from a specific author email. Requires authorization.',     'format' => 'email',     'type' => 'string',     'required' => false,   ),   'before' =>    array (     'description' => 'Limit response to comments published before a given ISO8601 compliant date.',     'type' => 'string',     'format' => 'date-time',     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'parent' =>    array (     'default' =>      array (     ),     'description' => 'Limit result set to comments of specific parent IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'parent_exclude' =>    array (     'default' =>      array (     ),     'description' => 'Ensure result set excludes specific parent IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'post' =>    array (     'default' =>      array (     ),     'description' => 'Limit result set to comments assigned to specific post IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'status' =>    array (     'default' => 'approve',     'description' => 'Limit result set to comments assigned a specific status. Requires authorization.',     'type' => 'string',     'required' => false,   ),   'type' =>    array (     'default' => 'comment',     'description' => 'Limit result set to comments assigned a specific type. Requires authorization.',     'type' => 'string',     'required' => false,   ), )` — Read by Rest\CommentsController::list() and filter(): the comment collection.
- const `USER_CREATE` = `array (   'username' =>    array (     'description' => 'Login name for the user.',     'type' => 'string',     'required' => true,   ),   'name' =>    array (     'description' => 'Display name for the user.',     'type' => 'string',     'required' => false,   ),   'first_name' =>    array (     'description' => 'First name for the user.',     'type' => 'string',     'required' => false,   ),   'last_name' =>    array (     'description' => 'Last name for the user.',     'type' => 'string',     'required' => false,   ),   'email' =>    array (     'description' => 'The email address for the user.',     'type' => 'string',     'format' => 'email',     'required' => true,   ),   'url' =>    array (     'description' => 'URL of the user.',     'type' => 'string',     'format' => 'uri',     'required' => false,   ),   'description' =>    array (     'description' => 'Description of the user.',     'type' => 'string',     'required' => false,   ),   'locale' =>    array (     'description' => 'Locale for the user.',     'type' => 'string',     'required' => false,   ),   'nickname' =>    array (     'description' => 'The nickname for the user.',     'type' => 'string',     'required' => false,   ),   'roles' =>    array (     'description' => 'Roles assigned to the user.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ),   'password' =>    array (     'description' => 'Password for the user (never included).',     'type' => 'string',     'required' => true,   ), )` — Read by Rest\UsersController::create(): the body of a user create. The locale takes any
string here; the reference's enum is the site's installed languages, which is not a constant.
- const `USER_EDIT` = `array (   'name' =>    array (     'description' => 'Display name for the user.',     'type' => 'string',     'required' => false,   ),   'first_name' =>    array (     'description' => 'First name for the user.',     'type' => 'string',     'required' => false,   ),   'last_name' =>    array (     'description' => 'Last name for the user.',     'type' => 'string',     'required' => false,   ),   'email' =>    array (     'description' => 'The email address for the user.',     'type' => 'string',     'format' => 'email',     'required' => false,   ),   'url' =>    array (     'description' => 'URL of the user.',     'type' => 'string',     'format' => 'uri',     'required' => false,   ),   'description' =>    array (     'description' => 'Description of the user.',     'type' => 'string',     'required' => false,   ),   'locale' =>    array (     'description' => 'Locale for the user.',     'type' => 'string',     'required' => false,   ),   'nickname' =>    array (     'description' => 'The nickname for the user.',     'type' => 'string',     'required' => false,   ),   'slug' =>    array (     'description' => 'An alphanumeric identifier for the user.',     'type' => 'string',     'required' => false,   ),   'roles' =>    array (     'description' => 'Roles assigned to the user.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ),   'password' =>    array (     'description' => 'Password for the user (never included).',     'type' => 'string',     'required' => false,   ),   'meta' =>    array (     'description' => 'Meta fields.',     'type' => 'object',     'properties' =>      array (       'show_admin_bar_front' =>        array (         'type' => 'string',         'default' => 'true',       ),     ),     'required' => false,   ), )` — Read by Rest\UsersController::update(): the body of a user edit; nothing is required.
- const `USER_DELETE` = `array (   'force' =>    array (     'type' => 'boolean',     'default' => false,     'description' => 'Required to be true, as users do not support trashing.',     'required' => false,   ),   'reassign' =>    array (     'type' => 'integer',     'description' => 'Reassign the deleted user\'s posts and links to this user ID.',     'required' => true,   ), )` — Read by Rest\UsersController::delete(): the reassignment a user delete requires, and the force it insists on.
- const `APPLICATION_PASSWORD` = `array (   'app_id' =>    array (     'description' => 'A UUID provided by the application to uniquely identify it. It is recommended to use an UUID v5 with the URL or DNS namespace.',     'type' => 'string',     'oneOf' =>      array (       0 =>        array (         'type' => 'string',         'format' => 'uuid',       ),       1 =>        array (         'type' => 'string',         'enum' =>          array (           0 => '',         ),       ),     ),     'required' => false,   ),   'name' =>    array (     'description' => 'The name of the application password.',     'type' => 'string',     'minLength' => 1,     'pattern' => '.*\\S.*',     'required' => true,   ), )` — Read by Rest\ApplicationPasswordsController::create(): the body of a new application password.
- const `SEARCH` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',     ),     'default' => 'view',     'required' => false,   ),   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'type' =>    array (     'default' => 'post',     'description' => 'Limit results to items of an object type.',     'type' => 'string',     'enum' =>      array (       0 => 'post',       1 => 'term',       2 => 'post-format',     ),     'required' => false,   ),   'subtype' =>    array (     'default' => 'any',     'description' => 'Limit results to items of one or more object subtypes.',     'type' => 'array',     'items' =>      array (       'enum' =>        array (         0 => 'post',         1 => 'page',         2 => 'category',         3 => 'post_tag',         4 => 'any',       ),       'type' => 'string',     ),     'required' => false,   ), )` — Read by Rest\SearchController::list(): the search collection, whose context has no edit view.

Used by: `Minn\Http\Route`, `Minn\Http\RouteRow`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\CommentsController`, `Minn\Rest\MediaController`, `Minn\Rest\PostsController`, `Minn\Rest\SearchController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

### static `merge(array $sets): array`

The sets merged into one map, as a route's endpoint publishes them.

- `@param list<array<string, array<string, mixed>>> $sets`
- `@return array<string, array<string, mixed>>`


## CertificateName

`final class Minn\Http\CertificateName` · `public/minn/src/Minn/Http/CertificateName.php`

Whether a TLS certificate names a host: a wildcard only as a whole first
label with at least two labels after it, matching exactly one label; an
IP address never matches by name; the subjectAltName DNS entries win over
the common name when present.

### static `valid(string $reference): bool`

Whether a certificate's reference name is one a host can match.

### static `matches(string $host, string $reference): bool`

Whether the host matches the reference name.

### static `certificateMatches(string $host, array $certificate): bool`

Whether a parsed certificate (openssl_x509_parse) names the host.

- `@param array<string, mixed> $certificate`


## CookieText

`final class Minn\Http\CookieText` · `public/minn/src/Minn/Http/CookieText.php`

Set-Cookie text and the matching rules a cookie jar applies: parsing a
header into name, value and attributes; normalizing attributes (expires
and max-age as timestamps, the domain without its leading dot); whether
a cookie belongs to a domain and a path (RFC 6265).

### static `parse(string $header, string $name = ''): array`

A Set-Cookie value split up. With a name given, the first part is
all value; a first part without "=" is a value with an empty name.

- `@return array{name: string, value: string, attributes: array<string, string|true>}`

### static `normalizeAttribute(string $name, mixed $value, int $referenceTime): mixed`

One attribute normalized, or null when it should go: dates become timestamps, the domain loses its leading dot.

### static `hostMatches(?string $cookieDomain, string $domain): bool`

Whether a host-only cookie with this domain attribute (none means any) belongs to the domain: the same text only.

### static `domainMatches(?string $cookieDomain, string $domain): bool`

Whether a cookie with this domain attribute (none means any) belongs to the domain or one of its subdomains (never an IP address).

### static `pathMatches(?string $cookiePath, string $requestPath): bool`

Whether a cookie with this path attribute (none means any) is sent for the request path.

### static `defaultPath(string $requestPath): string`

The path a cookie without one gets: the request path up to its last slash, or "/".


## Destination

`final readonly class Minn\Http\Destination` · `public/minn/src/Minn/Http/Destination.php`

Where Minn\Http lets a request go. Only http and https; when hosts are
named, every hop must start with one of them; and an address outside the
public internet (loopback, private and shared ranges, link-local, cloud
metadata, reserved) is refused unless its host is listed in $private. A
name is resolved here and curl is pinned to the addresses that were
checked, so DNS cannot answer one way to the check and another to the
connection.

Used by: `Minn\Http`, `Minn\Http\Download`, `Minn\Http\Location`

```php
__construct(array $hosts = array ( ), array $private = array ( ), ?Closure $lookup = NULL)
```
- `@param list<string> $hosts URL prefixes every hop must start with; none means any public host`
- `@param list<string> $private host names or addresses allowed to reach a private address`
- `@param (Closure(string): list<string>)|null $lookup a host's addresses; DNS when null`


### `refusal(string $url): ?string`

Why $url may not be requested, or null when nothing about the URL itself refuses it (a name is judged again once resolved).

### `pinned(Minn\Http\Outbound $request): Minn\Http\Outbound|Minn\Http\Exchange`

The request with curl pinned to the addresses its host resolves to,
or the failed exchange when the name does not resolve or resolves
somewhere private it was not allowed.

### static `host(string $url): string`

A URL's host, lower-cased, without the brackets around an IPv6 address.

Internals: `listed()` (private, line 83), `allowed()` (private, line 93), `dns()` (private, line 105)


## Download

`final class Minn\Http\Download` · `public/minn/src/Minn/Http/Download.php`

A file the engine fetches for itself (a package, a language pack), over
Minn\Http with the rules a download needs: https at every hop, one of the
caller's host prefixes when given, no private address, and a body over
the cap fails rather than being truncated. The messages are written for
the person who asked for the download.

Used by: `Minn\Admin\Translations`, `Minn\Ops\Packages`

### static `https(string $url, int $maxBytes, array $hostPrefixes = array ( ), string $userAgent = 'Minn Engine'): string`

The body at $url, following at most five redirects.

- `@param list<string> $hostPrefixes URL prefixes every hop must start with (none: any https host)`


## Envelope

`interface Minn\Http\Envelope` · `public/minn/src/Minn/Http/Envelope.php`

What runs around a matched route once the router knows the route takes
the request: the place a host lets other code refuse, answer or edit it
(the runtime's REST server filters). The router hands over the argument
check's refusal and the policy's, unthrown, because that code may clear
the one and must never run the handler past the other. Without an
envelope the router throws them itself.

Used by: `Minn\Http\Router`, `Minn\Rest\RuntimeEnvelope`

### `around(Minn\Http\Matched $matched, Minn\Http\Request $request, ?Minn\RestError $invalid, ?Minn\RestError $refusal, Closure $invoke): Minn\Http\Response`

The answer to a matched request, refusals included.

- `@param Closure(): Response $invoke the route's handler; it may throw RestError`


## Exchange

`final readonly class Minn\Http\Exchange` · `public/minn/src/Minn/Http/Exchange.php`

What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error.

Used by: `Minn\Http`, `Minn\Http\Destination`, `Minn\Http\Fake`, `Minn\Http\RawResponse`, `Minn\Http\RequestFailed`, `Minn\Http\Transport`

```php
__construct(int $code, array $headers, array $cookies, string $body, ?string $error = NULL, array $head = array ( ), int $errno = 0, string $url = '')
```
- `@param array<string, string|list<string>> $headers names lower-cased`
- `@param list<string> $cookies raw Set-Cookie header values`
- `@param list<string> $head the final response's status line and header lines as they came`

- readonly `int $code`
- readonly `array $headers`
- readonly `array $cookies`
- readonly `string $body`
- readonly `?string $error`
- readonly `array $head`
- readonly `int $errno`
- readonly `string $url`

### static `failure(string $error, int $errno, string $url): self`

An exchange in which no response arrived.

### `failed(): bool`

Whether the transport failed before any status came back.

### `ok(): bool`

A response arrived and it was a 2xx.

### `json(): mixed`

The body decoded as JSON, or null when it is not JSON.

### `header(string $name): ?string`

One header by any spelling of its name; a repeated header's values joined with ", ".

### `cookie(string $name): ?string`

One cookie's value from the Set-Cookie headers, percent-decoded; the last one set wins.

### `throw(): self`

This exchange when it is ok(), otherwise a RequestFailed exception
(a RuntimeException) carrying it, for callers who would rather catch
than check.


## Failure

`final class Minn\Http\Failure` · `public/minn/src/Minn/Http/Failure.php`

What the public sees when the engine cannot answer: a plain page with no
detail, while the detail goes to the log. Installed once per request,
it turns display_errors off (unless the site's own WP_DEBUG_DISPLAY asks
for them) and catches the fatal errors PHP would otherwise print.

- const `FATAL` = `4437`

Used by: `Minn\Engine`, `Minn\Front\Maintenance`, `Minn\Runtime\Plugins`


### static `armRecovery(): void`

Opens the window in which a failure is an extension's fault: while
the plugins and the theme's functions file load and the boot actions
fire, code that fails fails for every visitor, so pausing it heals
the site. A failure after that (a route, a shortcode, a template)
answers to one request's input and is never grounds for a pause;
it is logged and the request ends in the error page.

### static `disarmRecovery(): void`

Closes the window armRecovery() opened.

### static `onFatal(callable $recorder): void`

What to do with a fatal beyond showing the page: the engine records
the extension it came from so the next request loads without it.
Installed once the runtime is up, since it needs the database.

- `@param callable(array{type:int,file:string,line:int,message:string}): void $recorder`

### static `install(): void`

Installs the error handlers; detail is shown only under WP_DEBUG_DISPLAY.

### static `report(Throwable $error): Minn\Http\Response`

Logs the cause; the page says only that something went wrong.

### static `reportJson(Throwable $error): Minn\Http\Response`

The same, for a request that asked for JSON: a client that speaks REST
is answered in REST, never handed the HTML error page.

### static `internal(): Minn\Http\Response`

The 500 page.

### static `databaseUnavailable(): Minn\Http\Response`

The 503 page the reference shows when the database cannot be reached.

### static `maintenance(): Minn\Http\Response`

The 503 page while maintenance mode holds; monitors read the status, the retry hint, and the title.

### static `detailed(string $class, string $message, string $file, int $line): Minn\Http\Response`

The same page with the cause on it, for a site that asked to see
errors. Only ever reached when WP_DEBUG_DISPLAY (or WP_DEBUG) is on:
a site that has not asked never learns this much from a response.

Internals: `discardOutput()` (private, line 82), `note()` (private, line 111), `record()` (private, line 125), `page()` (private, line 177)


## Fake

`final class Minn\Http\Fake` · `public/minn/src/Minn/Http/Fake.php`

Answers requests in place of the network while a test runs, and keeps
what was sent. Made by Minn\Http::fake(); every request through
Minn\Http, wp_remote_*() and the Requests library reaches it.

Used by: `Minn\Http`

```php
__construct(array $answers)
```
- `@param array<string, mixed> $answers URL pattern => answer, see Minn\Http::fake()`


### `answer(Minn\Http\Outbound $request): Minn\Http\Exchange`

The answer for one request, which is recorded as sent.

### `sent(string $pattern = '*'): array`

The requests sent so far, oldest first; with a pattern, only those whose URL matches it.

- `@return list<Outbound>`

### `restore(): void`

Puts the network back.

### static `matches(string $pattern, string $url): bool`

Whether a URL matches a pattern in which * stands for anything; the scheme and the query string may be left off.

Internals: `exchange()` (private, line 61)


## Ipv6

`final class Minn\Http\Ipv6` · `public/minn/src/Minn/Http/Ipv6.php`

IPv6 addresses as text: written out in full (eight groups, or six and an
IPv4 tail), compressed ("::" for the longest run of zero groups, the
first when two tie), and checked. Zone suffixes ("%eth0") do not pass.

### static `expand(string $ip): string`

The address with "::" expanded into zero groups.

### static `compress(string $ip): string`

The address with leading zeros dropped from all-digit groups and the longest zero run as "::".

### static `valid(string $ip): bool`

Whether the text is an IPv6 address (with an optional IPv4 tail).


## IriParts

`final readonly class Minn\Http\IriParts` · `public/minn/src/Minn/Http/IriParts.php`

An IRI (a URL that may carry non-ASCII text) split into its parts and
written back normalized: scheme and host in lower case, a scheme's
default port dropped, dot segments removed, and every character a URL
may not hold percent-encoded. The IRI form keeps non-ASCII characters;
the URI form encodes them too.

- const `PORTS` = `array (   'acap' => 674,   'dict' => 2628,   'file' => NULL,   'http' => 80,   'https' => 443, )`

```php
__construct(?string $scheme, ?string $userinfo, ?string $host, ?int $port, string $path, ?string $query, ?string $fragment)
```

- readonly `?string $scheme`
- readonly `?string $userinfo`
- readonly `?string $host`
- readonly `?int $port`
- readonly `string $path`
- readonly `?string $query`
- readonly `?string $fragment`

### static `parse(string $iri): self`

The parts of an IRI (RFC 3986 appendix B).

### `valid(): bool`

Whether the parts make a well-formed IRI: a valid scheme when there is one, no "//" path without an authority.

### `resolve(self $ref): ?self`

The reference resolved against this base (RFC 3986 section 5.2), or null when the base has no scheme.

### `withHost(?string $host): self`

The same IRI with another host.

### `withPath(string $path): self`

The same IRI with another path.

### `toIri(): string`

The normalized IRI: non-ASCII characters stay as they are.

### `toUri(): string`

The normalized URI: non-ASCII characters percent-encoded too.

Internals: `write()` (private, line 101), `mergePath()` (private, line 117), `removeDots()` (private, line 126), `encode()` (private, line 146)


## Kernel

`final readonly class Minn\Http\Kernel` · `public/minn/src/Minn/Http/Kernel.php`

The edge. Turns a request into a response through the router and turns
a RestError thrown anywhere underneath into the WordPress error shape.

Used by: `Minn\Engine`

```php
__construct(Minn\Http\Router $router)
```


### `handle(Minn\Http\Request $request): ?Minn\Http\Response`

Routes the request; a thrown failure becomes its response, and null means no route matched.

Internals: `harden()` (private, line 35)


## Location

`final class Minn\Http\Location` · `public/minn/src/Minn/Http/Location.php`

Where a redirect leads: a Location header made absolute against the URL that sent it.

Used by: `Minn\Http`

### static `resolve(string $base, string $location): string`

The absolute URL $location names, read against $base.

### static `sameOrigin(string $one, string $two): bool`

Whether two URLs share scheme, host, and port, so credentials may follow from one to the other.


## Matched

`final readonly class Minn\Http\Matched` · `public/minn/src/Minn/Http/Matched.php`

A route the router matched to a request and whose policy it judged: the
route, the object and method that answer it, the pattern's captures, and
every method the same handler answers on that pattern (the reference
lists POST, PUT and PATCH together for one edit handler).

Used by: `Minn\Http\Envelope`, `Minn\Http\Router`, `Minn\Rest\RuntimeEnvelope`

```php
__construct(Minn\Http\Route $route, object $handler, string $method, array $captures, array $methods)
```
- `@param array<string, string> $captures`
- `@param list<string> $methods`

- readonly `Minn\Http\Route $route`
- readonly `object $handler`
- readonly `string $method`
- readonly `array $captures`
- readonly `array $methods`


## Method

`enum Minn\Http\Method` · `public/minn/src/Minn/Http/Method.php`

Cases: `Get` = `'GET'`, `Head` = `'HEAD'`, `Post` = `'POST'`, `Put` = `'PUT'`, `Patch` = `'PATCH'`, `Delete` = `'DELETE'`, `Options` = `'OPTIONS'`, `Any` = `'*'`

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Engine`, `Minn\Front\AssetsController`, `Minn\Front\Canonical`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Redirects`, `Minn\Front\SitemapController`, `Minn\Http\Request`, `Minn\Http\Route`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\AjaxController`

### static `fromName(string $name): self`

The verb for a name, GET when the name is unknown.

### `matches(self $declared): bool`

HEAD is served by GET handlers; the kernel drops the body.

### `canonicalRedirects(): bool`

Canonical redirects (trailing slash, pretty-URL mapping, 404 guessing)
run only for reads; every other method renders the URL as typed.


## Outbound

`final readonly class Minn\Http\Outbound` · `public/minn/src/Minn/Http/Outbound.php`

One outgoing HTTP request, normalised: the transport needs nothing else.

Used by: `Minn\Http`, `Minn\Http\Destination`, `Minn\Http\Fake`, `Minn\Http\Transport`

```php
__construct(string $method, string $url, array $headers = array ( ), ?string $body = NULL, float $timeout = 5.0, int $redirects = 5, bool $verifySsl = true, string $userAgent = '', ?string $caInfo = NULL, bool $blocking = true, ?Closure $prepare = NULL, ?float $connectTimeout = NULL, ?int $maxBytes = NULL)
```
- `@param list<string> $headers "Name: value" lines`
- `@param (Closure(\CurlHandle): void)|null $prepare a last word on the curl handle before it is sent`

- readonly `string $method`
- readonly `string $url`
- readonly `array $headers`
- readonly `?string $body`
- readonly `float $timeout`
- readonly `int $redirects`
- readonly `bool $verifySsl`
- readonly `string $userAgent`
- readonly `?string $caInfo`
- readonly `bool $blocking`
- readonly `?Closure $prepare`
- readonly `?float $connectTimeout`
- readonly `?int $maxBytes`

### `to(string $url, string $method, ?string $body, array $headers): self`

The same request sent somewhere else, as the next hop of a redirect.

- `@param list<string> $headers "Name: value" lines`

### `preparing(Closure $prepare): self`

The same request with a last word on the curl handle, run after any it already has.


## Policy

`final readonly class Minn\Http\Policy` · `public/minn/src/Minn/Http/Policy.php`

What a route requires of its caller, as data on the route: the router
enforces it before the handler runs, so a grep over the attributes is
the authorization surface. The two refusals are the reference's: a
caller who is not signed in gets the sign-in code (401), a signed-in
caller who lacks the capability gets the refusal code (403); how a bad
nonce is answered belongs to whoever judges the policy. A policy that
names a subject has the record looked up first, and answers the
record's 404 before either refusal, which is the reference's order.

Written inline in the attribute with `new`, which is what an attribute
argument allows: `policy: new Policy(Access::Cap, 'upload_files',
refuse: 'rest_cannot_create', message: '...')`.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Engine`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Route`, `Minn\Http\RouteRow`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\AjaxController`

```php
__construct(Minn\Http\Access $access = Minn\Http\Access::Public, ?string $cap = NULL, array $caps = array ( ), ?string $param = NULL, string $signIn = 'rest_not_logged_in', string $signInMessage = 'You are not currently logged in.', string $refuse = 'rest_forbidden', string $message = 'Sorry, you are not allowed to do that.', ?Minn\Http\Policy $edit = NULL, ?Minn\Http\Subject $subject = NULL, ?string $missing = NULL, ?string $missingMessage = NULL, int $signInStatus = 401)
```
- `@param list<string> $caps further capabilities every one of which the caller must hold`

- readonly `Minn\Http\Access $access`
- readonly `?string $cap`
- readonly `array $caps`
- readonly `?string $param`
- readonly `string $signIn`
- readonly `string $signInMessage`
- readonly `string $refuse`
- readonly `string $message`
- readonly `?Minn\Http\Policy $edit`
- readonly `?Minn\Http\Subject $subject`
- readonly `?string $missing`
- readonly `?string $missingMessage`
- readonly `int $signInStatus`

### `isPublic(): bool`

Whether the route is open to anyone with nothing to look up, so the gate need not run at all.

### `missingCode(): string`

The 404 code a missing subject earns.

### `missingText(): string`

The 404 message a missing subject earns.

### `capabilities(): array`

Every capability the policy asks for by name, the one on the object
included, so a listing can show what a route takes.

- `@return list<string>`

### `toArray(): array`

The policy as data, for the route catalogue. @return array<string, mixed>

- `@return array<string, mixed>`

### `describe(): string`

The policy in one line, for the route index and the docs.


## Punycode

`final class Minn\Http\Punycode` · `public/minn/src/Minn/Http/Punycode.php`

Internationalized host names in ASCII: each label that is not ASCII is
written as "xn--" plus its Punycode (RFC 3492) and must stay under 64
bytes. Labels keep their case: there is no Nameprep step, as on the
reference ("Bücher" becomes "xn--Bcher-kva").

- const `PREFIX` = `'xn--'`
- const `MAX_LENGTH` = `64`
- const `BASE` = `36`
- const `TMIN` = `1`
- const `TMAX` = `26`
- const `SKEW` = `38`
- const `DAMP` = `700`
- const `INITIAL_BIAS` = `72`
- const `INITIAL_N` = `128`

### static `host(string $hostname): string`

A whole host name, label by label.

### static `label(string $text): string`

One label in ASCII.

### static `encode(string $input): string`

The Punycode of a UTF-8 string (without the prefix).

Internals: `digits()` (private, line 86), `digit()` (private, line 100), `adapt()` (private, line 106)


## RawResponse

`final class Minn\Http\RawResponse` · `public/minn/src/Minn/Http/RawResponse.php`

An HTTP response as text, the way the Requests library hands it from
transport to parser: the status line and headers, a blank line, the
body. Built from an exchange, split back up, chunked bodies joined and
compressed ones inflated.

### static `fromExchange(Minn\Http\Exchange $exchange): string`

The exchange as raw response text: its status line, header lines, a blank line, the body.

### static `parse(string $raw): array`

The text split into protocol, status, header pairs (folded lines
joined) and body.

- `@return array{protocol: float, status: int, headers: list<array{0: string, 1: string}>, body: string}`

### static `parseHead(string $head): array`

A head alone (the body went to a file): protocol, status, header pairs, and an empty body.

- `@return array{protocol: float, status: int, headers: list<array{0: string, 1: string}>, body: string}`

### static `unchunk(string $body): string`

A chunked body joined; text that is not chunked comes back as it was.

### static `inflate(string $data): string`

A gzip or zlib body inflated; anything else (raw deflate included) comes back as it was.


## Request

`final readonly class Minn\Http\Request` · `public/minn/src/Minn/Http/Request.php`

An immutable picture of the incoming request. Built once from the PHP
globals at the edge; handlers only ever see this object.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Auth\Authenticator`, `Minn\Auth\SignIn`, `Minn\Autoloader`, `Minn\Context`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\AssetsController`, `Minn\Front\Canonical`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Resolver`, `Minn\Front\SitemapController`, `Minn\Http\Envelope`, `Minn\Http\Kernel`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Media\Upload`, `Minn\Ops\Diagnostics`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\BlocksController`, `Minn\Rest\Caller`, `Minn\Rest\CommentsController`, `Minn\Rest\Context`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\ListQuery`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\AjaxController`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostSave`, `Minn\Runtime\Runtime`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`

```php
__construct(Minn\Http\Method $method, string $path, array $query, array $headers, array $cookies, string $body, bool $secure, string $host, array $form = array ( ), array $files = array ( ), string $remoteAddress = '', array $server = array ( ))
```
- `@param array<string, string|array> $query`
- `@param array<string, string> $headers lower-cased names`
- `@param array<string, string> $cookies`
- `@param array<string, mixed> $form decoded form fields, for bodies that are not JSON`
- `@param array<string, array> $files uploaded files, keyed by field`

- readonly `Minn\Http\Method $method`
- readonly `string $path`
- readonly `array $query`
- readonly `array $headers`
- readonly `array $cookies`
- readonly `string $body`
- readonly `bool $secure`
- readonly `string $host`
- readonly `array $form`
- readonly `array $files`
- readonly `string $remoteAddress`
- readonly `array $server` — what the server says about itself: software, protocol, and address; diagnostics only

### static `fromGlobals(): self`

The request PHP received, read once from the superglobals.

### `json(): array`

The JSON body as an array, or the form fields when the body is empty or a form.

### `withPath(string $path): self`

The same request addressed to another path (a REST route carried in ?rest_route=).

### `query(string $key, ?string $default = NULL): ?string`

One query value as a string, or the default when it is absent or not a string.

### `flag(string $key): bool`

A query value read as the reference reads a boolean argument: true, 1, yes, on; anything else is false.

### `has(string $key): bool`

Whether the query carries this key at all, even empty.

### `header(string $name): ?string`

A request header by case-insensitive name, or null.

### `cookie(string $name): ?string`

A cookie's value, or null.

### `segments(): array`

The path split into non-empty segments: "/a/b/" becomes ["a", "b"].

### `queryStringWithout(string ...$keys): string`

The query string with the given keys removed, ready to append to a redirect.


## RequestFailed

`final class Minn\Http\RequestFailed` · `public/minn/src/Minn/Http/RequestFailed.php` · implements `Stringable`, `Throwable`

Thrown by Exchange::throw() when no response arrived or it was not a 2xx; the exchange rides along.

Used by: `Minn\Http\Exchange`

```php
__construct(Minn\Http\Exchange $reply, string $message)
```

- readonly `Minn\Http\Exchange $reply`


## RequestsNames

`final class Minn\Http\RequestsNames` · `public/minn/src/Minn/Http/RequestsNames.php`

The Requests library's PSR-0 class names (Requests_Exception_HTTP_404,
Requests_IDNAEncoder, ...) mapped to the PSR-4 names they stand for.

- const `SEGMENTS` = `array (   'http' => 'Http',   'idnaencoder' => 'IdnaEncoder',   'ipv6' => 'Ipv6',   'iri' => 'Iri',   'ssl' => 'Ssl',   'curl' => 'Curl',   'fsockopen' => 'Fsockopen',   'hooker' => 'HookManager',   'casesensitivedictionary' => 'CaseInsensitiveDictionary',   'caseinsensitivedictionary' => 'CaseInsensitiveDictionary',   'filterediterator' => 'FilteredIterator', )`

### static `modern(string $legacy): ?string`

The namespaced name for a Requests_* name, or null when it is not one.


## Response

`final readonly class Minn\Http\Response` · `public/minn/src/Minn/Http/Response.php`

What a handler returns. Nothing is written to the client until the
kernel calls send(), so a response can be inspected, wrapped, or
replaced on the way out. Work that belongs after the client has its
answer (a cron run a page found due) rides along as afterSend closures.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Auth\AuthCookies`, `Minn\Auth\SignIn`, `Minn\Engine`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\Maintenance`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Envelope`, `Minn\Http\Failure`, `Minn\Http\Kernel`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\AjaxController`

```php
__construct(int $status = 200, array $headers = array ( ), string $body = '', array $cookies = array ( ), array $afterSend = array ( ))
```
- `@param array<string, string> $headers`
- `@param list<array{0: string, 1: string, 2: array}> $cookies name, value, setcookie options`
- `@param list<Closure(): void> $afterSend run once the response has been handed to the client`

- readonly `int $status`
- readonly `array $headers`
- readonly `string $body`
- readonly `array $cookies`
- readonly `array $afterSend`

### static `html(string $body, int $status = 200): self`

An HTML response.

### static `json(mixed $payload, int $status = 200): self`

A JSON response with the payload encoded.

### static `redirect(string $location, int $status = 301): self`

A redirect to a location.

### `withHeader(string $name, string $value): self`

The same response with one header set.

### `withoutHeader(string $name): self`

The same response without one header.

### `withCookie(string $name, string $value, array $options): self`

The same response with a cookie to set.

- `@param array<string, mixed> $options setcookie options: expires, path, secure, httponly, samesite`

### `withoutBody(): self`

The same response with an empty body, the HEAD answer.

### `afterSend(Closure $work): self`

The same response with work to run once the client has been answered. @param Closure(): void $work

- `@param Closure(): void $work`

### `sendHead(): void`

Writes the status, the headers, and the cookies now, and nothing else:
for an answer that code outside the engine may finish on its own (an
ajax handler that ends the request), which must find them already said.

### `send(): void`

Writes the status, the headers, the cookies, and the body, ends the
request for the client, then runs the after-send work in the same
process (the server that can close the connection first does).


## Route

`final readonly class Minn\Http\Route` · `public/minn/src/Minn/Http/Route.php`

Declares a handler method as a route. The policy lives here, as
metadata the router enforces before the handler runs, so the
authorization surface of the engine is a grep away; a route with no
policy is one the ratchet in the style suite counts down. The parameter
sets it reads live here too, judged before the policy is (the reference
refuses a bad argument before it refuses a caller), so the REST index can
tell a client what a route takes as well as what it requires of them.

Patterns: "/wp/v2/posts/{id}" captures one segment, "{id:\d+}" constrains
it, and "/{path*}" captures the rest of the path (slashes included).

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Matched`, `Minn\Http\RouteRow`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\BlocksController`, `Minn\Rest\Catalogue`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\AjaxController`

```php
__construct(Minn\Http\Method $method, string $pattern, ?Minn\Http\Policy $policy = NULL, array $args = array ( ), bool $index = true, array $body = array ( ), ?string $name = NULL)
```
- `@param list<array<string, array<string, mixed>>> $args the parameter sets this route reads, from Args`
- `@param list<array<string, array<string, mixed>>> $body the parameter sets this route reads from the JSON body, from Args or the shape that owns them`

- readonly `Minn\Http\Method $method`
- readonly `string $pattern`
- readonly `?Minn\Http\Policy $policy`
- readonly `array $args`
- readonly `bool $index`
- readonly `array $body`
- readonly `?string $name`

### `arguments(): array`

The parameters this route accepts, as the REST index publishes them.

- `@return array<string, array<string, mixed>>`

### `bodyArguments(): array`

The parameters this route reads from the JSON body, validated before the caller is judged.

- `@return array<string, array<string, mixed>>`

### `regex(): string`

The pattern as a regular expression with named captures.


## RouteMiss

`final class Minn\Http\RouteMiss` · `public/minn/src/Minn/Http/RouteMiss.php` · implements `Stringable`, `Throwable`

A handler declining a request its pattern matched: the router swallows
it and goes on to the next route, so a catch-all pattern can leave a
path it does not own to whatever registers after it (a plugin's route
under the same namespace, say) instead of answering "no route" itself.

Used by: `Minn\Http\Router`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\IndexController`, `Minn\Rest\PolicyGate`


## RouteRow

`final readonly class Minn\Http\RouteRow` · `public/minn/src/Minn/Http/RouteRow.php`

One line of the route table: what a route is, who it is for, and what it
takes, read from the attribute and the handler method alone, so the table
can be built from the classes without a request, a database, or a site.
This is the row the REST index, the docs, and contracts/api/routes.json
are written from.

Used by: `Minn\Http\Router`, `Minn\Rest\Catalogue`, `Minn\Rest\RuntimeEnvelope`

```php
__construct(string $method, string $pattern, string $name, string $handler, string $summary, ?Minn\Http\Policy $policy, array $args, array $body, bool $index)
```
- `@param array<string, array<string, mixed>> $args the query parameters the route reads`
- `@param array<string, array<string, mixed>> $body the JSON body parameters it reads`

- readonly `string $method`
- readonly `string $pattern`
- readonly `string $name`
- readonly `string $handler`
- readonly `string $summary`
- readonly `?Minn\Http\Policy $policy`
- readonly `array $args`
- readonly `array $body`
- readonly `bool $index`

### static `of(Minn\Http\Route $route, ReflectionMethod $method): self`

The row for one attribute on one handler method; the name defaults to the handler.

### `toArray(): array`

The row as data, for the JSON catalogue. @return array<string, mixed>

- `@return array<string, mixed>`

Internals: `summary()` (private, line 69)


## Router

`final class Minn\Http\Router` · `public/minn/src/Minn/Http/Router.php`

Matches a request to a #[Route] on one of the registered handler
objects, has the check refuse a bad argument, has the gate judge the
route's policy, and invokes the method with the request plus the named
pattern captures. That order is the reference's: an invalid parameter is
answered before the caller is looked at. The gate is the one
thing a router cannot be built without: a policy nobody judges is a
route nobody may call.

Used by: `Minn\Engine`, `Minn\Http\Kernel`, `Minn\Rest\Api`, `Minn\Rest\Embed`, `Minn\Rest\EngineRoutes`, `Minn\Rest\IndexController`

```php
__construct(Closure $gate, ?Closure $check = NULL, ?Minn\Http\Envelope $envelope = NULL)
```
- `@param Closure(Policy $policy, Request $request, array<string, string> $captures): void $gate throws when the policy refuses the caller`
- `@param Closure(Route $route, Request $request): void|null $check throws when a declared argument is missing or invalid; judged before the gate`


### `register(object ...$handlers): self`

Registers every #[Route] method of the given handlers; returns the router for chaining.

### `routes(): array`

The routes that list in an index: pattern => methods. @return array<string, list<string>>

- `@return array<string, list<string>>`

### `table(): array`

Every registered route as a row: what it is, who it is for, what it
takes. The same rows Rest\Catalogue reads from the classes alone.

- `@return list<RouteRow>`

### `allowed(Minn\Http\Request $request): array`

The methods the caller may use on this path, for the Allow header:
every route matching the path, its policy judged for the caller
without dispatching. A route that states no policy counts for GET
only, until it states one. Empty when nothing matched or nothing
is allowed, and the header is then left out, as the reference does.

- `@return list<string> in the reference's order`

### `dispatch(Minn\Http\Request $request): ?Minn\Http\Response`

Null when nothing matched, so the caller can fall through.

Internals: `admits()` (private, line 109), `arguments()` (private, line 152), `answer()` (private, line 169), `enveloped()` (private, line 187), `methodsOf()` (private, line 215)


## Subject

`enum Minn\Http\Subject` · `public/minn/src/Minn/Http/Subject.php`

The record a route capture names, so a policy can have it looked up
before the caller is judged: the reference answers "no such post" ahead
of "you may not", and the 404 it sends is the record's own. A kind that
reads its post type or taxonomy from the {base} capture says so.

Cases: `Post`, `PostParent`, `Attachment`, `Block`, `Navigation`, `MenuItem`, `GlobalStyles`, `GlobalStylesParent`, `Term`, `Menu`, `User`, `Comment`

Used by: `Minn\Http\Policy`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\Subjects`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`

### `missingCode(): string`

The reference's error code for a record that does not exist.

### `missingMessage(): string`

The reference's message for a record that does not exist.


## Transport

`final class Minn\Http\Transport` · `public/minn/src/Minn/Http/Transport.php`

The engine's outgoing HTTP transport over curl: it sends exactly what an
Outbound says and nothing else decides. Redirects are followed by curl, so
the header lines of every hop arrive in order; only the last response's
block is kept, the way plugin code expects to read it. Code calling out
goes through Minn\Http, which owns the rules about where a request may go.

Used by: `Minn\Http`

### static `send(Minn\Http\Outbound $request): Minn\Http\Exchange`

Performs one outgoing request over curl and returns the exchange, a transport error included.

Internals: `options()` (private, line 55), `limit()` (private, line 85), `lastHead()` (private, line 100), `lastBlock()` (private, line 118)


## TrustedProxies

`final readonly class Minn\Http\TrustedProxies` · `public/minn/src/Minn/Http/TrustedProxies.php`

Which addresses in front of the engine may speak for the client.

By default none do: a forwarded header is a request header, and a request
header is the client's word. Behind a proxy or CDN that terminates TLS,
`REMOTE_ADDR` is then the proxy's, which makes every visitor look like one
address (the sign-in throttle would lock them all out together) and every
request look insecure (the auth cookie would lose its Secure flag).

A site says who to believe in `wp-config.php`:

define('MINN_TRUSTED_PROXIES', '10.0.0.0/8, 2400:cb00::/32');
define('MINN_TRUSTED_PROXIES', '*');   // any: only when nothing but the proxy can reach PHP

The client is then the right-most address in `X-Forwarded-For` that is not
itself trusted, which is what a proxy chain appends, so a client that sends
its own `X-Forwarded-For` cannot put an address the engine believes to the
right of the proxy's.

Used by: `Minn\Http\Request`


### static `none(): self`

Nobody in front of the engine speaks for the client.

### static `configured(): self`

What the site declared in wp-config.php, or nobody.

### `trusts(string $address): bool`

Whether the engine believes what this address says about the client.

### `clientAddress(string $remoteAddress, string $forwardedFor): string`

The client's address, given who connected and what was forwarded: the
right-most forwarded address that is not itself a trusted proxy.

### `forwardedSecure(string $remoteAddress, string $forwardedProto): bool`

Whether a forwarded scheme may be believed, and says https.

Internals: `bare()` (private, line 95), `covers()` (private, line 106)

