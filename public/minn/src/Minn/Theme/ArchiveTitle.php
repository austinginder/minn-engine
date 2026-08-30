<?php

declare(strict_types=1);

namespace Minn\Theme;

use DateTimeImmutable;
use DateTimeZone;
use Minn\Front\Kind;
use Minn\Front\Resolution;
use Minn\Runtime\Runtime;
use Minn\Support\Html;

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
     * @param string|null $dateFormat the site's date_format option, for day archives
     * @return array{string, string} the label (no colon) and the escaped bare name; both empty when the view has none
     */
    public static function parts(Resolution $resolution, ?string $dateFormat): array
    {
        $record = $resolution->record ?? [];
        return match ($resolution->kind) {
            Kind::Category => ['Category', Html::esc((string) ($record['name'] ?? ''))],
            Kind::Tag => ['Tag', Html::esc((string) ($record['name'] ?? ''))],
            Kind::Taxonomy => [self::taxonomyLabel((string) ($record['taxonomy'] ?? '')), Html::esc((string) ($record['name'] ?? ''))],
            Kind::Author => ['Author', Html::esc((string) ($record['display_name'] ?? $resolution->authorName ?? ''))],
            Kind::Date => self::dateParts($resolution->date, $dateFormat),
            default => ['', ''],
        };
    }

    /** The reference's prefixed shape: `Category: <span>Uncategorized</span>`. */
    public static function compose(string $label, string $name): string
    {
        return $label . ': <span>' . $name . '</span>';
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
            return ['Day', (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $utc))->format($dateFormat !== null && $dateFormat !== '' ? $dateFormat : 'F j, Y')];
        }
        if ($month !== null) {
            return ['Month', (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $utc))->format('F Y')];
        }
        return ['Year', (string) $year];
    }

    /** The singular label a plugin registered for its taxonomy, for the archive title prefix. */
    private static function taxonomyLabel(string $taxonomy): string
    {
        $row = Runtime::booted() ? Runtime::registry()->taxonomy($taxonomy) : null;
        return (string) ($row['labels']['singular_name'] ?? $row['label'] ?? ucfirst($taxonomy));
    }
}
