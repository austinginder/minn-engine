<?php

declare(strict_types=1);

use Minn\Runtime\LegacyPack;

/**
 * WordPress's browser files answered from the Minn Legacy Pack: only at the
 * addresses WordPress serves them from, only while the pack is active, only
 * files inside it, and only types a browser loads.
 */
$content = sys_get_temp_dir() . '/minn-unit-legacy-pack-' . getmypid();
$pack = $content . '/plugins/minn-legacy-pack';
@mkdir($pack . '/wp-includes/js', 0755, true);
@mkdir($pack . '/wp-admin/css', 0755, true);
file_put_contents($pack . '/wp-includes/js/backbone.min.js', '/* backbone */');
file_put_contents($pack . '/wp-admin/css/media.min.css', '/* media */');
file_put_contents($pack . '/wp-includes/js/notes.php', '<?php');
file_put_contents($content . '/secret.js', 'outside');
register_shutdown_function(static function () use ($content): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($content, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($content);
});
$active = [LegacyPack::PLUGIN];

return [
    'the addresses WordPress serves its browser files from are claimed, and nothing else' => static function (): bool|string {
        $yes = ['/wp-includes/js/backbone.min.js', '/wp-includes/css/dashicons.min.css', '/wp-includes/fonts/dashicons.woff2', '/wp-includes/images/spinner.gif', '/wp-admin/js/image-edit.min.js', '/wp-admin/css/media.min.css', '/wp-admin/images/wpspin.gif'];
        $no = ['/wp-admin/post.php', '/wp-includes/version.php', '/wp-content/plugins/x.js', '/wp-includes/js/../../wp-config.php', '/wp-admin/', '/wp-includes/js/x.js?ver=1'];
        $bad = array_merge(array_filter($yes, static fn (string $p): bool => !LegacyPack::claims($p)), array_filter($no, static fn (string $p): bool => LegacyPack::claims($p)));
        return $bad === [] ? true : json_encode(array_values($bad));
    },
    'an active pack answers with its file' => static function () use ($content, $pack, $active): bool|string {
        $file = LegacyPack::file($content, '/wp-includes/js/backbone.min.js', $active);
        $css = LegacyPack::file($content, '/wp-admin/css/media.min.css', $active);
        return $file === realpath($pack . '/wp-includes/js/backbone.min.js') && $css === realpath($pack . '/wp-admin/css/media.min.css') ? true : json_encode([$file, $css]);
    },
    'an inactive pack answers nothing' => static function () use ($content): bool|string {
        return LegacyPack::file($content, '/wp-includes/js/backbone.min.js', ['other/other.php']) === null ? true : 'answered';
    },
    'a missing file, a PHP file and a path out of the pack answer nothing' => static function () use ($content, $active): bool|string {
        $got = [
            LegacyPack::file($content, '/wp-includes/js/nope.js', $active),
            LegacyPack::file($content, '/wp-includes/js/notes.php', $active),
            LegacyPack::file($content, '/wp-includes/js/../../../secret.js', $active),
        ];
        return $got === [null, null, null] ? true : json_encode($got);
    },
    'the answer carries the file with its type' => static function () use ($pack): bool|string {
        $response = LegacyPack::response($pack . '/wp-admin/css/media.min.css');
        return $response->status === 200 && $response->body === '/* media */' && $response->headers['Content-Type'] === 'text/css' ? true : json_encode([$response->status, $response->headers]);
    },
];
