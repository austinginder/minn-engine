<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Content\PostRecord;
use Closure;
use Minn\Blocks\Block;

/**
 * The engine's side of the seams: what the loader collected, called at the
 * right moment and in registration order. An extension never sees this
 * class; it sees Seams, which is only the eight registrations.
 */
final readonly class SeamRunner
{
    public function __construct(private Seams $seams, private Registrations $registered)
    {
    }

    /** Whether every block gate lets a block render. */
    public function allowsBlock(Block $block): bool
    {
        foreach ($this->registered->blockGates as $gate) {
            if ($gate($block, $this->seams) === false) {
                return false;
            }
        }
        return true;
    }

    /** A block's HTML through every block filter. */
    public function filterBlock(Block $block, string $html): string
    {
        foreach ($this->registered->blockFilters as $filter) {
            $html = $filter($block, $html, $this->seams);
        }
        return $html;
    }

    /**
     * Shortcodes, then the content filters, over rendered post content. An
     * extension's filter is promised the post as a row (contracts/extensions.md),
     * so a record is handed over as one.
     */
    public function filterContent(string $html, PostRecord $post): string
    {
        $row = $post->row();
        $html = Shortcodes::apply($html, $this->registered->shortcodes, $this->seams);
        foreach ($this->registered->contentFilters as $filter) {
            $html = $filter($html, $row);
        }
        return $html;
    }

    /** The document title through the title filter. */
    public function title(string $title): string
    {
        $filter = $this->registered->title;
        return $filter === null ? $title : $filter($title);
    }

    /** What the extensions add to the head. */
    public function head(): string
    {
        return implode('', array_map(fn (Closure $render) => $render($this->seams), $this->registered->head));
    }

    /** What the extensions add to the footer. */
    public function footer(): string
    {
        return implode('', array_map(fn (Closure $render) => $render($this->seams), $this->registered->footer));
    }

    /**
     * The body classes the extensions add.
     *
     * @return list<string>
     */
    public function bodyClasses(): array
    {
        return $this->registered->bodyClasses;
    }

    /** The whole document through every document filter. */
    public function filterDocument(string $html): string
    {
        foreach ($this->registered->documentFilters as $filter) {
            $html = $filter($html);
        }
        return $html;
    }
}
