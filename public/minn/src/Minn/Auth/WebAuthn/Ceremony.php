<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/**
 * The two WebAuthn ceremonies, checked the way the specification's relying
 * party steps check them, with no state of its own: the caller hands in the
 * challenge it issued and, for a sign-in, the stored key and counter.
 * Both insist on user verification (Face ID, a PIN), since a passkey is the
 * whole sign-in here, not a second factor. Attestation is never relied on:
 * the creation options ask for none, a "none" statement must be empty, and
 * another format's statement is read past, not trusted.
 */
final readonly class Ceremony
{
    public function __construct(private RelyingParty $party)
    {
    }

    /** The passkey a registration response makes, once every check of the create ceremony passes. */
    public function register(string $challenge, string $clientDataJson, string $attestationObject): NewPasskey
    {
        $this->client($clientDataJson, 'webauthn.create', $challenge);
        $attestation = Cbor::decode($attestationObject);
        if (!is_array($attestation) || !is_string($attestation['fmt'] ?? null) || !is_array($attestation['attStmt'] ?? null) || !($attestation['authData'] ?? null) instanceof CborBytes) {
            throw new Refused('attestation', 'The passkey sent no attestation.');
        }
        if ($attestation['fmt'] === 'none' && $attestation['attStmt'] !== []) {
            throw new Refused('attestation', 'The passkey attestation is malformed.');
        }
        $data = $this->authenticator($attestation['authData']->value);
        if ($data->credentialId === '' || $data->publicKey === '') {
            throw new Refused('authenticator', 'The passkey sent no key.');
        }
        $key = CoseKey::fromBytes($data->publicKey);
        return new NewPasskey($data->credentialId, $data->publicKey, $key->algorithm, $data->signCount, $data->backupEligible(), $data->backedUp());
    }

    /** The counter and backup state after a sign-in response from a stored passkey passes every check of the get ceremony. */
    public function signIn(string $challenge, string $clientDataJson, string $authenticatorData, string $signature, CoseKey $key, int $storedCount): Assertion
    {
        $this->client($clientDataJson, 'webauthn.get', $challenge);
        $data = $this->authenticator($authenticatorData);
        if ($data->credentialId !== '') {
            throw new Refused('authenticator', 'The passkey sent a new key while signing in.');
        }
        if (!$key->verifies($authenticatorData . hash('sha256', $clientDataJson, true), $signature)) {
            throw new Refused('signature', 'The passkey signature does not match.');
        }
        // A counter of zero on both sides is an authenticator that keeps none (synced passkeys); any counter that
        // does not move forward means two copies of one key are in use.
        if (($data->signCount !== 0 || $storedCount !== 0) && $data->signCount <= $storedCount) {
            throw new Refused('counter', 'The passkey counter went backwards, as a copied key would.');
        }
        return new Assertion($data->signCount, $data->backedUp());
    }

    private function client(string $json, string $type, string $challenge): void
    {
        $client = ClientData::parse($json);
        if ($client->type !== $type) {
            throw new Refused('type', 'The browser answered a different passkey ceremony.');
        }
        if (!hash_equals($challenge, $client->challenge)) {
            throw new Refused('challenge', 'The passkey answered a different challenge.');
        }
        if ($client->origin !== $this->party->origin || $client->crossOrigin) {
            throw new Refused('origin', 'The passkey was used from another site.');
        }
    }

    private function authenticator(string $bytes): AuthenticatorData
    {
        $data = AuthenticatorData::parse($bytes);
        if (!hash_equals($this->party->idHash(), $data->rpIdHash)) {
            throw new Refused('party', 'The passkey belongs to another site.');
        }
        if (!$data->userPresent() || !$data->userVerified()) {
            throw new Refused('verification', 'The passkey did not check who is using it.');
        }
        if ($data->backedUp() && !$data->backupEligible()) {
            throw new Refused('authenticator', 'The passkey flags contradict each other.');
        }
        return $data;
    }
}
