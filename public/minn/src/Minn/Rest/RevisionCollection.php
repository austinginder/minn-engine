<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Http\Request;
use Minn\Query\PostSearch;
use Minn\RestError;

/**
 * A revisions list as the reference answers it (probe of the posts,
 * pages, patterns, menus, templates and global styles revisions routes):
 * the include list (which outranks exclude) or the exclude list, the
 * search (terms, quoted phrases and -exclusions over the title, excerpt
 * and content, as a post search reads them), the order, then the page. An
 * empty result is no pages at all. Without per_page every match is one
 * page. An offset sent (zero too) is where a page of per_page starts, in
 * the page number's place; one other than zero is refused at or past the
 * total, and without one a page past the last is refused.
 */
final class RevisionCollection
{
    /**
     * The revision a single route asks for, refused as the reference refuses
     * it: no revision by that id is an invalid id, one of another parent a
     * mismatch naming the parent asked for.
     *
     * @param array<string, mixed>|null $row
     */
    public static function ofParent(?array $row, int $parent): PostRecord
    {
        if ($row === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid revision ID.', 404);
        }
        $revision = PostRecord::fromRow($row);
        if ($revision->parentId !== $parent) {
            throw new RestError('rest_revision_parent_id_mismatch', sprintf('The revision does not belong to the specified parent with id of "%d"', $parent), 404);
        }
        return $revision;
    }

    /**
     * The rows the request's filters keep, in its order.
     *
     * @param list<PostRecord> $rows newest first
     * @return list<PostRecord>
     */
    public static function filtered(Request $request, array $rows): array
    {
        $search = (string) $request->query('search', '');
        $include = self::ids($request->query['include'] ?? []);
        $orderby = (string) $request->query('orderby', 'date');
        if ($orderby === 'relevance' && $search === '') {
            throw new RestError('rest_no_search_term_defined', 'You need to define a search term to order by relevance.', 400);
        }
        if ($orderby === 'include' && $include === []) {
            throw new RestError('rest_orderby_include_missing_include', 'You need to define an include parameter to order by include.', 400);
        }
        $exclude = $include === [] ? self::ids($request->query['exclude'] ?? []) : [];
        $kept = array_values(array_filter(
            $rows,
            static fn (PostRecord $row): bool => ($include === [] || in_array($row->id, $include, true)) && !in_array($row->id, $exclude, true),
        ));
        $terms = $search === '' ? [] : PostSearch::terms($search, static fn (): array => PostSearch::stopwords(PostSearch::STOPWORDS))['terms'];
        if ($terms !== []) {
            $kept = array_values(array_filter($kept, static fn (PostRecord $row): bool => self::matches($row, $terms)));
        }
        return self::sorted($kept, $orderby, $request->query('order', 'desc') === 'asc' ? 1 : -1, $include, $search, $terms);
    }

    /**
     * The page the request asks for, with the total and the page count.
     *
     * @template T
     * @param list<T> $rows
     * @return array{0: list<T>, 1: int, 2: int}
     */
    public static function paged(Request $request, array $rows): array
    {
        $total = count($rows);
        if ($total === 0) {
            return [[], 0, 0];
        }
        $perPage = $request->has('per_page') ? (int) trim((string) $request->query('per_page', '0')) : 0;
        $pages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = (int) trim((string) $request->query('offset', '0'));
        $page = (int) trim((string) $request->query('page', '1'));
        if ($offset !== 0 && $offset >= $total) {
            throw new RestError('rest_revision_invalid_offset_number', 'The offset number requested is larger than or equal to the number of available revisions.', 400);
        }
        if ($offset === 0 && $page > $pages) {
            throw new RestError('rest_revision_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        if ($perPage === 0) {
            return [$rows, $total, $pages];
        }
        // An offset sent, even zero, is where the page starts; the page number counts only without one.
        $start = $request->has('offset') ? abs($offset) : ($page - 1) * $perPage;
        return [array_slice($rows, $start, $perPage), $total, $pages];
    }

    /**
     * An id list argument as integers: a list, or a comma or space separated string.
     *
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, SchemaValues::toArray($value))));
    }

    /**
     * Whether a revision matches every search term: an excluded term (a leading dash) in none of its columns, any other in one.
     *
     * @param list<string> $terms
     */
    private static function matches(PostRecord $row, array $terms): bool
    {
        foreach ($terms as $term) {
            $excluded = str_starts_with($term, '-');
            $needle = $excluded ? substr($term, 1) : $term;
            $found = $needle === '' || self::contains($row->title, $needle) || self::contains($row->excerpt, $needle) || self::contains($row->content, $needle);
            if ($found === $excluded) {
                return false;
            }
        }
        return true;
    }

    private static function contains(string $haystack, string $needle): bool
    {
        return mb_stripos($haystack, $needle) !== false;
    }

    /**
     * The rows in the asked order: by date with the id breaking ties, by id,
     * by the include list's own order, by relevance (the whole search in
     * the title, then every term there, then any, then by date), or by
     * title or slug with ties left in id order.
     *
     * @param list<PostRecord> $rows
     * @param list<int> $include
     * @param list<string> $terms
     * @return list<PostRecord>
     */
    private static function sorted(array $rows, string $orderby, int $direction, array $include, string $search, array $terms): array
    {
        $byDate = static fn (PostRecord $a, PostRecord $b): int => $direction * ([$a->date, $a->id] <=> [$b->date, $b->id]);
        if ($orderby === 'include') {
            $position = array_flip($include);
            usort($rows, static fn (PostRecord $a, PostRecord $b): int => $position[$a->id] <=> $position[$b->id]);
            return $rows;
        }
        if ($orderby === 'relevance') {
            $rank = static fn (PostRecord $row): int => self::rank($row, $search, $terms);
            // Equal ranks go by date in the asked direction, and equal dates stay in id order.
            usort($rows, static fn (PostRecord $a, PostRecord $b): int => ($rank($a) <=> $rank($b)) ?: ($direction * ($a->date <=> $b->date)) ?: $a->id <=> $b->id);
            return $rows;
        }
        $key = match ($orderby) {
            'title' => static fn (PostRecord $row): string => mb_strtolower($row->title),
            'slug' => static fn (PostRecord $row): string => mb_strtolower($row->slug),
            'id' => null,
            'include_slugs' => static fn (PostRecord $row): string => '',
            default => false,
        };
        if ($key === false) {
            usort($rows, $byDate);
            return $rows;
        }
        if ($key === null) {
            usort($rows, static fn (PostRecord $a, PostRecord $b): int => $direction * ($a->id <=> $b->id));
            return $rows;
        }
        usort($rows, static fn (PostRecord $a, PostRecord $b): int => ($direction * ($key($a) <=> $key($b))) ?: $a->id <=> $b->id);
        return $rows;
    }

    /**
     * A row's place in the relevance order, lowest first.
     *
     * @param list<string> $terms
     */
    private static function rank(PostRecord $row, string $search, array $terms): int
    {
        $positive = array_values(array_filter($terms, static fn (string $term): bool => !str_starts_with($term, '-')));
        $inTitle = array_filter($positive, static fn (string $term): bool => self::contains($row->title, $term));
        return match (true) {
            self::contains($row->title, $search) => 1,
            $positive !== [] && count($inTitle) === count($positive) => 2,
            $inTitle !== [] => 3,
            self::contains($row->excerpt, $search) => 4,
            self::contains($row->content, $search) => 5,
            default => 6,
        };
    }
}
