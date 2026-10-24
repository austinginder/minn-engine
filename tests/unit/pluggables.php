<?php

declare(strict_types=1);

use Minn\Ops\ShapeFiles;
use Minn\Runtime\Pluggables;

/** The pluggable functions declared from the webroot's wp-includes/pluggable.php when the copy there is the running engine's (Austin, 2026-10-24). */
$engineDir = MINN_ENGINE_DIR;
$laid = Pluggables::laid($engineDir);
$own = (string) file_get_contents("{$engineDir}/wp-api/pluggable.php");
$root = sys_get_temp_dir() . '/minn-unit-pluggables-' . bin2hex(random_bytes(4));
$at = static function (string $name, string $contents) use ($root): string {
    @mkdir("{$root}/{$name}/wp-includes", 0755, true);
    file_put_contents("{$root}/{$name}/wp-includes/pluggable.php", $contents);
    return "{$root}/{$name}";
};
$current = $at('current', $laid);
$stale = $at('stale', $laid . "\n// edited\n");
$checks = [
    'the laid copy is the engine\'s file after a header of its own' => static fn () => str_starts_with($laid, '<?php') && str_ends_with($laid, ltrim(substr($own, 5), "\n")) && substr_count($laid, '<?php') === 1,
    'the laid copy lints' => static function () use ($current): bool {
        exec('php -l ' . escapeshellarg("{$current}/wp-includes/pluggable.php") . ' 2>&1', $out, $code);
        return $code === 0;
    },
    'a laid copy that is the engine\'s declares the functions' => static fn () => Pluggables::file($current, $engineDir) === "{$current}/wp-includes/pluggable.php",
    'a copy that differs leaves the declaring to the engine\'s own' => static fn () => Pluggables::file($stale, $engineDir) === "{$engineDir}/wp-api/pluggable.php",
    'no copy at all leaves it to the engine\'s own' => static fn () => Pluggables::file("{$root}/none", $engineDir) === "{$engineDir}/wp-api/pluggable.php",
    'an install lays the laid copy, not a loader' => static function () use ($root, $engineDir, $laid): bool {
        @mkdir("{$root}/install", 0755, true);
        ShapeFiles::lay($engineDir, "{$root}/install");
        return file_get_contents("{$root}/install/wp-includes/pluggable.php") === $laid;
    },
];
register_shutdown_function(static function () use ($root): void {
    exec('rm -rf ' . escapeshellarg($root));
});
return $checks;
