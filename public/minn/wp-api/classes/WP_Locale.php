<?php

use Minn\Support\Locale as MinnLocale;

/**
 * The locale's date, time, and number names. Plugin code reads the
 * globals directly (WooCommerce's block settings read weekday_abbrev),
 * so the members matter as much as the getters. English strings, from
 * the captured shape; the engine renders no core translations yet, and
 * every string passes through the translation filters on the way out.
 */
#[AllowDynamicProperties]
class WP_Locale
{
    public $weekday = [];
    public $weekday_initial = [];
    public $weekday_abbrev = [];
    public $month = [];
    public $month_genitive = [];
    public $month_abbrev = [];
    public $meridiem = [];
    public $text_direction = MinnLocale::TEXT_DIRECTION;
    public $number_format = [];
    public $list_item_separator = MinnLocale::LIST_ITEM_SEPARATOR;
    public $word_count_type = MinnLocale::WORD_COUNT_TYPE;

    public function __construct()
    {
        $this->init();
    }

    /**
     * The names, each through the translation functions, in the reference's
     * order (probe translations-early): the weekdays, their initials and
     * abbreviations keyed by the translated name, the months, their genitive
     * forms, their abbreviations, then the meridiem, the number format (an
     * untranslated separator falls back to the English one), the list
     * separator, the text direction (only "rtl" changes it) and the word
     * count type (only a characters type changes it).
     */
    public function init()
    {
        foreach (MinnLocale::WEEKDAYS as $index => $day) {
            $this->weekday[$index] = __($day);
        }
        foreach (MinnLocale::WEEKDAYS as $index => $day) {
            $this->weekday_initial[$this->weekday[$index]] = _x(MinnLocale::weekdayInitial($day), "{$day} initial");
        }
        foreach (MinnLocale::WEEKDAYS as $index => $day) {
            $this->weekday_abbrev[$this->weekday[$index]] = __(MinnLocale::abbreviation($day));
        }
        foreach (MinnLocale::MONTHS as $index => $month) {
            $this->month[MinnLocale::monthKey($index + 1)] = __($month);
        }
        foreach (MinnLocale::MONTHS as $index => $month) {
            $this->month_genitive[MinnLocale::monthKey($index + 1)] = _x($month, 'genitive');
        }
        foreach (MinnLocale::MONTHS as $index => $month) {
            $this->month_abbrev[$this->month[MinnLocale::monthKey($index + 1)]] = _x(MinnLocale::abbreviation($month), "{$month} abbreviation");
        }
        $this->meridiem = array_map(static fn (string $text) => __($text), MinnLocale::MERIDIEM);
        $thousands = __('number_format_thousands_sep');
        $decimal = __('number_format_decimal_point');
        $this->number_format = [
            'thousands_sep' => $thousands === 'number_format_thousands_sep' ? MinnLocale::NUMBER_FORMAT['thousands_sep'] : $thousands,
            'decimal_point' => $decimal === 'number_format_decimal_point' ? MinnLocale::NUMBER_FORMAT['decimal_point'] : $decimal,
        ];
        $this->list_item_separator = __(', ');
        if (_x('ltr', 'text direction') === 'rtl') {
            $this->text_direction = 'rtl';
        }
        $count = _x('words', 'Word count type. Do not translate!');
        if (in_array($count, ['characters_excluding_spaces', 'characters_including_spaces'], true)) {
            $this->word_count_type = $count;
        }
    }

    public function get_weekday($weekday_number)
    {
        return $this->weekday[$weekday_number] ?? '';
    }

    public function get_weekday_initial($weekday_name)
    {
        return $this->weekday_initial[$weekday_name] ?? '';
    }

    public function get_weekday_abbrev($weekday_name)
    {
        return $this->weekday_abbrev[$weekday_name] ?? '';
    }

    public function get_month($month_number)
    {
        return $this->month[MinnLocale::monthKey($month_number)] ?? '';
    }

    public function get_month_abbrev($month_name)
    {
        return $this->month_abbrev[$month_name] ?? '';
    }

    public function get_month_genitive($month_number)
    {
        return $this->month_genitive[MinnLocale::monthKey($month_number)] ?? '';
    }

    public function get_meridiem($meridiem)
    {
        return $this->meridiem[$meridiem] ?? '';
    }

    public function is_rtl()
    {
        return $this->text_direction === 'rtl';
    }

    public function get_list_item_separator()
    {
        return $this->list_item_separator;
    }

    public function get_word_count_type()
    {
        return $this->word_count_type;
    }

    public function register_globals()
    {
        $GLOBALS['weekday'] = $this->weekday;
        $GLOBALS['weekday_initial'] = $this->weekday_initial;
        $GLOBALS['weekday_abbrev'] = $this->weekday_abbrev;
        $GLOBALS['month'] = $this->month;
        $GLOBALS['month_abbrev'] = $this->month_abbrev;
    }

    public function _strings_for_pot()
    {
    }
}
