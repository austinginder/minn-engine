<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Objects\Post;
use Minn\Objects\User;
use Minn\Runtime\AssetQueue;
use Minn\Runtime\Attachments;
use Minn\Runtime\Handoff;
use Minn\Runtime\PostLinks;
use Minn\Runtime\PostReads;
use Minn\Runtime\PostTags;
use Minn\Runtime\QueryArgs;
use Minn\Runtime\Records;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteInfo;
use Minn\Runtime\SiteUrls;
use Minn\Support\Escape;
use Minn\Support\Url;

/**
 * A post as other sites embed it, the oEmbed provider side, as the
 * reference answers (probe oembed): the data (version, the site as
 * provider, the author or else the site, the title) through
 * oembed_response_data, whose default makes it "rich" with the embed
 * markup: a blockquote linking the post, a sandboxed iframe of its /embed/
 * page carrying a fresh secret, and the inline script that sizes it. Only
 * a publicly viewable post is embeddable; the width is held between 200
 * and 600 (oembed_min_max_width) and the height is a 16:9 share of it, at
 * least 200.
 */
final class PostEmbed
{
    /** get_oembed_response_data: the data, or false when the post cannot be embedded. @return array<string, mixed>|false */
    public static function data(mixed $post, int $width): array|false
    {
        $post = Records::post($post);
        if (!$post instanceof Post || !PostReads::publiclyViewable($post)) {
            return false;
        }
        $limits = (array) Runtime::hooks()->filter('oembed_min_max_width', [['min' => 200, 'max' => 600]]);
        $width = (int) min(max((int) $limits['min'], $width), (int) $limits['max']);
        $height = (int) max(ceil($width / 16 * 9), 200);
        $data = [
            'version' => '1.0',
            'provider_name' => SiteInfo::get('name'),
            'provider_url' => SiteUrls::home(),
            'author_name' => SiteInfo::get('name'),
            'author_url' => SiteUrls::home(),
            'title' => PostTags::title($post),
            'type' => 'link',
        ];
        $author = Handoff::user((int) $post->post_author);
        if ($author instanceof User) {
            $data['author_name'] = $author->display_name;
            $data['author_url'] = PostTags::authorPostsUrl($author->ID);
        }
        return Runtime::hooks()->filter('oembed_response_data', [$data, $post, $width, $height]);
    }

    /** get_oembed_response_data_rich: the size, the markup, and a thumbnail when the post has one. @param array<string, mixed> $data @return array<string, mixed> */
    public static function rich(array $data, Post $post, int $width, int $height): array
    {
        $data['width'] = abs($width);
        $data['height'] = abs($height);
        $data['type'] = 'rich';
        $data['html'] = self::html($width, $height, $post);
        $thumbnail = Attachments::isImage($post) ? $post->ID : (int) PostTags::thumbnailId($post);
        if ($thumbnail > 0) {
            // The size chosen by the width alone (probe embed-remaining).
            $image = \wp_get_attachment_image_src($thumbnail, [$width, 0]);
            if (is_array($image)) {
                [$data['thumbnail_url'], $data['thumbnail_width'], $data['thumbnail_height']] = $image;
            }
        }
        return $data;
    }

    /** get_post_embed_html: the blockquote, the iframe and its script, through embed_html; false for no post. */
    public static function html(int $width, int $height, mixed $post): string|false
    {
        $post = Records::post($post);
        if (!$post instanceof Post) {
            return false;
        }
        $secret = Handoff::password(10, false);
        $title = PostTags::title($post);
        $output = sprintf('<blockquote class="wp-embedded-content" data-secret="%1$s"><a href="%2$s">%3$s</a></blockquote>', Escape::attr($secret), Escape::url(PostLinks::permalink($post)), $title);
        $output .= sprintf(
            '<iframe sandbox="allow-scripts" security="restricted" src="%1$s" width="%2$d" height="%3$d" title="%4$s" data-secret="%5$s" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe>',
            Escape::url(self::url($post) . '#?secret=' . $secret),
            abs($width),
            abs($height),
            Escape::attr(sprintf('&#8220;%1$s&#8221; &#8212; %2$s', $title, SiteInfo::get('name'))),
            Escape::attr($secret),
        );
        $script = (string) file_get_contents(MINN_ENGINE_DIR . '/assets/embed-host.js');
        $output .= AssetQueue::inlineTag($script . "\n//# sourceURL=" . SiteUrls::includes('js/wp-embed.min.js'));
        return (string) Runtime::hooks()->filter('embed_html', [$output, $post, $width, $height]);
    }

    /** get_post_embed_url: the post's /embed/ address (?embed=true without pretty links), through post_embed_url. */
    public static function url(mixed $post): string|false
    {
        $post = Records::post($post);
        if (!$post instanceof Post) {
            return false;
        }
        $permalink = (string) PostLinks::permalink($post);
        $url = (string) Runtime::options()->filtered('permalink_structure') !== '' && !str_contains($permalink, '?')
            ? Url::withTrailingSlash($permalink) . SiteUrls::trailing('embed')
            : QueryArgs::add(['embed' => 'true'], $permalink);
        return (string) Runtime::hooks()->filter('post_embed_url', [$url, $post]);
    }

    /** The data as an oEmbed XML document (_oembed_create_xml); false for no data. */
    public static function xmlDocument(mixed $data, mixed $node = null): mixed
    {
        return (is_array($data) || is_object($data)) && (array) $data !== [] ? self::xml((array) $data, $node instanceof \SimpleXMLElement ? $node : null) : false;
    }

    /** _oembed_create_xml: the data as an oembed document (or under the root given), nested arrays as nested elements. @param array<string, mixed> $data */
    public static function xml(array $data, ?\SimpleXMLElement $root = null): string
    {
        $root ??= new \SimpleXMLElement('<oembed></oembed>');
        self::append($root, $data);
        return (string) $root->asXML();
    }

    /** @param array<string, mixed> $data */
    private static function append(\SimpleXMLElement $node, array $data): void
    {
        foreach ($data as $key => $value) {
            $key = is_numeric($key) ? 'oembed' : (string) $key;
            if (is_array($value) || is_object($value)) {
                self::append($node->addChild($key), (array) $value);
                continue;
            }
            // As written: an entity reference stays one (a numeric one becomes its character), a bare ampersand is escaped.
            $node->addChild($key, (string) preg_replace('/&(?!(?:[A-Za-z][A-Za-z0-9]*|#[0-9]+|#[xX][0-9A-Fa-f]+);)/', '&amp;', (string) $value));
        }
    }
}
