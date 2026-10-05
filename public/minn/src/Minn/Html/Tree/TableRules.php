<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The table insertion modes: in table (and its text), in caption, in
 * column group, in table body, in row, in cell. Anything that would be
 * foster-parented out of the table (text other than white space, most
 * elements) is where the reference stops.
 */
final class TableRules
{
    private const TABLE_PARTS = ['CAPTION', 'COL', 'COLGROUP', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR'];

    /** Processes one token in one of the table insertion modes. */
    public static function process(Builder $b, string $mode, Token $t): void
    {
        match ($mode) {
            'in caption' => self::inCaption($b, $t),
            'in column group' => self::inColumnGroup($b, $t),
            'in table body' => self::inTableBody($b, $t),
            'in row' => self::inRow($b, $t),
            'in cell' => self::inCell($b, $t),
            default => self::inTable($b, $t),
        };
    }

    private static function inTable(Builder $b, Token $t): void
    {
        if ($t->type === '#text' && $b->stack()->current()?->isHtml('TABLE', 'TBODY', 'TEMPLATE', 'TFOOT', 'THEAD', 'TR')) {
            self::tableText($b, $t);
            return;
        }
        match (true) {
            $t->type === '#comment', $t->type === '#cdata-section', $t->type === '#processing-instruction', $t->type === '#funky-comment', $t->type === '#presumptuous-tag' => $b->insertLeaf(),
            $t->type === '#doctype', $t->type === '#eof' => null,
            $t->isStart('CAPTION') => self::openInTable($b, 'in caption', 'marker'),
            $t->isStart('COLGROUP') => self::openInTable($b, 'in column group', ''),
            $t->isStart('COL') => self::impliedInTable($b, 'COLGROUP', 'in column group'),
            $t->isStart('TBODY', 'TFOOT', 'THEAD') => self::openInTable($b, 'in table body', ''),
            $t->isStart('TD', 'TH', 'TR') => self::impliedInTable($b, 'TBODY', 'in table body'),
            $t->isStart('TABLE') => self::nestedTable($b),
            $t->isEnd('TABLE') => self::closeTable($b),
            $t->isEnd('BODY', 'CAPTION', 'COL', 'COLGROUP', 'HTML', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR') => null,
            $t->isStart('STYLE', 'SCRIPT', 'TEMPLATE'), $t->isEnd('TEMPLATE') => HeadRules::process($b, 'in head', $t),
            $t->isStart('INPUT') && strtolower((string) $b->attribute('type')) === 'hidden' => $b->insertLeafElement(),
            $t->isStart('FORM') => self::form($b),
            default => $b->unsupported('Foster parenting is not supported.'),
        };
    }

    private static function tableText(Builder $b, Token $t): void
    {
        if ($t->kind === 'null') {
            return;
        }
        if ($t->kind !== 'whitespace') {
            $b->unsupported('Foster parenting is not supported.');
        }
        $b->insertLeaf();
    }

    private static function clearToTableContext(Builder $b): void
    {
        while (($node = $b->stack()->current()) !== null && !$node->isHtml('TABLE', 'TEMPLATE', 'HTML')) {
            $b->pop();
        }
    }

    private static function clearToTableBodyContext(Builder $b): void
    {
        while (($node = $b->stack()->current()) !== null && !$node->isHtml('TBODY', 'TFOOT', 'THEAD', 'TEMPLATE', 'HTML')) {
            $b->pop();
        }
    }

    private static function clearToRowContext(Builder $b): void
    {
        while (($node = $b->stack()->current()) !== null && !$node->isHtml('TR', 'TEMPLATE', 'HTML')) {
            $b->pop();
        }
    }

    private static function openInTable(Builder $b, string $mode, string $marker): void
    {
        self::clearToTableContext($b);
        if ($marker !== '') {
            $b->formatting()->insertMarker();
        }
        $b->insert();
        $b->switchTo($mode);
    }

    private static function impliedInTable(Builder $b, string $name, string $mode): void
    {
        self::clearToTableContext($b);
        $b->insertImplied($name);
        $b->reprocessIn($mode);
    }

    private static function nestedTable(Builder $b): void
    {
        if (!$b->stack()->inTableScope('TABLE')) {
            return;
        }
        $b->popUntil('TABLE');
        $b->resetInsertionMode();
        $b->process($b->token());
    }

    private static function closeTable(Builder $b): void
    {
        if (!$b->stack()->inTableScope('TABLE')) {
            return;
        }
        $b->popUntil('TABLE');
        $b->resetInsertionMode();
    }

    private static function form(Builder $b): void
    {
        if ($b->stack()->hasHtml('TEMPLATE') || $b->formPointer() !== null) {
            return;
        }
        $node = $b->insert();
        $b->setFormPointer($node);
        $b->pop();
    }

    private static function inCaption(Builder $b, Token $t): void
    {
        if ($t->isEnd('CAPTION')) {
            self::closeCaption($b);
            return;
        }
        if ($t->isStart(...self::TABLE_PARTS) || $t->isEnd('TABLE')) {
            if (self::closeCaption($b)) {
                $b->process($t);
            }
            return;
        }
        if ($t->isEnd('BODY', 'COL', 'COLGROUP', 'HTML', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR')) {
            return;
        }
        BodyRules::process($b, $t);
    }

    private static function closeCaption(Builder $b): bool
    {
        if (!$b->stack()->inTableScope('CAPTION')) {
            return false;
        }
        $b->generateImpliedEndTags();
        $b->popUntil('CAPTION');
        $b->formatting()->clearToLastMarker();
        $b->switchTo('in table');
        return true;
    }

    private static function inColumnGroup(Builder $b, Token $t): void
    {
        match (true) {
            $t->isText('whitespace'), $t->type === '#comment' => $b->insertLeaf(),
            $t->type === '#doctype', $t->isEnd('COL') => null,
            $t->isStart('HTML') => BodyRules::process($b, $t),
            $t->isStart('COL') => $b->insertLeafElement(),
            $t->isEnd('COLGROUP') => self::closeColumnGroup($b),
            $t->isStart('TEMPLATE'), $t->isEnd('TEMPLATE') => HeadRules::process($b, 'in head', $t),
            $t->type === '#eof' => null,
            default => self::leaveColumnGroup($b),
        };
    }

    private static function closeColumnGroup(Builder $b): bool
    {
        if (!$b->stack()->current()?->isHtml('COLGROUP')) {
            return false;
        }
        $b->popUntil('COLGROUP');
        $b->switchTo('in table');
        return true;
    }

    private static function leaveColumnGroup(Builder $b): void
    {
        if (self::closeColumnGroup($b)) {
            $b->process($b->token());
        }
    }

    private static function inTableBody(Builder $b, Token $t): void
    {
        match (true) {
            $t->isStart('TR') => self::openRow($b),
            $t->isStart('TH', 'TD') => self::impliedRow($b),
            $t->isEnd('TBODY', 'TFOOT', 'THEAD') => self::closeBody($b, $t->name),
            $t->isStart('CAPTION', 'COL', 'COLGROUP', 'TBODY', 'TFOOT', 'THEAD'), $t->isEnd('TABLE') => self::leaveBody($b),
            $t->isEnd('BODY', 'CAPTION', 'COL', 'COLGROUP', 'HTML', 'TD', 'TH', 'TR') => null,
            default => self::inTable($b, $t),
        };
    }

    private static function openRow(Builder $b): void
    {
        self::clearToTableBodyContext($b);
        $b->insert();
        $b->switchTo('in row');
    }

    private static function impliedRow(Builder $b): void
    {
        self::clearToTableBodyContext($b);
        $b->insertImplied('TR');
        $b->reprocessIn('in row');
    }

    private static function closeBody(Builder $b, string $name): void
    {
        if (!$b->stack()->inTableScope($name)) {
            return;
        }
        self::clearToTableBodyContext($b);
        $b->popUntil($name);
        $b->switchTo('in table');
    }

    private static function leaveBody(Builder $b): void
    {
        if (!$b->stack()->inTableScope('TBODY', 'THEAD', 'TFOOT')) {
            return;
        }
        self::clearToTableBodyContext($b);
        $b->pop();
        $b->reprocessIn('in table');
    }

    private static function inRow(Builder $b, Token $t): void
    {
        match (true) {
            $t->isStart('TH', 'TD') => self::openCell($b),
            $t->isEnd('TR') => self::closeRow($b),
            $t->isStart('CAPTION', 'COL', 'COLGROUP', 'TBODY', 'TFOOT', 'THEAD', 'TR'), $t->isEnd('TABLE') => self::leaveRow($b),
            $t->isEnd('TBODY', 'TFOOT', 'THEAD') => $b->stack()->inTableScope($t->name) ? self::leaveRow($b) : null,
            $t->isEnd('BODY', 'CAPTION', 'COL', 'COLGROUP', 'HTML', 'TD', 'TH') => null,
            default => self::inTable($b, $t),
        };
    }

    private static function openCell(Builder $b): void
    {
        self::clearToRowContext($b);
        $b->insert();
        $b->switchTo('in cell');
        $b->formatting()->insertMarker();
    }

    private static function closeRow(Builder $b): bool
    {
        if (!$b->stack()->inTableScope('TR')) {
            return false;
        }
        self::clearToRowContext($b);
        $b->popUntil('TR');
        $b->switchTo('in table body');
        return true;
    }

    private static function leaveRow(Builder $b): void
    {
        if (self::closeRow($b)) {
            $b->process($b->token());
        }
    }

    private static function inCell(Builder $b, Token $t): void
    {
        match (true) {
            $t->isEnd('TD', 'TH') => $b->stack()->inTableScope($t->name) ? self::closeCell($b, $t->name) : null,
            $t->isStart(...self::TABLE_PARTS) => self::leaveCell($b),
            $t->isEnd('BODY', 'CAPTION', 'COL', 'COLGROUP', 'HTML') => null,
            $t->isEnd('TABLE', 'TBODY', 'TFOOT', 'THEAD', 'TR') => $b->stack()->inTableScope($t->name) ? self::leaveCell($b) : null,
            default => BodyRules::process($b, $t),
        };
    }

    private static function closeCell(Builder $b, string $name): void
    {
        $b->generateImpliedEndTags();
        $b->popUntil($name);
        $b->formatting()->clearToLastMarker();
        $b->switchTo('in row');
    }

    private static function leaveCell(Builder $b): void
    {
        if (!$b->stack()->inTableScope('TD', 'TH')) {
            return;
        }
        self::closeCell($b, $b->stack()->inTableScope('TD') ? 'TD' : 'TH');
        $b->process($b->token());
    }
}
