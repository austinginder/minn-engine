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
    public static function render(array $items, array $args, int $instance, int $postId, ?string $opening = null): string
    {
        if ($items === []) {
            return '';
        }
        $itemTag = self::tag($args['itemtag'], 'dl');
        $iconTag = self::tag($args['icontag'], 'dt');
        $captionTag = self::tag($args['captiontag'], 'dd');
        $out = $opening ?? self::opening($args, $instance, $postId);
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

    /** The gallery's opening element: its instance id, its post, columns and size as classes. */
    public static function opening(array $args, int $instance, int $postId): string
    {
        $classes = 'gallery galleryid-' . $postId
            . ' gallery-columns-' . $args['columns']
            . ' gallery-size-' . self::token($args['size']);
        return "<div id='gallery-" . $instance . "' class='" . $classes . "'>";
    }

    /**
     * The style block printed before a gallery when use_default_gallery_style
     * asks for it (themes without HTML5 galleries), as the reference prints
     * it (probe filter-inputs-3): the items floated in equal whole-percent
     * columns, a border on the images, the caption flush left.
     */
    public static function defaultStyle(int $instance, int $columns): string
    {
        $id = '#gallery-' . $instance;
        $width = $columns > 0 ? (int) floor(100 / $columns) : 100;
        return "\n\t\t<style>\n\t\t\t{$id} {\n\t\t\t\tmargin: auto;\n\t\t\t}\n\t\t\t{$id} .gallery-item {\n\t\t\t\tfloat: left;\n\t\t\t\tmargin-top: 10px;\n\t\t\t\ttext-align: center;\n\t\t\t\twidth: {$width}%;\n\t\t\t}\n\t\t\t{$id} img {\n\t\t\t\tborder: 2px solid #cfcfcf;\n\t\t\t}\n\t\t\t{$id} .gallery-caption {\n\t\t\t\tmargin-left: 0;\n\t\t\t}\n\t\t\t/* see gallery_shortcode() in wp-includes/media.php */\n\t\t</style>\n\t\t";
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
