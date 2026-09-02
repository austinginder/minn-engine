<?php

declare(strict_types=1);

use Minn\Http\TrustedProxies;

/**
 * Who may speak for the client. The default is nobody, so a forwarded
 * header is what it is: the client's own word, ignored. A site that names
 * its proxy gets the address the proxy appended, and a client that forges
 * its own forwarded header cannot put an address to the right of the
 * proxy's, which is the one the engine reads.
 */
$configured = static function (array $ranges): TrustedProxies {
    // configured() reads a constant, so the ranges are exercised through the same parser.
    $probe = new ReflectionClass(TrustedProxies::class);
    $instance = $probe->newInstanceWithoutConstructor();
    $property = $probe->getProperty('ranges');
    $property->setValue($instance, $ranges);
    return $instance;
};

return [
    'with nobody trusted, the connecting address is the client' => static function (): bool|string {
        $none = TrustedProxies::none();
        $address = $none->clientAddress('203.0.113.9', '198.51.100.4');
        return $address === '203.0.113.9' && !$none->forwardedSecure('203.0.113.9', 'https') ? true : $address;
    },
    'a trusted proxy speaks for the client' => static function () use ($configured): bool|string {
        $proxies = $configured(['10.0.0.0/8']);
        $address = $proxies->clientAddress('10.1.2.3', '198.51.100.4');
        return $address === '198.51.100.4' && $proxies->forwardedSecure('10.1.2.3', 'https') ? true : $address;
    },
    'an untrusted address speaks only for itself, however it forwards' => static function () use ($configured): bool|string {
        $proxies = $configured(['10.0.0.0/8']);
        $address = $proxies->clientAddress('203.0.113.9', '198.51.100.4');
        return $address === '203.0.113.9' && !$proxies->forwardedSecure('203.0.113.9', 'https') ? true : $address;
    },
    'a forged forwarded header cannot outrank the proxy that appended to it' => static function () use ($configured): bool|string {
        $proxies = $configured(['10.0.0.0/8']);
        // The client sent "1.2.3.4"; the proxy appended the address it saw.
        $address = $proxies->clientAddress('10.1.2.3', '1.2.3.4, 198.51.100.4');
        return $address === '198.51.100.4' ? true : $address;
    },
    'a chain of trusted proxies resolves to the first address outside it' => static function () use ($configured): bool|string {
        $proxies = $configured(['10.0.0.0/8', '192.168.0.0/16']);
        $address = $proxies->clientAddress('10.1.2.3', '198.51.100.4, 192.168.1.1, 10.9.9.9');
        return $address === '198.51.100.4' ? true : $address;
    },
    'ports and brackets come off a forwarded address' => static function () use ($configured): bool|string {
        $proxies = $configured(['10.0.0.0/8']);
        $four = $proxies->clientAddress('10.1.2.3', '198.51.100.4:41234');
        $six = $proxies->clientAddress('10.1.2.3', '[2001:db8::1]:41234');
        return $four === '198.51.100.4' && $six === '2001:db8::1' ? true : "{$four} | {$six}";
    },
    'an IPv6 range is matched by its bits' => static function () use ($configured): bool|string {
        $proxies = $configured(['2400:cb00::/32']);
        return $proxies->trusts('2400:cb00:1234::9') && !$proxies->trusts('2400:cb01::9') ? true : 'v6 range wrong';
    },
    'a plain address without a range matches exactly' => static function () use ($configured): bool {
        $proxies = $configured(['203.0.113.9']);
        return $proxies->trusts('203.0.113.9') && !$proxies->trusts('203.0.113.10');
    },
    'a scheme that is not https stays insecure even from a trusted proxy' => static function () use ($configured): bool {
        $proxies = $configured(['10.0.0.0/8']);
        return !$proxies->forwardedSecure('10.1.2.3', 'http') && $proxies->forwardedSecure('10.1.2.3', 'https,http');
    },
];
