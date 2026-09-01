<?php

declare(strict_types=1);

namespace Minn;

/** PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php. */
final class Autoloader
{
    /** Registers the PSR-4 loader for the Minn namespace. */
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'Minn\\')) {
                return;
            }
            $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
