<?php

declare(strict_types=1);

namespace Minn\Ops;

/**
 * One published Minn release as the update service describes it (and as
 * the minn_release option keeps it, the same shape): the version, its notes
 * and page, when it went out, and its minn.zip with that file's sha256 and
 * its Ed25519 signature. Anything else is not a release Minn offers: a
 * version that is not major.minor.patch, a package anywhere but Minn's own
 * downloads, a missing checksum, or a missing signature.
 */
final readonly class Release
{
    /** The archive every release ships: the public/minn/ tree with Minn Admin bundled at minn/admin. */
    public const ASSET = 'minn.zip';

    /** Where release packages download from; EngineUpdate fetches nothing else. */
    public const DOWNLOADS = Directory::BASE . 'minn/download/';

    public function __construct(
        public string $version,
        public string $url,
        public string $published,
        public string $notes,
        public string $package,
        public string $sha256,
        public string $signature,
    ) {
    }

    /**
     * A release from the service's answer or the stored option; null when it is not one Minn offers.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $release = new self(
            (string) ($row['version'] ?? ''),
            (string) ($row['url'] ?? ''),
            (string) ($row['published'] ?? ''),
            (string) ($row['notes'] ?? ''),
            (string) ($row['package'] ?? ''),
            strtolower((string) ($row['sha256'] ?? '')),
            (string) ($row['signature'] ?? ''),
        );
        $offered = preg_match('/^\d+\.\d+\.\d+$/', $release->version) === 1
            && $release->package === self::DOWNLOADS . "{$release->version}/" . self::ASSET
            && preg_match('/^[0-9a-f]{64}$/', $release->sha256) === 1
            && preg_match('#^[A-Za-z0-9+/]{86}==$#', $release->signature) === 1;
        return $offered ? $release : null;
    }

    /**
     * The release as it is kept in the minn_release option.
     *
     * @return array{version: string, url: string, published: string, notes: string, package: string, sha256: string, signature: string}
     */
    public function toArray(): array
    {
        return ['version' => $this->version, 'url' => $this->url, 'published' => $this->published, 'notes' => $this->notes, 'package' => $this->package, 'sha256' => $this->sha256, 'signature' => $this->signature];
    }

    /** Whether this release is newer than a version that is installed. */
    public function newerThan(string $installed): bool
    {
        return version_compare($this->version, $installed, '>');
    }
}
