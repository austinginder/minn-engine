<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\MediaType;

/**
 * PHP's error settings from the debug constants, as the reference's boot
 * sets them (wp_debug_mode(); contracts/fixtures/debug-mode.json holds the
 * oracle's answer for each combination, and the unit suite replays it).
 * WP_DEBUG reports every level, or only errors and warnings. With it on,
 * WP_DEBUG_DISPLAY turns display on or off (null leaves PHP's own setting)
 * and WP_DEBUG_LOG sends the log to a file. A request that answers a
 * machine never displays errors.
 */
final class DebugMode
{
    /** What PHP reports while WP_DEBUG is off: errors and warnings, no notices, strict or deprecation messages. */
    public const QUIET = E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR;

    /** The debug constants, each with the value it has when wp-config.php leaves it out. */
    private const DEFAULTS = ['WP_DEBUG' => false, 'WP_DEBUG_DISPLAY' => true, 'WP_DEBUG_LOG' => false];

    /** The request constants answersMachine() reads. */
    private const REQUESTS = ['XMLRPC_REQUEST', 'REST_REQUEST', 'MS_FILES_REQUEST', 'WP_INSTALLING', 'DOING_AJAX'];

    /**
     * The settings the debug constants ask for: the error level, and the ini
     * values to set (none for what they leave alone).
     *
     * @param array<string, mixed> $constants the debug constants defined, by name; a missing one reads as its default
     * @return array{level: int, ini: array<string, string>}
     */
    public static function settings(array $constants, string $contentDir): array
    {
        $constants += self::DEFAULTS;
        if (!$constants['WP_DEBUG']) {
            return ['level' => self::QUIET, 'ini' => []];
        }
        $ini = [];
        if ($constants['WP_DEBUG_DISPLAY']) {
            $ini['display_errors'] = '1';
        } elseif ($constants['WP_DEBUG_DISPLAY'] !== null) {
            $ini['display_errors'] = '0';
        }
        $log = self::logPath($constants['WP_DEBUG_LOG'], $contentDir);
        if ($log !== null) {
            $ini['log_errors'] = '1';
            $ini['error_log'] = $log;
        }
        return ['level' => E_ALL, 'ini' => $ini];
    }

    /**
     * Where WP_DEBUG_LOG sends the log. True, 1 or "true" in any case mean
     * debug.log in the content folder; any other string but "" and "0" is
     * the path itself, "false" included; anything else keeps no log.
     */
    public static function logPath(mixed $setting, string $contentDir): ?string
    {
        if (is_scalar($setting) && in_array(strtolower((string) $setting), ['true', '1'], true)) {
            return rtrim($contentDir, '/') . '/debug.log';
        }
        return is_string($setting) && $setting !== '' && $setting !== '0' ? $setting : null;
    }

    /**
     * Whether what the boot knows marks a request that answers a machine,
     * whose errors must never print into the answer: XML-RPC, REST or
     * ms-files declared at all, an install or ajax declared true, or a
     * request that asks for or sends JSON (a JSONP or XML one is not). The
     * facade's wp_debug_mode() asks the same through wp_installing(),
     * wp_doing_ajax() and wp_is_json_request(), filters included.
     *
     * @param array<string, mixed> $constants the request constants defined, by name
     * @param array<string, mixed> $server the request's server variables
     */
    public static function answersMachine(array $constants, array $server): bool
    {
        foreach (['XMLRPC_REQUEST', 'REST_REQUEST', 'MS_FILES_REQUEST'] as $name) {
            if (array_key_exists($name, $constants)) {
                return true;
            }
        }
        if (!empty($constants['WP_INSTALLING']) || !empty($constants['DOING_AJAX'])) {
            return true;
        }
        return MediaType::json((string) ($server['HTTP_ACCEPT'] ?? '')) || MediaType::json((string) ($server['CONTENT_TYPE'] ?? ''));
    }

    /**
     * The debug and request constants defined right now, by name.
     *
     * @return array<string, mixed>
     */
    public static function defined(): array
    {
        $out = [];
        foreach ([...array_keys(self::DEFAULTS), ...self::REQUESTS] as $name) {
            if (defined($name)) {
                $out[$name] = constant($name);
            }
        }
        return $out;
    }

    /**
     * Sets PHP's error level and ini values to what settings() asked for.
     *
     * @param array{level: int, ini: array<string, string>} $settings
     */
    public static function apply(array $settings): void
    {
        error_reporting($settings['level']);
        foreach ($settings['ini'] as $name => $value) {
            ini_set($name, $value);
        }
    }
}
