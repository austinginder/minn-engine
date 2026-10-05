<?php
ini_set("display_errors", "stderr");
// A probe that names the request's host (mail's EHLO and Message-ID do) asks for one, as the reference runner sets it.
if (getenv('MINN_PROBE_HOST')) {
    $_SERVER += ['HTTP_HOST' => getenv('MINN_PROBE_HOST'), 'SERVER_NAME' => getenv('MINN_PROBE_HOST')];
}
require __DIR__ . '/engine-runtime.php';
// The probe runs in its own scope, as it does under wp eval-file on the reference.
(static function (string $probe): void {
    require __DIR__ . '/' . basename($probe);
})($argv[1] ?? 'api-probe.php');
