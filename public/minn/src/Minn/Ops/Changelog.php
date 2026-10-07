<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;
use Minn\Content\Site;
use Minn\Http;

/**
 * A changelog read from its GitHub repository rather than shipped: a Minn
 * release carries no notes, its own or Minn Admin's, for anyone to find on
 * the sites that run it. The file on the default branch is fetched at most
 * once a day and kept in an option (JSON) with the time it was fetched;
 * sections still marked Unreleased are left out, so a site only reads about
 * releases that exist. A fetch GitHub does not answer keeps the last copy; a
 * 404 (no such file, or a private repository) keeps nothing.
 */
final class Changelog
{
    public const TTL = 86400;
    public const ENGINE_SOURCE = 'https://raw.githubusercontent.com/' . Releases::REPOSITORY . '/main/changelog.md';
    public const ADMIN_SOURCE = 'https://raw.githubusercontent.com/austinginder/minn-admin/main/changelog.md';

    /**
     * @param Closure(): ?string $load the stored copy, as JSON
     * @param Closure(string): void $save keeps the copy, as JSON
     */
    public function __construct(
        private readonly Closure $load,
        private readonly Closure $save,
        private readonly string $source,
    ) {
    }

    /** Minn's changelog for a site, kept in its minn_changelog option. */
    public static function engine(Site $site): self
    {
        return self::kept($site, 'minn_changelog', self::ENGINE_SOURCE);
    }

    /** Minn Admin's changelog for a site running Minn, kept in its minn_admin_changelog option. */
    public static function admin(Site $site): self
    {
        return self::kept($site, 'minn_admin_changelog', self::ADMIN_SOURCE);
    }

    private static function kept(Site $site, string $option, string $source): self
    {
        return new self(
            static fn (): ?string => $site->option($option),
            static function (string $json) use ($site, $option): void {
                $site->setOption($option, $json);
            },
            $source,
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
