<?php

declare(strict_types=1);

namespace Minn\Feed;

use Minn\Support\KsesEntities;
use XMLParser;

/**
 * A feed document parsed into the nested arrays plugin code reads through
 * get_item_tags() and friends: each element is a node holding its own text
 * ("data", whitespace between children included), its attributes grouped
 * by namespace, the xml:base and xml:lang in force, and its children by
 * namespace and local name. Two shapes differ from plain XML: an Atom text
 * construct of type "xhtml" keeps its markup as text, and an RSS title is
 * stored HTML-escaped. Named HTML references are read as characters unless
 * the document brings its own DOCTYPE.
 */
final class Tree
{
    public const XML = 'http://www.w3.org/XML/1998/namespace';
    public const ATOM_10 = 'http://www.w3.org/2005/Atom';
    public const ATOM_03 = 'http://purl.org/atom/ns#';
    public const RSS_10 = 'http://purl.org/rss/1.0/';
    public const RSS_090 = 'http://my.netscape.com/rdf/simple/0.9/';
    private const SEPARATOR = "\x1F";
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /** @var list<array{ns: string, name: string, node: array<string, mixed>}> */
    private array $stack = [];
    /** @var array<string, mixed> */
    private array $root = ['child' => []];
    private int $xhtmlDepth = 0;

    /**
     * The document's elements under a root holding only "child".
     *
     * @return array{child: array<string, array<string, list<array<string, mixed>>>>}
     * @throws FeedError when the document is not well-formed XML
     */
    public static function parse(string $utf8): array
    {
        return (new self())->build(self::prepare($utf8));
    }

    /** The document as the parser reads it: HTML references made numeric, the declaration on a line of its own. */
    private static function prepare(string $xml): string
    {
        $xml = ltrim($xml);
        if (!preg_match('/<!DOCTYPE/i', substr($xml, 0, 2048))) {
            $xml = (string) preg_replace_callback('/<!\[CDATA\[.*?\]\]>|<!--.*?-->|&([A-Za-z][A-Za-z0-9]*);/s', static function (array $m): string {
                $name = $m[1] ?? '';
                if ($name === '' || in_array($name, ['amp', 'lt', 'gt', 'quot', 'apos'], true) || !KsesEntities::known($name)) {
                    return $m[0];
                }
                return '&#' . mb_ord(html_entity_decode($m[0], ENT_QUOTES | ENT_HTML401, 'UTF-8'), 'UTF-8') . ';';
            }, $xml);
        }
        // The reference reads a declared document with one line more before the root, so its error lines count from there.
        return (string) preg_replace('/^(<\?xml[^>]*\?>)/', "$1\n", $xml, 1);
    }

    /** @return array{child: array<string, array<string, list<array<string, mixed>>>>} */
    private function build(string $xml): array
    {
        $parser = xml_parser_create_ns('UTF-8', self::SEPARATOR);
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 0);
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
        xml_set_element_handler($parser, $this->open(...), $this->close(...));
        xml_set_character_data_handler($parser, $this->text(...));
        if (xml_parse($parser, $xml, true) !== 1) {
            throw new FeedError(sprintf('XML error: %s at line %d, column %d', xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser), xml_get_current_column_number($parser)));
        }
        return $this->root;
    }

    private function open(XMLParser $parser, string $name, array $attributes): void
    {
        [$ns, $local] = self::split($name);
        if ($this->xhtmlDepth > 0) {
            $this->xhtmlDepth++;
            $this->append('<' . $local . self::markupAttributes($attributes) . (in_array(strtolower($local), self::VOID, true) ? ' />' : '>'));
            return;
        }
        $attribs = [];
        foreach ($attributes as $key => $value) {
            [$attributeNs, $attributeName] = self::split((string) $key);
            $attribs[$attributeNs][$attributeName] = (string) $value;
        }
        $parent = $this->stack === [] ? null : $this->stack[count($this->stack) - 1]['node'];
        $base = (string) ($parent['xml_base'] ?? '');
        $explicit = (bool) ($parent['xml_base_explicit'] ?? false);
        if (isset($attribs[self::XML]['base'])) {
            $base = Iri::resolve($base, $attribs[self::XML]['base']) ?? $attribs[self::XML]['base'];
            $explicit = true;
        }
        $node = [
            'data' => '',
            'attribs' => $attribs,
            'xml_base' => $base,
            'xml_base_explicit' => $explicit,
            'xml_lang' => (string) ($attribs[self::XML]['lang'] ?? $parent['xml_lang'] ?? ''),
        ];
        $this->stack[] = ['ns' => $ns, 'name' => $local, 'node' => $node];
        if (in_array($ns, [self::ATOM_10, self::ATOM_03], true) && strtolower((string) ($attribs['']['type'] ?? $attribs['']['mode'] ?? '')) === ($ns === self::ATOM_10 ? 'xhtml' : 'xml')) {
            $this->xhtmlDepth = 1;
        }
    }

    private function close(XMLParser $parser, string $name): void
    {
        if ($this->xhtmlDepth > 1) {
            $this->xhtmlDepth--;
            [, $local] = self::split($name);
            if (!in_array(strtolower($local), self::VOID, true)) {
                $this->append('</' . $local . '>');
            }
            return;
        }
        $this->xhtmlDepth = 0;
        $frame = array_pop($this->stack);
        if ($frame === null) {
            return;
        }
        $node = $frame['node'];
        if ($frame['name'] === 'title' && in_array($frame['ns'], ['', self::RSS_10, self::RSS_090], true)) {
            $node['data'] = htmlspecialchars((string) $node['data'], ENT_QUOTES, 'UTF-8');
        }
        if ($this->stack === []) {
            $this->root['child'][$frame['ns']][$frame['name']][] = $node;
            return;
        }
        $this->stack[count($this->stack) - 1]['node']['child'][$frame['ns']][$frame['name']][] = $node;
    }

    private function text(XMLParser $parser, string $text): void
    {
        $this->append($this->xhtmlDepth > 1 ? htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8') : $text);
    }

    private function append(string $text): void
    {
        if ($this->stack !== []) {
            $this->stack[count($this->stack) - 1]['node']['data'] .= $text;
        }
    }

    /** @return array{0: string, 1: string} namespace and local name */
    private static function split(string $name): array
    {
        $at = strrpos($name, self::SEPARATOR);
        return $at === false ? ['', $name] : [substr($name, 0, $at), substr($name, $at + 1)];
    }

    private static function markupAttributes(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $key => $value) {
            $out .= ' ' . self::split((string) $key)[1] . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $out;
    }
}
