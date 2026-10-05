<?php

declare(strict_types=1);

use Minn\Feed\Charset;
use Minn\Feed\Dates;
use Minn\Feed\Document;
use Minn\Feed\FeedError;
use Minn\Feed\Iri;
use Minn\Feed\Locator;
use Minn\Feed\Parts;
use Minn\Feed\Tree;

/** The feed reader's pure parts; the reference's answers for whole feeds are pinned by tests/feeds.test.php. */
$doc = static fn (string $xml): Document => Document::parse($xml);
return [
    'references resolve as RFC 3986 says' => static fn () => Iri::resolve('http://a/b/c/d;p?q', '../../g') === 'http://a/g'
        && Iri::resolve('http://a/b/c/d;p?q', '?y') === 'http://a/b/c/d;p?y'
        && Iri::resolve('http://a/b/c/d;p?q', '#s') === 'http://a/b/c/d;p?q#s'
        && Iri::resolve('http://a/b/c/d;p?q', '//g/x') === 'http://g/x',
    'a URL is normalized as the reference prints it' => static fn () => Iri::resolve('', 'HTTP://Tags.Example.TEST:80/a/./b/../c d?x=1&y=é#frag') === 'http://tags.example.test/a/c%20d?x=1&y=%C3%A9#frag',
    'a relative reference without an absolute base stays unresolved' => static fn () => Iri::resolve('', 'relative') === null,
    'dates without a zone are UTC; named zones are read' => static fn () => Dates::parse('2026-09-01 08:00:00') === 1788249600 && Dates::parse('Fri, 02 Oct 2026 10:00:00 EDT') === 1790949600 && Dates::parse('not a date') === null && Dates::parse('  ') === null,
    'Windows-1252 and a byte-order mark become UTF-8' => static fn () => str_contains(Charset::toUtf8("<?xml version=\"1.0\" encoding=\"windows-1252\"?><a>\x93q\x94 \x80</a>"), '<a>“q” €</a>') && Charset::toUtf8("\xEF\xBB\xBF<a/>") === '<a/>',
    'an RSS title is stored escaped, a namespaced one is not' => static function () use ($doc): bool {
        $d = $doc('<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>A &amp; &lt;b&gt; "q"</title><dc:title>A &amp; B</dc:title></channel></rss>');
        return $d->channelTags('', 'title')[0]['data'] === 'A &amp; &lt;b&gt; &quot;q&quot;' && $d->channelTags('http://purl.org/dc/elements/1.1/', 'title')[0]['data'] === 'A & B' && $d->type === Document::RSS_20;
    },
    'an Atom xhtml construct keeps its markup as text' => static function () use ($doc): bool {
        $d = $doc('<feed xmlns="http://www.w3.org/2005/Atom"><title type="xhtml"><div xmlns="http://www.w3.org/1999/xhtml">X<b>Y &amp; Z</b><br/></div></title></feed>');
        return $d->channelTags(Tree::ATOM_10, 'title')[0]['data'] === '<div>X<b>Y &amp; Z</b><br /></div>' && $d->type === Document::ATOM_10;
    },
    'HTML named references read as characters, unless the document has its own DOCTYPE' => static function () use ($doc): bool {
        $ok = $doc('<rss><channel><description>a &mdash; b</description></channel></rss>')->channelTags('', 'description')[0]['data'] === 'a — b';
        try {
            $doc('<!DOCTYPE rss><rss><channel><description>a &mdash; b</description></channel></rss>');
            return false;
        } catch (FeedError $e) {
            return $ok && str_starts_with($e->getMessage(), 'XML error: ');
        }
    },
    'an error after a declaration counts the line the reference counts' => static function () use ($doc): bool {
        try {
            $doc("<?xml version=\"1.0\"?>\n<rss version=\"2.0\"><channel><title>Broken<item></channel>\n");
            return false;
        } catch (FeedError $e) {
            return $e->getMessage() === 'XML error: Mismatched tag at line 3, column 58';
        }
    },
    'xml:base resolves against the parent and marks itself explicit' => static function () use ($doc): bool {
        $node = $doc('<feed xmlns="http://www.w3.org/2005/Atom" xml:base="https://a.test/x/"><entry xml:base="y/"><id>1</id></entry></feed>')->items()[0];
        return $node['xml_base'] === 'https://a.test/x/y/' && $node['xml_base_explicit'] === true;
    },
    'RSS 1.0 items sit beside the channel' => static fn () => count($doc('<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/"><channel/><item/><item/></rdf:RDF>')->items()) === 2,
    'a page names its feeds; text is not a feed' => static fn () => Locator::alternates('<link rel="alternate" type="application/rss+xml" href="f.xml"><link rel="stylesheet" href="s.css">', 'https://p.test/a/b.html') === ['https://p.test/a/f.xml']
        && !Locator::looksLikeFeed('just text', 'text/plain') && Locator::looksLikeFeed("<?xml version=\"1.0\"?>\n<rss>", 'text/plain'),
    'links answer to their IANA names too, guids join the alternates' => static function (): bool {
        $links = Parts::links(['' => ['link' => [['data' => 'https://x.test/', 'attribs' => []]]]], [['', 'link']], ['https://x.test/g'], static fn (string $href): string => $href);
        return $links['alternate'] === ['https://x.test/', 'https://x.test/g'] && $links[Parts::IANA . 'alternate'] === $links['alternate'];
    },
];
