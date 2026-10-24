<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Where the pluggable functions are declared. The reference declares them in
 * wp-includes/pluggable.php, and code that asks where one lives (a mail
 * plugin reading ReflectionFunction('wp_mail')->getFileName() to decide
 * whether it may take over mail, as SandyWP's mail helper and FluentSMTP do)
 * stands aside when it finds them anywhere else. So an install lays the
 * engine's own wp-api/pluggable.php at that path, whole, behind a header
 * that hands over to the engine's copy when the laid one is not the running
 * engine's (an engine updated past it, a checkout edited since): a laid copy
 * never declares anything the engine running would not (Austin, 2026-10-24).
 */
final class Pluggables
{
    /** Where the reference declares the pluggable functions, from the webroot. */
    public const LAID = 'wp-includes/pluggable.php';

    private const HEADER = <<<'PHP'
        <?php
        // Minn Engine: the pluggable functions, declared where the reference declares them, so code that asks
        // where one lives finds this file. Laid by the engine from minn/wp-api/pluggable.php; a copy that is not
        // the running engine's leaves the declaring to the engine's own.
        if (defined('MINN_ENGINE_DIR') && !\Minn\Runtime\Pluggables::current(__FILE__, MINN_ENGINE_DIR)) {
            require_once MINN_ENGINE_DIR . '/wp-api/pluggable.php';
            return;
        }

        PHP;

    /** @var array<string, bool> whether each laid file is the running engine's, once a request */
    private static array $current = [];

    /** The file an install lays at wp-includes/pluggable.php for an engine: the header, then the engine's own file after its opening tag. */
    public static function laid(string $engineDir): string
    {
        $own = (string) file_get_contents("{$engineDir}/wp-api/pluggable.php");
        return self::HEADER . (str_starts_with($own, '<?php') ? ltrim(substr($own, 5), "\n") : $own);
    }

    /** Whether the file at $file is the copy this engine lays. */
    public static function current(string $file, string $engineDir): bool
    {
        return self::$current[$file] ??= is_file($file) && file_get_contents($file) === self::laid($engineDir);
    }

    /** The file that declares the pluggable functions for this webroot: the laid copy when it is the engine's, else the engine's own. */
    public static function file(string $root, string $engineDir): string
    {
        $laid = rtrim($root, '/') . '/' . self::LAID;
        return self::current($laid, $engineDir) ? $laid : "{$engineDir}/wp-api/pluggable.php";
    }
}
