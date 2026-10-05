<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * Where a mailer's debug text goes, as its Debugoutput setting names it:
 * "echo" (a timestamp and a tab, continuation lines indented under the
 * text), "html" (entity-escaped, line breaks dropped, ending in <br>),
 * "error_log", a PSR-3 logger's debug(), or any other callable, which gets
 * the text and its level. The two printed forms come back as text for the
 * caller to print; the others are delivered here and return null.
 */
final class DebugOutput
{
    private const INDENT = "\n                   \t                  ";

    /** Debug text from the SMTP client: the text to print, or null once delivered; its html form carries a timestamp. */
    public static function smtp(mixed $how, string $text, int $level): ?string
    {
        return $how === 'html' ? date('Y-m-d H:i:s') . ' ' . self::html($text) : self::write($how, $text, $level);
    }

    /** Debug text from the mailer itself: the text to print, or null once delivered; its html form has no timestamp. */
    public static function mailer(mixed $how, string $text, int $level): ?string
    {
        return $how === 'html' ? self::html($text) : self::write($how, $text, $level);
    }

    private static function write(mixed $how, string $text, int $level): ?string
    {
        if (is_object($how) && method_exists($how, 'debug') && !$how instanceof \Closure) {
            $how->debug($text);
            return null;
        }
        if ($how === 'error_log') {
            error_log($text);
            return null;
        }
        if ($how !== 'echo' && is_callable($how)) {
            $how($text, $level);
            return null;
        }
        return date('Y-m-d H:i:s') . "\t" . str_replace("\n", self::INDENT, trim(str_replace(["\r\n", "\r"], "\n", $text))) . "\n";
    }

    private static function html(string $text): string
    {
        return htmlentities((string) preg_replace('/[\r\n]+/', '', $text), ENT_QUOTES, 'UTF-8') . "<br>\n";
    }
}
