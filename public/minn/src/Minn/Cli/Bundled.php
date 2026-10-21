<?php

declare(strict_types=1);

namespace Minn\Cli;

use ReflectionMethod;
use Throwable;
use WP_CLI;
use WP_CLI\DocParser;

/**
 * WP-CLI's own bundled command for a verb the engine answers. Its docs give
 * an engine verb the same synopsis (the arguments, defaults and allowed
 * values the running WP-CLI accepts, which change between releases), and a
 * call the engine does not answer itself is handed back to it, run against
 * the engine's WordPress runtime as any bundled command is.
 */
final class Bundled
{
    /**
     * The bundled method's description and synopsis, as WP-CLI reads them
     * from its doc comment, for add_command's arguments; empty when this
     * WP-CLI has no such command.
     *
     * @return array<string, string>
     */
    public static function docs(string $class, string $method): array
    {
        try {
            if (!class_exists($class)) {
                return [];
            }
            $comment = (new ReflectionMethod($class, $method))->getDocComment();
        } catch (Throwable) {
            return [];
        }
        if (!is_string($comment) || !class_exists(DocParser::class)) {
            return [];
        }
        $parser = new DocParser($comment);
        $longdesc = (string) $parser->get_longdesc();
        $synopsis = (string) $parser->get_synopsis();
        if ($synopsis === '' && $longdesc !== '') {
            // Each "<arg>" line followed by a ": description" line, as WP-CLI extracts a synopsis.
            preg_match_all('/(.+?)[\r\n]+:/', $longdesc, $matches);
            $synopsis = implode(' ', $matches[1]);
        }
        if ($synopsis === '') {
            return [];
        }
        return ['shortdesc' => (string) $parser->get_shortdesc(), 'longdesc' => $longdesc, 'synopsis' => $synopsis];
    }

    /**
     * Whether WP-CLI can load this site, as it checks before loading one: the
     * core version file readable under ABSPATH and a wp-config.php found. A
     * Minn site carries both (its placeholder files); a bare webroot does not.
     */
    public static function loadable(): bool
    {
        return defined('ABSPATH') && is_readable(ABSPATH . 'wp-includes/version.php') && function_exists('WP_CLI\\Utils\\locate_wp_config') && \WP_CLI\Utils\locate_wp_config() !== false;
    }

    /**
     * Runs the bundled command for a call: WP-CLI loads the site (the engine
     * stands in for WordPress, plugins loaded), then the bundled method
     * answers with the arguments the shared synopsis already validated.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public static function run(string $class, string $method, array $args, array $assocArgs): void
    {
        if (!class_exists($class)) {
            WP_CLI::error("WP-CLI's {$class} is not available.");
        }
        WP_CLI::get_runner()->load_wordpress();
        $command = new $class();
        $command->{$method}($args, $assocArgs);
    }
}
