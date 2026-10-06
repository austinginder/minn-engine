<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Runtime\Runtime;

/**
 * The icon a file type is shown with, as wp_mime_type_icon finds it (probe
 * plugin-helpers): the icon folders (core's wp-includes/images/media, and
 * any icon_dirs adds) read once a request into a name => address map, an
 * icon of the asked kind (.png or .svg) preferred where both exist; then
 * the first name the type answers to: an attachment's file extension and
 * the kind of file it is, the type, its two halves, the type with an
 * underscore for the slash. Nothing answering is the default icon.
 */
final class Icons
{
    /** Core's icons, each kept as .png and .svg. */
    private const CORE = ['archive', 'audio', 'code', 'default', 'document', 'interactive', 'spreadsheet', 'text', 'video'];

    /** The icon's address for a type or an attachment id. */
    public static function forType(int|string $mime, string $preferred): string|false
    {
        $postId = 0;
        $names = [];
        if (is_numeric($mime) && (int) $mime > 0) {
            $post = \get_post((int) $mime);
            if (!$post instanceof \WP_Post) {
                return false;
            }
            $postId = $post->ID;
            $ext = strtolower((string) pathinfo((string) \get_attached_file($postId), PATHINFO_EXTENSION));
            $names = $ext === '' ? [] : array_filter([$ext, \wp_ext2type($ext)]);
            $mime = (string) $post->post_mime_type;
        }
        $mime = (string) $mime;
        if ($mime === '' || $mime === '0') {
            return false;
        }
        $names = [...$names, $mime, ...explode('/', $mime, 2), str_replace('/', '_', $mime)];
        $icons = self::map($preferred);
        $found = $icons['default'] ?? false;
        foreach ($names as $name) {
            if (isset($icons[$name])) {
                $found = $icons[$name];
                break;
            }
        }
        return \apply_filters('wp_mime_type_icon', $found, $mime, $postId);
    }

    /** Every icon by name, read once a request with the first asker's preferred kind. @return array<string, string> */
    private static function map(string $preferred): array
    {
        $cached = Runtime::current()->get('mime_type_icons');
        if (is_array($cached)) {
            return $cached;
        }
        $coreDir = (string) \apply_filters('icon_dir', ABSPATH . WPINC . '/images/media');
        $dirs = (array) \apply_filters('icon_dirs', [$coreDir => \apply_filters('icon_dir_uri', \includes_url('images/media'))]);
        $icons = [];
        foreach ($dirs as $dir => $uri) {
            foreach (self::files((string) $dir, $coreDir, $preferred) as $name => $file) {
                $icons[$name] ??= rtrim((string) $uri, '/') . '/' . $file;
            }
        }
        Runtime::current()->set('mime_type_icons', $icons);
        return $icons;
    }

    /** A folder's icons by name; core's are known, a plugin's folder is read. @return array<string, string> */
    private static function files(string $dir, string $coreDir, string $preferred): array
    {
        $ext = in_array($preferred, ['.png', '.svg'], true) ? $preferred : '.png';
        if ($dir === $coreDir) {
            return array_combine(self::CORE, array_map(static fn (string $name): string => $name . $ext, self::CORE));
        }
        $files = [];
        foreach (glob(rtrim($dir, '/') . '/*.{png,gif,jpg,svg}', GLOB_BRACE) ?: [] as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            if (!isset($files[$name]) || str_ends_with($path, $preferred)) {
                $files[$name] = basename($path);
            }
        }
        return $files;
    }
}
