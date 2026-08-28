<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Header values from a plugin or theme file. The labels (Plugin Name,
 * Theme Name, Version) are the published file-header contract; the
 * reader is a line scan of the first 8 KB, never PHP execution.
 */
final class FileHeaders
{
    /**
     * @param list<string> $labels
     * @return array<string, string>
     */
    public static function values(string $file, array $labels): array
    {
        $found = array_fill_keys($labels, '');
        if (!is_file($file) || !is_readable($file)) {
            return $found;
        }
        $chunk = (string) file_get_contents($file, false, null, 0, 8192);
        foreach (preg_split("/\r\n|\n|\r/", $chunk) ?: [] as $line) {
            $line = ltrim($line, " \t/*#@");
            foreach ($labels as $label) {
                $prefix = $label . ':';
                if ($found[$label] === '' && strncasecmp($line, $prefix, strlen($prefix)) === 0) {
                    $found[$label] = trim(substr($line, strlen($prefix)));
                }
            }
        }
        return $found;
    }
}
