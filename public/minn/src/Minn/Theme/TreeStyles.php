<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\CoreBlocks;
use Minn\Blocks\Styles;

/**
 * The CSS one theme.json tree writes, as WP_Theme_JSON keeps the tree
 * (presets keyed by origin): its custom properties, its preset classes, its
 * styles (the root layout, the root, elements, blocks) and its own CSS, by
 * type, origin and option (probe theme-json-css). The rules are
 * GlobalStyles'; what the page's writer takes for granted is read from the
 * tree instead: block gaps only when the tree turns them on, the root padding
 * only on the sides it gives, and none of the engine's own defaults.
 */
final readonly class TreeStyles
{
    private const ORIGINS = ['default', 'blocks', 'theme', 'custom'];
    /** Preset kinds in the order they print: where each sits under settings, and the key holding its value. */
    private const KINDS = [
        'aspect-ratio' => [['dimensions', 'aspectRatios'], 'ratio'],
        'color' => [['color', 'palette'], 'color'],
        'gradient' => [['color', 'gradients'], 'gradient'],
        'font-size' => [['typography', 'fontSizes'], 'size'],
        'font-family' => [['typography', 'fontFamilies'], 'fontFamily'],
        'spacing' => [['spacing', 'spacingSizes'], 'size'],
        'shadow' => [['shadow', 'presets'], 'shadow'],
        'border-radius' => [['border', 'radiusSizes'], 'size'],
        'dimension' => [['dimensions', 'dimensionSizes'], 'size'],
    ];

    /** @param array<string, mixed> $tree */
    public function __construct(private array $tree)
    {
    }

    /**
     * The stylesheet for the types asked for, in the order variables,
     * styles, presets, the tree's own CSS; origins narrow the presets.
     *
     * @param list<string> $types
     * @param list<string> $origins
     * @param array<string, mixed> $options root_selector, scope, skip_root_layout_styles
     */
    public function stylesheet(array $types, array $origins, array $options): string
    {
        $scope = (string) ($options['scope'] ?? '');
        $out = in_array('variables', $types, true) ? $this->variables($origins, $scope) : '';
        if (in_array('styles', $types, true)) {
            $out .= $this->styles($options);
        } elseif (in_array('base-layout-styles', $types, true)) {
            $out .= $this->rootLayoutRules((string) ($options['root_selector'] ?? 'body'));
        }
        $out .= in_array('presets', $types, true) ? $this->presetClasses($origins, $scope) : '';
        return $out . (in_array('custom-css', $types, true) ? $this->customCss() : '');
    }

    /**
     * The presets and settings.custom as custom properties: on :root, then
     * on each block's settings selector that has its own.
     *
     * @param list<string> $origins
     */
    public function variables(array $origins, string $scope): string
    {
        $out = $this->variablesOf((array) ($this->tree['settings'] ?? []), $origins, GlobalStyles::scoped(':root', $scope));
        foreach ((array) ($this->tree['settings']['blocks'] ?? []) as $name => $settings) {
            $out .= $this->variablesOf((array) $settings, $origins, GlobalStyles::scoped(self::settingsSelector((string) $name), $scope));
        }
        return $out;
    }

    /**
     * One settings node's custom properties under its selector; nothing when it has none.
     *
     * @param array<string, mixed> $settings
     * @param list<string> $origins
     */
    public function variablesOf(array $settings, array $origins, string $selector): string
    {
        $properties = StylePresets::presetProperties($this->presets($settings, $origins)) . StylePresets::customProperties((array) ($settings['custom'] ?? []));
        return $properties === '' ? '' : "{$selector}{{$properties}}";
    }

    /**
     * The has-* classes: the root's bare, each block's under :where(its settings selector).
     *
     * @param list<string> $origins
     */
    public function presetClasses(array $origins, string $scope): string
    {
        $out = $this->classesOf((array) ($this->tree['settings'] ?? []), $origins, GlobalStyles::scoped(':root', $scope));
        foreach ((array) ($this->tree['settings']['blocks'] ?? []) as $name => $settings) {
            $out .= $this->classesOf((array) $settings, $origins, GlobalStyles::scoped(self::settingsSelector((string) $name), $scope));
        }
        return $out;
    }

    /**
     * One settings node's has-* classes, under :where(selector) unless it is the bare :root.
     *
     * @param array<string, mixed> $settings
     * @param list<string> $origins
     */
    public function classesOf(array $settings, array $origins, string $selector): string
    {
        return StylePresets::presetClasses($this->presets($settings, $origins), $selector === ':root' ? '' : ":where({$selector})");
    }

    /**
     * The styles: the root layout (unless skip_root_layout_styles), the
     * root's own rule, its CSS when the root is not body, the elements, then
     * each block; under the root_selector and scope options.
     *
     * @param array<string, mixed> $options
     */
    public function styles(array $options): string
    {
        $scope = (string) ($options['scope'] ?? '');
        $root = (string) ($options['root_selector'] ?? 'body');
        $root = $scope === '' ? $root : "{$scope} {$root}";
        $out = !empty($options['skip_root_layout_styles']) ? '' : $this->rootLayoutRules($root);
        $styles = (array) ($this->tree['styles'] ?? []);
        $out .= $this->rootStyles($root);
        if ($root !== 'body' && is_string($styles['css'] ?? null) && $styles['css'] !== '') {
            $out .= CustomCss::scoped($styles['css'], $root);
        }
        $out .= GlobalStyles::elementStyles((array) ($styles['elements'] ?? []), $scope);
        foreach (array_keys((array) ($styles['blocks'] ?? [])) as $name) {
            $out .= $this->blockStyles((string) $name, $scope);
        }
        return $out;
    }

    /**
     * The root layout: the content and wide sizes, the body's margin, the
     * global padding rules, the alignments; for body, the block gap (or the
     * fallback gap of flex and grid) and the layouts' own rules.
     */
    public function rootLayoutRules(string $selector): string
    {
        $settings = (array) ($this->tree['settings'] ?? []);
        $sizes = [];
        foreach (['contentSize' => 'content-size', 'wideSize' => 'wide-size'] as $key => $name) {
            if (isset($settings['layout'][$key])) {
                $sizes[] = "--wp--style--global--{$name}: " . $settings['layout'][$key];
            }
        }
        $out = $sizes === [] ? '' : ':root { ' . implode(';', $sizes) . '; }';
        $out .= GlobalStyles::rootRules(!empty($settings['useRootPaddingAwareAlignments']));
        return $selector === 'body' ? $out . $this->layoutStyles() : $out;
    }

    /** The block gap and the layouts' own rules; flex and grid fall back to a 0.5em gap when the tree has no block gap. */
    public function layoutStyles(): string
    {
        $gap = $this->blockGap();
        return ($gap === null ? ':where(.is-layout-flex){gap: 0.5em;}:where(.is-layout-grid){gap: 0.5em;}' : GlobalStyles::blockGapRules($gap)) . GlobalStyles::layoutRules();
    }

    /** The root's own rule: body as it is, any other root under :root :where(). */
    public function rootStyles(string $selector): string
    {
        $styles = (array) ($this->tree['styles'] ?? []);
        $aware = !empty($this->tree['settings']['useRootPaddingAwareAlignments']);
        $padding = $styles['spacing']['padding'] ?? null;
        if ($aware && is_array($padding)) {
            unset($styles['spacing']['padding']);
        }
        $declarations = GlobalStyles::declarations(array_diff_key($styles, ['elements' => 1, 'blocks' => 1, 'css' => 1, 'variations' => 1]), ['blockGap']);
        if ($aware && is_array($padding)) {
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                if (isset($padding[$side])) {
                    $declarations[] = "--wp--style--root--padding-{$side}: " . Styles::value((string) $padding[$side]);
                }
            }
        }
        if ($declarations === []) {
            return '';
        }
        return ($selector === 'body' ? 'body' : ":root :where({$selector})") . '{' . implode(';', $declarations) . ';}';
    }

    /** One block's rules; its own block gap only when the tree turns block gaps on. */
    public function blockStyles(string $name, string $scope = ''): string
    {
        $styles = (array) ($this->tree['styles']['blocks'][$name] ?? []);
        if ($this->blockGap() === null) {
            unset($styles['spacing']['blockGap']);
        }
        return GlobalStyles::blockStyles($name, $styles, $scope);
    }

    /** One block's own rules, its elements left out: each element is a style node of its own. */
    public function blockOwnStyles(string $name): string
    {
        $styles = (array) ($this->tree['styles']['blocks'][$name] ?? []);
        unset($styles['elements']);
        if ($this->blockGap() === null) {
            unset($styles['spacing']['blockGap']);
        }
        return GlobalStyles::blockStyles($name, $styles);
    }

    /**
     * The elements a block styles, in the order they print.
     *
     * @return list<string>
     */
    public function blockElements(string $name): array
    {
        return array_values(array_intersect(array_keys(GlobalStyles::ELEMENT_SELECTORS), array_keys((array) ($this->tree['styles']['blocks'][$name]['elements'] ?? []))));
    }

    /** One element's rules, under a block's selector when it belongs to one. */
    public function elementStyles(string $element, string $block = ''): string
    {
        $elements = $block === '' ? (array) ($this->tree['styles']['elements'] ?? []) : (array) ($this->tree['styles']['blocks'][$block]['elements'] ?? []);
        return isset($elements[$element]) ? GlobalStyles::elementStyles([$element => $elements[$element]], $block === '' ? '' : GlobalStyles::rootSelector($block)) : '';
    }

    /** The tree's own top-level CSS, as written. */
    public function customCss(): string
    {
        return (string) ($this->tree['styles']['css'] ?? '');
    }

    /** The block gap: the styles' value when the tree's settings turn block gaps on, else null. */
    private function blockGap(): ?string
    {
        $value = $this->tree['styles']['spacing']['blockGap'] ?? null;
        return ($this->tree['settings']['spacing']['blockGap'] ?? null) === true && is_string($value) && $value !== '' ? Styles::value($value) : null;
    }

    /**
     * A settings node's presets by kind, the origins asked for in order, one
     * per slug; a font size is written fluid by the root's typography
     * settings.
     *
     * @param array<string, mixed> $settings
     * @param list<string> $origins
     * @return array<string, list<array{slug: string, value: string}>>
     */
    private function presets(array $settings, array $origins): array
    {
        $out = [];
        foreach (self::KINDS as $kind => [$path, $key]) {
            $node = $settings[$path[0]][$path[1]] ?? null;
            if (!is_array($node)) {
                continue;
            }
            $lists = array_is_list($node) ? [$node] : array_map(static fn (string $origin) => (array) ($node[$origin] ?? []), array_values(array_intersect(self::ORIGINS, $origins)));
            foreach ($lists as $list) {
                foreach ($list as $preset) {
                    if (!is_array($preset) || !isset($preset['slug'], $preset[$key])) {
                        continue;
                    }
                    $value = $kind === 'font-size' ? (string) Typography::fontSize($preset, (array) ($this->tree['settings'] ?? [])) : (string) $preset[$key];
                    // A later origin's preset of the same slug takes the earlier one's place.
                    $out[$kind][(string) $preset['slug']] = ['slug' => (string) $preset['slug'], 'value' => $value];
                }
            }
        }
        return array_map('array_values', $out);
    }

    /** A block's settings selector: its experimental selector when it has one, else .wp-block-{slug}. */
    public static function settingsSelector(string $name): string
    {
        $selector = CoreBlocks::metadata($name)['supports']['__experimentalSelector'] ?? null;
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '-', $name);
        return is_string($selector) ? $selector : ".wp-block-{$slug}";
    }
}
