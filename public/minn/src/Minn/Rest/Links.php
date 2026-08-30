<?php

declare(strict_types=1);

namespace Minn\Rest;

/** Response link relations compacted through CURIEs: a rel that matches a CURIE's template becomes `name:suffix`, and the used CURIEs ride along. */
final class Links
{
    /**
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
