<?php

declare(strict_types=1);

namespace Minn\Http;

use Minn\Support\Html;
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

    /** @var null|callable(array{type:int,file:string,line:int,message:string}): void */
    private static $recorder = null;
    /** Whether this site asked to see errors (WP_DEBUG_DISPLAY, else WP_DEBUG). */
    private static bool $display = false;
    /** Whether a failure right now may be recorded against an extension: only while the extensions boot. */
    private static bool $armed = false;

    /**
     * Opens the window in which a failure is an extension's fault: while
     * the plugins and the theme's functions file load and the boot actions
     * fire, code that fails fails for every visitor, so pausing it heals
     * the site. A failure after that (a route, a shortcode, a template)
     * answers to one request's input and is never grounds for a pause;
     * it is logged and the request ends in the error page.
     */
    public static function armRecovery(): void
    {
        self::$armed = true;
    }

    /** Closes the window armRecovery() opened. */
    public static function disarmRecovery(): void
    {
        self::$armed = false;
    }

    /**
     * What to do with a fatal beyond showing the page: the engine records
     * the extension it came from so the next request loads without it.
     * Installed once the runtime is up, since it needs the database.
     *
     * @param callable(array{type:int,file:string,line:int,message:string}): void $recorder
     */
    public static function onFatal(callable $recorder): void
    {
        self::$recorder = $recorder;
    }

    /** Installs the error handlers; detail is shown only under WP_DEBUG_DISPLAY. */
    public static function install(): void
    {
        $display = defined('WP_DEBUG_DISPLAY') ? (bool) WP_DEBUG_DISPLAY : (defined('WP_DEBUG') && WP_DEBUG);
        self::$display = $display;
        ini_set('display_errors', $display ? '1' : '0');
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            ini_set('log_errors', '1');
            ini_set('error_log', is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : ABSPATH . 'wp-content/debug.log');
        }
        register_shutdown_function(static function () use ($display): void {
            $error = error_get_last();
            if ($error === null || ($error['type'] & self::FATAL) === 0) {
                return;
            }
            self::record($error);
            if (!$display && !headers_sent()) {
                self::discardOutput();
                self::internal()->send();
            }
        });
    }

    /** Whatever a half-finished render left in the output buffers goes; the error page must stand alone. */
    private static function discardOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /** Logs the cause; the response says only that something went wrong. */
    public static function report(Throwable $error): Response
    {
        error_log(sprintf('Minn Engine: %s: %s in %s:%d', $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
        self::discardOutput();
        self::record(['type' => E_ERROR, 'file' => $error->getFile(), 'line' => $error->getLine(), 'message' => $error->getMessage()]);
        return self::$display ? self::detailed($error::class, $error->getMessage(), $error->getFile(), $error->getLine()) : self::internal();
    }

    /**
     * Hands the failure to the recorder, and never lets recovery itself
     * take the request down: a site that cannot record a pause should
     * still answer with the error page.
     *
     * @param array{type: int, file: string, line: int, message: string} $error
     */
    private static function record(array $error): void
    {
        if (self::$recorder === null || !self::$armed) {
            return;
        }
        $recorder = self::$recorder;
        self::$recorder = null;
        try {
            $recorder($error);
        } catch (Throwable $failed) {
            error_log('Minn Engine: recovery could not record the failure: ' . $failed->getMessage());
        }
    }

    /** The 500 page. */
    public static function internal(): Response
    {
        return self::page(500, 'Something went wrong', 'The site hit an error while answering this request. The site owner has the details in the log.')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** The 503 page the reference shows when the database cannot be reached. */
    public static function databaseUnavailable(): Response
    {
        return self::page(503, 'Error establishing a database connection', 'The site could not reach its database. If you are the site owner, check the database server and the credentials in wp-config.php.')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Retry-After', '60');
    }

    /**
     * The same page with the cause on it, for a site that asked to see
     * errors. Only ever reached when WP_DEBUG_DISPLAY (or WP_DEBUG) is on:
     * a site that has not asked never learns this much from a response.
     */
    public static function detailed(string $class, string $message, string $file, int $line): Response
    {
        $detail = '<p class="minn-error-detail"><code>' . Html::esc($class) . '</code>: ' . Html::esc($message) . '</p>'
            . '<p class="minn-error-where">' . Html::esc($file) . ' <b>line ' . $line . '</b></p>';
        return self::page(500, 'Something went wrong', 'The site hit an error while answering this request. This detail shows because the site has debug display switched on.', $detail)
            ->withHeader('Cache-Control', 'no-store');
    }

    private static function page(int $status, string $title, string $text, string $detail = ''): Response
    {
        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b0b0d;color:#ececed;font:16px/1.6 "Helvetica Neue",sans-serif}'
            . 'main{max-width:46rem;padding:2rem}h1{font-size:1.4rem;margin:0 0 .6rem}p{margin:0;color:#9d9da7}'
            . '.minn-error-detail{margin:1.25rem 0 .35rem;color:#ececed}.minn-error-detail code{color:#f0a1a1}'
            . '.minn-error-where{font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;color:#6f6f79;word-break:break-all}'
            . '.minn-error-where b{color:#9d9da7;font-weight:600}</style></head>'
            . '<body><main><h1>' . $title . '</h1><p>' . $text . '</p>' . $detail . '</main></body></html>',
            $status,
        );
    }
}
