<?php

declare(strict_types=1);

namespace Minn\Theme;

use DateTimeImmutable;
use DateTimeZone;
use Minn\Front\Kind;
use Minn\Front\Resolution;
use Minn\I18n\Gettext;
use Minn\I18n\LocalDates;
use Minn\Runtime\Runtime;
use Minn\Html;

/**
 * The label and name an archive titles itself with: `Category:` around the
 * term, `Author:` around the display name, the date formatted for its
 * granularity. One source of truth for the block path's query-title and
 * the classic path's get_the_archive_title(), which used to compute the
 * same labels separately. Search and post-type archives stay with their
 * callers: their captured shapes differ between the two paths.
 */
final class ArchiveTitle
{
    /**
     * The archive title's prefix and title for a resolution.
     *
     * @param string|null $dateFormat the site's date_format option, for day archives
     * @return array{string, string} the prefix (its colon included, in the site's language) and the escaped bare name; both empty when the view has none
     */
    public static function parts(Resolution $resolution, ?string $dateFormat): array
    {
        $record = $resolution->record ?? [];
        return match ($resolution->kind) {
            Kind::Category => [Gettext::inContext('Category:', 'category archive title prefix'), Html::esc((string) ($record['name'] ?? ''))],
            Kind::Tag => [Gettext::inContext('Tag:', 'tag archive title prefix'), Html::esc((string) ($record['name'] ?? ''))],
            Kind::Taxonomy => [sprintf(Gettext::inContext('%s:', 'taxonomy term archive title prefix'), self::taxonomyLabel((string) ($record['taxonomy'] ?? ''))), Html::esc((string) ($record['name'] ?? ''))],
            Kind::Author => [Gettext::inContext('Author:', 'author archive title prefix'), Html::esc((string) ($record['display_name'] ?? $resolution->authorName ?? ''))],
            Kind::Date => self::dateParts($resolution->date, $dateFormat),
            default => ['', ''],
        };
    }

    /** The reference's prefixed shape, `Category: <span>Uncategorized</span>`, through the language's own order. */
    public static function compose(string $prefix, string $name): string
    {
        return sprintf(Gettext::inContext('%1$s %2$s', 'archive title'), $prefix, '<span>' . $name . '</span>');
    }

    /**
     * @param array{0: int, 1: ?int, 2: ?int}|null $date
     * @return array{string, string}
     */
    private static function dateParts(?array $date, ?string $dateFormat): array
    {
        if ($date === null) {
            return ['', ''];
        }
        [$year, $month, $day] = $date;
        $utc = new DateTimeZone('UTC');
        if ($day !== null) {
            $format = $dateFormat !== null && $dateFormat !== '' ? $dateFormat : Gettext::inContext('F j, Y', 'daily archives date format');
            return [Gettext::inContext('Day:', 'date archive title prefix'), LocalDates::format(new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $utc), $format)];
        }
        if ($month !== null) {
            return [Gettext::inContext('Month:', 'date archive title prefix'), LocalDates::format(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $utc), Gettext::inContext('F Y', 'monthly archives date format'))];
        }
        return [Gettext::inContext('Year:', 'date archive title prefix'), LocalDates::format(new DateTimeImmutable(sprintf('%04d-01-01', $year), $utc), Gettext::inContext('Y', 'yearly archives date format'))];
    }

    /** The singular label a plugin registered for its taxonomy, for the archive title prefix. */
    private static function taxonomyLabel(string $taxonomy): string
    {
        $row = Runtime::booted() ? Runtime::registry()->taxonomy($taxonomy) : null;
        return (string) ($row['labels']['singular_name'] ?? $row['label'] ?? ucfirst($taxonomy));
    }
}
