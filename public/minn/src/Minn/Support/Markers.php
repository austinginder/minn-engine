<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The BEGIN/END marker blocks insert_with_markers() maintains in files like
 * .htaccess: the captured shape keeps everything outside the markers,
 * replaces the block inside them, and writes the reference's four-line
 * do-not-edit preamble ahead of the inserted lines.
 */
final class Markers
{
    /**
     * Replaces the lines between a marker's begin and end comments in a file.
     *
     * @param list<string> $lines
     */
    public static function write(string $file, string $marker, array $lines): bool
    {
        if (!file_exists($file) && !is_writable(dirname($file))) {
            return false;
        }
        $existing = file_exists($file) ? (string) file_get_contents($file) : '';
        $begin = "# BEGIN {$marker}";
        $end = "# END {$marker}";
        $pattern = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '/s';
        $preamble = "# The directives (lines) between \"BEGIN {$marker}\" and \"END {$marker}\" are\n"
            . "# dynamically generated, and should only be modified via WordPress filters.\n"
            . "# Any changes to the directives between these markers will be overwritten.\n";
        $block = $begin . "\n" . $preamble . implode("\n", $lines) . "\n" . $end;
        if (preg_match($pattern, $existing)) {
            $updated = (string) preg_replace($pattern, $block, $existing);
        } else {
            $updated = rtrim($existing) . ($existing === '' ? '' : "\n") . "\n" . $block . "\n";
        }
        return file_put_contents($file, $updated) !== false;
    }

    /**
     * The lines between a marker's begin and end comments, every block of it
     * in order. A line opens or closes a block when it contains the comment
     * anywhere (case-sensitive); lines that start with "#" are left out, so
     * the preamble and any nested markers are, while indented comments stay.
     * Lines split on "\n" only, and a block left open at the end of the file
     * keeps the empty line after its final newline.
     *
     * @return list<string>
     */
    public static function read(string $file, string $marker): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $lines = [];
        $inside = false;
        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            if (str_contains($line, "# END {$marker}")) {
                $inside = false;
            }
            if ($inside && !str_starts_with($line, '#')) {
                $lines[] = $line;
            }
            if (str_contains($line, "# BEGIN {$marker}")) {
                $inside = true;
            }
        }
        return $lines;
    }
}
