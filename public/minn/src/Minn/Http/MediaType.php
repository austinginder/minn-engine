<?php

declare(strict_types=1);

namespace Minn\Http;

/** Reading the media types a request names in its Accept and Content-Type headers. */
final class MediaType
{
    /**
     * Whether a list of media types names JSON: application/json, or any
     * application/…+json vendor type, parameters and case ignored
     * (wp_is_json_media_type(), probe load-remaining).
     */
    public static function json(string $types): bool
    {
        foreach (explode(',', $types) as $type) {
            $type = strtolower(trim(explode(';', $type)[0]));
            if (preg_match('#^application/(?:[^/]+\+)?json$#', $type) === 1) {
                return true;
            }
        }
        return false;
    }
}
