<?php

declare(strict_types=1);

use Minn\Ops\Changelog;
use Minn\Ops\Directory;
use Minn\Ops\EngineUpdate;
use Minn\Ops\Release;
use Minn\Ops\Releases;

/**
 * Minn's own releases: the update service's answer read into a release (or
 * not), the once-a-day check kept and offered against the running version,
 * the changelog read through the service, and the engine swapping itself for
 * a signed release archive in a scratch webroot. No request leaves the
 * process: the service's answers are faked, and the signing key is made here.
 */
$pair = sodium_crypto_sign_keypair();
$public = base64_encode(sodium_crypto_sign_publickey($pair));
$sign = static fn (string $zip): string => base64_encode(sodium_crypto_sign_detached($zip, sodium_crypto_sign_secretkey($pair)));
$answer = static fn (array $over = []): array => $over + [
    'version' => '0.2.0',
    'url' => 'https://github.com/austinginder/minn-engine/releases/tag/v0.2.0',
    'published' => '2026-10-20T12:00:00Z',
    'notes' => '## Notes',
    'package' => Directory::BASE . 'minn/download/0.2.0/minn.zip',
    'sha256' => str_repeat('ab', 32),
    'signature' => base64_encode(str_repeat("\x07", 64)),
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
// A scratch webroot holding a minimal engine at $version, and the zip of another.
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
    if ($engine) {
        $zip->addFromString('minn/bootstrap.php', "<?php\ndefine('MINN_ENGINE_VERSION', '{$version}');\n");
        $zip->addFromString('minn/bin/minn', "#!/usr/bin/env php\n");
    }
    $zip->addFromString('minn/src/New.php', '<?php');
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
    'a release from the service reads its version, page, package, sha256 and signature' => static function () use ($answer) {
        $release = Release::fromArray($answer());
        return $release !== null && $release->version === '0.2.0' && $release->sha256 === str_repeat('ab', 32)
            && $release->package === 'https://updates.minn.run/v1/minn/download/0.2.0/minn.zip' && $release->notes === '## Notes'
            && $release->toArray() === $answer() ?: json_encode($release);
    },
    'a package anywhere but Minn\'s downloads, a missing checksum or signature, or a version that is not one, is no release' => static function () use ($answer) {
        foreach ([
            ['package' => 'https://github.com/austinginder/minn-engine/releases/download/v0.2.0/minn.zip'],
            ['package' => Directory::BASE . 'minn/download/0.3.0/minn.zip'],
            ['sha256' => ''],
            ['signature' => ''],
            ['signature' => 'not-a-signature'],
            ['version' => 'nightly'],
        ] as $over) {
            if (Release::fromArray($answer($over)) !== null) {
                return json_encode($over);
            }
        }
        return true;
    },
    'the check asks the update service, keeps the latest release and offers it when it is newer' => static function () use ($memory, $answer) {
        $stored = null;
        [$load, $save] = $memory($stored);
        $fake = Minn\Http::fake(['updates.minn.run/*' => $answer()]);
        try {
            $releases = new Releases($load, $save, '0.1.0');
            $releases->refresh();
            $asked = array_map(static fn ($request) => $request->url, $fake->sent());
        } finally {
            $fake->restore();
        }
        return $asked === [Directory::BASE . 'minn/releases/latest'] && $releases->offer()?->version === '0.2.0' && !$releases->due() ?: json_encode($asked);
    },
    'the running version or a newer one is offered nothing' => static function () use ($check, $answer) {
        $stored = null;
        return $check(['updates.minn.run/*' => $answer()], $stored, '0.2.0')->offer() === null
            && $check(['updates.minn.run/*' => $answer()], $stored, '0.3.0')->offer() === null;
    },
    'no installable release (404) offers nothing' => static function () use ($check, $answer) {
        $stored = null;
        $check(['updates.minn.run/*' => $answer()], $stored);
        $releases = $check(['updates.minn.run/*' => Minn\Http::reply(['error' => 'no_release'], 404)], $stored);
        return $releases->offer() === null && json_decode((string) $stored, true)['latest'] === null;
    },
    'a check the service does not answer keeps the last answer, and waits a day' => static function () use ($check, $answer) {
        $stored = null;
        $check(['updates.minn.run/*' => $answer()], $stored);
        $releases = $check(['updates.minn.run/*' => Minn\Http::reply(['error' => 'upstream_failed'], 502)], $stored);
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
    'the changelog is read through the service once a day, and a failed read keeps the last copy' => static function () use ($memory) {
        $stored = null;
        [$load, $save] = $memory($stored);
        $changelog = new Changelog($load, $save, Changelog::ENGINE_SOURCE);
        $fake = Minn\Http::fake(['updates.minn.run/*' => "# Changelog\n\n## **v0.1.0** - November 2 2026\n\nfirst\n"]);
        try {
            $first = $changelog->markdown();
            $again = $changelog->markdown();
            $sent = array_map(static fn ($request) => $request->url, $fake->sent());
        } finally {
            $fake->restore();
        }
        $stored = json_encode(['checked' => time() - Changelog::TTL - 1, 'markdown' => $first]);
        $fake = Minn\Http::fake(['updates.minn.run/*' => Minn\Http::reply('busy', 503)]);
        try {
            $kept = $changelog->markdown();
        } finally {
            $fake->restore();
        }
        $fake = Minn\Http::fake(['updates.minn.run/*' => Minn\Http::reply('{"error":"not_found"}', 404)]);
        $stored = json_encode(['checked' => 0, 'markdown' => $first]);
        try {
            $gone = $changelog->markdown();
        } finally {
            $fake->restore();
        }
        return str_contains($first, 'v0.1.0') && $again === $first && $sent === [Directory::BASE . 'minn/changelog'] && $kept === $first && $gone === '' ?: json_encode([$first, $sent, $kept, $gone]);
    },
    'the engine swaps itself for a signed release archive and carries the install record' => static function () use ($webroot, $archive, $leftovers, $clean, $public, $sign) {
        $root = $webroot('0.1.0');
        try {
            $zip = $archive('0.2.0');
            $done = (new EngineUpdate("{$root}/minn", [$public]))->install($zip, hash('sha256', $zip), $sign($zip), '0.2.0');
            return $done === '0.2.0' && EngineUpdate::versionOf("{$root}/minn") === '0.2.0' && is_file("{$root}/minn/src/New.php")
                && file_get_contents("{$root}/minn/.install.json") === '{"park":"x"}' && $leftovers($root) === [] ?: json_encode(scandir($root));
        } finally {
            $clean($root);
        }
    },
    'an archive signed by a key the engine does not trust, or not signed, changes nothing' => static function () use ($webroot, $archive, $leftovers, $clean, $sign) {
        $root = $webroot('0.1.0');
        try {
            $zip = $archive('0.2.0');
            $answers = [];
            $other = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));
            foreach ([[$other, $sign($zip)], [EngineUpdate::KEYS[0], $sign($zip)], [$other, '']] as [$key, $signature]) {
                try {
                    (new EngineUpdate("{$root}/minn", [$key]))->install($zip, hash('sha256', $zip), $signature, '0.2.0');
                    $answers[] = 'installed';
                } catch (RuntimeException $e) {
                    $answers[] = str_contains($e->getMessage(), 'not signed by a key this Minn trusts') ? 'refused' : $e->getMessage();
                }
            }
            return $answers === ['refused', 'refused', 'refused'] && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' && $leftovers($root) === [] ?: json_encode($answers);
        } finally {
            $clean($root);
        }
    },
    'an archive that does not match its checksum changes nothing' => static function () use ($webroot, $archive, $leftovers, $clean, $public, $sign) {
        $root = $webroot('0.1.0');
        try {
            $zip = $archive('0.2.0');
            (new EngineUpdate("{$root}/minn", [$public]))->install($zip, str_repeat('0', 64), $sign($zip), '0.2.0');
            return 'installed';
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'checksum') && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' && $leftovers($root) === [] ?: $e->getMessage();
        } finally {
            $clean($root);
        }
    },
    'a signed archive holding another version, or no engine, changes nothing' => static function () use ($webroot, $archive, $leftovers, $clean, $public, $sign) {
        $root = $webroot('0.1.0');
        try {
            $answers = [];
            foreach ([[$archive('0.3.0'), '0.2.0'], [$archive('0.2.0', false), '0.2.0']] as [$zip, $version]) {
                try {
                    (new EngineUpdate("{$root}/minn", [$public]))->install($zip, hash('sha256', $zip), $sign($zip), $version);
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
    'a package that is not on the update service is refused before any download' => static function () use ($webroot, $clean) {
        $root = $webroot('0.1.0');
        $fake = Minn\Http::fake([]);
        try {
            $elsewhere = new Release('0.2.0', '', '', '', 'https://github.com/austinginder/minn-engine/releases/download/v0.2.0/minn.zip', str_repeat('ab', 32), base64_encode(str_repeat("\x07", 64)));
            (new EngineUpdate("{$root}/minn"))->apply($elsewhere);
            return 'installed';
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'not on the Minn update service') && $fake->sent() === [] && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' ?: $e->getMessage();
        } finally {
            $fake->restore();
            $clean($root);
        }
    },
    'a second update while one runs changes nothing' => static function () use ($webroot, $archive, $clean, $public, $sign) {
        $root = $webroot('0.1.0');
        $held = fopen(sys_get_temp_dir() . '/minn-update-' . md5("{$root}/minn") . '.lock', 'c');
        flock($held, LOCK_EX);
        try {
            $zip = $archive('0.2.0');
            (new EngineUpdate("{$root}/minn", [$public]))->install($zip, hash('sha256', $zip), $sign($zip), '0.2.0');
            return 'installed';
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'Another update') && EngineUpdate::versionOf("{$root}/minn") === '0.1.0' ?: $e->getMessage();
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
            $clean($root);
        }
    },
    'a development checkout (a link, or under git) is refused' => static function () use ($webroot, $archive, $clean, $public, $sign) {
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
                    (new EngineUpdate($dir, [$public]))->install($zip, hash('sha256', $zip), $sign($zip), '0.2.0');
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
