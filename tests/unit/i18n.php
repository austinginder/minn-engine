<?php

declare(strict_types=1);

use Minn\I18n\Catalog;
use Minn\I18n\MoFile;
use Minn\I18n\PluralExpression;
use Minn\I18n\PluralRule;

/** Plural rules, the .mo round trip, and catalog lookups; the reference's answers are in contracts/fixtures/api/l10n.json. */
$forms = static fn (string $header, array $counts): array => array_map(static fn (int $n): int => PluralRule::fromHeader($header)->index($n), $counts);
$polish = 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);';
$arabic = 'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);';
$value = static fn (string $expression, int $n): ?int => ($tree = PluralExpression::parse($expression)) === null ? null : PluralExpression::evaluate($tree, $n);
return [
    'Polish takes three forms by the last digits' => static fn () => $forms($polish, [1, 2, 4, 5, 12, 21, 22, 112]) === [0, 1, 1, 2, 2, 2, 1, 2],
    'Arabic takes six' => static fn () => $forms($arabic, [0, 1, 2, 3, 10, 11, 99, 100, 102, 103]) === [0, 1, 2, 3, 3, 4, 4, 5, 5, 3],
    'no header is English' => static fn () => $forms('', [0, 1, 2]) === [1, 0, 1] && PluralRule::fromHeader(null)->count === 2,
    'an expression that does not parse sends every number to the first form' => static fn () => $forms('nplurals=3; plural=n > ;', [0, 1, 5]) === [0, 0, 0],
    'comparisons bind tighter than equality, equality than &&' => static fn () => $value('1 < 2 == 1 && 0 || 1', 0) === 1,
    'the ternary binds to the right' => static fn () => $value('n == 1 ? 10 : n == 2 ? 20 : 30', 2) === 20,
    'unary not and minus' => static fn () => $value('!n', 0) === 1 && $value('-n + 5', 2) === 3,
    'division and remainder by zero are zero, not an error' => static fn () => $value('n / 0', 4) === 0 && $value('n % 0', 4) === 0,
    'anything outside the grammar is refused' => static fn () => PluralExpression::parse('n; system(1)') === null && PluralExpression::parse('(n') === null && PluralExpression::parse('') === null,
    'a .mo written and read back keeps headers, contexts and plurals' => static function (): bool {
        $bytes = MoFile::write(['Plural-Forms' => 'nplurals=2; plural=(n != 1);'], [
            'Hello' => ['translations' => ['Hola'], 'plural' => null],
            "ctx\x04Post" => ['translations' => ['Entrada'], 'plural' => null],
            '%d item' => ['translations' => ['%d elemento', '%d elementos'], 'plural' => '%d items'],
        ]);
        $catalog = MoFile::read($bytes);
        return $catalog !== null && $catalog->translate('Hello') === 'Hola' && $catalog->translate(Catalog::key('Post', 'ctx')) === 'Entrada'
            && $catalog->translatePlural('%d item', 5) === '%d elementos' && $catalog->entries['%d item']['plural'] === '%d items'
            && $catalog->headerValue('plural-forms') === 'nplurals=2; plural=(n != 1);';
    },
    'bytes that are not a .mo read as nothing' => static fn () => MoFile::read('not a catalog at all, just text') === null && MoFile::read('') === null,
    'an empty translation is no translation' => static fn () => Catalog::from([], ['x' => ['translations' => [''], 'plural' => null]])->translate('x') === null,
];
