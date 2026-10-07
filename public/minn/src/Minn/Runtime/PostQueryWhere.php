<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Query\Sql;

/**
 * The WHERE fragments WP_Query writes from its variables, in the reference's
 * order and spacing (probe wp-query-sql): menu order, the legacy m date and
 * the date variables, a slug or a page path, ids, parents, a page id (which
 * replaces everything before it), authors, comment counts, mime types,
 * passwords, and comment and ping status. Each appends to the parts it is
 * handed and settles the variables it reads, as the reference leaves them.
 */
final class PostQueryWhere
{
    private const LEGACY_DATE = [4 => 'MONTH', 6 => 'DAYOFMONTH', 8 => 'HOUR', 10 => 'MINUTE', 12 => 'SECOND'];

    public function __construct(private readonly \WP_Query $query, private readonly PostQueryParts $parts, private readonly string $table)
    {
    }

    /** Menu order, the m date, the date variables and the date_query. @param array<string, mixed> $q */
    public function dates(array &$q): void
    {
        $t = $this->table;
        ['menu_order' => $menuOrder] = $q;
        if ($menuOrder !== '') {
            $this->parts->where .= " AND {$t}.menu_order = " . $menuOrder;
        }
        if ($q['m']) {
            // The year always; each further unit once the digits reach it.
            $m = (string) $q['m'];
            $this->parts->where .= " AND YEAR({$t}.post_date)=" . substr($m, 0, 4);
            foreach (self::LEGACY_DATE as $from => $unit) {
                if (strlen($m) >= $from + 2) {
                    $this->parts->where .= " AND {$unit}({$t}.post_date)=" . substr($m, $from, 2);
                }
            }
        }
        $params = array_filter(['hour' => $q['hour'], 'minute' => $q['minute'], 'second' => $q['second']], static fn ($v) => $v !== '');
        $params += array_filter(['year' => $q['year'], 'monthnum' => $q['monthnum'], 'week' => $q['w'], 'day' => $q['day']]);
        if ($params !== []) {
            $this->parts->where .= (new \WP_Date_Query([$params]))->get_sql();
        }
        if (!empty($q['date_query'])) {
            $this->query->date_query = new \WP_Date_Query($q['date_query']);
            $this->parts->where .= $this->query->date_query->get_sql();
        }
    }

    /**
     * The post a slug, a page path, an attachment slug, a name list, an id or
     * an id list names, and the parent; a post type's query var stands for
     * the name (the page path, for a hierarchical type).
     *
     * @param array<string, mixed> $q
     */
    public function names(array &$q): void
    {
        $t = $this->table;
        $this->typeQueryVar($q);
        if ($q['title'] !== '') {
            $this->parts->where .= " AND {$t}.post_title = " . Sql::quote(stripslashes((string) $q['title']));
        }
        if ($q['name'] !== '') {
            $q['name'] = \sanitize_title_for_query($q['name']);
            $this->parts->where .= " AND {$t}.post_name = '" . $q['name'] . "'";
        } elseif ($q['pagename'] !== '') {
            $this->pagename($q);
        } elseif ($q['attachment'] !== '') {
            $q['attachment'] = \sanitize_title_for_query(\wp_basename($q['attachment']));
            $q['name'] = $q['attachment'];
            $this->parts->where .= " AND {$t}.post_name = '" . $q['attachment'] . "'";
        } elseif (is_array($names = $q['post_name__in']) && $names !== []) {
            $this->nameList($q, $names);
        }
        if ($q['attachment_id']) {
            $q['p'] = abs((int) ($q['attachment_id']));
        }
        $this->ids($q);
    }

    /** A name list, sanitized in place; the clause lists each name once, sorted. @param array<string, mixed> $q @param list<mixed> $names */
    private function nameList(array &$q, array $names): void
    {
        $names = array_map('sanitize_title_for_query', $names);
        $q = array_replace($q, ['post_name__in' => $names]);
        $names = array_unique($names);
        sort($names);
        $this->parts->where .= " AND {$this->table}.post_name IN ('" . implode("','", $names) . "')";
    }

    /** @param array<string, mixed> $q */
    private function typeQueryVar(array &$q): void
    {
        ['post_type' => $types] = $q;
        if (empty($types) || $types === 'any') {
            return;
        }
        foreach ((array) $types as $type) {
            $object = \get_post_type_object($type);
            if (!$object || !$object->query_var || empty($q[$object->query_var])) {
                continue;
            }
            if ($object->hierarchical) {
                $q['pagename'] = $q[$object->query_var];
                $q['name'] = '';
            } else {
                $q['name'] = $q[$object->query_var];
            }
            break;
        }
    }

    /** A page path: the page it finds (in a hierarchical type queried), unless it is the posts page. @param array<string, mixed> $q */
    private function pagename(array &$q): void
    {
        if (isset($this->query->queried_object_id)) {
            $page = $this->query->queried_object_id;
        } else {
            $found = null;
            ['post_type' => $types] = $q;
            if ($types !== 'page') {
                foreach ((array) $types as $type) {
                    $object = \get_post_type_object($type);
                    if ($object && $object->hierarchical) {
                        $found = \get_page_by_path($q['pagename'], OBJECT, $type);
                        if ($found) {
                            break;
                        }
                    }
                }
            } else {
                $found = \get_page_by_path($q['pagename']);
            }
            $page = !empty($found) ? $found->ID : 0;
        }
        $postsPage = Runtime::options()->filtered('page_for_posts');
        if (Runtime::options()->filtered('show_on_front') === 'page' && !empty($postsPage) && $page == $postsPage) {
            return;
        }
        $q['pagename'] = \sanitize_title_for_query(\wp_basename($q['pagename']));
        $q['name'] = $q['pagename'];
        $this->parts->where .= " AND ({$this->table}.ID = '{$page}')";
        $object = \get_post($page);
        if (is_object($object) && $object->post_type === 'attachment') {
            $this->query->is_attachment = true;
            $this->query->is_page = true;
            $this->parts->postType = 'attachment';
            $q = array_replace($q, ['post_type' => 'attachment', 'attachment_id' => $page]);
        }
    }

    /** An id or an id list, the parent or a parent list, and a page id that replaces all before it. @param array<string, mixed> $q */
    private function ids(array &$q): void
    {
        $t = $this->table;
        $ids = static fn ($values): array => array_map(static fn ($v) => abs((int) $v), (array) $values);
        $sorted = static function ($values) use ($ids): array {
            $values = array_unique($ids($values));
            sort($values);
            return $values;
        };
        ['p' => $p, 'post__in' => $in, 'post__not_in' => $notIn, 'post_parent' => $parent, 'post_parent__in' => $parentIn, 'post_parent__not_in' => $parentNotIn] = $q;
        if ($p) {
            $this->parts->where .= " AND {$t}.ID = " . $p;
        } elseif ($in) {
            $this->parts->where .= " AND {$t}.ID IN (" . implode(',', $sorted($in)) . ')';
        } elseif ($notIn) {
            $notIn = (array) $notIn;
            sort($notIn);
            $q = array_replace($q, ['post__not_in' => $notIn]);
            $this->parts->where .= " AND {$t}.ID NOT IN (" . implode(',', $ids($notIn)) . ')';
        }
        if (is_numeric($parent)) {
            $this->parts->where .= " AND {$t}.post_parent = " . (int) $parent . ' ';
        } elseif ($parentIn) {
            $this->parts->where .= " AND {$t}.post_parent IN (" . implode(',', $sorted($parentIn)) . ')';
        } elseif ($parentNotIn) {
            $this->parts->where .= " AND {$t}.post_parent NOT IN (" . implode(',', $ids($parentNotIn)) . ')';
        }
        if ($q['page_id'] && (Runtime::options()->filtered('show_on_front') !== 'page' || Runtime::options()->filtered('page_for_posts') != $q['page_id'])) {
            $q['p'] = $q['page_id'];
            $this->parts->where = " AND {$t}.ID = " . $q['page_id'];
        }
    }

    /**
     * Authors (an author list's negatives become author__not_in, which wins
     * over author__in), an author slug, a comment count, and mime types. The
     * slug and mime clauses wait to follow the search.
     *
     * @param array<string, mixed> $q
     */
    public function authors(array &$q): void
    {
        $t = $this->table;
        if (!empty($q['author']) && $q['author'] != '0') {
            $q['author'] = addslashes(urldecode((string) $q['author']));
            $authors = array_unique(array_map('intval', preg_split('/[,\s]+/', $q['author']) ?: []));
            sort($authors);
            foreach ($authors as $author) {
                $q[$author > 0 ? 'author__in' : 'author__not_in'][] = abs($author);
            }
            $q['author'] = implode(',', $authors);
        }
        foreach (['author__not_in' => 'NOT IN', 'author__in' => 'IN'] as $var => $operator) {
            if (empty($q[$var])) {
                continue;
            }
            if (is_array($q[$var])) {
                $q[$var] = array_values(array_unique(array_map('absint', $q[$var])));
                sort($q[$var]);
            }
            $this->parts->where .= " AND {$t}.post_author {$operator} (" . implode(',', (array) $q[$var]) . ') ';
            break;
        }
        if ($q['author_name'] !== '') {
            $this->authorName($q);
        }
        $this->commentCount($q);
        ['post_mime_type' => $mime] = $q + ['post_mime_type' => null];
        if ($mime !== null && $mime !== '') {
            $this->parts->whichmimetype = \wp_post_mime_type_where($mime, $t);
        }
    }

    /** @param array<string, mixed> $q */
    private function authorName(array &$q): void
    {
        if (str_contains((string) $q['author_name'], '/')) {
            $segments = explode('/', (string) $q['author_name']);
            $q['author_name'] = $segments[count($segments) - 1] !== '' ? $segments[count($segments) - 1] : $segments[count($segments) - 2];
        }
        $q['author_name'] = \sanitize_title_for_query($q['author_name']);
        $user = \get_user_by('slug', $q['author_name']);
        $q['author'] = $user ? $user->ID : false;
        $this->parts->whichauthor .= " AND ({$this->table}.post_author = " . abs((int) ($q['author'])) . ')';
    }

    /** @param array<string, mixed> $q */
    private function commentCount(array &$q): void
    {
        ['comment_count' => $count] = $q + ['comment_count' => null];
        if ($count === null) {
            return;
        }
        $count = is_numeric($count) ? ['value' => (int) $count] : $count;
        if (isset($count['value'])) {
            $count = array_merge(['compare' => '='], $count);
            $count['compare'] = in_array($count['compare'], ['=', '!=', '>', '>=', '<', '<='], true) ? $count['compare'] : '=';
            $this->parts->where .= " AND {$this->table}.comment_count {$count['compare']} " . (int) $count['value'];
        }
        $q = array_replace($q, ['comment_count' => $count]);
    }

    /** A password, or whether there is one, and comment and ping status. @param array<string, mixed> $q */
    public function access(array &$q): void
    {
        $t = $this->table;
        ['post_password' => $password] = $q + ['post_password' => null];
        if ($password !== null) {
            $this->parts->where .= " AND {$t}.post_password = " . Sql::quote((string) $password);
            if (empty($q['perm'])) {
                $q['perm'] = 'readable';
            }
        } elseif (isset($q['has_password'])) {
            $this->parts->where .= sprintf(" AND {$t}.post_password %s ''", $q['has_password'] ? '!=' : '=');
        }
        foreach (['comment_status', 'ping_status'] as $var) {
            if (!empty($q[$var])) {
                $this->parts->where .= " AND {$t}.{$var} = " . Sql::quote((string) $q[$var]) . ' ';
            }
        }
    }
}
