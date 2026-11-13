<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Auth\WebAuthn\{Base64Url, Ceremony, ClientData, CoseKey, RelyingParty, Refused};
use Minn\Content\{UserRecord, Users};
use Minn\Db;
use Minn\Runtime\Runtime;
use Minn\Support\Entities;

/**
 * The site's passkeys: the options a browser's navigator.credentials.create()
 * and get() take, as the JSON forms PublicKeyCredential reads
 * (parseCreationOptionsFromJSON, parseRequestOptionsFromJSON), and the
 * answers (PublicKeyCredential.toJSON()) checked and kept.
 *
 * Each passkey is a usermeta row of its owner, minn_passkey_key_ and forty
 * hex of the credential id's SHA-256, holding a PasskeyRecord; the WebAuthn
 * user handle (random, never the user id) is minn_passkey_handle. A
 * challenge is an option row of its own (autoload off) holding its expiry,
 * its ceremony and the user it was issued to; it is taken, so it answers
 * once, five minutes at most, and rows nobody came back for are swept.
 * Sign-in passkeys are discoverable: the browser offers the person their
 * passkeys for the site, and the credential names the user.
 */
final readonly class Passkeys
{
    private const KEY = 'minn_passkey_key_';
    private const HANDLE = 'minn_passkey_handle';
    private const CHALLENGE = 'minn_passkey_challenge_';
    private const LIFETIME = 300;
    private const LABEL_LENGTH = 100;
    private const TRANSPORTS = ['ble', 'hybrid', 'internal', 'nfc', 'smart-card', 'usb'];

    private Users $users;
    private Ceremony $ceremony;

    public function __construct(Db $db, private RelyingParty $party)
    {
        $this->users = new Users($db);
        $this->ceremony = new Ceremony($party);
    }

    /** The passkeys of the site its home address names, called by its title. */
    public static function forSite(Db $db): self
    {
        $options = Runtime::options();
        $title = Entities::decode((string) $options->filtered('blogname'), ENT_QUOTES);
        return new self($db, RelyingParty::forHome((string) $options->filtered('home'), $title));
    }

    /**
     * Options for adding a passkey to a user: a fresh challenge, the site,
     * the user's handle and name, the three key kinds, a discoverable
     * passkey that checks who is using it, and the passkeys the user has
     * already (so one authenticator is not added twice).
     *
     * @return array<string, mixed>
     */
    public function creationOptions(UserRecord $user): array
    {
        return [
            'challenge' => Base64Url::encode($this->issue('create', $user->id)),
            'rp' => ['id' => $this->party->id, 'name' => $this->party->name],
            'user' => ['id' => Base64Url::encode($this->handle($user->id)), 'name' => $user->login, 'displayName' => $user->displayName !== '' ? $user->displayName : $user->login],
            'pubKeyCredParams' => array_map(static fn (int $algorithm): array => ['type' => 'public-key', 'alg' => $algorithm], CoseKey::OFFERED),
            'timeout' => self::LIFETIME * 1000,
            'attestation' => 'none',
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required'],
            'excludeCredentials' => array_map(static fn (PasskeyRecord $passkey): array => ['type' => 'public-key', 'id' => $passkey->id, 'transports' => $passkey->transports], $this->of($user->id)),
        ];
    }

    /**
     * Options for signing in with any of the site's passkeys: a fresh
     * challenge and the site, no user named.
     *
     * @return array<string, mixed>
     */
    public function requestOptions(): array
    {
        return [
            'challenge' => Base64Url::encode($this->issue('get', 0)),
            'rpId' => $this->party->id,
            'timeout' => self::LIFETIME * 1000,
            'userVerification' => 'required',
            'allowCredentials' => [],
        ];
    }

    /**
     * Keeps the passkey a browser made from creationOptions for the same
     * user, under the label given; refused (Refused) when any check fails,
     * the challenge was not this user's or was used, or the passkey is
     * already someone's.
     *
     * @param array<string, mixed> $credential
     */
    public function register(UserRecord $user, array $credential, string $label): PasskeyRecord
    {
        $client = self::field($credential, 'clientDataJSON');
        $challenge = $this->redeem(ClientData::parse($client)->challenge, 'create', $user->id);
        $made = $this->ceremony->register($challenge, $client, self::field($credential, 'attestationObject'));
        if (self::rawId($credential) !== $made->credentialId) {
            throw new Refused('id', 'The passkey id does not match its data.');
        }
        if ($this->owner($made->credentialId) !== null) {
            throw new Refused('taken', 'That passkey is already in use on this site.');
        }
        $transports = $credential['response']['transports'] ?? [];
        $record = new PasskeyRecord(
            $user->id,
            Base64Url::encode($made->credentialId),
            Base64Url::encode($made->publicKey),
            $made->algorithm,
            $made->signCount,
            mb_substr(trim($label), 0, self::LABEL_LENGTH),
            array_values(array_intersect(self::TRANSPORTS, is_array($transports) ? $transports : [])),
            time(),
            0,
            $made->backupEligible,
            $made->backedUp,
        );
        $this->users->setMeta($user->id, self::KEY . self::digest($made->credentialId), $record->toStored());
        return $record;
    }

    /**
     * The user a browser's answer to requestOptions proves, the passkey's
     * counter and last use recorded; refused (Refused) when any check fails,
     * the challenge was used, or the passkey is no one's.
     *
     * @param array<string, mixed> $credential
     */
    public function verify(array $credential): UserRecord
    {
        $client = self::field($credential, 'clientDataJSON');
        $challenge = $this->redeem(ClientData::parse($client)->challenge, 'get', 0);
        $id = self::rawId($credential);
        $owner = $this->owner($id);
        $stored = $owner === null ? null : $this->users->meta($owner, self::KEY . self::digest($id));
        $record = $stored === null ? null : PasskeyRecord::fromStored((int) $owner, $stored);
        $user = $record === null ? null : $this->users->find($record->userId);
        if ($record === null || $user === null) {
            throw new Refused('unknown', 'That passkey is not one this site knows.');
        }
        $handle = $credential['response']['userHandle'] ?? null;
        if (is_string($handle) && $handle !== '' && !hash_equals(Base64Url::encode($this->handle($user->id)), $handle)) {
            throw new Refused('handle', 'The passkey names another user.');
        }
        $assertion = $this->ceremony->signIn(
            $challenge,
            $client,
            self::field($credential, 'authenticatorData'),
            self::field($credential, 'signature'),
            CoseKey::fromBytes((string) Base64Url::decode($record->publicKey)),
            $record->signCount,
        );
        $this->users->setMeta($user->id, self::KEY . self::digest($id), $record->usedFor($assertion, time())->toStored());
        return $user;
    }

    /**
     * A user's passkeys, newest first.
     *
     * @return list<PasskeyRecord>
     */
    public function of(int $userId): array
    {
        $records = [];
        foreach ($this->users->metaWithPrefix($userId, self::KEY) as $stored) {
            $record = PasskeyRecord::fromStored($userId, $stored);
            if ($record !== null) {
                $records[] = $record;
            }
        }
        usort($records, static fn (PasskeyRecord $a, PasskeyRecord $b): int => $b->created <=> $a->created);
        return $records;
    }

    /** Removes one of a user's passkeys by its base64url id: whether the user had it. */
    public function remove(int $userId, string $id): bool
    {
        $bytes = Base64Url::decode($id);
        $key = $bytes === null || $bytes === '' ? '' : self::KEY . self::digest($bytes);
        if ($key === '' || $this->users->meta($userId, $key) === null) {
            return false;
        }
        $this->users->deleteMeta($userId, $key);
        return true;
    }

    private function issue(string $ceremony, int $userId): string
    {
        $challenge = random_bytes(32);
        $options = Runtime::options();
        $options->upsert(self::CHALLENGE . self::digest($challenge), (time() + self::LIFETIME) . ":{$ceremony}:{$userId}", 'off');
        if (random_int(1, 20) === 1) {
            $options->deleteExpired(self::CHALLENGE, time());
        }
        return $challenge;
    }

    private function redeem(string $challenge, string $ceremony, int $userId): string
    {
        $stored = Runtime::options()->take(self::CHALLENGE . self::digest($challenge));
        [$expires, $for, $who] = explode(':', (string) $stored, 3) + ['', '', ''];
        if ($stored === null || (int) $expires < time() || $for !== $ceremony || $who !== (string) $userId) {
            throw new Refused('challenge', 'This passkey request has expired. Try again.');
        }
        return $challenge;
    }

    private function handle(int $userId): string
    {
        $stored = Base64Url::decode((string) $this->users->meta($userId, self::HANDLE));
        if ($stored !== null && strlen($stored) === 32) {
            return $stored;
        }
        $handle = random_bytes(32);
        $this->users->setMeta($userId, self::HANDLE, Base64Url::encode($handle));
        return $handle;
    }

    private function owner(string $credentialId): ?int
    {
        return $this->users->idsWithMeta(self::KEY . self::digest($credentialId))[0] ?? null;
    }

    private static function digest(string $bytes): string
    {
        return substr(hash('sha256', $bytes), 0, 40);
    }

    /** @param array<string, mixed> $credential */
    private static function rawId(array $credential): string
    {
        $id = is_string($credential['rawId'] ?? null) ? Base64Url::decode($credential['rawId']) : null;
        if ($id === null || $id === '' || ($credential['type'] ?? null) !== 'public-key') {
            throw new Refused('response', 'The browser sent no passkey.');
        }
        return $id;
    }

    /** @param array<string, mixed> $credential */
    private static function field(array $credential, string $name): string
    {
        $value = is_array($credential['response'] ?? null) && is_string($credential['response'][$name] ?? null) ? Base64Url::decode($credential['response'][$name]) : null;
        if ($value === null || $value === '') {
            throw new Refused('response', 'The browser sent no passkey.');
        }
        return $value;
    }
}
