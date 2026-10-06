<?php

declare(strict_types=1);

namespace Minn\Front;

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
        $post = \get_post($post);
        if (!$post instanceof \WP_Post || !\is_post_publicly_viewable($post)) {
            return false;
        }
        $limits = (array) \apply_filters('oembed_min_max_width', ['min' => 200, 'max' => 600]);
        $width = (int) min(max((int) $limits['min'], $width), (int) $limits['max']);
        $height = (int) max(ceil($width / 16 * 9), 200);
        $data = [
            'version' => '1.0',
            'provider_name' => \get_bloginfo('name'),
            'provider_url' => \get_home_url(),
            'author_name' => \get_bloginfo('name'),
            'author_url' => \get_home_url(),
            'title' => \get_the_title($post),
            'type' => 'link',
        ];
        $author = \get_userdata((int) $post->post_author);
        if ($author instanceof \WP_User) {
            $data['author_name'] = $author->display_name;
            $data['author_url'] = \get_author_posts_url($author->ID);
        }
        return \apply_filters('oembed_response_data', $data, $post, $width, $height);
    }

    /** get_oembed_response_data_rich: the size, the markup, and a thumbnail when the post has one. @param array<string, mixed> $data @return array<string, mixed> */
    public static function rich(array $data, \WP_Post $post, int $width, int $height): array
    {
        $data['width'] = abs($width);
        $data['height'] = abs($height);
        $data['type'] = 'rich';
        $data['html'] = self::html($width, $height, $post);
        $thumbnail = \wp_attachment_is_image($post) ? $post->ID : (int) \get_post_thumbnail_id($post);
        if ($thumbnail > 0) {
            $image = \wp_get_attachment_image_src($thumbnail, [$width, 99999]);
            if (is_array($image)) {
                [$data['thumbnail_url'], $data['thumbnail_width'], $data['thumbnail_height']] = $image;
            }
        }
        return $data;
    }

    /** get_post_embed_html: the blockquote, the iframe and its script, through embed_html; false for no post. */
    public static function html(int $width, int $height, mixed $post): string|false
    {
        $post = \get_post($post);
        if (!$post instanceof \WP_Post) {
            return false;
        }
        $secret = \wp_generate_password(10, false);
        $title = \get_the_title($post);
        $output = sprintf('<blockquote class="wp-embedded-content" data-secret="%1$s"><a href="%2$s">%3$s</a></blockquote>', \esc_attr($secret), \esc_url(\get_permalink($post)), $title);
        $output .= sprintf(
            '<iframe sandbox="allow-scripts" security="restricted" src="%1$s" width="%2$d" height="%3$d" title="%4$s" data-secret="%5$s" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="wp-embedded-content"></iframe>',
            \esc_url(\get_post_embed_url($post) . '#?secret=' . $secret),
            abs($width),
            abs($height),
            \esc_attr(sprintf('&#8220;%1$s&#8221; &#8212; %2$s', $title, \get_bloginfo('name'))),
            \esc_attr($secret),
        );
        $script = (string) file_get_contents(MINN_ENGINE_DIR . '/assets/wp-embed.min.js');
        $output .= "<script>\n" . $script . "\n//# sourceURL=" . \includes_url('js/wp-embed.min.js') . "\n</script>\n";
        return (string) \apply_filters('embed_html', $output, $post, $width, $height);
    }

    /** get_post_embed_url: the post's /embed/ address (?embed=true without pretty links), through post_embed_url. */
    public static function url(mixed $post): string|false
    {
        $post = \get_post($post);
        if (!$post instanceof \WP_Post) {
            return false;
        }
        $permalink = (string) \get_permalink($post);
        $url = (string) \get_option('permalink_structure') !== '' && !str_contains($permalink, '?')
            ? \trailingslashit($permalink) . \user_trailingslashit('embed')
            : \add_query_arg(['embed' => 'true'], $permalink);
        return (string) \apply_filters('post_embed_url', $url, $post);
    }

    /** _oembed_create_xml: the data as an oembed document, nested arrays as nested elements. @param array<string, mixed> $data */
    public static function xml(array $data): string
    {
        $root = new \SimpleXMLElement('<oembed></oembed>');
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
            $node->addChild($key, htmlspecialchars((string) $value, ENT_XML1));
        }
    }
}
