<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Json;
use Minn\Runtime\Runtime;

/**
 * Parses block markup into a tree. The grammar is the delimiter comment:
 * an opener with optional JSON attributes, a closer, or a self-closing
 * void block; a name without a namespace is core/. Anything outside a
 * block is a freeform block that renders as-is.
 */
final class Parser
{
    // Whitespace in a delimiter is these five characters only: space, tab, LF, CR and form feed, as every
    // parser of the reference agrees on (a vertical tab, NUL or Unicode space leaves an inert comment).
    private const TOKEN = '/<!--[ \t\n\r\f]+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)[ \t\n\r\f]+(?P<attrs>\{(?:(?!\}[ \t\n\r\f]+\/?-->).)*+\}[ \t\n\r\f]+)?(?P<void>\/)?-->/s';

    /**
     * The parser class block_parser_class names when a plugin swaps its own
     * in for WP_Block_Parser; null for the engine's own.
     */
    public static function swapped(): ?string
    {
        $class = Runtime::hooks()->filter('block_parser_class', ['WP_Block_Parser']);
        return $class === 'WP_Block_Parser' || !is_string($class) || !class_exists($class) ? null : $class;
    }

    /**
     * A document's blocks as do_blocks reads them: by the parser
     * block_parser_class names, the engine's own unless a plugin swaps
     * its own in.
     *
     * @return list<Block>
     */
    public static function named(string $markup): array
    {
        $class = self::swapped();
        return $class === null ? self::parse($markup) : self::withClass($class, $markup);
    }

    /**
     * Block markup as a parser class of a plugin's reads it.
     *
     * @return list<Block>
     */
    public static function withClass(string $class, string $markup): array
    {
        return array_values(array_map(static fn (mixed $parsed): Block => Block::fromArray((array) $parsed), (array) (new $class())->parse($markup)));
    }

    /**
     * Block markup as a tree of blocks.
     *
     * @return list<Block>
     */
    public static function parse(string $markup): array
    {
        preg_match_all(self::TOKEN, $markup, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $output = [];
        /** @var list<array{name: string, attrs: array, blocks: list<Block>, html: string, content: list<string|null>}> $stack */
        $stack = [];
        $cursor = 0;

        $append = static function (string $text) use (&$stack, &$output): void {
            if ($text === '') {
                return;
            }
            if ($stack === []) {
                $output[] = Block::freeform($text);
                return;
            }
            $top = &$stack[count($stack) - 1];
            $top['html'] .= $text;
            $top['content'][] = $text;
        };
        $addBlock = static function (Block $block) use (&$stack, &$output): void {
            if ($stack === []) {
                $output[] = $block;
                return;
            }
            $top = &$stack[count($stack) - 1];
            $top['blocks'][] = $block;
            $top['content'][] = null;
        };

        foreach ($tokens as $token) {
            [$full, $offset] = $token[0];
            $append(substr($markup, $cursor, $offset - $cursor));
            $cursor = $offset + strlen($full);

            $name = (($token['namespace'][0] ?? '') !== '' ? $token['namespace'][0] : 'core/') . $token['name'][0];
            $attrs = ($token['attrs'][0] ?? '') !== '' ? (array) Json::decode(trim($token['attrs'][0]), or: null) : [];
            $isCloser = ($token['closer'][0] ?? '') === '/';
            $isVoid = ($token['void'][0] ?? '') === '/';

            if ($isVoid) {
                $addBlock(new Block($name, $attrs, [], '', []));
                continue;
            }
            if (!$isCloser) {
                $stack[] = ['name' => $name, 'attrs' => $attrs, 'blocks' => [], 'html' => '', 'content' => []];
                continue;
            }
            $frame = array_pop($stack);
            if ($frame === null) {
                // A stray closer is just text.
                $append($full);
                continue;
            }
            $addBlock(new Block($frame['name'], $frame['attrs'], $frame['blocks'], $frame['html'], $frame['content']));
        }
        $append(substr($markup, $cursor));
        // Unclosed blocks flatten to their content.
        while ($stack !== []) {
            $frame = array_pop($stack);
            $addBlock(new Block($frame['name'], $frame['attrs'], $frame['blocks'], $frame['html'], $frame['content']));
        }
        return $output;
    }

    /**
     * The first block delimiter at or after an offset: where it starts, its
     * length, its name (core/ when it names no namespace), its attributes as
     * written (null when it has none), and whether it closes a block or is
     * a void one. Null when no delimiter follows.
     *
     * @return array{start: int, length: int, name: string, attrs: ?string, closer: bool, void: bool}|null
     */
    public static function token(string $markup, int $offset): ?array
    {
        if (preg_match(self::TOKEN, $markup, $m, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }
        return [
            'start' => $m[0][1],
            'length' => strlen($m[0][0]),
            'name' => (($m['namespace'][0] ?? '') !== '' ? $m['namespace'][0] : 'core/') . $m['name'][0],
            'attrs' => ($m['attrs'][0] ?? '') !== '' ? trim($m['attrs'][0]) : null,
            'closer' => ($m['closer'][0] ?? '') === '/',
            'void' => ($m['void'][0] ?? '') === '/',
        ];
    }

    /** Whether serialized content carries a block by name; a bare name means core/, and core blocks are also found by their short delimiter. */
    public static function contains(string $content, string $name): bool
    {
        if (!str_contains($name, '/')) {
            $name = 'core/' . $name;
        }
        $spellings = str_starts_with($name, 'core/') ? [substr($name, 5), $name] : [$name];
        foreach ($spellings as $spelling) {
            foreach ([' ', ' /-->', ' -->', '/-->'] as $tail) {
                if (str_contains($content, '<!-- wp:' . $spelling . $tail)) {
                    return true;
                }
            }
        }
        return false;
    }
}
