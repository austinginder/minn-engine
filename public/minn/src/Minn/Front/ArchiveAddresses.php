<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\PostFilter;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\TermRecord;
use Minn\Content\Terms;
use Minn\Content\UserRecord;
use Minn\Db;

/**
 * The archives an address may stand for, as the reference answers them:
 * the front, a search, a term's (a category's, a tag's, a plugin
 * taxonomy's), an author's and a date's, each a 404 when it is empty or
 * paged past its end (an author's only past a real author's end). The
 * resolver reads them off the path; a plugin's rule hands their vars.
 */
final readonly class ArchiveAddresses
{
    /** @param Closure(PostRecord): bool $readable whether the reader may read a post */
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Terms $terms,
        private Permalinks $permalinks,
        private Closure $readable,
    ) {
    }

    /** A search's results page; past the last page, a 404. */
    public function search(string $term, int $paged): Resolution
    {
        return $paged > 1 && $paged > $this->pages($this->posts->count(PostFilter::all()->matching($term))) ? Resolution::notFound() : Resolution::search($term, $paged);
    }

    /** The front: the static front page when there is one, else the posts listing; past its last page, a 404. */
    public function home(int $paged): Resolution
    {
        if ($this->permalinks->frontPageId > 0) {
            $page = $this->posts->find($this->permalinks->frontPageId);
            if ($page !== null && $page->isPage() && ($this->readable)($page)) {
                return Resolution::frontPage($page, $paged);
            }
        }
        $total = (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'",
        );
        return $paged > 1 && $paged > $this->pages($total) ? Resolution::notFound() : Resolution::home($paged);
    }

    /**
     * A plugin taxonomy's term archive by its path; a 404 when the term is
     * missing, empty or overpaged.
     *
     * @param list<string> $types the post types the taxonomy attaches to
     * @param list<string> $slugs
     */
    public function taxonomy(string $taxonomy, array $types, array $slugs, int $paged): Resolution
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

    /** A category's or tag's archive by its path; a 404 when the term is missing. @param list<string> $slugs */
    public function term(string $taxonomy, array $slugs, int $paged): Resolution
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
    public function termResolution(string $taxonomy, TermRecord $term, int $paged): Resolution
    {
        $total = $this->posts->count(PostFilter::all()->inTerm((int) $term['term_taxonomy_id']));
        if ($total === 0 || $paged > $this->pages($total)) {
            return Resolution::notFound();
        }
        return Resolution::term($taxonomy, $term, $paged);
    }

    /** An author's archive by nicename: 200 for any name, a 404 only past a real author's last page. */
    public function author(string $name, int $paged): Resolution
    {
        $row = $this->db->row(
            "SELECT ID, user_nicename, display_name FROM {$this->db->table('users')} WHERE user_nicename = ? LIMIT 1",
            [$name],
        );
        $user = $row === null ? null : UserRecord::fromRow($row);
        if ($user !== null) {
            $total = $this->posts->count(PostFilter::all()->byAuthor($user->id));
            if ($paged > 1 && $paged > $this->pages($total)) {
                return Resolution::notFound();
            }
        }
        return Resolution::author($name, $user, $paged);
    }

    /** A date archive by year, month and day; a 404 when invalid, empty or overpaged. @param list<string> $segments */
    public function date(array $segments, int $paged): Resolution
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

    /**
     * The site-local bounds of a date archive, or null when the date is invalid.
     *
     * @return array{0: string, 1: string}|null
     */
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

    /** How many listing pages a total fills. */
    public function pages(int $total): int
    {
        return max(1, (int) ceil($total / max(1, (int) ($this->db->option('posts_per_page') ?? 10))));
    }
}
