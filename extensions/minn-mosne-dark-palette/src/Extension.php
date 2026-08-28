<?php

declare(strict_types=1);

namespace Minn\Ext\DarkPalette;

use Minn\Blocks\Block;
use Minn\Content\Blocks;
use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Html;

/**
 * The mosne/dark-palette navigation item, as the plugin rendered it: a
 * toggle whose state lives in the reader's browser, with the plugin's
 * styles from its build folder, which the site carries. The plugin's view
 * script is a module on the block interactivity runtime, which the engine
 * does not provide, so this extension drives the toggle with its own
 * script instead.
 */
final class Extension implements MinnExtension
{
    private bool $used = false;

    public function register(Seams $minn): void
    {
        $own = rtrim((string) ($minn->site->option('siteurl') ?? ''), '/') . '/wp-content/plugins/minn-mosne-dark-palette/assets/view.js';
        $dir = ABSPATH . 'wp-content/plugins/mosne-dark-palette/build/';
        Blocks::renderer()->registerDynamic('mosne/dark-palette', function (Block $block): string {
            $this->used = true;
            $classes = trim('has-icon hide-label ' . (string) $block->attr('classOptions', ''));
            $classes = implode(' ', array_unique(array_filter(explode(' ', $classes))));
            $context = ['mode' => 'auto', 'aria' => 'OS auto', 'current' => 'has-icon--auto', 'submenu' => false, 'labels' => ['auto' => 'OS auto', 'light' => 'Light', 'dark' => 'Dark'], 'hasAuto' => true];
            return '<li class="' . Html::attr($classes) . ' wp-block-navigation-item open-on-hover-click wp-block-navigation-submenu wp-block-mosne-dark-palette">' . "\n\n\t\n\t\t"
                . '<div class="navigaiton-item__wrapper"' . "\n\t\t\t" . 'tabindex="-1"' . "\n\t\t\t" . 'data-wp-interactive="mosne/dark-palette"' . "\n\t\t\t" . 'data-wp-init="callbacks.colorInit"' . "\n\t\t\t"
                . 'data-wp-on--click="actions.toggleMode"' . "\n\t\t\t" . 'data-wp-on--keydown="actions.toggleMode"' . "\n\t\t\t"
                . "data-wp-context='" . json_encode($context, JSON_UNESCAPED_SLASHES) . "'\t\t>\n\t\t\t"
                . '<button aria-expanded="false"' . "\n\t\t\t\t" . 'type="button"' . "\n\t\t\t\t" . 'data-wp-bind--class="context.current"' . "\n\t\t\t\t" . 'data-wp-bind--aria-expanded="context.submenu"' . "\n\t\t\t\t" . 'class="has-icon--auto">' . "\n\t\t\t\t"
                . '<span aria-label="OS auto" data-wp-bind--aria-label="context.aria">' . "\n\t\t\t\t\t" . 'Theme' . "\t\t\t\t" . '</span>' . "\n\t\t\t" . '</button>' . "\n\t\t" . '</div>' . "\n\n\t" . '</li>' . "\n";
        });
        $minn->head(function () use ($dir): string {
            if (!$this->used || !is_file($dir . 'style-index.css')) {
                return '';
            }
            return '<style id="mosne-dark-palette-style-inline-css">' . "\n" . file_get_contents($dir . 'style-index.css') . "\n</style>\n";
        });
        $minn->footer(function () use ($own): string {
            if (!$this->used) {
                return '';
            }
            return '<script id="mosne-dark-palette-inline-js-after">(function () { let initMode = "auto"; try { initMode = window.localStorage.getItem("mosne-dark-palette") || "auto"; } catch (error) {} if (initMode === "dark" || initMode === "auto" && window.matchMedia("(prefers-color-scheme: dark)").matches) { document.documentElement.classList.add("is-dark-theme"); } })();</script>' . "\n"
                . '<script defer id="mosne-dark-palette-view-js" src="' . Html::attr($own) . '"></script>' . "\n";
        });
    }
}
