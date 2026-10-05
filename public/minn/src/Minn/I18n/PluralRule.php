<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * A language's plural rule from its Plural-Forms header: how many forms it
 * has and which form a number takes. A file without the header follows
 * English (two forms, n != 1); an expression that does not parse sends
 * every number to the first form, as the reference does.
 */
final readonly class PluralRule
{
    /** @param array<int, mixed>|null $tree */
    private function __construct(public int $count, public string $expression, private ?array $tree)
    {
    }

    /** English: one form for 1, the other for everything else. */
    public static function english(): self
    {
        return new self(2, 'n != 1', PluralExpression::parse('n != 1'));
    }

    /** The rule a Plural-Forms header states. */
    public static function fromHeader(?string $header): self
    {
        if ($header === null || preg_match('/nplurals\s*=\s*(\d+)/i', $header, $count) !== 1) {
            return self::english();
        }
        $expression = preg_match('/plural\s*=\s*(.*?)\s*;?\s*$/is', $header, $m) === 1 ? $m[1] : '';
        return self::fromExpression(max(1, (int) $count[1]), $expression);
    }

    /** A rule from a form count and an expression in n. */
    public static function fromExpression(int $count, string $expression): self
    {
        return new self($count, $expression, PluralExpression::parse($expression));
    }

    /** The form a number takes. */
    public function index(int $n): int
    {
        return $this->tree === null ? 0 : PluralExpression::evaluate($this->tree, $n);
    }
}
