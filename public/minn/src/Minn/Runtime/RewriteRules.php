<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * The rewrite rules WordPress makes from its structures, as the reference
 * makes them (probes rewrite-generate, rewrite-rules), so plugins that
 * read, filter or add to them see the same list.
 *
 * A structure is walked a directory at a time (one at a time only when
 * asked), each level that names something getting its rules: its feeds
 * and embed, its pages, its comment pages (for posts and pages), a static
 * front page's comment pages at the root, the endpoints placed there, and
 * the level itself; deepest level first. The level that names a post (a
 * name, an id, a page path, or a plugin post type's own tag) is the post's:
 * its embed and trackback, its feeds, pages and comment pages, its
 * endpoints and its attachments', then the post with its page number,
 * between the rules for attachments under it (by /attachment/ and, but for
 * a hierarchical type, directly). A tag nobody registered stays as written.
 *
 * The full list is the plugins' top rules (each permastruct's rules join
 * them as they are made, and stay), the fixed files, then root, comments,
 * search, author and dates, then pages and posts (posts first unless the
 * structure begins ambiguously), then the plugins' bottom rules; each
 * section through its filter, the whole through generate_rewrite_rules and
 * rewrite_rules_array.
 */
final class RewriteRules
{
    /** What a permastruct's flags default to (no endpoint places). */
    private const DEFAULTS = ['ep_mask' => 0, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true];

    /** The six date and time tags that together name a post. */
    private const POST_BY_TIME = ['%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%'];

    /**
     * generate_rewrite_rules: the rules for one structure.
     *
     * @param array{struct: string, ep_mask?: int, paged?: bool, feed?: bool, forcomments?: bool, walk_dirs?: bool, endpoints?: bool} $args
     * @return array<string, string>
     */
    public static function generate(\WP_Rewrite $rewrite, array $args): array
    {
        $args += self::DEFAULTS;
        $struct = (string) $args['struct'];
        $queries = self::queries($rewrite, $struct);
        [$post, $page] = self::namesPost($struct);
        $trimmed = (string) preg_replace('#/+$#', '', $struct);
        $dirs = $args['walk_dirs'] ? explode('/', $trimmed) : [$trimmed];
        $rules = [];
        $path = '';
        foreach ($dirs as $j => $dir) {
            $path = ltrim($path . $dir . '/', '/');
            $tokens = preg_match_all('/%.+?%/', $path);
            $last = $j === count($dirs) - 1;
            if ($tokens === 0 && !$last) {
                continue;
            }
            $level = [
                'match' => str_replace($rewrite->rewritecode, $rewrite->rewritereplace, $path),
                'query' => $tokens > 0 ? $queries[$tokens - 1] : $rewrite->index . '?',
                'tokens' => $tokens,
                'mask' => self::dirMask($dir),
            ];
            $levelRules = $post && $last ? self::postLevel($rewrite, $level, $args, $page) : self::plainLevel($rewrite, $level, $args);
            $rules = array_merge($levelRules, $rules);
        }
        return $rules;
    }

    /**
     * rewrite_rules: every rule, assembled and filtered.
     *
     * @return array<string, string>
     */
    public static function all(\WP_Rewrite $rewrite): array
    {
        if (!$rewrite->using_permalinks()) {
            return [];
        }
        $filter = static function (string $name, array $rules): array {
            $rules = (array) \apply_filters("{$name}_rewrite_rules", $rules);
            return $name === 'post_tag' ? (array) \apply_filters_deprecated('tag_rewrite_rules', [$rules], '3.1.0', 'post_tag_rewrite_rules') : $rules;
        };
        [$top, $rules] = self::assemble($rewrite, $filter);
        // As the reference does, a permastruct's rules join the top rules for good.
        $rewrite->extra_rules_top = $top;
        $rewrite->rules = $rules;
        \do_action_ref_array('generate_rewrite_rules', [&$rewrite]);
        $rewrite->rules = (array) \apply_filters('rewrite_rules_array', $rewrite->rules);
        return $rewrite->rules;
    }

    /**
     * The rules as the engine itself would make them, in their stored form
     * and with no plugin's filter or action on them: what the stored rules
     * are when no plugin changed them. The site's rewrite is left as it is.
     *
     * @return array<string, string>
     */
    public static function unfiltered(\WP_Rewrite $rewrite): array
    {
        $copy = clone $rewrite;
        $copy->matches = 'matches';
        return $copy->using_permalinks() ? self::assemble($copy, static fn (string $name, array $rules): array => $rules)[1] : [];
    }

    /**
     * The sections in the reference's order, each through $section: the top
     * rules (each permastruct's joining them), the fixed files, root,
     * comments, search, author, dates, pages and posts, the bottom rules.
     *
     * @param Closure(string, array<string, string>): array<string, string> $section
     * @return array{0: array<string, string>, 1: array<string, string>} the top rules, and every rule
     */
    private static function assemble(\WP_Rewrite $rewrite, Closure $section): array
    {
        $index = $rewrite->index;
        $fixed = [
            'robots\.txt$' => $index . '?robots=1',
            'favicon\.ico$' => $index . '?favicon=1',
            'sitemap\.xml' => $index . '?sitemap=index',
            '.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$' => $index . '?feed=old',
            '.*wp-app\.php(/.*)?$' => $index . '?error=403',
            '.*wp-register.php$' => $index . '?register=true',
        ];
        $post = $section('post', self::generate($rewrite, ['struct' => (string) $rewrite->permalink_structure, 'ep_mask' => EP_PERMALINK]));
        $date = $section('date', self::generate($rewrite, ['struct' => (string) $rewrite->get_date_permastruct(), 'ep_mask' => EP_DATE]));
        $root = $section('root', self::generate($rewrite, ['struct' => $rewrite->root . '/', 'ep_mask' => EP_ROOT]));
        $comments = $section('comments', self::generate($rewrite, ['struct' => $rewrite->root . $rewrite->comments_base, 'ep_mask' => EP_COMMENTS, 'paged' => false, 'forcomments' => true, 'walk_dirs' => false]));
        $search = $section('search', self::generate($rewrite, ['struct' => (string) $rewrite->get_search_permastruct(), 'ep_mask' => EP_SEARCH]));
        $author = $section('author', self::generate($rewrite, ['struct' => (string) $rewrite->get_author_permastruct(), 'ep_mask' => EP_AUTHORS]));
        $page = $section('page', $rewrite->page_rewrite_rules());
        $top = $rewrite->extra_rules_top;
        foreach ($rewrite->extra_permastructs as $name => $struct) {
            $top = array_merge($top, $section((string) $name, self::generate($rewrite, is_array($struct) ? $struct : ['struct' => (string) $struct])));
        }
        $singles = $rewrite->use_verbose_page_rules ? array_merge($page, $post) : array_merge($post, $page);
        return [$top, array_merge($top, $fixed, $root, $comments, $search, $author, $date, $singles, $rewrite->extra_rules)];
    }

    /**
     * The built-in taxonomies' tags and patterns, set once on the site's
     * rewrite under the front of the structure then; a structure set later
     * leaves them, and a plugin's, as they were (probe registry-rewrites).
     */
    public static function builtins(\WP_Rewrite $rewrite): void
    {
        $flags = ['with_front' => true, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true];
        foreach (['category' => ['(.+?)', 'category_name=', EP_CATEGORIES], 'post_tag' => ['([^/]+)', 'tag=', EP_TAGS], 'post_format' => ['([^/]+)', 'post_format=', EP_NONE]] as $name => [$regex, $query, $mask]) {
            $rewrite->add_rewrite_tag("%{$name}%", $regex, $query);
            $base = ['category' => 'category', 'post_tag' => 'tag', 'post_format' => 'type'][$name];
            $rewrite->extra_permastructs[$name] = ['with_front' => true, 'ep_mask' => $mask] + $flags + ['struct' => $rewrite->front . "{$base}/%{$name}%"];
        }
    }

    /** Whether a structure begins with a tag that could be a page's path too (use_verbose_page_rules). */
    public static function verbosePages(string $structure): bool
    {
        return preg_match('/^[^%]*%(?:postname|category|tag|author)%/', $structure) === 1;
    }

    /**
     * Each level's query: the index, then each tag's var and its match, one
     * more at a time.
     *
     * @return list<string>
     */
    private static function queries(\WP_Rewrite $rewrite, string $struct): array
    {
        preg_match_all('/%.+?%/', $struct, $found);
        $queries = [];
        foreach ($found[0] as $i => $token) {
            $part = str_replace($rewrite->rewritecode, $rewrite->queryreplace, $token) . $rewrite->preg_index($i + 1);
            $queries[] = $i === 0 ? $rewrite->index . '?' . $part : $queries[$i - 1] . '&' . $part;
        }
        return $queries;
    }

    /** Whether the structure names a post, and whether that post is hierarchical. @return array{0: bool, 1: bool} */
    private static function namesPost(string $struct): array
    {
        $page = str_contains($struct, '%pagename%');
        $post = $page || str_contains($struct, '%postname%') || str_contains($struct, '%post_id%')
            || array_filter(self::POST_BY_TIME, static fn ($tag) => !str_contains($struct, $tag)) === [];
        if (!$post && function_exists('get_post_types')) {
            foreach (\get_post_types(['_builtin' => false]) as $type) {
                if (str_contains($struct, "%{$type}%")) {
                    return [true, (bool) \is_post_type_hierarchical($type)];
                }
            }
        }
        return [$post, $page];
    }

    /** The endpoint place a date directory is besides the structure's own. */
    private static function dirMask(string $dir): int
    {
        return match ($dir) {
            '%year%' => EP_YEAR,
            '%monthnum%' => EP_MONTH,
            '%day%' => EP_DAY,
            default => EP_NONE,
        };
    }

    /**
     * A level that is not a post's.
     *
     * @param array{match: string, query: string, tokens: int, mask: int} $level
     * @param array<string, mixed> $args
     * @return array<string, string>
     */
    private static function plainLevel(\WP_Rewrite $rewrite, array $level, array $args): array
    {
        $rules = $args['feed'] ? self::feeds($rewrite, $level, $args) + [$level['match'] . 'embed/?$' => $level['query'] . '&embed=true'] : [];
        $rules += self::pagesAndComments($rewrite, $level, $args) + self::endpoints($rewrite, $level, $args);
        if ($level['tokens'] > 0) {
            $rules[$level['match'] . '?$'] = $level['query'];
        }
        return $rules;
    }

    /**
     * The post's level, between its attachments' rules.
     *
     * @param array{match: string, query: string, tokens: int, mask: int} $level
     * @param array<string, mixed> $args
     * @return array<string, string>
     */
    private static function postLevel(\WP_Rewrite $rewrite, array $level, array $args, bool $hierarchical): array
    {
        [$match, $query] = [$level['match'], $level['query']];
        $rules = [$match . 'embed/?$' => $query . '&embed=true', $match . 'trackback/?$' => $query . '&tb=1'];
        $rules += $args['feed'] ? self::feeds($rewrite, $level, $args) : [];
        $rules += self::pagesAndComments($rewrite, $level, $args) + self::endpoints($rewrite, $level, $args);
        $base = str_replace(['(', ')'], '', rtrim($match, '/'));
        [$under, $direct] = [$base . '/attachment/([^/]+)/', $base . '/([^/]+)/'];
        if ($args['endpoints']) {
            foreach ($rewrite->endpoints as [$places, $name, $var]) {
                if (((int) $places & EP_ATTACHMENT) !== 0) {
                    $rules[$direct . $name . '(/(.*))?/?$'] = $rewrite->index . '?attachment=' . $rewrite->preg_index(1) . '&' . $var . '=' . $rewrite->preg_index(3);
                    $rules[$under . $name . '(/(.*))?/?$'] = $rewrite->index . '?attachment=' . $rewrite->preg_index(1) . '&' . $var . '=' . $rewrite->preg_index(3);
                }
            }
        }
        $rules[rtrim($match, '/') . '(?:/([0-9]+))?/?$'] = $query . '&page=' . $rewrite->preg_index($level['tokens'] + 1);
        return self::attachments($rewrite, $under) + $rules + ($hierarchical ? [] : self::attachments($rewrite, $direct));
    }

    /**
     * An attachment's rules under a base: itself, its trackback, feeds,
     * comment pages and embed.
     *
     * @return array<string, string>
     */
    private static function attachments(\WP_Rewrite $rewrite, string $base): array
    {
        $query = $rewrite->index . '?attachment=' . $rewrite->preg_index(1);
        $feeds = '(' . implode('|', $rewrite->feeds) . ')/?$';
        return [
            $base . '?$' => $query,
            $base . 'trackback/?$' => $query . '&tb=1',
            $base . $rewrite->feed_base . '/' . $feeds => $query . '&feed=' . $rewrite->preg_index(2),
            $base . $feeds => $query . '&feed=' . $rewrite->preg_index(2),
            $base . $rewrite->comments_pagination_base . '-([0-9]{1,})/?$' => $query . '&cpage=' . $rewrite->preg_index(2),
            $base . 'embed/?$' => $query . '&embed=true',
        ];
    }

    /**
     * A level's two feed rules (with comments when asked).
     *
     * @param array{match: string, query: string, tokens: int, mask: int} $level
     * @param array<string, mixed> $args
     * @return array<string, string>
     */
    private static function feeds(\WP_Rewrite $rewrite, array $level, array $args): array
    {
        $feeds = '(' . implode('|', $rewrite->feeds) . ')/?$';
        $query = $level['query'] . '&feed=' . $rewrite->preg_index($level['tokens'] + 1) . ($args['forcomments'] ? '&withcomments=1' : '');
        return [$level['match'] . $rewrite->feed_base . '/' . $feeds => $query, $level['match'] . $feeds => $query];
    }

    /**
     * A level's page rule, its comment pages (posts' and pages' places
     * only), and a static front page's comment pages at the root.
     *
     * @param array{match: string, query: string, tokens: int, mask: int} $level
     * @param array<string, mixed> $args
     * @return array<string, string>
     */
    private static function pagesAndComments(\WP_Rewrite $rewrite, array $level, array $args): array
    {
        $next = $rewrite->preg_index($level['tokens'] + 1);
        $comments = $level['match'] . $rewrite->comments_pagination_base . '-([0-9]{1,})/?$';
        $rules = $args['paged'] ? [$level['match'] . $rewrite->pagination_base . '/?([0-9]{1,})/?$' => $level['query'] . '&paged=' . $next] : [];
        if (((int) $args['ep_mask'] & (EP_PERMALINK | EP_PAGES)) !== 0) {
            $rules[$comments] = $level['query'] . '&cpage=' . $next;
        }
        $front = (int) (function_exists('get_option') ? \get_option('page_on_front') : 0);
        if (((int) $args['ep_mask'] & EP_ROOT) !== 0 && $front > 0) {
            $rules[$comments] = $level['query'] . '&page_id=' . $front . '&cpage=' . $next;
        }
        return $rules;
    }

    /**
     * The endpoints placed at a level (by the structure's place or the
     * directory's own).
     *
     * @param array{match: string, query: string, tokens: int, mask: int} $level
     * @param array<string, mixed> $args
     * @return array<string, string>
     */
    private static function endpoints(\WP_Rewrite $rewrite, array $level, array $args): array
    {
        $rules = [];
        if (!$args['endpoints']) {
            return $rules;
        }
        foreach ($rewrite->endpoints as [$places, $name, $var]) {
            if (((int) $places & ((int) $args['ep_mask'] | $level['mask'])) !== 0) {
                $rules[$level['match'] . $name . '(/(.*))?/?$'] = $level['query'] . '&' . $var . '=' . $rewrite->preg_index($level['tokens'] + 2);
            }
        }
        return $rules;
    }
}
