<?php
/**
 * Builds the release archive, dist/minn.zip: one top folder, minn/, holding
 * the engine as committed (public/minn at HEAD, so nothing untracked or
 * uncommitted rides along) and the latest Minn Admin release unpacked at
 * minn/admin in place of the development symlink. Minn Admin's archive is
 * checked against the sha256 GitHub publishes for it and unpacked through
 * the engine's own archive checks. The finished zip is opened again and
 * checked before its sha256 is printed and written beside it.
 *
 *   php scripts/build-release.php                    build from HEAD
 *   php scripts/build-release.php --admin-zip=PATH   bundle a Minn Admin zip in hand instead
 *   php scripts/build-release.php --out=DIR          write somewhere other than dist/
 *   php scripts/build-release.php --allow-dirty      build although public/minn has uncommitted changes
 */

declare(strict_types=1);

$root = dirname(__DIR__);
define('MINN_ENGINE_DIR', $root . '/public/minn');
require MINN_ENGINE_DIR . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

const ADMIN_RELEASE = 'https://api.github.com/repos/austinginder/minn-admin/releases/latest';

$options = getopt('', ['admin-zip:', 'out:', 'allow-dirty']);
$fail = static function (string $message): never {
    fwrite(STDERR, "build-release: {$message}\n");
    exit(1);
};
$run = static function (string $command) use ($fail): string {
    exec($command . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $fail("`{$command}` failed:\n" . implode("\n", $output));
    }
    return implode("\n", $output);
};
$fetch = static function (string $url, array $headers = []) use ($fail): string {
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'minn-build-release', CURLOPT_HTTPHEADER => $headers]);
    $body = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if (!is_string($body) || $code !== 200) {
        $fail("{$url} answered {$code}.");
    }
    return $body;
};

$version = Minn\Ops\EngineUpdate::versionOf(MINN_ENGINE_DIR);
$dirty = trim($run('git -C ' . escapeshellarg($root) . ' status --porcelain -- public/minn'));
if ($dirty !== '' && !isset($options['allow-dirty'])) {
    $fail("public/minn has uncommitted changes; the release is built from HEAD (--allow-dirty to build anyway):\n{$dirty}");
}

$work = sys_get_temp_dir() . '/minn-build-' . bin2hex(random_bytes(4));
mkdir($work, 0755, true);
register_shutdown_function(static fn () => Minn\Support\Files::deleteTree($work));

// The engine as committed.
$run('git -C ' . escapeshellarg($root) . ' archive --format=tar HEAD public/minn | tar -x -C ' . escapeshellarg($work));
rename("{$work}/public/minn", "{$work}/minn");
rmdir("{$work}/public");
if (file_exists("{$work}/minn/admin")) {
    $fail('public/minn/admin is tracked at HEAD; it must stay the gitignored development link.');
}

// The latest Minn Admin release, checked against GitHub's digest.
if (isset($options['admin-zip'])) {
    $adminZip = (string) $options['admin-zip'];
    $adminFrom = $adminZip;
} else {
    $release = json_decode($fetch(ADMIN_RELEASE, ['Accept: application/vnd.github+json']), true);
    $asset = null;
    foreach ($release['assets'] ?? [] as $candidate) {
        if (($candidate['name'] ?? '') === 'minn-admin.zip') {
            $asset = $candidate;
        }
    }
    if ($asset === null || !preg_match('/^sha256:([0-9a-f]{64})$/', (string) ($asset['digest'] ?? ''), $digest)) {
        $fail('the latest Minn Admin release has no minn-admin.zip with a sha256 digest.');
    }
    $bytes = $fetch((string) $asset['browser_download_url']);
    if (!hash_equals($digest[1], hash('sha256', $bytes))) {
        $fail('minn-admin.zip does not match the sha256 GitHub publishes for it.');
    }
    $adminZip = "{$work}/minn-admin.zip";
    file_put_contents($adminZip, $bytes);
    $adminFrom = (string) $release['tag_name'];
}
$tree = Minn\Ops\Archive::unpackFolder($adminZip, "{$work}/admin-stage");
if (basename($tree) !== 'minn-admin' || !is_file("{$tree}/minn-admin.php")) {
    $fail('the Minn Admin archive does not hold a minn-admin/ folder with minn-admin.php.');
}
rename($tree, "{$work}/minn/admin");
preg_match('/^\s*\*\s*Version:\s*(\S+)/mi', (string) file_get_contents("{$work}/minn/admin/minn-admin.php"), $adminVersion);
$adminVersion = $adminVersion[1] ?? '?';
if (!isset($options['admin-zip']) && ltrim($adminFrom, 'v') !== $adminVersion) {
    $fail("Minn Admin {$adminFrom} holds a plugin header that says {$adminVersion}.");
}

// The archive: minn/ at the top, bin/minn executable, no dotfiles.
$out = rtrim((string) ($options['out'] ?? "{$root}/dist"), '/');
is_dir($out) || mkdir($out, 0755, true);
$target = "{$out}/minn.zip";
@unlink($target);
$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    $fail("could not create {$target}.");
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$work}/minn", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$count = 0;
foreach ($files as $file) {
    $name = 'minn/' . substr($file->getPathname(), strlen("{$work}/minn/"));
    if ($file->isLink()) {
        $fail("{$name} is a symbolic link.");
    }
    if (str_starts_with($file->getFilename(), '.')) {
        continue;
    }
    if ($file->isDir()) {
        $zip->addEmptyDir($name);
        continue;
    }
    $zip->addFile($file->getPathname(), $name);
    $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, ($file->isExecutable() ? 0100755 : 0100644) << 16);
    $count++;
}
$zip->close();

// Opened again: what an install will find.
$check = new ZipArchive();
$check->open($target);
foreach (['minn/bootstrap.php', 'minn/bin/minn', 'minn/admin/minn-admin.php', 'minn/admin/assets/js/app.js'] as $needed) {
    if ($check->locateName($needed) === false) {
        $fail("{$needed} is missing from the archive.");
    }
}
foreach (['minn/changelog.md', 'minn/.install.json'] as $unwanted) {
    if ($check->locateName($unwanted) !== false) {
        $fail("{$unwanted} must not ship.");
    }
}
$check->close();
$sha256 = hash_file('sha256', $target);
file_put_contents("{$target}.sha256", "{$sha256}  minn.zip\n");

printf(
    "%s\n  Minn %s, Minn Admin %s (%s)\n  %d files, %.1f MB\n  sha256 %s\n",
    $target,
    $version,
    $adminVersion,
    $adminFrom,
    $count,
    filesize($target) / 1048576,
    $sha256,
);
