<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A user query's roles and capabilities as the meta clauses the reference
 * writes for them (probe wp-user-query-sql): a capability matches its own
 * name or any role granting it, capability__in and __not_in widen the role
 * lists, and each role is a LIKE on the site's capabilities meta (NOT LIKE
 * to leave it out).
 */
final class UserQueryRoles
{
    public function __construct(private readonly string $capabilitiesKey)
    {
    }

    /**
     * The meta query's clauses once the roles and capabilities join them.
     *
     * @param array<string, mixed> $qv
     * @param array<array-key, mixed> $queries the meta query's own clauses
     * @param array<string, array{capabilities: array<string, bool>}> $roles the site's roles
     * @return array<array-key, mixed>
     */
    public function clauses(array $qv, array $queries, array $roles): array
    {
        [$wanted, $in, $out, $withRoles] = $this->lists($qv, $roles);
        if ($wanted['capabilities'] !== []) {
            $clauses = ['relation' => 'AND'];
            foreach ($wanted['capabilities'] as $cap) {
                $clauses[] = ['relation' => 'OR', $this->like($cap), ...array_map($this->like(...), $withRoles[$cap] ?? [])];
            }
            $queries = $queries === [] ? [$clauses] : ['relation' => 'AND', [$queries, [$clauses]]];
        }
        if ($wanted['roles'] === [] && $in === [] && $out === [] && !\is_multisite()) {
            return $queries;
        }
        $roleQueries = [];
        if ($wanted['roles'] !== []) {
            $roleQueries[] = ['relation' => 'AND', ...array_map($this->like(...), $wanted['roles'])];
        }
        if ($in !== []) {
            $roleQueries[] = ['relation' => 'OR', ...array_map($this->like(...), $in)];
        }
        if ($out !== []) {
            $roleQueries[] = ['relation' => 'AND', ...array_map(fn ($role) => $this->like($role, 'NOT LIKE'), $out)];
        }
        if ($roleQueries === []) {
            $roleQueries[] = ['key' => $this->capabilitiesKey, 'compare' => 'EXISTS'];
        }
        $roleQueries['relation'] = 'AND';
        return $queries === [] ? $roleQueries : ['relation' => 'AND', [$queries, $roleQueries]];
    }

    /**
     * The roles asked for, the role__in and role__not_in lists (widened by
     * the roles granting capability__in and __not_in, then the capabilities
     * themselves), and the roles granting each capability asked for.
     *
     * @param array<string, mixed> $qv
     * @param array<string, array{capabilities: array<string, bool>}> $roles
     * @return array{0: array{roles: list<string>, capabilities: list<string>}, 1: list<string>, 2: list<string>, 3: array<string, list<string>>}
     */
    private function lists(array $qv, array $roles): array
    {
        $asked = is_array($qv['role'] ?? null) ? $qv['role'] : (is_string($qv['role'] ?? null) && $qv['role'] !== '' ? array_map('trim', explode(',', $qv['role'])) : []);
        $in = (array) ($qv['role__in'] ?? []);
        $out = (array) ($qv['role__not_in'] ?? []);
        $caps = empty($qv['capability']) ? [] : (is_array($qv['capability']) ? $qv['capability'] : array_map('trim', explode(',', (string) $qv['capability'])));
        $capsIn = empty($qv['capability__in']) ? [] : (array) $qv['capability__in'];
        $capsOut = empty($qv['capability__not_in']) ? [] : (array) $qv['capability__not_in'];
        $withRoles = [];
        foreach (($caps !== [] || $capsIn !== [] || $capsOut !== []) ? $roles : [] as $role => $data) {
            $granted = array_keys(array_filter((array) ($data['capabilities'] ?? [])));
            $cap = self::firstOf($caps, $granted);
            if ($cap !== null) {
                $withRoles[$cap][] = (string) $role;
            }
            $in = self::firstOf($capsIn, $granted) !== null ? [...$in, (string) $role] : $in;
            $out = self::firstOf($capsOut, $granted) !== null ? [...$out, (string) $role] : $out;
        }
        return [['roles' => array_values(array_unique($asked)), 'capabilities' => $caps], array_values(array_unique([...$in, ...$capsIn])), array_values(array_unique([...$out, ...$capsOut])), $withRoles];
    }

    /** The first of the wanted capabilities a role grants, in the order asked. @param list<string> $wanted @param list<string> $granted */
    private static function firstOf(array $wanted, array $granted): ?string
    {
        foreach ($wanted as $cap) {
            if (in_array($cap, $granted, true)) {
                return $cap;
            }
        }
        return null;
    }

    /** @return array<string, string> */
    private function like(string $role, string $compare = 'LIKE'): array
    {
        return ['key' => $this->capabilitiesKey, 'value' => '"' . $role . '"', 'compare' => $compare];
    }
}
