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
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Front\Resolution;
use Minn\Front\Resolver;
use Minn\Support\Html;
use Minn\Support\Serialized;
use Minn\Content\Reader;
use Minn\Extension\Extensions;

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
    ) {
    }

    public static function create(Db $db, Theme $theme, Permalinks $permalinks, int $perPage): self
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
        return new self($db, $site, $posts, $permalinks, $theme, $templates, $renderer, $perPage);
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
            // The reference also adds admin-bar tokens here; the engine has no admin bar.
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
        return $classes;
    }

    public function render(Resolution $resolution, array $coreClasses, string $title): ?string
    {
        $template = $this->templates->forResolution($resolution);
        if ($template === null) {
            return null;
        }
        RenderState::reset();
        $query = $this->mainQuery($resolution);
        $this->renderer->withContext(new Context($resolution, $query['posts'], $query['total'], $this->perPage, true));
        $body = $this->renderer->renderBlocks(Parser::parse($template['markup']));
        $body = preg_replace('/<main(\s|>)/', '<main id="wp--skip-link--target"$1', $body, 1);
        $bodyClass = implode(' ', $this->bodyClasses($resolution, $coreClasses));
        // The stylesheet comes after the body: it lists the containers and
        // variations that rendering discovered.
        $globalStyles = (new GlobalStyles($this->theme, $this->templates->userStyles()))->css();
        $themeStyle = $this->theme->styleUri();

        $document = '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n" . '<head>' . "\n"
            . '<meta charset="UTF-8" />' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
            . '<title>' . Html::esc($title) . '</title>' . "\n"
            . '<link rel="stylesheet" id="minn-blocks-css" href="' . Html::attr($this->permalinks->url('/minn-engine/blocks.css')) . '" />' . "\n"
            . '<style id="global-styles-inline-css">' . "\n" . $globalStyles . "\n" . '</style>' . "\n"
            . ($themeStyle === null ? '' : '<link rel="stylesheet" id="' . Html::attr($this->theme->slug) . '-style-css" href="' . Html::attr($themeStyle) . '" />' . "\n")
            . (Extensions::seams()?->renderHead() ?? '')
            . '</head>' . "\n"
            . '<body class="' . Html::attr($bodyClass) . '">' . "\n"
            . '<a class="skip-link screen-reader-text" id="wp-skip-link" href="#wp--skip-link--target">Skip to content</a>'
            . '<div class="wp-site-blocks">' . $body . '</div>' . "\n"
            . (Extensions::seams()?->renderFooter() ?? '')
            . '</body>' . "\n" . '</html>' . "\n";
        return Extensions::seams()?->applyDocumentFilters($document) ?? $document;
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
