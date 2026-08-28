<?php

declare(strict_types=1);

namespace Minn\Content;

final class Slug
{
    /** Lower-case, hyphen-separated, ASCII only: the shape post_name takes. */
    public static function sanitize(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\-_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
