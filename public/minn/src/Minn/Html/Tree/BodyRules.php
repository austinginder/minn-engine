<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The "in body" insertion mode, with select handled in body as the current
 * spec does (no "in select" mode). The adoption agency runs its simple
 * cases (the formatting element is closed with nothing special inside it);
 * the reference stops on the others, and on an end tag for a formatting
 * element that is not active.
 */
final class BodyRules
{
    public const FORMATTING = ['A', 'B', 'BIG', 'CODE', 'EM', 'FONT', 'I', 'NOBR', 'S', 'SMALL', 'STRIKE', 'STRONG', 'TT', 'U'];
    public const SPECIAL = ['ADDRESS', 'APPLET', 'AREA', 'ARTICLE', 'ASIDE', 'BASE', 'BASEFONT', 'BGSOUND', 'BLOCKQUOTE', 'BODY', 'BR', 'BUTTON', 'CAPTION', 'CENTER', 'COL', 'COLGROUP', 'DD', 'DETAILS', 'DIR', 'DIV', 'DL', 'DT', 'EMBED', 'FIELDSET', 'FIGCAPTION', 'FIGURE', 'FOOTER', 'FORM', 'FRAME', 'FRAMESET', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HEAD', 'HEADER', 'HGROUP', 'HR', 'HTML', 'IFRAME', 'IMG', 'INPUT', 'KEYGEN', 'LI', 'LINK', 'LISTING', 'MAIN', 'MARQUEE', 'MENU', 'META', 'NAV', 'NOEMBED', 'NOFRAMES', 'NOSCRIPT', 'OBJECT', 'OL', 'P', 'PARAM', 'PLAINTEXT', 'PRE', 'SCRIPT', 'SEARCH', 'SECTION', 'SELECT', 'SOURCE', 'STYLE', 'SUMMARY', 'TABLE', 'TBODY', 'TD', 'TEMPLATE', 'TEXTAREA', 'TFOOT', 'TH', 'THEAD', 'TITLE', 'TR', 'TRACK', 'UL', 'WBR', 'XMP'];
    private const BLOCKS = ['ADDRESS', 'ARTICLE', 'ASIDE', 'BLOCKQUOTE', 'CENTER', 'DETAILS', 'DIALOG', 'DIR', 'DIV', 'DL', 'FIELDSET', 'FIGCAPTION', 'FIGURE', 'FOOTER', 'HEADER', 'HGROUP', 'MAIN', 'MENU', 'NAV', 'OL', 'P', 'SEARCH', 'SECTION', 'SUMMARY', 'UL'];
    private const BLOCK_ENDS = ['ADDRESS', 'ARTICLE', 'ASIDE', 'BLOCKQUOTE', 'BUTTON', 'CENTER', 'DETAILS', 'DIALOG', 'DIR', 'DIV', 'DL', 'FIELDSET', 'FIGCAPTION', 'FIGURE', 'FOOTER', 'HEADER', 'HGROUP', 'LISTING', 'MAIN', 'MENU', 'NAV', 'OL', 'PRE', 'SEARCH', 'SECTION', 'SUMMARY', 'UL'];
    private const HEADINGS = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'];

    /** Processes one token in the "in body" insertion mode. */
    public static function process(Builder $b, Token $t): void
    {
        match ($t->type) {
            '#text' => self::text($b, $t),
            '#tag' => $t->isEnd() ? self::endTag($b, $t) : self::startTag($b, $t),
            '#doctype', '#eof' => null,
            default => $b->insertLeaf(),
        };
    }

    private static function text(Builder $b, Token $t): void
    {
        if ($t->kind === 'null') {
            return;
        }
        $b->reconstructFormatting();
        $b->insertLeaf();
        if ($t->kind === 'generic') {
            $b->framesetNotOk();
        }
    }

    private static function startTag(Builder $b, Token $t): void
    {
        $name = $t->name;
        match (true) {
            $name === 'HTML', $name === 'BODY', $name === 'FRAMESET' => self::outerStart($b, $t),
            in_array($name, ['BASE', 'BASEFONT', 'BGSOUND', 'LINK', 'META', 'NOFRAMES', 'SCRIPT', 'STYLE', 'TEMPLATE', 'TITLE'], true) => HeadRules::process($b, 'in head', $t),
            in_array($name, self::BLOCKS, true) => self::block($b),
            in_array($name, self::HEADINGS, true) => self::heading($b),
            $name === 'PRE', $name === 'LISTING' => self::pre($b),
            $name === 'FORM' => self::form($b),
            $name === 'LI', $name === 'DD', $name === 'DT' => self::listItem($b, $name),
            $name === 'PLAINTEXT' => $b->unsupported('Cannot process PLAINTEXT elements.'),
            $name === 'BUTTON' => self::button($b),
            $name === 'A' => self::anchor($b),
            $name === 'NOBR' => self::nobr($b),
            in_array($name, self::FORMATTING, true) => self::formatting($b),
            in_array($name, ['APPLET', 'MARQUEE', 'OBJECT'], true) => self::withMarker($b),
            $name === 'TABLE' => self::table($b),
            in_array($name, ['AREA', 'BR', 'EMBED', 'IMG', 'KEYGEN', 'WBR', 'INPUT'], true) => self::voidElement($b, $t),
            in_array($name, ['PARAM', 'SOURCE', 'TRACK'], true) => $b->insertLeafElement(),
            $name === 'HR' => self::hr($b),
            $name === 'IMAGE' => $b->process($t->renamed('IMG', $t->flags)),
            $name === 'TEXTAREA', $name === 'IFRAME', $name === 'NOEMBED', $name === 'XMP' => self::rawText($b, $name),
            $name === 'SELECT' => self::select($b),
            $name === 'OPTION', $name === 'OPTGROUP' => self::option($b, $name),
            in_array($name, ['RB', 'RTC', 'RP', 'RT'], true) => self::ruby($b, $name),
            $name === 'MATH', $name === 'SVG' => self::foreign($b, $name),
            in_array($name, ['CAPTION', 'COL', 'COLGROUP', 'FRAME', 'HEAD', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR'], true) => null,
            default => self::ordinary($b),
        };
    }

    private static function endTag(Builder $b, Token $t): void
    {
        $name = $t->name;
        match (true) {
            $name === 'TEMPLATE' => HeadRules::process($b, 'in head', $t),
            $name === 'BODY', $name === 'HTML' => self::outerEnd($b, $t),
            in_array($name, self::BLOCK_ENDS, true) => self::closeBlock($b, $name),
            $name === 'FORM' => self::closeForm($b),
            $name === 'P' => self::closeParagraph($b),
            $name === 'LI', $name === 'DD', $name === 'DT' => self::closeListItem($b, $name),
            in_array($name, self::HEADINGS, true) => self::closeHeading($b),
            in_array($name, self::FORMATTING, true) => self::adoptionAgency($b, $name),
            in_array($name, ['APPLET', 'MARQUEE', 'OBJECT'], true) => self::closeWithMarker($b, $name),
            $name === 'BR' => $b->process($t->renamed('BR', 0)),
            default => self::anyOtherEnd($b, $name),
        };
    }

    private static function outerStart(Builder $b, Token $t): void
    {
        // A second html or body start tag only adds attributes; frameset is honoured only at the top of a document.
        if ($t->name === 'BODY' && $b->stack()->count() > 1 && $b->stack()->at(1)?->isHtml('BODY') && !$b->stack()->hasHtml('TEMPLATE')) {
            $b->framesetNotOk();
        }
        if ($t->name === 'FRAMESET' && !$b->isFragment() && $b->framesetOk() && $b->stack()->at(1)?->isHtml('BODY')) {
            $b->unsupported('Cannot process non-ignored FRAMESET tags.');
        }
    }

    private static function outerEnd(Builder $b, Token $t): void
    {
        if (!$b->stack()->inScope('BODY')) {
            return;
        }
        if ($t->name === 'BODY') {
            $b->switchTo('after body');
            return;
        }
        $b->reprocessIn('after body');
    }

    private static function block(Builder $b): void
    {
        $b->closePInButtonScope();
        $b->insert();
    }

    private static function heading(Builder $b): void
    {
        $b->closePInButtonScope();
        if ($b->stack()->current()?->isHtml(...self::HEADINGS)) {
            $b->pop();
        }
        $b->insert();
    }

    private static function pre(Builder $b): void
    {
        $b->closePInButtonScope();
        $b->insert();
        $b->skipNextNewline();
        $b->framesetNotOk();
    }

    private static function form(Builder $b): void
    {
        $inTemplate = $b->stack()->hasHtml('TEMPLATE');
        if ($b->formPointer() !== null && !$inTemplate) {
            return;
        }
        $b->closePInButtonScope();
        $node = $b->insert();
        if (!$inTemplate) {
            $b->setFormPointer($node);
        }
    }

    private static function listItem(Builder $b, string $name): void
    {
        $b->framesetNotOk();
        $closes = $name === 'LI' ? ['LI'] : ['DD', 'DT'];
        $nodes = $b->stack()->all();
        for ($i = count($nodes) - 1; $i >= 0; $i--) {
            $node = $nodes[$i];
            if ($node->isHtml(...$closes)) {
                $b->generateImpliedEndTags($node->name);
                $b->popUntil($node->name);
                break;
            }
            if (self::isSpecial($node) && !$node->isHtml('ADDRESS', 'DIV', 'P')) {
                break;
            }
        }
        $b->closePInButtonScope();
        $b->insert();
    }

    private static function button(Builder $b): void
    {
        if ($b->stack()->inScope('BUTTON')) {
            $b->generateImpliedEndTags();
            $b->popUntil('BUTTON');
        }
        $b->reconstructFormatting();
        $b->insert();
        $b->framesetNotOk();
    }

    private static function anchor(Builder $b): void
    {
        $active = $b->formatting()->lastNamed('A');
        if ($active !== null) {
            self::adoptionAgency($b, 'A');
            $b->formatting()->remove($active);
            if ($b->stack()->containsNode($active)) {
                $b->unsupported('Cannot remove an A element from the middle of the stack of open elements.');
            }
        }
        self::formatting($b);
    }

    private static function nobr(Builder $b): void
    {
        $b->reconstructFormatting();
        if ($b->stack()->inScope('NOBR')) {
            self::adoptionAgency($b, 'NOBR');
            $b->reconstructFormatting();
        }
        $b->formatting()->push($b->insert());
    }

    private static function formatting(Builder $b): void
    {
        $b->reconstructFormatting();
        $b->formatting()->push($b->insert());
    }

    private static function withMarker(Builder $b): void
    {
        $b->reconstructFormatting();
        $b->insert();
        $b->formatting()->insertMarker();
        $b->framesetNotOk();
    }

    private static function table(Builder $b): void
    {
        if ($b->compat() !== 'quirks') {
            $b->closePInButtonScope();
        }
        $b->insert();
        $b->framesetNotOk();
        $b->switchTo('in table');
    }

    private static function voidElement(Builder $b, Token $t): void
    {
        if ($t->isStart('INPUT') && $b->stack()->inScope('SELECT')) {
            $b->popUntil('SELECT');
        }
        $b->reconstructFormatting();
        if ($t->isStart('INPUT')) {
            $b->insertLeafElement();
            if (strtolower((string) $b->attribute('type')) !== 'hidden') {
                $b->framesetNotOk();
            }
            return;
        }
        $b->insertLeafElement();
        $b->framesetNotOk();
    }

    private static function hr(Builder $b): void
    {
        $b->closePInButtonScope();
        if ($b->stack()->inScope('SELECT')) {
            $b->generateImpliedEndTags();
        }
        $b->insertLeafElement();
        $b->framesetNotOk();
    }

    private static function rawText(Builder $b, string $name): void
    {
        if ($name === 'XMP') {
            $b->closePInButtonScope();
            $b->reconstructFormatting();
        }
        $b->framesetNotOk();
        $b->insertLeafElement();
    }

    private static function select(Builder $b): void
    {
        if ($b->stack()->inScope('SELECT')) {
            $b->popUntil('SELECT');
            return;
        }
        $b->reconstructFormatting();
        $b->insert();
        $b->framesetNotOk();
    }

    private static function option(Builder $b, string $name): void
    {
        if ($b->stack()->inScope('SELECT')) {
            $b->generateImpliedEndTags($name === 'OPTION' ? 'OPTGROUP' : '');
        } elseif ($b->stack()->current()?->isHtml('OPTION')) {
            $b->pop();
        }
        $b->reconstructFormatting();
        $b->insert();
    }

    private static function ruby(Builder $b, string $name): void
    {
        if ($b->stack()->inScope('RUBY')) {
            $b->generateImpliedEndTags($name === 'RP' || $name === 'RT' ? 'RTC' : '');
        }
        $b->insert();
    }

    private static function foreign(Builder $b, string $name): void
    {
        $b->reconstructFormatting();
        $namespace = strtolower($name);
        if ($b->token()->selfClosing()) {
            $b->insertLeafElement($namespace);
            return;
        }
        $b->insert($namespace);
    }

    private static function ordinary(Builder $b): void
    {
        $b->reconstructFormatting();
        $b->insert();
    }

    private static function closeBlock(Builder $b, string $name): void
    {
        if (!$b->stack()->inScope($name)) {
            return;
        }
        $b->generateImpliedEndTags();
        $b->popUntil($name);
    }

    private static function closeForm(Builder $b): void
    {
        if ($b->stack()->hasHtml('TEMPLATE')) {
            self::closeBlock($b, 'FORM');
            return;
        }
        $node = $b->formPointer();
        $b->setFormPointer(null);
        if ($node === null || !$b->stack()->containsNode($node) || !$b->stack()->inScope('FORM')) {
            return;
        }
        $b->generateImpliedEndTags();
        if ($b->stack()->current() !== $node) {
            $b->unsupported('Cannot close a FORM when other elements remain open as this would throw off the breadcrumbs for the following tokens.');
        }
        $b->popUntilNode($node);
    }

    private static function closeParagraph(Builder $b): void
    {
        if (!$b->stack()->inButtonScope('P')) {
            $b->insertImplied('P');
        }
        $b->closeP();
    }

    private static function closeListItem(Builder $b, string $name): void
    {
        if (!($name === 'LI' ? $b->stack()->inListItemScope('LI') : $b->stack()->inScope($name))) {
            return;
        }
        $b->generateImpliedEndTags($name);
        $b->popUntil($name);
    }

    private static function closeHeading(Builder $b): void
    {
        if (!$b->stack()->inScope(...self::HEADINGS)) {
            return;
        }
        $b->generateImpliedEndTags();
        $b->popUntil(...self::HEADINGS);
    }

    private static function closeWithMarker(Builder $b, string $name): void
    {
        if (!$b->stack()->inScope($name)) {
            return;
        }
        $b->generateImpliedEndTags();
        $b->popUntil($name);
        $b->formatting()->clearToLastMarker();
    }

    /** The adoption agency algorithm in the cases the reference runs: the formatting element closes with nothing special opened inside it. */
    private static function adoptionAgency(Builder $b, string $name): void
    {
        $current = $b->stack()->current();
        if ($current !== null && $current->isHtml($name) && !$b->formatting()->contains($current)) {
            $b->popUntilNode($current);
            return;
        }
        $element = $b->formatting()->lastNamed($name);
        if ($element === null) {
            $b->unsupported('Cannot run adoption agency when "any other end tag" is required.');
        }
        if (!$b->stack()->containsNode($element)) {
            $b->formatting()->remove($element);
            return;
        }
        if (!$b->stack()->inScope($name)) {
            return;
        }
        $nodes = $b->stack()->all();
        for ($i = array_search($element, $nodes, true) + 1; $i < count($nodes); $i++) {
            if (self::isSpecial($nodes[$i])) {
                $b->unsupported('Cannot extract common ancestor in adoption agency algorithm.');
            }
        }
        $b->popUntilNode($element);
        $b->formatting()->remove($element);
    }

    private static function anyOtherEnd(Builder $b, string $name): void
    {
        $nodes = $b->stack()->all();
        for ($i = count($nodes) - 1; $i >= 0; $i--) {
            $node = $nodes[$i];
            if ($node->isHtml($name)) {
                $b->generateImpliedEndTags($name);
                $b->popUntilNode($node);
                return;
            }
            if (self::isSpecial($node)) {
                return;
            }
        }
    }

    /** Whether the node is in the spec's "special" category. */
    public static function isSpecial(Node $node): bool
    {
        return $node->isHtml(...self::SPECIAL)
            || $node->isIn('math', 'MI', 'MO', 'MN', 'MS', 'MTEXT', 'ANNOTATION-XML')
            || $node->isIn('svg', 'FOREIGNOBJECT', 'DESC', 'TITLE');
    }
}
