<?php

declare(strict_types=1);

namespace Minn\Ops;

/**
 * One published Minn release as GitHub describes it: the version its tag
 * names (v0.1.0 is 0.1.0), its notes and page, when it went out, and the
 * minn.zip it ships with that file's sha256 (the digest GitHub publishes
 * for every release asset). A draft, a pre-release, a tag that is not a
 * version, or a release without minn.zip is not a release Minn offers.
 */
final readonly class Release
{
    /** The archive every release ships: the public/minn/ tree with Minn Admin bundled at minn/admin. */
    public const ASSET = 'minn.zip';

    public function __construct(
        public string $version,
        public string $url,
        public string $published,
        public string $notes,
        public string $package,
        public string $sha256,
    ) {
    }

    /**
     * A release from GitHub's answer for releases/latest (or one entry of
     * releases); null when it is not one Minn offers.
     *
     * @param array<string, mixed> $answer
     */
    public static function fromGitHub(array $answer): ?self
    {
        if (!empty($answer['draft']) || !empty($answer['prerelease'])) {
            return null;
        }
        $version = ltrim((string) ($answer['tag_name'] ?? ''), 'vV');
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            return null;
        }
        foreach ((array) ($answer['assets'] ?? []) as $asset) {
            if (!is_array($asset) || ($asset['name'] ?? '') !== self::ASSET) {
                continue;
            }
            $digest = (string) ($asset['digest'] ?? '');
            return new self(
                $version,
                (string) ($answer['html_url'] ?? ''),
                (string) ($answer['published_at'] ?? ''),
                (string) ($answer['body'] ?? ''),
                (string) ($asset['browser_download_url'] ?? ''),
                preg_match('/^sha256:([0-9a-f]{64})$/', $digest, $m) === 1 ? $m[1] : '',
            );
        }
        return null;
    }

    /**
     * A release as the minn_release option keeps it; null for anything else.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $version = (string) ($row['version'] ?? '');
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            return null;
        }
        return new self($version, (string) ($row['url'] ?? ''), (string) ($row['published'] ?? ''), (string) ($row['notes'] ?? ''), (string) ($row['package'] ?? ''), (string) ($row['sha256'] ?? ''));
    }

    /**
     * The release as it is kept in the minn_release option.
     *
     * @return array{version: string, url: string, published: string, notes: string, package: string, sha256: string}
     */
    public function toArray(): array
    {
        return ['version' => $this->version, 'url' => $this->url, 'published' => $this->published, 'notes' => $this->notes, 'package' => $this->package, 'sha256' => $this->sha256];
    }

    /** Whether this release is newer than a version that is installed. */
    public function newerThan(string $installed): bool
    {
        return version_compare($this->version, $installed, '>');
    }
}
