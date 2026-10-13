<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Values kept for a while, as WordPress keeps them in options: the site's
 * transients (_transient_{name}, with _transient_timeout_{name} when they
 * expire) and the network's (_site_transient_{name}), each read, set and
 * removed through the filters and actions plugins hook. An expired one reads
 * as false and is removed as it is read.
 */
final class Transients
{
    /** A transient's value, or false (get_transient). */
    public static function get(mixed $transient): mixed
    {
        $transient = (string) $transient;
        $pre = Runtime::hooks()->filter("pre_transient_{$transient}", [false, $transient]);
        if ($pre !== false) {
            return $pre;
        }
        // The timeout and the value are read together, one query rather than two.
        Runtime::options()->prime(["_transient_timeout_{$transient}", "_transient_{$transient}"]);
        $timeout = Runtime::options()->filtered("_transient_timeout_{$transient}");
        if ($timeout !== false && (int) $timeout < time()) {
            OptionWrites::delete("_transient_{$transient}");
            OptionWrites::delete("_transient_timeout_{$transient}");
            return false;
        }
        $value = Runtime::options()->filtered("_transient_{$transient}");
        return Runtime::hooks()->filter("transient_{$transient}", [$value, $transient]);
    }

    /**
     * A transient set (set_transient): one that expires is stored not to
     * autoload, its timeout beside it; setting an expiry on one that had
     * none stores it again that way.
     */
    public static function set(mixed $transient, mixed $value, mixed $expiration = 0): bool
    {
        $transient = (string) $transient;
        $expiration = (int) $expiration;
        $value = Runtime::hooks()->filter("pre_set_transient_{$transient}", [$value, $expiration, $transient]);
        $expiration = (int) Runtime::hooks()->filter("expiration_of_transient_{$transient}", [$expiration, $value, $transient]);
        $name = "_transient_{$transient}";
        $timeoutName = "_transient_timeout_{$transient}";
        if (Runtime::options()->filtered($name) === false) {
            $autoload = 'on';
            if ($expiration !== 0) {
                $autoload = 'off';
                OptionWrites::add($timeoutName, time() + $expiration, 'off');
            }
            $result = OptionWrites::add($name, $value, $autoload);
        } else {
            $update = true;
            if ($expiration !== 0) {
                if (Runtime::options()->filtered($timeoutName) === false) {
                    OptionWrites::delete($name);
                    OptionWrites::add($timeoutName, time() + $expiration, 'off');
                    $result = OptionWrites::add($name, $value, 'off');
                    $update = false;
                } else {
                    OptionWrites::update($timeoutName, time() + $expiration);
                }
            }
            if ($update) {
                $result = OptionWrites::update($name, $value);
            }
        }
        if ($result) {
            Runtime::hooks()->action("set_transient_{$transient}", [$value, $expiration, $transient]);
            Runtime::hooks()->action('set_transient', [$transient, $value, $expiration]);
            Runtime::hooks()->actionDeprecated('setted_transient', [$transient, $value, $expiration], '6.8.0', 'set_transient');
        }
        return $result;
    }

    /** A transient removed (delete_transient), its timeout with it. */
    public static function delete(mixed $transient): bool
    {
        $transient = (string) $transient;
        Runtime::hooks()->action("delete_transient_{$transient}", [$transient]);
        $result = OptionWrites::delete("_transient_{$transient}");
        if ($result) {
            OptionWrites::delete("_transient_timeout_{$transient}");
            Runtime::hooks()->action('deleted_transient', [$transient]);
        }
        return $result;
    }

    /** A network transient's value, or false (get_site_transient); the update offers never expire by their timeout. */
    public static function getSite(mixed $transient): mixed
    {
        $transient = (string) $transient;
        $pre = Runtime::hooks()->filter("pre_site_transient_{$transient}", [false, $transient]);
        if ($pre !== false) {
            return $pre;
        }
        $name = "_site_transient_{$transient}";
        $expired = false;
        if (!in_array($transient, ['update_core', 'update_plugins', 'update_themes'], true)) {
            // On a single site the timeout and the value are options, read together: one query rather than two.
            Runtime::options()->prime(["_site_transient_timeout_{$transient}", $name]);
            $timeout = SiteOptions::get("_site_transient_timeout_{$transient}");
            if ($timeout !== false && (int) $timeout < time()) {
                SiteOptions::delete($name);
                SiteOptions::delete("_site_transient_timeout_{$transient}");
                $expired = true;
            }
        }
        return Runtime::hooks()->filter("site_transient_{$transient}", [$expired ? false : SiteOptions::get($name), $transient]);
    }

    /** A network transient set (set_site_transient), its timeout beside it when it expires. */
    public static function setSite(mixed $transient, mixed $value, mixed $expiration = 0): bool
    {
        $transient = (string) $transient;
        $value = Runtime::hooks()->filter("pre_set_site_transient_{$transient}", [$value, $transient]);
        $expiration = (int) Runtime::hooks()->filter("expiration_of_site_transient_{$transient}", [(int) $expiration, $value, $transient]);
        $name = "_site_transient_{$transient}";
        $timeout = "_site_transient_timeout_{$transient}";
        if (SiteOptions::get($name) === false) {
            if ($expiration) {
                SiteOptions::add($timeout, time() + $expiration);
            }
            $result = SiteOptions::add($name, $value);
        } else {
            if ($expiration) {
                SiteOptions::update($timeout, time() + $expiration);
            }
            $result = SiteOptions::update($name, $value);
        }
        if ($result) {
            Runtime::hooks()->action("set_site_transient_{$transient}", [$value, $expiration, $transient]);
            Runtime::hooks()->action('set_site_transient', [$transient, $value, $expiration]);
            Runtime::hooks()->actionDeprecated('setted_site_transient', [$transient, $value, $expiration], '6.8.0', 'set_site_transient');
        }
        return $result;
    }

    /** A network transient removed (delete_site_transient), its timeout with it. */
    public static function deleteSite(mixed $transient): bool
    {
        $transient = (string) $transient;
        Runtime::hooks()->action("delete_site_transient_{$transient}", [$transient]);
        $result = SiteOptions::delete("_site_transient_{$transient}");
        if ($result) {
            SiteOptions::delete("_site_transient_timeout_{$transient}");
            Runtime::hooks()->action('deleted_site_transient', [$transient]);
        }
        return $result;
    }
}
