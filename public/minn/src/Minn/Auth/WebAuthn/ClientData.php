<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

use Minn\Json;

/** The browser's own account of a ceremony (clientDataJSON): which ceremony, the challenge it answered, the page that asked. */
final readonly class ClientData
{
    public function __construct(
        public string $type,
        public string $challenge,
        public string $origin,
        public bool $crossOrigin,
    ) {
    }

    /** The client data a JSON text holds; the challenge comes back as bytes. */
    public static function parse(string $json): self
    {
        $data = Json::decode($json);
        $challenge = is_array($data) && is_string($data['challenge'] ?? null) ? Base64Url::decode($data['challenge']) : null;
        if (!is_array($data) || !is_string($data['type'] ?? null) || !is_string($data['origin'] ?? null) || $challenge === null || $challenge === '') {
            throw new Refused('client', 'The browser sent no account of the passkey ceremony.');
        }
        return new self($data['type'], $challenge, $data['origin'], ($data['crossOrigin'] ?? false) === true);
    }
}
