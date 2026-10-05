<?php

declare(strict_types=1);

use Minn\Http\CertificateName;
use Minn\Http\CookieText;
use Minn\Http\IriParts;
use Minn\Http\Ipv6;
use Minn\Http\Punycode;
use Minn\Http\RawResponse;
use Minn\Http\RequestsNames;

/** The text-level HTTP helpers behind the Requests library; whole requests are pinned by tests/requests.test.php. */
return [
    'host names become Punycode label by label, case kept' => static fn () => Punycode::host('bücher.example') === 'xn--bcher-kva.example' && Punycode::host('例え.テスト') === 'xn--r8jz45g.xn--zckzah' && Punycode::label('Bücher') === 'xn--Bcher-kva',
    'an encoded label of 64 bytes or more is refused' => static function (): bool {
        try {
            Punycode::label(str_repeat('ü', 70));
            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getCode() === 2;
        }
    },
    'IPv6 expands, compresses the first longest zero run, and validates' => static fn () => Ipv6::expand('::ffff:192.0.2.128') === '0:0:0:0:0:ffff:192.0.2.128'
        && Ipv6::compress('2001:db8:0:0:1:0:0:1') === '2001:db8::1:0:0:1' && Ipv6::compress('2001:0db8:0000:0000:0000:0000:0000:0001') === '2001:0db8::1'
        && Ipv6::valid('::1') && !Ipv6::valid('fe80::1%eth0') && !Ipv6::valid('1:2:3:4:5:6:7:8:9'),
    'a cookie header splits into name, value and attributes' => static fn () => CookieText::parse('a=b c; Path=/x; Secure') === ['name' => 'a', 'value' => 'b c', 'attributes' => ['Path' => '/x', 'Secure' => true]]
        && CookieText::parse('plain=1', 'over')['value'] === 'plain=1' && CookieText::parse('novalue')['name'] === '',
    'cookie attributes normalize: max-age from the reference time, the domain without its dot' => static fn () => CookieText::normalizeAttribute('Max-Age', '60', 1000) === 1060 && CookieText::normalizeAttribute('max-age', 1060, 1000) === 1060 && CookieText::normalizeAttribute('Domain', '.Example.COM', 0) === 'Example.COM' && CookieText::normalizeAttribute('expires', 'not a date', 0) === null,
    'cookie domains and paths match as RFC 6265 says' => static fn () => CookieText::domainMatches('example.com', 'a.example.com') && !CookieText::hostMatches('example.com', 'a.example.com') && !CookieText::domainMatches('example.com', 'aexample.com')
        && CookieText::pathMatches('/app', '/app/x') && !CookieText::pathMatches('/app', '/application') && CookieText::defaultPath('/dir/page') === '/dir',
    'certificate names: one wildcard label, never an IP' => static fn () => CertificateName::matches('www.example.com', '*.example.com') && !CertificateName::matches('a.b.example.com', '*.example.com') && !CertificateName::valid('*.com') && !CertificateName::matches('192.168.0.1', '192.168.0.1'),
    'an IRI keeps non-ASCII text; its URI form encodes it' => static function (): bool {
        $iri = IriParts::parse('https://x.test/dir/')->resolve(IriParts::parse('é ü'));
        return $iri !== null && $iri->toIri() === 'https://x.test/dir/é%20ü' && $iri->toUri() === 'https://x.test/dir/%C3%A9%20%C3%BC';
    },
    'an IRI drops the default port in text but keeps it as a part' => static fn () => IriParts::parse('HTTP://Example.COM:80/a/./b/../c')->toIri() === 'http://example.com/a/c' && IriParts::parse('http://example.com:80/')->port === 80,
    'a raw response parses, chunked bodies join, gzip inflates, raw deflate passes' => static function (): bool {
        $parts = RawResponse::parse("HTTP/1.1 302 Found\r\nLocation: /x\r\nX-Folded: a\r\n b\r\n\r\nbody");
        return $parts['status'] === 302 && $parts['headers'] === [['Location', '/x'], ['X-Folded', 'a b']] && $parts['body'] === 'body'
            && RawResponse::unchunk("4\r\nWiki\r\n5\r\npedia\r\n0\r\n\r\n") === 'Wikipedia' && RawResponse::inflate((string) gzencode('g')) === 'g' && RawResponse::inflate((string) gzdeflate('d')) === (string) gzdeflate('d');
    },
    'PSR-0 Requests names map to their PSR-4 classes' => static fn () => RequestsNames::modern('Requests_Exception_HTTP_404') === 'WpOrg\\Requests\\Exception\\Http\\Status404' && RequestsNames::modern('Requests_Transport_cURL') === 'WpOrg\\Requests\\Transport\\Curl' && RequestsNames::modern('Requests_IDNAEncoder') === 'WpOrg\\Requests\\IdnaEncoder' && RequestsNames::modern('Other') === null,
];
