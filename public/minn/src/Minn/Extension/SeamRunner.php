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

    public function allowsBlock(Block $block): bool
    {
        foreach ($this->registered->blockGates as $gate) {
            if ($gate($block, $this->seams) === false) {
                return false;
            }
        }
        return true;
    }

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

    public function title(string $title): string
    {
        $filter = $this->registered->title;
        return $filter === null ? $title : $filter($title);
    }

    public function head(): string
    {
        return implode('', array_map(fn (Closure $render) => $render($this->seams), $this->registered->head));
    }

    public function footer(): string
    {
        return implode('', array_map(fn (Closure $render) => $render($this->seams), $this->registered->footer));
    }

    /** @return list<string> */
    public function bodyClasses(): array
    {
        return $this->registered->bodyClasses;
    }

    public function filterDocument(string $html): string
    {
        foreach ($this->registered->documentFilters as $filter) {
            $html = $filter($html);
        }
        return $html;
    }
}
