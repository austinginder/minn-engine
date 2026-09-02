<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\Context;
use Minn\Blocks\Dynamic\Theme\Comments;
use Minn\Blocks\Dynamic\Theme\Navigation;
use Minn\Blocks\Dynamic\Theme\PostBlocks;
use Minn\Blocks\Dynamic\Theme\QueryBlocks;
use Minn\Blocks\Dynamic\Theme\Structure;
use Minn\Blocks\Parser;
use Minn\Blocks\Renderer;
use Minn\Blocks\RenderState;
use Minn\Content\Blocks;
use Minn\Content\Comments as CommentStore;
use Minn\Content\Posts;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Content\SiteIcon;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Db;
use Minn\Extension\Extensions;
use Minn\Front\AdminBar;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Front\Resolution;
use Minn\Front\Resolver;
use Minn\Front\DocumentTitle;
use Minn\Runtime\MainQuery;
use Minn\Runtime\Runtime;
use Minn\Support\Html;
use Minn\Support\Serialized;

/**
 * A whole page from the active block theme: the template the resolution
 * maps to, rendered against the main query, inside the document shell the
 * reference emits (skip link, wp-site-blocks, the skip-link target on the
 * first main element).
 */
final readonly class PageRenderer
{
    public function __construct(
        private Site $site,
        private Theme $theme,
        private Templates $templates,
        private Renderer $renderer,
        private MainQueryBridge $bridge,
        private HeadLinks $headLinks,
        private ?AdminBar $bar = null,
    ) {
    }

    /** A page renderer over the database door. */
    public static function create(Db $db, Theme $theme, Permalinks $permalinks, int $perPage, ?AdminBar $bar = null): self
    {
        $site = new Site($db);
        $posts = new Posts($db);
        $users = new Users($db);
        $templates = new Templates($db, $posts, $theme, Runtime::booted() ? Runtime::blockTemplates() : null);
        $renderer = Blocks::renderer();
        (new Structure($theme, $templates, $site, $permalinks))->register($renderer);
        (new PostBlocks($posts, $users, $site, $permalinks))->register($renderer);
        (new QueryBlocks($posts, $site, $permalinks))->register($renderer);
        (new Navigation($db, $posts, $permalinks))->register($renderer);
        (new Comments($db, new CommentStore($db), $site, $permalinks))->register($renderer);
        return new self($site, $theme, $templates, $renderer, new MainQueryBridge($site, $posts, $perPage), new HeadLinks($site, new SiteIcon($site, $posts, $permalinks), $permalinks), $bar);
    }

    /**
     * The reference's body-class tokens: the singular and template tokens
     * sit in front of the type token ("page", "single"), the custom-logo
     * and embed tokens follow the core set, the paging tokens come after
     * those, and the theme (and child theme) tokens close the list.
     *
     * @return list<string>
     */
    public function bodyClasses(Resolution $resolution, array $coreClasses): array
    {
        $paging = array_values(array_filter($coreClasses, static fn (string $c) => preg_match('/^(?:page|single)?-?paged-\d+$/', $c) === 1));
        $classes = array_values(array_diff($coreClasses, $paging));
        if ($resolution->kind === Kind::Single) {
            $at = (int) array_search('single', $classes, true);
            array_splice($classes, $at, 0, ['wp-singular', (string) ($resolution->record['post_type'] ?? 'post') . '-template-default']);
        } elseif ($resolution->kind === Kind::Page) {
            $template = $this->templates->customTemplate($resolution->id());
            $tokens = $template === null
                ? ['wp-singular', 'page-template-default']
                : ['wp-singular', 'page-template', 'page-template-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($template))];
            $at = (int) array_search('page', $classes, true);
            array_splice($classes, $at, 0, $tokens);
        }
        if (Reader::current()->loggedIn()) {
            // The reference adds its own toolbar tokens here; the engine's bar is the Minn bar, named below.
            $classes[] = 'logged-in';
        }
        if ((int) ($this->site->option('site_logo') ?? 0) > 0) {
            $classes[] = 'wp-custom-logo';
        }
        $classes[] = 'wp-embed-responsive';
        array_push($classes, ...$paging);
        $parent = $this->theme->parentSlug();
        $classes[] = 'wp-theme-' . ($parent ?? $this->theme->slug);
        if ($parent !== null) {
            $classes[] = 'wp-child-theme-' . $this->theme->slug;
        }
        array_push($classes, ...(Extensions::runner()?->bodyClasses() ?? []));
        if ($this->bar !== null && !$resolution->preview) {
            $classes[] = 'minn-front-bar';
        }
        if (Runtime::booted()) {
            $filtered = Runtime::hooks()->filter('body_class', [$classes, []]);
            $classes = is_array($filtered) ? array_values(array_map('strval', $filtered)) : $classes;
        }
        return $classes;
    }

    /** The page for a resolution, or null when the theme has no template for it. */
    public function render(Resolution $resolution, array $coreClasses, string $title): ?string
    {
        $template = $this->templates->forResolution($resolution);
        if ($template === null) {
            return null;
        }
        $query = $this->bridge->stand($resolution);
        $takeover = $this->pluginTemplate();
        if ($takeover !== null) {
            return $takeover;
        }
        RenderState::current()->reset();
        $this->renderer->withContext(new Context($resolution, $query->posts, $query->total, $this->bridge->perPage(), true));
        // The reference texturizes the rendered template as a whole, after the
        // blocks: straight quotes in a theme's own markup curl, content that was
        // texturized on its way in is left alone.
        $body = Texturize::html($this->renderer->renderBlocks(Parser::parse($template['markup'])));
        [$body, $skipTarget] = self::skipLinkTarget($body);
        $bodyClass = implode(' ', $this->bodyClasses($resolution, $coreClasses));
        // The stylesheet comes after the body: it lists the containers and
        // variations that rendering discovered.
        $styles = new GlobalStyles($this->theme, $this->templates->userStyles());
        $title = $this->documentTitle($resolution, $title);
        $bar = $resolution->preview ? null : $this->bar;
        $document = '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n"
            . $this->head($resolution, $title, $styles, $bar)
            . '<body class="' . Html::attr($bodyClass) . '">' . "\n"
            . '<a class="skip-link screen-reader-text" id="wp-skip-link" href="#' . Html::attr($skipTarget) . '">Skip to content</a>'
            . '<div class="wp-site-blocks">' . $body . '</div>' . "\n"
            . (Extensions::runner()?->footer() ?? '')
            . (Runtime::booted() ? Runtime::capture('wp_footer') : '')
            . ($bar === null ? '' : $bar->render($resolution))
            . '</body>' . "\n" . '</html>' . "\n";
        return Extensions::runner()?->filterDocument($document) ?? $document;
    }

    /**
     * The reference filters template_include under block themes too (the
     * canvas is the incoming value); a plugin swapping in its own PHP file
     * takes the whole response over, the CaptainCore Manager shape.
     */
    private function pluginTemplate(): ?string
    {
        if (!Runtime::booted()) {
            return null;
        }
        $canvas = MINN_ENGINE_DIR . '/wp-api/template-canvas.php';
        $swapped = (string) \apply_filters('template_include', $canvas);
        if ($swapped === $canvas || $swapped === '' || !is_file($swapped)) {
            return null;
        }
        ob_start();
        \load_template($swapped, false);
        return (string) ob_get_clean();
    }

    /**
     * The skip link points at the first <main>: at its own id when the
     * template gave it one, otherwise at the id the reference injects.
     *
     * @return array{string, string} the body, the target id
     */
    private static function skipLinkTarget(string $body): array
    {
        if (preg_match('/<main\b[^>]*\sid="([^"]+)"/', $body, $m)) {
            return [$body, $m[1]];
        }
        return [(string) preg_replace('/<main(\s|>)/', '<main id="wp--skip-link--target"$1', $body, 1), 'wp--skip-link--target'];
    }

    /** The title after the extensions and, with the runtime up, the reference's document_title filters. */
    private function documentTitle(Resolution $resolution, string $title): string
    {
        $title = Extensions::runner()?->title($title) ?? $title;
        if (Runtime::booted()) {
            // Plugin code rewrites the title through the reference's filters; the engine's parts feed them.
            $parts = DocumentTitle::parts($resolution, (string) ($this->site->option('blogname') ?? ''), (string) ($this->site->option('blogdescription') ?? ''));
            if ($resolution->kind === Kind::PostTypeArchive) {
                // A plugin may rename its archive (WooCommerce titles the product archive after the shop page).
                $parts['title'] = (string) \apply_filters('post_type_archive_title', $parts['title'], (string) ($resolution->record['name'] ?? ''));
            }
            Runtime::current()->set('document_title_parts', $parts);
            $title = \_minn_document_title($parts);
        }
        return $title;
    }

    /**
     * The head: title, the discovery links, the engine's stylesheets (inside
     * wp_head when the runtime is up, where the reference prints a theme's,
     * after plugin styles), then the extension head (after the stylesheets
     * either way, as the seam contract says), the fonts, the bar's own.
     */
    private function head(Resolution $resolution, string $title, GlobalStyles $styles, ?AdminBar $bar): string
    {
        // The theme's own style.css is the theme's to enqueue from its
        // functions.php, which the runtime loads; the reference links it no other way.
        $stylesheets = $this->headLinks->engineStylesheet()
            . '<style id="global-styles-inline-css">' . "\n" . $styles->css() . "\n" . '</style>' . "\n";
        $runtimeHead = '';
        if (Runtime::booted()) {
            Runtime::current()->set('engine_head_styles', $stylesheets);
            $runtimeHead = Runtime::capture('wp_head');
            $stylesheets = '';
        }
        $fontFaces = $styles->fontFaces();
        return '<head>' . "\n"
            . '<meta charset="UTF-8" />' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
            . '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '</title>' . "\n"
            . $this->headLinks->all($resolution)
            . $stylesheets
            . $runtimeHead
            . (Extensions::runner()?->head() ?? '')
            . ($fontFaces === '' ? '' : '<style class="wp-fonts-local">' . "\n" . $fontFaces . '</style>' . "\n")
            . ($bar === null ? '' : $bar->head())
            . '</head>' . "\n";
    }
}
