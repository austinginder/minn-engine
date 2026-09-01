<?php

declare(strict_types=1);

namespace Minn\Media;

/** Whether an attachment is an image, audio, video, or a given extension, judged by its MIME type first and its file extension second. */
final class Kind
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'gif', 'png', 'webp', 'avif', 'heic'];

    /**
     * @param list<string> $audioExtensions
     * @param list<string> $videoExtensions
     */
    /**
     * Real mime types grouped under the wildcard patterns that match them
     * (image, audio/*, image/jpeg, *); a pattern with no matches is omitted.
     *
     * @param list<string> $patterns
     * @param list<string> $reals
     * @return array<string, list<string>>
     */
    public static function matchWildcards(array $patterns, array $reals): array
    {
        $matches = [];
        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;
            $regex = match (true) {
                $pattern === '*' => '[-.a-z0-9]+/[-.a-z0-9]+',
                str_contains($pattern, '/') => str_replace('\*', '[-.a-z0-9]+', preg_quote($pattern, '#')),
                default => preg_quote($pattern, '#') . '/[-.a-z0-9]+',
            };
            foreach ($reals as $real) {
                if (preg_match('#^' . $regex . '$#i', (string) $real)) {
                    $matches[$pattern][] = (string) $real;
                }
            }
        }
        return $matches;
    }

    /** Whether a file is of a media type, by mime prefix or by extension list. */
    public static function matches(string $type, string $mime, string $extension, array $audioExtensions, array $videoExtensions): bool
    {
        if (str_starts_with($mime, $type . '/')) {
            return true;
        }
        if ($extension === '') {
            return false;
        }
        $extension = strtolower($extension);
        return match ($type) {
            'image' => in_array($extension, self::IMAGE_EXTENSIONS, true),
            'audio' => in_array($extension, $audioExtensions, true),
            'video' => in_array($extension, $videoExtensions, true),
            default => $type === $extension,
        };
    }
}
