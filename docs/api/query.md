# `Minn\Query`

shared SQL fragments

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`DateSql`](#datesql) | final class | 155 | The WHERE fragment of a date query in the reference's shape: before and |
| [`MetaSql`](#metasql) | final class | 233 | The JOIN and WHERE fragments of a meta query in the reference's shape: |
| [`Sql`](#sql) | final class | 26 | Literal quoting for the SQL fragments the query classes hand to plugins, |
| [`TaxSql`](#taxsql) | final class | 133 | The JOIN and WHERE fragments of a taxonomy query in the reference's |

## DateSql

`final class Minn\Query\DateSql` · `public/minn/src/Minn/Query/DateSql.php`

The WHERE fragment of a date query in the reference's shape: before and
after bounds as full datetimes (inclusive bounds close the day), the
date parts as MySQL functions compared or listed, groups joined by
their relation, columns validated against the known date columns.

- const `COLUMNS` = `array (   'posts' =>    array (     0 => 'post_date',     1 => 'post_date_gmt',     2 => 'post_modified',     3 => 'post_modified_gmt',   ),   'comments' =>    array (     0 => 'comment_date',     1 => 'comment_date_gmt',   ),   'users' =>    array (     0 => 'user_registered',   ),   'blogs' =>    array (     0 => 'registered',     1 => 'last_updated',   ), )`
- const `PARTS` = `array (   'year' => 'YEAR',   'month' => 'MONTH',   'monthnum' => 'MONTH',   'week' => 'WEEK',   'w' => 'WEEK',   'dayofyear' => 'DAYOFYEAR',   'day' => 'DAYOFMONTH',   'dayofweek' => 'DAYOFWEEK',   'dayofweek_iso' => 'WEEKDAY',   'hour' => 'HOUR',   'minute' => 'MINUTE',   'second' => 'SECOND', )`
- const `PART_ORDER` = `array (   0 => 'year',   1 => 'month',   2 => 'monthnum',   3 => 'week',   4 => 'w',   5 => 'dayofyear',   6 => 'day',   7 => 'dayofweek',   8 => 'dayofweek_iso',   9 => 'hour',   10 => 'minute',   11 => 'second', )`

```php
__construct(array $tables, string $defaultColumn = 'post_date')
```
- `@param array<string, string> $tables table key to prefixed table name`


### `sanitize(array $queries, array $defaults): array`

Sanitises to the reference's shape: every level carries column, compare and relation.

### static `isFirstOrder(array $query): bool`

### `validateColumn(string $column): string`

A column name as "table.column", the default for anything unknown.

### `build(array $queries): string`

### static `datetime(mixed $value, bool $endOfUnit): string`

A full datetime from a string or a parts array; a parts array rounds up to the end of its unit when asked.

Internals: `group()` (private, line 91), `clause()` (private, line 109), `part()` (private, line 133), `compare()` (private, line 162)


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

- `@return array{join: string, where: string}`

### `clauses(): array`

- `@return array<string, array{alias: string, cast: string}> clauses seen while building, by name or alias`

### static `hasOr(array $queries): bool`

### static `cast(string $type): string`

The CAST target for a clause type; CHAR means no cast.

Internals: `hasOrWithNotExists()` (private, line 99), `group()` (private, line 130), `clause()` (private, line 157), `keyClause()` (private, line 178), `valueClause()` (private, line 190), `values()` (private, line 202), `alias()` (private, line 211)


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

### `build(array $queries): array`

- `@return array{join: string, where: string}`

### `queriedTerms(): array`

- `@return array<string, array{terms: list<mixed>, field: string}> the terms asked for, by taxonomy`

Internals: `group()` (private, line 85), `clause()` (private, line 111), `inClause()` (private, line 135)

