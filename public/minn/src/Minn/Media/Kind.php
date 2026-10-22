<?php

declare(strict_types=1);

namespace Minn\Media;

/** Whether an attachment is an image, audio or video (by its MIME type) or of a given extension. */
final class Kind
{
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
     * (image, audio/*, image/jpeg, *), the last pattern first; a pattern with
     * no matches is omitted (probe post-remaining).
     *
     * @param list<string> $patterns
     * @param list<string> $reals
     * @return array<string, list<string>>
     */
    public static function matchWildcards(array $patterns, array $reals): array
    {
        $matches = [];
        foreach (array_reverse($patterns) as $pattern) {
            $pattern = (string) $pattern;
            $quoted = preg_quote($pattern, '#');
            // A type/subtype matches whole, a star standing for one name; a bare
            // type matches from the start, then anywhere, a star for anything.
            $passes = str_contains($pattern, '/')
                ? ['#^' . str_replace('\*', '[-._a-z0-9]+', $quoted) . '$#']
                : ['#^' . str_replace('\*', '.*', $quoted) . '#', '#' . str_replace('\*', '.*', $quoted) . '#'];
            foreach ($passes as $regex) {
                foreach ($reals as $real) {
                    if (preg_match($regex, (string) $real) && !in_array((string) $real, $matches[$pattern] ?? [], true)) {
                        $matches[$pattern][] = (string) $real;
                    }
                }
            }
        }
        return $matches;
    }

    /**
     * Whether a file is of a media type (wp_attachment_is): an image, audio
     * or video by its mime type alone, whatever its extension says; any
     * other type by the file's extension.
     */
    public static function matches(string $type, string $mime, string $extension): bool
    {
        if (in_array($type, ['image', 'audio', 'video'], true)) {
            return str_starts_with($mime, $type . '/');
        }
        return $extension !== '' && strtolower($extension) === $type;
    }
}
