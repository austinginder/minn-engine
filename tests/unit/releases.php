<?php

declare(strict_types=1);

use Minn\Ops\Changelog;
use Minn\Ops\EngineUpdate;
use Minn\Ops\Release;
use Minn\Ops\Releases;

/**
 * Minn's own releases: GitHub's answer read into a release (or not), the
 * once-a-day check kept and offered against the running version, the
 * changelog read from the repository, and the engine swapping itself for a
 * release archive in a scratch webroot. No request leaves the process: the
 * GitHub answers are faked.
 */
$github = static fn (array $over = []): array => $over + [
    'tag_name' => 'v0.2.0',
    'html_url' => 'https://github.com/austinginder/minn-engine/releases/tag/v0.2.0',
    'published_at' => '2026-10-20T12:00:00Z',
    'body' => '## Notes',
    'draft' => false,
    'prerelease' => false,
    'assets' => [['name' => 'minn.zip', 'browser_download_url' => 'https://github.com/austinginder/minn-engine/releases/download/v0.2.0/minn.zip', 'digest' => 'sha256:' . str_repeat('ab', 32)]],
];
$memory = static function (?string &$stored): array {
    return [static function () use (&$stored): ?string {
        return $stored;
    }, static function (string $json) use (&$stored): void {
        $stored = $json;
    }];
};
$check = static function (array $answers, ?string &$stored, string $installed = '0.1.0') use ($memory): Releases {
    [$load, $save] = $memory($stored);
    $fake = Minn\Http::fake($answers);
    try {
        $releases = new Releases($load, $save, $installed);
        $releases->refresh();
        return $releases;
    } finally {
        $fake->restore();
    }
};
// A scratch webroot holding a minimal engine at $version, and the zip of another at $next.
$webroot = static function (string $version): string {
    $root = sys_get_temp_dir() . '/minn-update-' . bin2hex(random_bytes(4));
    mkdir("{$root}/minn/bin", 0755, true);
    file_put_contents("{$root}/minn/bootstrap.php", "<?php\ndefine('MINN_ENGINE_VERSION', '{$version}');\n");
    file_put_contents("{$root}/minn/bin/minn", "#!/usr/bin/env php\n");
    file_put_contents("{$root}/minn/.install.json", '{"park":"x"}');
    return $root;
};
$archive = static function (string $version, bool $engine = true): string {
    $file = tempnam(sys_get_temp_dir(), 'minn-zip-');
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $top = 'minn';
    if ($engine) {
        $zip->addFromString("{$top}/bootstrap.php", "<?php\ndefine('MINN_ENGINE_VERSION', '{$version}');\n");
        $zip->addFromString("{$top}/bin/minn", "#!/usr/bin/env php\n");
    }
    $zip->addFromString("{$top}/src/New.php", '<?php');
    $zip->close();
    $bytes = (string) file_get_contents($file);
    unlink($file);
    return $bytes;
};
$leftovers = static fn (string $root): array => array_values(array_filter(scandir($root) ?: [], static fn (string $f): bool => str_starts_with($f, '.minn-')));
$clean = static function (string $root): void {
    Minn\Support\Files::deleteTree($root);
};

return [
    'a published release with minn.zip reads its version, page, package and sha256' => static function () use ($github) {
        $release = Release::fromGitHub($github());
        return $release !== null && $release->version === '0.2.0' && $release->sha256 === str_repeat('ab', 32)
            && str_ends_with($release->package, '/v0.2.0/minn.zip') && $release->notes === '## Notes' ?: json_encode($release);
    },
    'a draft, a pre-release, a tag that is not a version, or no minn.zip is no release' => static function () use ($github) {
        return Release::fromGitHub($github(['draft' => true])) === null
            && Release::fromGitHub($github(['prerelease' => true])) === null
            && Release::fromGitHub($github(['tag_name' => 'nightly'])) === null
            && Release::fromGitHub($github(['assets' => [['name' => 'other.zip']]])) === null;
    },
    'an asset without a sha256 digest keeps an empty checksum' => static function () use ($github) {
        $release = Release::fromGitHub($github(['assets' => [['name' => 'minn.zip', 'browser_download_url' => 'https://github.com/x/minn.zip']]]));
        return $release !== null && $release->sha256 === '';
    },
    'the check keeps the latest release and offers it when it is newer' => static function () use ($check, $github) {
        $stored = null;
        $releases = $check(['api.github.com/*' => $github()], $stored);
        $offer = $releases->offer();
        return $offer !== null && $offer->version === '0.2.0' && !$releases->due() ?: (string) $stored;
    },
    'the running version or a newer one is offered nothing' => static function () use ($check, $github) {
        $stored = null;
        return $check(['api.github.com/*' => $github()], $stored, '0.2.0')->offer() === null
            && $check(['api.github.com/*' => $github()], $stored, '0.3.0')->offer() === null;
    },
    'a repository with no published release (404) offers nothing' => static function () use ($check, $github) {
        $stored = null;
        $check(['api.github.com/*' => $github()], $stored);
        $releases = $check(['api.github.com/*' => Minn\Http::reply(['message' => 'Not Found'], 404)], $stored);
        return $releases->offer() === null && json_decode((string) $stored, true)['latest'] === null;
    },
    'a check GitHub does not answer keeps the last answer, and waits a day' => static function () use ($check, $github) {
        $stored = null;
        $check(['api.github.com/*' => $github()], $stored);
        $releases = $check(['api.github.com/*' => Minn\Http::reply('rate limited', 403)], $stored);
        return $releases->offer()?->version === '0.2.0' && !$releases->due();
    },
    'a day-old answer is due' => static function () use ($memory) {
        $stored = json_encode(['checked' => time() - Releases::TTL - 1, 'latest' => null]);
        [$load, $save] = $memory($stored);
        return (new Releases($load, $save, '0.1.0'))->due();
    },
    'the changelog leaves out Unreleased sections and keeps the rest' => static function () {
        $md = "# Changelog\n\n## **v0.2.0** - Unreleased\n\nnext\n\n## **v0.1.0** - November 2 2026\n\nfirst\n";
        $out = Changelog::released($md);
        return $out === "# Changelog\n\n## **v0.1.0** - November 2 2026\n\nfirst\n" ?: $out;
    },
    'the changelog is fetched from the repository once a day, and a failed fetch keeps the last copy' => static function () use ($memory) {
        $stored = null;
        [$load, $save] = $memory($stored);
        $changelog = new Changelog($load, $save, Changelog::ENGINE_SOURCE);
        $fake = Minn\Http::fake(['raw.githubusercontent.com/*' => "# Changelog\n\n## **v0.1.0** - November 2 2026\n\nfirst\n"]);
        try {
            $first = $changelog->markdown();
            $again = $changelog->markdown();
            $fetches = count($fake->sent('raw.githubusercontent.com/*'));
        } finally {
            $fake->restore();
        }
        $stored = json_encode(['checked' => time() - Changelog::TTL - 1, 'markdown' => $first]);
        $fake = Minn\Http::fake(['raw.githubusercontent.com/*' => Minn\Http::reply('busy', 503)]);
        try {
            $kept = $changelog->markdown();
        } finally {
            $fake->restore();
        }
        $fake = Minn\Http::fake(['raw.githubusercontent.com/*' => Minn\Http::reply('404: Not Found', 404)]);
        $stored = json_encode(['checked' => 0, 'markdown' => $first]);
        try {
            $gone = $changelog->markdown();
        } finally {
            $fake->restore();
        }
        return str_contains($first, 'v0.1.0') && $again === $first && $fetches === 1 && $kept === $first && $gone === '' ?: json_encode([$first, $fetches, $kept, $gone]);
    },
    'the engine swaps itself for a release archive and carries the install record' => static function () use ($webroot, $archive, $leftovers, $clean) {
        $root = $webroot('0.1.0');
        try {
            $zip = $archive('0.2.0');
            $done = (new EngineUpdate("{$root}/minn"))->install($zip, hash('sha256', $zip), '0.2.0');
            return $done === '0.2.0' && EngineUpdate::versionOf("{$root}/minn") === '0.2.0' && is_file("{$root}/minn/src/New.php")
                && file_get_contents("{$root}/minn/.install.json") === '{"park":"x"}' && $leftovers($root) === [] ?: json_encode(scandir($root));
        } finally {
            $clean($root);
        }
    },
    'an archive that does not match its checksum changes nothing' => static function () use ($webroot, $archive, $leftovers, $clean) {
        $root = $webroot('0.1.0');
        try {
            $zip = $archive('0.2.0');
            (new EngineUpdate("{$root}/minn"))->install($zip, str_repeat('0', 64), '0.2.0');
            return 'installed';
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'checksum') && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' && $leftovers($root) === [] ?: $e->getMessage();
        } finally {
            $clean($root);
        }
    },
    'an archive holding another version, or no engine, changes nothing' => static function () use ($webroot, $archive, $leftovers, $clean) {
        $root = $webroot('0.1.0');
        try {
            $answers = [];
            foreach ([[$archive('0.3.0'), '0.2.0'], [$archive('0.2.0', false), '0.2.0']] as [$zip, $version]) {
                try {
                    (new EngineUpdate("{$root}/minn"))->install($zip, hash('sha256', $zip), $version);
                    $answers[] = 'installed';
                } catch (RuntimeException $e) {
                    $answers[] = $e->getMessage();
                }
            }
            return str_contains($answers[0], 'holds Minn 0.3.0') && str_contains($answers[1], 'not a Minn engine')
                && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' && $leftovers($root) === [] ?: json_encode($answers);
        } finally {
            $clean($root);
        }
    },
    'a package that is not one of the engine repository\'s own release downloads is refused before any download' => static function () use ($webroot, $clean) {
        $root = $webroot('0.1.0');
        try {
            $elsewhere = new Release('0.2.0', '', '', '', 'https://github.com/someone/else/releases/download/v0.2.0/minn.zip', str_repeat('ab', 32));
            (new EngineUpdate("{$root}/minn"))->apply($elsewhere);
            return 'installed';
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), "not one of Minn's own GitHub releases") && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' ?: $e->getMessage();
        } finally {
            $clean($root);
        }
    },
    'a second update while one runs changes nothing' => static function () use ($webroot, $archive, $clean) {
        $root = $webroot('0.1.0');
        $held = fopen(sys_get_temp_dir() . '/minn-update-' . md5("{$root}/minn") . '.lock', 'c');
        flock($held, LOCK_EX);
        try {
            $zip = $archive('0.2.0');
            (new EngineUpdate("{$root}/minn"))->install($zip, hash('sha256', $zip), '0.2.0');
            return 'installed';
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'Another update') && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' ?: $e->getMessage();
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
            $clean($root);
        }
    },
    'a development checkout (a link, or under git) is refused' => static function () use ($webroot, $archive, $clean) {
        $root = $webroot('0.1.0');
        try {
            symlink("{$root}/minn", "{$root}/linked");
            mkdir("{$root}/repo/public", 0755, true);
            rename("{$root}/minn", "{$root}/repo/public/minn");
            mkdir("{$root}/repo/.git");
            $zip = $archive('0.2.0');
            $refused = 0;
            foreach (["{$root}/linked", "{$root}/repo/public/minn"] as $dir) {
                try {
                    (new EngineUpdate($dir))->install($zip, hash('sha256', $zip), '0.2.0');
                } catch (RuntimeException $e) {
                    $refused += str_contains($e->getMessage(), 'development checkout') ? 1 : 0;
                }
            }
            return $refused === 2 && EngineUpdate::versionOf("{$root}/repo/public/minn") === '0.1.0' ?: (string) $refused;
        } finally {
            $clean($root);
        }
    },
];
