<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Request;

/**
 * The collection parameters a wp/v2 list accepts, read once from the
 * request into typed fields: the page, the id lists, the slugs, the search
 * words, and the ordering. clauses() turns the narrowing ones into the SQL
 * fragments and parameters a controller appends to its own visibility
 * clause, so posts, pages, and media build their lists the same way.
 */
final readonly class ListQuery
{
    /**
     * @param list<int> $include
     * @param list<int> $exclude
     * @param list<int> $author
     * @param list<int> $authorExclude
     * @param list<int> $parent
     * @param list<int> $parentExclude
     * @param list<string> $slugs
     * @param list<string> $words
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 10,
        public array $include = [],
        public array $exclude = [],
        public array $author = [],
        public array $authorExclude = [],
        public array $parent = [],
        public array $parentExclude = [],
        public array $slugs = [],
        public array $words = [],
        public ?int $menuOrder = null,
        public string $orderBy = 'date',
        public string $order = 'DESC',
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $menuOrder = (string) $request->query('menu_order', '');
        return new self(
            page: max(1, (int) $request->query('page', '1')),
            perPage: max(1, min(100, (int) $request->query('per_page', '10'))),
            include: self::ids((string) $request->query('include', '')),
            exclude: self::ids((string) $request->query('exclude', '')),
            author: self::ids((string) $request->query('author', '')),
            authorExclude: self::ids((string) $request->query('author_exclude', '')),
            parent: self::ids((string) $request->query('parent', ''), true),
            parentExclude: self::ids((string) $request->query('parent_exclude', ''), true),
            slugs: self::list((string) $request->query('slug', '')),
            words: self::words((string) $request->query('search', '')),
            menuOrder: preg_match('/^-?\d+$/', $menuOrder) === 1 ? (int) $menuOrder : null,
            orderBy: (string) $request->query('orderby', 'date'),
            order: strtoupper((string) $request->query('order', 'desc')) === 'ASC' ? 'ASC' : 'DESC',
        );
    }

    /**
     * The narrowing clauses, each starting with " AND", and their parameters
     * in the same order. Every search word must appear in the title, the
     * excerpt, or the content.
     *
     * @return array{string, list<mixed>}
     */
    public function clauses(): array
    {
        $where = '';
        $params = [];
        foreach ([
            'post_author IN (?)' => $this->author,
            'post_author NOT IN (?)' => $this->authorExclude,
            'ID IN (?)' => $this->include,
            'ID NOT IN (?)' => $this->exclude,
            'post_name IN (?)' => $this->slugs,
            'post_parent IN (?)' => $this->parent,
            'post_parent NOT IN (?)' => $this->parentExclude,
        ] as $clause => $values) {
            if ($values !== []) {
                $where .= " AND {$clause}";
                $params[] = $values;
            }
        }
        foreach ($this->words as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $where .= ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)';
            $params = [...$params, $like, $like, $like];
        }
        return [$where, $params];
    }

    public function isSearch(): bool
    {
        return $this->words !== [];
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function totalPages(int $total): int
    {
        return (int) ceil($total / $this->perPage);
    }

    /** A page past the last one is a parameter error, except page one of nothing. */
    public function isPastTheEnd(int $total): bool
    {
        return $this->page > 1 && $this->page > $this->totalPages($total);
    }

    /**
     * A comma-separated id list as distinct integers. Zero is dropped unless
     * asked for: parent=0 means "top level", author=0 means nothing.
     *
     * @return list<int>
     */
    public static function ids(string $csv, bool $keepZero = false): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $n = (int) $part;
            if ($n !== 0 || $keepZero) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private static function list(string $csv): array
    {
        return array_values(array_filter(explode(',', $csv), static fn (string $s) => $s !== ''));
    }

    /** @return list<string> */
    private static function words(string $search): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($search)) ?: [], static fn (string $w) => $w !== ''));
    }
}
