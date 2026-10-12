<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Site;
use Minn\Front\Permalinks;
use Minn\Json;

/**
 * The active theme's global styles as wp/v2/global-styles/themes/{stylesheet}
 * reports them: the engine's defaults (captured from the reference under a
 * theme with no theme.json) with the theme's own settings and styles over
 * them, and the style variations the theme ships.
 */
final readonly class ThemeStyles
{
    /**
     * @param Theme|null $theme null under a theme without theme.json: the defaults alone
     * @param array{settings?: array, styles?: array}|null $core core's layer when not the stored one (a plugin changed it)
     */
    public function __construct(
        private ?Theme $theme,
        public string $stylesheet,
        private ?array $core = null,
    ) {
    }

    /**
     * The same styling data over core's layer as wp_theme_json_data_default
     * leaves it, for the places the reference reads core's layer through
     * that filter (its resolver's core and merged data).
     */
    public function withFilteredCore(): self
    {
        return new self($this->theme, $this->stylesheet, ThemeJsonData::layer('default', self::storedCore()));
    }

    /** The site's active theme as styling data, whether or not it is a block theme. */
    public static function forSite(Site $site, Permalinks $permalinks, string $themesDir): self
    {
        return new self(Theme::forStyles($site, $permalinks, $themesDir), (string) ($site->option('stylesheet') ?? ''));
    }

    /** Settings: presets by origin, appearanceTools expanded, the defaults underneath. */
    public function settings(): array
    {
        $settings = (array) ($this->defaults()['settings'] ?? []);
        if ($this->theme === null) {
            return $settings;
        }
        $own = StyleSettings::normalize((array) ($this->theme->json()['settings'] ?? []), 'theme');
        // A theme.json switches the default shadow presets on unless it says otherwise.
        $own['shadow']['defaultPresets'] ??= true;
        return Theme::merge($settings, $own);
    }

    /** The pattern directory slugs the theme.json lists (its patterns), as given; [] when it lists none. @return array<mixed> */
    public function patterns(): array
    {
        $patterns = $this->theme?->json()['patterns'] ?? [];
        return is_array($patterns) ? $patterns : [];
    }

    /** Styles: the theme's over the defaults, block style partials and section styles in, every var:preset token resolved. */
    public function styles(): array
    {
        $styles = (array) ($this->defaults()['styles'] ?? []);
        if ($this->theme !== null) {
            $styles = Theme::merge($styles, $this->withSectionStyles((array) ($this->theme->json()['styles'] ?? []), $this->partials()));
        }
        return StyleSettings::resolved($styles);
    }

    /**
     * The style variations under styles/: every JSON file there that names
     * no blockTypes (those are block style partials), the parent theme's
     * first, in path order, $schema dropped and the nodes normalized the
     * way the theme's own are.
     *
     * @return list<array>
     */
    public function variations(): array
    {
        $out = [];
        $partials = $this->partials();
        foreach ($this->theme?->styleFiles() ?? [] as $file) {
            $variation = Json::file($file, or: null);
            if (!is_array($variation) || isset($variation['blockTypes'])) {
                continue;
            }
            unset($variation['$schema']);
            if (is_array($variation['settings'] ?? null)) {
                $variation['settings'] = StyleSettings::normalize($variation['settings'], 'theme');
            }
            if (is_array($variation['styles'] ?? null)) {
                $variation['styles'] = StyleSettings::resolved($this->withSectionStyles($variation['styles'], $partials));
            }
            $out[] = $variation;
        }
        return $out;
    }

    /**
     * A root styles.variations node names section styles by the slug of
     * the block style partial that declares them; each lands under every
     * block type that partial names, the blocks it has to add in name
     * order, and the root node goes.
     *
     * @param array<string, list<string>> $partials slug => block types
     */
    private function withSectionStyles(array $styles, array $partials): array
    {
        $sections = (array) ($styles['variations'] ?? []);
        unset($styles['variations']);
        $added = [];
        foreach ($sections as $slug => $node) {
            foreach ($partials[$slug] ?? [] as $type) {
                $added[$type]['variations'][$slug] = (array) $node;
            }
        }
        ksort($added);
        foreach ($added as $type => $node) {
            $styles['blocks'][$type] = Theme::merge((array) ($styles['blocks'][$type] ?? []), $node);
        }
        return $styles;
    }

    /**
     * The block style partials under styles/: slug => the block types each names.
     *
     * @return array<string, list<string>>
     */
    private function partials(): array
    {
        $partials = [];
        foreach ($this->theme?->styleFiles() ?? [] as $file) {
            $partial = Json::file($file, or: null);
            if (is_array($partial) && is_array($partial['blockTypes'] ?? null)) {
                $partials[(string) ($partial['slug'] ?? pathinfo($file, PATHINFO_FILENAME))] = array_values(array_filter($partial['blockTypes'], 'is_string'));
            }
        }
        return $partials;
    }

    /** @return array{settings?: array, styles?: array} core's layer: the one given, else the stored one */
    private function defaults(): array
    {
        return $this->core ?? self::storedCore();
    }

    /** @return array{settings: array, styles: array} core's own layer as stored */
    private static function storedCore(): array
    {
        return (array) Json::file(MINN_ENGINE_DIR . '/data/global-styles.json');
    }
}
