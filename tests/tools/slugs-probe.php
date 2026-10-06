<?php
/**
 * Slugs as the reference makes them: sanitize_title and
 * sanitize_title_with_dashes in each context over titles with entities,
 * colons, dashes, accents, symbols and long multibyte runs (cut at 200
 * bytes without splitting a character); utf8_uri_encode's length and ASCII
 * modes; remove_accents over Latin, Greek, Cyrillic and combining ranges and
 * in the locales with spellings of their own. Same protocol as
 * api-probe.php; nothing is saved.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$titles = ['Order &ndash; Oct 06, 2026 @ 03:07 PM', 'A: b', 'x&amp;y', 'café & crème', 'a — b – c', '10:30', 'a.b.c', "it's", 'a/b\\c', '%20 spaced', '50% off!', 'Ünïcödé', '&#8211; dash', 'a&nbsp;b', '<b>bold</b> title', 'a…b', '“quoted”', 'x × y', '1920×1080', 'C++ & C#', 'Привет мир', 'Γειά σου', 'e' . "\u{0301}" . 'te', str_repeat('a', 250), str_repeat('漢', 100), str_repeat('ab漢', 40)];
foreach ($titles as $title) {
    $say('title ' . mb_substr($title, 0, 40), [sanitize_title($title), sanitize_title_with_dashes($title, '', 'save'), sanitize_title_with_dashes($title), sanitize_title($title, 'fallback', 'query')]);
}
$say('an empty title falls back', [sanitize_title('', 'Fall Back'), sanitize_title('%%%', 'x')]);
$say('utf8_uri_encode', [utf8_uri_encode('aé漢'), utf8_uri_encode('aé漢', 0, true), utf8_uri_encode('aé漢', 5), utf8_uri_encode('aé漢', 7), utf8_uri_encode('abcdef', 2), utf8_uri_encode('a b/c', 0, true), utf8_uri_encode('a b/c', 3, true), utf8_uri_encode('AZaz09-_.~!', 0, true)]);
$sample = [];
foreach ([[0xA0, 0x17F, 1], [0x180, 0x24F, 3], [0x370, 0x3FF, 5], [0x400, 0x4FF, 7], [0x1E00, 0x1EFF, 5], [0x2000, 0x20CF, 4], [0x300, 0x36F, 3]] as [$from, $to, $step]) {
    for ($cp = $from; $cp <= $to; $cp += $step) {
        $char = mb_chr($cp, 'UTF-8');
        $plain = remove_accents($char, 'en_US');
        if ($plain !== $char) {
            $sample[sprintf('%04X', $cp)] = $plain;
        }
    }
}
$say('remove_accents across ranges', $sample);
foreach (['de_DE', 'da_DK', 'ca', 'sr_RS', 'bs_BA', 'fr_FR'] as $locale) {
    $say("remove_accents in {$locale}", remove_accents('Ä Ö Ü ß ẞ å æ ø Å Đ đ l·l é', $locale));
}
$say('remove_accents of Latin-1 bytes', bin2hex(remove_accents("a\xE9b\x8C\xDF", 'en_US')));
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
