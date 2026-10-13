<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\Escape;
use Minn\Support\Serialized;

/**
 * Options written the way add_option(), update_option() and delete_option()
 * write them: the name trimmed, alloptions and notoptions stopped, the
 * guarded ones refused, the value sanitized, and each change heard before
 * and after by the actions plugins hook.
 */
final class OptionWrites
{
    /**
     * A new option (add_option): refused when it already reads as anything
     * but its default; its autoload flag decided when not given.
     */
    public static function add(mixed $option, mixed $value = '', mixed $autoload = null): bool
    {
        $option = trim((string) $option);
        if ($option === '') {
            return false;
        }
        self::protect($option);
        if (Options::guarded($option)) {
            return false;
        }
        $value = OptionSanitizer::clean($option, $value);
        $options = Runtime::options();
        // An option is new while it reads as its default; a read that already found it unset needs no second look.
        if (!$options->knownMissing($option) && Runtime::hooks()->filter("default_option_{$option}", [false, $option, false]) !== $options->filtered($option)) {
            return false;
        }
        // Not given, the flag is decided (an option too big to autoload is stored auto-off, probe option-more).
        $flag = $autoload === 'auto' ? 'auto' : self::autoloadFlag($option, $value, Serialized::maybeEncode($value), $autoload);
        Runtime::hooks()->action('add_option', [$option, $value]);
        if (!$options->add($option, $value, $flag)) {
            return false;
        }
        Runtime::hooks()->action("add_option_{$option}", [$option, $value]);
        Runtime::hooks()->action('added_option', [$option, $value]);
        return true;
    }

    /**
     * An option changed (update_option): the new value through its filters;
     * an unchanged one writes nothing and tells nobody; one still at its
     * default is added.
     */
    public static function update(mixed $option, mixed $value, mixed $autoload = null): bool
    {
        $option = trim((string) $option);
        if ($option === '') {
            return false;
        }
        self::protect($option);
        if (Options::guarded($option)) {
            return false;
        }
        $value = OptionSanitizer::clean($option, $value);
        $old = Runtime::options()->filtered($option);
        $value = Runtime::hooks()->filter("pre_update_option_{$option}", [$value, $old, $option]);
        $value = Runtime::hooks()->filter('pre_update_option', [$value, $option, $old]);
        if ($value === $old || Serialized::maybeEncode($value) === Serialized::maybeEncode($old)) {
            return false;
        }
        if (Runtime::hooks()->filter("default_option_{$option}", [false, $option, false]) === $old) {
            return self::add($option, $value, $autoload);
        }
        Runtime::hooks()->action('update_option', [$option, $old, $value]);
        if (!Runtime::options()->update($option, $value)) {
            return false;
        }
        Runtime::hooks()->action("update_option_{$option}", [$old, $value, $option]);
        Runtime::hooks()->action('updated_option', [$option, $old, $value]);
        return true;
    }

    /** An option removed (delete_option): false when it was not there. */
    public static function delete(mixed $option): bool
    {
        $option = trim((string) $option);
        if ($option === '') {
            return false;
        }
        self::protect($option);
        if (Options::guarded($option) || !Runtime::options()->exists($option)) {
            return false;
        }
        Runtime::hooks()->action('delete_option', [$option]);
        if (!Runtime::options()->delete($option)) {
            return false;
        }
        Runtime::hooks()->action("delete_option_{$option}", [$option]);
        Runtime::hooks()->action('deleted_option', [$option]);
        return true;
    }

    /** Stops the request at alloptions or notoptions, which nothing may write (wp_protect_special_option). */
    public static function protect(mixed $option): void
    {
        if ($option === 'alloptions' || $option === 'notoptions') {
            Handoff::stop(sprintf(Gettext::text('%s is a protected WP option and may not be modified'), Escape::html($option)));
        }
    }

    /**
     * The autoload flag an option is stored with (wp_determine_option_autoload_value):
     * "on" for on, yes or true, "off" for off, no or false; anything else is
     * decided by wp_default_autoload_value (auto-on, auto-off) or left "auto".
     */
    public static function autoloadFlag(mixed $option, mixed $value, mixed $serialized, mixed $autoload): mixed
    {
        if ($autoload === 'on' || $autoload === 'off') {
            return $autoload;
        }
        if ($autoload === true || $autoload === 'yes') {
            return 'on';
        }
        if ($autoload === false || $autoload === 'no') {
            return 'off';
        }
        $decided = Runtime::hooks()->filter('wp_default_autoload_value', [null, $option, $value, $serialized]);
        return is_bool($decided) ? ($decided ? 'auto-on' : 'auto-off') : 'auto';
    }

    /**
     * Options' autoload flags set (wp_set_option_autoload_values): per option,
     * whether its flag changed. Only false, no, off, auto-off and 0 mean off.
     *
     * @return array<array-key, bool>
     */
    public static function setAutoload(mixed $options): array
    {
        $results = [];
        foreach ((array) $options as $name => $autoload) {
            $on = !in_array($autoload, [false, 'no', 'off', 'auto-off', 0], true);
            $results[$name] = $on ? Runtime::options()->markAutoload((string) $name) : Runtime::options()->unmarkAutoload((string) $name);
        }
        return $results;
    }
}
