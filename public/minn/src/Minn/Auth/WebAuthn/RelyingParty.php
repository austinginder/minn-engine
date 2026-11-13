<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/**
 * Who passkeys are made for: the id they are bound to (the site's host, so
 * a passkey made for one site is useless on another) and the one origin
 * whose pages may use them.
 */
final readonly class RelyingParty
{
    public function __construct(public string $id, public string $origin, public string $name)
    {
    }

    /** The relying party a site's home address makes: its host as the id; its scheme, host and port as the origin. */
    public static function forHome(string $home, string $name): self
    {
        $parts = parse_url($home);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
            throw new Refused('site', 'The site address cannot hold passkeys.');
        }
        $port = isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80) ? ':' . $parts['port'] : '';
        return new self($host, "{$scheme}://{$host}{$port}", $name === '' ? $host : $name);
    }

    /** The SHA-256 of the id, as authenticator data carries it. */
    public function idHash(): string
    {
        return hash('sha256', $this->id, true);
    }
}
