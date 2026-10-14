<?php
/**
 * The class names of WordPress's bundled AI client (wp-includes/php-ai-client),
 * which the reference loads on demand, so the API inventory (built from what a
 * booted reference has declared) sees only the dozen loaded at boot. The
 * symbol gate needs them all to know that a provider plugin extends classes
 * the engine does not carry.
 *
 * Names only: the client's own autoloader is asked to load each class its
 * PSR-4 layout maps a file to, and the names PHP then knows are kept. No
 * source is read and WordPress is not booted.
 *
 *   php tests/tools/ai-client-names.php /path/to/wp-reference            print the names, one a line
 *   php tests/tools/ai-client-names.php /path/to/wp-reference --merge    add them to public/minn/data/api-names.json
 */

declare(strict_types=1);

$reference = rtrim((string) ($argv[1] ?? ''), '/');
$client = "{$reference}/wp-includes/php-ai-client";
if ($reference === '' || !is_file("{$client}/autoload.php")) {
    fwrite(STDERR, "Usage: php tests/tools/ai-client-names.php /path/to/wp-reference [--merge]\n");
    exit(1);
}
define('ABSPATH', $reference . '/');
define('WPINC', 'wp-includes');
require "{$client}/autoload.php";

$names = [];
$src = "{$client}/src";
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $name = 'WordPress\\AiClient\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($src) + 1, -4));
    if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
        $names[] = (new ReflectionClass($name))->getName();
    }
}
sort($names);

if (!in_array('--merge', $argv, true)) {
    echo implode("\n", $names), "\n";
    fwrite(STDERR, count($names) . " names\n");
    exit(0);
}
$path = dirname(__DIR__, 2) . '/public/minn/data/api-names.json';
$data = json_decode((string) file_get_contents($path), true);
$before = count($data['classes']);
$data['classes'] = array_values(array_unique([...$data['classes'], ...$names]));
sort($data['classes']);
// The file keeps one name a line, unindented, with no newline at the end.
file_put_contents($path, (string) preg_replace('/^ +/m', '', (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
fwrite(STDERR, sprintf("%s: %d classes, %d added\n", $path, count($data['classes']), count($data['classes']) - $before));
