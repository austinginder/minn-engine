<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Closure;
use Minn\Content\Posts;
use Minn\Content\PostFilter;
use Minn\Content\Terms;
use Minn\Db;
use Minn\Http\Request;
use Minn\Runtime\Runtime;
use Minn\Content\Reader;
use Minn\Auth\Nonce;

/**
 * Turns a public URL into a Resolution, following the reference's observed
 * rules:
 *
 * - Unpaged content and archives without a trailing slash redirect to the
 *   slashed form, as typed (case kept); paged views, search, and 404s do not.
 * - Query-var forms (?p=, ?page_id=, ?cat=, ?tag=, ?author=, ?m=, ?name=,
 *   ?pagename=) redirect to the pretty form when the target is public.
 * - Archive-shaped paths (category, tag, author, search, a four-digit year)
 *   are strict: a mismatch is a 404 with no guessing.
 * - Plain-segment paths try the page hierarchy, then the post structure,
 *   then redirect to the closest published page or post whose name starts
 *   with the last segment, pages before posts, newest first.
 * - Empty term and date archives are 404; an author archive is 200 for any
 *   name, even one that belongs to nobody.
 * - Non-public posts are 404 to anonymous readers and served to a reader
 *   who can edit them.
 */
final readonly class Resolver
{
    /** @param Closure(array $post): bool $canReadUnpublished */
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Terms $terms,
        private Permalinks $permalinks,
        private Closure $canReadUnpublished,
        private int $perPage,
    ) {
    }

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

    public function permalinks(): Permalinks
    {
        return $this->permalinks;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * $canonical mirrors the reference's redirect_canonical rule: only GET
     * and HEAD get trailing-slash, pretty-URL, and 404-guess redirects;
     * every other method renders what the query alone finds, as typed.
     */
    public function resolve(Request $request, bool $canonical = true): Resolution
    {
        // A plugin's own rewrite rules: 'top' rules outrank everything the
        // engine would resolve, 'bottom' rules catch what it could not.
        $ruleVars = PluginRules::match($request->path, top: true);
        if ($ruleVars !== null) {
            return $this->fromRuleVars($ruleVars);
        }
        $resolution = $this->resolvePath($request, $canonical);
        if ($resolution->kind === Kind::NotFound) {
            $ruleVars = PluginRules::match($request->path, top: false);
            if ($ruleVars !== null) {
                return $this->fromRuleVars($ruleVars);
            }
        }
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

    /**
     * The resolution a matched plugin rewrite rule stands for: its content
     * vars when it names content, the home query otherwise (the reference's
     * shape for a rule that only sets a plugin's own flags). The vars stay
     * on the request state so get_query_var() answers them and the plugin's
     * template_include callback can take the page over.
     *
     * @param array<string, string> $vars
     */
    private function fromRuleVars(array $vars): Resolution
    {
        if (Runtime::booted()) {
            Runtime::current()->set(PluginRules::STATE, $vars);
        }
        $paged = max(1, (int) ($vars['paged'] ?? 1));
        $id = (int) ($vars['p'] ?? $vars['page_id'] ?? 0);
        if ($id > 0) {
            $post = $this->posts->find($id);
            if ($post !== null && $this->readable($post)) {
                return Resolution::single($post, $paged);
            }
            return Resolution::notFound();
        }
        $pagename = (string) ($vars['pagename'] ?? '');
        if ($pagename !== '') {
            $single = $this->resolveSingle(array_values(array_filter(explode('/', $pagename), static fn (string $s) => $s !== '')), $paged);
            return $single ?? Resolution::notFound();
        }
        if (($vars['s'] ?? '') !== '') {
            return Resolution::search((string) $vars['s'], $paged);
        }
        return Resolution::home($paged);
    }

    private function resolvePath(Request $request, bool $canonical = true): Resolution
    {
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
            return $this->resolveQueryVars($request, $canonical);
        }
        if (!$this->permalinks->isPretty()) {
            return Resolution::notFound();
        }
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s) => $s !== ''));
        $paged = 1;
        $count = count($segments);
        if ($count >= 2 && $segments[$count - 2] === 'page' && ctype_digit($segments[$count - 1])) {
            $paged = max(1, (int) $segments[$count - 1]);
            array_splice($segments, -2);
        }
        if ($segments !== [] && in_array(end($segments), ['embed', 'trackback'], true)) {
            $suffix = array_pop($segments);
            $single = $this->resolveSingle($segments);
            if ($suffix === 'trackback' && $single !== null) {
                return Resolution::redirect($this->permalinks->forPost($single->record), 302);
            }
            if ($single !== null && !str_ends_with($path, '/') && $canonical) {
                return Resolution::redirect($this->permalinks->url($path . '/') . $request->queryStringWithout());
            }
            return $single ?? Resolution::notFound();
        }

        $resolution = $this->pluginRoute($segments, $paged) ?? match (true) {
            $segments === [] => $this->home($paged),
            $segments[0] === 'category' => $this->termArchive('category', array_slice($segments, 1), $paged),
            $segments[0] === 'tag' => $this->termArchive('post_tag', array_slice($segments, 1), $paged),
            $segments[0] === 'author' => count($segments) === 2 ? $this->authorArchive($segments[1], $paged) : Resolution::notFound(),
            $segments[0] === 'search' => count($segments) === 2 ? Resolution::search(rawurldecode($segments[1]), $paged) : Resolution::notFound(),
            $segments[0] === 'feed' => Resolution::notFound(),
            preg_match('/^\d{4}$/', $segments[0]) === 1 => $this->dateArchive($segments, $paged),
            default => $this->resolveContent($segments, $paged, $canonical),
        };
        // The reference adds the trailing slash only for unpaged content
        // and archives; paged views, search, and 404s answer as typed.
        $slashable = in_array($resolution->kind, [Kind::Single, Kind::Page, Kind::Category, Kind::Tag, Kind::Author, Kind::Date], true);
        if ($canonical && $slashable && $paged === 1 && !str_ends_with($path, '/')) {
            return Resolution::redirect($this->permalinks->url($path . '/') . $request->queryStringWithout());
        }
        return $resolution;
    }

    private function resolveQueryVars(Request $request, bool $canonical = true): Resolution
    {
        $pretty = $this->permalinks->isPretty();
        foreach (['p', 'page_id'] as $key) {
            if (!$request->has($key)) {
                continue;
            }
            $post = $this->posts->find((int) $request->query($key, '0'));
            if ($post === null || !in_array($post['post_type'], ['post', 'page'], true)) {
                return Resolution::notFound();
            }
            if (!$this->readable($post)) {
                return Resolution::notFound();
            }
            if (!$canonical) {
                // Without the canonical pass each var is strict about type:
                // ?p= finds only posts, ?page_id= only pages.
                return $post['post_type'] === ($key === 'p' ? 'post' : 'page')
                    ? Resolution::single($post)
                    : Resolution::notFound();
            }
            $link = $this->permalinks->forPost($post);
            if ($pretty && !str_contains($link, '?')) {
                return Resolution::redirect($link);
            }
            return Resolution::single($post);
        }
        if ($request->has('name')) {
            $post = $this->posts->findByName((string) $request->query('name'), ['post']);
            if ($post === null) {
                return $this->formerSlug((string) $request->query('name')) ?? Resolution::notFound();
            }
            return $this->singleOrRedirect($post, 1, forceRedirect: $pretty && $canonical);
        }
        if ($request->has('pagename')) {
            $segments = array_values(array_filter(explode('/', (string) $request->query('pagename')), static fn (string $s) => $s !== ''));
            $page = $this->posts->pageByPath($segments);
            if ($page !== null) {
                if ((int) $page['ID'] === $this->permalinks->frontPageId) {
                    return $canonical ? Resolution::redirect($this->permalinks->url('/')) : Resolution::frontPage($page, 1);
                }
                return Resolution::single($page);
            }
            $bySlug = $segments === [] || !$canonical ? null : $this->posts->findByName(end($segments), ['page']);
            return $bySlug === null ? Resolution::notFound() : Resolution::redirect($this->permalinks->forPost($bySlug));
        }
        if ($request->has('cat')) {
            $term = $this->terms->find('category', (int) $request->query('cat', '0'));
            if ($term !== null && !$canonical) {
                return $this->termResolution('category', $term, 1);
            }
            return $term === null || !$pretty ? Resolution::notFound() : Resolution::redirect($this->permalinks->forTerm($term));
        }
        if ($request->has('tag')) {
            $term = $this->terms->findBySlug('post_tag', (string) $request->query('tag'));
            if ($term !== null && !$canonical) {
                return $this->termResolution('post_tag', $term, 1);
            }
            return $term === null || !$pretty ? Resolution::notFound() : Resolution::redirect($this->permalinks->forTerm($term));
        }
        if ($request->has('author')) {
            $user = $this->db->row("SELECT ID, user_nicename, display_name FROM {$this->db->table('users')} WHERE ID = ? LIMIT 1", [(int) $request->query('author', '0')]);
            if ($user !== null && !$canonical) {
                return $this->authorArchive((string) $user['user_nicename'], 1);
            }
            return $user === null || !$pretty ? Resolution::notFound() : Resolution::redirect($this->permalinks->forAuthor($user));
        }
        if ($request->has('m') && preg_match('/^(\d{4})(\d{2})?(\d{2})?$/', (string) $request->query('m'), $m)) {
            return $this->dateRedirect((int) $m[1], isset($m[2]) ? (int) $m[2] : null, isset($m[3]) ? (int) $m[3] : null, $canonical);
        }
        if ($request->has('year')) {
            $month = $request->has('monthnum') ? (int) $request->query('monthnum') : null;
            $day = $request->has('day') ? (int) $request->query('day') : null;
            return $this->dateRedirect((int) $request->query('year', '0'), $month, $day, $canonical);
        }
        if ($request->has('s')) {
            return Resolution::search((string) $request->query('s'), max(1, (int) $request->query('paged', '1')));
        }
        return $this->home(max(1, (int) $request->query('paged', '1')));
    }

    private function dateRedirect(int $year, ?int $month, ?int $day, bool $canonical = true): Resolution
    {
        if (!$canonical) {
            $range = self::dateRange($year, $month, $day);
            if ($range === null) {
                return Resolution::notFound();
            }
            $total = $this->posts->count(PostFilter::all()->between($range[0], $range[1]));
            return $total === 0 ? Resolution::notFound() : Resolution::date($year, $month, $day, 1);
        }
        if (!$this->permalinks->isPretty()) {
            return Resolution::notFound();
        }
        return Resolution::redirect($this->permalinks->forDate($year, $month, $day));
    }

    private function home(int $paged): Resolution
    {
        if ($this->permalinks->frontPageId > 0) {
            $page = $this->posts->find($this->permalinks->frontPageId);
            if ($page !== null && $page['post_type'] === 'page' && $this->readable($page)) {
                return Resolution::frontPage($page, $paged);
            }
        }
        $total = (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'",
        );
        return $paged > 1 && $paged > $this->pages($total) ? Resolution::notFound() : Resolution::home($paged);
    }

    /**
     * A plugin's post type or taxonomy behind the path: its archive at the
     * has_archive slug (which wins over a page of the same name), a single
     * under the type's rewrite slug, or a term under the taxonomy's. Only
     * once the runtime has loaded the plugins that register them.
     *
     * @param list<string> $segments
     */
    private function pluginRoute(array $segments, int $paged): ?Resolution
    {
        $registry = Runtime::booted() ? Runtime::registry() : null;
        if ($registry === null || $segments === []) {
            return null;
        }
        foreach ($registry->postTypes() as $name => $type) {
            if (!empty($type['_builtin']) || empty($type['publicly_queryable']) || empty($type['rewrite'])) {
                continue;
            }
            $archive = $type['has_archive'] ?? false;
            $archiveSlug = is_string($archive) && $archive !== '' ? $archive : ($archive === true ? $this->permalinks->typeSlug((string) $name) : null);
            if ($archiveSlug !== null && $segments === self::segmentsOf($archiveSlug)) {
                $total = $this->posts->count(PostFilter::types((string) $name));
                return $paged > 1 && $paged > $this->pages($total) ? Resolution::notFound() : Resolution::postTypeArchive(['name' => (string) $name] + $type, $paged);
            }
            $prefix = self::segmentsOf($this->permalinks->typeSlug((string) $name));
            if (count($segments) === count($prefix) + 1 && array_slice($segments, 0, count($prefix)) === $prefix) {
                $post = $this->posts->findByName(end($segments), [(string) $name], publishedOnly: false);
                return $post !== null && $this->readable($post) ? Resolution::single($post, $paged) : Resolution::notFound();
            }
        }
        foreach ($registry->taxonomies() as $name => $taxonomy) {
            if (!empty($taxonomy['_builtin']) || empty($taxonomy['publicly_queryable']) || empty($taxonomy['rewrite'])) {
                continue;
            }
            $prefix = self::segmentsOf($this->permalinks->taxonomySlug((string) $name) ?? (string) $name);
            if (count($segments) > count($prefix) && array_slice($segments, 0, count($prefix)) === $prefix) {
                return $this->taxonomyArchive((string) $name, (array) ($taxonomy['object_type'] ?? []), array_slice($segments, count($prefix)), $paged);
            }
        }
        return null;
    }

    /** @return list<string> */
    private static function segmentsOf(string $slug): array
    {
        return array_values(array_filter(explode('/', $slug), static fn (string $s) => $s !== ''));
    }

    /**
     * @param list<string> $types the post types the taxonomy attaches to
     * @param list<string> $slugs
     */
    private function taxonomyArchive(string $taxonomy, array $types, array $slugs, int $paged): Resolution
    {
        $term = $this->terms->findBySlug($taxonomy, end($slugs));
        if ($term === null || strcasecmp($this->terms->pathOf($term), implode('/', $slugs)) !== 0) {
            return Resolution::notFound();
        }
        $total = $this->posts->count(PostFilter::types(...$types)->inTerm((int) $term['term_taxonomy_id']));
        if ($total === 0 || $paged > $this->pages($total)) {
            return Resolution::notFound();
        }
        return Resolution::taxonomy($term, $paged);
    }

    /** @param list<string> $slugs */
    private function termArchive(string $taxonomy, array $slugs, int $paged): Resolution
    {
        if ($slugs === []) {
            return Resolution::notFound();
        }
        $term = $this->terms->findBySlug($taxonomy, end($slugs));
        if ($term === null || strcasecmp($this->terms->pathOf($term), implode('/', $slugs)) !== 0) {
            return Resolution::notFound();
        }
        return $this->termResolution($taxonomy, $term, $paged);
    }

    /** The archive a found term stands for, 404 when it is empty or overpaged. */
    private function termResolution(string $taxonomy, array $term, int $paged): Resolution
    {
        $total = $this->posts->count(PostFilter::all()->inTerm((int) $term['term_taxonomy_id']));
        if ($total === 0 || $paged > $this->pages($total)) {
            return Resolution::notFound();
        }
        return Resolution::term($taxonomy, $term, $paged);
    }

    private function authorArchive(string $name, int $paged): Resolution
    {
        $user = $this->db->row(
            "SELECT ID, user_nicename, display_name FROM {$this->db->table('users')} WHERE user_nicename = ? LIMIT 1",
            [$name],
        );
        if ($user !== null) {
            $total = $this->posts->count(PostFilter::all()->byAuthor((int) $user['ID']));
            if ($paged > 1 && $paged > $this->pages($total)) {
                return Resolution::notFound();
            }
        }
        return Resolution::author($name, $user, $paged);
    }

    /** @param list<string> $segments */
    private function dateArchive(array $segments, int $paged): Resolution
    {
        if (count($segments) > 3) {
            return Resolution::notFound();
        }
        foreach (array_slice($segments, 1) as $part) {
            if (!preg_match('/^\d{1,2}$/', $part)) {
                return Resolution::notFound();
            }
        }
        $year = (int) $segments[0];
        $month = isset($segments[1]) ? (int) $segments[1] : null;
        $day = isset($segments[2]) ? (int) $segments[2] : null;
        $range = self::dateRange($year, $month, $day);
        if ($range === null) {
            return Resolution::notFound();
        }
        $total = $this->posts->count(PostFilter::all()->between($range[0], $range[1]));
        if ($total === 0 || $paged > $this->pages($total)) {
            return Resolution::notFound();
        }
        return Resolution::date($year, $month, $day, $paged);
    }

    /** @return array{0: string, 1: string}|null */
    public static function dateRange(int $year, ?int $month, ?int $day): ?array
    {
        if ($month !== null && ($month < 1 || $month > 12)) {
            return null;
        }
        if ($day !== null && ($month === null || !checkdate($month, $day, $year))) {
            return null;
        }
        $from = sprintf('%04d-%02d-%02d 00:00:00', $year, $month ?? 1, $day ?? 1);
        $to = match (true) {
            $day !== null => date('Y-m-d 00:00:00', strtotime("{$year}-{$month}-{$day} +1 day")),
            $month !== null => date('Y-m-d 00:00:00', strtotime(sprintf('%04d-%02d-01 +1 month', $year, $month))),
            default => sprintf('%04d-01-01 00:00:00', $year + 1),
        };
        return [$from, $to];
    }

    /** @param list<string> $segments */
    private function resolveContent(array $segments, int $paged, bool $canonical = true): Resolution
    {
        $single = $this->resolveSingle($segments, $paged, $canonical);
        if ($single !== null) {
            return $single;
        }
        // A trailing number on a single is an old-style page number the
        // reference no longer honours; it redirects to the plain permalink.
        $number = '';
        if (count($segments) >= 2 && ctype_digit(end($segments))) {
            $parent = $this->resolveSingle(array_slice($segments, 0, -1), 1, $canonical);
            if ($parent !== null) {
                return $canonical ? Resolution::redirect($this->permalinks->forPost($parent->record)) : Resolution::notFound();
            }
            // A guessed destination keeps the number the reader typed.
            $number = array_pop($segments) . '/';
        }
        // A structure that opens with the category lets a bare category
        // path stand as the archive.
        if ($number === '' && str_starts_with($this->permalinks->structure, '/%category%')) {
            $archive = $this->termArchive('category', $segments, $paged);
            if ($archive->kind !== Kind::NotFound) {
                return $archive;
            }
        }
        // Under a category-first structure an unmatched bare path reads as
        // a category query on the reference, which never guesses; other
        // structures read it as a name and guess from it.
        if (str_starts_with($this->permalinks->structure, '/%category%')) {
            return Resolution::notFound();
        }
        $guess = $canonical ? $this->posts->guess(end($segments)) : null;
        return $guess === null ? Resolution::notFound() : Resolution::redirect($this->permalinks->forPost($guess) . $number);
    }

    /** @param list<string> $segments */
    private function resolveSingle(array $segments, int $paged = 1, bool $canonical = true): ?Resolution
    {
        if ($segments === []) {
            return null;
        }
        $page = $this->posts->pageByPath($segments, publishedOnly: false);
        if ($page !== null && $this->readable($page)) {
            // The static front page answers only at the site root.
            if ((int) $page['ID'] === $this->permalinks->frontPageId) {
                return $canonical ? Resolution::redirect($this->permalinks->url('/')) : Resolution::frontPage($page, $paged);
            }
            // The posts page paginates like the home listing: page/N serves
            // the blog's page N, and past the last page it is a 404.
            if ((int) $page['ID'] === $this->permalinks->postsPageId) {
                $total = (int) $this->db->value(
                    "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'",
                );
                return $paged > 1 && $paged > $this->pages($total) ? Resolution::notFound() : Resolution::postsPage($page, $paged);
            }
            return Resolution::single($page, $paged);
        }
        $regex = $this->permalinks->structureRegex();
        if ($regex !== null && preg_match($regex, implode('/', $segments), $m)) {
            $post = isset($m['post_id'])
                ? $this->posts->find((int) $m['post_id'])
                : $this->posts->findByName($m['postname'], ['post'], publishedOnly: false);
            if ($post !== null && $post['post_type'] === 'post' && $this->readable($post)) {
                return Resolution::single($post, $paged);
            }
            if ($post === null && isset($m['postname'])) {
                return $this->formerSlug($m['postname'], $paged);
            }
        }
        return null;
    }

    /**
     * A slug a post used to have redirects to where the post lives now,
     * keeping the page number and dropping the query string; the lookup
     * ignores status because the reference does (unreadable posts land on
     * their `?p=` form and answer 404 there).
     */
    private function formerSlug(string $slug, int $paged = 1): ?Resolution
    {
        $former = $this->posts->byOldSlug($slug, ['post']);
        if ($former === null) {
            return null;
        }
        $link = $this->permalinks->forPost($former);
        if ($paged > 1 && !str_contains($link, '?')) {
            $link = rtrim($link, '/') . "/page/{$paged}/";
        }
        return Resolution::redirect($link);
    }

    private function singleOrRedirect(array|PostRecord $post, int $paged, bool $forceRedirect): Resolution
    {
        if (!$this->readable($post)) {
            return Resolution::notFound();
        }
        $link = $this->permalinks->forPost($post);
        return $forceRedirect && !str_contains($link, '?') ? Resolution::redirect($link) : Resolution::single($post, $paged);
    }

    private function readable(array|PostRecord $post): bool
    {
        if ($post['post_status'] === 'publish') {
            return true;
        }
        if (in_array($post['post_status'], ['trash', 'auto-draft', 'inherit'], true)) {
            return false;
        }
        if ($post['post_status'] === 'private') {
            $reader = Reader::current();
            if ($post['post_type'] === 'page' ? $reader->readsPrivatePages : $reader->readsPrivatePosts) {
                return true;
            }
        }
        return ($this->canReadUnpublished)($post);
    }

    private function pages(int $total): int
    {
        return max(1, (int) ceil($total / $this->perPage));
    }
}
