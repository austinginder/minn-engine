<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Support\Html;

/**
 * The language picker's markup. English always leads the list and carries the
 * empty value, because "no locale" and "American English" are the same choice.
 * When the picker also offers what could be downloaded, the two halves split
 * into named groups; otherwise every option sits in one flat list.
 *
 * The attribute order is not tidy and is not ours to tidy: the English option
 * carries `data-installed` before `selected`, an installed translation carries
 * it after, and themes and plugins have been matching on that markup for
 * years.
 */
final class LanguageChoices
{
    private const ENGLISH = 'English (United States)';

    /**
     * The language select's HTML, installed languages first.
     *
     * @param list<string> $installed locale codes the site already holds
     * @param array<string, array{language: string, native_name: string, iso: array<int|string, string>}> $available every translation the directory offers, keyed by locale
     */
    public static function dropdown(string $name, string $id, array $installed, array $available, string $selected, bool $offerAvailable): string
    {
        $english = '<option value="" lang="en" data-installed="1"'
            . ($selected === '' || $selected === 'en_US' ? " selected='selected'" : '') . '>' . self::ENGLISH . '</option>';
        $held = [$english];
        $offered = [];
        foreach ($available as $locale => $translation) {
            $option = self::option((string) $locale, $translation, $selected, in_array((string) $locale, $installed, true));
            if (in_array((string) $locale, $installed, true)) {
                $held[] = $option;
                continue;
            }
            $offered[] = $option;
        }
        $body = $offerAvailable
            ? "<optgroup label=\"Installed\">\n" . implode("\n", $held) . "\n</optgroup>\n"
                . "<optgroup label=\"Available\">\n" . implode("\n", $offered) . "\n</optgroup>"
            : implode("\n", $held);
        return '<select name="' . Html::attr($name) . '" id="' . Html::attr($id) . '">' . $body . '</select>';
    }

    /** @param array{native_name: string, iso: array<int|string, string>} $translation */
    private static function option(string $locale, array $translation, string $selected, bool $installed): string
    {
        $iso = (string) ($translation['iso'][1] ?? $translation['iso'][0] ?? '');
        return '<option value="' . Html::attr($locale) . '"'
            . ($iso === '' ? '' : ' lang="' . Html::attr($iso) . '"')
            . ($selected === $locale ? " selected='selected'" : '')
            . ($installed ? ' data-installed="1"' : '')
            . '>' . Html::esc((string) $translation['native_name']) . '</option>';
    }
}
