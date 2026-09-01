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
use Minn\Admin\Diagnostics;
use Minn\Admin\HiddenIntegrations;
use Minn\Admin\Logs;
use Minn\Admin\Notifications;
use Minn\Admin\Packages;
use Minn\Admin\Translations;
use Minn\Admin\Updates;
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

    public function db(): Db
    {
        return $this->db;
    }

    public function request(): Request
    {
        return $this->request;
    }

    /** The site root, no trailing slash. */
    public function root(): string
    {
        return $this->root;
    }

    public function contentDir(): string
    {
        return $this->root . '/wp-content';
    }

    // ---- content

    public function users(): Users
    {
        return $this->share(Users::class, fn () => new Users($this->db));
    }

    public function posts(): Posts
    {
        return $this->share(Posts::class, fn () => new Posts($this->db));
    }

    public function terms(): Terms
    {
        return $this->share(Terms::class, fn () => new Terms($this->db));
    }

    public function comments(): Comments
    {
        return $this->share(Comments::class, fn () => new Comments($this->db));
    }

    public function site(): Site
    {
        return $this->share(Site::class, fn () => new Site($this->db));
    }

    public function writer(): PostWriter
    {
        return $this->share(PostWriter::class, fn () => new PostWriter($this->db, $this->posts(), $this->site()));
    }

    public function revisions(): Revisions
    {
        return $this->share(Revisions::class, fn () => new Revisions($this->db, $this->writer(), $this->site()));
    }

    public function menus(): Menus
    {
        return $this->share(Menus::class, fn () => new Menus($this->db, $this->posts(), $this->terms(), $this->permalinks(), $this->writer(), $this->site()));
    }

    public function inventory(): Inventory
    {
        return $this->share(Inventory::class, fn () => new Inventory($this->contentDir(), $this->site()));
    }

    // ---- links and identity

    public function permalinks(): Permalinks
    {
        return $this->share(Permalinks::class, fn () => Permalinks::fromDb($this->db));
    }

    public function url(): RestUrl
    {
        return $this->share(RestUrl::class, fn () => new RestUrl($this->permalinks()));
    }

    public function capabilities(): Capabilities
    {
        return $this->share(Capabilities::class, fn () => Capabilities::fromDb($this->db));
    }

    public function caller(): Caller
    {
        return $this->share(Caller::class, fn () => new Caller($this->request, Authenticator::fromDb($this->db), $this->capabilities()));
    }

    public function sessions(): Sessions
    {
        return $this->share(Sessions::class, fn () => new Sessions($this->users()));
    }

    public function applicationPasswords(): ApplicationPasswords
    {
        return $this->share(ApplicationPasswords::class, fn () => new ApplicationPasswords($this->users()));
    }

    // ---- extensions, types, media

    public function loader(): Loader
    {
        return $this->share(Loader::class, fn () => new Loader($this->contentDir(), $this->site()));
    }

    public function types(): Types
    {
        return $this->share(Types::class, fn () => new Types($this->url(), $this->loader()->declaredTypes()));
    }

    public function taxonomies(): Taxonomies
    {
        return $this->share(Taxonomies::class, fn () => new Taxonomies($this->url()));
    }

    public function uploads(): Uploads
    {
        return $this->share(Uploads::class, fn () => new Uploads($this->site(), $this->permalinks(), $this->contentDir() . '/uploads'));
    }

    public function mediaWriter(): Writer
    {
        return $this->share(Writer::class, fn () => new Writer($this->writer(), $this->site(), $this->uploads(), new Images($this->site())));
    }

    public function schema(): Schema
    {
        return $this->share(Schema::class, fn () => new Schema(
            static fn (string $e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL),
            static fn (int|float $n): string => number_format((float) $n),
            static fn (string $f, mixed $v): mixed => $v,
        ));
    }

    // ---- the admin app

    public function app(): App
    {
        return $this->share(App::class, fn () => new App($this->engineDir . '/admin'));
    }

    public function packages(): Packages
    {
        return $this->share(Packages::class, fn () => new Packages($this->site(), $this->contentDir()));
    }

    public function logs(): Logs
    {
        return $this->share(Logs::class, fn () => new Logs($this->root));
    }

    public function updates(): Updates
    {
        return $this->share(Updates::class, fn () => new Updates($this->site(), $this->inventory(), $this->packages(), $this->contentDir(), $this->permalinks()->url('/'), Engine::WP_VERSION));
    }

    public function translations(): Translations
    {
        return $this->share(Translations::class, fn () => new Translations($this->users(), $this->site(), $this->app(), $this->contentDir()));
    }

    public function appearance(): Appearance
    {
        return $this->share(Appearance::class, fn () => new Appearance($this->users()));
    }

    public function hiddenIntegrations(): HiddenIntegrations
    {
        return $this->share(HiddenIntegrations::class, fn () => new HiddenIntegrations($this->users(), $this->capabilities()));
    }

    public function activityFeed(): ActivityFeed
    {
        return $this->share(ActivityFeed::class, fn () => new ActivityFeed($this->db, $this->users(), $this->capabilities()));
    }

    public function dashboard(): Dashboard
    {
        return $this->share(Dashboard::class, fn () => new Dashboard($this->db, $this->site(), $this->users(), $this->capabilities(), new ActivityChart($this->db, $this->site()), $this->activityFeed(), $this->contentDir() . '/uploads'));
    }

    public function notifications(): Notifications
    {
        return $this->share(Notifications::class, fn () => new Notifications($this->db, $this->site(), $this->users(), $this->capabilities(), $this->activityFeed(), $this->updates()));
    }

    public function diagnostics(): Diagnostics
    {
        return $this->share(Diagnostics::class, fn () => new Diagnostics($this->db, $this->site(), $this->permalinks(), $this->inventory(), $this->loader(), $this->logs(), MINN_ENGINE_VERSION, $this->root));
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

    public function templateWriter(): ?TemplateWriter
    {
        $templates = $this->templates();
        return $templates === null ? null : $this->share(TemplateWriter::class, fn () => new TemplateWriter($this->db, $this->writer(), $this->terms(), $this->site(), $templates));
    }

    // ---- the REST shapes

    public function postObject(): PostObject
    {
        return $this->share(PostObject::class, fn () => new PostObject($this->db, $this->posts(), $this->users(), $this->permalinks(), $this->url(), $this->caller()));
    }

    public function termObject(): TermObject
    {
        return $this->share(TermObject::class, fn () => new TermObject($this->db, $this->permalinks(), $this->url(), $this->caller()));
    }

    public function userObject(): UserObject
    {
        return $this->share(UserObject::class, fn () => new UserObject($this->db, $this->users(), $this->permalinks(), $this->url(), $this->caller()));
    }

    public function mediaObject(): MediaObject
    {
        return $this->share(MediaObject::class, fn () => new MediaObject($this->posts(), $this->uploads(), $this->permalinks(), $this->url(), $this->caller()));
    }

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
