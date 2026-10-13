<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\Kses;

/**
 * The debugging notices plugins and the engine raise: a deprecated function,
 * argument, hook, file, class or constructor, and a function called
 * incorrectly. Each is heard by its action first; then, only under WP_DEBUG
 * and while its own filter still says so, it is raised as a PHP notice worded
 * as the reference words it. Every raised message passes
 * wp_trigger_error_always_run, wp_trigger_error_trigger_error and
 * wp_trigger_error_run, and keeps only links (http and https), line breaks,
 * code and emphasis. Values reach the hooks exactly as they were given.
 */
final class Notices
{
    /** The markup a raised notice keeps (each element with its attributes), and the protocols its links may use. */
    private const MARKUP = ['a' => ['href'], 'br' => [], 'code' => [], 'em' => [], 'strong' => []];
    private const PROTOCOLS = ['http', 'https'];

    /**
     * Each kind of deprecation: the action that hears it, the filter that may
     * silence it, its wording with an alternative and without one, and
     * whether a closing note follows the wording (and is heard after the
     * version).
     */
    private const DEPRECATIONS = [
        'function' => ['deprecated_function_run', 'deprecated_function_trigger_error', 'Function %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', 'Function %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', false],
        'argument' => ['deprecated_argument_run', 'deprecated_argument_trigger_error', 'Function %1$s was called with an argument that is <strong>deprecated</strong> since version %2$s! %3$s', 'Function %1$s was called with an argument that is <strong>deprecated</strong> since version %2$s with no alternative available.', false],
        'hook' => ['deprecated_hook_run', 'deprecated_hook_trigger_error', 'Hook %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', 'Hook %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', true],
        'file' => ['deprecated_file_included', 'deprecated_file_trigger_error', 'File %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', 'File %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', true],
        'class' => ['deprecated_class_run', 'deprecated_class_trigger_error', 'Class %1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', 'Class %1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', false],
    ];

    /** Whether a notice is raised: only under WP_DEBUG, and only while its filter (handed true and anything else given) still says so. */
    public static function wanted(string $filter, mixed ...$args): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && Runtime::hooks()->filter($filter, [true, ...$args]);
    }

    /**
     * A deprecation of one kind (function, argument, hook, file or class): its
     * name, the version that deprecated it, the alternative (for an argument,
     * the message), and for a hook or a file a note added after the wording.
     */
    public static function deprecated(string $kind, mixed $name, mixed $version, mixed $alternative = '', mixed $note = ''): void
    {
        [$action, $filter, $withAlternative, $withNone, $noted] = self::DEPRECATIONS[$kind];
        Runtime::hooks()->action($action, $noted ? [$name, $alternative, $version, $note] : [$name, $alternative, $version]);
        if (!self::wanted($filter)) {
            return;
        }
        $wording = $alternative
            ? sprintf(Gettext::text($withAlternative), $name, $version, $alternative)
            : sprintf(Gettext::text($withNone), $name, $version);
        self::notify($wording . ($noted && $note ? ' ' . $note : ''), E_USER_DEPRECATED);
    }

    /** A class still constructed through its old PHP 4 style constructor, in its parent class when one is named. */
    public static function constructor(mixed $class, mixed $version, mixed $parent = ''): void
    {
        Runtime::hooks()->action('deprecated_constructor_run', [$class, $version, $parent]);
        if (!self::wanted('deprecated_constructor_trigger_error')) {
            return;
        }
        $wording = $parent
            ? sprintf(Gettext::text('The called constructor method for %1$s class in %2$s is <strong>deprecated</strong> since version %3$s! Use %4$s instead.'), $class, $parent, $version, '<code>__construct()</code>')
            : sprintf(Gettext::text('The called constructor method for %1$s class is <strong>deprecated</strong> since version %2$s! Use %3$s instead.'), $class, $version, '<code>__construct()</code>');
        self::notify($wording, E_USER_DEPRECATED);
    }

    /** A function called incorrectly, with why, and the version that added the message. */
    public static function doingItWrong(mixed $function, mixed $message, mixed $version): void
    {
        Runtime::hooks()->action('doing_it_wrong_run', [$function, $message, $version]);
        if (!self::wanted('doing_it_wrong_trigger_error', $function, $message, $version)) {
            return;
        }
        $help = sprintf(Gettext::text('Please see <a href="%s">Debugging in WordPress</a> for more information.'), Gettext::text('https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/'));
        $since = $version ? sprintf(Gettext::text('(This message was added in version %s.)'), $version) : '';
        self::notify(sprintf(Gettext::text('Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s'), $function, $message . ' ' . $help, $since));
    }

    /**
     * A notice's message as it would be raised, or null when it is not:
     * wp_trigger_error_always_run hears every one; under WP_DEBUG, and unless
     * wp_trigger_error_trigger_error silences it, wp_trigger_error_run hears it
     * and it is cleaned to the markup a notice keeps.
     */
    public static function message(mixed $function, mixed $message, mixed $level): ?string
    {
        $hooks = Runtime::hooks();
        $hooks->action('wp_trigger_error_always_run', [$function, $message, $level]);
        if (!defined('WP_DEBUG') || !WP_DEBUG || !$hooks->filter('wp_trigger_error_trigger_error', [true, $function, $message, $level])) {
            return null;
        }
        $hooks->action('wp_trigger_error_run', [$function, $message, $level]);
        $allowed = array_map(static fn (array $attributes): array => array_fill_keys($attributes, true), self::MARKUP);
        return Kses::allowing(((string) $function !== '' ? "{$function}(): " : '') . $message, $allowed, self::PROTOCOLS);
    }

    /** One of the engine's own notices, raised when it is wanted. */
    private static function notify(string $message, int $level = E_USER_NOTICE): void
    {
        $raised = self::message('', $message, $level);
        if ($raised !== null) {
            trigger_error($raised, $level);
        }
    }
}
