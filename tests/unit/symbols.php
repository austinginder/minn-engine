<?php

declare(strict_types=1);

use Minn\Runtime\SymbolGap;
use Minn\Runtime\Symbols;

/**
 * The static symbol gate judged against a hand-made gap: the runtime here
 * "lacks" wp_missing_fn and WP_Missing_Class and "defines" wp_mail and
 * wp_present_fn. Each case writes a plugin folder and reads the verdict.
 */
$scratch = sys_get_temp_dir() . '/minn-unit-symbols-' . getmypid();
$gapFile = $scratch . '/gap.json';
@mkdir($scratch, 0755, true);
file_put_contents($gapFile, json_encode([
    'functions' => ['wp_missing_fn'],
    'classes' => ['WP_Missing_Class'],
    'known' => ['wp_missing_fn', 'wp_mail', 'wp_present_fn'],
]));
$gap = SymbolGap::fromFile($gapFile);
register_shutdown_function(static function () use ($scratch): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($scratch);
});

$verdict = static function (string $name, string $source) use ($scratch, $gap): array {
    $dir = "{$scratch}/{$name}";
    @mkdir($dir, 0755, true);
    file_put_contents("{$dir}/{$name}.php", "<?php\n" . $source);
    return Symbols::missingAgainst($dir, $gap);
};

return [
    'a call to a function the runtime lacks is reported' => static function () use ($verdict): bool|string {
        $v = $verdict('call-missing', 'wp_missing_fn(1); wp_present_fn();');
        return $v['functions'] === ['wp_missing_fn'] && $v['classes'] === [] && $v['redeclares'] === [] ? true : json_encode($v);
    },
    'new on a class the runtime lacks is reported (the class half of the gate)' => static function () use ($verdict): bool|string {
        $v = $verdict('new-missing', '$x = new WP_Missing_Class();');
        return $v['classes'] === ['WP_Missing_Class'] ? true : json_encode($v);
    },
    'extends a class the runtime lacks is reported' => static function () use ($verdict): bool|string {
        $v = $verdict('extends-missing', 'class Mine extends WP_Missing_Class {}');
        return $v['classes'] === ['WP_Missing_Class'] ? true : json_encode($v);
    },
    'a static call on a class the runtime lacks is reported' => static function () use ($verdict): bool|string {
        $v = $verdict('static-missing', 'WP_Missing_Class::go();');
        return $v['classes'] === ['WP_Missing_Class'] ? true : json_encode($v);
    },
    'a guarded call and a guarded class are not reported' => static function () use ($verdict): bool|string {
        $v = $verdict('guarded', "if (function_exists('wp_missing_fn')) { wp_missing_fn(); } if (class_exists('WP_Missing_Class')) { new WP_Missing_Class(); }");
        return $v['functions'] === [] && $v['classes'] === [] ? true : json_encode($v);
    },
    'the folder\'s own declarations are not reported' => static function () use ($verdict): bool|string {
        $v = $verdict('own', 'function wp_missing_fn() {} class WP_Missing_Class {} wp_missing_fn(); new WP_Missing_Class();');
        return $v['functions'] === [] && $v['classes'] === [] ? true : json_encode($v);
    },
    'an unguarded declaration of a function the runtime defines is a redeclaration' => static function () use ($verdict): bool|string {
        $v = $verdict('pluggable', 'function wp_mail($to) { return true; }');
        return $v['redeclares'] === ['wp_mail'] && $v['functions'] === [] ? true : json_encode($v);
    },
    'a declaration behind function_exists is not' => static function () use ($verdict): bool|string {
        $v = $verdict('pluggable-guarded', "if (!function_exists('wp_mail')) { function wp_mail(\$to) { return true; } }");
        return $v['redeclares'] === [] ? true : json_encode($v);
    },
    'a plugin\'s own new function is neither missing nor a redeclaration' => static function () use ($verdict): bool|string {
        $v = $verdict('own-new', 'function acme_helper() {} acme_helper();');
        return $v['functions'] === [] && $v['redeclares'] === [] ? true : json_encode($v);
    },
    'a namespaced plugin\'s bare class names resolve inside its namespace' => static function () use ($verdict): bool|string {
        $v = $verdict('namespaced', 'namespace Acme; class Thing extends Base {} $x = new \WP_Missing_Class();');
        return $v['classes'] === ['WP_Missing_Class'] ? true : json_encode($v);
    },
    'a method named like a runtime function is not a redeclaration' => static function () use ($verdict): bool|string {
        $v = $verdict('method', "class Acme { public function wp_mail(\$to) { return true; } public static function get_option() {} }\ninterface Q { public function wp_present_fn(); }\n\$o = new class { function wp_mail() {} };\n\$s = \"{\$x} \${y}\"; trait T { function wp_present_fn() {} }");
        return $v['redeclares'] === [] ? true : json_encode($v);
    },
    'a global declaration after a class body is still global' => static function () use ($verdict): bool|string {
        $v = $verdict('after-class', "class Acme { public function go() { if (1) { return; } } }\nfunction wp_mail(\$to) {}");
        return $v['redeclares'] === ['wp_mail'] ? true : json_encode($v);
    },
    'a "use function" import is not a declaration' => static function () use ($verdict): bool|string {
        $v = $verdict('use-function', "namespace Acme;\nuse function wp_mail;\nwp_present_fn();");
        return $v['redeclares'] === [] ? true : json_encode($v);
    },
    'the gap knows what it defines and what it lacks' => static function () use ($gap): bool {
        return $gap->defines('wp_mail') && $gap->defines('WP_MAIL') && !$gap->defines('wp_missing_fn') && !$gap->defines('acme_helper') && $gap->lacksFunction('wp_missing_fn');
    },
];
