<?php
/**
 * The locale's names when translations answer (probe locale-names): a new
 * WP_Locale built while the gettext filters return chosen translations. The
 * initials and abbreviations are keyed by the translated names; an
 * untranslated number separator falls back to the English one; only "rtl"
 * changes the text direction and only a characters type changes the word
 * count type, so a second init() without the translations keeps both.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$translating = static function (array $map, callable $run) {
    $answer = static fn ($translation, $text, $context = null) => $map[$context === null ? $text : "{$context}|{$text}"] ?? $translation;
    add_filter('gettext', $answer, 10, 2);
    add_filter('gettext_with_context', $answer, 10, 3);
    $result = $run();
    remove_filter('gettext', $answer, 10);
    remove_filter('gettext_with_context', $answer, 10);
    return $result;
};
$names = static fn (WP_Locale $locale) => ['weekday' => $locale->weekday, 'weekday_initial' => $locale->weekday_initial, 'weekday_abbrev' => $locale->weekday_abbrev, 'month' => $locale->month, 'month_genitive' => $locale->month_genitive, 'month_abbrev' => $locale->month_abbrev, 'meridiem' => $locale->meridiem, 'number_format' => $locale->number_format, 'list_item_separator' => $locale->list_item_separator, 'text_direction' => $locale->text_direction, 'word_count_type' => $locale->word_count_type];

$spanish = ['Sunday' => 'Domingo', 'Sunday initial|S' => 'D', 'Sun' => 'Dom', 'January' => 'Enero', 'genitive|January' => 'de enero', 'January abbreviation|Jan' => 'Ene', 'am' => 'a. m.', 'PM' => 'P. M.', 'number_format_thousands_sep' => '.', 'number_format_decimal_point' => ',', ', ' => '、', 'text direction|ltr' => 'rtl', 'Word count type. Do not translate!|words' => 'characters_excluding_spaces'];
$say('in English', $names(new WP_Locale()));
$say('translated', $translating($spanish, static fn () => $names(new WP_Locale())));
$say('odd translations', $translating(['text direction|ltr' => 'RTL', 'Word count type. Do not translate!|words' => 'letters', 'number_format_thousands_sep' => '', 'number_format_decimal_point' => 'number_format_decimal_point', ', ' => ''], static fn () => $names(new WP_Locale())));
$say('the other characters type', $translating(['Word count type. Do not translate!|words' => 'characters_including_spaces'], static fn () => (new WP_Locale())->word_count_type));
$kept = $translating(['text direction|ltr' => 'rtl', 'Word count type. Do not translate!|words' => 'characters_including_spaces'], static fn () => new WP_Locale());
$kept->init();
$say('init() again without the translations', ['text_direction' => $kept->text_direction, 'word_count_type' => $kept->word_count_type, 'weekday' => $kept->weekday[0]]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
