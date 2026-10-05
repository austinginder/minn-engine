<?php
/**
 * The field lookups the feed, its items and an item's source share: the
 * first tag that carries text, sanitized as its construct says; dates;
 * people; links by relation; categories. Each candidate names a namespace,
 * a tag and a construct (the SIMPLEPIE_CONSTRUCT_* bits, or "atom" to read
 * an Atom text construct's type attribute).
 */

namespace SimplePie;

use Minn\Feed\Dates;
use Minn\Feed\Parts;
use Minn\Feed\Tree;

/** @internal */
trait MinnFields
{
    /** @return list<array<string, mixed>>|null the element's child tags */
    abstract protected function minn_tags(string $namespace, string $tag): ?array;

    /** The feed whose sanitizer and base this object reads. */
    abstract protected function minn_feed(): SimplePie;

    /** @return array<string, array<string, list<array<string, mixed>>>> the element's children by namespace and name */
    abstract protected function minn_children(): array;

    /** The URL a relative reference in this element resolves against ($forLink: the element is a link of the same object). */
    abstract protected function minn_raw_base(array $node, bool $forLink = false): string;

    /** @param list<array{0: string, 1: string, 2: int|string}> $candidates */
    protected function minn_text(array $candidates): ?string
    {
        foreach ($candidates as [$ns, $tag, $construct]) {
            $node = $this->minn_tags($ns, $tag)[0] ?? null;
            if ($node === null || trim((string) $node['data']) === '') {
                continue;
            }
            $type = $construct === 'atom' ? self::minn_atom_type($node) : (int) $construct;
            return $this->minn_feed()->sanitize((string) $node['data'], $type, $this->minn_raw_base($node));
        }
        return null;
    }

    /** An Atom text construct's type: text unless it says html or xhtml (Atom 0.3's mode too). */
    protected static function minn_atom_type(array $node): int
    {
        $attributes = $node['attribs'][''] ?? [];
        $type = strtolower((string) ($attributes['type'] ?? 'text'));
        $mode = strtolower((string) ($attributes['mode'] ?? ''));
        if ($mode === 'base64') {
            return SIMPLEPIE_CONSTRUCT_BASE64 | ($type === 'text/html' ? SIMPLEPIE_CONSTRUCT_HTML : SIMPLEPIE_CONSTRUCT_TEXT);
        }
        return match (true) {
            $type === 'html', $type === 'text/html', $mode === 'escaped' => SIMPLEPIE_CONSTRUCT_HTML,
            $type === 'xhtml', $type === 'application/xhtml+xml', $mode === 'xml' => SIMPLEPIE_CONSTRUCT_XHTML,
            default => SIMPLEPIE_CONSTRUCT_TEXT,
        };
    }

    /** @param list<array{0: string, 1: string}> $candidates the first date any of them carries, as a timestamp */
    protected function minn_date(array $candidates): ?int
    {
        foreach ($candidates as [$ns, $tag]) {
            $stamp = Dates::parse($this->minn_tags($ns, $tag)[0]['data'] ?? null);
            if ($stamp !== null) {
                return $stamp;
            }
        }
        return null;
    }

    /** One Atom person (author or contributor), or null when it names nothing. */
    protected function minn_person(array $node, string $ns, string $class): ?object
    {
        $part = function (string $tag, int $type) use ($node, $ns): ?string {
            $child = $node['child'][$ns][$tag][0] ?? null;
            return $child === null || trim((string) $child['data']) === '' ? null : $this->minn_feed()->sanitize((string) $child['data'], $type, $this->minn_raw_base($child));
        };
        $name = $part('name', SIMPLEPIE_CONSTRUCT_TEXT);
        $link = $part($ns === Tree::ATOM_10 ? 'uri' : 'url', SIMPLEPIE_CONSTRUCT_IRI);
        $email = $part('email', SIMPLEPIE_CONSTRUCT_TEXT);
        return $name === null && $link === null && $email === null ? null : new $class($name, $link, $email);
    }

    /** @return list<object> the Atom people under one tag, both versions */
    protected function minn_people(string $tag): array
    {
        $people = [];
        foreach ([Tree::ATOM_10, Tree::ATOM_03] as $ns) {
            foreach ($this->minn_tags($ns, $tag) ?? [] as $node) {
                $person = $this->minn_person($node, $ns, $this->minn_feed()->minn_class('author'));
                if ($person !== null) {
                    $people[] = $person;
                }
            }
        }
        return $people;
    }

    /** @return list<object> creators named in Dublin Core and iTunes tags */
    protected function minn_creators(): array
    {
        $people = [];
        $class = $this->minn_feed()->minn_class('author');
        foreach ([[SIMPLEPIE_NAMESPACE_DC_11, 'creator'], [SIMPLEPIE_NAMESPACE_DC_10, 'creator'], [SIMPLEPIE_NAMESPACE_ITUNES, 'author']] as [$ns, $tag]) {
            foreach ($this->minn_tags($ns, $tag) ?? [] as $node) {
                if (trim((string) $node['data']) !== '') {
                    $people[] = new $class($this->minn_feed()->sanitize((string) $node['data'], SIMPLEPIE_CONSTRUCT_TEXT), null, null);
                }
            }
        }
        return $people;
    }

    /**
     * Links by relation, each URL resolved against its element's base.
     *
     * @param list<array{0: string, 1: string}> $plain RSS-style link tags
     * @param list<string> $more alternates found elsewhere (an item's permalink guid)
     * @return array<string, list<string>>
     */
    protected function minn_link_map(array $plain, array $more = []): array
    {
        return Parts::links($this->minn_children(), $plain, $more, fn (string $href, array $node): string => $this->minn_feed()->sanitize($href, SIMPLEPIE_CONSTRUCT_IRI, $this->minn_raw_base($node, true)));
    }

    /** @return list<object> categories from Atom, RSS 2.0 and Dublin Core subjects */
    protected function minn_category_list(): array
    {
        $class = $this->minn_feed()->minn_class('category');
        $text = fn (?string $value): ?string => $value === null || trim($value) === '' ? null : $this->minn_feed()->sanitize($value, SIMPLEPIE_CONSTRUCT_TEXT);
        return array_map(static fn (array $c): object => new $class($text($c['term']), $text($c['scheme']), $text($c['label']), $c['type']), Parts::categories($this->minn_children()));
    }
}
