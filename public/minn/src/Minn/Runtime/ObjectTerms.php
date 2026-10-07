<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * wp_get_object_terms's handling of taxonomies registered with their own
 * query arguments, as the reference handles them: among several, each such
 * taxonomy is asked on its own with its arguments over the caller's; alone,
 * its arguments join the caller's.
 */
final class ObjectTerms
{
    /**
     * The terms asked for apart, the taxonomies left (keys as they were), and the arguments for those.
     *
     * @param list<int> $objectIds
     * @param array<int, string> $taxonomies
     * @param array<string, mixed> $args
     * @return array{0: list<mixed>, 1: array<int, string>, 2: array<string, mixed>}
     */
    public static function byTaxonomyArgs(array $objectIds, array $taxonomies, array $args): array
    {
        $terms = [];
        if (count($taxonomies) > 1) {
            foreach ($taxonomies as $index => $taxonomy) {
                $own = \get_taxonomy($taxonomy)->args ?? null;
                if (is_array($own) && array_merge($args, $own) != $args) {
                    unset($taxonomies[$index]);
                    $terms = array_merge($terms, (array) \wp_get_object_terms($objectIds, $taxonomy, array_merge($args, $own)));
                }
            }
        } else {
            $own = \get_taxonomy(reset($taxonomies))->args ?? null;
            $args = is_array($own) ? array_merge($args, $own) : $args;
        }
        $args['taxonomy'] = $taxonomies;
        $args['object_ids'] = $objectIds;
        return [$terms, $taxonomies, $args];
    }
}
