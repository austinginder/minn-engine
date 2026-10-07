<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The pieces of one WP_Query run as the reference builds them and hands
 * them to filters. The seven clause pieces stay untyped: a filter may hand
 * back anything, and the request is written from whatever it handed back.
 */
final class PostQueryParts
{
    /** The pieces posts_clauses and posts_clauses_request see, in their order. */
    public const PIECES = ['where', 'groupby', 'join', 'orderby', 'distinct', 'fields', 'limits'];

    public mixed $where = '';
    public mixed $groupby = '';
    public mixed $join = '';
    public mixed $orderby = '';
    public mixed $distinct = '';
    public mixed $fields = '';
    public mixed $limits = '';
    public mixed $search = '';
    public string $whichauthor = '';
    public string $whichmimetype = '';
    /** Whether an attachment's status follows its parent's (a taxonomy archive's attachments). */
    public bool $statusJoin = false;
    public int $page = 1;
    /** The post type the query settles on: a name, a list, `any`, or empty. */
    public mixed $postType = '';
    public ?\WP_Post_Type $typeObject = null;
    public string $typeCap = '';
    /** The statuses asked for by name, which a single post's status check lets through. @var list<string> */
    public array $statuses = [];

    public function __construct(public readonly string $table)
    {
    }

    /** The seven pieces by name. @return array<string, mixed> */
    public function pieces(): array
    {
        $pieces = [];
        foreach (self::PIECES as $piece) {
            $pieces[$piece] = $this->{$piece};
        }
        return $pieces;
    }

    /** Takes back the pieces a clauses filter returned; one it dropped becomes empty. @param array<array-key, mixed> $clauses */
    public function take(array $clauses): void
    {
        foreach (self::PIECES as $piece) {
            $this->{$piece} = $clauses[$piece] ?? '';
        }
    }

    /** The SELECT as the reference writes it, line breaks and all, for the given field list. */
    public function request(string $foundRows, mixed $fields): string
    {
        $groupby = !empty($this->groupby) ? 'GROUP BY ' . $this->groupby : '';
        $orderby = !empty($this->orderby) ? 'ORDER BY ' . $this->orderby : '';
        $break = "\n\t\t\t\t\t ";
        return "SELECT {$foundRows} {$this->distinct} {$fields}{$break}FROM " . $this->table . " {$this->join}{$break}WHERE 1=1 {$this->where}{$break}{$groupby}{$break}{$orderby}{$break}{$this->limits}";
    }
}
