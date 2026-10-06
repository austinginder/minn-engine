<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A file's type from its content as much as its name, as the reference's
 * wp_check_filetype_and_ext decides it (probe upload-filters): an image the
 * server can measure is what its bytes say, and a name with the wrong image
 * extension is corrected (a.png holding a JPEG becomes a.jpg); anything
 * claiming to be such an image and not being one has no type; any other
 * type must match what the content is, plain text standing for a few text
 * types and an office or archive container for the document types; and
 * the type must be one the site allows.
 */
final class FileTypeCheck
{
    /** The image types measured by their bytes, and the extension each takes. */
    public const IMAGES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/bmp' => 'bmp', 'image/tiff' => 'tif', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/heic' => 'heic'];

    private const TEXT_TYPES = ['text/plain', 'text/csv', 'application/csv', 'text/richtext', 'text/tsv', 'text/vtt'];

    private const CONTAINERS = ['application/octet-stream', 'application/encrypted', 'application/CDFV2-encrypted', 'application/zip'];

    /**
     * ext, type, proper_filename, and the content's own type (real_mime).
     *
     * @param array<string, string>|null $mimes
     * @param array<string, string> $images getimagesize_mimes_to_exts
     * @return array{ext: string|false, type: string|false, proper_filename: string|false, real_mime: string|false}
     */
    public static function check(string $file, string $filename, ?array $mimes, array $images): array
    {
        ['ext' => $ext, 'type' => $type] = \wp_check_filetype($filename, $mimes);
        $proper = false;
        $real = false;
        if (!is_file($file)) {
            return ['ext' => $ext, 'type' => $type, 'proper_filename' => $proper, 'real_mime' => $real];
        }
        if ($type && isset($images[$type])) {
            $real = self::imageMime($file);
            if ($real === false || !isset($images[$real])) {
                return ['ext' => false, 'type' => false, 'proper_filename' => false, 'real_mime' => $real];
            }
            if ($real !== $type) {
                $parts = explode('.', $filename);
                array_pop($parts);
                $parts[] = $images[$real];
                $proper = implode('.', $parts);
                ['ext' => $ext, 'type' => $type] = \wp_check_filetype($proper, $mimes);
            }
        } elseif ($type && function_exists('finfo_open')) {
            $real = (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
            if (!self::contentFits((string) $type, $real)) {
                $ext = false;
                $type = false;
            }
        }
        if ($type && !in_array($type, \get_allowed_mime_types(), true)) {
            $ext = false;
            $type = false;
        }
        return ['ext' => $ext, 'type' => $type, 'proper_filename' => $proper, 'real_mime' => $real];
    }

    /** What an image's bytes say it is, or false when they are not an image. */
    private static function imageMime(string $file): string|false
    {
        $size = @getimagesize($file);
        return is_array($size) && isset($size['mime']) ? (string) $size['mime'] : false;
    }

    /** Whether content of the real type may carry a name of the given type. */
    private static function contentFits(string $type, string $real): bool
    {
        if (in_array($real, self::CONTAINERS, true)) {
            return (bool) preg_match('#^application/(vnd\.(ms-|openxmlformats|oasis\.opendocument|apple)|msword|zip|x-zip|x-7z|x-rar|rar|x-tar|gzip|x-gzip|octet-stream)#', $type);
        }
        if (str_starts_with($real, 'video/') || str_starts_with($real, 'audio/')) {
            return strtok($real, '/') === strtok($type, '/');
        }
        if ($real === 'text/plain') {
            return in_array($type, self::TEXT_TYPES, true);
        }
        if ($real === 'application/csv' || $real === 'text/csv') {
            return in_array($type, ['text/csv', 'application/csv', 'text/plain'], true);
        }
        if ($real === 'text/rtf') {
            return in_array($type, ['text/rtf', 'application/rtf', 'text/richtext'], true);
        }
        return $type === $real;
    }
}
