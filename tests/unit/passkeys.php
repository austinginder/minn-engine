<?php
/**
 * The WebAuthn ceremonies (Minn\Auth\WebAuthn), proven against a software
 * authenticator (tests/fixtures/passkeys/soft-authenticator.php): each of
 * the three key kinds makes a passkey and signs in, and each relying party
 * check refuses the one thing it guards. The browser suite
 * (tests/browser/passkeys.test.js) proves the same against Chrome's own
 * authenticator.
 */

declare(strict_types=1);

use Minn\Auth\WebAuthn\{Base64Url, Cbor, CborBytes, Ceremony, CoseKey, RelyingParty, Refused};

require_once dirname(__DIR__) . '/fixtures/passkeys/soft-authenticator.php';

$party = RelyingParty::forHome('https://minn.localhost/', 'Minn');
$ceremony = new Ceremony($party);
$origin = 'https://minn.localhost';

/** The refusal's reason, or a note that nothing was refused. */
$reason = static function (Closure $run): string {
    try {
        $run();
        return 'accepted';
    } catch (Refused $refused) {
        return $refused->reason;
    }
};

/** A registered key and counter, then the sign-in the case shapes. */
$signIn = static function (array $o = [], ?Closure $before = null, string $kind = 'es256') use ($ceremony, $origin): mixed {
    $auth = new MinnTestAuthenticator($kind);
    $challenge = random_bytes(32);
    $made = $auth->create('minn.localhost', $origin, $challenge);
    $passkey = $ceremony->register($challenge, $made['clientDataJSON'], $made['attestationObject']);
    $stored = $passkey->signCount;
    if ($before !== null) {
        $stored = $before($auth);
    }
    $challenge = random_bytes(32);
    $answer = $auth->get('minn.localhost', $origin, $challenge, $o);
    return $ceremony->signIn($challenge, $answer['clientDataJSON'], $answer['authenticatorData'], $answer['signature'], CoseKey::fromBytes($passkey->publicKey), $stored);
};

/** A registration the case shapes. */
$register = static function (array $o = [], string $kind = 'es256') use ($ceremony, $origin): mixed {
    $auth = new MinnTestAuthenticator($kind);
    $challenge = random_bytes(32);
    $made = $auth->create('minn.localhost', $origin, $challenge, $o);
    return $ceremony->register($challenge, $made['clientDataJSON'], $made['attestationObject']);
};

$cases = [];

$cases['base64url round-trips and refuses padding and the other alphabet'] = static function (): bool|string {
    $bytes = random_bytes(33);
    return Base64Url::decode(Base64Url::encode($bytes)) === $bytes && Base64Url::decode('YQ') === 'a'
        && Base64Url::decode('YQ==') === null && Base64Url::decode('a+b/') === null && Base64Url::decode('a') === null;
};

$cases['the relying party is the home host; the origin keeps a port and drops the path'] = static function (): bool|string {
    $plain = RelyingParty::forHome('https://Example.com/blog/', 'Blog');
    $port = RelyingParty::forHome('https://example.com:8443', '');
    return $plain->id === 'example.com' && $plain->origin === 'https://example.com' && $port->origin === 'https://example.com:8443' && $port->name === 'example.com';
};

$cases['cbor reads integers, text, bytes, lists, maps and tags'] = static function (): bool|string {
    $value = Cbor::decode(MinnTestCbor::encode(new MinnTestCborMap(['a' => [1, -2, 1000000, true, null], -3 => new MinnTestCborBytes("\x01\x02")])) );
    $tagged = Cbor::decode("\xc1\x1a\x00\x01\x00\x00");
    return $value['a'] === [1, -2, 1000000, true, null] && $value[-3] instanceof CborBytes && $value[-3]->value === "\x01\x02" && $tagged === 65536;
};

foreach ([
    'trailing bytes' => "\x01\x01",
    'an indefinite length' => "\x5f\x41\x00\xff",
    'a float' => "\xfa\x3f\x80\x00\x00",
    'a byte string longer than the input' => "\x5a\xff\xff\xff\xff\x00",
    'a list longer than the input' => "\x9a\x7f\xff\xff\xff",
    'a key given twice' => "\xa2\x01\x01\x01\x02",
    'a byte-string key' => "\xa1\x41\x00\x01",
    'nesting past sixteen levels' => str_repeat("\x81", 20) . "\x01",
    'text that is not UTF-8' => "\x62\xc3\x28",
    'an empty input' => '',
] as $label => $bytes) {
    $cases["cbor refuses {$label}"] = static fn () => $reason(static fn () => Cbor::decode($bytes)) === 'cbor';
}

foreach (['es256', 'eddsa', 'rs256'] as $kind) {
    $cases["{$kind}: a passkey is made and signs in, the counter moving on"] = static function () use ($signIn, $kind): bool|string {
        $assertion = $signIn([], null, $kind);
        return $assertion->signCount === 1 ? true : "count {$assertion->signCount}";
    };
    $cases["{$kind}: a signature one bit off is refused"] = static fn () => ($r = $reason(static fn () => $signIn(['corrupt' => true], null, $kind))) === 'signature' ?: $r;
}

$cases['registration keeps the id, the key as sent and the backup flags'] = static function () use ($register): bool|string {
    $passkey = $register(['flags' => 0x01 | 0x04 | 0x08 | 0x10 | 0x40]);
    return strlen($passkey->credentialId) === 32 && $passkey->algorithm === CoseKey::ES256 && $passkey->backupEligible && $passkey->backedUp;
};

foreach ([
    'a get answer' => [['type' => 'webauthn.get'], 'type'],
    'another challenge' => [['challenge' => 'a different challenge'], 'challenge'],
    'another origin' => [['origin' => 'https://evil.example'], 'origin'],
    'an http origin for an https site' => [['origin' => 'http://minn.localhost'], 'origin'],
    'a cross-origin frame' => [['crossOrigin' => true], 'origin'],
    'another relying party' => [['rpId' => 'evil.example'], 'party'],
    'no user presence' => [['flags' => 0x04 | 0x40], 'verification'],
    'no user verification' => [['flags' => 0x01 | 0x40], 'verification'],
    'backed up but not eligible' => [['flags' => 0x01 | 0x04 | 0x10 | 0x40], 'authenticator'],
    'no credential data' => [['flags' => 0x01 | 0x04], 'authenticator'],
    'bytes after the key' => [['trailing' => "\x00"], 'authenticator'],
    'a none attestation with a statement' => [['attStmt' => ['sig' => new MinnTestCborBytes('x')]], 'attestation'],
    'an unoffered algorithm' => [['cose' => MinnTestCbor::encode(new MinnTestCborMap([1 => 2, 3 => -35, -1 => 2]))], 'key'],
    'an EC point off the curve' => [['cose' => MinnTestCbor::encode(new MinnTestCborMap([1 => 2, 3 => -7, -1 => 1, -2 => new MinnTestCborBytes(str_repeat("\x01", 32)), -3 => new MinnTestCborBytes(str_repeat("\x02", 32))]))], 'key'],
    'a small RSA key' => [['cose' => MinnTestCbor::encode(new MinnTestCborMap([1 => 3, 3 => -257, -1 => new MinnTestCborBytes(random_bytes(128)), -2 => new MinnTestCborBytes("\x01\x00\x01")]))], 'key'],
] as $label => [$o, $expected]) {
    $cases["registration refuses {$label}"] = static fn () => ($r = $reason(static fn () => $register($o))) === $expected ?: $r;
}

$cases['registration reads past another attestation format without trusting it'] = static fn () => $register(['fmt' => 'packed', 'attStmt' => ['alg' => -7, 'sig' => new MinnTestCborBytes('not checked')]])->algorithm === CoseKey::ES256;

foreach ([
    'a create answer' => [['type' => 'webauthn.create'], 'type'],
    'another challenge' => [['challenge' => 'another'], 'challenge'],
    'another origin' => [['origin' => 'https://minn.localhost.evil.example'], 'origin'],
    'another relying party' => [['rpId' => 'evil.example'], 'party'],
    'no user verification' => [['flags' => 0x01], 'verification'],
    'no user presence' => [['flags' => 0x04], 'verification'],
] as $label => [$o, $expected]) {
    $cases["sign-in refuses {$label}"] = static fn () => ($r = $reason(static fn () => $signIn($o))) === $expected ?: $r;
}

$cases['sign-in refuses a counter that did not move forward'] = static function () use ($signIn, $reason): bool|string {
    $r = $reason(static fn () => $signIn(['count' => 5], static fn (MinnTestAuthenticator $a): int => 5));
    return $r === 'counter' ?: $r;
};

$cases['sign-in accepts a passkey that keeps no counter at all'] = static fn () => $signIn(['count' => 0])->signCount === 0;

$cases['sign-in refuses a counter that falls back to zero'] = static function () use ($signIn, $reason): bool|string {
    $r = $reason(static fn () => $signIn(['count' => 0], static fn (MinnTestAuthenticator $a): int => 7));
    return $r === 'counter' ?: $r;
};

$cases['a stored ES256 key verifies only its own signatures'] = static function (): bool|string {
    $one = new MinnTestAuthenticator();
    $two = new MinnTestAuthenticator();
    $answer = $two->get('minn.localhost', 'https://minn.localhost', 'c');
    return !CoseKey::fromBytes($one->cose)->verifies($answer['authenticatorData'] . hash('sha256', $answer['clientDataJSON'], true), $answer['signature'])
        && CoseKey::fromBytes($two->cose)->verifies($answer['authenticatorData'] . hash('sha256', $answer['clientDataJSON'], true), $answer['signature']);
};

return $cases;
