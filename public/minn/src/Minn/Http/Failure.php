<?php

declare(strict_types=1);

namespace Minn\Http;

use Throwable;

/**
 * What the public sees when the engine cannot answer: a plain page with no
 * detail, while the detail goes to the log. Installed once per request,
 * it turns display_errors off (unless the site's own WP_DEBUG_DISPLAY asks
 * for them) and catches the fatal errors PHP would otherwise print.
 */
final class Failure
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    public static function install(): void
    {
        $display = defined('WP_DEBUG_DISPLAY') ? (bool) WP_DEBUG_DISPLAY : (defined('WP_DEBUG') && WP_DEBUG);
        ini_set('display_errors', $display ? '1' : '0');
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            ini_set('log_errors', '1');
            ini_set('error_log', is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : ABSPATH . 'wp-content/debug.log');
        }
        register_shutdown_function(static function () use ($display): void {
            $error = error_get_last();
            if ($error === null || ($error['type'] & self::FATAL) === 0 || headers_sent()) {
                return;
            }
            if (!$display) {
                self::internal()->send();
            }
        });
    }

    /** Logs the cause; the response says only that something went wrong. */
    public static function report(Throwable $error): Response
    {
        error_log(sprintf('Minn Engine: %s: %s in %s:%d', $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
        return self::internal();
    }

    public static function internal(): Response
    {
        return self::page(500, 'Something went wrong', 'The site hit an error while answering this request. The site owner has the details in the log.')
            ->withHeader('Cache-Control', 'no-store');
    }

    public static function databaseUnavailable(): Response
    {
        return self::page(503, 'Error establishing a database connection', 'The site could not reach its database. If you are the site owner, check the database server and the credentials in wp-config.php.')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Retry-After', '60');
    }

    private static function page(int $status, string $title, string $text): Response
    {
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b0b0d;color:#ececed;font:16px/1.6 "Helvetica Neue",sans-serif}'
            . 'main{max-width:34rem;padding:2rem}h1{font-size:1.4rem;margin:0 0 .6rem}p{margin:0;color:#9d9da7}</style></head>'
            . '<body><main><h1>' . $title . '</h1><p>' . $text . '</p></main></body></html>',
            $status,
        );
    }
}
