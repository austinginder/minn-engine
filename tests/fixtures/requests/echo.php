<?php
// The request as JSON (method, query, headers, raw body, parsed form, cookies),
// or what the query asks for: ?redirect=N answers 302 to itself N times,
// ?status=404 that status, ?cookies=1 sets two cookies, ?slow=2 waits.
if (isset($_GET['slow'])) {
    sleep((int) $_GET['slow']);
}
if (isset($_GET['redirect']) && (int) $_GET['redirect'] > 0) {
    header('Location: echo.php?redirect=' . ((int) $_GET['redirect'] - 1) . (isset($_GET['to']) ? '&to=' . rawurlencode((string) $_GET['to']) : ''), true, (int) ($_GET['code'] ?? 302));
    exit;
}
if (isset($_GET['to'])) {
    header('Location: ' . $_GET['to'], true, 302);
    exit;
}
if (isset($_GET['cookies'])) {
    setcookie('plain', 'one', ['path' => '/']);
    setcookie('flagged', 'two words', ['path' => '/wp-content/', 'httponly' => true, 'samesite' => 'Lax', 'expires' => 2139722880]);
}
http_response_code((int) ($_GET['status'] ?? 200));
header('Content-Type: application/json; charset=utf-8');
header('X-Multi: one');
header('X-Multi: two', false);
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
        $headers[strtolower(str_replace('_', '-', $key))] = $value;
    }
}
ksort($headers);
echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'query' => $_GET, 'headers' => $headers, 'body' => file_get_contents('php://input'), 'form' => $_POST, 'cookies' => $_COOKIE], JSON_UNESCAPED_SLASHES);
