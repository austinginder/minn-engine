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
