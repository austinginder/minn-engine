# `Minn\Auth\WebAuthn`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Assertion`](#assertion) | final readonly class | 6 | A sign-in a stored passkey proved: the counter to keep and whether the passkey is synced now. |
| [`AuthenticatorData`](#authenticatordata) | final readonly class | 78 | Authenticator data, read: the hash of the relying party id it signed for, |
| [`Base64Url`](#base64url) | final class | 18 | Base64 in the URL alphabet with no padding, the form every WebAuthn value travels in as JSON. |
| [`Cbor`](#cbor) | final class | 143 | A CBOR reader (RFC 8949) for what authenticators send: integers, byte |
| [`CborBytes`](#cborbytes) | final readonly class | 6 | A CBOR byte string, kept apart from a text string so a reader can insist on the one it expects. |
| [`Ceremony`](#ceremony) | final readonly class | 73 | The two WebAuthn ceremonies, checked the way the specification's relying |
| [`ClientData`](#clientdata) | final readonly class | 21 | The browser's own account of a ceremony (clientDataJSON): which ceremony, the challenge it answered, the page that asked. |
| [`CoseKey`](#cosekey) | final readonly class | 102 | A passkey's public key, from the COSE map (RFC 9052/9053) an authenticator |
| [`Der`](#der) | final class | 46 | The few DER shapes (ITU-T X.690) a public key needs to be handed to OpenSSL as a PEM SubjectPublicKeyInfo. |
| [`NewPasskey`](#newpasskey) | final readonly class | 12 | A passkey a registration ceremony proved: its id, its COSE key as sent, and what the authenticator said about it. |
| [`Refused`](#refused) | final class | 7 | A passkey ceremony that did not pass. The reason is a short stable word |
| [`RelyingParty`](#relyingparty) | final readonly class | 25 | Who passkeys are made for: the id they are bound to (the site's host, so |

## Assertion

`final readonly class Minn\Auth\WebAuthn\Assertion` · `public/minn/src/Minn/Auth/WebAuthn/Assertion.php`

A sign-in a stored passkey proved: the counter to keep and whether the passkey is synced now.

Used by: `Minn\Auth\WebAuthn\Ceremony`

```php
__construct(int $signCount, bool $backedUp)
```

- readonly `int $signCount`
- readonly `bool $backedUp`


## AuthenticatorData

`final readonly class Minn\Auth\WebAuthn\AuthenticatorData` · `public/minn/src/Minn/Auth/WebAuthn/AuthenticatorData.php`

Authenticator data, read: the hash of the relying party id it signed for,
its flags, its signature counter and, when a passkey is being made, the new
credential's id and COSE key. Extension output is read past and not kept.
Bytes left over, or a length that runs past the end, are refused.

- const `USER_PRESENT` = `1`
- const `USER_VERIFIED` = `4`
- const `BACKUP_ELIGIBLE` = `8`
- const `BACKED_UP` = `16`
- const `CREDENTIAL` = `64`
- const `EXTENSIONS` = `128`
- const `MAX_CREDENTIAL_ID` = `1023`

Used by: `Minn\Auth\WebAuthn\Ceremony`

```php
__construct(string $rpIdHash, int $flags, int $signCount, string $credentialId = '', string $publicKey = '')
```

- readonly `string $rpIdHash`
- readonly `int $flags`
- readonly `int $signCount`
- readonly `string $credentialId`
- readonly `string $publicKey`

### static `parse(string $bytes): self`

The authenticator data the bytes hold.

### `userPresent(): bool`

Whether a person touched or otherwise answered the authenticator.

### `userVerified(): bool`

Whether the authenticator checked who the person is (Face ID, a PIN, a fingerprint).

### `backupEligible(): bool`

Whether the passkey may be synced to other devices (iCloud Keychain, a password manager).

### `backedUp(): bool`

Whether the passkey is synced to other devices now.


## Base64Url

`final class Minn\Auth\WebAuthn\Base64Url` · `public/minn/src/Minn/Auth/WebAuthn/Base64Url.php`

Base64 in the URL alphabet with no padding, the form every WebAuthn value travels in as JSON.

Used by: `Minn\Auth\WebAuthn\ClientData`

### static `encode(string $bytes): string`

The text form of some bytes.

### static `decode(string $text): ?string`

The bytes a text form holds, or null when it is not one (padding, the other alphabet and stray characters are refused).


## Cbor

`final class Minn\Auth\WebAuthn\Cbor` · `public/minn/src/Minn/Auth/WebAuthn/Cbor.php`

A CBOR reader (RFC 8949) for what authenticators send: integers, byte
strings (as CborBytes), text, arrays, maps keyed by integers or text,
tags (read through), true, false and null. Everything else an
authenticator has no reason to send is refused: lengths of unknown size,
floats, byte-string map keys, a key given twice, nesting past sixteen
levels, and any length longer than the bytes left. Lengths are checked
against what remains before anything is read, so a short message cannot
ask for a large allocation.

- const `MAX_DEPTH` = `16`

Used by: `Minn\Auth\WebAuthn\AuthenticatorData`, `Minn\Auth\WebAuthn\Ceremony`, `Minn\Auth\WebAuthn\CoseKey`


### static `decode(string $bytes): mixed`

The one item the bytes hold; bytes left after it are refused.

### static `decodeAt(string $bytes, int $offset): array`

The item that starts at an offset, and the offset just past it: a key
inside authenticator data, which more bytes may follow.

- `@return array{0: mixed, 1: int}`

Internals: `item()` (private, line 49), `argument()` (private, line 72), `simple()` (private, line 94), `text()` (private, line 104), `list()` (private, line 114), `map()` (private, line 125), `room()` (private, line 143), `take()` (private, line 150)


## CborBytes

`final readonly class Minn\Auth\WebAuthn\CborBytes` · `public/minn/src/Minn/Auth/WebAuthn/CborBytes.php`

A CBOR byte string, kept apart from a text string so a reader can insist on the one it expects.

Used by: `Minn\Auth\WebAuthn\Cbor`, `Minn\Auth\WebAuthn\Ceremony`, `Minn\Auth\WebAuthn\CoseKey`

```php
__construct(string $value)
```

- readonly `string $value`


## Ceremony

`final readonly class Minn\Auth\WebAuthn\Ceremony` · `public/minn/src/Minn/Auth/WebAuthn/Ceremony.php`

The two WebAuthn ceremonies, checked the way the specification's relying
party steps check them, with no state of its own: the caller hands in the
challenge it issued and, for a sign-in, the stored key and counter.
Both insist on user verification (Face ID, a PIN), since a passkey is the
whole sign-in here, not a second factor. Attestation is never relied on:
the creation options ask for none, a "none" statement must be empty, and
another format's statement is read past, not trusted.

```php
__construct(Minn\Auth\WebAuthn\RelyingParty $party)
```


### `register(string $challenge, string $clientDataJson, string $attestationObject): Minn\Auth\WebAuthn\NewPasskey`

The passkey a registration response makes, once every check of the create ceremony passes.

### `signIn(string $challenge, string $clientDataJson, string $authenticatorData, string $signature, Minn\Auth\WebAuthn\CoseKey $key, int $storedCount): Minn\Auth\WebAuthn\Assertion`

The counter and backup state after a sign-in response from a stored passkey passes every check of the get ceremony.

Internals: `client()` (private, line 60), `authenticator()` (private, line 74)


## ClientData

`final readonly class Minn\Auth\WebAuthn\ClientData` · `public/minn/src/Minn/Auth/WebAuthn/ClientData.php`

The browser's own account of a ceremony (clientDataJSON): which ceremony, the challenge it answered, the page that asked.

Used by: `Minn\Auth\WebAuthn\Ceremony`

```php
__construct(string $type, string $challenge, string $origin, bool $crossOrigin)
```

- readonly `string $type`
- readonly `string $challenge`
- readonly `string $origin`
- readonly `bool $crossOrigin`

### static `parse(string $json): self`

The client data a JSON text holds; the challenge comes back as bytes.


## CoseKey

`final readonly class Minn\Auth\WebAuthn\CoseKey` · `public/minn/src/Minn/Auth/WebAuthn/CoseKey.php`

A passkey's public key, from the COSE map (RFC 9052/9053) an authenticator
hands over when the passkey is made. Three kinds are verified, the three
the creation options ask for: ES256 (an EC2 key on P-256), EdDSA (an OKP
Ed25519 key) and RS256 (an RSA key of at least 2048 bits). Anything else is
refused when the passkey is made, never at sign-in.

- const `ES256` = `-7`
- const `EDDSA` = `-8`
- const `RS256` = `-257`
- const `OFFERED` = `array (   0 => -7,   1 => -8,   2 => -257, )` — The algorithms the creation options offer, in the order offered.

Used by: `Minn\Auth\WebAuthn\Ceremony`

- readonly `int $algorithm`

### static `fromBytes(string $cose): self`

The key a stored COSE encoding describes.

### static `fromMap(mixed $map): self`

The key a decoded COSE map describes, refused when its type, curve, algorithm or size is not one Minn verifies.

### `verifies(string $data, string $signature): bool`

Whether the signature over the data was made by this key.

Internals: `ec2()` (private, line 64), `okp()` (private, line 76), `rsa()` (private, line 85), `checked()` (private, line 98), `bytes()` (private, line 109)


## Der

`final class Minn\Auth\WebAuthn\Der` · `public/minn/src/Minn/Auth/WebAuthn/Der.php`

The few DER shapes (ITU-T X.690) a public key needs to be handed to OpenSSL as a PEM SubjectPublicKeyInfo.

Used by: `Minn\Auth\WebAuthn\CoseKey`

### static `sequence(string $content): string`

A SEQUENCE of already encoded parts.

### static `oid(string $hex): string`

An OBJECT IDENTIFIER from its encoded body, written as hex.

### static `integer(string $bytes): string`

A non-negative INTEGER from big-endian bytes, a zero byte added when the top bit would read as a sign.

### static `bitString(string $bytes): string`

A BIT STRING with no unused bits.

### static `pem(string $der): string`

A SubjectPublicKeyInfo as PEM text.

Internals: `element()` (private, line 44)


## NewPasskey

`final readonly class Minn\Auth\WebAuthn\NewPasskey` · `public/minn/src/Minn/Auth/WebAuthn/NewPasskey.php`

A passkey a registration ceremony proved: its id, its COSE key as sent, and what the authenticator said about it.

Used by: `Minn\Auth\WebAuthn\Ceremony`

```php
__construct(string $credentialId, string $publicKey, int $algorithm, int $signCount, bool $backupEligible, bool $backedUp)
```

- readonly `string $credentialId`
- readonly `string $publicKey`
- readonly `int $algorithm`
- readonly `int $signCount`
- readonly `bool $backupEligible`
- readonly `bool $backedUp`


## Refused

`final class Minn\Auth\WebAuthn\Refused` · `public/minn/src/Minn/Auth/WebAuthn/Refused.php` · implements `Stringable`, `Throwable`

A passkey ceremony that did not pass. The reason is a short stable word
naming the check (origin, challenge, signature, counter, ...), for logs and
suites; the message is plain text for a person. Callers show people one
message for every reason, so a refusal never coaches a forger.

Used by: `Minn\Auth\WebAuthn\AuthenticatorData`, `Minn\Auth\WebAuthn\Cbor`, `Minn\Auth\WebAuthn\Ceremony`, `Minn\Auth\WebAuthn\ClientData`, `Minn\Auth\WebAuthn\CoseKey`, `Minn\Auth\WebAuthn\RelyingParty`

```php
__construct(string $reason, string $message)
```

- readonly `string $reason`


## RelyingParty

`final readonly class Minn\Auth\WebAuthn\RelyingParty` · `public/minn/src/Minn/Auth/WebAuthn/RelyingParty.php`

Who passkeys are made for: the id they are bound to (the site's host, so
a passkey made for one site is useless on another) and the one origin
whose pages may use them.

Used by: `Minn\Auth\WebAuthn\Ceremony`

```php
__construct(string $id, string $origin, string $name)
```

- readonly `string $id`
- readonly `string $origin`
- readonly `string $name`

### static `forHome(string $home, string $name): self`

The relying party a site's home address makes: its host as the id; its scheme, host and port as the origin.

### `idHash(): string`

The SHA-256 of the id, as authenticator data carries it.

