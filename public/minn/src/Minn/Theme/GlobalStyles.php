<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\RenderState;
use Minn\Blocks\Styles;

/**
 * theme.json to CSS. Presets become custom properties on :root and their
 * has-* utility classes; settings.layout sizes, root styles, elements, and
 * per-block styles become rules; the containers, galleries, and style
 * variations the page actually rendered get their own stylesheets. The
 * layout rules themselves (flow, constrained, flex, grid, alignments) are
 * the engine's own, written to the same class hooks.
 */
final readonly class GlobalStyles
{
    /**
     * Element selectors in the order the reference prints them, whatever
     * order theme.json or the saved styles list them in: the heading group
     * lands before the individual levels, so an h1 line-height beats the
     * group's. Themes list h1..h6 before heading and would otherwise win.
     */
    private const ELEMENT_SELECTORS = [
        'link' => 'a:where(:not(.wp-element-button))',
        'heading' => 'h1, h2, h3, h4, h5, h6',
        'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6',
        'button' => '.wp-element-button, .wp-block-button__link',
        'caption' => '.wp-element-caption, .wp-block-audio figcaption, .wp-block-embed figcaption, .wp-block-gallery figcaption, .wp-block-image figcaption, .wp-block-table figcaption, .wp-block-video figcaption',
        'cite' => 'cite',
    ];

    /** Blocks whose metadata names a root selector other than .wp-block-{slug}. */
    private const BLOCK_SELECTORS = [
        'core/paragraph' => 'p',
        'core/list-item' => '.wp-block-list > li',
        'core/button' => '.wp-block-button .wp-block-button__link',
        'core/table' => '.wp-block-table > table',
        'core/icon' => '.wp-block-icon svg',
    ];

    /**
     * The reference prints a core block's theme.json styles only when the
     * page rendered the block with output (a generated excerpt counts), and
     * attaches them to the block's own stylesheet; these core blocks have
     * none, so their styles never reach a page. A block from outside core
     * has no such stylesheet to wait for, so its styles always print.
     */
    private const STYLESHEET_LESS = [
        'core/block', 'core/column', 'core/comments-pagination-next', 'core/comments-pagination-numbers', 'core/comments-pagination-previous',
        'core/comments-title', 'core/freeform', 'core/home-link', 'core/html', 'core/legacy-widget', 'core/list-item', 'core/missing', 'core/more',
        'core/navigation-submenu', 'core/nextpage', 'core/page-list-item', 'core/pattern', 'core/query-no-results', 'core/query-pagination-next',
        'core/query-pagination-numbers', 'core/query-pagination-previous', 'core/query', 'core/shortcode', 'core/social-link', 'core/tab-panels',
        'core/template-part', 'core/terms-query', 'core/widget-group',
    ];

    /** @param array|null $user the site editor's saved global styles, layered over the theme */
    public function __construct(private Theme $theme, private ?array $user = null)
    {
    }

    public function css(): string
    {
        $json = $this->user === null ? $this->theme->json() : Theme::merge($this->theme->json(), $this->user);
        $settings = (array) ($json['settings'] ?? []);
        // Core's own theme.json sits under the theme's: the button element's inherit-everything
        // defaults print for every theme, each key replaceable by the theme.
        $defaults = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/styles.json'), true);
        $styles = Theme::merge($defaults, (array) ($json['styles'] ?? []));
        $presets = $this->presets($settings);

        $out = ':root{' . $this->presetProperties($presets) . '}';
        $out .= '.wp-block-button{--wp--preset--dimension--25: 25%;--wp--preset--dimension--50: 50%;--wp--preset--dimension--75: 75%;--wp--preset--dimension--100: 100%;}';
        $layout = (array) ($settings['layout'] ?? []);
        $out .= ':root { --wp--style--global--content-size: ' . ($layout['contentSize'] ?? '620px') . ';--wp--style--global--wide-size: ' . ($layout['wideSize'] ?? '1000px') . '; }';
        $out .= self::structuralRules((string) Styles::value((string) ($styles['spacing']['blockGap'] ?? '24px')));
        $out .= $this->rootStyles($styles);
        $out .= $this->elementStyles((array) ($styles['elements'] ?? []), '');
        $out .= $this->presetClasses($presets);
        if (is_string($styles['css'] ?? null) && $styles['css'] !== '') {
            // The theme's (or the site editor's) own CSS, printed as written between the preset classes and the block styles.
            $out .= str_ireplace('</style', '', $styles['css']);
        }
        $rendered = RenderState::blocks();
        foreach ((array) ($styles['blocks'] ?? []) as $name => $blockStyles) {
            $core = str_starts_with((string) $name, 'core/');
            if (!$core || isset($rendered[$name]) && !in_array($name, self::STYLESHEET_LESS, true)) {
                $out .= $this->blockStyles((string) $name, (array) $blockStyles);
            }
        }
        $out .= $this->variationStyles((array) ($styles['blocks'] ?? []));
        $out .= $this->containerStyles();
        return $out;
    }

    /** @return array<string, list<array{slug: string, value: string}>> */
    private function presets(array $settings): array
    {
        $core = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/presets.json'), true);
        $merge = static fn (array $defaults, array $own, string $key) => [...$defaults, ...array_map(static fn (array $p) => ['slug' => (string) $p['slug'], 'value' => (string) $p[$key]], $own)];
        $fontSizes = self::presetList($settings['typography']['fontSizes'] ?? []);
        $spacing = self::presetList($settings['spacing']['spacingSizes'] ?? []);
        return [
            'aspect-ratio' => $core['aspect-ratio'] ?? [],
            'color' => $merge($core['color'] ?? [], self::presetList($settings['color']['palette'] ?? []), 'color'),
            'gradient' => $merge($core['gradient'] ?? [], self::presetList($settings['color']['gradients'] ?? []), 'gradient'),
            'font-size' => array_map(static fn (array $p) => ['slug' => (string) $p['slug'], 'value' => self::fluidFontSize($p, $settings)], self::defaultSlugsFirst($fontSizes, ['small', 'medium', 'large', 'x-large'])),
            'font-family' => array_map(static fn (array $p) => ['slug' => (string) $p['slug'], 'value' => (string) $p['fontFamily']], self::fontFamilies($settings)),
            'spacing' => self::spacingPresets($core['spacing'] ?? [], $spacing),
            'shadow' => $merge($core['shadow'] ?? [], self::presetList($settings['shadow']['presets'] ?? []), 'shadow'),
        ];
    }

    /**
     * A theme size that reuses one of core's slugs prints in core's position;
     * the theme's own slugs follow.
     *
     * @param list<array> $presets
     * @param list<string> $defaults
     * @return list<array>
     */
    private static function defaultSlugsFirst(array $presets, array $defaults): array
    {
        $rank = array_flip($defaults);
        $keyed = [];
        foreach ($presets as $i => $preset) {
            $slug = (string) ($preset['slug'] ?? '');
            $keyed[] = [isset($rank[$slug]) ? $rank[$slug] : count($defaults) + $i, $preset];
        }
        usort($keyed, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        return array_column($keyed, 1);
    }

    /**
     * The default spacing scale (20 to 80) is always present, whatever
     * defaultSpacingSizes says; a theme size with the same slug replaces the
     * default in place, and the theme's other sizes follow the scale.
     *
     * @return list<array{slug: string, value: string}>
     */
    private static function spacingPresets(array $defaults, array $own): array
    {
        $out = [];
        foreach ($defaults as $preset) {
            $out[(string) $preset['slug']] = ['slug' => (string) $preset['slug'], 'value' => (string) $preset['value']];
        }
        foreach ($own as $preset) {
            $out[(string) $preset['slug']] = ['slug' => (string) $preset['slug'], 'value' => (string) $preset['size']];
        }
        return array_values($out);
    }

    /**
     * A preset list as theme.json writes it is a plain list; as the site
     * editor saves it, it is keyed by origin (default, theme, custom). The
     * reference prints the origins in that order, so the two shapes flatten
     * to one list here.
     *
     * @return list<array>
     */
    public static function presetList(mixed $presets): array
    {
        if (!is_array($presets)) {
            return [];
        }
        if (array_is_list($presets)) {
            return array_values(array_filter($presets, 'is_array'));
        }
        $out = [];
        foreach (['default', 'theme', 'custom'] as $origin) {
            foreach ((array) ($presets[$origin] ?? []) as $preset) {
                if (is_array($preset)) {
                    $out[] = $preset;
                }
            }
        }
        return $out;
    }

    /** @return list<array> */
    public static function fontFamilies(array $settings): array
    {
        return self::presetList($settings['typography']['fontFamilies'] ?? []);
    }

    /**
     * The @font-face rules for every family that declares font files, as
     * the reference prints them in its own style element: family (quoted
     * when it has a space), style, weight, display, then the sources with
     * file:./ resolved against the theme that carries the file and the
     * format named from the extension. Families without files print
     * nothing.
     */
    public function fontFaces(): string
    {
        $json = $this->user === null ? $this->theme->json() : Theme::merge($this->theme->json(), $this->user);
        $out = '';
        foreach (self::fontFamilies((array) ($json['settings'] ?? [])) as $family) {
            foreach ((array) ($family['fontFace'] ?? []) as $face) {
                if (!is_array($face) || !isset($face['fontFamily'])) {
                    continue;
                }
                $sources = [];
                foreach ((array) ($face['src'] ?? []) as $src) {
                    $url = $this->fontUrl((string) $src);
                    if ($url !== null) {
                        $sources[] = "url('" . $url . "') format('" . self::fontFormat($url) . "')";
                    }
                }
                if ($sources === []) {
                    continue;
                }
                $name = (string) $face['fontFamily'];
                $out .= '@font-face{font-family:' . (preg_match('/^[\w-]+$/', $name) ? $name : '"' . str_replace('"', '', $name) . '"')
                    . ';font-style:' . Styles::value((string) ($face['fontStyle'] ?? 'normal'))
                    . ';font-weight:' . Styles::value((string) ($face['fontWeight'] ?? '400'))
                    . ';font-display:' . Styles::value((string) ($face['fontDisplay'] ?? 'fallback'))
                    . ';src:' . implode(', ', $sources) . ";}\n";
            }
        }
        return $out;
    }

    private function fontUrl(string $src): ?string
    {
        if (!str_starts_with($src, 'file:./')) {
            return preg_match('#^https?://[^\s\'"()]+$#', $src) ? $src : null;
        }
        $relative = substr($src, 7);
        if ($relative === '' || str_contains($relative, '..') || !preg_match('#^[\w./-]+$#', $relative)) {
            return null;
        }
        for ($theme = $this->theme; $theme !== null; $theme = $theme->parent) {
            if (is_file("{$theme->dir}/{$relative}")) {
                return "{$theme->uri}/{$relative}";
            }
        }
        return null;
    }

    private static function fontFormat(string $url): string
    {
        return match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))) {
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            'eot' => 'embedded-opentype',
            'svg' => 'svg',
            default => 'woff2',
        };
    }

    /**
     * A font size with fluid bounds becomes clamp(min, min + ((1vw - v) * f),
     * max) scaled between a 320px viewport and the theme's wide size, which
     * is how the reference arrives at 0.196 for a 1rem to 1.125rem size on a
     * 1340px wide layout. A plain size stays as written.
     */
    private static function fluidFontSize(array $preset, array $settings): string
    {
        $size = (string) $preset['size'];
        $fluid = $preset['fluid'] ?? ($settings['typography']['fluid'] ?? false);
        if ($fluid === false || !is_array($fluid) || !isset($fluid['min'], $fluid['max'])) {
            return $size;
        }
        $min = (string) $fluid['min'];
        $max = (string) $fluid['max'];
        $unit = preg_replace('/[0-9.]/', '', $min) ?: 'rem';
        $perRem = $unit === 'rem' ? 1 : 16;
        $minViewport = 320 / 16;
        $maxViewport = ((float) ($settings['layout']['wideSize'] ?? '1600px')) / 16;
        $slope = ((float) $max - (float) $min) / $perRem / ($maxViewport - $minViewport);
        $factor = rtrim(rtrim(number_format($slope * 100, 3, '.', ''), '0'), '.');
        $offset = $unit === 'rem' ? ($minViewport / 100) . 'rem' : (320 / 100) . 'px';
        // The additive term is always in rem: a px minimum is converted (20px becomes 1.25rem, 35px 2.188rem).
        $base = $unit === 'rem' ? $min : rtrim(rtrim(number_format((float) $min / 16, 3, '.', ''), '0'), '.') . 'rem';
        return sprintf('clamp(%s, %s + ((1vw - %s) * %s), %s)', $min, $base, $offset, $factor, $max);
    }

    private function presetProperties(array $presets): string
    {
        $out = '';
        foreach ($presets as $kind => $entries) {
            foreach ($entries as $entry) {
                $out .= "--wp--preset--{$kind}--{$entry['slug']}: {$entry['value']};";
            }
        }
        return $out;
    }

    private function presetClasses(array $presets): string
    {
        $out = '';
        foreach ($presets['color'] as $c) {
            $out .= ".has-{$c['slug']}-color{color: var(--wp--preset--color--{$c['slug']}) !important;}";
        }
        foreach ($presets['color'] as $c) {
            $out .= ".has-{$c['slug']}-background-color{background-color: var(--wp--preset--color--{$c['slug']}) !important;}";
        }
        foreach ($presets['color'] as $c) {
            $out .= ".has-{$c['slug']}-border-color{border-color: var(--wp--preset--color--{$c['slug']}) !important;}";
        }
        foreach ($presets['gradient'] as $g) {
            $out .= ".has-{$g['slug']}-gradient-background{background: var(--wp--preset--gradient--{$g['slug']}) !important;}";
        }
        foreach ($presets['font-size'] as $f) {
            $out .= ".has-{$f['slug']}-font-size{font-size: var(--wp--preset--font-size--{$f['slug']}) !important;}";
        }
        foreach ($presets['font-family'] as $f) {
            $out .= ".has-{$f['slug']}-font-family{font-family: var(--wp--preset--font-family--{$f['slug']}) !important;}";
        }
        return $out;
    }

    /** The engine's own layout rules on the reference's class hooks. */
    private static function structuralRules(string $gap): string
    {
        return ':where(body) { margin: 0; }'
            . '.wp-site-blocks { padding-top: var(--wp--style--root--padding-top); padding-bottom: var(--wp--style--root--padding-bottom); }'
            . '.has-global-padding { padding-right: var(--wp--style--root--padding-right); padding-left: var(--wp--style--root--padding-left); }'
            . '.has-global-padding > .alignfull { margin-right: calc(var(--wp--style--root--padding-right) * -1); margin-left: calc(var(--wp--style--root--padding-left) * -1); }'
            . '.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) { padding-right: 0; padding-left: 0; }'
            . '.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) > .alignfull { margin-left: 0; margin-right: 0; }'
            . '.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }'
            . ":where(.wp-site-blocks) > * { margin-block-start: {$gap}; margin-block-end: 0; }:where(.wp-site-blocks) > :first-child { margin-block-start: 0; }:where(.wp-site-blocks) > :last-child { margin-block-end: 0; }"
            . ":root { --wp--style--block-gap: {$gap}; }"
            . self::gapRules('', $gap)
            . '.is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}.is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}.is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}'
            . '.is-layout-constrained > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}.is-layout-constrained > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}.is-layout-constrained > .aligncenter{margin-left: auto !important;margin-right: auto !important;}'
            . '.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width: var(--wp--style--global--content-size);margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > .alignwide{max-width: var(--wp--style--global--wide-size);}'
            . 'body .is-layout-flex{display: flex;}.is-layout-flex{flex-wrap: wrap;align-items: center;}.is-layout-flex > :is(*, div){margin: 0;}body .is-layout-grid{display: grid;}.is-layout-grid > :is(*, div){margin: 0;}';
    }

    /** The block-gap rules for a layout scope: "" for the root, or a block's -is-layout- prefix. */
    private static function gapRules(string $prefix, string $gap): string
    {
        $flow = $prefix === '' ? '.is-layout-flow' : ".{$prefix}-is-layout-flow";
        $constrained = $prefix === '' ? '.is-layout-constrained' : ".{$prefix}-is-layout-constrained";
        $flex = $prefix === '' ? '.is-layout-flex' : ".{$prefix}-is-layout-flex";
        $grid = $prefix === '' ? '.is-layout-grid' : ".{$prefix}-is-layout-grid";
        return ":root :where({$flow}) > :first-child{margin-block-start: 0;}:root :where({$flow}) > :last-child{margin-block-end: 0;}:root :where({$flow}) > *{margin-block-start: {$gap};margin-block-end: 0;}"
            . ":root :where({$constrained}) > :first-child{margin-block-start: 0;}:root :where({$constrained}) > :last-child{margin-block-end: 0;}:root :where({$constrained}) > *{margin-block-start: {$gap};margin-block-end: 0;}"
            . ":root :where({$flex}){gap: {$gap};}:root :where({$grid}){gap: {$gap};}";
    }

    /**
     * Root styles land on body. With root-padding-aware alignments the root
     * padding is NOT applied to body; it becomes the custom properties the
     * has-global-padding rules read.
     */
    private function rootStyles(array $styles): string
    {
        $aware = (bool) ($this->theme->json()['settings']['useRootPaddingAwareAlignments'] ?? false);
        $declarations = self::declarations($aware ? array_diff_key($styles, ['spacing' => 1]) + ['spacing' => array_diff_key((array) ($styles['spacing'] ?? []), ['padding' => 1])] : $styles, ['blockGap']);
        $padding = (array) ($styles['spacing']['padding'] ?? []);
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $declarations[] = "--wp--style--root--padding-{$side}: " . Styles::value((string) ($padding[$side] ?? '0px'));
        }
        return 'body{' . implode(';', $declarations) . ';}';
    }

    private function elementStyles(array $elements, string $scope): string
    {
        $out = '';
        foreach (self::ELEMENT_SELECTORS as $element => $selector) {
            $rules = $elements[$element] ?? null;
            if (!is_array($rules)) {
                continue;
            }
            $states = ['' => (array) $rules];
            foreach ([':visited', ':hover', ':focus', ':active'] as $state) {
                if (isset($rules[$state])) {
                    $states[$state] = (array) $rules[$state];
                }
            }
            foreach ($states as $state => $stateRules) {
                $declarations = self::declarations($stateRules, []);
                if ($declarations === []) {
                    continue;
                }
                $full = $scope === '' ? $selector : implode(', ', array_map(static fn (string $s) => "{$scope} {$s}", explode(', ', $selector)));
                $wrapped = $state === '' && $scope === '' && !in_array($element, ['button', 'caption'], true)
                    ? $full
                    : ':root :where(' . ($state === '' ? $full : implode(', ', array_map(static fn (string $s) => $s . $state, explode(', ', $full)))) . ')';
                $out .= $wrapped . '{' . implode(';', $declarations) . ';}';
            }
        }
        return $out;
    }

    private function blockStyles(string $name, array $blockStyles): string
    {
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '-', $name);
        $selector = self::BLOCK_SELECTORS[$name] ?? ".wp-block-{$slug}";
        $out = '';
        $declarations = self::declarations($blockStyles, ['blockGap']);
        if ($declarations !== []) {
            $out .= ":root :where({$selector}){" . implode(';', $declarations) . ';}';
        }
        if (isset($blockStyles['spacing']['blockGap'])) {
            $out .= self::gapRules("wp-block-{$slug}", Styles::value((string) $blockStyles['spacing']['blockGap']));
        }
        if (isset($blockStyles['css'])) {
            $out .= self::scopedCss((string) $blockStyles['css'], $selector);
        }
        $out .= $this->elementStyles((array) ($blockStyles['elements'] ?? []), $selector);
        return $out;
    }

    private static function withoutEmpty(array $styles): array
    {
        foreach ($styles as $key => $value) {
            if (is_array($value)) {
                $styles[$key] = self::withoutEmpty($value);
            } elseif ($value === '' || $value === null) {
                unset($styles[$key]);
            }
        }
        return $styles;
    }

    /** Custom "css" blocks in theme.json use "&" for the block selector. */
    private static function scopedCss(string $css, string $selector): string
    {
        $out = '';
        foreach (preg_split('/(?<=\})/', $css, -1, PREG_SPLIT_NO_EMPTY) as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }
            if (!str_contains($rule, '{')) {
                $out .= ":root :where({$selector}){{$rule}}";
                continue;
            }
            [$sel, $body] = explode('{', $rule, 2);
            // Without an ampersand the reference simply prefixes the block selector: no space, and the
            // selector text keeps its own trailing whitespace.
            $sel = str_contains($sel, '&') ? str_replace('&', $selector, trim($sel)) : $selector . ltrim($sel);
            $out .= ":root :where({$sel}){{$body}";
        }
        return $out;
    }

    /** Style variations the page rendered, one rule set per numbered instance. */
    private function variationStyles(array $blocks): string
    {
        $out = '';
        foreach (RenderState::variations() as [$name, $style, $instance]) {
            $slug = substr($name, 5);
            $variation = (array) ($blocks[$name]['variations'][$style] ?? []);
            $selector = ".wp-block-{$slug}.is-style-{$style}--{$instance}";
            $declarations = self::declarations($variation, ['blockGap']);
            $out .= ":root :where({$selector}){" . implode(';', $declarations) . ($declarations === [] ? '' : ';') . '}';
            if (isset($variation['css'])) {
                $out .= self::scopedCss((string) $variation['css'], $selector);
            }
            $out .= $this->elementStyles((array) ($variation['elements'] ?? []), $selector);
        }
        return $out;
    }

    private function containerStyles(): string
    {
        $out = implode('', RenderState::elementRules());
        foreach (RenderState::containers() as $class => $declarations) {
            $out .= ".{$class}{{$declarations}}";
        }
        foreach (RenderState::galleries() as $instance) {
            $out .= ".wp-block-gallery.wp-block-gallery-{$instance}{--wp--style--unstable-gallery-gap:var( --wp--style--gallery-gap-default, var( --gallery-block--gutter-size, var( --wp--style--block-gap, 0.5em ) ) );}";
        }
        return $out;
    }

    /**
     * theme.json style properties to declarations: color, typography, spacing,
     * border, shadow, dimensions, outline.
     *
     * @return list<string>
     */
    private static function declarations(array $styles, array $skip): array
    {
        $out = [];
        $value = static fn ($v) => Styles::value((string) $v);
        // An empty string is how the site editor clears a value; the reference prints nothing for it.
        $styles = self::withoutEmpty($styles);
        if (isset($styles['color']['background'])) {
            $out[] = 'background-color: ' . $value($styles['color']['background']);
        }
        if (isset($styles['color']['gradient'])) {
            $out[] = 'background: ' . $value($styles['color']['gradient']);
        }
        if (isset($styles['color']['text'])) {
            $out[] = 'color: ' . $value($styles['color']['text']);
        }
        $border = (array) ($styles['border'] ?? []);
        foreach (['color', 'radius', 'style', 'width'] as $property) {
            if (isset($border[$property]) && !is_array($border[$property])) {
                $out[] = "border-{$property}: " . $value($border[$property]);
            }
        }
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            foreach (['color', 'style', 'width'] as $property) {
                if (isset($border[$side][$property])) {
                    $out[] = "border-{$side}-{$property}: " . $value($border[$side][$property]);
                }
            }
        }
        $typography = (array) ($styles['typography'] ?? []);
        foreach ([
            'fontFamily' => 'font-family', 'fontSize' => 'font-size', 'fontStyle' => 'font-style', 'fontWeight' => 'font-weight',
            'letterSpacing' => 'letter-spacing', 'lineHeight' => 'line-height', 'textDecoration' => 'text-decoration',
            'textTransform' => 'text-transform', 'textColumns' => 'column-count', 'writingMode' => 'writing-mode',
        ] as $key => $property) {
            if (isset($typography[$key])) {
                $out[] = "{$property}: " . $value($typography[$key]);
            }
        }
        foreach (['margin', 'padding'] as $property) {
            $box = $styles['spacing'][$property] ?? null;
            if (!is_array($box)) {
                continue;
            }
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                if (isset($box[$side])) {
                    $out[] = "{$property}-{$side}: " . $value($box[$side]);
                }
            }
        }
        if (isset($styles['shadow'])) {
            $out[] = 'box-shadow: ' . $value($styles['shadow']);
        }
        if (isset($styles['dimensions']['minHeight'])) {
            $out[] = 'min-height: ' . $value($styles['dimensions']['minHeight']);
        }
        foreach (['color', 'offset', 'style', 'width'] as $property) {
            if (isset($styles['outline'][$property])) {
                $out[] = "outline-{$property}: " . $value($styles['outline'][$property]);
            }
        }
        return self::ordered($out);
    }

    /**
     * The reference prints declarations in its own fixed property order:
     * alphabetical, except that the sides of a margin or padding run
     * top, right, bottom, left.
     *
     * @param list<string> $declarations
     * @return list<string>
     */
    private static function ordered(array $declarations): array
    {
        $sides = ['top' => 0, 'right' => 1, 'bottom' => 2, 'left' => 3];
        $key = static function (string $declaration) use ($sides): string {
            $property = strstr($declaration, ':', true) ?: $declaration;
            if (preg_match('/^(margin|padding)-(top|right|bottom|left)$/', $property, $m)) {
                return $m[1] . '-' . $sides[$m[2]];
            }
            return $property;
        };
        usort($declarations, static fn (string $a, string $b) => strcmp($key($a), $key($b)));
        return $declarations;
    }
}
