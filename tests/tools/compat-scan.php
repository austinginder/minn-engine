<?php
/**
 * Runs the symbol gate over a directory of plugin or theme folders using an
 * exported gap, with no database and no facade loaded. Built to run on the
 * mirror box, which holds the wp.org corpus but no engine.
 *
 * php compat-scan.php <gap.json> <folder-list-file> > report.json
 *
 * The list file is one path per line; a line may be "<name>\t<path>".
 */
declare(strict_types=1);

if (!defined('MINN_ENGINE_DIR')) {
    define('MINN_ENGINE_DIR', dirname(__DIR__, 2) . '/public/minn');
}
require MINN_ENGINE_DIR . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

$gap = Minn\Runtime\SymbolGap::fromFile($argv[1] ?? '');
$report = [];
foreach (file($argv[2] ?? '', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $parts = explode("\t", $line);
    $path = trim(array_pop($parts));
    $name = $parts === [] ? basename($path) : trim($parts[0]);
    if (!is_dir($path)) {
        $report[$name] = ['state' => 'missing'];
        continue;
    }
    $missing = Minn\Runtime\Symbols::missingAgainst($path, $gap);
    $loads = $missing['functions'] === [] && $missing['classes'] === [] && !$missing['truncated'];
    $report[$name] = ['state' => $loads ? 'loads' : 'skipped'] + $missing;
    fwrite(STDERR, sprintf("%-40s %s\n", $name, $report[$name]['state']));
}
echo json_encode($report, JSON_UNESCAPED_SLASHES), "\n";
