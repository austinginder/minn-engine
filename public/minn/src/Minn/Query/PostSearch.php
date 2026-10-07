<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * WP_Query's search as the reference writes it (probe wp-query-sql): the
 * search string split into terms (quoted phrases kept whole, single letters
 * and stopwords dropped, ten or more terms or none left searched as one
 * sentence), each term a group of LIKEs over the searched columns (NOT LIKE
 * and AND for an excluded term), the password clause for a visitor, and the
 * relevance order: a CASE ladder for several terms, the title match first
 * for one.
 */
final class PostSearch
{
    public const COLUMNS = ['post_title', 'post_excerpt', 'post_content'];
    public const STOPWORDS = 'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www';
    private const TERM = '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/';

    /**
     * The terms a search string becomes and how many it was split into.
     *
     * @param callable(): list<string> $stopwords asked only when the string splits
     * @return array{terms: list<string>, count: int}
     */
    public static function terms(string $search, callable $stopwords): array
    {
        if (preg_match_all(self::TERM, $search, $matches) < 1) {
            return ['terms' => [$search], 'count' => 1];
        }
        $terms = self::checked($matches[0], $stopwords());
        return ['terms' => $terms === [] || count($terms) > 9 ? [$search] : $terms, 'count' => count($matches[0])];
    }

    /**
     * The terms worth searching: quotes trimmed (a quoted term keeps its
     * inner spaces), and a single letter or dash, or a stopword, dropped.
     *
     * @param list<string> $terms
     * @param list<string> $stopwords
     * @return list<string>
     */
    public static function checked(array $terms, array $stopwords): array
    {
        $checked = [];
        foreach ($terms as $term) {
            $term = preg_match('/^".+"$/', $term) === 1 ? trim($term, "\"'") : trim($term, "\"' ");
            if ($term === '' || (strlen($term) === 1 && preg_match('/^[a-z\-]$/i', $term) === 1)) {
                continue;
            }
            if (in_array(mb_strtolower($term), $stopwords, true)) {
                continue;
            }
            $checked[] = $term;
        }
        return $checked;
    }

    /**
     * The stopword list from its comma-separated (translated) form.
     *
     * @return list<string>
     */
    public static function stopwords(string $list): array
    {
        return array_values(array_filter(array_map(static fn (string $word) => trim($word, "\r\n\t "), explode(',', $list)), static fn (string $word) => $word !== ''));
    }

    /**
     * The searched columns: those given that the reference searches, or all three.
     *
     * @return list<string>
     */
    public static function columns(array $given): array
    {
        $columns = array_values(array_intersect($given, self::COLUMNS));
        return $columns === [] ? self::COLUMNS : $columns;
    }

    /**
     * The search's WHERE fragment (no password clause; a visitor's search
     * adds one) and the title matches its order ranks by. An exact search
     * passes no wildcard, and asks for no title matches.
     *
     * @param list<string> $terms
     * @param list<string> $columns each with its table, as the clause names it
     * @return array{where: string, titles: list<string>}
     */
    public static function where(string $table, array $terms, array $columns, string $exclusionPrefix, string $wild): array
    {
        $groups = [];
        $titles = [];
        foreach ($terms as $term) {
            $exclude = $exclusionPrefix !== '' && str_starts_with($term, $exclusionPrefix);
            $term = $exclude ? substr($term, 1) : $term;
            [$like, $join] = $exclude ? ['NOT LIKE', 'AND'] : ['LIKE', 'OR'];
            if ($wild !== '' && !$exclude) {
                $titles[] = "{$table}.post_title LIKE " . Sql::quote('%' . Sql::like($term) . '%');
            }
            $value = Sql::quote($wild . Sql::like($term) . $wild);
            $parts = array_map(static fn (string $column) => "({$column} {$like} {$value})", $columns);
            $groups[] = '(' . implode(" {$join} ", $parts) . ')';
        }
        return ['where' => $groups === [] ? '' : ' AND (' . implode(' AND ', $groups) . ') ', 'titles' => $titles];
    }

    /**
     * The relevance order: for several terms, the whole string in the title,
     * then every term, then any term (up to six), then the whole string in the
     * excerpt and the content (no sentence match when a term is negated); for
     * one term, its title match.
     *
     * @param list<string> $titles
     */
    public static function order(string $table, string $search, int $count, array $titles): string
    {
        if ($count <= 1) {
            return reset($titles) . ' DESC';
        }
        $like = preg_match('/(?:\s|^)\-/', $search) === 1 ? '' : Sql::quote('%' . Sql::like($search) . '%');
        $order = $like !== '' ? "WHEN {$table}.post_title LIKE {$like} THEN 1 " : '';
        if (count($titles) < 7) {
            $order .= 'WHEN ' . implode(' AND ', $titles) . ' THEN 2 ';
            $order .= count($titles) > 1 ? 'WHEN ' . implode(' OR ', $titles) . ' THEN 3 ' : '';
        }
        if ($like !== '') {
            $order .= "WHEN {$table}.post_excerpt LIKE {$like} THEN 4 WHEN {$table}.post_content LIKE {$like} THEN 5 ";
        }
        return $order !== '' ? '(CASE ' . $order . 'ELSE 6 END)' : '';
    }
}
