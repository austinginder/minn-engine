<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\CoreBlocks;
use Minn\Blocks\RenderState;
use Minn\Blocks\Styles;
use Minn\Runtime\Runtime;

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

    /** The theme.json (plus site-editor) styles node, under the engine's own defaults. */
    public function styles(): array
    {
        $json = $this->user === null ? $this->theme->json() : Theme::merge($this->theme->json(), $this->user);
        $defaults = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/styles.json'), true);
        return Theme::merge($defaults, (array) ($json['styles'] ?? []));
    }

    /**
     * The same node with every `var:preset|…` token resolved to the custom
     * property it names, which is the shape a caller reading the styles as
     * data expects (the CSS writer resolves them on the way out instead).
     */
    public function resolvedStyles(): array
    {
        return StyleSettings::resolved($this->styles());
    }

    /** The global stylesheet from theme.json and the user's styles, as a page prints it: only the core blocks it rendered. */
    public function css(): string
    {
        ['variables' => $variables, 'base' => $base, 'presets' => $presets, 'blocks' => $blocks] = $this->parts();
        $styles = $this->styles();
        $out = $variables . $base . $presets;
        if (is_string($styles['css'] ?? null) && $styles['css'] !== '') {
            // The theme's (or the site editor's) own CSS, printed as written between the preset classes and the block styles.
            $out .= str_ireplace('</style', '', $styles['css']);
        }
        $rendered = RenderState::current()->blocks();
        foreach ($blocks as $name => $blockStyles) {
            $core = str_starts_with((string) $name, 'core/');
            if (!$core || isset($rendered[$name]) && !in_array($name, self::STYLESHEET_LESS, true)) {
                $out .= $this->blockStyles((string) $name, (array) $blockStyles);
            }
        }
        $out .= $this->containerStyles();
        return $out;
    }

    /**
     * The stylesheet wp_get_global_stylesheet answers (probe editor-styles),
     * by type: the custom properties, the styles (every block's, rendered or
     * not), the preset classes; in that order.
     *
     * @param list<string> $types
     */
    public function stylesheet(array $types): string
    {
        ['variables' => $variables, 'base' => $base, 'presets' => $presets, 'blocks' => $blocks] = $this->parts();
        $out = in_array('variables', $types, true) ? $variables : '';
        if (in_array('styles', $types, true)) {
            $out .= $base;
            // The blocks in the reference's merged order: its own theme.json and the blocks' defaults (data/global-styles.json), then the theme's.
            $core = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/global-styles.json'), true);
            $blocks = Theme::merge((array) ($core['styles']['blocks'] ?? []), $blocks);
            foreach ($blocks as $name => $blockStyles) {
                $out .= $this->blockStyles((string) $name, (array) $blockStyles);
            }
        }
        return $out . (in_array('presets', $types, true) ? $presets : '');
    }

    /** @return array{variables: string, base: string, presets: string, blocks: array<string, mixed>} */
    private function parts(): array
    {
        $json = $this->user === null ? $this->theme->json() : Theme::merge($this->theme->json(), $this->user);
        $settings = (array) ($json['settings'] ?? []);
        // Core's own theme.json sits under the theme's: the button element's inherit-everything
        // defaults print for every theme, each key replaceable by the theme.
        $styles = $this->styles();
        $presets = StylePresets::presets($settings);
        $variables = ':root{' . StylePresets::presetProperties($presets) . StylePresets::customProperties((array) ($settings['custom'] ?? [])) . '}';
        $variables .= $this->blockCustomProperties((array) ($settings['blocks'] ?? []));
        $variables .= '.wp-block-button{--wp--preset--dimension--25: 25%;--wp--preset--dimension--50: 50%;--wp--preset--dimension--75: 75%;--wp--preset--dimension--100: 100%;}';
        $layout = (array) ($settings['layout'] ?? []);
        $base = ':root { --wp--style--global--content-size: ' . ($layout['contentSize'] ?? '620px') . ';--wp--style--global--wide-size: ' . ($layout['wideSize'] ?? '1000px') . '; }';
        $base .= self::structuralRules((string) Styles::value((string) ($styles['spacing']['blockGap'] ?? '24px')), (bool) ($this->theme->json()['settings']['useRootPaddingAwareAlignments'] ?? false));
        $base .= $this->rootStyles($styles);
        $base .= self::elementStyles((array) ($styles['elements'] ?? []), '');
        return ['variables' => $variables, 'base' => $base, 'presets' => StylePresets::presetClasses($presets), 'blocks' => (array) ($styles['blocks'] ?? [])];
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
        foreach (StylePresets::fontFamilies((array) ($json['settings'] ?? [])) as $family) {
            foreach ((array) ($family['fontFace'] ?? []) as $face) {
                if (!is_array($face) || !isset($face['fontFamily'])) {
                    continue;
                }
                $sources = [];
                foreach ((array) ($face['src'] ?? []) as $src) {
                    $url = $this->fontUrl((string) $src);
                    if ($url !== null) {
                        $sources[] = "url('" . $url . "') format('" . StylePresets::fontFormat($url) . "')";
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

    /** The engine's own layout rules on the reference's class hooks; the global-padding rules exist only under root-padding-aware alignments. */
    private static function structuralRules(string $gap, bool $aware = true): string
    {
        $globalPadding = !$aware ? '' : '.wp-site-blocks { padding-top: var(--wp--style--root--padding-top); padding-bottom: var(--wp--style--root--padding-bottom); }'
            . '.has-global-padding { padding-right: var(--wp--style--root--padding-right); padding-left: var(--wp--style--root--padding-left); }'
            . '.has-global-padding > .alignfull { margin-right: calc(var(--wp--style--root--padding-right) * -1); margin-left: calc(var(--wp--style--root--padding-left) * -1); }'
            . '.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) { padding-right: 0; padding-left: 0; }'
            . '.has-global-padding :where(:not(.alignfull.is-layout-flow) > .has-global-padding:not(.wp-block-block, .alignfull)) > .alignfull { margin-left: 0; margin-right: 0; }';
        return ':where(body) { margin: 0; }'
            . $globalPadding
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
        $declarations = self::declarations($aware ? array_diff_key($styles, ['spacing' => 1]) + ['spacing' => array_diff_key((array) ($styles['spacing'] ?? []), ['padding' => 1])] : array_diff_key($styles, ['spacing' => 1]) + ['spacing' => array_diff_key((array) ($styles['spacing'] ?? []), ['padding' => 1])], ['blockGap']);
        $padding = (array) ($styles['spacing']['padding'] ?? []);
        // Root padding is custom properties only under root-padding-aware
        // alignments; otherwise it lands on body outright (0px when unset).
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $property = $aware ? "--wp--style--root--padding-{$side}" : "padding-{$side}";
            $declarations[] = "{$property}: " . Styles::value((string) ($padding[$side] ?? '0px'));
        }
        return 'body{' . implode(';', $declarations) . ';}';
    }

    private static function elementStyles(array $elements, string $scope): string
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
            $full = $scope === '' ? $selector : implode(', ', array_map(static fn (string $s) => "{$scope} {$s}", explode(', ', $selector)));
            foreach ($states as $state => $stateRules) {
                $declarations = self::declarations($stateRules, []);
                if ($declarations === []) {
                    continue;
                }
                $wrapped = $state === '' && $scope === '' && !in_array($element, ['button', 'caption'], true)
                    ? $full
                    : ':root :where(' . ($state === '' ? $full : implode(', ', array_map(static fn (string $s) => $s . $state, explode(', ', $full)))) . ')';
                $out .= $wrapped . '{' . implode(';', $declarations) . ';}';
            }
            // An element's own CSS, under the element's selector (probe editor-styles).
            if (is_string($rules['css'] ?? null) && $rules['css'] !== '') {
                $out .= CustomCss::scoped($rules['css'], $full);
            }
        }
        return $out;
    }

    /** Each block's own settings.custom as custom properties on the block's root selector. @param array<string, mixed> $blocks */
    private function blockCustomProperties(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $name => $settings) {
            $properties = StylePresets::customProperties((array) (((array) $settings)['custom'] ?? []));
            if ($properties !== '') {
                $slug = str_starts_with((string) $name, 'core/') ? substr((string) $name, 5) : str_replace('/', '-', (string) $name);
                $selectors = self::selectorsOf((string) $name);
                $out .= (is_string($selectors['root'] ?? null) ? $selectors['root'] : (self::BLOCK_SELECTORS[$name] ?? ".wp-block-{$slug}")) . '{' . $properties . '}';
            }
        }
        return $out;
    }

    /**
     * A block's rules: its root rule, its layout gap rules, then a rule for
     * each feature its metadata gives a selector of its own (the avatar's
     * border on its image), its own CSS and its elements (probe
     * editor-styles).
     */
    private function blockStyles(string $name, array $blockStyles): string
    {
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '-', $name);
        $selectors = self::selectorsOf($name);
        $selector = self::rootSelector($name);
        [$rootStyles, $features] = self::byFeature($blockStyles, $selectors);
        $out = '';
        $declarations = self::declarations($rootStyles, ['blockGap']);
        if ($declarations !== []) {
            $out .= ":root :where({$selector}){" . implode(';', $declarations) . ';}';
        }
        if (isset($blockStyles['spacing']['blockGap'])) {
            $out .= self::gapRules("wp-block-{$slug}", Styles::value((string) $blockStyles['spacing']['blockGap']));
        }
        foreach ($features as $featureSelector => $featureStyles) {
            $featureDeclarations = self::declarations($featureStyles, ['blockGap']);
            if ($featureDeclarations !== []) {
                $out .= ":root :where({$featureSelector}){" . implode(';', $featureDeclarations) . ';}';
            }
        }
        if (isset($blockStyles['css'])) {
            $out .= CustomCss::scoped((string) $blockStyles['css'], $selector);
        }
        $out .= self::elementStyles((array) ($blockStyles['elements'] ?? []), $selector);
        return $out;
    }

    /** A block's root selector: its metadata's, the reference's own for a few core blocks, else .wp-block-{slug}. */
    public static function rootSelector(string $name): string
    {
        $root = self::selectorsOf($name)['root'] ?? null;
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '-', $name);
        return is_string($root) ? $root : (self::BLOCK_SELECTORS[$name] ?? ".wp-block-{$slug}");
    }

    /** A core block's selectors from its metadata. @return array<string, mixed> */
    private static function selectorsOf(string $name): array
    {
        return (array) (CoreBlocks::metadata($name)['selectors'] ?? []);
    }

    /**
     * A block's styles split between its root selector and the features (or
     * a feature's single properties) its metadata selects elsewhere.
     *
     * @param array<string, mixed> $styles
     * @param array<string, mixed> $selectors
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
     */
    private static function byFeature(array $styles, array $selectors): array
    {
        $features = [];
        foreach (['border', 'color', 'typography', 'spacing', 'dimensions', 'shadow'] as $feature) {
            $where = $selectors[$feature] ?? null;
            if ($where === null || !isset($styles[$feature])) {
                continue;
            }
            if (is_string($where)) {
                $features[$where] = array_merge_recursive($features[$where] ?? [], [$feature => $styles[$feature]]);
                unset($styles[$feature]);
                continue;
            }
            foreach ((array) $where as $property => $propertySelector) {
                if ($property !== 'root' && is_array($styles[$feature]) && array_key_exists($property, $styles[$feature])) {
                    $features[$propertySelector][$feature][$property] = $styles[$feature][$property];
                    unset($styles[$feature][$property]);
                }
            }
            if (isset($where['root']) && is_array($styles[$feature]) && $styles[$feature] !== []) {
                $features[$where['root']][$feature] = $styles[$feature];
                unset($styles[$feature]);
            }
        }
        return [$styles, $features];
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

    /**
     * A numbered style variation's CSS (probe block-supports): its element
     * rules scoped by the numbered class alone, then the variation on the
     * block's root selector (the class riding on the block's own class:
     * .wp-block-button.is-style-outline--3 .wp-block-button__link), its own
     * CSS, and the blocks styled inside it.
     */
    public static function variationCss(string $name, string $style, int $instance, array $variation): string
    {
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '-', $name);
        $root = self::BLOCK_SELECTORS[$name] ?? ".wp-block-{$slug}";
        $class = ".is-style-{$style}--{$instance}";
        $selector = str_contains($root, ".wp-block-{$slug}") ? preg_replace('/\.wp-block-' . preg_quote($slug, '/') . '(?![\w-])/', ".wp-block-{$slug}{$class}", $root, 1) : $root . $class;
        $out = self::elementStyles((array) ($variation['elements'] ?? []), $class);
        $declarations = self::declarations($variation, ['blockGap']);
        $out .= ":root :where({$selector}){" . implode(';', $declarations) . ($declarations === [] ? '' : ';') . '}';
        if (isset($variation['css'])) {
            $out .= CustomCss::scoped((string) $variation['css'], $selector);
        }
        foreach ((array) ($variation['blocks'] ?? []) as $innerName => $innerStyles) {
            $innerSlug = str_starts_with((string) $innerName, 'core/') ? substr((string) $innerName, 5) : str_replace('/', '-', (string) $innerName);
            $innerSelector = "{$class} " . (self::BLOCK_SELECTORS[$innerName] ?? ".wp-block-{$innerSlug}");
            $innerDeclarations = self::declarations((array) $innerStyles, ['blockGap']);
            if ($innerDeclarations !== []) {
                $out .= ":root :where({$innerSelector}){" . implode(';', $innerDeclarations) . ';}';
            }
            $out .= self::elementStyles((array) ($innerStyles['elements'] ?? []), $innerSelector);
        }
        return $out;
    }

    /** The page's block-support rules (layouts, element styles, and the rest), which the facade keeps in the style engine's block-supports store. */
    private function containerStyles(): string
    {
        $out = (string) Runtime::hooks()->filter('minn_block_supports_css', ['']);
        foreach (RenderState::current()->galleries() as $instance) {
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
            if (is_scalar($box) && (string) $box !== '') {
                $out[] = "{$property}: " . $value($box);
                continue;
            }
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
        foreach (['aspectRatio' => 'aspect-ratio', 'height' => 'height', 'minHeight' => 'min-height', 'minWidth' => 'min-width', 'width' => 'width'] as $key => $property) {
            if (isset($styles['dimensions'][$key])) {
                $out[] = "{$property}: " . $value($styles['dimensions'][$key]);
            }
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
        // The border shorthands run radius, color, width, style, ahead of the alphabet.
        $border = ['border-radius' => 0, 'border-color' => 1, 'border-width' => 2, 'border-style' => 3];
        $key = static function (string $declaration) use ($sides, $border): string {
            $property = strstr($declaration, ':', true) ?: $declaration;
            if (preg_match('/^(margin|padding)-(top|right|bottom|left)$/', $property, $m)) {
                return $m[1] . '-' . $sides[$m[2]];
            }
            if (isset($border[$property])) {
                return 'border-' . $border[$property];
            }
            return $property;
        };
        usort($declarations, static fn (string $a, string $b) => strcmp($key($a), $key($b)));
        return $declarations;
    }
}
