<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Closure;
use Minn\Content\Posts;
use Minn\Content\Terms;
use Minn\Db;
use Minn\Http\Request;
use Minn\Runtime\Runtime;
use Minn\Content\Reader;
use Minn\Auth\Nonce;

/**
 * Turns a public URL into a Resolution the way the reference's request
 * parse does: the root by its query string, any other path by the first
 * of the site's rewrite rules it fits (Front\RuleTable), whose query vars
 * name what it is (Front\RuleRoutes). What is left here are the
 * reference's canonical answers around that (redirect_canonical,
 * wp_old_slug_redirect):
 *
 * - Unpaged content and archives without a trailing slash redirect to the
 *   slashed form, as typed (case kept), as do an embed's and an endpoint's
 *   addresses; paged views, search, and 404s do not.
 * - Query-var forms redirect to the pretty form when the target is
 *   public: a single's (?p=, ?page_id=, ?name=, ?pagename=; SingleQueries)
 *   and an archive's (?m=, ?year=, ?author=, one taxonomy's term;
 *   QueryMoves), unless a search is asked for.
 * - A single asked for under a category it is not in (where the
 *   structure names one), or with an old-style page number, moves to its
 *   own address; a trackback address goes to the post; the front page
 *   answers only at the root.
 * - A name the site no longer has: the post that used to have it, else the
 *   closest published page or post whose name starts with it, pages before
 *   posts, newest first. Archives never guess.
 * - Non-public posts are 404 to anonymous readers and served to a reader
 *   who can edit them.
 */
final readonly class Resolver
{
    /** @param Closure(PostRecord): bool $canReadUnpublished */
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Terms $terms,
        private Permalinks $permalinks,
        private Closure $canReadUnpublished,
        private int $perPage,
    ) {
    }

    /** A resolver over the site's own settings. */
    public static function fromDb(Db $db, Closure $canReadUnpublished): self
    {
        return new self(
            $db,
            new Posts($db),
            new Terms($db),
            Permalinks::fromDb($db),
            $canReadUnpublished,
            max(1, (int) ($db->option('posts_per_page') ?? 10)),
        );
    }

    /** The link builder. */
    public function permalinks(): Permalinks
    {
        return $this->permalinks;
    }

    /** Posts per page. */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * $canonical mirrors the reference's redirect_canonical rule: only GET
     * and HEAD get trailing-slash, pretty-URL, and 404-guess redirects;
     * every other method renders what the query alone finds, as typed.
     */
    public function resolve(Request $request, ?Redirects $mode = null): Resolution
    {
        $resolution = $this->resolvePath($request, $mode ?? Redirects::forRequest($request));
        // A preview link names an autosave: preview_id plus the reader's own nonce for that post.
        if ($resolution->kind === Kind::Single || $resolution->kind === Kind::Page) {
            $reader = Reader::current();
            $previewId = (int) $request->query('preview_id', '0');
            if ($previewId > 0 && $previewId === $resolution->id() && $reader->loggedIn() && $reader->canEdit($previewId)
                && Nonce::verify((string) $request->query('preview_nonce', ''), $reader->userId, $reader->sessionToken, 'post_preview_' . $previewId)) {
                return $resolution->asPreview();
            }
        }
        return $resolution;
    }

    private function resolvePath(Request $request, Redirects $redirects): Resolution
    {
        $canonical = $redirects->follows();
        $path = $request->path;
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
            if ($path !== '/' && $canonical) {
                return Resolution::redirect($this->permalinks->url(rtrim($path, '/') . '/') . $request->queryStringWithout());
            }
        }
        if (str_contains($path, '//')) {
            // Doubled slashes collapse to the canonical path.
            if ($canonical) {
                return Resolution::redirect($this->permalinks->url((string) preg_replace('#/{2,}#', '/', $path)) . $request->queryStringWithout());
            }
            $path = (string) preg_replace('#/{2,}#', '/', $path);
        }
        if ($path === '/') {
            return $this->resolveQueryVars($request, $redirects);
        }
        $vars = RuleTable::vars($path);
        if ($vars === null) {
            return Resolution::notFound()->withVars(['error' => '404']);
        }
        $routes = new RuleRoutes($this->posts, $this->archives(), $this->permalinks->frontPageId, $this->permalinks->postsPageId, $this->readable(...));
        return $this->canonical($routes->resolve($vars)->withVars($vars), $request, $path, $redirects);
    }

    /**
     * The canonical answer for what a path's rule found: the found thing
     * as typed, or where it should be asked for instead.
     */
    private function canonical(Resolution $resolution, Request $request, string $path, Redirects $redirects): Resolution
    {
        $vars = $resolution->vars;
        $follows = $redirects->follows();
        $slashed = str_ends_with($path, '/');
        $slash = fn (): Resolution => Resolution::redirect($this->permalinks->url($path . '/') . $request->queryStringWithout());
        // With attachment pages off, every address of an attachment, its feed and embed among them, goes to the file.
        if (AttachmentAddresses::names($resolution) && $this->db->option('wp_attachment_pages_enabled') !== '1') {
            return $this->attachments()->answer($resolution, $request, $redirects);
        }
        // A feed alias moves to the feed's own address (/rss2/ and /feed/rss2/ to /feed/, /hello-world/atom/ to
        // /hello-world/feed/atom/, /hello-world/?feed=rss2 to /hello-world/feed/: the query's kind wins over the path's);
        // a search's feed is served where it was asked for, its trailing slash aside.
        $asked = is_string($request->query['feed'] ?? null) && in_array(preg_replace('/^comments-/', '', $request->query['feed']), ['feed', 'rss2', 'rss', 'atom', 'rdf'], true) ? $request->query['feed'] : '';
        $pathFeed = (string) ($vars['feed'] ?? '');
        if ($pathFeed !== '' || ($asked !== '' && $resolution->kind !== Kind::NotFound)) {
            $feed = Permalinks::feedPath((string) preg_replace('#(?:/feed)?/(?:feed|rdf|rss|rss2|atom)/?$#', '/', $path), $asked !== '' ? $asked : $pathFeed);
            if ($follows && $resolution->kind !== Kind::Search && ($feed !== $path || $asked !== '')) {
                return Resolution::redirect($this->permalinks->url($feed) . $request->queryStringWithout('feed'));
            }
            if ($pathFeed !== '') {
                return $follows && !$slashed ? $slash() : $resolution;
            }
        }
        if ($resolution->kind === Kind::NotFound) {
            return $this->missing($vars, $redirects) ?? $resolution;
        }
        $record = $resolution->record instanceof PostRecord ? $resolution->record : null;
        if ($record !== null && isset($vars['tb'])) {
            // A trackback address goes to the post, even as typed.
            return Resolution::redirect($this->permalinks->forPost($record), 302);
        }
        if ($record !== null && ($vars['cpage'] ?? '') !== '') {
            // Comment pages are the single's when the site pages its comments; otherwise the single's own address.
            return $follows && !(bool) $this->db->option('page_comments') ? Resolution::redirect($this->permalinks->forPost($record)) : $resolution;
        }
        if ($record !== null && (($vars['page'] ?? '') !== '' || ($resolution->front && ($vars['pagename'] ?? '') !== '' && !isset($vars['embed'])))) {
            // An old-style page number (the reference no longer honours one) and the front page at its own path: the plain address.
            return $follows ? Resolution::redirect($this->permalinks->forPost($record)) : ($resolution->front ? $resolution : Resolution::notFound());
        }
        $paging = $follows && $resolution->kind !== Kind::Search ? $this->pagedMove($resolution, $request, $path) : null;
        if ($paging !== null) {
            return $paging;
        }
        if (isset($vars['embed']) || $this->endpointIn($vars)) {
            return $follows && !$slashed ? $slash() : $resolution;
        }
        if ($follows && $record !== null && !isset($vars['paged']) && $this->elsewhere()->misplaced($record, $vars)) {
            return Resolution::redirect($this->permalinks->forPost($record) . $request->queryStringWithout());
        }
        if (AttachmentAddresses::names($resolution)) {
            return $this->attachments()->answer($resolution, $request, $redirects);
        }
        $slashable = in_array($resolution->kind, [Kind::Single, Kind::Page, Kind::Category, Kind::Tag, Kind::Author, Kind::Date], true);
        return $follows && $slashable && !isset($vars['paged']) && !$slashed ? $slash() : $resolution;
    }

    /**
     * A listing page that is not one: a post (not a page) asked for with a
     * page of listing (/hello-world/page/2/, ?paged=2) moves to the post, the
     * rest of the query along but that; the first page of any listing or page
     * drops its /page/1/. Null when neither applies.
     */
    private function pagedMove(Resolution $resolution, Request $request, string $path): ?Resolution
    {
        $paged = $resolution->vars['paged'] ?? null;
        $record = $resolution->record instanceof PostRecord ? $resolution->record : null;
        if ($record !== null && $resolution->kind === Kind::Single && ($paged !== null || $request->has('paged'))) {
            return Resolution::redirect($this->permalinks->forPost($record) . $request->queryStringWithout('paged'));
        }
        if ($paged !== null && (int) $paged === 1) {
            return Resolution::redirect($this->permalinks->url((string) preg_replace('#/page/1/?$#', '/', $path)) . $request->queryStringWithout());
        }
        return null;
    }

    /**
     * A name the site does not have: where the post that used to have it
     * lives now (as typed too, where wp_old_slug_redirect makes the move),
     * else, on a single's rule, the closest match. Null when there is none.
     *
     * @param array<string, string> $vars
     */
    private function missing(array $vars, Redirects $redirects): ?Resolution
    {
        $name = (string) ($vars['name'] ?? '');
        $former = $name !== '' && in_array($vars['post_type'] ?? 'post', ['post'], true)
            ? $this->elsewhere()->formerSlug($name, max(1, (int) ($vars['paged'] ?? 1)))
            : null;
        if ($former !== null) {
            if (($vars['page'] ?? '') !== '') {
                return $redirects->follows() ? $this->elsewhere()->formerSlug($name) : null;
            }
            return isset($vars['embed']) ? $this->elsewhere()->formerSuffix($former, 'embed') : $former;
        }
        // A page path guesses only when no page stands there (a page past its last listing page is just a 404).
        $pagename = self::segments((string) ($vars['pagename'] ?? ''));
        $pagename = $pagename !== [] && $this->posts->byTypedPath($pagename, ['page', 'attachment']) === null ? $pagename : [];
        $slug = $name !== '' ? $name : (string) ($vars['attachment'] ?? end($pagename));
        $guess = $redirects->follows() && $slug !== '' ? $this->posts->guess($slug, self::viewableTypes()) : null;
        if ($guess === null) {
            return null;
        }
        // A guessed destination keeps the page number the reader typed.
        return Resolution::redirect($this->permalinks->forPost($guess) . (($vars['page'] ?? '') !== '' ? $vars['page'] . '/' : ''));
    }

    /**
     * The post types a guess looks among: those a reader can view that
     * searches include.
     *
     * @return list<string>
     */
    private static function viewableTypes(): array
    {
        $types = [];
        foreach (Runtime::registry()->postTypes() as $name => $type) {
            $viewable = !empty($type['publicly_queryable']) || (!empty($type['_builtin']) && !empty($type['public']));
            if ($viewable && empty($type['exclude_from_search'])) {
                $types[] = (string) $name;
            }
        }
        return $types;
    }

    /** Whether the vars carry a rewrite endpoint's var. @param array<string, string> $vars */
    private function endpointIn(array $vars): bool
    {
        foreach ((array) ($GLOBALS['wp_rewrite']->endpoints ?? []) as $endpoint) {
            $var = (string) (array_values((array) $endpoint)[2] ?? '');
            if ($var !== '' && array_key_exists($var, $vars)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $path), static fn (string $s) => $s !== ''));
    }

    /**
     * The root by its query string, in the grammar a path's rule vars are
     * read in (RuleRoutes): a single's vars first (SingleQueries makes
     * their moves), then the archive forms that move (QueryMoves), then
     * what the vars name, an id form read as the name it stands for.
     */
    private function resolveQueryVars(Request $request, Redirects $redirects): Resolution
    {
        $single = (new SingleQueries($this->posts, $this->permalinks, $this->attachments(), $this->elsewhere(), $this->readable(...)))->find($request, $redirects);
        if ($single !== null) {
            return $single;
        }
        $move = $redirects->follows() && $this->permalinks->isPretty() ? (new QueryMoves($this->db, $this->terms, $this->permalinks))->for($request) : null;
        if ($move !== null) {
            return $move;
        }
        $vars = $this->rootVars($request);
        if ($vars === null) {
            return Resolution::notFound();
        }
        return (new RuleRoutes($this->posts, $this->archives(), $this->permalinks->frontPageId, $this->permalinks->postsPageId, $this->readable(...)))->resolve($vars);
    }

    /**
     * The root's query vars as RuleRoutes reads them: the query string's
     * (error is not the query string's to set), ?cat= as the category's
     * slug, ?author= as the author's name, ?m= as its year, month and day.
     * Null when an id names nothing the site has.
     *
     * @return array<string, string>|null
     */
    private function rootVars(Request $request): ?array
    {
        $vars = array_map(strval(...), array_filter($request->query, is_scalar(...)));
        unset($vars['error']);
        $cat = (int) ($vars['cat'] ?? 0);
        if ($cat > 0 && !isset($vars['category_name'])) {
            $term = $this->terms->find('category', $cat);
            if ($term === null) {
                return null;
            }
            $vars['category_name'] = $term->slug;
        }
        $author = (int) ($vars['author'] ?? 0);
        if ($author > 0 && !isset($vars['author_name'])) {
            $name = $this->db->value("SELECT user_nicename FROM {$this->db->table('users')} WHERE ID = ? LIMIT 1", [$author]);
            if ($name === null) {
                return null;
            }
            $vars['author_name'] = (string) $name;
        }
        if (!isset($vars['year']) && preg_match('/^(\d{4})(\d{2})?(\d{2})?$/', $vars['m'] ?? '', $m)) {
            $vars += array_filter(['year' => $m[1], 'monthnum' => $m[2] ?? '', 'day' => $m[3] ?? ''], static fn (string $part) => $part !== '');
        }
        return $vars;
    }

    /** The archives a request may stand for. */
    private function archives(): ArchiveAddresses
    {
        return new ArchiveAddresses($this->db, $this->posts, $this->terms, $this->permalinks, $this->readable(...));
    }

    /** The addresses an attachment's page answers to. */
    private function attachments(): AttachmentAddresses
    {
        return new AttachmentAddresses($this->db, $this->posts, $this->permalinks, $this->readable(...));
    }

    /** The addresses a single answers to besides its own. */
    private function elsewhere(): SingleAddresses
    {
        return new SingleAddresses($this->db, $this->posts, $this->permalinks);
    }

    private function readable(PostRecord $post): bool
    {
        if ($post->isPublished()) {
            return true;
        }
        if (in_array($post->status, ['trash', 'auto-draft', 'inherit'], true)) {
            return false;
        }
        if ($post->status === 'private') {
            $reader = Reader::current();
            if ($post->isPage() ? $reader->readsPrivatePages : $reader->readsPrivatePosts) {
                return true;
            }
        }
        return ($this->canReadUnpublished)($post);
    }
}
