<?php

declare(strict_types=1);

namespace Minn\Feed;

/**
 * A parsed feed: the tree and the format it was recognized as (the
 * reference's type bits: RSS 0.90 1, the 0.9x versions 2 to 32, RSS 1.0 64,
 * RSS 2.0 128, Atom 0.3 256, Atom 1.0 512), with the lookups the feed
 * object is built on: the root's children, the channel's, the image's, and
 * the items of every format the document carries.
 */
final readonly class Document
{
    public const NONE = 0;
    public const RSS_090 = 1;
    public const RSS_10 = 64;
    public const RSS_20 = 128;
    public const ATOM_03 = 256;
    public const ATOM_10 = 512;
    public const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const RSS_VERSIONS = ['0.91' => 4, '0.92' => 8, '0.93' => 16, '0.94' => 32];

    /** @param array{child?: array<string, mixed>} $tree */
    public function __construct(public array $tree, public int $type)
    {
    }

    /**
     * A document read from bytes; the type is NONE when it parsed but is no feed.
     *
     * @throws FeedError when the bytes are not well-formed XML
     */
    public static function parse(string $body, string $contentType = ''): self
    {
        return self::fromTree(Tree::parse(Charset::toUtf8($body, $contentType)));
    }

    /**
     * A document over a tree already parsed (a cached feed), its type read from the root.
     *
     * @param array{child?: array<string, mixed>} $tree
     */
    public static function fromTree(array $tree): self
    {
        $child = $tree['child'] ?? [];
        $type = self::NONE;
        if (isset($child['']['rss'][0])) {
            $type = self::RSS_VERSIONS[trim((string) ($child['']['rss'][0]['attribs']['']['version'] ?? ''))] ?? self::RSS_20;
        } elseif (isset($child[self::RDF]['RDF'][0])) {
            $type = isset($child[self::RDF]['RDF'][0]['child'][Tree::RSS_090]) && !isset($child[self::RDF]['RDF'][0]['child'][Tree::RSS_10]) ? self::RSS_090 : self::RSS_10;
        } elseif (isset($child[Tree::ATOM_10]['feed'][0])) {
            $type = self::ATOM_10;
        } elseif (isset($child[Tree::ATOM_03]['feed'][0])) {
            $type = self::ATOM_03;
        }
        return new self($tree, $type);
    }

    /** The root element (rss, rdf:RDF or feed), or null. */
    public function root(): ?array
    {
        foreach ($this->tree['child'] ?? [] as $tags) {
            foreach ($tags as $nodes) {
                return $nodes[0] ?? null;
            }
        }
        return null;
    }

    /** The channel element: an RSS channel, or the Atom feed itself. */
    public function channel(): ?array
    {
        $root = $this->root();
        if ($root === null || $this->type >= self::ATOM_03) {
            return $root;
        }
        $children = $root['child'] ?? [];
        return $children['']['channel'][0] ?? $children[Tree::RSS_10]['channel'][0] ?? $children[Tree::RSS_090]['channel'][0] ?? null;
    }

    /** The image element: inside an RSS 2.0 channel, beside an RSS 1.0 one. */
    public function image(): ?array
    {
        $channel = $this->channel();
        $root = $this->root();
        return $channel['child']['']['image'][0] ?? $root['child'][Tree::RSS_10]['image'][0] ?? $root['child'][Tree::RSS_090]['image'][0] ?? null;
    }

    /** Children of the root element by namespace and name, or null. */
    public function feedTags(string $ns, string $tag): ?array
    {
        return $this->root()['child'][$ns][$tag] ?? null;
    }

    /** Children of the channel by namespace and name, or null. */
    public function channelTags(string $ns, string $tag): ?array
    {
        return $this->channel()['child'][$ns][$tag] ?? null;
    }

    /** Children of the image by namespace and name, or null. */
    public function imageTags(string $ns, string $tag): ?array
    {
        return $this->image()['child'][$ns][$tag] ?? null;
    }

    /**
     * Every item node, in document order: Atom 1.0 and 0.3 entries, RSS 1.0
     * and 0.90 items beside the channel, RSS 2.0 items inside it.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $root = $this->root()['child'] ?? [];
        $channel = $this->channel()['child'] ?? [];
        return [
            ...($root[Tree::ATOM_10]['entry'] ?? []),
            ...($root[Tree::ATOM_03]['entry'] ?? []),
            ...($root[Tree::RSS_10]['item'] ?? []),
            ...($root[Tree::RSS_090]['item'] ?? []),
            ...($channel['']['item'] ?? []),
        ];
    }
}
