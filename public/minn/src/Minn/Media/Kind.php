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
