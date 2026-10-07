# `Minn\Query`

shared SQL fragments

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`DateSql`](#datesql) | final class | 248 | The WHERE fragment of a date query in the reference's shape: before and |
| [`MetaSql`](#metasql) | final class | 252 | The JOIN and WHERE fragments of a meta query in the reference's shape: |
| [`Sql`](#sql) | final class | 42 | Literal quoting for the SQL fragments the query classes hand to plugins, |
| [`TaxSql`](#taxsql) | final class | 140 | The JOIN and WHERE fragments of a taxonomy query in the reference's |

## DateSql

`final class Minn\Query\DateSql` · `public/minn/src/Minn/Query/DateSql.php`

The WHERE fragment of a date query in the reference's shape: before and
after bounds as full datetimes (inclusive bounds close the day), the
date parts as MySQL functions compared or listed, groups joined by
their relation, columns validated against the known date columns.

- const `COLUMNS` = `array (   'posts' =>    array (     0 => 'post_date',     1 => 'post_date_gmt',     2 => 'post_modified',     3 => 'post_modified_gmt',   ),   'comments' =>    array (     0 => 'comment_date',     1 => 'comment_date_gmt',   ),   'users' =>    array (     0 => 'user_registered',   ),   'blogs' =>    array (     0 => 'registered',     1 => 'last_updated',   ), )`
- const `PART_ORDER` = `array (   0 => 'year',   1 => 'month',   2 => 'monthnum',   3 => 'week',   4 => 'w',   5 => 'dayofyear',   6 => 'day',   7 => 'dayofweek',   8 => 'dayofweek_iso',   9 => 'hour',   10 => 'minute',   11 => 'second', )`
- const `RANGES` = `array (   'month' =>    array (     0 => 1,     1 => 12,   ),   'week' =>    array (     0 => 1,     1 => 53,   ),   'dayofyear' =>    array (     0 => 1,     1 => 366,   ),   'day' =>    array (     0 => 1,     1 => 31,   ),   'dayofweek' =>    array (     0 => 1,     1 => 7,   ),   'dayofweek_iso' =>    array (     0 => 1,     1 => 7,   ),   'hour' =>    array (     0 => 0,     1 => 23,   ),   'minute' =>    array (     0 => 0,     1 => 59,   ),   'second' =>    array (     0 => 0,     1 => 59,   ), )` — The ranges a date part is checked against, as the reference names them in its notice.

```php
__construct(array $tables, string $defaultColumn = 'post_date', int $startOfWeek = 1)
```
- `@param array<string, string> $tables table key to prefixed table name`


### static `week(string $column, int $startOfWeek): string`

The week of a column as the reference counts it from the site's first day of the week.

### static `validate(array $clause): void`

Notices for parts out of range and for a year, month and day that make no date (probe query-clauses).

### `sanitize(array $queries, array $defaults): array`

Sanitises to the reference's shape: every level carries column, compare and relation.

### static `isFirstOrder(array $query): bool`

Whether a date query is one clause rather than a group of them.

### `validateColumn(string $column): string`

A column name as "table.column", the default for anything unknown.

### `build(array $queries): string`

The WHERE fragment for a date query.

### static `datetime(mixed $value, bool $endOfUnit): string`

A full datetime from a string or a parts array. A string as precise as
a year, a month, a day or a minute is read as those parts, so an end
bound reaches the end of its unit; any other string is read as a time
in the site's zone. Missing parts start (or end) the unit.

Internals: `group()` (private, line 124), `clause()` (private, line 142), `value()` (private, line 178), `time()` (private, line 196), `compare()` (private, line 255)


## MetaSql

`final class Minn\Query\MetaSql` · `public/minn/src/Minn/Query/MetaSql.php`

The JOIN and WHERE fragments of a meta query in the reference's shape:
one meta-table join per clause (the first under the table's own name,
then mt1, mt2, ...), INNER joins unless an OR relation or a NOT EXISTS
clause needs LEFT ones, and casts by the clause's declared type. Under
an OR relation a clause shares an equality-shaped sibling's join.

- const `COMPATIBLE` = `array (   0 => '=',   1 => 'IN',   2 => 'BETWEEN',   3 => 'LIKE',   4 => 'REGEXP',   5 => 'RLIKE',   6 => '>',   7 => '>=',   8 => '<',   9 => '<=', )`
- const `OPERATORS` = `array (   0 => '=',   1 => '!=',   2 => '>',   3 => '>=',   4 => '<',   5 => '<=',   6 => 'LIKE',   7 => 'NOT LIKE',   8 => 'IN',   9 => 'NOT IN',   10 => 'BETWEEN',   11 => 'NOT BETWEEN',   12 => 'EXISTS',   13 => 'NOT EXISTS',   14 => 'REGEXP',   15 => 'NOT REGEXP',   16 => 'RLIKE', )`

```php
__construct(string $metaTable, string $objectColumn, string $primaryTable, string $primaryId)
```
- `@param array<string, array{0: string, 1: string}> $tables meta type to [table, object column]`


### static `sanitize(array $queries): array`

Normalises a raw meta_query: clauses get key/value/compare/type
defaults, a nested group its relation, and a "relation" key sits on
every level. Named clauses keep their names.

### static `isFirstOrder(array $query): bool`

A clause rather than a group: it names a key or a value.

### `build(array $queries): array`

The JOIN and WHERE fragments for a meta query.

- `@return array{join: string, where: string}`

### `clauses(): array`

The clauses the last build resolved, by name.

- `@return array<string, array{alias: string, cast: string}> clauses seen while building, by name or alias`

### static `hasOr(array $queries): bool`

Whether any level of the query relates two or more clauses by OR.

### static `cast(string $type): string`

The CAST target for a clause type; CHAR means no cast.

Internals: `normalized()` (private, line 79), `hasOrWithNotExists()` (private, line 120), `group()` (private, line 152), `clause()` (private, line 174), `keyClause()` (private, line 197), `valueClause()` (private, line 209), `values()` (private, line 221), `alias()` (private, line 230)


## Sql

`final class Minn\Query\Sql` · `public/minn/src/Minn/Query/Sql.php`

Literal quoting for the SQL fragments the query classes hand to plugins,
which embed them verbatim in their own statements.

Used by: `Minn\Query\DateSql`, `Minn\Query\MetaSql`, `Minn\Query\TaxSql`

### static `quote(string $value): string`

A quoted string literal.

### static `escape(string $value): string`

The reference's escaping: backslash, quotes, NUL, newlines, and the substitute character.

### static `like(string $value): string`

A LIKE operand with its wildcards escaped; quote() afterwards.

### static `list(array $values): string`

A comma list of quoted literals.

### static `group(array $chunks, string $relation, int $depth): string`

Clauses joined as the reference joins them at every depth (probe
query-clauses): wrapped in parentheses, each clause and the relation
on its own line, indented two spaces a level.

- `@param list<string> $chunks`


## TaxSql

`final class Minn\Query\TaxSql` · `public/minn/src/Minn/Query/TaxSql.php`

The JOIN and WHERE fragments of a taxonomy query in the reference's
shape: IN clauses join term_relationships (the first under the table's
own name, then tt1, tt2, ..., shared between IN siblings under OR),
NOT IN and AND read subqueries, EXISTS checks the taxonomy, and a term
nobody has yields "0 = 1".

```php
__construct(string $relationships, string $termTaxonomy, string $primaryTable, string $primaryId, Closure $termTaxonomyIds)
```
- `@param Closure(string, string, list<mixed>, bool): list<int> $termTaxonomyIds taxonomy, field, terms, include children`


### static `sanitize(array $queries): array`

The query with every clause in its canonical shape.

### `build(array $queries): array`

The JOIN and WHERE fragments for a taxonomy query.

- `@return array{join: string, where: string}`

### `queriedTerms(): array`

The terms the last build matched, by taxonomy.

- `@return array<string, array{terms: list<mixed>, field: string}> the terms asked for, by taxonomy`

Internals: `group()` (private, line 95), `clause()` (private, line 116), `inClause()` (private, line 142)

