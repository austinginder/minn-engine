<?php
/**
 * The browser and server globals (Runtime\BrowserGlobals) for the user
 * agents and SERVER_SOFTWARE strings captured from the reference's boot
 * (vars-matrix: each row's flags in BrowserGlobals::NAMES order, 1 set).
 * Chrome Frame's switch is the facade's and is not here.
 */

declare(strict_types=1);

use Minn\Runtime\BrowserGlobals;

$rows = [
    ["Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36", "Apache/2.4.58", '0000000100010000'],
    ["Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15", "Apache/2.4.58", '0000001000010000'],
    ["Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1", "Apache/2.4.58", '0000001010010000'],
    ["Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1", "Apache/2.4.58", '0000001010010000'],
    ["Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0", "Apache/2.4.58", '0100000000010000'],
    ["Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0", "Apache/2.4.58", '0000000000110000'],
    ["Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36 Edge/18.19582", "Apache/2.4.58", '0000000000110000'],
    ["Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko", "Apache/2.4.58", '0010000001010000'],
    ["Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)", "Apache/2.4.58", '0010000001010000'],
    ["Mozilla/4.0 (compatible; MSIE 5.23; Mac_PowerPC)", "Apache/2.4.58", '0001000001010000'],
    ["Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; chromeframe/11.0.696.57)", "Apache/2.4.58", '0010000001010000'],
    ["Mozilla/4.0 (compatible; MSIE 8.0; chromeframe/11.0)", "Apache/2.4.58", '0010000001010000'],
    ["Opera/9.80 (Windows NT 6.1; U; en) Presto/2.10.289 Version/12.02", "Apache/2.4.58", '0000100000010000'],
    ["Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/111.0.0.0", "Apache/2.4.58", '0000100000010000'],
    ["Lynx/2.8.9rel.1 libwww-FM/2.14 SSL-MM/1.4.1 OpenSSL/1.1.1", "Apache/2.4.58", '1000000000010000'],
    ["Mozilla/4.79 [en] (WinNT; U) Nav", "Apache/2.4.58", '0000010000010000'],
    ["Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36", "Apache/2.4.58", '0000000100010000'],
    ["Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)", "Apache/2.4.58", '0000000000010000'],
    ["curl/8.6.0", "Apache/2.4.58", '0000000000010000'],
    ["", "Apache/2.4.58", '0000000000010000'],
    ["", "Apache/2.4.58", '0000000000010000'],
    ["curl/8", "Apache/2.4.58 (Unix)", '0000000000010000'],
    ["curl/8", "LiteSpeed", '0000000000010000'],
    ["curl/8", "nginx/1.25.3", '0000000000000010'],
    ["curl/8", "Caddy", '0000000000000001'],
    ["curl/8", "Microsoft-IIS/10.0", '0000000000001100'],
    ["curl/8", "Microsoft-IIS/6.0", '0000000000001000'],
    ["curl/8", "Microsoft-IIS/7.5", '0000000000001100'],
    ["curl/8", "ExpressionDevServer/1.0", '0000000000001000'],
    ["curl/8", "FrankenPHP", '0000000000000001'],
    ["curl/8", "Apache nginx", '0000000000010010'],
    ["curl/8", "", '0000000000000000'],
    ["curl/8", "", '0000000000000000'],
];
$cases = [];
foreach ($rows as [$agent, $software, $expected]) {
    $cases["{$agent} on {$software}"] = static function () use ($agent, $software, $expected) {
        $flags = implode('', array_map(static fn (bool $on): string => $on ? '1' : '0', BrowserGlobals::detect($agent, $software)));
        return $flags === $expected ? true : "{$flags}, not {$expected}";
    };
}
return $cases;
