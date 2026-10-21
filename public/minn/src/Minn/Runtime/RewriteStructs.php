<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Rewrite;

/**
 * The rewrite object's state and the structures it builds links and rules
 * from (probe db-mail-rewrite-doubts): init reads the permalink structure
 * and clears the bottom, external and endpoint rules while the top rules
 * stay; each structure (date, author, search, page, feed, comment feed) is
 * the one already set on the object when there is one, else made once and
 * kept; year and month come from the date structure with its empty steps
 * folded; a removed tag leaves its place empty.
 */
final class RewriteStructs
{
    /** The structure each getter keeps, by name, and how it is made from the front or the root. */
    private const MADE = [
        'author' => ['author_structure', 'front', ['author_base'], '/%author%'],
        'search' => ['search_structure', 'root', ['search_base'], '/%search%'],
        'page' => ['page_structure', 'root', [], '%pagename%'],
        'feed' => ['feed_structure', 'root', ['feed_base'], '/%feed%'],
        'comment_feed' => ['comment_feed_structure', 'root', ['comments_base', 'feed_base'], '/%feed%'],
    ];

    /** The object set up from the stored permalink structure (WP_Rewrite::init). */
    public static function init(Rewrite $rewrite): void
    {
        $structure = (string) Runtime::options()->filtered('permalink_structure');
        $rewrite->permalink_structure = $structure;
        $rewrite->front = substr($structure, 0, (int) strpos($structure, '%'));
        $rewrite->root = $rewrite->using_index_permalinks() ? $rewrite->index . '/' : '';
        unset($rewrite->author_structure, $rewrite->date_structure, $rewrite->page_structure, $rewrite->search_structure, $rewrite->feed_structure, $rewrite->comment_feed_structure);
        $rewrite->use_trailing_slashes = str_ends_with($structure, '/');
        $rewrite->use_verbose_rules = false;
        $rewrite->use_verbose_page_rules = RewriteRules::verbosePages($structure);
        $rewrite->extra_rules = [];
        $rewrite->non_wp_rules = [];
        $rewrite->endpoints = [];
    }

    /**
     * The date structure: the one set, else the structure's own order of
     * year, month and day (under date/ when a post id is among its first
     * three tags, as it would read as a date); false without permalinks.
     */
    public static function date(Rewrite $rewrite): string|false
    {
        if (isset($rewrite->date_structure)) {
            return $rewrite->date_structure;
        }
        $structure = (string) $rewrite->permalink_structure;
        if ($structure === '') {
            return false;
        }
        $date = '%year%/%monthnum%/%day%';
        foreach (['%year%/%monthnum%/%day%', '%day%/%monthnum%/%year%', '%monthnum%/%day%/%year%'] as $order) {
            if (str_contains($structure, $order)) {
                $date = $order;
                break;
            }
        }
        preg_match_all('/%.+?%/', $structure, $tokens);
        $front = in_array('%post_id%', array_slice($tokens[0], 0, 3), true) ? $rewrite->front . 'date/' : $rewrite->front;
        return $rewrite->date_structure = $front . $date;
    }

    /** The date structure without the given tags, its empty steps folded into one slash. */
    public static function dateWithout(Rewrite $rewrite, array $tags): string|false
    {
        $date = $rewrite->get_date_permastruct();
        return $date === false ? false : (string) preg_replace('#/+#', '/', str_replace($tags, '', $date));
    }

    /** One of the named structures (author, search, page, feed, comment_feed): the one set, else made once and kept; false without permalinks. */
    public static function made(Rewrite $rewrite, string $name): string|false
    {
        [$property, $from, $bases, $tail] = self::MADE[$name];
        if (isset($rewrite->{$property})) {
            return $rewrite->{$property};
        }
        if (empty($rewrite->permalink_structure)) {
            return false;
        }
        $steps = implode('/', array_map(static fn (string $base): string => (string) $rewrite->{$base}, $bases));
        return $rewrite->{$property} = $rewrite->{$from} . $steps . $tail;
    }

    /** A rewrite tag removed, its place in each list left empty. */
    public static function removeTag(Rewrite $rewrite, mixed $tag): void
    {
        $position = array_search($tag, $rewrite->rewritecode, true);
        if ($position !== false) {
            unset($rewrite->rewritecode[$position], $rewrite->rewritereplace[$position], $rewrite->queryreplace[$position]);
        }
    }

    /**
     * A permanent structure's arguments: an array as given, or the old
     * signature's with_front and ep_mask, over the defaults; the structure
     * under the front or the root.
     *
     * @param list<mixed> $legacy the arguments after the structure, as given
     * @return array<string, mixed>
     */
    public static function permastruct(Rewrite $rewrite, mixed $struct, array $legacy): array
    {
        $args = $legacy[0] ?? [];
        if (!is_array($args)) {
            $args = ['with_front' => $args] + (array_key_exists(1, $legacy) ? ['ep_mask' => $legacy[1]] : []);
        }
        $defaults = ['with_front' => true, 'ep_mask' => EP_NONE, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true];
        $args = array_merge($defaults, array_intersect_key($args, $defaults));
        $args['struct'] = ($args['with_front'] ? $rewrite->front : $rewrite->root) . $struct;
        return $args;
    }

    /** Sets a base option and starts the object over, only when the value changes. */
    public static function setBase(Rewrite $rewrite, string $option, mixed $value): void
    {
        if ((string) Runtime::options()->filtered($option) === (string) $value) {
            return;
        }
        OptionWrites::update($option, $value);
        $rewrite->init();
    }
}
