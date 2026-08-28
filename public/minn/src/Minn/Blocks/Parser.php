<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * Parses block markup into a tree. The grammar is the delimiter comment:
 * an opener with optional JSON attributes, a closer, or a self-closing
 * void block; a name without a namespace is core/. Anything outside a
 * block is a freeform block that renders as-is.
 */
final class Parser
{
    private const TOKEN = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>\{(?:(?!\}\s+\/?-->).)*+\}\s+)?(?P<void>\/)?-->/s';

    /** @return list<Block> */
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
            $attrs = ($token['attrs'][0] ?? '') !== '' ? (array) json_decode(trim($token['attrs'][0]), true) : [];
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
}
