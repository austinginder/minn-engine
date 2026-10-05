<?php

declare(strict_types=1);

namespace Minn\Media;

/** Whether an attachment is an image, audio, video, or a given extension, judged by its MIME type first and its file extension second. */
final class Kind
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'gif', 'png', 'webp', 'avif', 'heic'];

    /**
     * The media library's filter groups: a MIME pattern (a top-level type, or
     * a comma list of full types) => its plural label, its manage label, and
     * the singular and plural count labels.
     */
    public const POST_MIME_TYPES = [
        'image' => ['Images', 'Manage Images', 'Image <span class="count">(%s)</span>', 'Images <span class="count">(%s)</span>'],
        'audio' => ['Audio', 'Manage Audio', 'Audio <span class="count">(%s)</span>', 'Audio <span class="count">(%s)</span>'],
        'video' => ['Video', 'Manage Video', 'Video <span class="count">(%s)</span>', 'Video <span class="count">(%s)</span>'],
        'application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
            . 'application/vnd.ms-word.document.macroEnabled.12,application/vnd.ms-word.template.macroEnabled.12,'
            . 'application/vnd.oasis.opendocument.text,application/vnd.apple.pages,application/pdf,application/vnd.ms-xpsdocument,'
            . 'application/oxps,application/rtf,application/wordperfect,application/octet-stream'
            => ['Documents', 'Manage Documents', 'Document <span class="count">(%s)</span>', 'Documents <span class="count">(%s)</span>'],
        'application/vnd.apple.numbers,application/vnd.oasis.opendocument.spreadsheet,application/vnd.ms-excel,'
            . 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroEnabled.12,'
            . 'application/vnd.ms-excel.sheet.binary.macroEnabled.12'
            => ['Spreadsheets', 'Manage Spreadsheets', 'Spreadsheet <span class="count">(%s)</span>', 'Spreadsheets <span class="count">(%s)</span>'],
        'application/x-gzip,application/rar,application/x-tar,application/zip,application/x-7z-compressed'
            => ['Archives', 'Manage Archives', 'Archive <span class="count">(%s)</span>', 'Archives <span class="count">(%s)</span>'],
    ];

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
