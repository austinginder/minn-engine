<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\ScriptTag;

/**
 * The scripts and styles a request queues, as WordPress's asset functions
 * queue them: each kind's registry kept for the request, a handle queued
 * (with the notice for one queued before init), and a script's element,
 * from a file or inline, through the attribute filters.
 */
final class AssetQueue
{
    /**
     * The registry the page's functions work on: the one held by the object
     * in the global a plugin may replace ($wp_scripts, $wp_styles), as the
     * reference's functions follow that global. Elementor's editor starts
     * from a new registry of each kind, leaving every other plugin's assets
     * out; from then on that one is the page's, the engine's own printing
     * included. Without such an object, the request's own.
     */
    public static function follow(string $kind, mixed $global): Assets
    {
        $assets = self::assets($kind);
        $own = is_object($global) ? (fn (): mixed => $this->assets ?? null)->call($global) : null;
        if (!$own instanceof Assets || $own->kind() !== $kind || $own === $assets) {
            return $assets;
        }
        Runtime::current()->set('assets_' . $kind, $own);
        return $own;
    }

    /** The request's registry of one kind of asset, script or style, made the first time it is asked for. */
    public static function assets(string $kind): Assets
    {
        $runtime = Runtime::current();
        $assets = $runtime->get('assets_' . $kind);
        if (!$assets instanceof Assets) {
            $assets = new Assets($kind);
            $runtime->set('assets_' . $kind, $assets);
        }
        return $assets;
    }

    /**
     * The notice (doing it wrong, 3.3.0) for an asset function run before
     * init and before any of the enqueue actions
     * (_wp_scripts_maybe_doing_it_wrong), naming the handle when there is one.
     */
    public static function warnTooEarly(mixed $function, mixed $handle = ''): void
    {
        $hooks = Runtime::hooks();
        if ($hooks->actionsDone('init') || $hooks->actionsDone('wp_enqueue_scripts') || $hooks->actionsDone('admin_enqueue_scripts') || $hooks->actionsDone('login_enqueue_scripts')) {
            return;
        }
        $message = sprintf(Gettext::text('Scripts and styles should not be registered or enqueued until the %1$s, %2$s, or %3$s hooks.'), '<code>wp_enqueue_scripts</code>', '<code>admin_enqueue_scripts</code>', '<code>login_enqueue_scripts</code>');
        if ($handle) {
            $message .= ' ' . sprintf(Gettext::text('This notice was triggered by the %s handle.'), '<code>' . $handle . '</code>');
        }
        Notices::doingItWrong($function, $message, '3.3.0');
    }

    /**
     * The scripts lined up for printing, as print_scripts_array leaves them.
     * WordPress's own scripts among them that the engine has no file for are
     * noted for the Minn Legacy Pack (LegacyPack::noteScripts).
     *
     * @param list<string> $toDo
     */
    public static function linedUp(Assets $scripts, array $toDo): mixed
    {
        $toDo = Runtime::hooks()->filter('print_scripts_array', [$toDo]);
        if (is_array($toDo)) {
            LegacyPack::noteScripts($scripts, array_values($toDo));
        }
        return $toDo;
    }

    /** A registered script queued (wp_enqueue_script with a handle alone). */
    public static function enqueueScript(string $handle): void
    {
        self::warnTooEarly('wp_enqueue_script', $handle);
        self::assets('script')->enqueue($handle);
    }

    /** A registered style queued (wp_enqueue_style with a handle alone). */
    public static function enqueueStyle(string $handle): void
    {
        self::warnTooEarly('wp_enqueue_style', $handle);
        self::assets('style')->enqueue($handle);
    }

    /** A script file's element (wp_get_script_tag), its attributes through wp_script_attributes first. */
    public static function tag(mixed $attributes): string
    {
        return ScriptTag::element((array) Runtime::hooks()->filter('wp_script_attributes', [$attributes]));
    }

    /** Inline code's element (wp_get_inline_script_tag): the code trimmed between newlines, wp_inline_script_attributes asked. */
    public static function inlineTag(mixed $data, mixed $attributes = []): string
    {
        $data = "\n" . trim((string) $data, "\n\r ") . "\n";
        $attributes = Runtime::hooks()->filter('wp_inline_script_attributes', [$attributes, $data]);
        return ScriptTag::inline($data, (array) $attributes);
    }
}
