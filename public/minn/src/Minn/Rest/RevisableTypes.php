<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;

/**
 * The post types plugin code or an extension registered that answer
 * revisions and autosaves routes at their rest_base, as the reference
 * registers those controllers per type shown in REST under wp/v2:
 * autosaves for any type but attachments, revisions for a type that
 * supports them.
 */
final class RevisableTypes
{
    /** The registered post type a REST base names under wp/v2, or null. Asked only inside a REST request, where the runtime has booted. */
    public static function typeOf(string $base): ?string
    {
        foreach (Runtime::registry()->postTypes() as $slug => $row) {
            if (!empty($row['show_in_rest']) && (string) ($row['rest_base'] ?: $slug) === $base && (string) ($row['rest_namespace'] ?? '' ?: 'wp/v2') === 'wp/v2') {
                return (string) $slug;
            }
        }
        return null;
    }

    /** Whether a base's type answers the revisions routes: it supports revisions. */
    public static function revisable(string $base): bool
    {
        $type = self::autosavingType($base);
        return $type !== null && !empty(Runtime::registry()->supports($type)['revisions']);
    }

    /** Whether a base's type answers the autosaves routes: any type but attachments. */
    public static function autosaving(string $base): bool
    {
        return self::autosavingType($base) !== null;
    }

    private static function autosavingType(string $base): ?string
    {
        $type = self::typeOf($base);
        return $type === 'attachment' ? null : $type;
    }
}
