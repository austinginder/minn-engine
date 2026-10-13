<?php

declare(strict_types=1);

use Minn\I18n\LocalDates;

/** Dates in the site's language: with no runtime nothing is loaded, so the English names stand, written as PHP writes them. */
$at = new DateTimeImmutable('2026-08-28 14:05:00', new DateTimeZone('UTC'));
return [
    'the names a format asks for are the English ones with nothing loaded' => static function () use ($at) {
        $out = LocalDates::format($at, 'l, F j, Y g:i a');
        return $out === 'Friday, August 28, 2026 2:05 pm' ?: $out;
    },
    'abbreviations and capital meridiems' => static function () use ($at) {
        $out = LocalDates::format($at, 'D M j A');
        return $out === 'Fri Aug 28 PM' ?: $out;
    },
    'a backslashed character stays as written' => static function () use ($at) {
        $out = LocalDates::format($at, '\\F \\l F');
        return $out === 'F l August' ?: $out;
    },
    'the formats without names are PHP\'s own' => static function () use ($at) {
        $out = LocalDates::format($at, 'Y-m-d\\TH:i:s');
        return $out === $at->format('Y-m-d\\TH:i:s') ?: $out;
    },
];
