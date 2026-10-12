<?php

declare(strict_types=1);

namespace Minn\Rest;

/** Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along. */
final class Links
{
    /**
     * One link as a response serves it: its attributes, then its href; a
     * self link's target hints after the href, where the reference adds them.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public static function item(string $rel, string $href, array $attributes): array
    {
        $hints = $rel === 'self' && array_key_exists('targetHints', $attributes) ? $attributes['targetHints'] : null;
        unset($attributes['href']);
        if ($hints !== null) {
            unset($attributes['targetHints']);
        }
        return $attributes + ['href' => $href] + ($hints === null ? [] : ['targetHints' => $hints]);
    }

    /**
     * An answer's compact links as the reference keeps them on a response:
     * each link under its full relation (a curie's short name expanded,
     * the curies themselves dropped), with its href and its other
     * attributes.
     *
     * @param array<string, mixed> $links
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function expanded(array $links): array
    {
        $curies = [];
        foreach ((array) ($links['curies'] ?? []) as $curie) {
            $curies[(string) ($curie['name'] ?? '')] = (string) ($curie['href'] ?? '');
        }
        $out = [];
        foreach ($links as $rel => $items) {
            if ($rel === 'curies') {
                continue;
            }
            [$prefix, $name] = array_pad(explode(':', (string) $rel, 2), 2, null);
            $full = $name !== null && !str_starts_with($name, '//') && isset($curies[$prefix]) ? str_replace('{rel}', $name, $curies[$prefix]) : (string) $rel;
            foreach ((array) $items as $link) {
                if (is_array($link) && isset($link['href'])) {
                    $out[] = [$full, (string) $link['href'], array_diff_key($link, ['href' => true])];
                }
            }
        }
        return $out;
    }

    /**
     * Links with their curies applied, as the reference compacts them.
     *
     * @param array<string, mixed> $links rel => items
     * @param list<array{name: string, href: string, templated?: bool}> $curies
     * @return array<string, mixed>
     */
    public static function compact(array $links, array $curies): array
    {
        if ($links === []) {
            return [];
        }
        $used = [];
        foreach ($links as $rel => $items) {
            foreach ($curies as $curie) {
                $prefix = substr($curie['href'], 0, (int) strpos($curie['href'], '{rel}'));
                if (!str_starts_with((string) $rel, $prefix)) {
                    continue;
                }
                $used[$curie['name']] = $curie;
                if (preg_match('!' . str_replace('\{rel\}', '(.+)', preg_quote($curie['href'], '!')) . '!', (string) $rel, $m)) {
                    $links[$curie['name'] . ':' . $m[1]] = $items;
                    unset($links[$rel]);
                    break;
                }
            }
        }
        if ($used !== []) {
            $links['curies'] = array_values($used);
        }
        return $links;
    }
}
