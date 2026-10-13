<?php

declare(strict_types=1);

use Minn\Auth\LoginThrottle;
use Minn\Auth\Password;
use Minn\Auth\PortableHash;
use Minn\Cli\Installer;
use Minn\Support\Kses;
use Minn\Support\Url;

/** The security sweep of 2026-10-13 (docs/security-review-2026-10-13.md): the fixes that live in pure code. */
$private = static function (string $class, string $method): Closure {
    $reflection = new ReflectionMethod($class, $method);
    return static fn (...$args) => $reflection->invoke(null, ...$args);
};
return [
    'a URL scheme is judged without the tabs, line breaks and control characters a browser drops' => static function (): bool|string {
        foreach (["java\tscript:x", "java\nscript:x", "java\r\nscript:x", " \x01javascript:x"] as $url) {
            if (Kses::url($url) !== '') {
                return json_encode($url) . ' passed';
            }
        }
        return Kses::url('https://example.com/a') === 'https://example.com/a' && Kses::url('/relative') === '/relative' && Kses::url('mailto:a@example.com') === 'mailto:a@example.com' ? true : 'an allowed URL changed';
    },
    'a safe request refuses every address outside the global range, as the reference refuses them' => static function (): bool|string {
        $refused = ['127.0.0.1', '10.0.0.1', '169.254.169.254', '100.64.0.1', '100.100.100.200', '198.18.0.1', '192.0.0.9', '192.0.2.1', '192.88.99.1', '198.51.100.1', '203.0.113.1', '224.0.0.1', '240.0.0.1', '255.255.255.255'];
        $allowed = ['8.8.8.8', '1.1.1.1', '100.63.255.255', '100.128.0.1', '198.20.0.1', '172.32.0.1', '223.255.255.255'];
        $check = static fn (string $ip): bool => Url::validateForHttp("http://{$ip}/x", 'site.example', 443, static fn (): bool => false) !== null;
        foreach ($refused as $ip) {
            if ($check($ip)) {
                return "{$ip} allowed";
            }
        }
        foreach ($allowed as $ip) {
            if (!$check($ip)) {
                return "{$ip} refused";
            }
        }
        return true;
    },
    'a password over 4,096 characters is never hashed and never matches' => static function (): bool|string {
        $long = str_repeat('a', 4097);
        $atLimit = str_repeat('a', 4096);
        if (Password::hash($long) !== '*' || PortableHash::hash($long) !== '*') {
            return 'a long password was hashed';
        }
        if (Password::verify($long, Password::hash($atLimit)) || PortableHash::verify($long, PortableHash::hash($atLimit))) {
            return 'a long password matched';
        }
        return Password::verify($atLimit, Password::hash($atLimit)) && PortableHash::verify($atLimit, PortableHash::hash($atLimit)) ? true : 'a password at the limit failed';
    },
    'the cookie fragment: offset 8 for phpass and plain bcrypt, the last four for anything else' => static function (): bool|string {
        $cases = ['$P$Babcdefghijklmnopqrstuvwxyz0123' => 'efgh', '$2y$10$abcdefghijklmnopqrstuv' => 'bcde', '$wp$2y$12$abcdefghijklmnopqrstuv' => 'stuv', '$argon2id$v=19$m=65536,t=4,p=1$wxyz' => 'wxyz'];
        foreach ($cases as $hash => $fragment) {
            if (Password::fragment($hash) !== $fragment) {
                return "{$hash}: " . Password::fragment($hash);
            }
        }
        return true;
    },
    'an eject acts only on paths inside the webroot' => static function () use ($private): bool|string {
        $inside = $private(Installer::class, 'inside');
        foreach (['wp-settings.php' => true, 'minn-site/x' => true, 'ok..name' => true, '../other-site' => false, '/etc/hosts' => false, 'a/../b' => false, '' => false] as $path => $expected) {
            if ($inside($path) !== $expected) {
                return json_encode($path);
            }
        }
        return true;
    },
    'a URL attribute an interactive block binds keeps only an allowed scheme' => static function (): bool|string {
        $refused = new Minn\Runtime\TagEditor('<a>', []);
        $refused->set('href', "java\tscript:x");
        $kept = new Minn\Runtime\TagEditor('<a>', []);
        $kept->set('href', 'https://example.com/a');
        return $refused->html() === '<a href="">' && $kept->html() === '<a href="https://example.com/a">' ? true : $refused->html() . ' / ' . $kept->html();
    },
    "an archive's __MACOSX notes are checked like any entry and never unpacked" => static function (): bool|string {
        $dir = sys_get_temp_dir() . '/minn-sweep-zip-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($dir);
        $zip = new ZipArchive();
        $zip->open("{$dir}/a.zip", ZipArchive::CREATE);
        $zip->addFromString('top/minn.json', '{}');
        $zip->addFromString('__MACOSX/._minn.json', 'x');
        $zip->close();
        $folder = Minn\Ops\Archive::unpackFolder("{$dir}/a.zip", "{$dir}/stage");
        $written = is_file("{$folder}/minn.json") && !file_exists("{$dir}/stage/__MACOSX");
        $climbing = new ZipArchive();
        $climbing->open("{$dir}/b.zip", ZipArchive::CREATE);
        $climbing->addFromString('top/minn.json', '{}');
        $climbing->addFromString('__MACOSX/../outside.txt', 'x');
        $climbing->close();
        try {
            Minn\Ops\Archive::inspect("{$dir}/b.zip");
            $refused = false;
        } catch (Minn\RestError) {
            $refused = true;
        }
        Minn\Support\Files::deleteTree($dir);
        return $written && $refused ? true : ($written ? 'a climbing note passed' : 'the notes were unpacked');
    },
    'the sign-in throttle counts an IPv6 address by its /64, and keeps IPv4 clients apart' => static function () use ($private): bool|string {
        $key = $private(LoginThrottle::class, 'key');
        if ($key('2001:db8:1:2::1') !== $key('2001:db8:1:2:ffff::9') || $key('2001:db8:1:2::1') === $key('2001:db8:1:3::1')) {
            return 'IPv6 grouping';
        }
        return $key('::ffff:198.51.100.4') !== $key('::ffff:198.51.100.5') && $key('198.51.100.4') !== $key('198.51.100.5') ? true : 'IPv4 clients shared a counter';
    },
];
