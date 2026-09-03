<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use LogicException;
use Minn\Admin\ActivityChart;
use Minn\Admin\ActivityFeed;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Admin\Dashboard;
use Minn\Ops\Diagnostics;
use Minn\Admin\HiddenIntegrations;
use Minn\Ops\InstalledSoftware;
use Minn\Ops\Logs;
use Minn\Admin\Notifications;
use Minn\Ops\Packages;
use Minn\Admin\Translations;
use Minn\Ops\Updates;
use Minn\Auth\ApplicationPasswords;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Auth\Sessions;
use Minn\Content\Comments;
use Minn\Content\Inventory;
use Minn\Content\Menus;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Revisions;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Content\Users;
use Minn\Db;
use Minn\Engine;
use Minn\Extension\Loader;
use Minn\Front\Permalinks;
use Minn\Http\Request;
use Minn\Media\Images;
use Minn\Media\Uploads;
use Minn\Media\Writer;
use Minn\Runtime\Runtime;
use Minn\Theme\TemplateIndex;
use Minn\Theme\TemplateWriter;
use Minn\Theme\ThemeStyles;
use Minn\Theme\UserStyles;
use Minn\Theme\Theme;

/**
 * The objects one REST request shares, each made once, on first use, from
 * the database door and the request. Every getter is typed and names its
 * dependencies in plain constructor calls: there is no autowiring, and
 * get() knows only the names listed here, so a wrong one fails at the
 * first call rather than deep in a handler.
 */
final class Services
{
    /** @var array<string, string> class name => getter, for get() */
    private const NAMED = [
        Users::class => 'users',
        Posts::class => 'posts',
        Terms::class => 'terms',
        Comments::class => 'comments',
        Site::class => 'site',
        PostWriter::class => 'writer',
        Permalinks::class => 'permalinks',
        RestUrl::class => 'url',
        Capabilities::class => 'capabilities',
        Caller::class => 'caller',
        Subjects::class => 'subjects',
        Loader::class => 'loader',
        Types::class => 'types',
        Taxonomies::class => 'taxonomies',
        Uploads::class => 'uploads',
        Inventory::class => 'inventory',
        Packages::class => 'packages',
        App::class => 'app',
        Logs::class => 'logs',
        Updates::class => 'updates',
        Menus::class => 'menus',
        Revisions::class => 'revisions',
        Sessions::class => 'sessions',
        ApplicationPasswords::class => 'applicationPasswords',
        Translations::class => 'translations',
        Appearance::class => 'appearance',
        HiddenIntegrations::class => 'hiddenIntegrations',
        ActivityFeed::class => 'activityFeed',
        Dashboard::class => 'dashboard',
        Notifications::class => 'notifications',
        Diagnostics::class => 'diagnostics',
        Writer::class => 'mediaWriter',
        Schema::class => 'schema',
        PostObject::class => 'postObject',
        TermObject::class => 'termObject',
        UserObject::class => 'userObject',
        MediaObject::class => 'mediaObject',
        CommentObject::class => 'commentObject',
        UserStyles::class => 'userStyles',
        ThemeStyles::class => 'themeStyles',
    ];

    /** @var array<string, object> */
    private array $made = [];

    private function __construct(
        private readonly Db $db,
        private readonly Request $request,
        private readonly string $root,
        private readonly string $engineDir,
    ) {
    }

    /** The services for one request, none made yet. */
    public static function forRequest(Db $db, Request $request): self
    {
        return new self($db, $request, rtrim(ABSPATH, '/'), MINN_ENGINE_DIR);
    }

    /**
     * The service registered under a class name. Only the names in NAMED
     * answer; anything else is a programming error and says so at once.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function get(string $class): object
    {
        $getter = self::NAMED[$class] ?? throw new LogicException("No service is registered for {$class}; add it to Services::NAMED.");
        /** @var T */
        return $this->{$getter}();
    }

    /** The record lookups the policy gate asks before it judges a caller. */
    public function subjects(): Subjects
    {
        return $this->share(Subjects::class, fn () => new Subjects($this->db()));
    }

    /** The database door. */
    public function db(): Db
    {
        return $this->db;
    }

    /** The request being answered. */
    public function request(): Request
    {
        return $this->request;
    }

    /** The site root, no trailing slash. */
    public function root(): string
    {
        return $this->root;
    }

    /** wp-content under the site root. */
    public function contentDir(): string
    {
        return $this->root . '/wp-content';
    }

    // ---- content

    /** The users repository. */
    public function users(): Users
    {
        return $this->share(Users::class, fn () => new Users($this->db));
    }

    /** The posts repository. */
    public function posts(): Posts
    {
        return $this->share(Posts::class, fn () => new Posts($this->db));
    }

    /** The terms repository. */
    public function terms(): Terms
    {
        return $this->share(Terms::class, fn () => new Terms($this->db));
    }

    /** The comments repository. */
    public function comments(): Comments
    {
        return $this->share(Comments::class, fn () => new Comments($this->db));
    }

    /** The site's options. */
    public function site(): Site
    {
        return $this->share(Site::class, fn () => new Site($this->db));
    }

    /** The post writer. */
    public function writer(): PostWriter
    {
        return $this->share(PostWriter::class, fn () => new PostWriter($this->db, $this->posts(), $this->site()));
    }

    /** Revisions and autosaves. */
    public function revisions(): Revisions
    {
        return $this->share(Revisions::class, fn () => new Revisions($this->db, $this->writer(), $this->site()));
    }

    /** The site editor's saved global styles. */
    public function userStyles(): UserStyles
    {
        return $this->share(UserStyles::class, fn () => new UserStyles($this->db, $this->posts(), $this->writer(), $this->terms(), $this->site()));
    }

    /** The active theme's global styles and variations. */
    public function themeStyles(): ThemeStyles
    {
        return $this->share(ThemeStyles::class, fn () => ThemeStyles::forSite($this->site(), $this->permalinks(), $this->contentDir() . '/themes'));
    }

    /** Classic menus and their items. */
    public function menus(): Menus
    {
        return $this->share(Menus::class, fn () => new Menus($this->db, $this->posts(), $this->terms(), $this->permalinks(), $this->writer(), $this->site()));
    }

    /** What wp-content holds: plugins, themes, drop-ins. */
    public function inventory(): Inventory
    {
        return $this->share(Inventory::class, fn () => new Inventory($this->contentDir(), $this->site()));
    }

    // ---- links and identity

    /** Link building from the permalink structure. */
    public function permalinks(): Permalinks
    {
        return $this->share(Permalinks::class, fn () => Permalinks::fromDb($this->db));
    }

    /** REST URL building. */
    public function url(): RestUrl
    {
        return $this->share(RestUrl::class, fn () => new RestUrl($this->permalinks()));
    }

    /** The capability engine. */
    public function capabilities(): Capabilities
    {
        return $this->share(Capabilities::class, fn () => Capabilities::fromDb($this->db));
    }

    /** Who is making this call. */
    public function caller(): Caller
    {
        return $this->share(Caller::class, fn () => new Caller($this->request, Authenticator::fromDb($this->db), $this->capabilities()));
    }

    /** The session store. */
    public function sessions(): Sessions
    {
        return $this->share(Sessions::class, fn () => new Sessions($this->users()));
    }

    /** Application passwords for Basic auth. */
    public function applicationPasswords(): ApplicationPasswords
    {
        return $this->share(ApplicationPasswords::class, fn () => new ApplicationPasswords($this->users()));
    }

    // ---- extensions, types, media

    /** The extension loader. */
    public function loader(): Loader
    {
        return $this->share(Loader::class, fn () => new Loader($this->contentDir(), $this->site()));
    }

    /** The post types the REST surface knows. */
    public function types(): Types
    {
        return $this->share(Types::class, fn () => new Types($this->url(), $this->loader()->declaredTypes()));
    }

    /** The taxonomies the REST surface knows. */
    public function taxonomies(): Taxonomies
    {
        return $this->share(Taxonomies::class, fn () => new Taxonomies($this->url()));
    }

    /** The uploads directory. */
    public function uploads(): Uploads
    {
        return $this->share(Uploads::class, fn () => new Uploads($this->site(), $this->permalinks(), $this->contentDir() . '/uploads'));
    }

    /** Stores an upload as an attachment. */
    public function mediaWriter(): Writer
    {
        return $this->share(Writer::class, fn () => new Writer($this->writer(), $this->posts(), $this->site(), $this->uploads(), new Images($this->site())));
    }

    /** The JSON Schema validator, with the reference's filters passed in. */
    public function schema(): Schema
    {
        return $this->share(Schema::class, fn () => new Schema(
            static fn (string $e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL),
            static fn (int|float $n): string => number_format((float) $n),
            static fn (string $f, mixed $v): mixed => $v,
        ));
    }

    // ---- the admin app

    /** The Minn Admin bundle on disk. */
    public function app(): App
    {
        return $this->share(App::class, fn () => new App($this->engineDir . '/admin'));
    }

    /** Installing and removing themes and extensions. */
    public function packages(): Packages
    {
        return $this->share(Packages::class, fn () => new Packages($this->site(), $this->contentDir()));
    }

    /** The debug log reader. */
    public function logs(): Logs
    {
        return $this->share(Logs::class, fn () => new Logs($this->root));
    }

    /** Update checks and offers. */
    public function updates(): Updates
    {
        return $this->share(Updates::class, fn () => new Updates($this->site(), $this->inventory(), $this->packages(), $this->contentDir(), $this->permalinks()->url('/'), Engine::WP_VERSION));
    }

    /** Locales and the app's catalogs. */
    public function translations(): Translations
    {
        return $this->share(Translations::class, fn () => new Translations($this->users(), $this->site(), $this->app(), $this->contentDir()));
    }

    /** The app's per-user appearance. */
    public function appearance(): Appearance
    {
        return $this->share(Appearance::class, fn () => new Appearance($this->users()));
    }

    /** The app's per-user hidden views. */
    public function hiddenIntegrations(): HiddenIntegrations
    {
        return $this->share(HiddenIntegrations::class, fn () => new HiddenIntegrations($this->users(), $this->capabilities()));
    }

    /** What happened lately. */
    public function activityFeed(): ActivityFeed
    {
        return $this->share(ActivityFeed::class, fn () => new ActivityFeed($this->db, $this->users(), $this->capabilities()));
    }

    /** The overview payload. */
    public function dashboard(): Dashboard
    {
        return $this->share(Dashboard::class, fn () => new Dashboard($this->db, $this->site(), $this->users(), $this->capabilities(), new ActivityChart($this->db, $this->site()), $this->activityFeed(), $this->contentDir() . '/uploads'));
    }

    /** The bell feed. */
    public function notifications(): Notifications
    {
        return $this->share(Notifications::class, fn () => new Notifications($this->db, $this->site(), $this->users(), $this->capabilities(), $this->activityFeed(), $this->updates()));
    }

    /** The System view's payload. */
    public function diagnostics(): Diagnostics
    {
        return $this->share(Diagnostics::class, fn () => new Diagnostics($this->db, $this->site(), $this->permalinks(), new InstalledSoftware($this->site(), $this->inventory(), $this->loader(), $this->root), $this->logs(), MINN_ENGINE_VERSION, $this->root));
    }

    // ---- the block theme, when there is one

    /** The active block theme's templates; null under a classic theme, where the routes answer as the reference does when it never registered them. */
    public function templates(): ?TemplateIndex
    {
        if (!array_key_exists('templates', $this->made)) {
            $theme = Theme::active($this->site(), $this->permalinks(), $this->contentDir() . '/themes');
            $this->made['templates'] = $theme === null ? null : new TemplateIndex($this->db, $theme, $this->site(), Runtime::booted() ? Runtime::blockTemplates() : null);
        }
        return $this->made['templates'];
    }

    /** Writes block templates; null under a classic theme. */
    public function templateWriter(): ?TemplateWriter
    {
        $templates = $this->templates();
        return $templates === null ? null : $this->share(TemplateWriter::class, fn () => new TemplateWriter($this->db, $this->writer(), $this->terms(), $this->site(), $templates));
    }

    // ---- the REST shapes

    /** The wp/v2 post shape. */
    public function postObject(): PostObject
    {
        return $this->share(PostObject::class, fn () => new PostObject($this->db, $this->posts(), $this->users(), $this->permalinks(), $this->url(), $this->caller()));
    }

    /** The wp/v2 term shape. */
    public function termObject(): TermObject
    {
        return $this->share(TermObject::class, fn () => new TermObject($this->db, $this->permalinks(), $this->url(), $this->caller()));
    }

    /** The wp/v2 user shape. */
    public function userObject(): UserObject
    {
        return $this->share(UserObject::class, fn () => new UserObject($this->db, $this->users(), $this->permalinks(), $this->url(), $this->caller()));
    }

    /** The wp/v2 media shape. */
    public function mediaObject(): MediaObject
    {
        return $this->share(MediaObject::class, fn () => new MediaObject($this->posts(), $this->uploads(), $this->permalinks(), $this->url(), $this->caller()));
    }

    /** The wp/v2 comment shape. */
    public function commentObject(): CommentObject
    {
        return $this->share(CommentObject::class, fn () => new CommentObject($this->comments(), $this->posts(), $this->permalinks(), $this->url(), $this->caller()));
    }

    /**
     * @template T of object
     * @param class-string<T> $name
     * @param Closure(): T $make
     * @return T
     */
    private function share(string $name, Closure $make): object
    {
        /** @var T */
        return $this->made[$name] ??= $make();
    }
}
