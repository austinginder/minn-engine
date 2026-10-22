<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\RouteParams;

/**
 * A global styles update's parameters as the reference declares them: its
 * styles and settings, each an object, and its title, then the fields
 * plugins register for wp_global_styles (Minn Admin's own among them). Each
 * is held to its type before the caller is judged; the id is the path's
 * alone, and a key the update does not know is ignored.
 */
final class GlobalStylesParams implements RouteParams
{
    private const UPDATE = [
        'styles' => ['description' => 'Global styles.', 'type' => ['object'], 'required' => false],
        'settings' => ['description' => 'Global settings.', 'type' => ['object'], 'required' => false],
        'title' => [
            'description' => 'Title of the global styles variation.',
            'type' => ['object', 'string'],
            'properties' => [
                'raw' => ['description' => 'Title for the global styles variation, as it exists in the database.', 'type' => 'string', 'context' => ['view', 'edit', 'embed']],
                'rendered' => ['description' => 'HTML title for the post, transformed for display.', 'type' => 'string', 'context' => ['view', 'edit', 'embed'], 'readonly' => true],
            ],
            'required' => false,
        ],
    ];

    /** The update's parameters; the route's id capture changes none of them. */
    public static function for(array $captures): array
    {
        return self::UPDATE + RegisteredFields::args('wp_global_styles');
    }
}
