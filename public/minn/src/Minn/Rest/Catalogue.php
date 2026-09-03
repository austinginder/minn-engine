<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Route;
use Minn\Http\RouteRow;
use ReflectionClass;
use ReflectionMethod;

/**
 * The route table read from the classes alone: every #[Route] under
 * src/Minn, as rows, with no request, database, or site behind it. This is
 * what contracts/api/routes.json is written from, so an agent can read
 * which routes exist, who may call them, and what they take, without
 * booting the engine. The per-request router builds the same rows for the
 * handlers it actually registered, which is what the live index serves.
 */
final class Catalogue
{
    /**
     * Every route declared by a class under the source directory, in file
     * order, then declaration order.
     *
     * @return list<RouteRow>
     */
    public static function scan(string $sourceDir): array
    {
        $rows = [];
        foreach (self::classes($sourceDir) as $class) {
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $rows[] = RouteRow::of($attribute->newInstance(), $method);
                }
            }
        }
        return $rows;
    }

    /**
     * The rows as the JSON catalogue: a version line and one entry per route.
     *
     * @param list<RouteRow> $rows
     * @return array<string, mixed>
     */
    public static function document(array $rows, string $engineVersion): array
    {
        $bare = count(array_filter($rows, static fn (RouteRow $r): bool => $r->policy === null));
        return [
            'engine' => $engineVersion,
            'routes' => count($rows),
            'withPolicy' => count($rows) - $bare,
            'entries' => array_map(static fn (RouteRow $r): array => $r->toArray(), $rows),
        ];
    }

    /**
     * The fully qualified names of the classes under the directory, from
     * their paths (PSR-4: Minn\A\B is A/B.php), sorted.
     *
     * @return list<class-string>
     */
    private static function classes(string $sourceDir): array
    {
        $sourceDir = rtrim($sourceDir, '/');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        $classes = [];
        foreach ($files as $path) {
            $relative = substr($path, strlen($sourceDir) + 1, -4);
            $class = 'Minn\\' . str_replace('/', '\\', $relative);
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }
        return $classes;
    }
}
