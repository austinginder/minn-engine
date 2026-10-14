<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\SiteLanguage;
use Minn\Media\FileTypes;
use Minn\Support\Accents;
use Minn\Support\Paths;

/**
 * Files as WordPress's file functions name and remove them: a name made
 * safe to store, a name no file in the folder has, a file's type from its
 * content as much as its name, and a file deleted (only inside the folder
 * it should be in).
 */
final class FileNames
{
    /**
     * A file name safe to store (sanitize_file_name): accents transliterated,
     * the special characters out, spaces and runs of dashes as one dash, no
     * dots or dashes at the ends; an inner part that looks like an extension
     * but is no type the site allows gets an underscore; a name that is only
     * an extension becomes unnamed-file.{ext}; as sanitize_file_name leaves it.
     */
    public static function sanitize(mixed $filename): mixed
    {
        $raw = (string) $filename;
        $hooks = Runtime::hooks();
        $special = ['?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr(0)];
        $special = $hooks->filter('sanitize_file_name_chars', [$special, $raw]);
        $filename = Accents::strip(str_replace("\x00", '', $raw), SiteLanguage::locale());
        $filename = (string) preg_replace("#\x{00a0}#siu", ' ', $filename);
        $filename = str_replace($special, '', $filename);
        $filename = str_replace(['%20', '+'], '-', $filename);
        $filename = (string) preg_replace('/\.{2,}/', '.', $filename);
        $filename = (string) preg_replace('/[\r\n\t -]+/', '-', $filename);
        $filename = trim($filename, '.-_');
        $filename = self::neutralizeInnerExtensions($filename);
        if (!str_contains($filename, '.')) {
            $named = FileTypes::check('test.' . $filename, FileTypes::table()['mime']);
            if ($named['ext'] !== false && $named['ext'] === $filename) {
                $filename = 'unnamed-file.' . $named['ext'];
            }
        }
        return $hooks->filter('sanitize_file_name', [$filename, $raw]);
    }

    /** An inner part of a file name that looks like an extension (two to five letters, maybe a digit) and is no allowed type, given an underscore. */
    public static function neutralizeInnerExtensions(string $filename): string
    {
        $parts = explode('.', $filename);
        if (count($parts) <= 2) {
            return $filename;
        }
        $first = array_shift($parts);
        $last = array_pop($parts);
        $allowed = array_keys((array) FileTypes::allowed());
        foreach ($parts as $i => $part) {
            $known = array_filter($allowed, static fn (string $pattern): bool => preg_match('!^(' . $pattern . ')$!i', $part) === 1) !== [];
            $parts[$i] = preg_match('/^[a-zA-Z]{2,5}\d?$/', $part) === 1 && !$known ? $part . '_' : $part;
        }
        return implode('.', [$first, ...$parts, $last]);
    }

    /**
     * A clean name no file in the folder has (wp_unique_filename): the name
     * sanitized, its extension lowercased, a callback's name when one is
     * given, else -1, -2... before the extension; as wp_unique_filename
     * leaves it.
     */
    public static function unique(mixed $dir, mixed $filename, mixed $callback = null): mixed
    {
        $filename = (string) self::sanitize($filename);
        $dot = strrpos($filename, '.');
        $ext = $dot === false ? '' : substr($filename, $dot);
        $name = $dot === false ? $filename : substr($filename, 0, $dot);
        FileTypes::check($filename);
        $uploads = rtrim((string) (Attachments::uploadDirMade()['path'] ?? ''), '/');
        $dir = rtrim((string) $dir, '/');
        $number = '';
        $hooks = Runtime::hooks();
        $listed = $dir === $uploads ? $hooks->filter('pre_wp_unique_filename_file_list', [null, $dir, $filename]) : null;
        $taken = is_array($listed) ? static fn (string $candidate): bool => in_array($candidate, $listed, true) : static fn (string $candidate): bool => file_exists($dir . '/' . $candidate);
        if ($callback !== null && is_callable($callback)) {
            $candidate = (string) $callback($dir, $filename, $ext);
        } else {
            $number = preg_match('/-(?:\d+x\d+|scaled|rotated)$/', $name) === 1 ? 1 : '';
            $candidate = $name . ($number === '' ? '' : '-' . $number) . strtolower($ext);
            while ($taken($candidate)) {
                $number = $number === '' ? 1 : $number + 1;
                $candidate = $name . '-' . $number . strtolower($ext);
            }
        }
        return $hooks->filter('wp_unique_filename', [$candidate, $ext, $dir, $callback, [], $number]);
    }

    /**
     * A file's extension and type from its content as much as its name
     * (wp_check_filetype_and_ext), with a corrected name when an image
     * carried the wrong extension; as wp_check_filetype_and_ext leaves them.
     */
    public static function checkUpload(mixed $file, mixed $filename, mixed $mimes = null): mixed
    {
        $hooks = Runtime::hooks();
        $images = (array) $hooks->filter('getimagesize_mimes_to_exts', [FileTypeCheck::IMAGES]);
        $checked = FileTypeCheck::check((string) $file, (string) $filename, is_array($mimes) ? $mimes : null, $images);
        $result = ['ext' => $checked['ext'], 'type' => $checked['type'], 'proper_filename' => $checked['proper_filename']];
        return $hooks->filter('wp_check_filetype_and_ext', [$result, $file, $filename, $mimes, $checked['real_mime']]);
    }

    /** A file deleted (wp_delete_file), as wp_delete_file names it; whether it went. */
    public static function delete(mixed $file): bool
    {
        $delete = Runtime::hooks()->filter('wp_delete_file', [(string) $file]);
        if ($delete !== '' && is_file((string) $delete)) {
            return @unlink((string) $delete);
        }
        return false;
    }

    /** A file deleted only when it is inside the folder (wp_delete_file_from_directory); false when it is not, or not there. */
    public static function deleteFromDirectory(mixed $file, mixed $directory): bool
    {
        $realFile = realpath(Paths::normalize((string) $file));
        $realDirectory = realpath(Paths::normalize((string) $directory));
        if ($realFile === false || $realDirectory === false || !str_starts_with(Paths::normalize($realFile), rtrim(Paths::normalize($realDirectory), '/\\') . '/')) {
            return false;
        }
        self::delete($file);
        return true;
    }
}
