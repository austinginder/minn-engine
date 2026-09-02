<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Site;
use Minn\Front\Permalinks;

/**
 * The active theme's global styles as wp/v2/global-styles/themes/{stylesheet}
 * reports them: the engine's defaults (captured from the reference under a
 * theme with no theme.json) with the theme's own settings and styles over
 * them, and the style variations the theme ships.
 */
final readonly class ThemeStyles
{
    /** @param Theme|null $theme null under a theme without theme.json: the defaults alone */
    public function __construct(
        private ?Theme $theme,
        public string $stylesheet,
    ) {
    }

    /** The site's active theme as styling data, whether or not it is a block theme. */
    public static function forSite(Site $site, Permalinks $permalinks, string $themesDir): self
    {
        return new self(Theme::forStyles($site, $permalinks, $themesDir), (string) ($site->option('stylesheet') ?? ''));
    }

    /** Settings: presets by origin, appearanceTools expanded, the defaults underneath. */
    public function settings(): array
    {
        $settings = self::defaults()['settings'];
        if ($this->theme === null) {
            return $settings;
        }
        $own = StyleSettings::normalize((array) ($this->theme->json()['settings'] ?? []), 'theme');
        // A theme.json switches the default shadow presets on unless it says otherwise.
        $own['shadow']['defaultPresets'] ??= true;
        return Theme::merge($settings, $own);
    }

    /** Styles: the theme's over the defaults, block style partials and section styles in, every var:preset token resolved. */
    public function styles(): array
    {
        $styles = self::defaults()['styles'];
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
            $variation = json_decode((string) file_get_contents($file), true);
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
            $partial = json_decode((string) file_get_contents($file), true);
            if (is_array($partial) && is_array($partial['blockTypes'] ?? null)) {
                $partials[(string) ($partial['slug'] ?? pathinfo($file, PATHINFO_FILENAME))] = array_values(array_filter($partial['blockTypes'], 'is_string'));
            }
        }
        return $partials;
    }

    /** @return array{settings: array, styles: array} */
    private static function defaults(): array
    {
        return (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/global-styles.json'), true);
    }
}
