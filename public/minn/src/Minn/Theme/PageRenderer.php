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
        private Db $db,
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private Theme $theme,
        private Templates $templates,
        private Renderer $renderer,
        private int $perPage,
        private ?AdminBar $bar = null,
    ) {
    }

    public static function create(Db $db, Theme $theme, Permalinks $permalinks, int $perPage, ?AdminBar $bar = null): self
    {
        $site = new Site($db);
        $posts = new Posts($db);
        $users = new Users($db);
        $templates = new Templates($db, $posts, $theme);
        $renderer = Blocks::renderer();
        (new Structure($theme, $templates, $site, $permalinks))->register($renderer);
        (new PostBlocks($posts, $users, $site, $permalinks))->register($renderer);
        (new QueryBlocks($posts, $site, $permalinks))->register($renderer);
        (new Navigation($db, $posts, $permalinks))->register($renderer);
        (new Comments($db, new CommentStore($db), $site, $permalinks))->register($renderer);
        return new self($db, $site, $posts, $permalinks, $theme, $templates, $renderer, $perPage, $bar);
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
            array_splice($classes, $at, 0, ['wp-singular', 'post-template-default']);
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
        array_push($classes, ...(Extensions::seams()?->bodyClasses() ?? []));
        if ($this->bar !== null && !$resolution->preview) {
            $classes[] = 'minn-front-bar';
        }
        if (Runtime::booted()) {
            $filtered = Runtime::hooks()->filter('body_class', [$classes, []]);
            $classes = is_array($filtered) ? array_values(array_map('strval', $filtered)) : $classes;
        }
        return $classes;
    }

    public function render(Resolution $resolution, array $coreClasses, string $title): ?string
    {
        $template = $this->templates->forResolution($resolution);
        if ($template === null) {
            return null;
        }
        $query = $this->mainQuery($resolution);
        if (Runtime::booted()) {
            \_minn_seed_main_query(MainQuery::vars($resolution), array_map(static fn (array $p) => (int) $p['ID'], $query['posts']), $query['total'], $this->perPage, $resolution->postsPage);
            Runtime::hooks()->action('template_redirect', []);
        }
        RenderState::reset();
        $this->renderer->withContext(new Context($resolution, $query['posts'], $query['total'], $this->perPage, true));
        // The reference texturizes the rendered template as a whole, after the
        // blocks: straight quotes in a theme's own markup curl, content that was
        // texturized on its way in is left alone.
        $body = Texturize::html($this->renderer->renderBlocks(Parser::parse($template['markup'])));
        // The skip link points at the first <main>: at its own id when the
        // template gave it one, otherwise at the id the reference injects.
        $skipTarget = 'wp--skip-link--target';
        if (preg_match('/<main\b[^>]*\sid="([^"]+)"/', $body, $m)) {
            $skipTarget = $m[1];
        } else {
            $body = preg_replace('/<main(\s|>)/', '<main id="wp--skip-link--target"$1', $body, 1);
        }
        $bodyClass = implode(' ', $this->bodyClasses($resolution, $coreClasses));
        // The stylesheet comes after the body: it lists the containers and
        // variations that rendering discovered.
        $styles = new GlobalStyles($this->theme, $this->templates->userStyles());
        $globalStyles = $styles->css();
        $fontFaces = $styles->fontFaces();
        $themeStyle = $this->theme->styleUri();

        $title = Extensions::seams()?->applyTitle($title) ?? $title;
        if (Runtime::booted()) {
            // Plugin code rewrites the title through the reference's filters; the engine's parts feed them.
            Runtime::current()->set('document_title_parts', DocumentTitle::parts($resolution, (string) ($this->site->option('blogname') ?? ''), (string) ($this->site->option('blogdescription') ?? '')));
            $title = \_minn_document_title(DocumentTitle::parts($resolution, (string) ($this->site->option('blogname') ?? ''), (string) ($this->site->option('blogdescription') ?? '')));
        }
        $bar = $resolution->preview ? null : $this->bar;
        $stylesheets = '<link rel="stylesheet" id="minn-blocks-css" href="' . Html::attr($this->permalinks->url('/minn-engine/blocks.css')) . '" />' . "\n"
            . '<style id="global-styles-inline-css">' . "\n" . $globalStyles . "\n" . '</style>' . "\n"
            . ($themeStyle === null ? '' : '<link rel="stylesheet" id="' . Html::attr($this->theme->slug) . '-style-css" href="' . Html::attr($themeStyle) . '" />' . "\n");
        // With the runtime up, the engine's stylesheets print where the
        // reference prints a theme's: inside wp_head, after plugin styles.
        $runtimeHead = '';
        if (Runtime::booted()) {
            Runtime::current()->set('engine_head_styles', $stylesheets);
            $runtimeHead = Runtime::capture('wp_head');
            $stylesheets = '';
        }
        $document = '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n" . '<head>' . "\n"
            . '<meta charset="UTF-8" />' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
            . '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '</title>' . "\n"
            . $this->headLinks($resolution)
            . $stylesheets
            . (Extensions::seams()?->renderHead() ?? '')
            . $runtimeHead
            . ($fontFaces === '' ? '' : '<style class="wp-fonts-local">' . "\n" . $fontFaces . '</style>' . "\n")
            . ($bar === null ? '' : $bar->head())
            . '</head>' . "\n"
            . '<body class="' . Html::attr($bodyClass) . '">' . "\n"
            . '<a class="skip-link screen-reader-text" id="wp-skip-link" href="#' . Html::attr($skipTarget) . '">Skip to content</a>'
            . '<div class="wp-site-blocks">' . $body . '</div>' . "\n"
            . (Extensions::seams()?->renderFooter() ?? '')
            . (Runtime::booted() ? Runtime::capture('wp_footer') : '')
            . ($bar === null ? '' : $bar->render($resolution))
            . '</body>' . "\n" . '</html>' . "\n";
        return Extensions::seams()?->applyDocumentFilters($document) ?? $document;
    }

    /**
     * The links the reference puts in every head: the site and comments
     * feeds (plus the archive's own feed), the REST discovery link, the
     * JSON alternate for the queried object, and the site icon set.
     */
    private function headLinks(Resolution $resolution): string
    {
        $site = Html::esc((string) ($this->site->option('blogname') ?? ''));
        $feed = fn (string $path): string => Html::attr($this->permalinks->url($path));
        $out = '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; Feed" href="' . $feed('/feed/') . '" />' . "\n"
            . '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; Comments Feed" href="' . $feed('/comments/feed/') . '" />' . "\n";
        $record = $resolution->record ?? [];
        $json = null;
        switch ($resolution->kind) {
            case Kind::Category:
            case Kind::Tag:
                $label = $resolution->kind === Kind::Category ? 'Category' : 'Tag';
                $out .= '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; ' . Html::esc((string) $record['name']) . ' ' . $label . ' Feed" href="' . Html::attr($this->permalinks->forTerm($record) . 'feed/') . '" />' . "\n";
                $json = '/wp/v2/' . ($resolution->kind === Kind::Category ? 'categories' : 'tags') . '/' . (int) $record['term_id'];
                break;
            case Kind::Search:
                $out .= '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; Search Results for &#8220;' . Html::esc((string) $resolution->search) . '&#8221; Feed" href="' . $feed('/search/' . rawurlencode((string) $resolution->search) . '/feed/rss2/') . '" />' . "\n";
                break;
            case Kind::Single:
            case Kind::Page:
                // The static front page announces its own comments feed while comments or pings are
                // open on it, even with none yet; every other single needs a comment first.
                $open = ($record['comment_status'] ?? '') === 'open' || ($record['ping_status'] ?? '') === 'open';
                if ((int) ($record['comment_count'] ?? 0) > 0 || ($resolution->front && $open)) {
                    $own = $record['post_type'] === 'page' ? $this->permalinks->pagePath($record) : $this->permalinks->forPost($record);
                    $out .= '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; ' . Html::esc((string) $record['post_title']) . ' Comments Feed" href="' . Html::attr($own . 'feed/') . '" />' . "\n";
                }
                $json = '/wp/v2/' . ($record['post_type'] === 'page' ? 'pages' : 'posts') . '/' . (int) $record['ID'];
                break;
        }
        $out .= '<link rel="https://api.w.org/" href="' . $feed('/wp-json/') . '" />' . "\n";
        if ($json !== null) {
            $out .= '<link rel="alternate" title="JSON" type="application/json" href="' . $feed('/wp-json' . $json) . '" />' . "\n";
        }
        $icon = (int) ($this->site->option('site_icon') ?? 0);
        $iconFile = $icon > 0 ? $this->posts->meta($icon, '_wp_attached_file') : null;
        if ($iconFile !== null) {
            $url = Html::attr($this->permalinks->url('/wp-content/uploads/' . $iconFile));
            $out .= '<link rel="icon" href="' . $url . '" sizes="32x32" />' . "\n"
                . '<link rel="icon" href="' . $url . '" sizes="192x192" />' . "\n"
                . '<link rel="apple-touch-icon" href="' . $url . '" />' . "\n"
                . '<meta name="msapplication-TileImage" content="' . $url . '" />' . "\n";
        }
        return $out;
    }

    /** @return array{posts: list<array>, total: int} */
    private function mainQuery(Resolution $resolution): array
    {
        $record = $resolution->record ?? [];
        $filter = match ($resolution->kind) {
            Kind::Category, Kind::Tag => ['term' => (int) $record['term_taxonomy_id']],
            Kind::Author => ['author' => (int) ($record['ID'] ?? -1)],
            Kind::Date => array_combine(['from', 'to'], Resolver::dateRange(...$resolution->date) ?? ['1970-01-01', '1970-01-01']),
            Kind::Search => ['search' => (string) $resolution->search],
            Kind::Home => [],
            default => null,
        };
        if ($filter === null) {
            return ['posts' => [], 'total' => 0];
        }
        $sticky = $resolution->kind === Kind::Home ? Serialized::intList($this->site->option('sticky_posts')) : [];
        return $this->posts->listing($filter, $resolution->paged, $this->perPage, $sticky);
    }
}
