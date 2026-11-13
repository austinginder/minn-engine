# Passkeys

Minn signs people in with passkeys (WebAuthn Level 3) as well as passwords. WordPress has no passkeys, so this surface is Minn on its own: there is no oracle, and the contract is the WebAuthn and CBOR specifications plus the choices below. Extensions reach it through `$minn->accounts()` (`contracts/extensions.md`, "Accounts for a sign-in surface of an extension's own").

## The pieces

| Class | Does |
|---|---|
| `Auth\WebAuthn\Cbor` | reads CBOR (RFC 8949) strictly: integers, byte strings (`CborBytes`), UTF-8 text, arrays, maps keyed by integers or text, tags read through, true, false, null. Refused: lengths of unknown size, floats, byte-string keys, a key given twice, nesting past sixteen levels, any length past the bytes left (checked before reading, so a short message cannot ask for a large allocation), and bytes after the item |
| `Auth\WebAuthn\CoseKey` | a COSE key: ES256 (EC2, P-256, the point checked by OpenSSL), EdDSA (OKP, Ed25519, checked with sodium), RS256 (RSA of 2048 to 8192 bits). Anything else is refused when the passkey is made, never at sign-in |
| `Auth\WebAuthn\AuthenticatorData` | the relying party hash, flags (UP, UV, BE, BS, AT, ED), counter, and when AT is set the credential id (1 to 1023 bytes) and COSE key; extension output is read past; leftover bytes are refused |
| `Auth\WebAuthn\Ceremony` | the create and get checks, stateless: type, challenge (constant-time), origin equal to the site's (`crossOrigin` refused), relying party hash, user presence and user verification both required, BS without BE refused; for sign-in the signature over authenticator data plus the SHA-256 of the client data, then the counter |
| `Auth\WebAuthn\RelyingParty` | the site's home address made into an id (its host) and the one origin (scheme, host, a non-default port) |
| `Auth\Passkeys` | options, challenges and storage (below) |
| `Auth\PasskeyRecord` | one stored passkey |

## Choices

- **User verification is required** in both ceremonies, since a passkey is the whole sign-in, not a second factor.
- **Attestation is never relied on.** The creation options ask for `none`; a `none` statement must be empty; another format's statement is read past and not trusted.
- **Passkeys are discoverable.** Sign-in options name no user; the credential names its owner. Creation asks for a resident key.
- **The user handle is random** (32 bytes, user meta `minn_passkey_handle`), never the user id or login. A sign-in whose `userHandle` names another user is refused.
- **Counters:** a passkey that reports zero and has always reported zero (synced passkeys) is accepted; any other counter must move forward, or the sign-in is refused as a copied key would be.
- **Refusals** carry a stable `reason` for logs and suites; people see one message for every sign-in refusal.

## Storage

- Each passkey is a usermeta row of its owner: key `minn_passkey_key_` plus the first forty hex of the credential id's SHA-256, value a JSON `PasskeyRecord` (id and COSE key in base64url, algorithm, counter, label, transports from `ble hybrid internal nfc smart-card usb`, created, used, backup eligible, backed up). A credential id is one user's: adding it for a second user is refused (`taken`). Deleting the user deletes the rows.
- A challenge is 32 random bytes and an option row of its own (autoload off): `minn_passkey_challenge_` plus forty hex of its SHA-256, value `expires:ceremony:user`. It lives five minutes, is taken by a single delete (`Runtime\Options::take`, so of two answers racing only one gets it), is bound to its ceremony and, for creation, its user. One issue in twenty also sweeps rows past their expiry (`Runtime\Options::deleteExpired`).
- Failed passkey sign-ins count against the per-address `LoginThrottle` the password sign-in uses.

## PRF

Creation and sign-in options carry no client extensions. A client that wants a key derived from the passkey (Datapad's end-to-end encryption) adds `extensions: {prf: ...}` in the browser after parsing the options; the PRF output stays in the browser and never reaches the site. The browser suite proves Chrome's authenticator hands back 32 bytes.

## Suites

- `tests/unit/passkeys.php`: the ceremonies against a software authenticator (`tests/fixtures/passkeys/soft-authenticator.php`) for all three key kinds, and each check refusing the one thing it guards.
- `tests/passkeys.test.php`: the fixture extension's routes (`tests/fixtures/extensions/minn-test-passkeys`) with the software authenticator: challenges across users and ceremonies, expiry, one authenticator claimed twice, a foreign user handle, a counter going backwards, labels, transports, removal by another user, the throttle.
- `tests/browser/passkeys.test.js`: the same routes with Chrome's virtual authenticator: create with PRF, the browser refusing the same authenticator twice, sign-in with PRF output, a real session, replay refused, a removed passkey refused.

## Not yet

- Minn Admin's own sign-in page does not offer passkeys yet.
- The challenge sweep is probabilistic and not pinned by a suite.
