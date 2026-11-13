<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Auth\WebAuthn\Assertion;
use Minn\Json;

/**
 * One of a user's passkeys as the site keeps it (Auth\Passkeys): the
 * credential id and COSE key in base64url, the algorithm, the last signature
 * counter, the label its owner gave it, the transports the browser named,
 * when it was made and last used, and whether it is synced.
 */
final readonly class PasskeyRecord
{
    /** @param list<string> $transports */
    public function __construct(
        public int $userId,
        public string $id,
        public string $publicKey,
        public int $algorithm,
        public int $signCount,
        public string $label,
        public array $transports,
        public int $created,
        public int $lastUsed,
        public bool $backupEligible,
        public bool $backedUp,
    ) {
    }

    /** The record a stored JSON text holds, or null when it is not one. */
    public static function fromStored(int $userId, string $text): ?self
    {
        $data = Json::decode($text);
        if (!is_array($data) || !is_string($data['id'] ?? null) || !is_string($data['key'] ?? null) || !is_int($data['alg'] ?? null)) {
            return null;
        }
        $transports = array_values(array_filter((array) ($data['transports'] ?? []), 'is_string'));
        return new self(
            $userId,
            $data['id'],
            $data['key'],
            $data['alg'],
            (int) ($data['count'] ?? 0),
            (string) ($data['label'] ?? ''),
            $transports,
            (int) ($data['created'] ?? 0),
            (int) ($data['used'] ?? 0),
            ($data['eligible'] ?? false) === true,
            ($data['backedUp'] ?? false) === true,
        );
    }

    /** The JSON text the record is stored as. */
    public function toStored(): string
    {
        return Json::encode([
            'id' => $this->id,
            'key' => $this->publicKey,
            'alg' => $this->algorithm,
            'count' => $this->signCount,
            'label' => $this->label,
            'transports' => $this->transports,
            'created' => $this->created,
            'used' => $this->lastUsed,
            'eligible' => $this->backupEligible,
            'backedUp' => $this->backedUp,
        ]);
    }

    /** The record after a sign-in it proved: the counter and backup state it reported, and the time. */
    public function usedFor(Assertion $assertion, int $now): self
    {
        return new self($this->userId, $this->id, $this->publicKey, $this->algorithm, $assertion->signCount, $this->label, $this->transports, $this->created, $now, $this->backupEligible, $assertion->backedUp);
    }

    /**
     * What a person may see of it: id, label, transports, dates, synced. Never the key.
     *
     * @return array{id: string, label: string, transports: list<string>, created: int, lastUsed: int, synced: bool}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'transports' => $this->transports, 'created' => $this->created, 'lastUsed' => $this->lastUsed, 'synced' => $this->backedUp];
    }
}
