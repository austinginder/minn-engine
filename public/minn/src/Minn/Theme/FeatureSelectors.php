<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * A block's styles split between the selectors its metadata gives its
 * features (probe theme-json-css), in the metadata's order: a property with
 * a selector of its own first, then what is left of its feature under the
 * feature's selector. What is taken leaves the node.
 */
final class FeatureSelectors
{
    /**
     * The styles each feature selector takes, and the node left over.
     *
     * @param array<string, mixed> $selectors the block's metadata selectors
     * @param array<string, mixed> $node the block's styles
     * @return array{0: list<array{0: string, 1: array<string, mixed>}>, 1: array<string, mixed>} the styles each selector takes, in order, and what stays
     */
    public static function split(array $selectors, array $node): array
    {
        $taken = [];
        foreach ($selectors as $feature => $featureSelectors) {
            if ($feature === 'root' || empty($node[$feature])) {
                continue;
            }
            if (is_array($featureSelectors)) {
                foreach ($featureSelectors as $property => $propertySelector) {
                    if ($property !== 'root' && is_string($propertySelector) && !empty($node[$feature][$property])) {
                        $taken[] = [$propertySelector, [$feature => [$property => $node[$feature][$property]]]];
                        unset($node[$feature][$property]);
                    }
                }
            }
            $featureSelector = is_string($featureSelectors) ? $featureSelectors : ($featureSelectors['root'] ?? null);
            if (is_string($featureSelector) && !empty($node[$feature])) {
                $taken[] = [$featureSelector, [$feature => $node[$feature]]];
                unset($node[$feature]);
            }
        }
        return [$taken, $node];
    }
}
