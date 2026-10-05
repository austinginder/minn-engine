<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The rules for content in SVG and MathML: text and comments stay in the
 * foreign namespace, an HTML-only start tag (or a font with color, face or
 * size) breaks out back to HTML, other start tags open elements in the
 * current foreign namespace, and an end tag closes the nearest element of
 * its name or falls through to the HTML rules.
 */
final class ForeignRules
{
    private const BREAKOUT = ['B', 'BIG', 'BLOCKQUOTE', 'BODY', 'BR', 'CENTER', 'CODE', 'DD', 'DIV', 'DL', 'DT', 'EM', 'EMBED', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HEAD', 'HR', 'I', 'IMG', 'LI', 'LISTING', 'MENU', 'META', 'NOBR', 'OL', 'P', 'PRE', 'RUBY', 'S', 'SMALL', 'SPAN', 'STRONG', 'STRIKE', 'SUB', 'SUP', 'TABLE', 'TT', 'U', 'UL', 'VAR'];

    /** Processes one token by the rules for foreign content. */
    public static function process(Builder $b, Token $t): void
    {
        $namespace = (string) $b->adjustedCurrent()?->namespace;
        match (true) {
            $t->type === '#text' => self::text($b, $t, $namespace),
            $t->type === '#doctype' => null,
            $t->type !== '#tag' => $b->insertLeaf($namespace),
            self::breaksOut($b, $t) => self::breakOut($b, $t),
            $t->isStart() => self::start($b, $t, $namespace),
            default => self::end($b, $t),
        };
    }

    private static function text(Builder $b, Token $t, string $namespace): void
    {
        $b->insertLeaf($namespace);
        if ($t->kind === 'generic') {
            $b->framesetNotOk();
        }
    }

    private static function breaksOut(Builder $b, Token $t): bool
    {
        if ($t->isEnd('BR', 'P')) {
            return true;
        }
        if (!$t->isStart()) {
            return false;
        }
        if ($t->name === 'FONT') {
            return $b->attribute('color') !== null || $b->attribute('face') !== null || $b->attribute('size') !== null;
        }
        return in_array($t->name, self::BREAKOUT, true);
    }

    private static function breakOut(Builder $b, Token $t): void
    {
        while (($node = $b->stack()->current()) !== null && $node->namespace !== 'html' && !$node->isMathTextIntegrationPoint() && !$node->isHtmlIntegrationPoint()) {
            $b->pop();
        }
        $b->process($t);
    }

    private static function start(Builder $b, Token $t, string $namespace): void
    {
        if ($t->selfClosing()) {
            $b->insertLeafElement($namespace);
            return;
        }
        $b->insert($namespace);
    }

    private static function end(Builder $b, Token $t): void
    {
        $nodes = $b->stack()->all();
        for ($i = count($nodes) - 1; $i > 0; $i--) {
            $node = $nodes[$i];
            if ($node->type === '#tag' && $node->name === $t->name) {
                $b->popUntilNode($node);
                return;
            }
            if ($nodes[$i - 1]->namespace === 'html') {
                $b->processIn($b->mode(), $t);
                return;
            }
        }
    }
}
