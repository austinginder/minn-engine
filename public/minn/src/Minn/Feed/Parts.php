<?php

declare(strict_types=1);

namespace Minn\Feed;

use Closure;

/**
 * The repeating parts of a feed element read out of the tree as raw
 * values: links by relation, categories, and enclosures (Media RSS
 * contents, Atom enclosure links, RSS enclosures). The feed object
 * sanitizes and wraps them; each raw value travels with the node it came
 * from, so a relative URL resolves against that node's base.
 */
final class Parts
{
    public const IANA = 'http://www.iana.org/assignments/relation/';
    public const MEDIA = 'http://search.yahoo.com/mrss/';
    private const DC = ['http://purl.org/dc/elements/1.1/', 'http://purl.org/dc/elements/1.0/'];

    /**
     * Links by relation: Atom links (rel defaults to alternate, IANA names
     * answer to both spellings), then the RSS-style link elements and the
     * given extra alternates.
     *
     * @param array<string, array<string, list<array<string, mixed>>>> $child the element's children
     * @param list<array{0: string, 1: string}> $plain RSS-style link tags, by namespace and name
     * @param list<string> $more alternates already in their final form
     * @param Closure(string, array<string, mixed>): string $iri a URL as the feed hands it out, given its node
     * @return array<string, list<string>>
     */
    public static function links(array $child, array $plain, array $more, Closure $iri): array
    {
        $links = ['alternate' => []];
        foreach ([Tree::ATOM_10, Tree::ATOM_03] as $ns) {
            foreach ($child[$ns]['link'] ?? [] as $node) {
                $href = $node['attribs']['']['href'] ?? null;
                if ($href !== null) {
                    $rel = trim((string) ($node['attribs']['']['rel'] ?? 'alternate'));
                    $links[str_starts_with($rel, self::IANA) ? substr($rel, strlen(self::IANA)) : $rel][] = $iri((string) $href, $node);
                }
            }
        }
        foreach ($plain as [$ns, $tag]) {
            $node = $child[$ns][$tag][0] ?? null;
            if ($node !== null && trim((string) $node['data']) !== '') {
                $links['alternate'][] = $iri((string) $node['data'], $node);
            }
        }
        array_push($links['alternate'], ...$more);
        foreach ($links as $rel => $list) {
            if (!str_contains((string) $rel, ':')) {
                $links[self::IANA . $rel] = $list;
            }
        }
        return $links;
    }

    /**
     * Categories as raw text: Atom (term, scheme, label), RSS 2.0 (the text,
     * the domain as scheme), Dublin Core subjects.
     *
     * @param array<string, array<string, list<array<string, mixed>>>> $child
     * @return list<array{term: ?string, scheme: ?string, label: ?string, type: string}>
     */
    public static function categories(array $child): array
    {
        $out = [];
        foreach ([Tree::ATOM_10, Tree::ATOM_03] as $ns) {
            foreach ($child[$ns]['category'] ?? [] as $node) {
                $a = $node['attribs'][''] ?? [];
                $out[] = ['term' => $a['term'] ?? null, 'scheme' => $a['scheme'] ?? null, 'label' => $a['label'] ?? null, 'type' => 'category'];
            }
        }
        foreach ($child['']['category'] ?? [] as $node) {
            $out[] = ['term' => (string) $node['data'], 'scheme' => $node['attribs']['']['domain'] ?? null, 'label' => null, 'type' => 'category'];
        }
        foreach (self::DC as $ns) {
            foreach ($child[$ns]['subject'] ?? [] as $node) {
                $out[] = ['term' => (string) $node['data'], 'scheme' => null, 'label' => null, 'type' => 'subject'];
            }
        }
        return $out;
    }

    /**
     * An item's enclosures as raw fields, Media RSS contents first (with the
     * item's thumbnails when a content has none of its own), then Atom
     * enclosure links, then RSS enclosures. "node" is where the URL came from.
     *
     * @param array<string, mixed> $item the item's node
     * @return list<array<string, mixed>>
     */
    public static function enclosures(array $item): array
    {
        $child = $item['child'] ?? [];
        $contents = $child[self::MEDIA]['content'] ?? [];
        foreach ($child[self::MEDIA]['group'] ?? [] as $group) {
            array_push($contents, ...($group['child'][self::MEDIA]['content'] ?? []));
        }
        $out = [];
        foreach ($contents as $node) {
            if (isset($node['attribs']['']['url'])) {
                $out[] = self::media($node, self::thumbnails($node) ?: self::thumbnails($item));
            }
        }
        foreach ($child[Tree::ATOM_10]['link'] ?? [] as $node) {
            $a = $node['attribs'][''] ?? [];
            if (($a['rel'] ?? '') === 'enclosure' && isset($a['href'])) {
                $out[] = ['url' => (string) $a['href'], 'node' => $node, 'type' => $a['type'] ?? null, 'length' => isset($a['length']) ? (int) $a['length'] : null, 'title' => $a['title'] ?? null];
            }
        }
        foreach ($child['']['enclosure'] ?? [] as $node) {
            $a = $node['attribs'][''] ?? [];
            if (isset($a['url'])) {
                $out[] = ['url' => (string) $a['url'], 'node' => $node, 'type' => $a['type'] ?? null, 'length' => isset($a['length']) ? (int) $a['length'] : null];
            }
        }
        return $out;
    }

    /**
     * The media:thumbnail URLs directly under an element, each with its node.
     *
     * @param array<string, mixed> $node
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public static function thumbnails(array $node): array
    {
        $out = [];
        foreach ($node['child'][self::MEDIA]['thumbnail'] ?? [] as $thumbnail) {
            if (isset($thumbnail['attribs']['']['url'])) {
                $out[] = [(string) $thumbnail['attribs']['']['url'], $thumbnail];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $node a media:content element
     * @param list<array{0: string, 1: array<string, mixed>}> $thumbnails
     * @return array<string, mixed>
     */
    private static function media(array $node, array $thumbnails): array
    {
        $a = $node['attribs'][''];
        $text = static fn (string $tag): ?string => isset($node['child'][self::MEDIA][$tag][0]) ? (string) $node['child'][self::MEDIA][$tag][0]['data'] : null;
        $fields = ['url' => (string) $a['url'], 'node' => $node, 'type' => $a['type'] ?? null, 'length' => isset($a['fileSize']) ? (int) $a['fileSize'] : null, 'title' => $text('title'), 'description' => $text('description'), 'thumbnails' => $thumbnails];
        foreach (['bitrate', 'channels', 'duration', 'expression', 'framerate', 'height', 'lang', 'medium', 'samplingrate', 'width'] as $key) {
            $fields[$key] = $a[$key] ?? null;
        }
        return $fields;
    }
}
