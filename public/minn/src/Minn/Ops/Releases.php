<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;
use Minn\Content\Site;
use Minn\Http;

/**
 * Whether a newer Minn is out, asked of GitHub at most once a day: the
 * latest published release of the engine's own repository, kept in the
 * minn_release option (JSON) with the time it was asked. A check GitHub
 * does not answer (offline, rate limited) keeps the last answer and waits
 * a day like any other; a repository with no published release (a 404,
 * which is also what a private one answers) offers nothing. This is Minn
 * asking Minn, never wordpress.org.
 */
final class Releases
{
    public const OPTION = 'minn_release';
    public const TTL = 86400;
    public const REPOSITORY = 'austinginder/minn-engine';
    public const SOURCE = 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest';
    /** Where the repository's release assets download from; nothing else is installed. */
    public const DOWNLOADS = 'https://github.com/' . self::REPOSITORY . '/releases/download/';

    /**
     * @param Closure(): ?string $load the stored answer, as JSON
     * @param Closure(string): void $save keeps the answer, as JSON
     */
    public function __construct(
        private readonly Closure $load,
        private readonly Closure $save,
        private readonly string $installed,
        private readonly string $source = self::SOURCE,
    ) {
    }

    /** The check for a site, its answer kept in the site's minn_release option. */
    public static function forSite(Site $site, string $installed): self
    {
        return new self(
            static fn (): ?string => $site->option(self::OPTION),
            static function (string $json) use ($site): void {
                $site->setOption(self::OPTION, $json);
            },
            $installed,
        );
    }

    /**
     * The stored answer: when GitHub was last asked and the latest release then.
     *
     * @return array{checked: int, latest: ?array<string, string>}
     */
    public function stored(): array
    {
        $stored = json_decode((string) (($this->load)() ?? ''), true);
        return [
            'checked' => is_array($stored) ? (int) ($stored['checked'] ?? 0) : 0,
            'latest' => is_array($stored) && is_array($stored['latest'] ?? null) ? $stored['latest'] : null,
        ];
    }

    /** Whether the stored answer is a day old, or there is none. */
    public function due(): bool
    {
        return $this->stored()['checked'] <= time() - self::TTL;
    }

    /**
     * Asks GitHub now and keeps the answer; `answered` says whether GitHub
     * did (when it did not, `latest` is the last answer kept).
     *
     * @return array{checked: int, latest: ?array<string, string>, answered: bool}
     */
    public function refresh(): array
    {
        $latest = $this->stored()['latest'];
        $reply = Http::get($this->source, headers: ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'], timeout: 5.0, hosts: [self::origin($this->source)], maxBytes: 1048576);
        $answered = $reply->ok() || $reply->code === 404;
        if ($reply->ok()) {
            $answer = $reply->json();
            $latest = is_array($answer) ? Release::fromGitHub($answer)?->toArray() : null;
        } elseif ($reply->code === 404) {
            $latest = null;
        }
        $state = ['checked' => time(), 'latest' => $latest];
        ($this->save)((string) json_encode($state, JSON_UNESCAPED_SLASHES));
        return $state + ['answered' => $answered];
    }

    /** The release on offer: the latest one, when it is newer than the running engine. */
    public function offer(): ?Release
    {
        $latest = $this->stored()['latest'];
        $release = $latest === null ? null : Release::fromArray($latest);
        return $release !== null && $release->newerThan($this->installed) ? $release : null;
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . '/';
    }
}
