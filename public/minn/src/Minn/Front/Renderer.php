<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Content\Blocks;
use Minn\Content\Page;
use Minn\Content\Excerpt;
use Minn\Db;
use Minn\Support\Html;
use Minn\Theme\QueryClasses;
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
        private Permalinks $permalinks,
    ) {
    }

    /**
     * The body classes the main query stands for (Theme\QueryClasses);
     * none before one stands.
     *
     * @return list<string>
     */
    public function bodyClasses(): array
    {
        $query = $GLOBALS['wp_query'] ?? null;
        return $query instanceof \WP_Query ? (new QueryClasses($this->db))->of($query) : [];
    }

    /** The interim page for a resolution, without a theme: a listing shows the main query's page of posts. */
    public function render(Resolution $resolution, Page $page): string
    {
        $site = $this->db->option('blogname') ?? '';
        [$title, $main] = match ($resolution->kind) {
            Kind::Single, Kind::Page => [$resolution->record['post_title'], $this->article($resolution->record)],
            Kind::NotFound => ['Page not found', '<h1>Page not found</h1><p>Nothing lives at this address.</p>'],
            default => $this->archive($resolution, $page),
        };
        $heading = $title === '' ? $site : $title . ' – ' . $site;
        $classes = implode(' ', $this->bodyClasses());
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

    private function article(PostRecord $post): string
    {
        $body = PasswordGate::is($post)
            ? PasswordGate::form($post, $this->permalinks->url(''), $this->permalinks->forPost($post))
            : Blocks::render($post->content);
        return '<article class="entry"><h1 class="entry-title">' . Html::esc(PasswordGate::title($post)) . '</h1>'
            . '<div class="entry-content">' . $body . '</div></article>';
    }

    /** @return array{0: string, 1: string} title and markup */
    private function archive(Resolution $resolution, Page $page): array
    {
        $title = match ($resolution->kind) {
            Kind::Category, Kind::Tag => (string) $resolution->record['name'],
            Kind::Author => (string) ($resolution->record['display_name'] ?? $resolution->authorName),
            Kind::Date => implode('/', array_filter($resolution->date, static fn ($v) => $v !== null)),
            Kind::Search => 'Search: ' . $resolution->search,
            default => '',
        };
        $items = '';
        foreach ($page->posts as $post) {
            $items .= '<li><a href="' . Html::attr($this->permalinks->forPost($post)) . '">' . Html::esc($post->title) . '</a>'
                . '<time>' . Html::esc(substr($post->date, 0, 10)) . '</time>'
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
