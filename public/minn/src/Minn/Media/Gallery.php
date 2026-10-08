<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * The classic `[gallery]` shortcode's markup. Every gallery on a page is
 * numbered from one shared per-request counter, and the wrapper names both
 * that number and the post the gallery sits in, so two galleries on one page
 * can be styled apart.
 *
 * The single quotes around the wrapper attributes and the tab-indented item
 * body are the reference's, and themes have styled against them for long
 * enough that they are contract rather than formatting.
 */
final class Gallery
{
    /**
     * The gallery shortcode's HTML for these attachments.
     *
     * @param list<array{icon: string, orientation: string, caption: string, caption_id: string}> $items
     * @param array{itemtag: string, icontag: string, captiontag: string, columns: int, size: string} $args
     */
    public static function render(array $items, array $args, int $instance, int $postId): string
    {
        if ($items === []) {
            return '';
        }
        $itemTag = self::tag($args['itemtag'], 'dl');
        $iconTag = self::tag($args['icontag'], 'dt');
        $captionTag = self::tag($args['captiontag'], 'dd');
        $classes = 'gallery galleryid-' . $postId
            . ' gallery-columns-' . $args['columns']
            . ' gallery-size-' . self::token($args['size']);
        $out = "<div id='gallery-" . $instance . "' class='" . $classes . "'>";
        foreach ($items as $item) {
            $out .= '<' . $itemTag . " class='gallery-item'>\n\t\t\t"
                . '<' . $iconTag . " class='gallery-icon " . $item['orientation'] . "'>\n\t\t\t\t"
                . $item['icon'] . "\n\t\t\t"
                . '</' . $iconTag . '>'
                . self::caption($item, $captionTag)
                . '</' . $itemTag . '>';
        }
        return $out . "\n\t\t</div>\n";
    }

    /** @param array{caption: string, caption_id: string} $item */
    private static function caption(array $item, string $tag): string
    {
        if ($item['caption'] === '') {
            return '';
        }
        return "\n\t\t\t" . '<' . $tag . " class='wp-caption-text gallery-caption' id='" . $item['caption_id'] . "'>\n\t\t\t"
            . $item['caption'] . "\n\t\t\t" . '</' . $tag . '>';
    }

    /** A caller's tag name, reduced to what may safely open an element. */
    private static function tag(string $tag, string $fallback): string
    {
        $tag = strtolower(trim($tag));
        return preg_match('/^[a-z][a-z0-9]*$/', $tag) === 1 ? $tag : $fallback;
    }

    /** A size name reduced to a class token. */
    private static function token(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
    }
}
