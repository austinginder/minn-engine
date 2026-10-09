<?php

declare(strict_types=1);

namespace Minn\I18n;

use Exception;

/**
 * The plural expression reader the old POMO library carries (Plural_Forms),
 * kept apart from PluralExpression because plugins see its quirks: it reads
 * only n, whole numbers, parentheses, % and the comparisons, && and ||, and
 * the ternary, skipping spaces and tabs alone. It turns the expression into
 * postfix tokens once (operators of equal strength group to the left, the
 * ternary to the right) and runs them on a stack per number: an operand the
 * stack lacks is null, == and != compare strictly, values stay as PHP left
 * them until the answer is cast to an integer, and % is PHP's own, so a zero
 * divisor throws. Every refusal is an Exception with the reference's words
 * (probe translation-files).
 */
final class LegacyPluralForms
{
    private const OP_CHARS = '|&><!=%?:';

    /** @var array<string, int> operator strength; ( and ) sit below everything */
    private const PRECEDENCE = ['%' => 6, '<' => 5, '<=' => 5, '>' => 5, '>=' => 5, '==' => 4, '!=' => 4, '&&' => 3, '||' => 2, '?:' => 1, '?' => 1, '(' => 0, ')' => 0];

    /**
     * The expression as postfix tokens: ['var'], ['value', int] or ['op', string].
     *
     * @return list<array{0: string, 1?: int|string}>
     * @throws Exception on an unknown symbol or operator, a stray ":" or unmatched parentheses
     */
    public static function parse(string $expression): array
    {
        $output = [];
        $stack = [];
        $at = 0;
        $length = strlen($expression);
        while ($at < $length) {
            $at = self::token($expression, $at, $output, $stack);
        }
        while ($stack !== []) {
            $operator = array_pop($stack);
            if ($operator === '(') {
                throw new Exception('Mismatched parentheses');
            }
            $output[] = ['op', $operator];
        }
        return $output;
    }

    /**
     * The value of postfix tokens for one number.
     *
     * @param list<array{0: string, 1?: int|string}> $tokens
     * @throws Exception on an operator it cannot run or values left over; DivisionByZeroError on % 0
     */
    public static function execute(array $tokens, int $n): int
    {
        $stack = [];
        foreach ($tokens as $token) {
            $stack[] = match ($token[0]) {
                'var' => $n,
                'value' => $token[1],
                default => self::apply((string) $token[1], $stack),
            };
        }
        if (count($stack) !== 1) {
            throw new Exception('Too many values remaining on the stack');
        }
        return (int) $stack[0];
    }

    /**
     * Reads one token at an offset onto the output or the operator stack; the offset after it.
     *
     * @param list<array{0: string, 1?: int|string}> $output
     * @param list<string> $stack
     */
    private static function token(string $expression, int $at, array &$output, array &$stack): int
    {
        $char = $expression[$at];
        if ($char === ' ' || $char === "\t") {
            return $at + 1;
        }
        if ($char === 'n') {
            $output[] = ['var'];
            return $at + 1;
        }
        if (ctype_digit($char)) {
            $digits = strspn($expression, '0123456789', $at);
            $output[] = ['value', (int) substr($expression, $at, $digits)];
            return $at + $digits;
        }
        if ($char === '(') {
            $stack[] = '(';
            return $at + 1;
        }
        if ($char === ')') {
            self::closeParenthesis($output, $stack);
            return $at + 1;
        }
        if (!str_contains(self::OP_CHARS, $char)) {
            throw new Exception(sprintf('Unknown symbol "%s"', $char));
        }
        return $at + self::operator($expression, $at, $output, $stack);
    }

    /**
     * Reads the one- or two-character operator at an offset; its length.
     *
     * @param list<array{0: string, 1?: int|string}> $output
     * @param list<string> $stack
     */
    private static function operator(string $expression, int $at, array &$output, array &$stack): int
    {
        $pair = substr($expression, $at, 2);
        $operator = isset(self::PRECEDENCE[$pair]) ? $pair : $expression[$at];
        if ($operator === ':') {
            self::closeTernary($output, $stack);
            return 1;
        }
        if (!isset(self::PRECEDENCE[$operator])) {
            throw new Exception(sprintf('Unknown operator "%s"', $operator));
        }
        // Equal strength groups to the left, except the ternary, which groups to the right.
        $strength = self::PRECEDENCE[$operator];
        while ($stack !== [] && (self::PRECEDENCE[end($stack)] > $strength || (self::PRECEDENCE[end($stack)] === $strength && $operator !== '?'))) {
            $output[] = ['op', array_pop($stack)];
        }
        $stack[] = $operator;
        return strlen($operator);
    }

    /**
     * @param list<array{0: string, 1?: int|string}> $output
     * @param list<string> $stack
     */
    private static function closeParenthesis(array &$output, array &$stack): void
    {
        while ($stack !== [] && end($stack) !== '(') {
            $output[] = ['op', array_pop($stack)];
        }
        if ($stack === []) {
            throw new Exception('Mismatched parentheses');
        }
        array_pop($stack);
    }

    /**
     * A ":" moves operators out until its "?", which becomes the whole ternary.
     *
     * @param list<array{0: string, 1?: int|string}> $output
     * @param list<string> $stack
     */
    private static function closeTernary(array &$output, array &$stack): void
    {
        while ($stack !== [] && end($stack) !== '?' && end($stack) !== '(') {
            $output[] = ['op', array_pop($stack)];
        }
        if ($stack === [] || end($stack) !== '?') {
            throw new Exception('Missing starting "?" ternary operator');
        }
        $stack[count($stack) - 1] = '?:';
    }

    /**
     * Runs one operator on the values it pops, rightmost first.
     *
     * @param list<mixed> $stack
     */
    private static function apply(string $operator, array &$stack): mixed
    {
        if ($operator === '?:') {
            [$else, $then, $condition] = [array_pop($stack), array_pop($stack), array_pop($stack)];
            return $condition ? $then : $else;
        }
        [$right, $left] = [array_pop($stack), array_pop($stack)];
        return match ($operator) {
            '%' => $left % $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '==' => $left === $right,
            '!=' => $left !== $right,
            '&&' => $left && $right,
            '||' => $left || $right,
            default => throw new Exception(sprintf('Unknown operator "%s"', $operator)),
        };
    }
}
