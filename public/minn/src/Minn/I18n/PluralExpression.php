<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The C subset a Plural-Forms header writes its rule in: the number n,
 * integer literals, parentheses, ! and unary minus, * / %, + -, the four
 * comparisons, == and !=, && and ||, and the ternary, with C's precedence
 * and the ternary binding to the right. Parsed into a small tree once and
 * evaluated per number; nothing is ever handed to PHP to run.
 */
final class PluralExpression
{
    private const TOKEN = '/\G\s*(\d+|n|==|!=|<=|>=|&&|\|\||[<>!?:()+\-*\/%])/';

    /** @var array<string, int> binary operators by binding strength; the ternary sits below them all */
    private const BINARY = ['||' => 1, '&&' => 2, '==' => 3, '!=' => 3, '<' => 4, '>' => 4, '<=' => 4, '>=' => 4, '+' => 5, '-' => 5, '*' => 6, '/' => 6, '%' => 6];

    /**
     * The expression as a tree, or null when it is not a whole expression.
     *
     * @return array<int, mixed>|null
     */
    public static function parse(string $expression): ?array
    {
        $tokens = self::tokens($expression);
        if ($tokens === null || $tokens === []) {
            return null;
        }
        $position = 0;
        $tree = self::ternary($tokens, $position);
        return $tree !== null && $position === count($tokens) ? $tree : null;
    }

    /**
     * The value of a parsed expression for one number; true is 1 and false 0.
     *
     * @param array<int, mixed> $node
     */
    public static function evaluate(array $node, int $n): int
    {
        return match ($node[0]) {
            'num' => (int) $node[1],
            'n' => $n,
            '!' => self::evaluate($node[1], $n) === 0 ? 1 : 0,
            'neg' => -self::evaluate($node[1], $n),
            '?' => self::evaluate($node[1], $n) !== 0 ? self::evaluate($node[2], $n) : self::evaluate($node[3], $n),
            '&&' => self::evaluate($node[1], $n) !== 0 && self::evaluate($node[2], $n) !== 0 ? 1 : 0,
            '||' => self::evaluate($node[1], $n) !== 0 || self::evaluate($node[2], $n) !== 0 ? 1 : 0,
            default => self::binary((string) $node[0], self::evaluate($node[1], $n), self::evaluate($node[2], $n)),
        };
    }

    private static function binary(string $operator, int $left, int $right): int
    {
        return match ($operator) {
            '==' => $left === $right ? 1 : 0,
            '!=' => $left !== $right ? 1 : 0,
            '<' => $left < $right ? 1 : 0,
            '>' => $left > $right ? 1 : 0,
            '<=' => $left <= $right ? 1 : 0,
            '>=' => $left >= $right ? 1 : 0,
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right === 0 ? 0 : intdiv($left, $right),
            default => $right === 0 ? 0 : $left % $right,
        };
    }

    /** @return list<string>|null */
    private static function tokens(string $expression): ?array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen(rtrim($expression));
        while ($offset < $length) {
            if (preg_match(self::TOKEN, $expression, $m, 0, $offset) !== 1) {
                return null;
            }
            $tokens[] = $m[1];
            $offset += strlen($m[0]);
        }
        return $tokens;
    }

    /**
     * @param list<string> $tokens
     * @return array<int, mixed>|null
     */
    private static function ternary(array $tokens, int &$position): ?array
    {
        $condition = self::binaryLevel($tokens, $position, 1);
        if ($condition === null || ($tokens[$position] ?? '') !== '?') {
            return $condition;
        }
        $position++;
        $then = self::ternary($tokens, $position);
        if ($then === null || ($tokens[$position] ?? '') !== ':') {
            return null;
        }
        $position++;
        $else = self::ternary($tokens, $position);
        return $else === null ? null : ['?', $condition, $then, $else];
    }

    /**
     * One level of left-associative binary operators, and every tighter level under it.
     *
     * @param list<string> $tokens
     * @return array<int, mixed>|null
     */
    private static function binaryLevel(array $tokens, int &$position, int $level): ?array
    {
        if ($level > 6) {
            return self::unary($tokens, $position);
        }
        $left = self::binaryLevel($tokens, $position, $level + 1);
        while ($left !== null && (self::BINARY[$tokens[$position] ?? ''] ?? 0) === $level) {
            $operator = $tokens[$position++];
            $right = self::binaryLevel($tokens, $position, $level + 1);
            $left = $right === null ? null : [$operator, $left, $right];
        }
        return $left;
    }

    /**
     * @param list<string> $tokens
     * @return array<int, mixed>|null
     */
    private static function unary(array $tokens, int &$position): ?array
    {
        $token = $tokens[$position] ?? null;
        if ($token === '!' || $token === '-') {
            $position++;
            $operand = self::unary($tokens, $position);
            return $operand === null ? null : [$token === '!' ? '!' : 'neg', $operand];
        }
        if ($token === '(') {
            $position++;
            $inner = self::ternary($tokens, $position);
            if ($inner === null || ($tokens[$position] ?? '') !== ')') {
                return null;
            }
            $position++;
            return $inner;
        }
        if ($token === 'n') {
            $position++;
            return ['n'];
        }
        if ($token !== null && ctype_digit($token)) {
            $position++;
            return ['num', (int) $token];
        }
        return null;
    }
}
