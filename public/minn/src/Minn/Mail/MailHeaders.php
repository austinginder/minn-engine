<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * The headers argument of wp_mail() read the way the reference reads it:
 * a string split into lines (CRLF or LF) or a list of lines; a line
 * without a colon is skipped. From gives an address and a name (quotes
 * dropped); Cc, Bcc and Reply-To add comma-separated entries (a comma
 * inside quotes still splits); Content-Type gives a type and either a
 * charset or a boundary (a boundary leaves the charset empty); every
 * other header is kept by name, the last of a name winning.
 */
final readonly class MailHeaders
{
    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $replyTo
     * @param array<string, string> $custom
     */
    public function __construct(
        public ?string $fromEmail,
        public ?string $fromName,
        public array $cc,
        public array $bcc,
        public array $replyTo,
        public ?string $contentType,
        public ?string $charset,
        public string $boundary,
        public array $custom,
    ) {
    }

    /** The headers argument, as a string of lines or a list of them. */
    public static function parse(mixed $headers): self
    {
        $lines = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers));
        $found = ['from' => [null, null], 'cc' => [], 'bcc' => [], 'reply-to' => [], 'type' => [null, null, ''], 'custom' => []];
        foreach ($lines as $line) {
            if (!is_string($line) || !str_contains($line, ':')) {
                continue;
            }
            [$name, $content] = array_map('trim', explode(':', trim($line), 2));
            match (strtolower($name)) {
                'from' => $found['from'] = self::from($content),
                'content-type' => $found['type'] = self::contentType($content, $found['type']),
                'cc', 'bcc', 'reply-to' => $found[strtolower($name)] = [...$found[strtolower($name)], ...explode(',', $content)],
                default => $found['custom'][$name] = $content,
            };
        }
        return new self($found['from'][0], $found['from'][1], $found['cc'], $found['bcc'], $found['reply-to'], $found['type'][0], $found['type'][1], $found['type'][2], $found['custom']);
    }

    /**
     * One recipient entry as [address, name]: "Name <address>" (the name as written) or a bare address.
     *
     * @return array{0: string, 1: string}
     */
    public static function recipient(string $entry): array
    {
        if (preg_match('/(.*)<(.+)>/', $entry, $m)) {
            return [trim($m[2]), trim($m[1])];
        }
        return [trim($entry), ''];
    }

    /** @return array{0: string|null, 1: string|null} */
    private static function from(string $content): array
    {
        $bracket = strpos($content, '<');
        if ($bracket === false) {
            return [$content === '' ? null : $content, null];
        }
        return [trim(str_replace('>', '', substr($content, $bracket + 1))), trim(str_replace('"', '', substr($content, 0, $bracket)))];
    }

    /**
     * @param array{0: string|null, 1: string|null, 2: string} $current
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    private static function contentType(string $content, array $current): array
    {
        if (!str_contains($content, ';')) {
            return $content === '' ? $current : [$content, $current[1], $current[2]];
        }
        [$type, $params] = explode(';', $content);
        if (stripos($params, 'charset=') !== false) {
            return [trim($type), trim(str_ireplace(['charset=', '"'], '', $params)), $current[2]];
        }
        if (stripos($params, 'boundary=') !== false) {
            return [trim($type), '', trim(str_ireplace(['boundary=', '"'], '', $params))];
        }
        return [trim($type), $current[1], $current[2]];
    }
}
