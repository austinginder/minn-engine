<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\Blocks;
use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Db;
use Minn\Support\Html;
use Minn\Content\PasswordGate;

/**
 * The interim public theme: one clean template until the block-theme
 * reader lands. The body-class tokens are the contract (stylesheets and
 * crawlers key off them); the markup around them is engine-defined.
 */
final readonly class Renderer
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Permalinks $permalinks,
        private int $perPage,
    ) {
    }

    /** @return list<string> */
    public function bodyClasses(Resolution $resolution): array
    {
        $classes = match ($resolution->kind) {
            Kind::Home => $resolution->postsPage ? ['blog'] : ['home', 'blog'],
            Kind::Single => [
                'single',
                'single-' . (string) ($resolution->record['post_type'] ?? 'post'),
                'postid-' . $resolution->id(),
                ...(($resolution->record['post_type'] ?? 'post') === 'post' ? ['single-format-standard'] : []),
            ],
            Kind::Taxonomy => ['archive', 'tax-' . (string) $resolution->record['taxonomy'], 'term-' . (string) $resolution->record['slug'], 'term-' . $resolution->id()],
            Kind::PostTypeArchive => ['archive', 'post-type-archive', 'post-type-archive-' . (string) ($resolution->record['name'] ?? '')],
            Kind::Page => [...($resolution->front ? ['home'] : []), ...$this->pageClasses($resolution->record)],
            Kind::Category => ['archive', 'category', 'category-' . $resolution->record['slug'], 'category-' . $resolution->id()],
            Kind::Tag => ['archive', 'tag', 'tag-' . $resolution->record['slug'], 'tag-' . $resolution->id()],
            Kind::Author => array_merge(
                ['archive', 'author'],
                $resolution->record === null ? [] : ['author-' . $resolution->record['user_nicename'], 'author-' . $resolution->id()],
            ),
            Kind::Date => ['archive', 'date'],
            Kind::Search => ['search', 'search-results'],
            Kind::NotFound => ['error404'],
            Kind::Redirect => [],
        };
        if ($resolution->paged > 1) {
            $prefix = match ($resolution->kind) {
                Kind::Single => 'single-paged-',
                Kind::Page => 'page-paged-',
                default => null,
            };
            array_splice($classes, $resolution->front ? 1 : 0, 0, ['paged']);
            $classes[] = 'paged-' . $resolution->paged;
            if ($prefix !== null) {
                $classes[] = $prefix . $resolution->paged;
            }
        }
        return $classes;
    }

    /** The document title: the item's title with the site name, or the site name alone. */
    public function title(Resolution $resolution): string
    {
        return DocumentTitle::compose(DocumentTitle::parts($resolution, (string) ($this->db->option('blogname') ?? ''), (string) ($this->db->option('blogdescription') ?? '')));
    }

    public function render(Resolution $resolution): string
    {
        $site = $this->db->option('blogname') ?? '';
        [$title, $main] = match ($resolution->kind) {
            Kind::Single, Kind::Page => [$resolution->record['post_title'], $this->article($resolution->record)],
            Kind::NotFound => ['Page not found', '<h1>Page not found</h1><p>Nothing lives at this address.</p>'],
            default => $this->archive($resolution),
        };
        $heading = $title === '' ? $site : $title . ' – ' . $site;
        $classes = implode(' ', $this->bodyClasses($resolution));
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . Html::esc($heading) . '</title>'
            . '<style>' . self::CSS . '</style></head>'
            . '<body class="' . Html::attr($classes) . '">'
            . '<header class="site-header"><a class="site-title" href="' . Html::attr($this->permalinks->url('/')) . '">' . Html::esc($site) . '</a></header>'
            . '<main>' . $main . '</main>'
            . '<footer class="site-footer">Served by Minn Engine ' . Html::esc(MINN_ENGINE_VERSION) . '</footer>'
            . '</body></html>';
    }

    private function pageClasses(array $page): array
    {
        $classes = ['page', 'page-id-' . (int) $page['ID']];
        $hasChildren = (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'page' AND post_status = 'publish'",
            [(int) $page['ID']],
        );
        if ($hasChildren > 0) {
            $classes[] = 'page-parent';
        }
        if ((int) $page['post_parent'] > 0) {
            $classes[] = 'page-child';
            $classes[] = 'parent-pageid-' . (int) $page['post_parent'];
        }
        return $classes;
    }

    private function article(array $post): string
    {
        $body = PasswordGate::is($post)
            ? PasswordGate::form($post, $this->permalinks->url(''), $this->permalinks->forPost($post))
            : Blocks::render((string) $post['post_content']);
        return '<article class="entry"><h1 class="entry-title">' . Html::esc(PasswordGate::title($post)) . '</h1>'
            . '<div class="entry-content">' . $body . '</div></article>';
    }

    /** @return array{0: string, 1: string} title and markup */
    private function archive(Resolution $resolution): array
    {
        [$title, $filter] = match ($resolution->kind) {
            Kind::Home => ['', []],
            Kind::Category, Kind::Tag => [$resolution->record['name'], ['term' => (int) $resolution->record['term_taxonomy_id']]],
            Kind::Author => [
                $resolution->record['display_name'] ?? $resolution->authorName,
                ['author' => (int) ($resolution->record['ID'] ?? -1)],
            ],
            Kind::Date => [
                implode('/', array_filter($resolution->date, static fn ($v) => $v !== null)),
                array_combine(['from', 'to'], Resolver::dateRange(...$resolution->date) ?? ['1970-01-01', '1970-01-01']),
            ],
            Kind::Search => ['Search: ' . $resolution->search, ['search' => $resolution->search]],
            default => ['', []],
        };
        $page = $this->posts->archive($filter, $resolution->paged, $this->perPage);
        $items = '';
        foreach ($page['posts'] as $post) {
            $items .= '<li><a href="' . Html::attr($this->permalinks->forPost($post)) . '">' . Html::esc($post['post_title']) . '</a>'
                . '<time>' . Html::esc(substr((string) $post['post_date'], 0, 10)) . '</time>'
                . '<p>' . Excerpt::render($post) . '</p></li>';
        }
        $markup = ($title !== '' ? '<h1 class="archive-title">' . Html::esc($title) . '</h1>' : '')
            . ($items === '' ? '<p>No posts yet.</p>' : '<ul class="post-list">' . $items . '</ul>');
        return [$title, $markup];
    }

    private const CSS = <<<'CSS'
        :root { color-scheme: light dark; }
        body { margin: 0; font: 17px/1.6 "Hanken Grotesk", "Helvetica Neue", sans-serif; background: #fbfbfc; color: #1a1a1f; }
        @media (prefers-color-scheme: dark) { body { background: #0b0b0d; color: #ececed; } }
        .site-header, main, .site-footer { max-width: 680px; margin: 0 auto; padding: 0 24px; }
        .site-header { padding-top: 32px; }
        .site-title { font-weight: 800; letter-spacing: -0.02em; text-decoration: none; color: inherit; font-size: 20px; }
        main { padding-top: 40px; padding-bottom: 40px; }
        h1 { font-size: 36px; letter-spacing: -0.02em; line-height: 1.15; margin: 0 0 24px; }
        .post-list { list-style: none; margin: 0; padding: 0; }
        .post-list li { padding: 18px 0; border-top: 1px solid rgba(128,128,140,0.25); }
        .post-list a { font-weight: 600; color: inherit; text-decoration: none; font-size: 20px; }
        .post-list time { display: block; font-size: 13px; opacity: 0.6; }
        .post-list p { margin: 6px 0 0; opacity: 0.85; }
        .entry-content img { max-width: 100%; height: auto; }
        .site-footer { padding-bottom: 32px; font: 12px/1.6 "JetBrains Mono", monospace; opacity: 0.6; }
        CSS;
}
