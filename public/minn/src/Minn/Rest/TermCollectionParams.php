<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\RouteParams;
use Minn\Http\RouteMiss;

/**
 * A taxonomy's term list parameters as the reference declares them (probe
 * rest-term-lists): the context and Args::TERMS, a hierarchical taxonomy's
 * with a parent and no offset, a flat one's with an offset and no parent.
 * Plugins change them through rest_{taxonomy}_collection_params when the
 * list runs.
 */
final class TermCollectionParams implements RouteParams
{
    /** The parameters of the list a {base} capture names; none for a base no REST taxonomy has. */
    public static function for(array $captures): array
    {
        try {
            $config = TermObject::config((string) ($captures['base'] ?? ''));
        } catch (RouteMiss) {
            return [];
        }
        return array_diff_key(Args::CONTEXT + Args::TERMS, [$config['has_parent'] ? 'offset' : 'parent' => true]);
    }
}
