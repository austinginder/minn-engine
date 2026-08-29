<?php
ini_set("display_errors", "stderr");
require __DIR__ . '/engine-runtime.php';
// The probe runs in its own scope, as it does under wp eval-file on the reference.
(static function (string $probe): void {
    require __DIR__ . '/' . basename($probe);
})($argv[1] ?? 'api-probe.php');
