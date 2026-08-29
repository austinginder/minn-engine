<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Records every call into a generated placeholder while a site opts in by
 * having wp-content/minn-placeholder-trace.log on disk: one line per call
 * with the symbol, the plugin file that called it, and the request. The
 * log says which placeholders deserve behaviour; an absent file costs one
 * stat per request.
 */
final class PlaceholderTrace
{
    private static ?string $file = null;
    private static bool $checked = false;

    public static function hit(string $symbol): void
    {
        if (!self::$checked) {
            self::$checked = true;
            $candidate = (defined('ABSPATH') ? ABSPATH : '') . 'wp-content/minn-placeholder-trace.log';
            self::$file = is_file($candidate) ? $candidate : null;
        }
        if (self::$file === null) {
            return;
        }
        $caller = '';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6) as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file !== '' && !str_contains($file, '/wp-api/') && !str_contains($file, '/src/Minn/')) {
                $caller = $file . ':' . ($frame['line'] ?? 0);
                break;
            }
        }
        @file_put_contents(self::$file, $symbol . "\t" . $caller . "\t" . (Runtime::booted() ? (Runtime::current()->request?->path ?? 'cli') : 'cli') . "\n", FILE_APPEND);
    }
}
