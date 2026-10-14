<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The active theme's settings (theme_mods_{stylesheet}) as WordPress keeps
 * them: read through theme_mod_{name}, a default with %s naming the
 * theme's folders, written through pre_set_theme_mod_{name}, and removed one
 * by one or all at once.
 */
final class ThemeMods
{
    /**
     * Every setting of the active theme (get_theme_mods).
     *
     * @return array<array-key, mixed>
     */
    public static function all(): array
    {
        $mods = Runtime::options()->filtered('theme_mods_' . Themes::stylesheet());
        return is_array($mods) ? $mods : [];
    }

    /** One setting (get_theme_mod), else the default (a %s in it names the parent's and the theme's folder addresses). */
    public static function get(mixed $name, mixed $default = false): mixed
    {
        $mods = self::all();
        if (isset($mods[$name])) {
            return Runtime::hooks()->filter("theme_mod_{$name}", [$mods[$name]]);
        }
        // Only a default with a %s (or %1$s) in it names the theme's folders; any other % is left alone.
        if (is_string($default) && preg_match('#(?<!%)%(?:\d+\$?)?s#', $default) === 1) {
            $default = sprintf($default, Themes::templateDirectoryUri(), Themes::stylesheetDirectoryUri());
        }
        return Runtime::hooks()->filter("theme_mod_{$name}", [$default]);
    }

    /** A setting written (set_theme_mod), through pre_set_theme_mod_{name}. */
    public static function set(mixed $name, mixed $value): bool
    {
        $mods = self::all();
        $old = $mods[$name] ?? false;
        $mods[$name] = Runtime::hooks()->filter("pre_set_theme_mod_{$name}", [$value, $old]);
        return OptionWrites::update('theme_mods_' . Themes::stylesheet(), $mods);
    }

    /** A setting removed (remove_theme_mod); the last one takes the option with it. */
    public static function remove(mixed $name): void
    {
        $mods = self::all();
        if (!isset($mods[$name])) {
            return;
        }
        unset($mods[$name]);
        if ($mods === []) {
            self::removeAll();
            return;
        }
        OptionWrites::update('theme_mods_' . Themes::stylesheet(), $mods);
    }

    /** Every setting removed (remove_theme_mods). */
    public static function removeAll(): void
    {
        OptionWrites::delete('theme_mods_' . Themes::stylesheet());
    }
}
