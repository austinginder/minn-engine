<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * Email addresses: the checks a mailer can apply (PHP's filter, the
 * RFC 5322 grammar with comments, quoted local parts and domain literals,
 * the HTML5 form rule), parsing an address list, and the domain of an
 * address in ASCII.
 */
final class AddressRules
{
    private const HTML5 = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD';

    /** Whether the address passes the named check ("php", "pcre", "pcre8", "html5", "noregex"). */
    public static function valid(string $address, string $pattern): bool
    {
        if (str_contains($address, "\n") || str_contains($address, "\r")) {
            return false;
        }
        return match ($pattern) {
            'pcre', 'pcre8' => self::rfc5322($address),
            'html5' => (bool) preg_match(self::HTML5, $address),
            default => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        };
    }

    /**
     * The addresses in a comma-separated list, each with its display name
     * (encoded words decoded into $charset); entries that are not valid
     * addresses are left out.
     *
     * @return list<array{name: string, address: string}>
     */
    public static function parseList(string $list, string $charset): array
    {
        $out = [];
        foreach (self::splitList($list) as $entry) {
            $entry = trim($entry);
            $name = '';
            $address = $entry;
            if (preg_match('/^(.*)<([^<>]+)>\s*$/s', $entry, $m)) {
                $name = trim(trim($m[1]), '"\'');
                $address = trim($m[2]);
                if (str_contains($name, '=?')) {
                    $name = (string) mb_convert_encoding(mb_decode_mimeheader($name), $charset, 'UTF-8');
                }
            }
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $out[] = ['name' => $name, 'address' => $address];
            }
        }
        return $out;
    }

    /** The address with its domain in ASCII (IDNA), the domain read from $charset first; unchanged when it cannot be. */
    public static function asciiDomain(string $address, string $charset): string
    {
        $at = strrpos($address, '@');
        if ($at === false || $charset === '' || !function_exists('idn_to_ascii')) {
            return $address;
        }
        $domain = substr($address, $at + 1);
        if (!Transfer::has8bit($domain)) {
            return $address;
        }
        $domain = (string) mb_convert_encoding($domain, 'UTF-8', $charset);
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ | IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        return $ascii === false ? $address : substr($address, 0, $at + 1) . $ascii;
    }

    /** The text as an RFC 822 quoted string when it holds a character that needs one. */
    public static function quoted(string $text): string
    {
        return preg_match('/[ ()<>@,;:\\\\"\/\[\]?=]/', $text) ? '"' . addcslashes($text, '\\"') . '"' : $text;
    }

    /** RFC 5322 addr-spec with comments and folding white space, a quoted local part or a domain literal; local part 64, label 63 characters. */
    private static function rfc5322(string $address): bool
    {
        $atext = "[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~-]";
        $pair = '\\\\[\x09\x20-\x7E]';
        $quoted = '"(?:[ \t]|[\x21\x23-\x5B\x5D-\x7E]|' . $pair . ')*"';
        $comment = '\((?:[ \t]|[\x21-\x27\x2A-\x5B\x5D-\x7E]|' . $pair . ')*\)';
        $cfws = '(?:[ \t]*' . $comment . ')*[ \t]*';
        $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
        $pattern = '/^' . $cfws . '(?<local>' . $atext . '+(?:\.' . $atext . '+)*|' . $quoted . ')' . $cfws . '@' . $cfws
            . '(?<domain>' . $label . '(?:\.' . $label . ')*|\[[^\[\]\\\\]*\])' . $cfws . '$/D';
        if (!preg_match($pattern, $address, $m) || strlen($m['local']) > 64 || strlen($m['local'] . '@' . $m['domain']) > 254) {
            return false;
        }
        if ($m['domain'][0] !== '[') {
            return true;
        }
        $literal = substr($m['domain'], 1, -1);
        return str_starts_with($literal, 'IPv6:')
            ? filter_var(substr($literal, 5), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            : filter_var($literal, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /** @return list<string> the list split at commas outside quotes and angle brackets */
    private static function splitList(string $list): array
    {
        $parts = [];
        $current = '';
        $quoted = false;
        foreach (str_split($list) as $char) {
            if ($char === '"') {
                $quoted = !$quoted;
            }
            if ($char === ',' && !$quoted) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;
        return array_values(array_filter($parts, static fn (string $part): bool => trim($part) !== ''));
    }
}
