<?php
/**
 * Writes contracts/api/routes.json: every #[Route] under src/Minn as a row
 * (method, pattern, name, handler, summary, structured policy, args, body),
 * read from the classes alone. Run it after any route or policy change; the
 * style suite fails while the file is stale.
 *
 *   php tests/tools/route-catalogue.php            write the file
 *   php tests/tools/route-catalogue.php --check    print {"stale": bool, ...} and exit 1 when stale
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/public/');
}
if (!defined('MINN_ENGINE_DIR')) {
    define('MINN_ENGINE_DIR', $root . '/public/minn');
}
require MINN_ENGINE_DIR . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

preg_match("/define\('MINN_ENGINE_VERSION', '([^']+)'\)/", (string) file_get_contents(MINN_ENGINE_DIR . '/bootstrap.php'), $version);
$rows = Minn\Rest\Catalogue::scan(MINN_ENGINE_DIR . '/src/Minn');
$document = Minn\Rest\Catalogue::document($rows, $version[1] ?? 'unknown');
$json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
$target = $root . '/contracts/api/routes.json';
$current = is_file($target) ? file_get_contents($target) : '';
$summary = ['routes' => $document['routes'], 'withPolicy' => $document['withPolicy'], 'stale' => $current !== $json];
if (in_array('--check', $argv, true)) {
    echo json_encode($summary), "\n";
    exit($summary['stale'] ? 1 : 0);
}
file_put_contents($target, $json);
echo "contracts/api/routes.json: {$document['routes']} routes, {$document['withPolicy']} with a policy\n";
