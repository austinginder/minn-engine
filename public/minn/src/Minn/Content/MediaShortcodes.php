<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Runtime\Runtime;

/**
 * The [video] and [audio] shortcodes as the reference prints them: a
 * plugin may take either over first (wp_*_shortcode_override, handed the
 * shortcode's own attributes and its instance number), then the source by
 * src or by each format's attribute (a file of another kind is only a
 * link; YouTube and Vimeo addresses are video of their own; with none,
 * the post's first attached video or audio), the player's attributes (a
 * video no wider than the content, its height in proportion; the flags
 * bare), each source numbered by the instance, a fallback link, and the
 * class, library and final HTML through their filters.
 */
final class MediaShortcodes
{
    private const YOUTUBE = '#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#';
    private const VIMEO = '#^https?://(.+\.)?vimeo\.com/.*#';

    /** [video]. @param array<string, mixed>|string $attr */
    public static function video(array|string $attr, string $content): ?string
    {
        $instance = self::instance('video');
        $override = \apply_filters('wp_video_shortcode_override', '', $attr, $content, $instance);
        if ($override !== '') {
            return (string) $override;
        }
        $types = (array) \wp_get_video_extensions();
        $defaults = ['src' => '', 'poster' => '', 'loop' => '', 'autoplay' => '', 'muted' => 'false', 'preload' => 'metadata', 'width' => 640, 'height' => 360, 'class' => 'wp-video-shortcode'] + array_fill_keys($types, '');
        $atts = \shortcode_atts($defaults, (array) $attr, 'video');
        $hosted = match (true) {
            preg_match(self::YOUTUBE, (string) $atts['src']) === 1 => 'video/youtube',
            preg_match(self::VIMEO, (string) $atts['src']) === 1 => 'video/vimeo',
            default => null,
        };
        if ($hosted === 'video/vimeo') {
            // The reference rewrites a Vimeo address to its bare form, with loop, for the player.
            $parts = (array) parse_url((string) $atts['src']);
            $atts['src'] = \add_query_arg('loop', \wp_validate_boolean($atts['loop']) ? '1' : '0', 'https://' . ($parts['host'] ?? '') . ($parts['path'] ?? ''));
        }
        $sources = self::sources($atts, $types, $hosted, 'video');
        if (!is_array($sources)) {
            return $sources;
        }
        [$video, $postId] = [$sources['attachment'], self::postId()];
        $atts = $sources['atts'];
        [$atts['width'], $atts['height']] = self::fitted((int) $atts['width'], (int) $atts['height']);
        $library = self::library('video');
        $atts['class'] = \apply_filters('wp_video_shortcode_class', $atts['class'], $atts);
        $attributes = self::attributes(['class' => $atts['class'], 'id' => sprintf('video-%d-%d', $postId, $instance), 'width' => \absint($atts['width']), 'height' => \absint($atts['height']), 'poster' => \esc_url((string) $atts['poster']), 'loop' => \wp_validate_boolean($atts['loop']), 'autoplay' => \wp_validate_boolean($atts['autoplay']), 'muted' => \wp_validate_boolean($atts['muted']), 'preload' => $atts['preload']], ['poster', 'loop', 'autoplay', 'muted', 'preload']);
        $html = '<video ' . $attributes . ' controls="controls">' . self::sourceTags($atts, $sources['order'], $instance, $hosted) . self::fallback($sources['file'], $library) . '</video>';
        $output = sprintf('<div style="%s" class="wp-video">%s</div>', empty($atts['width']) ? '' : sprintf('width: %dpx;', $atts['width']), $html);
        return (string) \apply_filters('wp_video_shortcode', $output, $atts, $video, $postId, $library);
    }

    /** [audio]. @param array<string, mixed>|string $attr */
    public static function audio(array|string $attr, string $content): ?string
    {
        $instance = self::instance('audio');
        $override = \apply_filters('wp_audio_shortcode_override', '', $attr, $content, $instance);
        if ($override !== '') {
            return (string) $override;
        }
        $types = (array) \wp_get_audio_extensions();
        $defaults = ['src' => '', 'loop' => '', 'autoplay' => '', 'muted' => 'false', 'preload' => 'none', 'class' => 'wp-audio-shortcode', 'style' => 'width: 100%;'] + array_fill_keys($types, '');
        $atts = \shortcode_atts($defaults, (array) $attr, 'audio');
        $sources = self::sources($atts, $types, null, 'audio');
        if (!is_array($sources)) {
            return $sources;
        }
        [$audio, $postId, $atts] = [$sources['attachment'], self::postId(), $sources['atts']];
        $library = self::library('audio');
        $atts['class'] = \apply_filters('wp_audio_shortcode_class', $atts['class'], $atts);
        $attributes = self::attributes(['class' => $atts['class'], 'id' => sprintf('audio-%d-%d', $postId, $instance), 'loop' => \wp_validate_boolean($atts['loop']), 'autoplay' => \wp_validate_boolean($atts['autoplay']), 'muted' => \wp_validate_boolean($atts['muted']), 'preload' => $atts['preload'], 'style' => $atts['style']], ['loop', 'autoplay', 'muted', 'preload']);
        $html = '<audio ' . $attributes . ' controls="controls">' . self::sourceTags($atts, $sources['order'], $instance, null) . self::fallback($sources['file'], $library) . '</audio>';
        return (string) \apply_filters('wp_audio_shortcode', $html, $atts, $audio, $postId, $library);
    }

    /** get_attached_media: the post's attachments of a kind ("video", "audio", a MIME type), as the reference's children query finds them. */
    public static function attached(string $type, mixed $post): array
    {
        $post = \get_post($post);
        if (!$post) {
            return [];
        }
        $args = (array) \apply_filters('get_attached_media_args', ['post_parent' => $post->ID, 'post_type' => 'attachment', 'post_mime_type' => $type, 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC'], $type, $post);
        return (array) \apply_filters('get_attached_media', (array) \get_children($args), $type, $post);
    }

    /**
     * The sources a shortcode names: the order they print in, the first
     * file, and the attachment when the post's own media stands in. A
     * src of another kind answers as a link; nothing at all as null.
     *
     * @param array<string, mixed> $atts
     * @param list<string> $types
     * @return array{order: list<string>, file: string, attachment: ?\WP_Post, atts: array<string, mixed>}|string|null
     */
    private static function sources(array $atts, array $types, ?string $hosted, string $kind): array|string|null
    {
        $attachment = null;
        if (!empty($atts['src'])) {
            $ext = strtolower((string) (\wp_check_filetype((string) $atts['src'], \wp_get_mime_types())['ext'] ?? ''));
            if ($hosted === null && !in_array($ext, $types, true)) {
                return sprintf('<a class="wp-embedded-%s" href="%s">%s</a>', $kind, \esc_url((string) $atts['src']), \esc_html((string) $atts['src']));
            }
            $types = ['src', ...$types];
        } elseif (!self::anyOwnFormat($atts, $types)) {
            $media = self::attached($kind, self::postId());
            $attachment = $media === [] ? null : reset($media);
            $atts['src'] = $attachment instanceof \WP_Post ? (string) \wp_get_attachment_url($attachment->ID) : '';
            if ($atts['src'] === '') {
                return null;
            }
            $types = ['src', ...$types];
        }
        $file = '';
        foreach ($types as $type) {
            if (!empty($atts[$type]) && $file === '') {
                $file = (string) $atts[$type];
            }
        }
        return ['order' => $types, 'file' => $file, 'attachment' => $attachment, 'atts' => $atts];
    }

    /** Whether any format attribute names a file of its own kind. @param array<string, mixed> $atts @param list<string> $types */
    private static function anyOwnFormat(array $atts, array $types): bool
    {
        foreach ($types as $type) {
            if (!empty($atts[$type]) && strtolower((string) (\wp_check_filetype((string) $atts[$type], \wp_get_mime_types())['ext'] ?? '')) === $type) {
                return true;
            }
        }
        return false;
    }

    /** Each source as a source tag, numbered by the instance (src a hosted video's own type when it is one). @param array<string, mixed> $atts @param list<string> $order */
    private static function sourceTags(array $atts, array $order, int $instance, ?string $hosted): string
    {
        $out = '';
        foreach ($order as $fallback) {
            if (empty($atts[$fallback])) {
                continue;
            }
            $type = $fallback === 'src' && $hosted !== null ? $hosted : (string) (\wp_check_filetype((string) $atts[$fallback], \wp_get_mime_types())['type'] ?? '');
            $out .= sprintf('<source type="%s" src="%s" />', $type, \esc_url((string) \add_query_arg('_', $instance, (string) $atts[$fallback])));
        }
        return $out;
    }

    /** The attributes as written: the flags bare when on, the empty ones left out. @param array<string, mixed> $attributes @param list<string> $omitEmpty */
    private static function attributes(array $attributes, array $omitEmpty): string
    {
        $out = [];
        foreach ($attributes as $name => $value) {
            if (in_array($name, $omitEmpty, true) && empty($value)) {
                continue;
            }
            $out[] = in_array($name, ['loop', 'autoplay', 'muted'], true) && $value === true ? $name : $name . '="' . \esc_attr((string) $value) . '"';
        }
        return implode(' ', $out);
    }

    /** A video no wider than the content: wider, it takes the content's width and keeps its proportion. @return array{0: int, 1: int} */
    private static function fitted(int $width, int $height): array
    {
        $content = (int) ($GLOBALS['content_width'] ?? 0);
        return $content > 0 && $width > $content ? [$content, (int) round($height * $content / $width)] : [$width, $height];
    }

    /** The player library (wp_*_shortcode_library), its scripts and styles queued when it is the reference's own. */
    private static function library(string $kind): string
    {
        $library = (string) \apply_filters("wp_{$kind}_shortcode_library", 'mediaelement');
        if ($library === 'mediaelement' && \did_action('init')) {
            \wp_enqueue_style('wp-mediaelement');
            \wp_enqueue_script('wp-mediaelement');
        }
        return $library;
    }

    /** The link a player without the library falls back to (wp_mediaelement_fallback). */
    private static function fallback(string $file, string $library): string
    {
        return $library === 'mediaelement' ? (string) \apply_filters('wp_mediaelement_fallback', sprintf('<a href="%1$s">%1$s</a>', \esc_url($file)), $file) : '';
    }

    private static function postId(): int
    {
        return \get_post() ? (int) \get_the_ID() : 0;
    }

    /** The shortcode's instance number this request, counted whether or not a plugin takes it over. */
    private static function instance(string $kind): int
    {
        $key = 'av_instance_' . $kind;
        $n = (int) (Runtime::current()->get($key) ?? 0) + 1;
        Runtime::current()->set($key, $n);
        return $n;
    }
}
