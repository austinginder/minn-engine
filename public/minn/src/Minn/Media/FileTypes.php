<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Auth\Can;
use Minn\Json;
use Minn\Runtime\Runtime;

/**
 * File types as WordPress knows them (data/mime.json): every extension's
 * MIME type, the ones a user may upload, the kind each extension is (image,
 * audio, document...), and the type a file name's extension says, each
 * list through the filter plugins hook.
 */
final class FileTypes
{
    /** @var array<string, mixed>|null */
    private static ?array $table = null;

    /**
     * The table: mime (extensions => type), allowed (the ones uploads take
     * by default) and ext2type (kind => extensions).
     *
     * @return array<string, mixed>
     */
    public static function table(): array
    {
        return self::$table ??= Json::file(Runtime::current()->engineDir . '/data/mime.json');
    }

    /** Every extension's MIME type (wp_get_mime_types), as mime_types leaves them. */
    public static function all(): mixed
    {
        return Runtime::hooks()->filter('mime_types', [self::table()['mime']]);
    }

    /**
     * The types a user (the request's, unless one is given) may upload
     * (get_allowed_mime_types): HTML and JavaScript too for one with
     * unfiltered_html; then upload_mimes.
     */
    public static function allowed(mixed $user = null): mixed
    {
        $types = self::table()['allowed'];
        if ($user === null ? Can::current('unfiltered_html') : Can::user($user, 'unfiltered_html')) {
            $types += array_intersect_key(self::table()['mime'], ['htm|html' => 0, 'js' => 0]);
        }
        return Runtime::hooks()->filter('upload_mimes', [$types, $user]);
    }

    /**
     * The extension and type a file name has among the types given (the
     * allowed ones by default), false for each when none matches (wp_check_filetype).
     *
     * @return array{ext: string|false, type: mixed}
     */
    public static function check(mixed $filename, mixed $mimes = null): array
    {
        $mimes ??= self::allowed();
        $type = false;
        $ext = false;
        foreach ($mimes as $pattern => $mime) {
            if (preg_match('!\.(' . $pattern . ')$!i', (string) $filename, $m)) {
                $type = $mime;
                $ext = $m[1];
                break;
            }
        }
        return ['ext' => $ext, 'type' => $type];
    }

    /** The extensions of each kind (wp_get_ext_types), as ext2type leaves them. */
    public static function kinds(): mixed
    {
        return Runtime::hooks()->filter('ext2type', [self::table()['ext2type']]);
    }

    /** The kind an extension is (wp_ext2type), or null. */
    public static function kindOf(mixed $ext): mixed
    {
        $ext = strtolower((string) $ext);
        foreach (self::kinds() as $kind => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $kind;
            }
        }
        return null;
    }
}
