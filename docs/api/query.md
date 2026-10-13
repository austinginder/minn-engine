# `Minn\Query`

shared SQL fragments

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AuthorPostsSql`](#authorpostssql) | final class | 30 | get_posts_by_author_sql as the reference writes it: for each type, its |
| [`CommentOrder`](#commentorder) | final class | 100 | WP_Comment_Query's ORDER BY and LIMIT as the reference writes them |
| [`DateSql`](#datesql) | final class | 303 | The WHERE fragment of a date query in the reference's shape: before and |
| [`MetaSql`](#metasql) | final class | 291 | The JOIN and WHERE fragments of a meta query in the reference's shape: |
| [`MimeWhere`](#mimewhere) | final class | 36 | wp_post_mime_type_where as the reference writes it (probe wp-query-sql): |
| [`NetworkSql`](#networksql) | final class | 257 | The SQL WP_Site_Query and WP_Network_Query build from their variables |
| [`PostOrder`](#postorder) | final class | 122 | WP_Query's ORDER BY as the reference writes it (probe wp-query-sql): the |
| [`PostSearch`](#postsearch) | final class | 118 | WP_Query's search as the reference writes it (probe wp-query-sql): the |
| [`Sql`](#sql) | final class | 42 | Literal quoting for the SQL fragments the query classes hand to plugins, |
| [`TaxSql`](#taxsql) | final class | 197 | The JOIN and WHERE fragments of a taxonomy query in the reference's |

## AuthorPostsSql

`final class Minn\Query\AuthorPostsSql` · `public/minn/src/Minn/Query/AuthorPostsSql.php`

get_posts_by_author_sql as the reference writes it: for each type, its
published posts, and its private ones the reader may see (all of them
with the cap to read others', their own otherwise), OR'd; an author
narrows it; WHERE in front when asked.

### static `sql(array $types, int $reader, string $full, ?int $author, string $publicOnly): string`

The clause for the posts an author's lists count, as the reader may see them.

- `@param list<array{type: string, readPrivate: bool}> $types each type that exists, and whether the reader may read its private posts`


## CommentOrder

`final class Minn\Query\CommentOrder` · `public/minn/src/Minn/Query/CommentOrder.php`

WP_Comment_Query's ORDER BY and LIMIT as the reference writes them
(probe wp-comment-query-sql): no orderby means comment_date_gmt in the
order asked; a list, a string of keys or a map of keys to directions
each become a column, a meta value or the FIELD() list comment__in
gives; a key it does not know is dropped (none left means
comment_date_gmt), and unless comment_ID or comment__in is among them a
comment_ID tiebreak follows, in the first date clause's direction or
DESC.

- const `COLUMNS` = `array (   0 => 'comment_agent',   1 => 'comment_approved',   2 => 'comment_author',   3 => 'comment_author_email',   4 => 'comment_author_IP',   5 => 'comment_author_url',   6 => 'comment_content',   7 => 'comment_date',   8 => 'comment_date_gmt',   9 => 'comment_ID',   10 => 'comment_karma',   11 => 'comment_parent',   12 => 'comment_post_ID',   13 => 'comment_type',   14 => 'user_id', )`
- const `IDS` = `'comment__in'`

Used by: `Minn\Runtime\CommentQueryRunner`

### static `direction(mixed $order): string`

ASC when asked for, DESC for anything else.

### static `build(array $qv, string $table, string $metaTable, array $metaClauses): string`

The ORDER BY body for the query's orderby and order ('' for none).

- `@param array<string, mixed> $qv`
- `@param array<array-key, array<string, mixed>> $metaClauses the meta query's clauses, by name`

### static `clause(string $by, array $qv, string $table, string $metaTable, array $metaClauses): string`

One orderby key as SQL, or '' when the reference does not take it: a
column, the meta value (as a number when asked), a named meta
clause's cast value, or the FIELD() list keeping comment__in's order.

- `@param array<string, mixed> $qv`
- `@param array<array-key, array<string, mixed>> $metaClauses`

### static `limits(array $qv): string`

LIMIT from number, and the offset (or the page when no offset is given). @param array<string, mixed> $qv

- `@param array<string, mixed> $qv`

Internals: `field()` (private, line 102), `tiebreak()` (private, line 109)


## DateSql

`final class Minn\Query\DateSql` · `public/minn/src/Minn/Query/DateSql.php`

The WHERE fragment of a date query in the reference's shape: before and
after bounds as full datetimes (inclusive bounds close the day), the
date parts as MySQL functions compared or listed, groups joined by
their relation, columns validated against the known date columns.

- const `COLUMNS` = `array (   'posts' =>    array (     0 => 'post_date',     1 => 'post_date_gmt',     2 => 'post_modified',     3 => 'post_modified_gmt',   ),   'comments' =>    array (     0 => 'comment_date',     1 => 'comment_date_gmt',   ),   'users' =>    array (     0 => 'user_registered',   ),   'blogs' =>    array (     0 => 'registered',     1 => 'last_updated',   ), )`
- const `PART_ORDER` = `array (   0 => 'year',   1 => 'month',   2 => 'monthnum',   3 => 'week',   4 => 'w',   5 => 'dayofyear',   6 => 'day',   7 => 'dayofweek',   8 => 'dayofweek_iso',   9 => 'hour',   10 => 'minute',   11 => 'second', )`
- const `RANGES` = `array (   'dayofyear' =>    array (     0 => 1,     1 => 366,   ),   'dayofweek' =>    array (     0 => 1,     1 => 7,   ),   'dayofweek_iso' =>    array (     0 => 1,     1 => 7,   ),   'month' =>    array (     0 => 1,     1 => 12,   ),   'week' =>    array (     0 => 1,     1 => 53,   ),   'day' =>    array (     0 => 1,     1 => 31,   ),   'hour' =>    array (     0 => 0,     1 => 23,   ),   'minute' =>    array (     0 => 0,     1 => 59,   ),   'second' =>    array (     0 => 0,     1 => 59,   ), )` — The ranges a date part is checked against, in the order the reference reports them (week's top depends on the year).

```php
__construct(array $tables, string $defaultColumn = 'post_date', int $startOfWeek = 1)
```
- `@param array<string, string> $tables table key to prefixed table name`


### static `week(string $column, int $startOfWeek): string`

The week of a column as the reference counts it from the site's first day of the week.

### static `validate(array $clause): bool`

Whether a clause's date values are valid, with a notice for each part
out of range (week up to the given year's ISO weeks, 53 without one)
and for a month and day that make no date (in a leap year when no year
is given, and only once every part is in range); before and after given
as parts are checked too (probes
query-clauses, query-clause-internals). An empty clause is not valid.

### `sanitize(array $queries, array $defaults): array`

Sanitises to the reference's shape: every level carries column, compare and relation.

### static `isFirstOrder(array $query): bool`

Whether a date query is one clause rather than a group of them.

### `validateColumn(string $column): string`

A column name as "table.column", the default for anything unknown.

### `checkColumnsWith(Closure $validColumns): self`

Has the valid columns pass through a filter (WP_Date_Query hands it date_query_valid_columns).

### `build(array $queries): string`

The WHERE fragment for a date query.

### `forQuery(array $queries, int $depth): array`

A query or group as the reference's get_sql_for_query returns it, indented for its depth. @return array{join: string, where: string}

- `@return array{join: string, where: string}`

### `clauseParts(array $clause): array`

One clause's pieces, before they are joined: the bounds, the date parts
in order (a part whose value builds to nothing, or to 0, is left out),
then the time.

- `@return list<string>`

### static `buildValue(string $compare, mixed $value): string|int|false`

A value as the comparison wants it (build_value): a list of numbers
for IN, exactly two for BETWEEN, one number otherwise; false when the
value does not fit.

### static `buildTime(string $column, string $compare, mixed $hour, mixed $minute, mixed $second): string|false`

The time of day (build_time_query): listed or ranged parts each on
their own; a single part by its function; an hour with a second and no
minute not at all; otherwise the parts as one decimal, DATE_FORMAT( col,
'%H.%i%s' ). False when no part is given.

### static `datetime(mixed $value, bool $endOfUnit): string`

A full datetime from a string or a parts array. A string as precise as
a year, a month, a day or a minute is read as those parts, so an end
bound reaches the end of its unit; any other string is read as a time
in the site's zone. Missing parts start (or end) the unit.

### static `compare(string $compare): string`

A comparison upper-cased, = for anything the reference does not know.

Internals: `validDate()` (private, line 84), `partExpressions()` (private, line 255), `group()` (private, line 263), `clause()` (private, line 277)


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

### `sqlClauses(array $queries): array`

The whole query's JOIN and WHERE (get_sql_clauses), each clause given its alias in place. @return array{join: string, where: string}

- `@return array{join: string, where: string}`

### `querySql(array $queries, int $depth): array`

A group's JOIN and WHERE (get_sql_for_query), indented for its depth;
each clause is given its alias in place, so its siblings can share it.

- `@return array{join: string, where: string}`

### `clauseSql(array $clause, array $parent, string $key): array`

One clause's JOIN and WHERE pieces (get_sql_for_clause): the clause is
settled in place (compare, compare_key, then its alias and cast), its
alias shared with a compatible sibling or a new join.

- `@return array{where: list<string>, join: list<string>}`

### `filterAliasesWith(Closure $filter): self`

Has every clause's shared alias (or false) pass through a filter (WP_Meta_Query hands it meta_query_find_compatible_table_alias).

### `compatibleAlias(array $clause, array $parent): string|false`

The alias of a sibling the clause can share (find_compatible_table_alias):
under OR, the first sibling already given an alias whose comparison,
like the clause's, reads the same join; false otherwise.

### `clauses(): array`

The clauses the last build resolved, by name.

- `@return array<string, array{alias: string, cast: string}> clauses seen while building, by name or alias`

### static `hasOr(array $queries): bool`

Whether any level of the query relates two or more clauses by OR.

### static `cast(string $type): string`

The CAST target for a clause type; CHAR means no cast.

Internals: `normalized()` (private, line 80), `hasOrWithNotExists()` (private, line 199), `clause()` (private, line 232), `keyClause()` (private, line 250), `valueClause()` (private, line 262), `values()` (private, line 274), `newAlias()` (private, line 283)


## MimeWhere

`final class Minn\Query\MimeWhere` · `public/minn/src/Minn/Query/MimeWhere.php`

wp_post_mime_type_where as the reference writes it (probe wp-query-sql):
each mime type (a list or a comma-separated string) cleaned to a pattern,
a bare group or a star widened with % into a LIKE, an exact type an
equality, all OR'd; an empty type or a lone wildcard means no clause.

- const `WILDCARDS` = `array (   0 => '',   1 => '%',   2 => '%/%', )`

### static `sql(array|string $types, string $alias = ''): string`

The clause for some mime types, against a table's column when one is named. @param string|list<string> $types

- `@param string|list<string> $types`

Internals: `pattern()` (private, line 37)


## NetworkSql

`final class Minn\Query\NetworkSql` · `public/minn/src/Minn/Query/NetworkSql.php`

The SQL WP_Site_Query and WP_Network_Query build from their variables
(probe multisite-classes). A site query names only blog_id through its
table (blogs); every other column stands bare. A network query names each
column through its table (site). On one site both tables are unnamed, so
the column is written after a bare dot, as the reference writes it.

- const `SITE_ORDER` = `array (   'domain' => 'domain',   'last_updated' => 'last_updated',   'path' => 'path',   'registered' => 'registered',   'deleted' => 'deleted',   'spam' => 'spam',   'mature' => 'mature',   'archived' => 'archived',   'public' => 'public',   'network_id' => 'site_id',   'domain_length' => 'CHAR_LENGTH(domain)',   'path_length' => 'CHAR_LENGTH(path)', )` — The columns a site query orders by, as each is written; blog_id is the table's own.
- const `SITE_FLAGS` = `array (   'archived' => '%s',   'mature' => '%d',   'spam' => '%d',   'deleted' => '%d',   'public' => '%d',   'lang_id' => '%d', )` — The site flags as they are compared: archived as text, the rest as numbers, each followed by a space.

```php
__construct(object $wpdb)
```


### `siteWhere(array $qv, ?Closure $columns = NULL): array`

A site query's conditions, in the reference's order: the ids, the
networks, domains, paths, the flags, the languages, then the search
(across the columns asked for, as $columns lets plugins change them).

- `@param array<string, mixed> $qv`
- `@param (\Closure(list<string>): list<string>)|null $columns`
- `@return list<string>`

### `networkWhere(array $qv): array`

A network query's conditions: the ids, domains, paths, then the search.

- `@param array<string, mixed> $qv`
- `@return list<string>`

### `siteSearch(string $search, array $columns): string`

A site query's search across columns, each a LIKE on the term
anywhere, its stars wildcards.

- `@param list<string> $columns`

### `networkSearch(string $search, array $columns): string`

A network query's search: the same LIKEs, its stars kept.

- `@param list<string> $columns`

### `siteOrderby(array $qv): string`

A site query's ORDER BY, or '' (an unknown column is passed over). @param array<string, mixed> $qv

- `@param array<string, mixed> $qv`

### `siteOrderColumn(string $by, array $qv): ?string`

One column a site query orders by, as written; null for one it does not. @param array<string, mixed> $qv

- `@param array<string, mixed> $qv`

### `networkOrderby(array $qv): string`

A network query's ORDER BY, or ''. @param array<string, mixed> $qv

- `@param array<string, mixed> $qv`

### `networkOrderColumn(string $by, array $qv): ?string`

One column a network query orders by, as written; null for one it does not. @param array<string, mixed> $qv

- `@param array<string, mixed> $qv`

### static `direction(mixed $order): string`

ASC when asked for, in any case; anything else is DESC.

### static `limits(array $qv): string`

The LIMIT for a number and an offset; none without a number. @param array<string, mixed> $qv

- `@param array<string, mixed> $qv`

### static `pieces(array $clauses, string $table, string $found): array`

The request from its clauses, joined as the reference joins them.

- `@param array<string, string> $clauses fields, join, where, orderby, limits, groupby`
- `@return array<string, string> the pieces kept on the query`

### `ids(string $request): array`

The ids a request finds. @return list<int>

- `@return list<int>`

### `count(string $request): int`

The count a counting request reads.

### `rowById(string $table, string $column, int $id): ?object`

A table's row by its id, or null when there is none.

### `found(string $sql): int`

The rows a counted request found.

Internals: `likeAny()` (private, line 110), `orderby()` (private, line 213), `field()` (private, line 236), `ints()` (private, line 242), `strings()` (private, line 254), `list()` (private, line 266)


## PostOrder

`final class Minn\Query\PostOrder` · `public/minn/src/Minn/Query/PostOrder.php`

WP_Query's ORDER BY as the reference writes it (probe wp-query-sql): the
keys it takes, what each key becomes (a column, a meta value, a FIELD()
list, a seeded RAND()), and how a list or a map of keys joins with its
directions. A key it does not know is dropped; none left means post_date.

- const `FIELDS` = `array (   0 => 'name',   1 => 'author',   2 => 'date',   3 => 'title',   4 => 'modified',   5 => 'parent',   6 => 'type', )` — The post fields an orderby may name, bare or with their column's post_ prefix.
- const `OTHERS` = `array (   0 => 'ID',   1 => 'menu_order',   2 => 'comment_count',   3 => 'rand',   4 => 'post__in',   5 => 'post_parent__in',   6 => 'post_name__in', )` — The other names an orderby may use.
- const `COLUMNS` = `array (   0 => 'post_name',   1 => 'post_author',   2 => 'post_date',   3 => 'post_title',   4 => 'post_modified',   5 => 'post_parent',   6 => 'post_type',   7 => 'ID',   8 => 'menu_order',   9 => 'comment_count', )`
- const `IN_GIVEN_ORDER` = `array (   0 => 'post__in',   1 => 'post_name__in',   2 => 'post_parent__in', )`

Used by: `Minn\Runtime\PostQuery`

### static `direction(mixed $order): string`

ASC when asked for, DESC for anything else.

### static `build(array $q, string $table, array $metaClauses): string`

The ORDER BY body for the query's orderby and order, settling both in
$q as the reference leaves them (a random or a given-order sort has no
direction; a string orderby is url-decoded and slashed).

- `@param array<string, mixed> $q`
- `@param array<array-key, array<string, mixed>> $metaClauses the meta query's clauses, by name`

### static `clause(string $orderby, string $table, array $metaClauses, array $q): string|false`

One orderby key as SQL, or false when the reference does not take it:
a column, RAND() (seeded when asked), the primary meta clause's value
(cast when it has a type), a named meta clause's cast value, or the
FIELD() list that keeps post__in, post_name__in or post_parent__in in
the order given.

- `@param array<array-key, array<string, mixed>> $metaClauses`
- `@param array<string, mixed> $q`

Internals: `map()` (private, line 67), `field()` (private, line 121)


## PostSearch

`final class Minn\Query\PostSearch` · `public/minn/src/Minn/Query/PostSearch.php`

WP_Query's search as the reference writes it (probe wp-query-sql): the
search string split into terms (quoted phrases kept whole, single letters
and stopwords dropped, ten or more terms or none left searched as one
sentence), each term a group of LIKEs over the searched columns (NOT LIKE
and AND for an excluded term), the password clause for a visitor, and the
relevance order: a CASE ladder for several terms, the title match first
for one.

- const `COLUMNS` = `array (   0 => 'post_title',   1 => 'post_excerpt',   2 => 'post_content', )`
- const `STOPWORDS` = `'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www'`
- const `TERM` = `'/".*?("|$)|((?<=[\\t ",+])|^)[^\\t ",+]+/'`

Used by: `Minn\Runtime\PostQuery`

### static `terms(string $search, callable $stopwords): array`

The terms a search string becomes and how many it was split into.

- `@param callable(): list<string> $stopwords asked only when the string splits`
- `@return array{terms: list<string>, count: int}`

### static `checked(array $terms, array $stopwords): array`

The terms worth searching: quotes trimmed (a quoted term keeps its
inner spaces), and a single letter or dash, or a stopword, dropped.

- `@param list<string> $terms`
- `@param list<string> $stopwords`
- `@return list<string>`

### static `stopwords(string $list): array`

The stopword list from its comma-separated (translated) form.

- `@return list<string>`

### static `columns(array $given): array`

The searched columns: those given that the reference searches, or all three.

- `@return list<string>`

### static `where(string $table, array $terms, array $columns, string $exclusionPrefix, string $wild): array`

The search's WHERE fragment (no password clause; a visitor's search
adds one) and the title matches its order ranks by. An exact search
passes no wildcard, and asks for no title matches.

- `@param list<string> $terms`
- `@param list<string> $columns each with its table, as the clause names it`
- `@return array{where: string, titles: list<string>}`

### static `order(string $table, string $search, int $count, array $titles): string`

The relevance order: for several terms, the whole string in the title,
then every term, then any term (up to six), then the whole string in the
excerpt and the content (no sentence match when a term is negated); for
one term, its title match.

- `@param list<string> $titles`


## Sql

`final class Minn\Query\Sql` · `public/minn/src/Minn/Query/Sql.php`

Literal quoting for the SQL fragments the query classes hand to plugins,
which embed them verbatim in their own statements.

Used by: `Minn\Query\DateSql`, `Minn\Query\MetaSql`, `Minn\Query\PostSearch`, `Minn\Query\TaxSql`, `Minn\Runtime\CommentQueryWhere`, `Minn\Runtime\PostQueryStatus`, `Minn\Runtime\PostQueryWhere`, `Minn\Runtime\TermQueryRunner`, `Minn\Runtime\UserQueryRunner`

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

The query as the reference keeps it: each clause merged over the
defaults (taxonomy, terms as a list, field term_id, operator IN,
children included), a nested group given relation AND when it has
none, an empty group dropped. The top level keeps a relation only when
one was given.

- `@param array<array-key, mixed> $queries`
- `@return array<array-key, mixed>`

### static `isFirstOrder(mixed $query): bool`

Whether a query part is a clause (it names a clause key, or is empty) rather than a group.

### static `queried(array $queries, array $queried = array ( )): array`

The terms a sanitized query asks for, by taxonomy: the first terms and
the first field each taxonomy's clauses give, NOT IN clauses aside.

- `@param array<array-key, mixed> $queries`
- `@return array<string, array{terms?: list<mixed>, field?: string}>`

### `build(array $queries): array`

The JOIN and WHERE fragments for a taxonomy query.

- `@return array{join: string, where: string}`

### `sqlClauses(array $queries): array`

The whole query's JOIN and WHERE (get_sql_clauses), each clause settled in place. @return array{join: string, where: string}

- `@return array{join: string, where: string}`

### `querySql(array $queries, int $depth): array`

A group's JOIN and WHERE (get_sql_for_query), indented for its depth. @return array{join: string, where: string}

- `@return array{join: string, where: string}`

### `clauseSql(array $clause, array $parent): array`

One clause's JOIN and WHERE pieces (get_sql_for_clause). Its terms are
turned into term_taxonomy_ids in place; an IN clause reads
term_relationships under an alias it shares with a compatible sibling
or joins anew, and keeps that alias. A term nobody has yields "0 = 1"
(nothing at all for NOT IN).

- `@return array{where: list<string>, join: list<string>}`

### `compatibleAlias(array $clause, array $parent): string|false`

The alias of an IN sibling the clause can share under OR (find_compatible_table_alias), or false.

Internals: `operator()` (private, line 194), `inClause()` (private, line 201)

