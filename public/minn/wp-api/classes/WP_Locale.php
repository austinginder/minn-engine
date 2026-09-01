<?php

/**
 * The locale's date, time, and number names. Plugin code reads the
 * globals directly (WooCommerce's block settings read weekday_abbrev),
 * so the members matter as much as the getters. English strings, from
 * the captured shape; the engine renders no core translations yet, and
 * every string passes through the translation filters on the way out.
 */
class WP_Locale
{
    public $weekday = [];
    public $weekday_initial = [];
    public $weekday_abbrev = [];
    public $month = [];
    public $month_genitive = [];
    public $month_abbrev = [];
    public $meridiem = [];
    public $text_direction = 'ltr';
    public $number_format = [];
    public $list_item_separator = ', ';
    public $word_count_type = 'words';

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($days as $index => $day) {
            $this->weekday[$index] = __($day);
            $this->weekday_initial[__($day)] = __(substr($day, 0, 1));
            $this->weekday_abbrev[__($day)] = __(substr($day, 0, 3));
        }
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];
        foreach ($months as $index => $month) {
            $key = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $this->month[$key] = __($month);
            $this->month_genitive[$key] = __($month);
            $this->month_abbrev[__($month)] = __(substr($month, 0, 3));
        }
        // May is its own abbreviation; every other month keeps three letters.
        $this->meridiem = ['am' => 'am', 'pm' => 'pm', 'AM' => 'AM', 'PM' => 'PM'];
        $this->number_format = ['thousands_sep' => ',', 'decimal_point' => '.'];
        $this->text_direction = 'ltr';
        $this->list_item_separator = ', ';
        $this->word_count_type = 'words';
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
        return $this->month[str_pad((string) (int) $month_number, 2, '0', STR_PAD_LEFT)] ?? '';
    }

    public function get_month_abbrev($month_name)
    {
        return $this->month_abbrev[$month_name] ?? '';
    }

    public function get_month_genitive($month_number)
    {
        return $this->month_genitive[str_pad((string) (int) $month_number, 2, '0', STR_PAD_LEFT)] ?? '';
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
