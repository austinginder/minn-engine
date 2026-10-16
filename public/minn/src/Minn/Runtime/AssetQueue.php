<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
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

    /**
     * The scripts, styles and script modules queued now, to compare with once a block has rendered.
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    public static function snapshot(): array
    {
        return [self::assets('script')->queue(), self::assets('style')->queue(), Runtime::scriptModules()->queue()];
    }

    /**
     * Takes off the queues what joined them since the snapshot: what a block that rendered nothing enqueued, as
     * the reference takes it off.
     *
     * @param array{0: list<string>, 1: list<string>, 2: list<string>} $before
     */
    public static function dequeueSince(array $before): void
    {
        [$scripts, $styles, $modules] = self::snapshot();
        foreach (array_diff($scripts, $before[0]) as $handle) {
            self::assets('script')->dequeue($handle);
        }
        foreach (array_diff($styles, $before[1]) as $handle) {
            self::assets('style')->dequeue($handle);
        }
        foreach (array_diff($modules, $before[2]) as $id) {
            Runtime::scriptModules()->dequeue($id);
        }
    }

    /**
     * After a block has rendered: when a named block rendered nothing, whitespace aside, and its render enqueued
     * something or its own assets are not all queued yet, enqueue_empty_block_content_assets is asked; unless it
     * keeps them, what the render enqueued is taken off the queues again and the block's own are not added.
     * Otherwise $enqueue adds its own. The text between blocks (no name) is never asked about, as on the reference.
     *
     * @param array{0: list<string>, 1: list<string>, 2: list<string>} $before
     */
    public static function settleBlock(array $before, string $html, string $name, Closure $enqueue, mixed $type = null): void
    {
        if ($name !== '' && trim($html) === '' && (self::snapshot() !== $before || self::pending($type)) && !Runtime::hooks()->filter('enqueue_empty_block_content_assets', [false, $name])) {
            self::dequeueSince($before);
            return;
        }
        $enqueue();
    }

    /** Whether a block type has assets of its own not queued yet (its scripts, view scripts and modules, styles). */
    private static function pending(mixed $type): bool
    {
        if (!is_object($type)) {
            return false;
        }
        foreach ([...(array) ($type->script_handles ?? []), ...(array) ($type->view_script_handles ?? [])] as $handle) {
            if (!self::assets('script')->enqueued((string) $handle)) {
                return true;
            }
        }
        foreach ((array) ($type->style_handles ?? []) as $handle) {
            if (!self::assets('style')->enqueued((string) $handle)) {
                return true;
            }
        }
        return array_diff(array_map('strval', (array) ($type->view_script_module_ids ?? [])), Runtime::scriptModules()->queue()) !== [];
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
