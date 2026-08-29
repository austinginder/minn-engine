<?php
ini_set("display_errors", "stderr");
require __DIR__ . '/engine-runtime.php';
require __DIR__ . '/' . basename($argv[1] ?? 'api-probe.php');
