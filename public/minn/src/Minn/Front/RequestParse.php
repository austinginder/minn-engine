<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Runtime\Runtime;

/**
 * The query vars the reference's request parse sets for an address
 * (WP::parse_request), which plugins read off $wp->query_vars and
 * get_query_var: the vars of the rewrite rule the path matches (a post's
 * name and page, a page's full path, an archive's slug, the paged, feed,
 * comment-page, embed and trackback suffixes), then each public var the
 * query string or form carries, all in the public vars' order (a plugin's
 * own after the core ones); a post type's or taxonomy's own var brings
 * post_type and name; a path no rule matches is error=404. The engine
 * knows what the path resolved to, so the rule is read off that and the
 * path's shape rather than matched from a rule list.
 */
final class RequestParse
{
    /**
     * The parse's vars, in the reference's order.
     *
     * @param list<string> $publicVars the public query vars, after the query_vars filter
     * @param array<string, mixed> $given the query string's and the form's values
     * @return array<string, string>
     */
    public static function vars(string $path, Resolution $resolution, array $publicVars, array $given): array
    {
        // A plugin's own rule (or an endpoint's var) stands as matched; the engine's reading of the path fills the rest.
        $plugin = PluginRules::stashed();
        $rule = Runtime::booted() && Runtime::current()->get(PluginRules::MATCHED) === true ? $plugin : (trim($path, '/') === '' ? [] : self::ruleVars(self::segments($path), $resolution)) + $plugin;
        $vars = [];
        foreach ($publicVars as $var) {
            $value = $given[$var] ?? $rule[$var] ?? null;
            if ($value !== null && is_scalar($value)) {
                $vars[$var] = (string) $value;
            }
        }
        foreach (self::objectVars() as $var => [$key, $name]) {
            if (isset($vars[$var])) {
                $vars[$key] = $name;
                if ($key === 'post_type') {
                    $vars['name'] = $vars[$var];
                }
            }
        }
        return isset($rule['error']) ? $vars + ['error' => '404'] : $vars;
    }

    /** The reference's query string for parse vars (WP::build_query_string): each one with a value, encoded. */
    public static function queryString(array $vars): string
    {
        $pairs = [];
        foreach ($vars as $var => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $pairs[] = $var . '=' . rawurlencode((string) $value);
            }
        }
        return implode('&', $pairs);
    }

    /** @return list<string> */
    private static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim(rawurldecode($path), '/')), static fn (string $s) => $s !== ''));
    }

    /**
     * The matched rule's vars: the suffixes the path ends in, then what the
     * rest names.
     *
     * @param list<string> $segments
     * @return array<string, string>
     */
    private static function ruleVars(array $segments, Resolution $resolution): array
    {
        [$segments, $suffix] = self::suffixes($segments);
        $base = match (true) {
            $resolution->postsPage => ['pagename' => implode('/', $segments), 'page' => ''],
            default => self::kindVars($segments, $resolution),
        };
        // Only the plain rule carries the page; a suffix's rule names the single alone.
        return ($suffix === [] ? $base : array_diff_key($base, ['page' => true])) + $suffix;
    }

    /**
     * What the rest of the path names, by what it resolved to.
     *
     * @param list<string> $segments
     * @return array<string, string>
     */
    private static function kindVars(array $segments, Resolution $resolution): array
    {
        return match ($resolution->kind) {
            Kind::Home => self::homeVars($segments),
            Kind::Single, Kind::Page => self::singleVars($segments, $resolution),
            Kind::Category => ['category_name' => implode('/', array_slice($segments, ($segments[0] ?? '') === self::categoryBase() ? 1 : 0))],
            Kind::Tag => ['tag' => (string) end($segments)],
            Kind::Author => ['author_name' => (string) end($segments)],
            Kind::Date => array_combine(array_slice(['year', 'monthnum', 'day'], 0, count($segments)), $segments) ?: [],
            Kind::Search => ['s' => implode('/', array_slice($segments, 1))],
            Kind::Taxonomy => [self::taxonomyVar((string) ($resolution->record['taxonomy'] ?? '')) => (string) end($segments)],
            Kind::PostTypeArchive => ['post_type' => (string) ($resolution->record['name'] ?? '')],
            default => self::unmatched($segments),
        };
    }

    /**
     * The suffixes a rule takes off the end of a path: page/N, comment-page-N,
     * feed[/type], embed, trackback.
     *
     * @param list<string> $segments
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private static function suffixes(array $segments): array
    {
        $suffix = [];
        $count = count($segments);
        if ($count >= 2 && $segments[$count - 2] === 'page' && ctype_digit($segments[$count - 1])) {
            $suffix['paged'] = $segments[$count - 1];
            $segments = array_slice($segments, 0, -2);
        } elseif ($count >= 1 && preg_match('/^comment-page-(\d+)$/', $segments[$count - 1], $m) === 1) {
            $suffix['cpage'] = $m[1];
            $segments = array_slice($segments, 0, -1);
        } elseif ($count >= 2 && $segments[$count - 2] === 'feed' && in_array($segments[$count - 1], ['feed', 'rdf', 'rss', 'rss2', 'atom'], true)) {
            $suffix['feed'] = $segments[$count - 1];
            $segments = array_slice($segments, 0, -2);
        } elseif ($count >= 1 && in_array($segments[$count - 1], ['feed', 'embed', 'trackback'], true)) {
            $suffix = match ($segments[$count - 1]) {
                'feed' => ['feed' => 'feed'],
                'embed' => ['embed' => 'true'],
                default => ['tb' => '1'],
            };
            $segments = array_slice($segments, 0, -1);
        }
        if (($suffix['feed'] ?? null) !== null && $segments === ['comments']) {
            return [[], $suffix + ['withcomments' => '1']];
        }
        return [$segments, $suffix];
    }

    /** @param list<string> $segments @return array<string, string> */
    private static function homeVars(array $segments): array
    {
        return ($segments[0] ?? '') === 'search' ? ['s' => implode('/', array_slice($segments, 1))] : [];
    }

    /**
     * A single's rule: a page by its full path, a post by its name (and its
     * page, '' for the first), a post type's own by its var, an attachment
     * by the page rule at the top and by its own rule under a post.
     *
     * @param list<string> $segments
     * @return array<string, string>
     */
    private static function singleVars(array $segments, Resolution $resolution): array
    {
        $record = $resolution->record;
        $type = $record instanceof PostRecord ? $record->type : 'post';
        $page = count($segments) >= 2 && ctype_digit((string) end($segments)) ? (string) array_pop($segments) : '';
        return match (true) {
            $resolution->kind === Kind::Page => ['pagename' => implode('/', $segments), 'page' => $page],
            $type === 'attachment' && count($segments) === 1 => ['pagename' => $segments[0], 'page' => $page],
            $type === 'attachment' => ['attachment' => (string) end($segments)],
            $type === 'post' => ['name' => (string) end($segments), 'page' => $page],
            default => [self::queryVarOf($type) => (string) end($segments), 'page' => $page],
        };
    }

    /**
     * What an address that found nothing matched: a post's rule for one
     * segment, an attachment's for two, an archive's by its base; else none.
     *
     * @param list<string> $segments
     * @return array<string, string>
     */
    private static function unmatched(array $segments): array
    {
        return match (true) {
            $segments === [] => [],
            ($segments[0] ?? '') === 'category' && count($segments) > 1 => ['category_name' => implode('/', array_slice($segments, 1))],
            ($segments[0] ?? '') === 'tag' && count($segments) === 2 => ['tag' => $segments[1]],
            ($segments[0] ?? '') === 'author' && count($segments) === 2 => ['author_name' => $segments[1]],
            count($segments) === 1 => ['name' => $segments[0], 'page' => ''],
            count($segments) === 2 => ['attachment' => $segments[1]],
            default => ['error' => '404'],
        };
    }

    /**
     * The vars that name a post type's or taxonomy's object, and what they
     * bring along: var => [key, value].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function objectVars(): array
    {
        if (!Runtime::booted()) {
            return [];
        }
        $vars = [];
        foreach (Runtime::registry()->postTypes() as $name => $type) {
            $var = $type['query_var'] ?? false;
            if (is_string($var) && $var !== '' && !in_array($name, ['post', 'page', 'attachment'], true)) {
                $vars[$var] = ['post_type', (string) $name];
            }
        }
        return $vars;
    }

    /** The category archives' base (category_base, "category" unless set); a category-first structure's bare path has none. */
    private static function categoryBase(): string
    {
        $base = Runtime::booted() ? trim((string) \get_option('category_base'), '/') : '';
        return $base === '' ? 'category' : $base;
    }

    /** A taxonomy's query var (its name unless it set one). */
    private static function taxonomyVar(string $taxonomy): string
    {
        $var = Runtime::booted() ? (Runtime::registry()->taxonomy($taxonomy)['query_var'] ?? $taxonomy) : $taxonomy;
        return is_string($var) && $var !== '' ? $var : $taxonomy;
    }

    /** A post type's query var (its name unless it set one). */
    private static function queryVarOf(string $type): string
    {
        $var = Runtime::booted() ? (Runtime::registry()->postType($type)['query_var'] ?? $type) : $type;
        return is_string($var) && $var !== '' ? $var : $type;
    }
}
