<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * A path's parts the way a mailer names attachments, safe for multibyte
 * names: the directory ('' when there is none), the last segment, its
 * extension (after the last dot, so ".hidden" is all extension) and the
 * name before it. Either slash separates; trailing ones are ignored.
 */
final class PathParts
{
    /**
     * The path's four parts.
     *
     * @return array{dirname: string, basename: string, extension: string, filename: string}
     */
    public static function of(string $path): array
    {
        $path = rtrim($path, '/\\');
        $cut = max((int) strrpos('/' . $path, '/'), (int) strrpos('/' . $path, '\\')) - 1;
        $dirname = $cut > 0 ? substr($path, 0, $cut) : '';
        $basename = $cut >= 0 ? substr($path, $cut + 1) : $path;
        $dot = strrpos($basename, '.');
        return [
            'dirname' => $dirname,
            'basename' => $basename,
            'extension' => $dot === false ? '' : substr($basename, $dot + 1),
            'filename' => $dot === false ? $basename : substr($basename, 0, $dot),
        ];
    }
}
