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
 * toggle whose state lives in the reader's browser, the plugin's styles
 * from its build folder, which the site carries, and the palette rule the
 * plugin generated from the block's own attributes: under
 * html[data-theme="dark"] every colour in darkColorsPalette becomes a
 * --mosne-dark-palette-{slug} variable and re-points the matching
 * --wp--preset--color--{slug}. Without that rule the theme's light palette
 * stays in force in dark mode. The plugin's view script is a module on the
 * block interactivity runtime, which the engine does not provide, so this
 * extension drives the toggle with its own script instead; the head script
 * that sets data-theme before paint is the plugin's own, reproduced.
 */
final class Extension implements MinnExtension
{
    private bool $used = false;

    private string $palette = '';

    private string $defaultMode = 'auto';

    public function register(Seams $minn): void
    {
        $own = rtrim((string) ($minn->site->option('siteurl') ?? ''), '/') . '/wp-content/plugins/minn-mosne-dark-palette/assets/view.js';
        $dir = ABSPATH . 'wp-content/plugins/mosne-dark-palette/build/';
        Blocks::renderer()->registerDynamic('mosne/dark-palette', function (Block $block): string {
            $this->used = true;
            $this->defaultMode = self::mode((string) $block->attr('defaultOption', 'auto'));
            $this->palette = self::paletteRule($block);
            $classes = trim((string) $block->attr('classOptions', 'has-icon has-label'));
            $classes = implode(' ', array_unique(array_filter(explode(' ', $classes))));
            $labels = [
                'auto' => Html::attr((string) $block->attr('autoLabel', 'OS auto')),
                'light' => Html::attr((string) $block->attr('lightLabel', 'Light')),
                'dark' => Html::attr((string) $block->attr('darkLabel', 'Dark')),
            ];
            $context = [
                'mode' => $this->defaultMode,
                'aria' => $labels[$this->defaultMode],
                'current' => 'has-icon--auto',
                'submenu' => false,
                'labels' => $labels,
                'hasAuto' => (bool) $block->attr('enableAuto', true),
            ];
            return '<li class="' . Html::attr($classes) . ' wp-block-navigation-item open-on-hover-click wp-block-navigation-submenu wp-block-mosne-dark-palette">' . "\n\n\t\n\t\t"
                . '<div class="navigaiton-item__wrapper"' . "\n\t\t\t" . 'tabindex="-1"' . "\n\t\t\t" . 'data-wp-interactive="mosne/dark-palette"' . "\n\t\t\t" . 'data-wp-init="callbacks.colorInit"' . "\n\t\t\t"
                . 'data-wp-on--click="actions.toggleMode"' . "\n\t\t\t" . 'data-wp-on--keydown="actions.toggleMode"' . "\n\t\t\t"
                . "data-wp-context='" . json_encode($context, JSON_UNESCAPED_SLASHES) . "'\t\t>\n\t\t\t"
                . '<button aria-expanded="false"' . "\n\t\t\t\t" . 'type="button"' . "\n\t\t\t\t" . 'data-wp-bind--class="context.current"' . "\n\t\t\t\t" . 'data-wp-bind--aria-expanded="context.submenu"' . "\n\t\t\t\t" . 'class="has-icon--auto">' . "\n\t\t\t\t"
                . '<span aria-label="' . $labels[$this->defaultMode] . '" data-wp-bind--aria-label="context.aria">' . "\n\t\t\t\t\t" . Html::esc((string) $block->attr('defaultLabel', 'Theme')) . "\t\t\t\t" . '</span>' . "\n\t\t\t" . '</button>' . "\n\t\t" . '</div>' . "\n\n\t" . '</li>' . "\n";
        });
        $minn->head(function () use ($dir): string {
            if (!$this->used || !is_file($dir . 'style-index.css')) {
                return '';
            }
            $mode = $this->defaultMode;
            return '<style id="mosne-dark-palette-style-inline-css">' . "\n" . file_get_contents($dir . 'style-index.css') . "\n\n" . $this->palette . "\n</style>\n"
                . '<script id="mosne-dark-palette-inline-js-after">(function () { let initMode = "' . $mode . '"; try { initMode = window.localStorage.getItem("mosne-dark-palette") || "' . $mode . '"; } catch (error) { console.error(error.message); } if (initMode === "dark" || initMode === "auto" && window.matchMedia("(prefers-color-scheme: dark)").matches) { document.documentElement.setAttribute("data-theme", "dark"); } else { document.documentElement.setAttribute("data-theme", "light"); } })();</script>' . "\n";
        });
        $minn->footer(function () use ($own): string {
            if (!$this->used) {
                return '';
            }
            return '<script defer id="mosne-dark-palette-view-js" src="' . Html::attr($own) . '"></script>' . "\n";
        });
    }

    /** The palette rule: one variable pair per colour, under the scheme the block is not designed for. */
    private static function paletteRule(Block $block): string
    {
        $own = '';
        $presets = '';
        $colors = $block->attr('darkColorsPalette', []);
        foreach (is_array($colors) ? $colors : [] as $color) {
            if (!is_array($color) || !isset($color['slug'], $color['color'])) {
                continue;
            }
            $slug = Html::attr((string) $color['slug']);
            $value = Html::attr((string) $color['color']);
            $own .= '--mosne-dark-palette-' . $slug . ': ' . $value . ';';
            $presets .= '--wp--preset--color--' . $slug . ': var(--mosne-dark-palette-' . $slug . ', ' . $value . ');';
        }
        $scheme = (string) $block->attr('themeOption', 'light') === 'light' ? 'dark' : 'light';
        return 'html[data-theme="' . $scheme . '"] { ' . $own . ' ' . $presets . ' prefers-color-scheme: ' . $scheme . ';}';
    }

    private static function mode(string $mode): string
    {
        return in_array($mode, ['auto', 'light', 'dark'], true) ? $mode : 'auto';
    }
}
