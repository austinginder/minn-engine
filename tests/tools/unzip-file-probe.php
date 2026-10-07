<?php
/**
 * unzip_file() as plugins call it (probe unzip-file): refused before
 * WP_Filesystem() sets up the filesystem; then an archive with nested and
 * empty folders unpacked over a destination it creates, over one that
 * already has files, a file that is not an archive, one that is missing,
 * resource-fork entries, an entry that climbs out of the destination, and
 * what the pre_unzip_file and unzip_file filters are handed. Archives are
 * made here and everything is removed afterwards. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$work = rtrim(sys_get_temp_dir(), '/') . '/minn-unzip-probe-' . getmypid();
$rel = static fn ($value) => is_string($value) ? str_replace($work, '{work}', $value) : $value;
$shape = static fn ($result) => $result instanceof WP_Error ? ['error', $result->get_error_code(), $rel($result->get_error_message()), $rel($result->get_error_data())] : $result;
$listing = static function (string $dir) use ($work): array {
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $out[] = substr($item->getPathname(), strlen($dir) + 1) . ($item->isDir() ? '/' : ' ' . $item->getSize());
    }
    sort($out);
    return $out;
};
$zip = static function (string $file, array $entries): void {
    $archive = new ZipArchive();
    $archive->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        $content === null ? $archive->addEmptyDir($name) : $archive->addFromString($name, $content);
    }
    $archive->close();
};
$heard = [];
add_filter('pre_unzip_file', static function ($result, ...$args) use (&$heard, $rel) {
    $heard[] = ['pre_unzip_file', $result, array_map($rel, array_map(static fn ($a) => is_array($a) ? count($a) : $a, $args))];
    return $result;
}, 10, 5);
add_filter('unzip_file', static function ($result, ...$args) use (&$heard, $rel) {
    $heard[] = ['unzip_file', $result instanceof WP_Error ? $result->get_error_code() : $result, array_map($rel, array_map(static fn ($a) => is_array($a) ? count($a) : $a, $args))];
    return $result;
}, 10, 5);
@mkdir($work, 0755, true);
try {
    $zip("{$work}/good.zip", ['a/b.txt' => 'bee', 'c.txt' => 'see', 'd/' => null, 'a/e/f.txt' => 'eff']);
    $zip("{$work}/fork.zip", ['x.txt' => 'ex', '__MACOSX/._x.txt' => 'fork', '.DS_Store' => 'store']);
    $zip("{$work}/climb.zip", ['ok.txt' => 'ok', '../escaped.txt' => 'out']);
    file_put_contents("{$work}/not.zip", 'not an archive');
    $saved = $GLOBALS['wp_filesystem'] ?? null;
    unset($GLOBALS['wp_filesystem']);
    $say('before WP_Filesystem', $shape(unzip_file("{$work}/good.zip", "{$work}/out-early")));
    $say('WP_Filesystem', WP_Filesystem());
    $heard = [];
    $say('an archive into a new folder', [$shape(unzip_file("{$work}/good.zip", "{$work}/out")), $listing("{$work}/out")]);
    $say('the filters', $heard);
    file_put_contents("{$work}/out/c.txt", 'changed');
    file_put_contents("{$work}/out/keep.txt", 'kept');
    $say('over a folder with files', [$shape(unzip_file("{$work}/good.zip", "{$work}/out")), $listing("{$work}/out"), file_get_contents("{$work}/out/c.txt")]);
    $say('not an archive', $shape(unzip_file("{$work}/not.zip", "{$work}/out-not")));
    $say('a missing archive', $shape(unzip_file("{$work}/missing.zip", "{$work}/out-missing")));
    $say('resource forks', [$shape(unzip_file("{$work}/fork.zip", "{$work}/out-fork")), $listing("{$work}/out-fork")]);
    $say('an entry that climbs out', [$shape(unzip_file("{$work}/climb.zip", "{$work}/out-climb/inner")), $listing("{$work}/out-climb"), file_exists("{$work}/escaped.txt") || file_exists("{$work}/out-climb/escaped.txt")]);
    $GLOBALS['wp_filesystem'] = $saved;
} finally {
    if (is_dir($work)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($work);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
