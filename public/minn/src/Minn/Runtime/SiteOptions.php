<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Serialized;

/**
 * The network's options, as a single site keeps them: options stored not
 * to autoload, with the network layer's own hooks around them, told the
 * network asked for (the current one, 1, when none is).
 */
final class SiteOptions
{
    /**
     * A network option (get_network_option, get_site_option):
     * pre_site_option_{name} and pre_site_option may answer first,
     * default_site_option_{name} sets its default, site_option_{name} has
     * the last word.
     */
    public static function get(mixed $option, mixed $default = false, mixed $networkId = null): mixed
    {
        $option = (string) $option;
        $network = self::network($networkId);
        $pre = Runtime::hooks()->filter("pre_site_option_{$option}", [false, $option, $network, $default]);
        $pre = Runtime::hooks()->filter('pre_site_option', [$pre, $option, $network, $default]);
        if ($pre !== false) {
            return $pre;
        }
        $default = Runtime::hooks()->filter("default_site_option_{$option}", [$default, $option, $network]);
        return Runtime::hooks()->filter("site_option_{$option}", [Runtime::options()->filtered($option, $default), $option, $network]);
    }

    /** A new network option (add_network_option, add_site_option), stored not to autoload. */
    public static function add(mixed $option, mixed $value, mixed $networkId = null): bool
    {
        $option = (string) $option;
        $network = self::network($networkId);
        $value = Runtime::hooks()->filter("pre_add_site_option_{$option}", [$value, $option, $network]);
        if (!OptionWrites::add($option, $value, false)) {
            return false;
        }
        Runtime::hooks()->action("add_site_option_{$option}", [$option, $value, $network]);
        Runtime::hooks()->action('add_site_option', [$option, $value, $network]);
        return true;
    }

    /** A network option changed (update_network_option, update_site_option); one not there yet is added. */
    public static function update(mixed $option, mixed $value, mixed $networkId = null): bool
    {
        $option = (string) $option;
        $network = self::network($networkId);
        $old = self::get($option, false, $networkId);
        $value = Runtime::hooks()->filter("pre_update_site_option_{$option}", [$value, $old, $option, $network]);
        if ($value === $old || Serialized::maybeEncode($value) === Serialized::maybeEncode($old)) {
            return false;
        }
        if ($old === false) {
            return self::add($option, $value, $networkId);
        }
        if (!OptionWrites::update($option, $value, false)) {
            return false;
        }
        Runtime::hooks()->action("update_site_option_{$option}", [$option, $value, $old, $network]);
        Runtime::hooks()->action('update_site_option', [$option, $value, $old, $network]);
        return true;
    }

    /** A network option removed (delete_network_option, delete_site_option). */
    public static function delete(mixed $option, mixed $networkId = null): bool
    {
        $option = (string) $option;
        $network = self::network($networkId);
        Runtime::hooks()->action("pre_delete_site_option_{$option}", [$option, $network]);
        if (!OptionWrites::delete($option)) {
            return false;
        }
        Runtime::hooks()->action("delete_site_option_{$option}", [$option, $network]);
        Runtime::hooks()->action('delete_site_option', [$option, $network]);
        return true;
    }

    /** The network asked for, else the current one: Minn is one network, 1. */
    private static function network(mixed $networkId): int
    {
        return $networkId ? (int) $networkId : 1;
    }
}
