<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Blocks\Block;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Blocks\Wrapper;
use Minn\Content\TermRecord;
use Minn\Content\Terms;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Support\Html;

/**
 * term-name, term-count and term-description: the term a block's context
 * names (termId and taxonomy, as a terms query provides them), else the
 * term archive being viewed; nothing for neither (probe core-blocks).
 */
final readonly class TermBlocks
{
    private const BRACKETS = ['round' => ['(', ')'], 'square' => ['[', ']'], 'curly' => ['{', '}'], 'angle' => ['<', '>'], 'none' => ['', '']];

    public function __construct(
        private Terms $terms,
        private Permalinks $permalinks,
    ) {
    }

    /** Registers this family's blocks with the renderer. */
    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/term-name', $this->name(...));
        $renderer->registerDynamic('core/term-count', $this->count(...));
        $renderer->registerDynamic('core/term-description', $this->description(...));
    }

    /** The name, as a paragraph or the heading level asked for, linked to its archive when asked. */
    private function name(Block $block, Renderer $renderer): string
    {
        [$term] = $this->term($renderer);
        if ($term === null) {
            return '';
        }
        $level = (int) $block->attr('level', 0);
        $tag = $level === 0 ? 'p' : 'h' . min(6, max(1, $level));
        $name = Html::esc((string) $term['name']);
        if ((bool) $block->attr('isLink', false)) {
            $name = '<a href="' . Html::attr($this->permalinks->forTerm($term)) . '">' . $name . '</a>';
        }
        return Wrapper::open($tag, 'wp-block-term-name', $block) . $name . '</' . $tag . '>';
    }

    /** How many posts the term holds, in the brackets asked for (round by default). */
    private function count(Block $block, Renderer $renderer): string
    {
        [$term] = $this->term($renderer);
        if ($term === null) {
            return '';
        }
        [$open, $close] = self::BRACKETS[(string) $block->attr('bracketType', 'round')] ?? self::BRACKETS['round'];
        return Wrapper::open('div', 'wp-block-term-count', $block) . $open . (int) $term['count'] . $close . '</div>';
    }

    /** The description as written for a term in context; a term archive's as a paragraph, the way term_description gives it. */
    private function description(Block $block, Renderer $renderer): string
    {
        [$term, $fromContext] = $this->term($renderer);
        $description = trim((string) ($term['description'] ?? ''));
        if ($description === '') {
            return '';
        }
        $classes = implode(' ', ['wp-block-term-description', ...Styles::classes($block->attrs)]);
        return '<div class="' . Html::attr($classes) . '">' . ($fromContext ? $description : '<p>' . Html::esc($description) . '</p>') . '</div>';
    }

    /** @return array{?TermRecord, bool} the term, and whether the block's context named it */
    private function term(Renderer $renderer): array
    {
        $provided = $renderer->blockContext();
        if (!empty($provided['termId']) && !empty($provided['taxonomy'])) {
            return [$this->terms->find((string) $provided['taxonomy'], (int) $provided['termId']), true];
        }
        $resolution = $renderer->context()->resolution;
        if (!in_array($resolution->kind, [Kind::Category, Kind::Tag, Kind::Taxonomy], true)) {
            return [null, false];
        }
        return [$this->terms->find((string) $resolution->record['taxonomy'], (int) $resolution->record['term_id']), false];
    }
}
