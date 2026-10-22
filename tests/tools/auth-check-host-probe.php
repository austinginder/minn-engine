<?php
/**
 * wp_auth_check_html by the request's host, over HTTPS and not: only the
 * login page's own host, as HTTP_HOST names it, shows the login frame; a
 * port (even 443), another host or another case gets the fallback alone.
 * Same protocol as api-probe.php.
 */
$log = [];
$saved = [$_SERVER['HTTP_HOST'] ?? null, $_SERVER['HTTPS'] ?? null];
foreach (['on', null] as $https) {
    foreach (['minn.localhost', 'minn.localhost:443', 'minn.localhost:8443', 'other.localhost', 'MINN.localhost'] as $host) {
        $_SERVER['HTTP_HOST'] = $host;
        if ($https === null) {
            unset($_SERVER['HTTPS']);
        } else {
            $_SERVER['HTTPS'] = $https;
        }
        ob_start();
        wp_auth_check_html();
        $html = (string) ob_get_clean();
        $log[] = [($https === null ? 'http ' : 'https ') . $host, [str_contains($html, 'wp-auth-fallback'), str_contains($html, 'data-src='), str_replace(parse_url(home_url(), PHP_URL_HOST), '{host}', $html)]];
    }
}
[$_SERVER['HTTP_HOST'], $_SERVER['HTTPS']] = $saved;
if ($saved[1] === null) {
    unset($_SERVER['HTTPS']);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
