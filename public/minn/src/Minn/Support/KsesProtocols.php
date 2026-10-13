<?php

declare(strict_types=1);

namespace Minn\Support;

use Minn\Json;
use Minn\Runtime\Runtime;

/**
 * The protocols an address may use, as kses judges them: the site's list
 * (kses_allowed_protocols), and an address with a protocol not on the list
 * taken off it, encoded colons and entity-spelled protocols seen through.
 */
final class KsesProtocols
{
    /**
     * The protocols addresses may use (wp_allowed_protocols): the table's
     * list through kses_allowed_protocols, asked again on every call until
     * wp_loaded has run, then kept.
     *
     * @return list<string>
     */
    public static function allowed(): array
    {
        static $kept = null;
        static $listed = null;
        if ($kept !== null && Runtime::hooks()->actionsDone('wp_loaded') > 0) {
            return $kept;
        }
        $listed ??= (array) (Json::file(Runtime::current()->engineDir . '/data/kses.json', [])['protocols'] ?? []);
        return $kept = array_values(array_unique((array) Runtime::hooks()->filter('kses_allowed_protocols', [$listed])));
    }

    /**
     * An address with a protocol the list does not allow taken off
     * (wp_kses_bad_protocol): null bytes gone; an http or https address left
     * as written when that protocol is allowed; otherwise the protocol checked
     * again until nothing changes (up to six times), and empty if it never
     * settles.
     *
     * @param array<array-key, mixed>|string $protocols
     */
    public static function strip(string $content, array|string $protocols): string
    {
        $content = Kses::withoutNulls($content);
        foreach (['https', 'http'] as $scheme) {
            if (str_starts_with($content, "{$scheme}://") && in_array($scheme, (array) $protocols, true)) {
                return $content;
            }
        }
        for ($pass = 0; $pass < 6; $pass++) {
            $next = self::stripOnce($content, $protocols);
            if ($next === $content) {
                return $content;
            }
            $content = $next;
        }
        return '';
    }

    /**
     * One pass of the protocol check (wp_kses_bad_protocol_once): encoded
     * colons decoded; when what comes before the first colon (and holds no
     * "/?") is an allowed protocol it is lower-cased and kept, otherwise taken
     * off; either way what follows is trimmed.
     *
     * @param array<array-key, mixed>|string $protocols
     */
    public static function stripOnce(string $content, array|string $protocols): string
    {
        $content = (string) preg_replace('/(&colon;|&#0*58(?:;|(?!\d))|&#x0*3a;)/i', ':', $content);
        $parts = (array) preg_split('/:|&#0*58(?:;|(?!\d))|&#x0*3a;|&colon;/i', $content, 2);
        if (!isset($parts[1]) || preg_match('%/\?%', (string) $parts[0]) === 1) {
            return $content;
        }
        $protocol = (string) preg_replace('/[^a-zA-Z0-9-+.]/', '', (string) preg_replace('/[\s\x00-\x20]/', '', Entities::numericAsBytes((string) $parts[0])));
        $known = array_map(static fn (mixed $allowed): string => strtolower((string) $allowed), (array) $protocols);
        return in_array(strtolower($protocol), $known, true) ? strtolower($protocol) . ':' . trim((string) $parts[1]) : trim((string) $parts[1]);
    }
}
