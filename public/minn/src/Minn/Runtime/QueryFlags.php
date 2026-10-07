<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The conditional flags a set of query variables implies (is_single, is_archive,
 * is_home, ...), derived the way the reference's parse step derives them, plus
 * the variables after the integer casts that step applies.
 */
final readonly class QueryFlags
{
    private const INTEGER_VARS = ['p', 'page_id', 'attachment_id', 'year', 'monthnum', 'day', 'w', 'paged'];

    /**
     * @param array<string, mixed> $vars
     * @param array<string, bool> $flags only the flags that are on
     */
    private function __construct(public array $vars, public array $flags)
    {
    }

    /**
     * The variables with every one the reference fills given its empty
     * value: the template's keys up to search_columns (the ones after it are
     * get_posts' own, settled when the query runs).
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    public static function fill(array $vars, Registry $registry): array
    {
        foreach ($registry->queryVars as $key => $default) {
            if ($key === 'ignore_sticky_posts') {
                break;
            }
            if (!isset($vars[$key])) {
                $vars[$key] = is_array($default) ? [] : ($key === 'p' ? 0 : $default);
            }
        }
        return $vars;
    }

    /**
     * The is_* flags the query vars amount to.
     *
     * @param array<string, mixed> $vars the filled query variables
     * @param callable(string): mixed $option a filtered option read
     */
    public static function derive(array $vars, Registry $registry, callable $option): self
    {
        foreach (self::INTEGER_VARS as $key) {
            if (isset($vars[$key]) && $vars[$key] !== '' && !is_array($vars[$key])) {
                $vars[$key] = (int) $vars[$key];
            }
        }
        $vars = self::sanitize($vars);
        if (is_string($vars['feed'] ?? null) && str_starts_with($vars['feed'], 'comments-')) {
            $vars['feed'] = substr($vars['feed'], 9);
            $vars['withcomments'] = 1;
        }
        $on = [];
        if ((int) $vars['p'] < 0 || (int) $vars['page_id'] < 0) {
            $vars['error'] = '404';
        }
        $on['is_404'] = ($vars['error'] ?? null) === '404';
        $on['is_embed'] = !empty($vars['embed']);
        $on['is_trackback'] = !empty($vars['tb']);
        $on['is_paged'] = !empty($vars['paged']) && (int) $vars['paged'] > 1;
        $on['is_search'] = !empty($vars['s']);
        $on['is_feed'] = !empty($vars['feed']);
        if (!empty($vars['attachment']) || !empty($vars['attachment_id'])) {
            $on['is_single'] = true;
            $on['is_attachment'] = true;
        } elseif (!empty($vars['p']) || !empty($vars['name'])) {
            $on['is_single'] = true;
        } elseif (!empty($vars['page_id']) || !empty($vars['pagename'])) {
            $on['is_page'] = true;
        } else {
            $on += self::archiveFlags($vars, $registry);
        }
        $on['is_singular'] = !empty($on['is_single']) || !empty($on['is_page']);
        $on['is_home'] = !$on['is_singular'] && empty($on['is_archive']) && !$on['is_search'] && !$on['is_feed'] && !$on['is_trackback'] && !$on['is_404'] && !$on['is_embed'];
        $pageId = (int) $vars['page_id'];
        if (!empty($on['is_page']) && $pageId > 0 && $pageId === (int) $option('page_for_posts')) {
            $on['is_home'] = true;
            $on['is_page'] = false;
            $on['is_singular'] = false;
            $on['is_posts_page'] = true;
        }
        if (!empty($on['is_page']) && (int) $option('wp_page_for_privacy_policy') > 0 && $pageId === (int) $option('wp_page_for_privacy_policy')) {
            $on['is_privacy_policy'] = true;
        }
        // A feed of comments: asked for, or a single post's feed that does not refuse them (the posts page's feed is its posts').
        $on['is_comment_feed'] = $on['is_feed'] && (!empty($vars['withcomments']) || (empty($vars['withoutcomments']) && $on['is_singular']));
        return new self($vars, array_filter($on));
    }

    /**
     * The reference's clean-up of the free-form variables: m and the time
     * parts to digits, cat and author to id lists, names trimmed, an
     * over-long or non-scalar search dropped.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    private static function sanitize(array $vars): array
    {
        $vars['m'] = is_scalar($vars['m']) ? preg_replace('|[^0-9]|', '', (string) $vars['m']) : '';
        $ids = static fn ($value) => preg_replace('|[^0-9,-]|', '', (string) $value);
        $vars['cat'] = is_array($vars['cat']) ? array_map($ids, $vars['cat']) : $ids($vars['cat']);
        $vars['author'] = is_scalar($vars['author']) ? preg_replace('|[^0-9,-]|', '', (string) $vars['author']) : '';
        foreach (['pagename', 'name', 'title'] as $key) {
            $vars[$key] = is_scalar($vars[$key]) ? trim((string) $vars[$key]) : '';
        }
        foreach (['hour', 'minute', 'second', 'menu_order'] as $key) {
            if ($vars[$key] !== '') {
                $vars[$key] = abs((int) $vars[$key]);
            }
        }
        if (!is_scalar($vars['s']) || ($vars['s'] !== '' && strlen((string) $vars['s']) > 1600)) {
            $vars['s'] = '';
        }
        // Statuses keep only key characters (a comma list keeps its commas); types become keys.
        ['post_status' => $statuses, 'post_type' => $types] = $vars + ['post_status' => null, 'post_type' => null];
        $clean = [];
        if (!empty($statuses)) {
            $clean += ['post_status' => is_array($statuses) ? array_map('sanitize_key', $statuses) : preg_replace('|[^a-z0-9_,-]|', '', (string) $statuses)];
        }
        if (!empty($types)) {
            $clean += ['post_type' => is_array($types) ? array_map('sanitize_key', $types) : \sanitize_key((string) $types)];
        }
        return array_replace($vars, $clean);
    }

    /** @return array<string, bool> */
    private static function archiveFlags(array $vars, Registry $registry): array
    {
        $on = [];
        if ($vars['year'] || $vars['monthnum'] || $vars['day'] || $vars['w'] || !empty($vars['m']) || !empty($vars['hour']) || !empty($vars['minute']) || !empty($vars['second'])) {
            $on['is_date'] = true;
            $on['is_year'] = (bool) $vars['year'];
            $on['is_month'] = (bool) $vars['monthnum'];
            $on['is_day'] = (bool) $vars['day'];
            $on['is_time'] = !empty($vars['hour']) || !empty($vars['minute']) || !empty($vars['second']);
        }
        $categoryIds = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($vars['cat'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)), static fn (int $id) => $id > 0);
        $on['is_category'] = $categoryIds !== [] || !empty($vars['category_name']) || !empty($vars['category__in']) || !empty($vars['category__and']);
        $on['is_tag'] = !empty($vars['tag']) || !empty($vars['tag_id']) || !empty($vars['tag__in']) || !empty($vars['tag__and']) || !empty($vars['tag_slug__in']) || !empty($vars['tag_slug__and']);
        $on['is_tax'] = (!empty($vars['tax_query']) && is_array($vars['tax_query'])) || self::customTaxonomyVar($vars, $registry) !== null;
        $on['is_author'] = !empty($vars['author']) || !empty($vars['author_name']) || !empty($vars['author__in']);
        if (!empty($vars['post_type']) && !is_array($vars['post_type']) && $vars['post_type'] !== 'any') {
            $type = $registry->postType((string) $vars['post_type']);
            $on['is_post_type_archive'] = $type !== null && !empty($type['has_archive']);
        }
        $on['is_archive'] = count(array_filter([$on['is_date'] ?? false, $on['is_category'], $on['is_tag'], $on['is_tax'], $on['is_author'], $on['is_post_type_archive'] ?? false])) > 0;
        return $on;
    }

    /**
     * The first registered taxonomy (other than the two built-in ones) whose
     * query variable carries a value, as [taxonomy name, query var].
     *
     * @return array{0: string, 1: string}|null
     */
    public static function customTaxonomyVar(array $vars, Registry $registry): ?array
    {
        foreach ($registry->taxonomies() as $name => $taxonomy) {
            $var = $taxonomy['query_var'] ?? false;
            if (is_string($var) && $var !== '' && !in_array($var, ['category_name', 'tag'], true) && !empty($vars[$var])) {
                return [(string) $name, $var];
            }
        }
        return null;
    }
}
