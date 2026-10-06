<?php
/**
 * Captures the reference's remove_accents as data for
 * public/minn/data/accents.json: what each character of the Basic
 * Multilingual Plane becomes (only those it changes), what the locales
 * with their own spellings change differently, and the Latin-1 bytes a
 * non-UTF-8 string is read as. Run on the reference only:
 *
 *   cd wp-reference && wp eval-file <this> > public/minn/data/accents.json
 */

$locales = ['de_DE', 'de_DE_formal', 'de_CH', 'de_CH_informal', 'de_AT', 'da_DK', 'ca', 'sr_RS', 'bs_BA'];
$table = [];
$byLocale = array_fill_keys($locales, []);
for ($cp = 0x80; $cp <= 0xFFFF; $cp++) {
    if ($cp >= 0xD800 && $cp <= 0xDFFF) {
        continue;
    }
    $char = mb_chr($cp, 'UTF-8');
    if ($char === false) {
        continue;
    }
    $plain = remove_accents($char, 'en_US');
    if ($plain !== $char) {
        $table[$char] = $plain;
    }
    foreach ($locales as $locale) {
        $local = remove_accents($char, $locale);
        if ($local !== $plain) {
            $byLocale[$locale][$char] = $local;
        }
    }
}
// Sequences the table holds beyond single characters (the Catalan middle dot).
foreach (['l·l', 'L·L', 'l·', '·l'] as $sequence) {
    $local = remove_accents($sequence, 'ca');
    if ($local !== remove_accents($sequence, 'en_US')) {
        $byLocale['ca'][$sequence] = $local;
    }
}
$latin1 = [];
for ($byte = 0x80; $byte <= 0xFF; $byte++) {
    $out = remove_accents('a' . chr($byte) . 'b', 'en_US');
    if ($out !== 'a' . chr($byte) . 'b') {
        $latin1[sprintf('%02x', $byte)] = substr($out, 1, -1);
    }
}
echo json_encode(['table' => $table, 'locales' => array_filter($byLocale), 'latin1' => $latin1, 'decomposed' => remove_accents("e\u{0301}a\u{0300}", 'en_US')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
