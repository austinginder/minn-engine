<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\Content\Posts;
use Minn\Objects\Post;
use Minn\Query\AuthorPostsSql;
use Minn\Query\MimeWhere;
use Minn\Support\Lists;
use Minn\Support\Url;

/**
 * What WordPress's post functions tell about posts: a page's path and a
 * page by its path, a post's type, status and fields, whether it can be
 * viewed, when posts last changed, posts by query arguments and a post's
 * children and categories, the SQL that limits a query to what an author
 * may read, and the caches primed for a list of posts.
 */
final class PostReads
{
    /** A page's path from its ancestors' slugs (get_page_uri), or false. */
    public static function pageUri(mixed $page = 0): mixed
    {
        $page = $page instanceof Post ? $page : Records::post($page);
        if ($page === null) {
            return false;
        }
        $uri = $page->post_name;
        foreach (TermLookup::postAncestors($page) as $parent) {
            $ancestor = Records::post($parent);
            if ($ancestor !== null && $ancestor->post_name !== '') {
                $uri = $ancestor->post_name . '/' . $uri;
            }
        }
        return Runtime::hooks()->filter('get_page_uri', [$uri, $page]);
    }

    /** A page by its path (get_page_by_path): one type looks among attachments too; a list looks among exactly its types. */
    public static function pageByPath(mixed $path, mixed $output = 'OBJECT', mixed $type = 'page'): mixed
    {
        $segments = array_values(array_filter(explode('/', trim((string) $path, '/')), static fn (string $s): bool => $s !== ''));
        if ($segments === []) {
            return null;
        }
        $types = is_array($type) ? array_values(array_map('strval', $type)) : [(string) $type, 'attachment'];
        $found = self::store()->byTypedPath($segments, $types);
        return $found === null ? null : Records::post($found->id, $output);
    }

    /** A post's type (get_post_type), or false. */
    public static function type(mixed $post = null): mixed
    {
        $post = Records::post($post);
        return $post === null ? false : $post->post_type;
    }

    /** A post's status (get_post_status): an attachment's is its parent's (a trashed parent's from before the trash); false for none. */
    public static function status(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $status = $post->post_status;
        if ($post->post_type === 'attachment' && $status === 'inherit') {
            $parent = $post->post_parent > 0 ? Records::post($post->post_parent) : null;
            $status = $parent === null ? 'publish' : ($parent->post_status === 'trash' ? MetaData::get('post', $parent->ID, '_wp_trash_meta_status', true) ?: 'publish' : $parent->post_status);
        }
        return Runtime::hooks()->filter('get_post_status', [$status, $post]);
    }

    /** One of a post's fields in a context (get_post_field), empty when there is none. */
    public static function field(mixed $field, mixed $post = null, mixed $context = 'display'): mixed
    {
        $post = Records::post($post);
        if ($post === null || !isset($post->{$field})) {
            return '';
        }
        return PostFields::value((string) $field, $post->{$field}, $post->ID, (string) $context);
    }

    /** Whether a post can be viewed by anyone (is_post_publicly_viewable): its type and its status both viewable. */
    public static function publiclyViewable(mixed $post = null): bool
    {
        $post = Records::post($post);
        return $post !== null && Registered::viewableType($post->post_type) && Registered::viewableStatus(self::status($post));
    }

    /**
     * When a public post was last published or changed (_get_last_post_time),
     * in GMT, the site's time or the server's (with microseconds); false for
     * none or a question it cannot answer.
     */
    public static function lastTime(mixed $timezone, mixed $field, mixed $type = 'any'): mixed
    {
        $zone = strtolower((string) $timezone);
        if (!in_array($zone, ['gmt', 'blog', 'server'], true) || !in_array($field, ['date', 'modified'], true) || !is_string($type)
            || ($type !== 'any' && !in_array($type, Registered::postTypes(['public' => true]), true))) {
            return false;
        }
        $types = $type === 'any' ? array_values(Registered::postTypes(['public' => true])) : [(string) $type];
        $value = self::store()->newest($types, "post_{$field}" . ($zone === 'blog' ? '' : '_gmt'));
        if ($value === null) {
            return false;
        }
        return $zone === 'server' ? $value . '.000000' : $value;
    }

    /** When a public post was last published (get_lastpostdate), as get_lastpostdate leaves it. */
    public static function lastDate(mixed $timezone = 'server', mixed $type = 'any'): mixed
    {
        return Runtime::hooks()->filter('get_lastpostdate', [self::lastTime($timezone, 'date', $type), $timezone, $type]);
    }

    /** When a public post last changed or was published, the later (get_lastpostmodified), pre_get_lastpostmodified answering first. */
    public static function lastModified(mixed $timezone = 'server', mixed $type = 'any'): mixed
    {
        $modified = Runtime::hooks()->filter('pre_get_lastpostmodified', [false, $timezone, $type]);
        if ($modified !== false) {
            return $modified;
        }
        $modified = self::lastTime($timezone, 'modified', $type);
        $date = self::lastDate($timezone, $type);
        return Runtime::hooks()->filter('get_lastpostmodified', [$date > $modified ? $date : $modified, $timezone, $type]);
    }

    /**
     * Posts by query arguments (get_posts): five published posts by date
     * unless asked otherwise, numberposts read as posts_per_page, category,
     * include and exclude read as the query's own, stickies ignored, no rows
     * counted.
     *
     * @return array<int, mixed>
     */
    public static function query(mixed $args = null): mixed
    {
        $defaults = ['numberposts' => 5, 'category' => 0, 'orderby' => 'date', 'order' => 'DESC', 'include' => [], 'exclude' => [], 'meta_key' => '', 'meta_value' => '', 'post_type' => 'post', 'suppress_filters' => true];
        $parsed = Lists::args($args, $defaults);
        $parsed = array_replace($parsed, self::filledArguments($parsed));
        $parsed = array_replace($parsed, self::chosenPosts($parsed), ['ignore_sticky_posts' => true, 'no_found_rows' => true]);
        unset($parsed['numberposts'], $parsed['category'], $parsed['include'], $parsed['exclude']);
        return Handoff::newQuery()->query($parsed);
    }

    /**
     * The query arguments get_posts fills from its own: a status when none
     * is given (inherit for attachments, publish for the rest), the page
     * size from numberposts when no posts_per_page is, the category as cat.
     *
     * @param array<array-key, mixed> $parsed
     * @return array<string, mixed>
     */
    private static function filledArguments(array $parsed): array
    {
        ['post_type' => $type, 'post_status' => $status, 'numberposts' => $count, 'posts_per_page' => $perPage, 'category' => $category] = $parsed + ['post_status' => null, 'posts_per_page' => null];
        $filled = [
            'post_status' => empty($status) ? ($type === 'attachment' ? 'inherit' : 'publish') : null,
            'posts_per_page' => empty($perPage) && !empty($count) ? $count : null,
            'cat' => empty($category) ? null : $category,
        ];
        return array_filter($filled, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The posts get_posts names by id: the included ones, the page as large
     * as their number; else the ones left out; else none.
     *
     * @param array<array-key, mixed> $parsed
     * @return array<string, mixed>
     */
    private static function chosenPosts(array $parsed): array
    {
        ['include' => $include, 'exclude' => $exclude] = $parsed;
        if (empty($include)) {
            return empty($exclude) ? [] : ['post__not_in' => self::idList($exclude)];
        }
        $ids = self::idList($include);
        return ['posts_per_page' => count($ids), 'post__in' => $ids];
    }

    /**
     * A post's children (get_children), the post being read's when none is
     * named, keyed by id, as objects or arrays.
     *
     * @return array<int, mixed>
     */
    public static function children(mixed $args = '', mixed $output = 'OBJECT'): array
    {
        if (is_numeric($args) || is_object($args)) {
            $args = ['post_parent' => is_object($args) ? (int) $args->ID : (int) $args];
        }
        $parsed = Lists::args($args, ['post_parent' => 0, 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1]);
        ['post_parent' => $parent] = $parsed;
        if (empty($parent)) {
            $current = Records::post();
            if ($current === null) {
                return [];
            }
            $parsed = array_replace($parsed, ['post_parent' => $current->ID]);
        }
        $out = [];
        foreach (self::query($parsed) as $child) {
            $out[$child->ID] = $output === 'ARRAY_A' ? $child->to_array() : ($output === 'ARRAY_N' ? array_values($child->to_array()) : $child);
        }
        return $out;
    }

    /** A post's categories (wp_get_post_categories), their ids unless asked otherwise; empty on an error. */
    public static function categories(mixed $postId = 0, mixed $args = []): mixed
    {
        $terms = TermLookup::objectTerms((int) $postId, 'category', Lists::args($args, ['fields' => 'ids']));
        return Records::isError($terms) ? [] : $terms;
    }

    /** The WHERE clause for MIME types (wp_post_mime_type_where), a wildcard or a list. */
    public static function mimeTypeWhere(mixed $types, mixed $tableAlias = ''): string
    {
        return MimeWhere::sql(is_array($types) ? array_values($types) : (string) $types, (string) $tableAlias);
    }

    /**
     * The SQL limiting posts to what may be read (get_posts_by_author_sql): an
     * author's own, the published, and the private ones the reader may read.
     */
    public static function byAuthorSql(mixed $type, mixed $full = true, mixed $author = null, mixed $publicOnly = false): string
    {
        $types = [];
        foreach ((array) $type as $one) {
            $object = Registered::postType($one);
            if ($object) {
                $cap = Runtime::hooks()->filterDeprecated('pub_priv_sql_capability', [''], '3.2.0');
                $types[] = ['type' => (string) $one, 'readPrivate' => (bool) ($cap ?: Can::current($object->cap->read_private_posts))];
            }
        }
        return AuthorPostsSql::sql($types, Handoff::signedIn() ? (int) CurrentUser::id() : 0, $full ? 'full' : '', $author === null ? null : (int) $author, $publicOnly ? 'public' : '');
    }

    /** Posts read into the caches together (_prime_post_caches): rows, and their meta and terms unless asked not to. */
    public static function prime(mixed $ids, mixed $updateTermCache = true, mixed $updateMetaCache = true): void
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }
        $store = self::store();
        $store->primeRows($ids);
        if ($updateMetaCache) {
            $store->primeMeta($ids);
        }
        if ($updateTermCache) {
            $types = [];
            foreach ($ids as $id) {
                $types[(string) ($store->find($id)?->type ?? '')] = true;
            }
            $taxonomies = [];
            foreach (array_keys(array_filter($types, static fn (mixed $on, mixed $type): bool => $type !== '', ARRAY_FILTER_USE_BOTH)) as $type) {
                $taxonomies = [...$taxonomies, ...Registered::objectTaxonomies((string) $type)];
            }
            $store->primeTerms($ids, array_values(array_unique($taxonomies)));
        }
    }

    /** A list of posts kept in the caches (update_post_caches): whole posts kept as they are, then primed. */
    public static function updateCaches(mixed &$posts, mixed $type = 'post', mixed $updateTermCache = true, mixed $updateMetaCache = true): void
    {
        if (!$posts) {
            return;
        }
        self::store()->rememberRows(array_map(static fn (object $post): array => get_object_vars($post), array_values(array_filter((array) $posts, static fn (mixed $post): bool => $post instanceof Post))));
        self::prime(array_map(static fn (mixed $post): int => is_object($post) ? (int) ($post->ID ?? 0) : (int) $post, (array) $posts), $updateTermCache, $updateMetaCache);
    }

    /**
     * The post an address names (url_to_postid): a ?p= or ?page_id= id, else
     * with pretty permalinks a page by its path, a post whose own address it
     * is, or an attachment by its own; 0 for none.
     */
    public static function idFromUrl(mixed $url): int
    {
        $url = (string) preg_replace('/#.*$/', '', (string) Runtime::hooks()->filter('url_to_postid', [(string) $url]));
        $home = SiteUrls::home();
        if (preg_match('/[?&]p=(\d+)/', $url, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/[?&]page_id=(\d+)/', $url, $m)) {
            return (int) $m[1];
        }
        if (str_starts_with($url, 'http')) {
            if (!str_starts_with($url, $home)) {
                return 0;
            }
            $url = substr($url, strlen($home));
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return 0;
        }
        $permalinks = Runtime::current()->get('permalinks');
        if ($permalinks !== null) {
            $segments = explode('/', $path);
            $store = self::store();
            $page = $store->pageByPathAnyStatus($segments);
            if ($page !== null) {
                return $page->id;
            }
            $post = $store->findByNameAnyStatus(end($segments), ['post']);
            if ($post !== null && $permalinks->forPost($post) === rtrim($home, '/') . '/' . $path . '/') {
                return $post->id;
            }
            $attachment = $store->findByNameAnyStatus(end($segments), ['attachment']);
            if ($attachment !== null && Url::withoutTrailingSlash((string) PostLinks::permalink($attachment->id)) === rtrim($home, '/') . '/' . $path) {
                return $attachment->id;
            }
        }
        return 0;
    }

    /** @return array<int, int> ids from a list or a string, each once, as positive integers */
    private static function idList(mixed $list): array
    {
        return array_unique(array_map(static fn (mixed $id): int => abs((int) $id), is_array($list) ? $list : Lists::items($list)));
    }

    private static function store(): Posts
    {
        return new Posts(Runtime::current()->db);
    }
}
