<?php

declare(strict_types=1);

namespace Minn\Ext\GalleryLinks;

use Minn\Content\Posts;
use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Html;

/**
 * Wraps every attachment image that carries a _gallery_link_url in the
 * anchor the plugin printed: the URL, the attachment's title, the aria
 * label, target, and rel from its meta, and a click stopper. The plugin
 * worked on the whole page through an HTML parser, so it counted every
 * image in the document, and when it linked at least one it re-wrote the
 * page with attribute names in lower case (viewBox became viewbox); the
 * same happens here, and the same count is left before the body closes.
 */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $posts = new Posts($minn->db);
        $minn->filterDocument(static function (string $html) use ($posts): string {
            $scanned = preg_match_all('/<img\s/i', $html);
            $linked = 0;
            $html = (string) preg_replace_callback('/(<a\s[^>]*>\s*)?(<img\s[^>]*\bwp-image-(\d+)\b[^>]*>)/', static function (array $m) use ($posts, &$linked): string {
                if (($m[1] ?? '') !== '') {
                    return $m[0];
                }
                $id = (int) $m[3];
                $url = (string) ($posts->meta($id, '_gallery_link_url') ?? '');
                if ($url === '') {
                    return $m[0];
                }
                $attachment = $posts->find($id);
                $linked++;
                return '<a href="' . Html::attr($url) . '" class="custom-link no-lightbox" title="' . Html::attr((string) ($attachment['post_title'] ?? '')) . '"'
                    . ' aria-label="' . Html::attr((string) ($posts->meta($id, '_gallery_link_aria') ?? '')) . '" onclick="event.stopPropagation()"'
                    . ' target="' . Html::attr((string) ($posts->meta($id, '_gallery_link_target') ?: '_self')) . '" rel="' . Html::attr((string) ($posts->meta($id, '_gallery_link_rel') ?? '')) . '">'
                    . $m[2] . '</a>';
            }, $html);
            if ($linked > 0) {
                $html = self::lowercaseAttributeNames($html);
            }
            $note = "<!-- Gallery Custom Links: {$scanned} images scanned, {$linked} linked (HtmlDomParser). -->\n";
            $at = strrpos($html, '</body>');
            return $at === false ? $html . $note : substr($html, 0, $at) . $note . substr($html, $at);
        });
    }

    private static function lowercaseAttributeNames(string $html): string
    {
        $parts = preg_split('/(<script\b.*?<\/script>|<style\b.*?<\/style>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }
            $parts[$i] = (string) preg_replace_callback(
                '/<[a-zA-Z][^>]*>/',
                static fn (array $t): string => (string) preg_replace_callback('/(\s)([A-Za-z][\w:-]*)(?==)/', static fn (array $a): string => $a[1] . strtolower($a[2]), $t[0]),
                $part,
            );
        }
        return implode('', $parts);
    }
}
