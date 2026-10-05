<?php
/**
 * One entry of a feed (SimplePie\Item): its id, title, description and
 * content, dates, people, links, categories and enclosures, read from the
 * item's element in the parsed tree. Values come back sanitized: HTML
 * through kses, text escaped, URLs resolved and escaped.
 */

namespace SimplePie;

use Minn\Feed\Document;
use Minn\Feed\Parts;
use Minn\Feed\Tree;

class Item implements RegistryAware
{
    use MinnFields;

    public $feed;
    public $data = [];
    protected $registry;
    protected $sanitize;

    private ?array $minn_links = null;
    private ?array $minn_enclosures = null;
    /** @var array<string, ?int> */
    private array $minn_stamps = [];

    public function __construct($feed, $data)
    {
        $this->feed = $feed;
        $this->data = $data;
    }

    public function set_registry($registry)
    {
        $this->registry = $registry;
    }

    public function __toString()
    {
        return md5(serialize($this->data));
    }

    public function __destruct()
    {
    }

    public function get_item_tags($namespace, $tag)
    {
        return $this->data['child'][$namespace][$tag] ?? null;
    }

    /** An element's own xml:base when it set one; otherwise the item's permalink, or the feed's base for a part of the item. */
    public function get_base($element = [])
    {
        if (!empty($element['xml_base_explicit']) && isset($element['xml_base'])) {
            return $element['xml_base'];
        }
        return $element === [] ? ($this->get_permalink() ?? $this->feed->get_base()) : $this->feed->get_base();
    }

    public function sanitize($data, $type, $base = '')
    {
        return $this->feed->sanitize($data, $type, $base);
    }

    public function get_feed()
    {
        return $this->feed;
    }

    /** The item's own id (Atom id, guid, dc:identifier, rdf:about), else a hash of its permalink, title and content. */
    public function get_id($hash = false, $fn = 'md5')
    {
        if (!$hash) {
            $id = $this->minn_text([
                [Tree::ATOM_10, 'id', SIMPLEPIE_CONSTRUCT_TEXT], [Tree::ATOM_03, 'id', SIMPLEPIE_CONSTRUCT_TEXT], ['', 'guid', SIMPLEPIE_CONSTRUCT_TEXT],
                [SIMPLEPIE_NAMESPACE_DC_11, 'identifier', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'identifier', SIMPLEPIE_CONSTRUCT_TEXT],
            ]);
            $about = $this->data['attribs'][Document::RDF]['about'] ?? null;
            if ($id !== null || ($about !== null && trim((string) $about) !== '')) {
                return $id ?? $this->sanitize((string) $about, SIMPLEPIE_CONSTRUCT_TEXT);
            }
        }
        $string = $this->get_permalink() . $this->get_title() . $this->get_content();
        return $fn !== 'md5' && is_callable($fn) ? call_user_func($fn, $string) : md5($string);
    }

    public function get_title()
    {
        return $this->minn_text([
            [Tree::ATOM_10, 'title', 'atom'], [Tree::ATOM_03, 'title', 'atom'],
            [Tree::RSS_10, 'title', SIMPLEPIE_CONSTRUCT_HTML], [Tree::RSS_090, 'title', SIMPLEPIE_CONSTRUCT_HTML], ['', 'title', SIMPLEPIE_CONSTRUCT_HTML],
            [SIMPLEPIE_NAMESPACE_DC_11, 'title', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'title', SIMPLEPIE_CONSTRUCT_TEXT],
        ]);
    }

    /** The summary; without one, the content (unless only the summary was asked for). */
    public function get_description($description_only = false)
    {
        $text = $this->minn_text([
            [Tree::ATOM_10, 'summary', 'atom'], [Tree::ATOM_03, 'summary', 'atom'],
            [Tree::RSS_10, 'description', SIMPLEPIE_CONSTRUCT_HTML], [Tree::RSS_090, 'description', SIMPLEPIE_CONSTRUCT_HTML], ['', 'description', SIMPLEPIE_CONSTRUCT_HTML],
            [SIMPLEPIE_NAMESPACE_DC_11, 'description', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'description', SIMPLEPIE_CONSTRUCT_TEXT],
            [SIMPLEPIE_NAMESPACE_ITUNES, 'summary', SIMPLEPIE_CONSTRUCT_HTML], [SIMPLEPIE_NAMESPACE_ITUNES, 'subtitle', SIMPLEPIE_CONSTRUCT_HTML],
        ]);
        return $text ?? ($description_only ? null : $this->get_content(true));
    }

    /** The full content; without it, the summary (unless only the content was asked for). */
    public function get_content($content_only = false)
    {
        $text = $this->minn_text([[Tree::ATOM_10, 'content', 'atom'], [Tree::ATOM_03, 'content', 'atom'], [SIMPLEPIE_NAMESPACE_RSS_10_MODULES_CONTENT, 'encoded', SIMPLEPIE_CONSTRUCT_HTML]]);
        return $text ?? ($content_only ? null : $this->get_description(true));
    }

    /** The item's first media:thumbnail as its attributes, the URL resolved. */
    public function get_thumbnail()
    {
        $node = $this->get_item_tags(SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail')[0] ?? null;
        if ($node === null) {
            return null;
        }
        $thumbnail = $node['attribs'][''] ?? [];
        if (isset($thumbnail['url'])) {
            $thumbnail['url'] = $this->sanitize((string) $thumbnail['url'], SIMPLEPIE_CONSTRUCT_IRI, $this->minn_raw_base($node));
        }
        return $thumbnail;
    }

    public function get_category($key = 0)
    {
        return $this->get_categories()[$key] ?? null;
    }

    public function get_categories()
    {
        return $this->minn_category_list() ?: null;
    }

    public function get_author($key = 0)
    {
        return $this->get_authors()[$key] ?? null;
    }

    public function get_contributor($key = 0)
    {
        return $this->get_contributors()[$key] ?? null;
    }

    public function get_contributors()
    {
        return $this->minn_people('contributor') ?: null;
    }

    /** Atom authors, the RSS author (an email), Dublin Core and iTunes creators; with none, the source's or the feed's. */
    public function get_authors()
    {
        $class = $this->feed->minn_class('author');
        $people = $this->minn_people('author');
        foreach ($this->get_item_tags('', 'author') ?? [] as $node) {
            if (trim((string) $node['data']) !== '') {
                $people[] = new $class(null, null, $this->sanitize((string) $node['data'], SIMPLEPIE_CONSTRUCT_TEXT));
            }
        }
        $people = [...$people, ...$this->minn_creators()];
        if ($people === []) {
            $people = $this->get_source()?->get_authors() ?? $this->feed->get_authors() ?? [];
        }
        return $people ?: null;
    }

    public function get_copyright()
    {
        return $this->minn_text([[Tree::ATOM_10, 'rights', 'atom'], [Tree::ATOM_03, 'copyright', 'atom'], [SIMPLEPIE_NAMESPACE_DC_11, 'rights', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'rights', SIMPLEPIE_CONSTRUCT_TEXT]]);
    }

    public function get_date($date_format = 'j F Y, g:i a')
    {
        return self::minn_format($this->minn_stamp('date'), (string) $date_format, false);
    }

    public function get_updated_date($date_format = 'j F Y, g:i a')
    {
        return self::minn_format($this->minn_stamp('updated'), (string) $date_format, false);
    }

    /** The date in strftime() notation (the common conversions), rendered with date(). */
    public function get_local_date($date_format = '%c')
    {
        $stamp = $this->minn_stamp('date');
        if ($stamp === null) {
            return null;
        }
        $map = ['%a' => 'D', '%A' => 'l', '%d' => 'd', '%e' => 'j', '%b' => 'M', '%B' => 'F', '%h' => 'M', '%m' => 'm', '%y' => 'y', '%Y' => 'Y', '%H' => 'H', '%I' => 'h', '%l' => 'g', '%M' => 'i', '%p' => 'A', '%P' => 'a', '%S' => 's', '%Z' => 'T', '%z' => 'O', '%c' => 'D M j H:i:s Y', '%x' => 'm/d/y', '%X' => 'H:i:s', '%D' => 'm/d/y', '%F' => 'Y-m-d', '%T' => 'H:i:s', '%%' => '%'];
        $format = preg_replace_callback('/%[a-zA-Z%]|[a-zA-Z\\\\]/', static fn (array $m): string => $map[$m[0]] ?? ($m[0][0] === '%' ? $m[0] : '\\' . $m[0]), (string) $date_format);
        return date((string) $format, $stamp);
    }

    public function get_gmdate($date_format = 'j F Y, g:i a')
    {
        return self::minn_format($this->minn_stamp('date'), (string) $date_format, true);
    }

    public function get_updated_gmdate($date_format = 'j F Y, g:i a')
    {
        return self::minn_format($this->minn_stamp('updated'), (string) $date_format, true);
    }

    /** The first alternate link, else the first enclosure's. */
    public function get_permalink()
    {
        return $this->get_link() ?? $this->get_enclosure(0)?->get_link();
    }

    public function get_link($key = 0, $rel = 'alternate')
    {
        return $this->get_links($rel)[$key] ?? null;
    }

    /** Links by relation; an RSS guid that is a permalink counts as an alternate link. */
    public function get_links($rel = 'alternate')
    {
        if ($this->minn_links === null) {
            $guids = [];
            foreach ($this->get_item_tags('', 'guid') ?? [] as $guid) {
                if (strtolower(trim((string) ($guid['attribs']['']['isPermaLink'] ?? 'true'))) !== 'false' && trim((string) $guid['data']) !== '') {
                    $guids[] = $this->sanitize((string) $guid['data'], SIMPLEPIE_CONSTRUCT_IRI, $this->minn_raw_base($guid));
                }
            }
            $this->minn_links = $this->minn_link_map([[Tree::RSS_10, 'link'], [Tree::RSS_090, 'link'], ['', 'link']], $guids);
        }
        return $this->minn_links[$rel] ?? null;
    }

    public function get_enclosure($key = 0)
    {
        return $this->get_enclosures()[$key] ?? null;
    }

    /** Media RSS contents, Atom enclosure links and RSS enclosures, each URL once. */
    public function get_enclosures()
    {
        $this->minn_enclosures ??= $this->minn_collect_enclosures();
        $seen = [];
        $unique = array_values(array_filter($this->minn_enclosures, static function ($enclosure) use (&$seen): bool {
            $link = (string) $enclosure->get_link();
            return !isset($seen[$link]) && ($seen[$link] = true);
        }));
        return $unique ?: null;
    }

    public function get_latitude()
    {
        return $this->minn_coordinate(0);
    }

    public function get_longitude()
    {
        return $this->minn_coordinate(1);
    }

    /** The feed an Atom entry was copied from, when it says. */
    public function get_source()
    {
        $node = $this->get_item_tags(Tree::ATOM_10, 'source')[0] ?? null;
        if ($node === null) {
            return null;
        }
        $class = $this->feed->minn_class('source');
        return new $class($this, $node);
    }

    public function set_sanitize($sanitize)
    {
        $this->sanitize = $sanitize;
    }

    protected function minn_tags(string $namespace, string $tag): ?array
    {
        return $this->get_item_tags($namespace, $tag);
    }

    protected function minn_children(): array
    {
        return (array) ($this->data['child'] ?? []);
    }

    protected function minn_feed(): SimplePie
    {
        return $this->feed;
    }

    protected function minn_raw_base(array $node, bool $forLink = false): string
    {
        if (!empty($node['xml_base_explicit']) && isset($node['xml_base'])) {
            return (string) $node['xml_base'];
        }
        return $this->feed->minn_item_base();
    }

    private function minn_stamp(string $which): ?int
    {
        if (!array_key_exists($which, $this->minn_stamps)) {
            $this->minn_stamps[$which] = $which === 'updated'
                ? $this->minn_date([[Tree::ATOM_10, 'updated'], [Tree::ATOM_03, 'modified']])
                : $this->minn_date([[Tree::ATOM_10, 'published'], [Tree::ATOM_10, 'updated'], [Tree::ATOM_03, 'issued'], [Tree::ATOM_03, 'created'], [Tree::ATOM_03, 'modified'], ['', 'pubDate'], [SIMPLEPIE_NAMESPACE_DC_11, 'date'], [SIMPLEPIE_NAMESPACE_DC_10, 'date']]);
        }
        return $this->minn_stamps[$which];
    }

    private static function minn_format(?int $stamp, string $format, bool $gmt)
    {
        if ($stamp === null) {
            return null;
        }
        if ($format === 'U') {
            return $stamp;
        }
        return $gmt ? gmdate($format, $stamp) : date($format, $stamp);
    }

    /** @return list<object> the enclosures, every URL resolved and every text escaped */
    private function minn_collect_enclosures(): array
    {
        $iri = fn (string $url, array $node): string => $this->sanitize($url, SIMPLEPIE_CONSTRUCT_IRI, $this->minn_raw_base($node));
        $text = fn (?string $value): ?string => $value === null ? null : $this->sanitize($value, SIMPLEPIE_CONSTRUCT_TEXT);
        $out = [];
        foreach (Parts::enclosures($this->data) as $f) {
            $f['link'] = $iri($f['url'], $f['node']);
            $f['type'] = $text($f['type']);
            $f['title'] = $text($f['title'] ?? null);
            $f['description'] = $text($f['description'] ?? null);
            $f['thumbnails'] = array_map(static fn (array $t): string => $iri($t[0], $t[1]), $f['thumbnails'] ?? []);
            $out[] = $this->minn_enclosure($f);
        }
        return $out;
    }

    /** @param array<string, mixed> $f */
    private function minn_enclosure(array $f): object
    {
        $class = $this->feed->minn_class('enclosure');
        return new $class($f['link'] ?? null, $f['type'] ?? null, $f['length'] ?? null, null, $f['bitrate'] ?? null, null, null, $f['channels'] ?? null, null, null, $f['description'] ?? null, $f['duration'] ?? null, $f['expression'] ?? null, $f['framerate'] ?? null, null, $f['height'] ?? null, null, $f['lang'] ?? null, $f['medium'] ?? null, null, null, null, $f['samplingrate'] ?? null, ($f['thumbnails'] ?? []) ?: null, $f['title'] ?? null, $f['width'] ?? null);
    }

    private function minn_coordinate(int $index): ?float
    {
        $point = $this->get_item_tags(SIMPLEPIE_NAMESPACE_GEORSS, 'point')[0]['data'] ?? null;
        if ($point !== null && preg_match('/^\s*(-?\d+(?:\.\d+)?)[\s,]+(-?\d+(?:\.\d+)?)\s*$/', (string) $point, $m)) {
            return (float) $m[$index + 1];
        }
        $single = $this->get_item_tags(SIMPLEPIE_NAMESPACE_W3C_BASIC_GEO, $index === 0 ? 'lat' : 'long')[0]['data'] ?? null;
        return $single === null ? null : (float) $single;
    }
}
