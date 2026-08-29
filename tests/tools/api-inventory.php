<?php
/**
 * Interface inventory of the reference runtime, by reflection.
 *
 * Run inside wp-reference: `wp eval-file ../tests/tools/api-inventory.php`.
 * Records only interface facts (names, signatures, defaults, types, which
 * file declares them, constants) into contracts/api/*.json. No bodies, no
 * docblocks: the inventory is the header file the runtime is written against.
 */

$root = ABSPATH;
// The admin half only loads on admin requests, and many classes load on
// demand; pull them all in so their interface is inventoried. Files whose
// parent class is not loaded yet are retried until nothing new loads.
require_once ABSPATH . 'wp-admin/includes/admin.php';
$pending = array_merge(glob(ABSPATH . 'wp-admin/includes/class-*.php'), glob(ABSPATH . 'wp-includes/class-*.php'), glob(ABSPATH . 'wp-includes/*/class-*.php'));
$pending = array_filter($pending, static fn (string $f) => !str_contains($f, 'ms-') && !str_contains($f, 'deprecated'));
do {
    $before = count($pending);
    foreach ($pending as $i => $f) {
        try {
            require_once $f;
            unset($pending[$i]);
        } catch (Throwable $e) {
        }
    }
} while (count($pending) < $before && $pending !== []);
$out = dirname(__DIR__, 2) . '/contracts/api';

$relative = static function (?string $file) use ($root): ?string {
    if ($file === null || $file === false) {
        return null;
    }
    return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
};
$isCore = static fn (?string $file): bool => $file !== null && (str_starts_with($file, 'wp-includes/') || str_starts_with($file, 'wp-admin/') || in_array($file, ['wp-settings.php', 'wp-load.php'], true));

$typeOf = static fn (?ReflectionType $t): ?string => $t === null ? null : (string) $t;
$default = static function (ReflectionParameter $p): array {
    if (!$p->isDefaultValueAvailable()) {
        return [];
    }
    if ($p->isDefaultValueConstant()) {
        return ['default' => ['const' => $p->getDefaultValueConstantName()]];
    }
    $v = $p->getDefaultValue();
    if (is_object($v)) {
        return ['default' => ['object' => get_class($v)]];
    }
    return ['default' => $v];
};
$params = static function (ReflectionFunctionAbstract $f) use ($typeOf, $default): array {
    $rows = [];
    foreach ($f->getParameters() as $p) {
        $rows[] = array_filter([
            'name' => $p->getName(),
            'type' => $typeOf($p->getType()),
            'byRef' => $p->isPassedByReference() ?: null,
            'variadic' => $p->isVariadic() ?: null,
            'optional' => $p->isOptional() ?: null,
        ], static fn ($v) => $v !== null) + $default($p);
    }
    return $rows;
};

$functions = [];
foreach (get_defined_functions()['user'] as $name) {
    $f = new ReflectionFunction($name);
    $file = $relative($f->getFileName());
    if (!$isCore($file)) {
        continue;
    }
    $functions[$f->getName()] = array_filter([
        'file' => $file,
        'params' => $params($f),
        'returns' => $typeOf($f->getReturnType()),
        'byRefReturn' => $f->returnsReference() ?: null,
        'deprecated' => str_contains($file, 'deprecated') ?: null,
    ], static fn ($v) => $v !== null);
}
ksort($functions);

$classes = [];
foreach (array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $name) {
    $c = new ReflectionClass($name);
    $file = $relative($c->getFileName() ?: null);
    if (!$isCore($file)) {
        continue;
    }
    $methods = [];
    foreach ($c->getMethods() as $m) {
        if ($m->getDeclaringClass()->getName() !== $c->getName()) {
            continue;
        }
        $methods[$m->getName()] = array_filter([
            'visibility' => $m->isPublic() ? null : ($m->isProtected() ? 'protected' : 'private'),
            'static' => $m->isStatic() ?: null,
            'abstract' => $m->isAbstract() ?: null,
            'params' => $params($m),
            'returns' => $typeOf($m->getReturnType()),
        ], static fn ($v) => $v !== null);
    }
    $properties = [];
    foreach ($c->getProperties() as $p) {
        if ($p->getDeclaringClass()->getName() !== $c->getName()) {
            continue;
        }
        $row = array_filter([
            'visibility' => $p->isPublic() ? null : ($p->isProtected() ? 'protected' : 'private'),
            'static' => $p->isStatic() ?: null,
            'type' => $typeOf($p->getType()),
        ], static fn ($v) => $v !== null);
        if ($p->hasDefaultValue() && !is_object($p->getDefaultValue())) {
            $row['default'] = $p->getDefaultValue();
        }
        $properties[$p->getName()] = $row;
    }
    $constants = [];
    foreach ($c->getReflectionConstants() as $k) {
        if ($k->getDeclaringClass()->getName() === $c->getName() && !is_object($k->getValue())) {
            $constants[$k->getName()] = $k->getValue();
        }
    }
    $classes[$c->getName()] = array_filter([
        'file' => $file,
        'kind' => $c->isInterface() ? 'interface' : ($c->isTrait() ? 'trait' : ($c->isAbstract() ? 'abstract' : ($c->isFinal() ? 'final' : null))),
        'extends' => $c->getParentClass() ? $c->getParentClass()->getName() : null,
        'implements' => $c->getInterfaceNames() ?: null,
        'constants' => $constants ?: null,
        'properties' => $properties ?: null,
        'methods' => $methods ?: null,
    ], static fn ($v) => $v !== null);
}
ksort($classes);

$constants = [];
foreach (get_defined_constants(true)['user'] ?? [] as $k => $v) {
    if (is_scalar($v) || $v === null) {
        $constants[$k] = $v;
    }
}
foreach (['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT', 'ABSPATH', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR', 'WP_LANG_DIR', 'COOKIEHASH', 'WP_HOME', 'WP_SITEURL', 'DB_CHARSET', 'DB_COLLATE', 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'WP_ENVIRONMENT_TYPE'] as $secret) {
    if (array_key_exists($secret, $constants)) {
        $constants[$secret] = ['perSite' => true];
    }
}
foreach (array_keys($constants) as $k) {
    if (preg_match('/COOKIE$|COOKIEPATH|COOKIE_DOMAIN|^WP_CLI|^MINN|^CAPTAINCORE/', $k)) {
        $constants[$k] = ['perSite' => true];
    }
}
ksort($constants);

$globals = [];
foreach (array_keys($GLOBALS) as $g) {
    if (in_array($g, ['GLOBALS', '_GET', '_POST', '_COOKIE', '_FILES', '_ENV', '_REQUEST', '_SERVER', 'argv', 'argc', 'root', 'out', 'relative', 'isCore', 'typeOf', 'default', 'params', 'functions', 'classes', 'constants', 'globals', 'name', 'f', 'file', 'c', 'k', 'v', 'g', 'secret'], true)) {
        continue;
    }
    $v = $GLOBALS[$g];
    $globals[$g] = is_object($v) ? ['class' => get_class($v)] : ['type' => gettype($v)];
}
ksort($globals);

$write = static function (string $name, array $data) use ($out): void {
    file_put_contents("{$out}/{$name}.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDERR, sprintf("%-14s %6d\n", $name, count($data)));
};
$write('functions', $functions);
$write('classes', $classes);
$write('constants', $constants);
$write('globals', $globals);
$write('meta', ['version' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'captured' => gmdate('c')]);
