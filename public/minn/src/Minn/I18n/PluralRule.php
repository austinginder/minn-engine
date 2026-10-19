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
        // The expression as the header writes it, a closing semicolon included; it is parsed without one.
        $expression = preg_match('/plural\s*=\s*(.*?)\s*$/is', $header, $m) === 1 ? $m[1] : '';
        return self::fromExpression(max(1, (int) $count[1]), $expression);
    }

    /** A rule from a form count and an expression in n. */
    public static function fromExpression(int $count, string $expression): self
    {
        return new self($count, $expression, PluralExpression::parse(rtrim($expression, "; \t\r\n")));
    }

    /** The form a number takes. */
    public function index(int $n): int
    {
        return $this->tree === null ? 0 : PluralExpression::evaluate($this->tree, $n);
    }

    /** The form count and the expression, as nplurals_and_expression_from_header() answers them. @return array{0: int, 1: string} */
    public function pair(): array
    {
        return [$this->count, $this->expression];
    }

    /** The form a number takes, as the function make_plural_form_function() hands back. @return \Closure(mixed): int */
    public function chooser(): \Closure
    {
        return fn ($n): int => $this->index((int) $n);
    }
}
