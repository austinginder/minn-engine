<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The timezone choices a settings form offers (wp_timezone_choice, probe
 * placeholder-helpers): the zones of each continent, cities in natural
 * order (a zone with three parts reads "City - Sub-city"), then UTC, then
 * the offsets set by hand. "Select a city" leads when nothing is chosen, and
 * a real zone outside the list (an old name, Etc/) leads when it is the one
 * chosen. Each choice is a line of its own.
 */
final class TimezoneChoice
{
    private const CONTINENTS = ['Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific'];

    /** The offsets offered by hand, in hours. */
    private const OFFSETS = [-12, -11.5, -11, -10.5, -10, -9.5, -9, -8.5, -8, -7.5, -7, -6.5, -6, -5.5, -5, -4.5, -4, -3.5, -3, -2.5, -2, -1.5, -1, -0.5, 0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 5.75, 6, 6.5, 7, 7.5, 8, 8.5, 8.75, 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.75, 13, 13.75, 14];

    /**
     * The choices as option lines, the chosen zone marked.
     *
     * @param \Closure(string, string): string $translate a text in a text domain, translated
     */
    public static function html(string $selected, \Closure $translate): string
    {
        $zones = self::zones($translate);
        $lines = [];
        if ($selected === '') {
            $lines[] = '<option selected="selected" value="">' . Escape::html($translate('Select a city', 'default')) . '</option>';
        } elseif (self::outsideList($selected, array_column($zones, 'value'))) {
            $lines[] = self::option($selected, $selected, $selected);
        }
        foreach (self::groups($zones) as $continent => $group) {
            $lines[] = '<optgroup label="' . Escape::attr($continent) . '" dir="auto">';
            foreach ($group as $zone) {
                $lines[] = self::option($zone['value'], $zone['label'], $selected);
            }
            $lines[] = '</optgroup>';
        }
        $utc = $translate('UTC', 'default');
        array_push($lines, '<optgroup label="' . Escape::attr($utc) . '" dir="auto">', self::option('UTC', $utc, $selected), '</optgroup>');
        $lines[] = '<optgroup label="' . Escape::attr($translate('Manual Offsets', 'default')) . '" dir="auto">';
        foreach (self::OFFSETS as $offset) {
            [$value, $label] = self::offset($offset);
            $lines[] = self::option($value, $label, $selected);
        }
        $lines[] = '</optgroup>';
        return implode("\n", $lines);
    }

    /**
     * The continents' zones in order: continent, city, then sub-city, each
     * compared naturally and without case.
     *
     * @return list<array{value: string, continent: string, city: string, sub: string, label: string}>
     */
    private static function zones(\Closure $translate): array
    {
        $zones = [];
        foreach (timezone_identifiers_list() as $zone) {
            $parts = explode('/', $zone);
            if (!in_array($parts[0], self::CONTINENTS, true)) {
                continue;
            }
            $city = isset($parts[1]) ? $translate(str_replace('_', ' ', $parts[1]), 'continents-cities') : '';
            $sub = isset($parts[2]) ? $translate(str_replace('_', ' ', $parts[2]), 'continents-cities') : '';
            $zones[] = ['value' => $zone, 'continent' => $translate($parts[0], 'continents-cities'), 'city' => $city, 'sub' => $sub, 'label' => $sub === '' ? $city : "{$city} - {$sub}"];
        }
        usort($zones, static fn (array $a, array $b): int => strnatcasecmp($a['continent'], $b['continent']) ?: (strnatcasecmp($a['city'], $b['city']) ?: strnatcasecmp($a['sub'], $b['sub'])));
        return $zones;
    }

    /**
     * @param list<array{value: string, continent: string, city: string, sub: string, label: string}> $zones
     * @return array<string, list<array{value: string, continent: string, city: string, sub: string, label: string}>>
     */
    private static function groups(array $zones): array
    {
        $groups = [];
        foreach ($zones as $zone) {
            $groups[$zone['continent']][] = $zone;
        }
        return $groups;
    }

    /** Whether a chosen zone is a real one the list leaves out (not UTC, not an offset). @param list<string> $listed */
    private static function outsideList(string $selected, array $listed): bool
    {
        return $selected !== 'UTC' && !str_starts_with($selected, 'UTC') && !in_array($selected, $listed, true)
            && in_array($selected, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true);
    }

    /** An offset's value (UTC+5.75) and label (UTC+5:45). @return array{0: string, 1: string} */
    private static function offset(float|int $offset): array
    {
        $sign = $offset < 0 ? '-' : '+';
        $hours = abs($offset);
        $whole = (int) floor($hours);
        $minutes = (int) round(($hours - $whole) * 60);
        $number = $hours == $whole ? (string) $whole : (string) $hours;
        return ["UTC{$sign}{$number}", "UTC{$sign}{$whole}" . ($minutes === 0 ? '' : ':' . $minutes)];
    }

    /** One choice, marked when it is the chosen zone. */
    private static function option(string $value, string $label, string $chosen): string
    {
        return '<option ' . ($value === $chosen ? 'selected="selected" ' : '') . 'value="' . Escape::attr($value) . '" dir="auto">' . Escape::html($label) . '</option>';
    }
}
