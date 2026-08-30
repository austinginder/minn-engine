<?php

declare(strict_types=1);

namespace Minn\Support;

use Normalizer;

/** Accented and special Latin characters to their plain ASCII spelling; a character with no ASCII form stays as it is. */
final class Accents
{
    private const SPELLINGS = ['ß' => 'ss', 'Æ' => 'AE', 'æ' => 'ae', 'Œ' => 'OE', 'œ' => 'oe', 'Ø' => 'O', 'ø' => 'o', 'Đ' => 'D', 'đ' => 'd', 'Ł' => 'L', 'ł' => 'l', 'Þ' => 'TH', 'þ' => 'th', 'Ð' => 'D', 'ð' => 'd', '€' => 'E', '£' => '', '“' => '', '”' => '', '‘' => '', '’' => '', '–' => '-', '—' => '-', '…' => ''];

    public static function strip(string $text): string
    {
        if (!preg_match('/[\x80-\xff]/', $text)) {
            return $text;
        }
        $out = '';
        foreach (preg_split('//u', strtr($text, self::SPELLINGS), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (ord($char) < 0x80) {
                $out .= $char;
                continue;
            }
            $stripped = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($char, Normalizer::FORM_D));
            $out .= $stripped === '' || preg_match('/[\x80-\xff]/', $stripped) ? $char : $stripped;
        }
        return $out;
    }
}
