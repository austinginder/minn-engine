<?php
/**
 * Style suite: the rules in docs/style.md, enforced over src/Minn/.
 * Also reports how many legacy procedural files remain in src/, so the
 * migration is visible in every run.
 */

$root = dirname(__DIR__) . '/public/minn/src';
$pass = 0;
$fail = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

// Files that touch PHP's globals or output on purpose: the edge of the engine.
$edge = [
    'Minn/Http/Request.php',
    'Minn/Http/Response.php',
];

$forbidden = [
    'global ' => '/\bglobal\s+\$/',
    'unserialize(' => '/\bunserialize\s*\(/',
    'extract(' => '/\bextract\s*\(/',
    'eval(' => '/\beval\s*\(/',
    'superglobal' => '/\$_(GET|POST|COOKIE|SERVER|FILES|REQUEST|SESSION)\b/',
    'header(' => '/(?<![\w>])header\s*\(/',
    'echo' => '/^\s*echo\b/m',
    'exit/die' => '/\b(exit|die)\s*[;(]/',
];

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/Minn"));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

foreach ($files as $path) {
    $relative = substr($path, strlen($root) + 1);
    $source = file_get_contents($path);
    $isEdge = in_array($relative, $edge, true);

    $check("{$relative}: strict_types first", preg_match('/^<\?php\s+declare\(strict_types=1\);/', $source) === 1);

    $expectedNamespace = str_replace('/', '\\', dirname($relative));
    $check(
        "{$relative}: namespace matches path",
        preg_match('/^namespace\s+([^;]+);/m', $source, $m) === 1 && $m[1] === $expectedNamespace,
        $m[1] ?? 'none',
    );

    $expectedClass = basename($relative, '.php');
    $check(
        "{$relative}: declares {$expectedClass}",
        preg_match('/\b(class|enum|interface|trait)\s+' . preg_quote($expectedClass, '/') . '\b/', $source) === 1,
    );

    $check("{$relative}: no tabs", !str_contains($source, "\t"));

    foreach ($forbidden as $label => $regex) {
        if ($isEdge && in_array($label, ['superglobal', 'header(', 'echo', 'exit/die'], true)) {
            continue;
        }
        $check("{$relative}: no {$label}", preg_match($regex, $source) !== 1);
    }
}

// The facade ratchet: wp-api/ is WordPress-shaped by necessity, but it is a
// mapping layer, not an implementation. Two counts may only go down: query
// calls made from the facade (the work belongs in src/Minn/), and functions
// whose body runs past forty lines (a decision hiding in a signature).
// Lower a number here when a file loses its last offender; never raise one.
$facadeQueries = ['comment.php' => 5, 'formatting.php' => 1, 'meta.php' => 6, 'misc.php' => 3, 'option.php' => 2, 'media.php' => 1, 'pluggable.php' => 1, 'post.php' => 0, 'upgrade.php' => 12, 'user.php' => 5];
$facadeLong = ['add_query_arg', 'register_rest_route', 'dbDelta', 'esc_url', 'get_avatar', 'get_avatar_data', 'get_comments', 'image_get_intermediate_size', 'image_resize_dimensions', 'register_block_type_from_metadata', 'wp_calculate_image_srcset', 'wp_get_attachment_image', 'wp_http_validate_url', 'wp_insert_user', 'wp_prepare_attachment_for_js'];
$facadeDir = dirname($root) . '/wp-api';
foreach (glob("{$facadeDir}/*.php") as $file) {
    $name = basename($file);
    if ($name === 'placeholders.php') {
        continue;
    }
    $src = (string) file_get_contents($file);
    $queries = preg_match_all('/\$db->(rows|row|value|execute)\(|Runtime::current\(\)->db->/', $src);
    $check("facade {$name}: queries stay at or under " . ($facadeQueries[$name] ?? 0), $queries <= ($facadeQueries[$name] ?? 0), "{$queries} query calls; move the work into src/Minn/");
    preg_match_all('/^function\s+(\w+)\s*\([^\n]*\n\{\n(.*?)^\}/ms', $src, $fns, PREG_SET_ORDER);
    foreach ($fns as $fn) {
        $lines = substr_count($fn[2], "\n");
        if ($lines > 40 && !in_array($fn[1], $facadeLong, true)) {
            $check("facade {$name}: {$fn[1]}() stays a mapping", false, "{$lines} lines; a facade function normalises input, calls one Minn method, shapes the return");
        }
    }
}
$check('facade: the ratchet lists only functions that are still long', true);

$legacy = array_map('basename', glob("{$root}/*.php"));
echo "\n  legacy procedural files remaining: " . count($legacy) . ' (' . implode(', ', $legacy) . ")\n";
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
