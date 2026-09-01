<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Texturize;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/**
 * wp/v2 search over published content: id, title, url, type, and the
 * post type as subtype. Ordered the way the reference orders a search:
 * a title holding every term first, then a title holding any term, then
 * an excerpt match, then a content match, newest first within a rank.
 */
final readonly class SearchController
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private Db $db,
        private Types $types,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/search')]
    public function list(Request $request): Response
    {
        $type = $request->query('type') ?? 'post';
        if ($type !== 'post') {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): type', 400, ['params' => ['type' => 'type is not one of post.']]);
        }
        $perPage = self::intParam($request, 'per_page', 10, 1, self::MAX_PER_PAGE);
        $page = self::intParam($request, 'page', 1, 1, PHP_INT_MAX);
        $subtypes = $this->subtypes($request->query('subtype') ?? 'any');
        $terms = self::terms((string) ($request->query('search') ?? ''));

        $posts = $this->db->table('posts');
        $where = "post_status = 'publish' AND post_type IN (?)";
        $params = [$subtypes];
        $rank = '0';
        $rankParams = [];
        if ($terms !== []) {
            $rank = $this->rankExpression($terms, $rankParams);
            foreach ($terms as $term) {
                $like = '%' . self::escapeLike($term) . '%';
                $where .= ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)';
                array_push($params, $like, $like, $like);
            }
        }
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$posts} WHERE {$where}", $params);
        $rows = $this->db->rows(
            "SELECT ID, post_title, post_type, post_name, post_date, post_parent, post_status FROM {$posts}
             WHERE {$where} ORDER BY {$rank}, post_date DESC, ID DESC LIMIT ? OFFSET ?",
            [...$params, ...$rankParams, $perPage, ($page - 1) * $perPage],
        );
        $items = array_map(fn (array $row) => $this->item($row), $rows);
        return Reply::list($items, $total, $total === 0 ? 0 : (int) ceil($total / $perPage), Fields::fromQuery($request->query));
    }

    private function item(array $row): array
    {
        $type = (string) $row['post_type'];
        $base = $this->types->restBase($type);
        return [
            'id' => (int) $row['ID'],
            'title' => Texturize::text((string) $row['post_title']),
            'url' => $type === 'page' ? $this->permalinks->forPage($row) : $this->permalinks->forPost($row),
            'type' => 'post',
            'subtype' => $type,
            '_links' => [
                'self' => [[
                    'embeddable' => true,
                    'href' => $this->url->to("/wp/v2/{$base}/{$row['ID']}"),
                    'targetHints' => ['allow' => $this->caller->can('edit_post', (int) $row['ID']) ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
                ]],
                'about' => [['href' => $this->url->to("/wp/v2/types/{$type}")]],
                'collection' => [['href' => $this->url->to('/wp/v2/search')]],
            ],
        ];
    }

    /** The searchable post types: every registered type except the ones the reference keeps out. */
    private function subtypes(string $subtype): array
    {
        $all = array_values(array_diff(array_keys($this->types->all()), ['attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face']));
        if ($subtype === 'any' || $subtype === '') {
            return $all;
        }
        $wanted = array_values(array_filter(array_map('trim', explode(',', $subtype))));
        $unknown = array_diff($wanted, $all);
        if ($unknown !== []) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): subtype', 400, ['params' => ['subtype' => 'subtype is not one of ' . implode(', ', $all) . ', any.']]);
        }
        return $wanted;
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

    private static function intParam(Request $request, string $name, int $default, int $min, int $max): int
    {
        $raw = $request->query($name);
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (!is_numeric($raw) || (float) $raw !== (float) (int) $raw) {
            throw new RestError('rest_invalid_param', "Invalid parameter(s): {$name}", 400, ['params' => [$name => "{$name} is not of type integer."]]);
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw new RestError('rest_invalid_param', "Invalid parameter(s): {$name}", 400, ['params' => [$name => "{$name} must be between {$min} (inclusive) and {$max} (inclusive)"]]);
        }
        return $value;
    }
}
