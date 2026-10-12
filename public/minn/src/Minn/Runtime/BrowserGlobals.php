<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The browser and web server a request names, as the reference's globals
 * have them from boot ($is_chrome, $is_safari, $is_IE, $is_apache,
 * $is_nginx, …; probe vars-matrix): one browser at most, read from the
 * user agent in the reference's order (Lynx, Edge, Opera, Chrome, Safari,
 * Internet Explorer on a Mac then elsewhere, Gecko, Netscape 4), an
 * iPhone being a mobile Safari; the server from SERVER_SOFTWARE, IIS 7 and
 * up apart. Chrome Frame's switch is the facade's (use_google_chrome_frame).
 */
final class BrowserGlobals
{
    /** The globals, in the order the reference sets them. */
    public const NAMES = ['is_lynx', 'is_gecko', 'is_winIE', 'is_macIE', 'is_opera', 'is_NS4', 'is_safari', 'is_chrome', 'is_iphone', 'is_IE', 'is_edge', 'is_apache', 'is_IIS', 'is_iis7', 'is_nginx', 'is_caddy'];

    /**
     * Each global's value for this user agent and server software.
     *
     * @return array<string, bool>
     */
    public static function detect(string $agent, string $software): array
    {
        $flags = array_fill_keys(self::NAMES, false);
        $browser = self::browser($agent);
        if ($browser !== null) {
            $flags[$browser] = true;
        }
        $flags['is_iphone'] = $flags['is_safari'] && str_contains($agent, 'Mobile');
        $flags['is_IE'] = $flags['is_macIE'] || $flags['is_winIE'];
        $flags['is_apache'] = str_contains($software, 'Apache') || str_contains($software, 'LiteSpeed');
        $flags['is_nginx'] = str_contains($software, 'nginx');
        $flags['is_caddy'] = str_contains($software, 'Caddy') || str_contains($software, 'FrankenPHP');
        $flags['is_IIS'] = !$flags['is_apache'] && (str_contains($software, 'Microsoft-IIS') || str_contains($software, 'ExpressionDevServer'));
        $flags['is_iis7'] = $flags['is_IIS'] && preg_match('#Microsoft-IIS/(\d+)#', $software, $m) === 1 && (int) $m[1] >= 7;
        return $flags;
    }

    /** The one browser global the user agent sets, or null. */
    private static function browser(string $agent): ?string
    {
        $ie = str_contains($agent, 'MSIE') || str_contains($agent, 'Trident');
        return match (true) {
            str_contains($agent, 'Lynx') => 'is_lynx',
            str_contains($agent, 'Edg') => 'is_edge',
            str_contains($agent, 'Opera') || str_contains($agent, 'OPR/') => 'is_opera',
            str_contains($agent, 'Chrome') => 'is_chrome',
            str_contains($agent, 'Safari') => 'is_safari',
            $ie && str_contains($agent, 'Mac') => 'is_macIE',
            $ie => 'is_winIE',
            str_contains($agent, 'Gecko') => 'is_gecko',
            str_contains($agent, 'Nav') && str_contains($agent, 'Mozilla/4.') => 'is_NS4',
            default => null,
        };
    }
}
