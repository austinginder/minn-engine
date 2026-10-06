<?php
/**
 * The local server tests/http.test.php talks to (php -S router): echoes a
 * request, redirects, sends a body of a chosen size with or without its
 * length, sets cookies, and sleeps.
 */

declare(strict_types=1);

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch ($path) {
    case '/echo':
        header('Content-Type: application/json');
        echo json_encode([
            'method' => $_SERVER['REQUEST_METHOD'],
            'query' => $_GET,
            'form' => $_POST,
            'body' => file_get_contents('php://input'),
            'type' => $_SERVER['CONTENT_TYPE'] ?? null,
            'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        ], JSON_UNESCAPED_SLASHES);
        return true;
    case '/redirect':
        header('Location: ' . ($_GET['to'] ?? '/echo'), true, (int) ($_GET['status'] ?? 302));
        return true;
    case '/big':
        $bytes = (int) ($_GET['bytes'] ?? 0);
        if (isset($_GET['announce'])) {
            header('Content-Length: ' . $bytes);
            echo str_repeat('x', $bytes);
            return true;
        }
        for ($sent = 0; $sent < $bytes; $sent += 8192) {
            echo str_repeat('x', min(8192, $bytes - $sent));
            flush();
        }
        return true;
    case '/cookies':
        header('Set-Cookie: plain=one; path=/', false);
        header('Set-Cookie: flagged=two%20words; HttpOnly', false);
        header('X-Multi: one', false);
        header('X-Multi: two', false);
        echo 'ok';
        return true;
    case '/slow':
        usleep((int) ($_GET['ms'] ?? 0) * 1000);
        echo 'late';
        return true;
}
http_response_code(404);
echo 'none';
return true;
