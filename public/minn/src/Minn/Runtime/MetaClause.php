<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/**
 * The meta side of a post query: meta_key and its friends as one clause,
 * then an explicit meta_query, each clause an EXISTS on postmeta with the
 * reference's compare and type rules, as one WHERE fragment on p.ID.
 */
final class MetaClause
{
    /** @var list<mixed> */
    private array $params = [];

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * The WHERE fragment and its parameters for a query's meta conditions,
     * or null when the query has none.
     *
     * @return array{string, list<mixed>}|null
     */
    public function where(array $q): ?array
    {
        $this->params = [];
        [$relation, $clauses] = self::metaClauses($q);
        if ($clauses === []) {
            return null;
        }
        $parts = array_map(fn (array $clause) => $this->metaSql($clause), $clauses);
        return ['(' . implode(" {$relation} ", $parts) . ')', $this->params];
    }

    /**
     * The meta_query the query vars amount to.
     *
     * @return array{string, list<array<string, mixed>>} relation, clauses
     */
    private static function metaClauses(array $q): array
    {
        $clauses = [];
        if (!empty($q['meta_key'])) {
            $clause = ['key' => (string) $q['meta_key']];
            if (isset($q['meta_value']) && $q['meta_value'] !== '') {
                $clause['value'] = $q['meta_value'];
                $clause['compare'] = (string) ($q['meta_compare'] ?? '=');
            } elseif (isset($q['meta_value_num']) && $q['meta_value_num'] !== '') {
                $clause['value'] = $q['meta_value_num'];
                $clause['compare'] = (string) ($q['meta_compare'] ?? '=');
                $clause['type'] = 'NUMERIC';
            } elseif (!empty($q['meta_compare'])) {
                $clause['compare'] = (string) $q['meta_compare'];
            }
            $clauses[] = $clause;
        }
        $relation = 'AND';
        $raw = $q['meta_query'] ?? [];
        if (is_array($raw)) {
            if (isset($raw['relation'])) {
                $relation = strtoupper((string) $raw['relation']) === 'OR' ? 'OR' : 'AND';
                unset($raw['relation']);
            }
            foreach ($raw as $clause) {
                if (is_array($clause) && (isset($clause['key']) || isset($clause['value']))) {
                    $clauses[] = $clause;
                }
            }
        }
        return [$relation, $clauses];
    }

    /** One clause as an EXISTS on postmeta, its parameters pushed. @param array<string, mixed> $clause */
    private function metaSql(array $clause): string
    {
        $parts = [];
        $meta = $this->db->table('postmeta');
            $key = (string) ($clause['key'] ?? '');
            $compare = strtoupper((string) ($clause['compare'] ?? (isset($clause['value']) && is_array($clause['value']) ? 'IN' : '=')));
            $type = strtoupper((string) ($clause['type'] ?? 'CHAR'));
            $cast = in_array($type, ['NUMERIC', 'DECIMAL', 'SIGNED'], true) ? 'CAST(meta_value AS SIGNED)' : ($type === 'UNSIGNED' ? 'CAST(meta_value AS UNSIGNED)' : 'meta_value');
            $keyClause = $key === '' ? '1=1' : 'meta_key = ?';
            $keyParams = $key === '' ? [] : [$key];
            if ($compare === 'NOT EXISTS') {
                $parts[] = "NOT EXISTS (SELECT 1 FROM {$meta} WHERE post_id = p.ID AND {$keyClause})";
                array_push($this->params, ...$keyParams);
                return $parts[0];
            }
            if ($compare === 'EXISTS' || !array_key_exists('value', $clause)) {
                $parts[] = "EXISTS (SELECT 1 FROM {$meta} WHERE post_id = p.ID AND {$keyClause})";
                array_push($this->params, ...$keyParams);
                return $parts[0];
            }
            $value = $clause['value'];
            switch ($compare) {
                case 'IN':
                case 'NOT IN':
                    $values = array_values((array) $value);
                    $valueClause = "{$cast} {$compare} (?)";
                    $valueParams = [$values];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $values = array_values((array) $value);
                    $valueClause = "{$cast} {$compare} ? AND ?";
                    $valueParams = [$values[0] ?? '', $values[1] ?? ''];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                    $valueClause = "{$cast} {$compare} ?";
                    $valueParams = ['%' . addcslashes((string) $value, '%_\\') . '%'];
                    break;
                case 'REGEXP':
                case 'NOT REGEXP':
                case 'RLIKE':
                    $valueClause = "{$cast} {$compare} ?";
                    $valueParams = [(string) $value];
                    break;
                default:
                    $operator = in_array($compare, ['!=', '>', '>=', '<', '<='], true) ? $compare : '=';
                    $valueClause = "{$cast} {$operator} ?";
                    $valueParams = [is_array($value) ? ($value[0] ?? '') : $value];
            }
            $parts[] = "EXISTS (SELECT 1 FROM {$meta} WHERE post_id = p.ID AND {$keyClause} AND {$valueClause})";
            array_push($this->params, ...$keyParams, ...$valueParams);
        return $parts[0];
    }
}
