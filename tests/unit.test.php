<?php
/**
 * The unit suite: pure classes proven without a site, a database, or an
 * oracle. Each file under tests/unit/ returns a list of cases, and a case
 * is a label plus a closure that returns true, false, or a string naming
 * what went wrong. The whole suite runs in well under a second, which is
 * the point: this is where a Minn class is proven while it is being written.
 *
 *   php tests/unit.test.php            all cases
 *   php tests/unit.test.php db         only the files whose name contains "db"
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/fixtures/');
}
if (!defined('MINN_ENGINE_DIR')) {
    define('MINN_ENGINE_DIR', dirname(__DIR__) . '/public/minn');
}
require MINN_ENGINE_DIR . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

$only = $argv[1] ?? '';
$pass = 0;
$fail = 0;
foreach (glob(__DIR__ . '/unit/*.php') as $file) {
    $name = basename($file, '.php');
    if ($only !== '' && !str_contains($name, $only)) {
        continue;
    }
    echo "  {$name}\n";
    foreach ((array) require $file as $label => $case) {
        try {
            $result = $case();
        } catch (Throwable $e) {
            $result = get_class($e) . ': ' . $e->getMessage();
        }
        if ($result === true) {
            $pass++;
            echo "  ok   {$label}\n";
        } else {
            $fail++;
            echo "  FAIL {$label}" . (is_string($result) ? " ({$result})" : '') . "\n";
        }
    }
}
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
