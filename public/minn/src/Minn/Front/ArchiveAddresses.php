<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\TermRecord;
use Minn\Content\Terms;
use Minn\Content\UserRecord;
use Minn\Db;

/**
 * The archives an address may stand for, as the reference answers them:
 * the front, a search, a term's (a category's, a tag's, a plugin
 * taxonomy's), an author's and a date's. Each is the archive when what it
 * names exists (a term by its path's last slug, a valid date; an author's for any
 * name); whether its page has posts is the main query's to say, and an
 * empty page past the first, or an empty date, is the 404 it decides
 * (Theme\FrontLifecycle::handle404).
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

    /** A search's results page. */
    public function search(string $term, int $paged): Resolution
    {
        return Resolution::search($term, $paged);
    }

    /** The front: the static front page when there is one, else the posts listing. */
    public function home(int $paged): Resolution
    {
        if ($this->permalinks->frontPageId > 0) {
            $page = $this->posts->find($this->permalinks->frontPageId);
            if ($page !== null && $page->isPage() && ($this->readable)($page)) {
                return Resolution::frontPage($page, $paged);
            }
        }
        return Resolution::home($paged);
    }

    /**
     * A plugin taxonomy's term archive by its path; a 404 when no term is
     * at that path.
     *
     * @param list<string> $slugs
     */
    public function taxonomy(string $taxonomy, array $slugs, int $paged): Resolution
    {
        $term = $this->at($taxonomy, $slugs);
        return $term === null ? Resolution::notFound() : Resolution::taxonomy($term, $paged);
    }

    /** A category's or tag's archive by its path; a 404 when no term is at that path. @param list<string> $slugs */
    public function term(string $taxonomy, array $slugs, int $paged): Resolution
    {
        $term = $this->at($taxonomy, $slugs);
        return $term === null ? Resolution::notFound() : Resolution::term($taxonomy, $term, $paged);
    }

    /** An author's archive by nicename: the archive for any name, the author's when one has it. */
    public function author(string $name, int $paged): Resolution
    {
        $row = $this->db->row(
            "SELECT ID, user_nicename, display_name FROM {$this->db->table('users')} WHERE user_nicename = ? LIMIT 1",
            [$name],
        );
        return Resolution::author($name, $row === null ? null : UserRecord::fromRow($row), $paged);
    }

    /** A date archive by year, month and day; a 404 when the date is not one. @param list<string> $segments */
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
        return self::isDate($year, $month, $day) ? Resolution::date($year, $month, $day, $paged) : Resolution::notFound();
    }

    /**
     * The term a slug path names: its last slug's, whatever the segments
     * before it say (the reference serves /category/any/child/ as the
     * child's archive, unmoved).
     *
     * @param list<string> $slugs
     */
    private function at(string $taxonomy, array $slugs): ?TermRecord
    {
        return $slugs === [] ? null : $this->terms->findBySlug($taxonomy, (string) end($slugs));
    }

    /** Whether a year, month and day (the last two optional, a day only with its month) name a date. */
    public static function isDate(int $year, ?int $month, ?int $day): bool
    {
        if ($month !== null && ($month < 1 || $month > 12)) {
            return false;
        }
        return $day === null || ($month !== null && checkdate($month, $day, $year));
    }
}
