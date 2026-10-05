<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The insertion modes around the body: initial (doctype and the
 * compatibility mode it indicates), before html, before head, in head, in
 * head noscript, after head, in template, after body, the frameset modes,
 * and after after body (where a comment would land outside the html
 * element, which the reference does not support).
 */
final class HeadRules
{
    private const HEAD_LEAVES = ['BASE', 'BASEFONT', 'BGSOUND', 'LINK', 'META', 'TITLE', 'NOFRAMES', 'STYLE', 'SCRIPT'];

    /** Processes one token in one of these insertion modes. */
    public static function process(Builder $b, string $mode, Token $t): void
    {
        match ($mode) {
            'initial' => self::initial($b, $t),
            'before html' => self::beforeHtml($b, $t),
            'before head' => self::beforeHead($b, $t),
            'in head' => self::inHead($b, $t),
            'in head noscript' => self::inHeadNoscript($b, $t),
            'after head' => self::afterHead($b, $t),
            'in template' => self::inTemplate($b, $t),
            'after body' => self::afterBody($b, $t),
            'in frameset', 'after frameset' => self::frameset($b, $mode, $t),
            'after after body', 'after after frameset' => self::afterAfter($b, $mode, $t),
            default => BodyRules::process($b, $t),
        };
    }

    private static function initial(Builder $b, Token $t): void
    {
        if ($t->isText('whitespace')) {
            return;
        }
        if ($t->type === '#comment') {
            $b->insertDocumentLeaf();
            return;
        }
        if ($t->type === '#doctype') {
            $b->insertDocumentLeaf();
            $b->setCompat(Compat::of($b->doctype()));
            $b->switchTo('before html');
            return;
        }
        $b->setCompat('quirks');
        $b->reprocessIn('before html');
    }

    private static function beforeHtml(Builder $b, Token $t): void
    {
        if ($t->type === '#doctype' || $t->isText('whitespace') || ($t->isEnd() && !$t->isEnd('HEAD', 'BODY', 'HTML', 'BR'))) {
            return;
        }
        if ($t->type === '#comment') {
            $b->insertDocumentLeaf();
            return;
        }
        if ($t->isStart('HTML')) {
            $b->insert();
            $b->switchTo('before head');
            return;
        }
        if ($t->type === '#eof') {
            return;
        }
        $b->insertImplied('HTML');
        $b->reprocessIn('before head');
    }

    private static function beforeHead(Builder $b, Token $t): void
    {
        if ($t->type === '#doctype' || $t->isText('whitespace') || ($t->isEnd() && !$t->isEnd('HEAD', 'BODY', 'HTML', 'BR'))) {
            return;
        }
        match (true) {
            $t->type === '#comment' => $b->insertLeaf(),
            $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->isStart('HEAD') => self::openHead($b),
            $t->type === '#eof' => null,
            default => self::impliedHead($b),
        };
    }

    private static function openHead(Builder $b): void
    {
        $b->insert();
        $b->switchTo('in head');
    }

    private static function impliedHead(Builder $b): void
    {
        $b->insertImplied('HEAD');
        $b->reprocessIn('in head');
    }

    private static function inHead(Builder $b, Token $t): void
    {
        $name = $t->name;
        match (true) {
            $t->isText('whitespace') => $b->insertLeaf(),
            $t->type === '#comment', $t->type === '#cdata-section', $t->type === '#processing-instruction' => $b->insertLeaf(),
            $t->type === '#doctype', $t->isStart('HEAD') => null,
            $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->isStart() && in_array($name, self::HEAD_LEAVES, true) => $b->insertLeafElement(),
            $t->isStart('NOSCRIPT') => self::open($b, 'in head noscript'),
            $t->isEnd('HEAD') => self::closeHead($b),
            $t->isStart('TEMPLATE') => self::openTemplate($b),
            $t->isEnd('TEMPLATE') => self::closeTemplate($b),
            $t->isEnd() && !$t->isEnd('BODY', 'HTML', 'BR') => null,
            $t->type === '#eof' => null,
            default => self::leaveHead($b),
        };
    }

    private static function open(Builder $b, string $mode): void
    {
        $b->insert();
        $b->switchTo($mode);
    }

    private static function closeHead(Builder $b): void
    {
        $b->popUntil('HEAD');
        $b->switchTo('after head');
    }

    private static function leaveHead(Builder $b): void
    {
        $b->pop();
        $b->reprocessIn('after head');
    }

    private static function openTemplate(Builder $b): void
    {
        $b->insert();
        $b->formatting()->insertMarker();
        $b->framesetNotOk();
        $b->switchTo('in template');
        $b->pushTemplateMode('in template');
    }

    private static function closeTemplate(Builder $b): void
    {
        if (!$b->stack()->hasHtml('TEMPLATE')) {
            return;
        }
        $b->generateAllImpliedEndTags();
        $b->popUntil('TEMPLATE');
        $b->formatting()->clearToLastMarker();
        $b->popTemplateMode();
        $b->resetInsertionMode();
    }

    private static function inHeadNoscript(Builder $b, Token $t): void
    {
        match (true) {
            $t->type === '#doctype', $t->isStart('HEAD', 'NOSCRIPT') => null,
            $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->isEnd('NOSCRIPT') => self::closeNoscript($b),
            $t->isText('whitespace'), $t->type === '#comment', $t->isStart('BASEFONT', 'BGSOUND', 'LINK', 'META', 'NOFRAMES', 'STYLE') => self::inHead($b, $t),
            $t->isEnd() && !$t->isEnd('BR') => null,
            $t->type === '#eof' => null,
            default => self::leaveNoscript($b),
        };
    }

    private static function closeNoscript(Builder $b): void
    {
        $b->popUntil('NOSCRIPT');
        $b->switchTo('in head');
    }

    private static function leaveNoscript(Builder $b): void
    {
        $b->pop();
        $b->reprocessIn('in head');
    }

    private static function afterHead(Builder $b, Token $t): void
    {
        $name = $t->name;
        match (true) {
            $t->isText('whitespace'), $t->type === '#comment' => $b->insertLeaf(),
            $t->type === '#doctype', $t->isStart('HEAD') => null,
            $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->isStart('BODY') => self::openBody($b),
            $t->isStart('FRAMESET') => self::open($b, 'in frameset'),
            $t->isStart() && (in_array($name, self::HEAD_LEAVES, true) || $name === 'TEMPLATE') => self::backIntoHead($b, $t),
            $t->isEnd('TEMPLATE') => self::inHead($b, $t),
            $t->isEnd() && !$t->isEnd('BODY', 'HTML', 'BR') => null,
            $t->type === '#eof' => null,
            default => self::impliedBody($b),
        };
    }

    private static function openBody(Builder $b): void
    {
        $b->insert();
        $b->framesetNotOk();
        $b->switchTo('in body');
    }

    private static function impliedBody(Builder $b): void
    {
        $b->insertImplied('BODY');
        $b->reprocessIn('in body');
    }

    private static function backIntoHead(Builder $b, Token $t): void
    {
        $b->unsupported('Cannot process elements after HEAD which reopen the HEAD element.');
    }

    private static function inTemplate(Builder $b, Token $t): void
    {
        $name = $t->name;
        if ($t->type !== '#tag' || ($t->isStart() && (in_array($name, self::HEAD_LEAVES, true) || $name === 'TEMPLATE')) || $t->isEnd('TEMPLATE')) {
            $t->type === '#tag' ? self::inHead($b, $t) : BodyRules::process($b, $t);
            return;
        }
        if ($t->isEnd()) {
            return;
        }
        $mode = match ($name) {
            'CAPTION', 'COLGROUP', 'TBODY', 'TFOOT', 'THEAD' => 'in table',
            'COL' => 'in column group',
            'TR' => 'in table body',
            'TD', 'TH' => 'in row',
            default => 'in body',
        };
        $b->popTemplateMode();
        $b->pushTemplateMode($mode);
        $b->reprocessIn($mode);
    }

    private static function afterBody(Builder $b, Token $t): void
    {
        match (true) {
            $t->isText('whitespace'), $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->type === '#comment' => $b->unsupported('Content outside of BODY is unsupported.'),
            $t->type === '#doctype', $t->type === '#eof' => null,
            $t->isEnd('HTML') => $b->isFragment() ? null : $b->switchTo('after after body'),
            default => $b->reprocessIn('in body'),
        };
    }

    private static function frameset(Builder $b, string $mode, Token $t): void
    {
        match (true) {
            $t->isText('whitespace'), $t->type === '#comment' => $b->insertLeaf(),
            $t->isText('generic') => $b->unsupported('Non-whitespace characters cannot be handled in frameset.'),
            $t->isStart('HTML') => BodyRules::process($b, $t),
            $mode === 'in frameset' && $t->isStart('FRAMESET') => $b->insert(),
            $mode === 'in frameset' && $t->isEnd('FRAMESET') => self::closeFrameset($b),
            $mode === 'in frameset' && $t->isStart('FRAME') => $b->insertLeafElement(),
            $mode === 'after frameset' && $t->isEnd('HTML') => $b->switchTo('after after frameset'),
            $t->isStart('NOFRAMES') => self::inHead($b, $t),
            default => null,
        };
    }

    private static function closeFrameset(Builder $b): void
    {
        if ($b->stack()->current()?->isHtml('HTML')) {
            return;
        }
        $b->pop();
        if (!$b->isFragment() && !$b->stack()->current()?->isHtml('FRAMESET')) {
            $b->switchTo('after frameset');
        }
    }

    private static function afterAfter(Builder $b, string $mode, Token $t): void
    {
        match (true) {
            $t->type === '#comment' => $b->unsupported('Content outside of HTML is unsupported.'),
            $t->type === '#doctype', $t->isText('whitespace'), $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->type === '#eof' => null,
            $mode === 'after after frameset' => $t->isStart('NOFRAMES') ? self::inHead($b, $t) : null,
            default => $b->reprocessIn('in body'),
        };
    }
}
