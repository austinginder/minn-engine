<?php
/**
 * The Ed25519 key that signs Minn releases. The secret half stays on the
 * release maintainer's machine (never in this repository, on GitHub or on
 * Cloudflare); the public half is built into the engine
 * (Ops\EngineUpdate::KEYS), which refuses any minn.zip it does not verify.
 *
 *   php scripts/release-key.php generate [--key=PATH]   make a key; prints the public half to build in
 *   php scripts/release-key.php public   [--key=PATH]   print the public half of an existing key
 *
 * The default key file is ~/Keys/minn-release/ed25519.key (mode 600). Keep a
 * copy somewhere safe: a lost key means the next release cannot be signed by
 * a key the installed engines trust.
 */

declare(strict_types=1);

// Read by hand: getopt() stops at the command, so a --key after it would be ignored.
$command = '';
$path = getenv('HOME') . '/Keys/minn-release/ed25519.key';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--key=')) {
        $path = substr($arg, 6);
    } elseif (!str_starts_with($arg, '--')) {
        $command = $arg;
    }
}

if ($command === 'generate') {
    if (file_exists($path)) {
        fwrite(STDERR, "release-key: {$path} exists; refusing to overwrite a signing key.\n");
        exit(1);
    }
    is_dir(dirname($path)) || mkdir(dirname($path), 0700, true);
    $pair = sodium_crypto_sign_keypair();
    file_put_contents($path, base64_encode(sodium_crypto_sign_secretkey($pair)) . "\n");
    chmod($path, 0600);
    echo "Wrote {$path}\nPublic key (build it into Ops\\EngineUpdate::KEYS):\n" . base64_encode(sodium_crypto_sign_publickey($pair)) . "\n";
    exit(0);
}
if ($command === 'public') {
    $secret = base64_decode(trim((string) @file_get_contents($path)), true);
    if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        fwrite(STDERR, "release-key: no signing key at {$path}.\n");
        exit(1);
    }
    echo base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)) . "\n";
    exit(0);
}
fwrite(STDERR, "Usage: php scripts/release-key.php generate|public [--key=PATH]\n");
exit(1);
