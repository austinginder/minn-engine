<?php
/**
 * wp_guess_url by where the running script sits: the install's root, a
 * folder inside it, a folder above it (WordPress in a folder of the
 * site), wp-admin and wp-login.php, somewhere else; the install's own
 * folder name read as {folder}. Same protocol as api-probe.php.
 */
$abs = rtrim(ABSPATH, '/');
$above = dirname($abs);
$cases = [
    'root index' => [$abs . '/index.php', '/index.php', '/index.php'],
    'root page' => [$abs . '/index.php', '/about/', '/index.php'],
    'subdir script' => [$abs . '/sub/x.php', '/sub/x.php', '/sub/x.php'],
    'subdir script, query' => [$abs . '/sub/x.php', '/sub/x.php?a=1', '/sub/x.php'],
    'above, index' => [$above . '/index.php', '/index.php', '/index.php'],
    'above, page' => [$above . '/index.php', '/blog/about/', '/index.php'],
    'above, wp in folder' => [$above . '/index.php', '/wp/hello/', '/index.php'],
    'wp-admin' => [$abs . '/wp-admin/index.php', '/wp/wp-admin/index.php', '/wp/wp-admin/index.php'],
    'wp-login' => [$abs . '/wp-login.php', '/wp/wp-login.php?action=x', '/wp/wp-login.php'],
    'elsewhere' => ['/tmp/zz/x.php', '/zz/x.php', '/zz/x.php'],
    'above, a query with a slash' => [$above . '/index.php', '/blog/?a=b/c', '/index.php'],
    'above, https' => [$above . '/index.php', '/blog/', '/index.php'],
];
$saved = $_SERVER;
$out = [];
foreach ($cases as $k => [$file, $uri, $self]) {
    $_SERVER['SCRIPT_FILENAME'] = $file; $_SERVER['REQUEST_URI'] = $uri; $_SERVER['PHP_SELF'] = $self; $_SERVER['HTTP_HOST'] = 'example.test';
    if ($k === 'above, https') { $_SERVER['HTTPS'] = 'on'; } else { unset($_SERVER['HTTPS']); }
    $out[] = [$k, str_replace(basename($abs), '{folder}', wp_guess_url())];
}
$_SERVER = $saved;
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
