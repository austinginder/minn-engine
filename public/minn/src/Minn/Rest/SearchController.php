<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Content\Texturize;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Registered;
use Minn\Support\Lists;

/**
 * wp/v2 search over published content: id, title, url, type, and the
 * post type as subtype. Ordered the way the reference orders a search:
 * a title holding every term first, then a title holding any term, then
 * an excerpt match, then a content match, newest first within a rank.
 */
final readonly class SearchController
{
    private const MAX_PER_PAGE = 100;

    /** Read by list(): the ids the results keep to, and those they leave out. */
    private const IDS = [
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
    ];

    /** The largest row offset the database takes; a page past it fails the query, and the reference then refuses the page. */
    private const LAST_OFFSET = 18446744073709551615.0;

    public function __construct(
        private Db $db,
        private Types $types,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** Search across post types with the reference's relevance order. */
    #[Route(Method::Get, '/wp/v2/search', policy: new Policy(Access::Public), args: [Args::SEARCH, self::IDS])]
    public function list(Request $request): Response
    {
        if (isset($request->query['type']) && !is_string($request->query['type'])) {
            throw RestError::invalidParam('type', 'type is not of type string.', 'rest_invalid_type', ['param' => 'type']);
        }
        // Terms, post formats and any type a plugin adds are its search handler's (wp_rest_search_handlers).
        if (($request->query('type') ?? 'post') !== 'post') {
            return RuntimeRoutes::fromWp(\_minn_rest_search_through_handlers(RuntimeRoutes::wpRequest($request)));
        }
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($request->query('per_page') ?: 10)));
        $page = max(1, (int) ($request->query('page') ?: 1));
        // The offset as the reference computes it, a page past the database's last offset included.
        $offset = (max(1.0, (float) ($request->query('page') ?: 1)) - 1) * $perPage;
        $subtypes = $this->subtypes($request->query['subtype'] ?? 'any');
        $terms = self::terms((string) ($request->query('search') ?? ''));

        $posts = $this->db->table('posts');
        // A protected post is no search result for someone signed out, title or content, as on the reference; with no term it is listed.
        $where = "post_status = 'publish' AND post_type IN (?)" . ($this->caller->id() === 0 && $terms !== [] ? " AND post_password = ''" : '');
        $params = [$subtypes];
        foreach (['include' => 'IN', 'exclude' => 'NOT IN'] as $name => $operator) {
            $ids = Lists::ids($request->query[$name] ?? []);
            if ($ids !== []) {
                $where .= " AND ID {$operator} (?)";
                $params[] = $ids;
            }
        }
        // With no term there is nothing to rank: the newest first, as the reference lists (the older id first on a tie).
        $order = 'post_date DESC, ID ASC';
        $rankParams = [];
        if ($terms !== []) {
            $order = $this->rankExpression($terms, $rankParams) . ', ' . $order;
            foreach ($terms as $term) {
                $like = '%' . self::escapeLike($term) . '%';
                $where .= ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)';
                array_push($params, $like, $like, $like);
            }
        }
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$posts} WHERE {$where}", $params);
        if ($offset > self::LAST_OFFSET && $total > 0) {
            throw new RestError('rest_search_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        // A page past the last finds nothing, and nothing is counted with it.
        if ($offset >= $total) {
            return Reply::list([], 0, 0, Fields::fromQuery($request->query));
        }
        $rows = $this->db->rows(
            "SELECT ID, post_title, post_type, post_name, post_date, post_parent, post_status FROM {$posts}
             WHERE {$where} ORDER BY {$order} LIMIT ? OFFSET ?",
            [...$params, ...$rankParams, $perPage, ($page - 1) * $perPage],
        );
        $items = array_map(fn (PostRecord $post) => $this->item($post), PostRecord::fromRows($rows));
        return Reply::list($items, $total, $total === 0 ? 0 : (int) ceil($total / $perPage), Fields::fromQuery($request->query));
    }

    private function item(PostRecord $row): array
    {
        $type = $row->type;
        $base = $this->types->restBase($type);
        return [
            'id' => $row->id,
            'title' => Texturize::text($row->title),
            'url' => $this->permalinks->link($row),
            'type' => 'post',
            'subtype' => $type,
            '_links' => [
                'self' => [[
                    'embeddable' => true,
                    'href' => $this->url->to("/wp/v2/{$base}/{$row->id}"),
                    'targetHints' => ['allow' => $this->caller->can('edit_post', $row->id) ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
                ]],
                'about' => [['href' => $this->url->to("/wp/v2/types/{$type}")]],
                'collection' => [['href' => $this->url->to('/wp/v2/search')]],
            ],
        ];
    }

    /**
     * The post types searched: every searchable one for "any", otherwise
     * those asked for that are post types (a taxonomy's name is another
     * handler's subtype and finds no post here), and posts when none is
     * left, as the reference's query falls back. A subtype is refused only
     * when no search handler offers it, with the schema's own words: the
     * post types', the taxonomies' and "any".
     *
     * @return list<string>
     */
    private function subtypes(mixed $subtype): array
    {
        $all = $this->types->searchable();
        $wanted = is_array($subtype)
            ? array_values(array_filter($subtype, is_string(...)))
            : array_values(array_filter(array_map('trim', explode(',', (string) $subtype)), static fn (string $s): bool => $s !== ''));
        $offered = [...$all, ...$this->taxonomies(), 'any'];
        foreach ($wanted as $index => $name) {
            if (!in_array($name, $offered, true)) {
                $message = sprintf('subtype[%d] is not one of %s and any.', $index, implode(', ', array_slice($offered, 0, -1)) . ',');
                throw RestError::invalidParam('subtype', $message);
            }
        }
        if (in_array('any', $wanted, true) || $subtype === 'any') {
            return $all;
        }
        $types = array_values(array_intersect($wanted, $all));
        return $types === [] ? ['post'] : $types;
    }

    /** The taxonomies the term search offers as subtypes: those shown in REST that are public. @return list<string> */
    private function taxonomies(): array
    {
        return array_values(array_map('strval', Registered::taxonomies(['public' => true, 'show_in_rest' => true])));
    }

    /** A CASE that ranks a title match over an excerpt over a content match. */
    private function rankExpression(array $terms, array &$params): string
    {
        $titleAll = implode(' AND ', array_fill(0, count($terms), 'post_title LIKE ?'));
        $titleAny = implode(' OR ', array_fill(0, count($terms), 'post_title LIKE ?'));
        $excerptAny = implode(' OR ', array_fill(0, count($terms), 'post_excerpt LIKE ?'));
        $likes = array_map(static fn (string $t) => '%' . self::escapeLike($t) . '%', $terms);
        array_push($params, ...$likes, ...$likes, ...$likes);
        return "(CASE WHEN {$titleAll} THEN 1 WHEN {$titleAny} THEN 2 WHEN {$excerptAny} THEN 3 ELSE 4 END)";
    }

    /** @return list<string> */
    private static function terms(string $search): array
    {
        $search = trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags($search)) ?? '');
        if ($search === '') {
            return [];
        }
        preg_match_all('/"([^"]+)"|(\S+)/', $search, $m);
        $terms = [];
        foreach ($m[0] as $i => $raw) {
            $term = $m[1][$i] !== '' ? $m[1][$i] : $m[2][$i];
            if (mb_strlen($term) > 1 || count($m[0]) === 1) {
                $terms[] = $term;
            }
        }
        return array_slice($terms, 0, 9);
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
