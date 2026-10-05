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

    public function init()
    {
        foreach (MinnLocale::WEEKDAYS as $index => $day) {
            $this->weekday[$index] = __($day);
            $this->weekday_initial[__($day)] = __(MinnLocale::weekdayInitial($day));
            $this->weekday_abbrev[__($day)] = __(MinnLocale::abbreviation($day));
        }
        foreach (MinnLocale::MONTHS as $index => $month) {
            $key = MinnLocale::monthKey($index + 1);
            $this->month[$key] = __($month);
            $this->month_genitive[$key] = __($month);
            $this->month_abbrev[__($month)] = __(MinnLocale::abbreviation($month));
        }
        $this->meridiem = MinnLocale::MERIDIEM;
        $this->number_format = MinnLocale::NUMBER_FORMAT;
        $this->text_direction = MinnLocale::TEXT_DIRECTION;
        $this->list_item_separator = MinnLocale::LIST_ITEM_SEPARATOR;
        $this->word_count_type = MinnLocale::WORD_COUNT_TYPE;
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
