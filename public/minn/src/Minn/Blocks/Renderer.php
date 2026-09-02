<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Blocks\Dynamic\Archives;
use Minn\Blocks\Dynamic\Categories;
use Minn\Blocks\Dynamic\LatestComments;
use Minn\Blocks\Dynamic\LatestPosts;
use Minn\Blocks\Dynamic\Search;
use Minn\Blocks\Dynamic\SocialLinks;
use Minn\Blocks\Dynamic\SyncedPattern;
use Minn\Blocks\Dynamic\TagCloud;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Db;
use Minn\Extension\Extensions;
use Minn\Front\Permalinks;
use Minn\Media\Uploads;
use Minn\Runtime\BlockFilters;
use Minn\Support\Html;
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

    /** @var array<string, callable> */
    private array $dynamic = [];
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
        Layout::rootPaddingAware((bool) ($theme?->json()['settings']['useRootPaddingAwareAlignments'] ?? false));
        $renderer->registerDynamic('core/latest-posts', (new LatestPosts($db, $site, $permalinks))->render(...));
        $renderer->registerDynamic('core/categories', (new Categories($db, $permalinks))->render(...));
        $renderer->registerDynamic('core/archives', (new Archives($db, $permalinks))->render(...));
        $renderer->registerDynamic('core/search', (new Search($permalinks))->render(...));
        $renderer->registerDynamic('core/tag-cloud', (new TagCloud($db, $permalinks))->render(...));
        $renderer->registerDynamic('core/latest-comments', (new LatestComments($db, $site, $posts, $permalinks))->render(...));
        $renderer->registerDynamic('core/block', (new SyncedPattern($db))->render(...));
        // Navigation renders wherever block content does, not only inside a
        // theme's templates: wp/v2/navigation serves a menu's rendered
        // markup, and a post may hold a navigation block of its own.
        (new Dynamic\Theme\Navigation($db, $posts, $permalinks))->register($renderer);
        (new SocialLinks(MINN_ENGINE_DIR . '/data/social-icons.json'))->register($renderer);
        return $renderer;
    }

    /**
     * Registers a dynamic block's render callback.
     *
     * @param callable(Block, Renderer): string $render
     */
    public function registerDynamic(string $name, callable $render): void
    {
        $this->dynamic[$name] = $render;
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
            $html = $this->renderNamed($block);
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

    private function renderNamed(Block $block): string
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
        return $this->decorate($block, $out, $elements);
    }

    private function decorate(Block $block, string $html, ?string $elements): string
    {
        $slug = str_starts_with($block->name, 'core/') ? substr($block->name, 5) : str_replace('/', '-', $block->name);
        if ($elements !== null) {
            $html = Html::addClasses($html, [$elements]);
        }
        $html = match ($block->name) {
            'core/paragraph' => Html::addClasses($html, ['wp-block-paragraph']),
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
