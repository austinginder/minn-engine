<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Posts;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Content\SiteIcon;
use Minn\Front\AdminBar;
use Minn\Front\DocumentTitle;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Front\Resolution;
use Minn\Runtime\Plugins;
use Minn\Runtime\Runtime;
use Minn\Support\Html;

/**
 * A whole page from the active classic theme: the reference's PHP template
 * loader. The main query stands, template_redirect fires, the hierarchy
 * picks a PHP file, template_include filters it, and load_template() runs
 * it as the response body. The theme prints its own document; the engine
 * contributes the reference's wp_head defaults (title tag, robots, feed
 * links, REST and oEmbed discovery, canonical, site icon) as hooks the
 * theme's wp_head() call fires.
 */
final readonly class ClassicRenderer
{
    public function __construct(
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private ClassicTheme $theme,
        private ?Theme $styleTheme,
        private MainQueryBridge $bridge,
        private ?AdminBar $bar = null,
    ) {
    }

    /** A classic renderer over the database door. */
    public static function create(\Minn\Db $db, ClassicTheme $theme, ?Theme $styleTheme, Permalinks $permalinks, int $perPage, ?AdminBar $bar = null): self
    {
        $site = new Site($db);
        $posts = new Posts($db);
        return new self($site, $posts, $permalinks, $theme, $styleTheme, new MainQueryBridge($site, $posts, $perPage), $bar);
    }

    /** The page for a resolution through the theme's PHP templates. */
    public function render(Resolution $resolution, array $coreClasses, string $title): ?string
    {
        // Without the runtime (or with the theme's functions.php refused by
        // the symbol gate) the templates would fatal on the theme's own
        // helpers; the interim template stands in and the report says why.
        if (!Runtime::booted() || array_key_exists('theme:' . $this->theme->stylesheet, Plugins::skipped())) {
            return null;
        }
        Runtime::current()->set('classic_resolution', $resolution);
        Runtime::current()->set('classic_head', new HeadLinks($this->site, new SiteIcon($this->site, $this->posts, $this->permalinks), $this->permalinks));
        if ($this->bar !== null && !$resolution->preview) {
            Runtime::current()->set('classic_bar', $this->bar);
        }
        Runtime::current()->set('classic_body_classes', $this->bodyClasses($resolution, $coreClasses));
        $this->standTitle($resolution);
        $this->registerHead();
        $this->bridge->stand($resolution);
        $template = (string) \apply_filters('template_include', $this->template());
        if ($template === '' || !is_file($template)) {
            return null;
        }
        ob_start();
        \load_template($template, false);
        return (string) ob_get_clean();
    }

    /** The reference's loader order: the first true conditional whose template exists wins, index.php last. */
    private function template(): string
    {
        $sequence = [
            ['is_404', 'get_404_template'],
            ['is_search', 'get_search_template'],
            ['is_front_page', 'get_front_page_template'],
            ['is_home', 'get_home_template'],
            ['is_privacy_policy', 'get_privacy_policy_template'],
            ['is_post_type_archive', 'get_post_type_archive_template'],
            ['is_tax', 'get_taxonomy_template'],
            ['is_attachment', 'get_attachment_template'],
            ['is_single', 'get_single_template'],
            ['is_page', 'get_page_template'],
            ['is_singular', 'get_singular_template'],
            ['is_category', 'get_category_template'],
            ['is_tag', 'get_tag_template'],
            ['is_author', 'get_author_template'],
            ['is_date', 'get_date_template'],
            ['is_archive', 'get_archive_template'],
        ];
        foreach ($sequence as [$conditional, $getter]) {
            if (!$conditional()) {
                continue;
            }
            $template = (string) $getter();
            if ($template !== '') {
                return $template;
            }
        }
        return (string) \get_index_template();
    }

    /** @param list<string> $coreClasses @return list<string> */
    private function bodyClasses(Resolution $resolution, array $coreClasses): array
    {
        $custom = null;
        if (in_array($resolution->kind, [Kind::Page, Kind::Single], true)) {
            $custom = $this->posts->meta($resolution->id(), '_wp_page_template');
            $custom = $custom === 'default' ? null : $custom;
        }
        $parent = $this->theme->template !== $this->theme->stylesheet ? $this->theme->template : null;
        return BodyClasses::classic($resolution, $coreClasses, $custom, $this->theme->stylesheet, $parent, new BodyFacts(
            privacyPage: $resolution->kind === Kind::Page && $resolution->id() === (int) ($this->site->option('wp_page_for_privacy_policy') ?? 0),
            loggedIn: Reader::current()->loggedIn(),
            customLogo: (int) \get_theme_mod('custom_logo') > 0,
            embedResponsive: \current_theme_supports('responsive-embeds') !== false,
            bar: $this->bar !== null && !$resolution->preview,
        ));
    }

    private function standTitle(Resolution $resolution): void
    {
        $parts = DocumentTitle::parts($resolution, (string) ($this->site->option('blogname') ?? ''), (string) ($this->site->option('blogdescription') ?? ''));
        if ($resolution->kind === Kind::PostTypeArchive) {
            $parts['title'] = (string) \apply_filters('post_type_archive_title', $parts['title'], (string) ($resolution->record['name'] ?? ''));
        }
        Runtime::current()->set('document_title_parts', $parts);
    }

    /** The piece only the renderer holds: the engine stylesheets. The head defaults registered at setup_theme. */
    private function registerHead(): void
    {
        $this->registerStyles();
    }

    /** The engine's block stylesheet, and the theme.json presets when the theme ships one, where the reference prints its block-library and global styles. */
    private function registerStyles(): void
    {
        $trailer = defined('WP_DEBUG') && WP_DEBUG ? "\n/*# sourceURL=global-styles-inline-css */" : '';
        $styles = '<link rel="stylesheet" id="minn-blocks-css" href="' . Html::attr($this->permalinks->url('/minn/assets/blocks.css')) . '" />' . "\n";
        if ($this->styleTheme !== null) {
            $globalStyles = new GlobalStyles($this->styleTheme, null);
            $styles .= '<style id="global-styles-inline-css">' . "\n" . $globalStyles->css() . $trailer . "\n" . '</style>' . "\n";
            $fonts = $globalStyles->fontFaces();
            if ($fonts !== '') {
                $styles .= '<style class="wp-fonts-local">' . "\n" . $fonts . '</style>' . "\n";
            }
        }
        Runtime::current()->set('engine_head_styles', $styles);
    }
}
