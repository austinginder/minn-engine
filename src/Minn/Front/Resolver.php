<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\Posts;
use Minn\Content\Terms;
use Minn\Db;
use Minn\Http\Request;

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

    public function resolve(Request $request): Resolution
    {
        $path = $request->path;
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
            if ($path !== '/') {
                return Resolution::redirect($this->permalinks->url(rtrim($path, '/') . '/') . $request->queryStringWithout());
            }
        }
        if ($path === '/') {
            return $this->resolveQueryVars($request);
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
            if ($single !== null && !str_ends_with($path, '/')) {
                return Resolution::redirect($this->permalinks->url($path . '/') . $request->queryStringWithout());
            }
            return $single ?? Resolution::notFound();
        }

        $resolution = match (true) {
            $segments === [] => $this->home($paged),
            $segments[0] === 'category' => $this->termArchive('category', array_slice($segments, 1), $paged),
            $segments[0] === 'tag' => $this->termArchive('post_tag', array_slice($segments, 1), $paged),
            $segments[0] === 'author' => count($segments) === 2 ? $this->authorArchive($segments[1], $paged) : Resolution::notFound(),
            $segments[0] === 'search' => count($segments) === 2 ? Resolution::search(rawurldecode($segments[1]), $paged) : Resolution::notFound(),
            $segments[0] === 'feed' => Resolution::notFound(),
            preg_match('/^\d{4}$/', $segments[0]) === 1 => $this->dateArchive($segments, $paged),
            default => $this->resolveContent($segments, $paged),
        };
        // The reference adds the trailing slash only for unpaged content
        // and archives; paged views, search, and 404s answer as typed.
        $slashable = in_array($resolution->kind, [Kind::Single, Kind::Page, Kind::Category, Kind::Tag, Kind::Author, Kind::Date], true);
        if ($slashable && $paged === 1 && !str_ends_with($path, '/')) {
            return Resolution::redirect($this->permalinks->url($path . '/') . $request->queryStringWithout());
        }
        return $resolution;
    }

    private function resolveQueryVars(Request $request): Resolution
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
            $link = $this->permalinks->forPost($post);
            if ($pretty && !str_contains($link, '?')) {
                return Resolution::redirect($link);
            }
            return Resolution::single($post);
        }
        if ($request->has('name')) {
            $post = $this->posts->findByName((string) $request->query('name'), ['post']);
            return $post === null ? Resolution::notFound() : $this->singleOrRedirect($post, 1, forceRedirect: $pretty);
        }
        if ($request->has('pagename')) {
            $segments = array_values(array_filter(explode('/', (string) $request->query('pagename')), static fn (string $s) => $s !== ''));
            $page = $this->posts->pageByPath($segments);
            if ($page !== null) {
                return (int) $page['ID'] === $this->permalinks->frontPageId ? Resolution::redirect($this->permalinks->url('/')) : Resolution::single($page);
            }
            $bySlug = $segments === [] ? null : $this->posts->findByName(end($segments), ['page']);
            return $bySlug === null ? Resolution::notFound() : Resolution::redirect($this->permalinks->forPost($bySlug));
        }
        if ($request->has('cat')) {
            $term = $this->terms->find('category', (int) $request->query('cat', '0'));
            return $term === null || !$pretty ? Resolution::notFound() : Resolution::redirect($this->permalinks->forTerm($term));
        }
        if ($request->has('tag')) {
            $term = $this->terms->findBySlug('post_tag', (string) $request->query('tag'));
            return $term === null || !$pretty ? Resolution::notFound() : Resolution::redirect($this->permalinks->forTerm($term));
        }
        if ($request->has('author')) {
            $user = $this->db->row("SELECT ID, user_nicename, display_name FROM {$this->db->table('users')} WHERE ID = ? LIMIT 1", [(int) $request->query('author', '0')]);
            return $user === null || !$pretty ? Resolution::notFound() : Resolution::redirect($this->permalinks->forAuthor($user));
        }
        if ($request->has('m') && preg_match('/^(\d{4})(\d{2})?(\d{2})?$/', (string) $request->query('m'), $m)) {
            return $this->dateRedirect((int) $m[1], isset($m[2]) ? (int) $m[2] : null, isset($m[3]) ? (int) $m[3] : null);
        }
        if ($request->has('year')) {
            $month = $request->has('monthnum') ? (int) $request->query('monthnum') : null;
            $day = $request->has('day') ? (int) $request->query('day') : null;
            return $this->dateRedirect((int) $request->query('year', '0'), $month, $day);
        }
        if ($request->has('s')) {
            return Resolution::search((string) $request->query('s'), max(1, (int) $request->query('paged', '1')));
        }
        return $this->home(max(1, (int) $request->query('paged', '1')));
    }

    private function dateRedirect(int $year, ?int $month, ?int $day): Resolution
    {
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
        $total = $this->posts->archive(['term' => (int) $term['term_taxonomy_id']], 1, 1)['total'];
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
            $total = $this->posts->archive(['author' => (int) $user['ID']], 1, 1)['total'];
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
        $total = $this->posts->archive(['from' => $range[0], 'to' => $range[1]], 1, 1)['total'];
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
    private function resolveContent(array $segments, int $paged): Resolution
    {
        $single = $this->resolveSingle($segments, $paged);
        if ($single !== null) {
            return $single;
        }
        // A trailing number on a single is an old-style page number the
        // reference no longer honours; it redirects to the plain permalink.
        $number = '';
        if (count($segments) >= 2 && ctype_digit(end($segments))) {
            $parent = $this->resolveSingle(array_slice($segments, 0, -1));
            if ($parent !== null) {
                return Resolution::redirect($this->permalinks->forPost($parent->record));
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
        $guess = $this->posts->guess(end($segments));
        return $guess === null ? Resolution::notFound() : Resolution::redirect($this->permalinks->forPost($guess) . $number);
    }

    /** @param list<string> $segments */
    private function resolveSingle(array $segments, int $paged = 1): ?Resolution
    {
        if ($segments === []) {
            return null;
        }
        $page = $this->posts->pageByPath($segments, publishedOnly: false);
        if ($page !== null && $this->readable($page)) {
            // The static front page answers only at the site root.
            return (int) $page['ID'] === $this->permalinks->frontPageId
                ? Resolution::redirect($this->permalinks->url('/'))
                : Resolution::single($page, $paged);
        }
        $regex = $this->permalinks->structureRegex();
        if ($regex !== null && preg_match($regex, implode('/', $segments), $m)) {
            $post = isset($m['post_id'])
                ? $this->posts->find((int) $m['post_id'])
                : $this->posts->findByName($m['postname'], ['post'], publishedOnly: false);
            if ($post !== null && $post['post_type'] === 'post' && $this->readable($post)) {
                return Resolution::single($post, $paged);
            }
        }
        return null;
    }

    private function singleOrRedirect(array $post, int $paged, bool $forceRedirect): Resolution
    {
        if (!$this->readable($post)) {
            return Resolution::notFound();
        }
        $link = $this->permalinks->forPost($post);
        return $forceRedirect && !str_contains($link, '?') ? Resolution::redirect($link) : Resolution::single($post, $paged);
    }

    private function readable(array $post): bool
    {
        if ($post['post_status'] === 'publish') {
            return true;
        }
        if (in_array($post['post_status'], ['trash', 'auto-draft', 'inherit'], true)) {
            return false;
        }
        return ($this->canReadUnpublished)($post);
    }

    private function pages(int $total): int
    {
        return max(1, (int) ceil($total / $this->perPage));
    }
}
