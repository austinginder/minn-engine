<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * A message cut into the lines SMTP DATA sends. Any line break (CRLF, CR
 * or LF) ends a line. A line longer than 998 characters is cut at its last
 * space within the first 998 (the space dropped), or at 997 when there is
 * none; in the header block (when the first line reads "Name: ..." with no
 * space in the name, up to the first empty line) each continuation starts
 * with a tab. Dot stuffing is the sender's job, after the cut.
 */
final class DataLines
{
    /**
     * The lines to send, without their line endings.
     *
     * @return list<string>
     */
    public static function split(string $message): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
        $colon = strpos($lines[0], ':');
        $inHeaders = $colon !== false && $colon > 0 && !str_contains(substr($lines[0], 0, $colon), ' ');
        $out = [];
        foreach ($lines as $line) {
            if ($inHeaders && $line === '') {
                $inHeaders = false;
            }
            while (strlen($line) > SmtpSession::MAX_LINE) {
                $space = strrpos(substr($line, 0, SmtpSession::MAX_LINE), ' ');
                $cut = $space ?: SmtpSession::MAX_LINE - 1;
                $out[] = substr($line, 0, $cut);
                $line = ($inHeaders ? "\t" : '') . substr($line, $space ? $cut + 1 : $cut);
            }
            $out[] = $line;
        }
        return $out;
    }
}
