<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Blocks\Dynamic\Archives;
use Minn\Blocks\Dynamic\LatestComments;
use Minn\Blocks\Dynamic\LatestPosts;
use Minn\Blocks\Dynamic\Search;
use Minn\Blocks\Dynamic\SocialLinks;
use Minn\Blocks\Dynamic\SyncedPattern;
use Minn\Blocks\Dynamic\TagCloud;
use Minn\Blocks\Dynamic\Theme\Comments;
use Minn\Blocks\Dynamic\Theme\PostBlocks;
use Minn\Blocks\Dynamic\Theme\QueryBlocks;
use Minn\Blocks\Dynamic\Theme\Structure;
use Minn\Blocks\Dynamic\Theme\TermBlocks;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Content\Users;
use Minn\Content\Texturize;
use Minn\Db;
use Minn\Extension\Extensions;
use Minn\Front\Permalinks;
use Minn\Media\Uploads;
use Minn\Runtime\BlockFilters;
use Minn\Runtime\Runtime;
use Minn\Support\Html;
use Minn\Theme\Templates;
use Minn\Theme\Theme;

/**
 * Renders a block tree the way the reference renders post_content:
 * delimiters gone, each block's own HTML kept with inner blocks rendered
 * in place, then the per-block render-time additions (layout classes, the
 * paragraph class, image attributes, gallery ids, style-variation
 * counters), and finally texturize over the whole.
 *
 * @phpstan-type DynamicRenderer callable(Block, Renderer): string
 */
final class Renderer
{
    /**
     * Style variations that carry a numbered companion class at render.
     * These come from the active theme's registered block styles; the set
     * mirrors the reference's theme until the engine reads theme data.
     */
    private const NUMBERED_STYLES = [
        'core/separator' => ['wide'],
        'core/button' => ['outline'],
        'core/post-terms' => ['post-terms-1'],
    ];

    /** The numbered companion of a registered style variation, consuming a counter; null when none applies. */
    public static function numberedStyle(string $blockName, string $className): ?string
    {
        foreach (self::NUMBERED_STYLES[$blockName] ?? [] as $style) {
            if (preg_match('/\bis-style-' . preg_quote($style, '/') . '\b/', $className)) {
                $instance = RenderState::current()->nextId();
                RenderState::current()->recordVariation($blockName, $style, $instance);
                return "is-style-{$style}--" . $instance;
            }
        }
        return null;
    }

    /** @var array<string, callable> what renders each dynamic block: the engine's own, or a plugin's callback that took it over */
    private array $dynamic = [];
    /** @var array<string, callable> the engine's own renderers, kept when a plugin's callback takes a block over */
    private array $native = [];
    /** @var list<array<string, mixed>> context enclosing blocks provide to those inside */
    private array $provided = [];
    private Context $context;
    private RenderState $state;

    public function __construct(private readonly ImageTags $images)
    {
        $this->state = RenderState::current();
        $this->state->adopt();
        $this->context = Context::forRest();
    }

    /** The per-request rendering state: counters, containers, variations, the images seen. */
    public function state(): RenderState
    {
        return $this->state;
    }

    /** What is being rendered. */
    public function context(): Context
    {
        return $this->context;
    }

    /** Sets what is being rendered. */
    public function withContext(Context $context): void
    {
        $this->context = $context;
    }

    /** The image tag builder. */
    public function images(): ImageTags
    {
        return $this->images;
    }

    /** A renderer with every dynamic block wired over the database door. */
    public static function forDb(Db $db): self
    {
        $site = new Site($db);
        $permalinks = Permalinks::fromDb($db);
        $uploads = new Uploads($site, $permalinks, ABSPATH . 'wp-content/uploads');
        $posts = new Posts($db);
        $renderer = null;
        $images = new ImageTags($posts, $uploads, static function () use (&$renderer): bool {
            return $renderer?->context()->front ?? false;
        });
        $renderer = new self($images);
        $theme = Theme::active($site, $permalinks, ABSPATH . 'wp-content/themes');
        $renderer->state()->useRootPadding((bool) ($theme?->json()['settings']['useRootPaddingAwareAlignments'] ?? false));
        $renderer->registerDynamic('core/latest-posts', (new LatestPosts($db, $site, $permalinks))->render(...));
        $renderer->registerDynamic('core/archives', (new Archives($db, $permalinks))->render(...));
        $renderer->registerDynamic('core/search', (new Search($permalinks))->render(...));
        $renderer->registerDynamic('core/tag-cloud', (new TagCloud($db, $permalinks))->render(...));
        $renderer->registerDynamic('core/latest-comments', (new LatestComments($db, $site, $posts, $permalinks))->render(...));
        $renderer->registerDynamic('core/block', (new SyncedPattern($db))->render(...));
        // Every core block renders wherever block content does, not only
        // inside a theme's templates: wp/v2/navigation serves a menu's
        // rendered markup, a post may hold a navigation block of its own, and
        // a plugin calls render_block() or a core block's render callback
        // from a shortcode or a REST route (probe core-blocks).
        (new Dynamic\Theme\Navigation($db, $posts, $permalinks))->register($renderer);
        (new SocialLinks(MINN_ENGINE_DIR . '/data/social-icons.json'))->register($renderer);
        $users = new Users($db);
        (new PostBlocks($posts, $users, $site, $permalinks))->register($renderer);
        (new QueryBlocks($posts, $site, $permalinks))->register($renderer);
        (new TermBlocks(new Terms($db), $permalinks))->register($renderer);
        (new Comments($site, $permalinks, $posts, $users))->register($renderer);
        if ($theme !== null) {
            (new Structure($theme, new Templates($db, $posts, $theme, Runtime::blockTemplates()), $site, $permalinks))->register($renderer);
        }
        // The core blocks the facade renders, from the WordPress functions they are made of (get_avatar, comments_open, the login form).
        if (Runtime::booted()) {
            Runtime::hooks()->action('minn_block_renderers', [$renderer]);
        }
        return $renderer;
    }

    /**
     * Registers the engine's own renderer for a dynamic block. A plugin's
     * callback that already took the block over (bridge()) keeps it, as a
     * replaced render callback does on the reference.
     *
     * @param callable(Block, Renderer): string $render
     */
    public function registerDynamic(string $name, callable $render): void
    {
        $bridged = isset($this->dynamic[$name]) && $this->dynamic[$name] !== ($this->native[$name] ?? null);
        $this->native[$name] = $render;
        if (!$bridged) {
            $this->dynamic[$name] = $render;
        }
    }

    /**
     * Hands a block to a plugin's render callback; the engine's own renderer
     * for it stays for renderNative().
     *
     * @param callable(Block, Renderer): string $render
     */
    public function bridge(string $name, callable $render): void
    {
        $this->dynamic[$name] = $render;
    }

    /**
     * One block as HTML through the engine's own renderer for its name (its
     * static markup when it has none), even where a plugin's callback took
     * the block over: what a core render callback gives, so a plugin's
     * callback that calls the one it replaced does not call itself.
     */
    public function renderNative(Block $block): string
    {
        $name = (string) $block->name;
        $taken = $this->dynamic[$name] ?? null;
        $own = $this->native[$name] ?? null;
        if ($taken === $own) {
            return $this->renderBlock($block);
        }
        $this->renderWith($name, $own);
        try {
            return $this->renderBlock($block);
        } finally {
            $this->renderWith($name, $taken);
        }
    }

    private function renderWith(string $name, ?callable $render): void
    {
        if ($render === null) {
            unset($this->dynamic[$name]);
        } else {
            $this->dynamic[$name] = $render;
        }
    }

    /** Block markup as HTML, texturized. */
    public function render(string $markup): string
    {
        return Texturize::html($this->renderBlocks(Parser::parse($markup)));
    }

    /**
     * A tree of blocks as HTML.
     *
     * @param list<Block> $blocks
     */
    public function renderBlocks(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $out .= $this->renderBlock($block);
        }
        return $out;
    }

    /** One block as HTML, with the filters around it. */
    public function renderBlock(Block $block): string
    {
        if ($block->name === null) {
            return $block->innerHtml;
        }
        if (!$this->state->descend()) {
            return '';
        }
        $seams = Extensions::runner();
        if ($seams !== null && !$seams->allowsBlock($block)) {
            $this->state->ascend();
            return '';
        }
        try {
            $filtered = BlockFilters::active();
            if ($filtered) {
                $before = BlockFilters::before($block);
                if (is_string($before)) {
                    return $before;
                }
                $block = $before;
            }
            $bound = Bindings::values($block, $this->blockContext());
            $html = $this->renderNamed($bound === [] ? $block : $block->withAttrs(array_merge($block->attrs, $bound)), $bound);
            if ($html !== '') {
                $this->state->recordBlock($block->name);
            }
            $html = $seams === null ? $html : $seams->filterBlock($block, $html);
            if (!$filtered) {
                return $html;
            }
            $after = BlockFilters::after($block, $html);
            $lost = substr_count($html, '<img') - substr_count($after, '<img');
            if ($lost > 0) {
                $this->state->refundImages($lost, str_contains($html, 'fetchpriority="high"') && !str_contains($after, 'fetchpriority="high"'));
            }
            return $after;
        } finally {
            $this->state->ascend();
        }
    }

    /**
     * The context a block's bindings read: the post being rendered (postId,
     * postType) and what enclosing blocks provide (a synced pattern's
     * overrides).
     *
     * @return array<string, mixed>
     */
    public function blockContext(): array
    {
        $post = $this->context->post();
        return array_merge($post === null ? [] : ['postId' => $post->id, 'postType' => $post->type], ...$this->provided);
    }

    /** Renders with context provided to the blocks inside (render_block_context's job on the reference). @param array<string, mixed> $context */
    public function providing(array $context, \Closure $render): string
    {
        $this->provided[] = $context;
        try {
            return (string) $render();
        } finally {
            array_pop($this->provided);
        }
    }

    /** @param array<string, mixed> $bound the bound attributes' values, put into a static block's HTML */
    private function renderNamed(Block $block, array $bound = []): string
    {
        if (isset($this->dynamic[$block->name])) {
            // The element class is numbered before the block renders (the
            // reference counts it even for a block that renders nothing).
            $outer = $this->state->setPendingElements(Elements::className($block->attrs, $block->name));
            $out = ($this->dynamic[$block->name])($block, $this);
            $this->state->setPendingElements($outer);
            // A plugin's block gets the content image treatment the reference applies to the_content.
            return str_starts_with($block->name, 'core/') ? $out : $this->images->enrichPlugin($out);
        }
        // A parent's element styles number before its children's.
        $elements = Elements::className($block->attrs, $block->name);
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $this->renderBlock($block->innerBlocks[$inner++]);
        }
        return $this->decorate($block, $bound === [] ? $out : Bindings::html($out, (string) $block->name, $bound), $elements);
    }

    private function decorate(Block $block, string $html, ?string $elements): string
    {
        $slug = str_starts_with($block->name, 'core/') ? substr($block->name, 5) : str_replace('/', '-', $block->name);
        if ($elements !== null) {
            $html = Html::addClasses($html, [$elements]);
        }
        $html = match ($block->name) {
            'core/paragraph' => Html::addClasses($html, ['wp-block-paragraph']),
            // Saved without their class (older content), these get it as they render.
            'core/heading' => Html::addClasses($html, ['wp-block-heading']),
            'core/list' => Html::addClasses($html, ['wp-block-list']),
            'core/group' => Html::addClasses($html, Layout::classes('group', $block->attrs)),
            'core/columns' => Html::addClasses($html, Layout::classes('columns', $block->attrs, 'flex')),
            'core/column', 'core/quote', 'core/details' => Html::addClasses($html, ['is-layout-flow', "wp-block-{$slug}-is-layout-flow"]),
            'core/buttons' => Html::addClasses($html, self::flexWithoutContainer('buttons', $block->attrs)),
            'core/gallery' => $this->images->enrichGallery(Html::addClasses($html, ['wp-block-gallery-' . self::gallery(), 'is-layout-flex', 'wp-block-gallery-is-layout-flex'])),
            'core/cover' => Html::addClasses(
                $this->images->enrich($html),
                ['has-global-padding', 'is-layout-constrained', 'wp-block-cover-is-layout-constrained'],
                'wp-block-cover__inner-container',
            ),
            'core/image', 'core/media-text' => $this->images->enrich($html),
            // Third-party blocks pass through as stored; their images still
            // count toward the page's loading rules, as the reference's
            // content filter sees them.
            default => str_starts_with($block->name, 'core/') ? $html : $this->images->enrich($html),
        };
        $numbered = self::numberedStyle($block->name, $block->className());
        return $numbered === null ? $html : Html::addClasses($html, [$numbered]);
    }

    private static function gallery(): int
    {
        $instance = RenderState::current()->nextId();
        RenderState::current()->recordGallery($instance);
        return $instance;
    }

    /** Buttons are flex containers without a stylesheet of their own by default. */
    private static function flexWithoutContainer(string $slug, array $attrs): array
    {
        return array_values(array_filter(
            Layout::classes($slug, $attrs, 'flex'),
            static fn (string $class) => !str_starts_with($class, 'wp-container-'),
        ));
    }
}
