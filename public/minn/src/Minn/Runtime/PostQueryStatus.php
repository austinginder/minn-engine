<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Query\Sql;

/**
 * WP_Query's post type and status clause as the reference writes it (probe
 * wp-query-sql). Statuses asked for by name: the type clause, then the
 * statuses (any, less those excluded from search; private kept apart when
 * the query asks for readable posts; the user's own when they may not read
 * or edit others'; an attachment's parent's status for a taxonomy archive).
 * None asked for, on a listing: each queried type, sorted, with its public
 * statuses, the admin list's protected ones in the admin, and private ones
 * the signed-in user may read. A single post: the type clause alone.
 */
final class PostQueryStatus
{
    public function __construct(private readonly \WP_Query $query, private readonly PostQueryParts $parts)
    {
    }

    /**
     * Settles the post type the caps come from: a list of several types has
     * none (its caps are named for multiple_post_type), any other names one.
     */
    public function settleType(): void
    {
        $type = $this->parts->postType;
        if (is_array($type) && count($type) > 1) {
            $this->parts->typeCap = 'multiple_post_type';
            return;
        }
        $type = is_array($type) ? reset($type) : $type;
        $this->parts->postType = $type;
        $this->parts->typeObject = is_string($type) ? \get_post_type_object($type) : null;
        if ($this->parts->typeObject === null) {
            $this->parts->typeCap = (string) $type;
        }
    }

    /** Appends the type and status clause. @param array<string, mixed> $q */
    public function append(array &$q): void
    {
        [$typeWhere, $skip] = $this->typeWhere();
        ['post_status' => $asked] = $q + ['post_status' => null];
        if ($skip) {
            $this->parts->where .= $typeWhere;
        } elseif (!empty($asked)) {
            $this->parts->where .= $typeWhere;
            $this->named($asked, $q['perm'] ?? '');
        } elseif (!$this->query->is_singular) {
            $this->listing();
        } else {
            $this->parts->where .= $typeWhere;
        }
    }

    /** The type clause, and whether there are no searchable types to give statuses to. @return array{0: string, 1: bool} */
    private function typeWhere(): array
    {
        $t = $this->parts->table;
        $type = $this->parts->postType;
        if ($type === 'any') {
            $searchable = \get_post_types(['exclude_from_search' => false]);
            return $searchable === [] ? [' AND 1=0 ', true] : [" AND {$t}.post_type IN ('" . implode("', '", array_map('esc_sql', $searchable)) . "')", false];
        }
        if (!empty($type) && is_array($type)) {
            $this->parts->postType = array_map('sanitize_key', $type);
            return [" AND {$t}.post_type IN ('" . implode("', '", \esc_sql($this->parts->postType)) . "')", false];
        }
        $type = match (true) {
            !empty($type) => \sanitize_key((string) $type),
            $this->query->is_attachment => 'attachment',
            $this->query->is_page => 'page',
            default => 'post',
        };
        if (!empty($this->parts->postType)) {
            $this->parts->postType = $type;
        }
        $this->parts->typeObject = \get_post_type_object($type);
        return [" AND {$t}.post_type = " . Sql::quote($type), false];
    }

    /** The cap a type's object names, or the one its name makes. */
    private function cap(string $which): string
    {
        $object = $this->parts->typeObject;
        return $object !== null ? (string) $object->cap->{$which} : str_replace('posts', $this->parts->typeCap . 's', $which);
    }

    /** Statuses asked for by name (or `any`), under the query's perm. */
    private function named(mixed $asked, mixed $perm): void
    {
        $t = $this->parts->table;
        $asked = is_array($asked) ? $asked : explode(',', (string) $asked);
        $this->parts->statuses = $asked;
        [$listed, $private, $excluded] = [[], [], []];
        if (in_array('any', $asked, true)) {
            foreach (\get_post_stati(['exclude_from_search' => true]) as $status) {
                if (!in_array($status, $asked, true)) {
                    $excluded[] = "{$t}.post_status <> '{$status}'";
                }
            }
        } else {
            foreach (\get_post_stati() as $status) {
                if ($status === 'private' && in_array($status, $asked, true)) {
                    $private[] = "{$t}.post_status = '{$status}'";
                } elseif (in_array($status, $asked, true)) {
                    $listed[] = "{$t}.post_status = '{$status}'";
                }
            }
        }
        if (empty($perm) || $perm !== 'readable') {
            [$listed, $private] = [[...$listed, ...$private], []];
        }
        $user = \get_current_user_id();
        $wheres = $excluded === [] ? [] : ['(' . implode(' AND ', $excluded) . ')'];
        if ($listed !== []) {
            $own = $perm === 'editable' && !\current_user_can($this->cap('edit_others_posts'));
            $wheres[] = $own ? "({$t}.post_author = {$user} AND (" . implode(' OR ', $listed) . '))' : '(' . implode(' OR ', $listed) . ')';
        }
        if ($private !== []) {
            $own = $perm === 'readable' && !\current_user_can($this->cap('read_private_posts'));
            $wheres[] = $own ? "({$t}.post_author = {$user} AND (" . implode(' OR ', $private) . '))' : '(' . implode(' OR ', $private) . ')';
        }
        if ($this->parts->statusJoin) {
            $this->parts->join .= " LEFT JOIN {$t} AS p2 ON ({$t}.post_parent = p2.ID) ";
            $wheres = array_map(static fn (string $where) => "({$where} OR ({$t}.post_status = 'inherit' AND " . str_replace($t, 'p2', $where) . '))', $wheres);
        }
        if ($wheres !== []) {
            $this->parts->where .= ' AND (' . implode(' OR ', $wheres) . ')';
        }
    }

    /** A listing with no statuses asked for: each type with the statuses its reader may see. */
    private function listing(): void
    {
        $t = $this->parts->table;
        $type = $this->parts->postType;
        $types = match (true) {
            $type === 'any' => array_values(\get_post_types(['exclude_from_search' => false])),
            is_array($type) => $type,
            !empty($type) => [$type],
            default => ['post'],
        };
        if ($types === []) {
            $this->parts->where .= ' AND 1=0 ';
            return;
        }
        sort($types);
        $clauses = [];
        foreach ($types as $name) {
            $clause = "({$t}.post_type = " . Sql::quote((string) $name) . ' AND (' . implode(' OR ', array_map(static fn (string $s) => "{$t}.post_status = '{$s}'", \get_post_stati(['public' => true])));
            if ($this->query->is_admin) {
                foreach (\get_post_stati(['protected' => true, 'show_in_admin_all_list' => true]) as $status) {
                    $clause .= " OR {$t}.post_status = '{$status}'";
                }
            }
            $clauses[] = $clause . $this->privateStatuses((string) $name) . '))';
        }
        $this->parts->where .= ' AND (' . implode(' OR ', $clauses) . ')';
    }

    /** The private statuses a signed-in user sees in a type: all of them with the cap to read them, their own otherwise. */
    private function privateStatuses(string $type): string
    {
        $object = \get_post_type_object($type);
        if (!\is_user_logged_in() || !$object instanceof \WP_Post_Type) {
            return '';
        }
        $t = $this->parts->table;
        $user = \get_current_user_id();
        $readable = \current_user_can($object->cap->read_private_posts);
        $sql = '';
        foreach (\get_post_stati(['private' => true]) as $status) {
            $sql .= $readable ? " \nOR {$t}.post_status = '{$status}'" : " \nOR ({$t}.post_author = {$user} AND {$t}.post_status = '{$status}')";
        }
        return $sql;
    }
}
