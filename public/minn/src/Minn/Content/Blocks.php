<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Blocks\Renderer;
use Minn\Db;

/**
 * The content pipeline's front door: block markup goes through the block
 * renderer (Minn\Blocks), classic content rides the paragraph pipeline.
 */
final class Blocks
{
    private static ?Renderer $renderer = null;

    /** Post content as HTML: blocks through the renderer, classic content through the paragraph rules. */
    public static function render(string $raw): string
    {
        if (str_contains($raw, '<!-- wp:')) {
            return self::renderer()->render($raw);
        }
        if (trim($raw) !== '' && !preg_match('/<(p|div|ul|ol|h\d|blockquote|pre|table|figure)[\s>]/i', $raw)) {
            return self::paragraphs($raw);
        }
        // Classic markup that already carries paragraphs: each closes on its own line.
        return (string) preg_replace('#</p>(?!\n)#', "</p>\n", self::renderer()->render($raw));
    }

    /** The shared block renderer, built once from the shared database door. */
    public static function renderer(): Renderer
    {
        return self::$renderer ??= Renderer::forDb(Db::shared());
    }

    /**
     * Plain prose to paragraphs: texturize, split on blank lines, <br /> on
     * single newlines. Shared by classic post content and comment text.
     */
    public static function paragraphs(string $raw): string
    {
        $text = Texturize::html(trim($raw));
        if ($text === '') {
            return '';
        }
        $out = '';
        foreach (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $text)) as $paragraph) {
            $out .= '<p>' . str_replace("\n", "<br />\n", trim($paragraph)) . "</p>\n";
        }
        return $out;
    }
}
