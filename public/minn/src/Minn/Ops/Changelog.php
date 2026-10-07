<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;
use Minn\Content\Site;
use Minn\Http;

/**
 * Minn's changelog, read from the engine's GitHub repository rather than
 * shipped with it: a release carries no notes for anyone to find on the
 * sites that run it. The file on the default branch is fetched at most once
 * a day and kept in the minn_changelog option (JSON) with the time it was
 * fetched; sections still marked Unreleased are left out, so a site only
 * reads about releases that exist. A fetch GitHub does not answer keeps the
 * last copy; a 404 (no such file, or a private repository) keeps nothing.
 */
final class Changelog
{
    public const OPTION = 'minn_changelog';
    public const TTL = 86400;
    public const SOURCE = 'https://raw.githubusercontent.com/' . Releases::REPOSITORY . '/main/changelog.md';

    /**
     * @param Closure(): ?string $load the stored copy, as JSON
     * @param Closure(string): void $save keeps the copy, as JSON
     */
    public function __construct(
        private readonly Closure $load,
        private readonly Closure $save,
        private readonly string $source = self::SOURCE,
    ) {
    }

    /** The changelog for a site, its copy kept in the site's minn_changelog option. */
    public static function forSite(Site $site): self
    {
        return new self(
            static fn (): ?string => $site->option(self::OPTION),
            static function (string $json) use ($site): void {
                $site->setOption(self::OPTION, $json);
            },
        );
    }

    /** The released sections as Markdown, fetched first when the copy is a day old; '' when there is none. */
    public function markdown(): string
    {
        $stored = json_decode((string) (($this->load)() ?? ''), true);
        $checked = is_array($stored) ? (int) ($stored['checked'] ?? 0) : 0;
        $markdown = is_array($stored) ? (string) ($stored['markdown'] ?? '') : '';
        if ($checked > time() - self::TTL) {
            return $markdown;
        }
        $reply = Http::get($this->source, timeout: 5.0, hosts: ['https://raw.githubusercontent.com/'], maxBytes: 4194304);
        if ($reply->ok()) {
            $markdown = self::released($reply->body);
        } elseif ($reply->code === 404) {
            $markdown = '';
        }
        ($this->save)((string) json_encode(['checked' => time(), 'markdown' => $markdown], JSON_UNESCAPED_SLASHES));
        return $markdown;
    }

    /** The changelog without its Unreleased sections. */
    public static function released(string $markdown): string
    {
        $parts = preg_split('/^(?=## )/m', $markdown) ?: [];
        $kept = array_filter($parts, static fn (string $part): bool => preg_match('/^## [^\n]*\bUnreleased\b/i', $part) !== 1);
        return rtrim(implode('', $kept)) . "\n";
    }
}
