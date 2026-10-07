<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * get_posts_by_author_sql as the reference writes it: for each type, its
 * published posts, and its private ones the reader may see (all of them
 * with the cap to read others', their own otherwise), OR'd; an author
 * narrows it; WHERE in front when asked.
 */
final class AuthorPostsSql
{
    /**
     * The clause for the posts an author's lists count, as the reader may see them.
     *
     * @param list<array{type: string, readPrivate: bool}> $types each type that exists, and whether the reader may read its private posts
     * @param int $reader the signed-in reader's id, 0 when signed out
     */
    public static function sql(array $types, int $reader, string $full, ?int $author, string $publicOnly): string
    {
        $clauses = [];
        foreach ($types as ['type' => $type, 'readPrivate' => $readPrivate]) {
            $status = "post_status = 'publish'";
            if ($publicOnly === '') {
                $status .= match (true) {
                    $readPrivate => " OR post_status = 'private'",
                    $reader > 0 && ($author === null || $full === '') => " OR post_status = 'private' AND post_author = {$reader}",
                    $reader > 0 && $reader === $author => " OR post_status = 'private'",
                    default => '',
                };
            }
            $clauses[] = "( post_type = '{$type}' AND ( {$status} ) )";
        }
        if ($clauses === []) {
            return $full !== '' ? 'WHERE 1 = 0' : '1 = 0';
        }
        $sql = '( ' . implode(' OR ', $clauses) . ' )' . ($author !== null ? ' AND post_author = ' . $author : '');
        return $full !== '' ? 'WHERE ' . $sql : $sql;
    }
}
