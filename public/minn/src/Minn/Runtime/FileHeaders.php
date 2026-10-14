<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The headers at the top of a theme, plugin or translation file, as
 * WordPress's get_file_data reads them: the first eight kilobytes, each
 * header by its label (the extra ones extra_{context}_headers adds first).
 */
final class FileHeaders
{
    /**
     * A file's headers by field (get_file_data), empty for a header it does
     * not carry.
     *
     * @return array<string, string>
     */
    public static function read(mixed $file, mixed $defaultHeaders, mixed $context = ''): array
    {
        $handle = @fopen((string) $file, 'r');
        $head = $handle ? (string) fread($handle, 8 * KB_IN_BYTES) : '';
        if ($handle) {
            fclose($handle);
        }
        $head = str_replace("\r", "\n", $head);
        $headers = $context !== '' ? Runtime::hooks()->filter("extra_{$context}_headers", [[]]) : [];
        $headers = array_merge(array_fill_keys($headers, ''), $defaultHeaders);
        $out = [];
        foreach ($headers as $field => $regex) {
            $label = is_string($regex) && $regex !== '' ? $regex : $field;
            if (preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote((string) $label, '/') . ':(.*)$/mi', $head, $m) && $m[1]) {
                $out[$field] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $m[1]));
            } else {
                $out[$field] = '';
            }
        }
        return $out;
    }
}
