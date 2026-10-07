<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\TermRecord;
use Minn\Content\Terms;
use Minn\Content\UserRecord;
use Minn\Db;
use Minn\Http\Request;
use Minn\Runtime\Runtime;

/**
 * Where the root's archive query forms move under pretty permalinks, as
 * the reference's canonical redirect answers them (suite permalinks): a
 * date (?m=, ?year=) first, then an author by id, then a term when the
 * query names exactly one taxonomy (?cat= or ?category_name=, ?tag=,
 * ?taxonomy= with ?term=, a registered taxonomy's own var; ?post_format=
 * counts as one but never moves itself), each to its pretty address with
 * the other arguments along. Nothing moves while a search is asked for
 * (?s=, even empty), past the first page, or to something the site does
 * not have. The singles' forms are SingleQueries'.
 */
final readonly class QueryMoves
{
    public function __construct(
        private Db $db,
        private Terms $terms,
        private Permalinks $permalinks,
    ) {
    }

    /** The move a root request's query string makes; null when it makes none. */
    public function for(Request $request): ?Resolution
    {
        if ($request->has('s') || (int) $request->query('paged', '1') > 1) {
            return null;
        }
        return $this->date($request) ?? $this->author($request) ?? $this->term($request);
    }

    private function date(Request $request): ?Resolution
    {
        if ($request->has('m') && preg_match('/^(\d{4})(\d{2})?(\d{2})?$/', (string) $request->query('m'), $m)) {
            [$year, $month, $day] = [(int) $m[1], isset($m[2]) ? (int) $m[2] : null, isset($m[3]) ? (int) $m[3] : null];
        } elseif ($request->has('year')) {
            $year = (int) $request->query('year', '0');
            $month = $request->has('monthnum') ? (int) $request->query('monthnum') : null;
            $day = $request->has('day') ? (int) $request->query('day') : null;
        } else {
            return null;
        }
        return Resolution::redirect($this->permalinks->forDate($year, $month, $day) . $request->queryStringWithout('m', 'year', 'monthnum', 'day', 'paged'));
    }

    private function author(Request $request): ?Resolution
    {
        $id = (int) $request->query('author', '0');
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->row("SELECT ID, user_nicename, display_name FROM {$this->db->table('users')} WHERE ID = ? LIMIT 1", [$id]);
        return $row === null ? null : Resolution::redirect($this->permalinks->forAuthor(UserRecord::fromRow($row)) . $request->queryStringWithout('author', 'paged'));
    }

    /** The one taxonomy's term the query names, at its pretty address. */
    private function term(Request $request): ?Resolution
    {
        $named = [];
        if ($request->has('cat') || $request->has('category_name')) {
            $named['category'] = [['cat', 'category_name'], fn (): ?TermRecord => $request->has('category_name')
                ? $this->terms->findBySlug('category', self::lastSlug((string) $request->query('category_name')))
                : $this->terms->find('category', (int) $request->query('cat', '0'))];
        }
        if ($request->has('tag')) {
            $named['post_tag'] = [['tag'], fn (): ?TermRecord => $this->terms->findBySlug('post_tag', (string) $request->query('tag'))];
        }
        if ($request->has('post_format')) {
            $named['post_format'] = [[], static fn (): ?TermRecord => null];
        }
        if ($request->has('taxonomy') && $request->has('term')) {
            $taxonomy = (string) $request->query('taxonomy');
            $named[$taxonomy] = [['taxonomy', 'term'], fn (): ?TermRecord => $this->terms->findBySlug($taxonomy, self::lastSlug((string) $request->query('term')))];
        }
        foreach (Runtime::registry()->taxonomies() as $name => $taxonomy) {
            $var = $taxonomy['query_var'] ?? false;
            if (empty($taxonomy['_builtin']) && is_string($var) && $var !== '' && $request->has($var)) {
                $named[(string) $name] = [[$var], fn (): ?TermRecord => $this->terms->findBySlug((string) $name, self::lastSlug((string) $request->query($var)))];
            }
        }
        if (count($named) !== 1) {
            return null;
        }
        [$consumed, $find] = reset($named);
        $term = $consumed === [] ? null : $find();
        return $term === null ? null : Resolution::redirect($this->permalinks->forTerm($term) . $request->queryStringWithout('paged', ...$consumed));
    }

    private static function lastSlug(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s) => $s !== ''));
        return (string) end($segments);
    }
}
