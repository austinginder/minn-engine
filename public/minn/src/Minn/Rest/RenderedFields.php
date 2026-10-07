<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Content\Blocks;
use Minn\Content\Excerpt;
use Minn\Content\PostRecord;
use Minn\Content\Texturize;
use Minn\Runtime\Runtime;

/**
 * A post's rendered title, content and excerpt as a REST response carries
 * them. Without plugins they are Minn's own render. With plugins loaded
 * they pass the filters the reference's controller runs, with the post set
 * up as it sets it up: the_title over the stored title, the engine's
 * render of the content then the rest of the_content (shortcodes and every
 * plugin's callback), and get_the_excerpt then the_excerpt.
 */
final class RenderedFields
{
    /** The rendered title. */
    public static function title(PostRecord $p): string
    {
        return Runtime::booted() ? (string) self::withPost($p, static fn () => \apply_filters('the_title', $p->title, $p->id)) : Texturize::html($p->title);
    }

    /** The rendered content. */
    public static function content(PostRecord $p): string
    {
        $html = Blocks::render($p->content);
        return Runtime::booted() ? (string) self::withPost($p, static fn () => Runtime::contentFilter(Runtime::shortcodes()->apply($html))) : $html;
    }

    /** The rendered excerpt. */
    public static function excerpt(PostRecord $p): string
    {
        if (!Runtime::booted()) {
            return Excerpt::render($p);
        }
        return (string) self::withPost($p, static fn (\WP_Post $post) => \apply_filters('the_excerpt', \apply_filters('get_the_excerpt', $post->post_excerpt, $post)));
    }

    /**
     * Runs a render with the post as the global post and its postdata set
     * up, then puts back whatever was there.
     *
     * @param Closure(\WP_Post): mixed $render
     */
    private static function withPost(PostRecord $p, Closure $render): mixed
    {
        $post = \get_post($p->id);
        if (!$post instanceof \WP_Post) {
            return $render(new \WP_Post((object) ['ID' => $p->id, 'post_title' => $p->title, 'post_content' => $p->content, 'post_excerpt' => $p->excerpt]));
        }
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;
        \setup_postdata($post);
        try {
            return $render($post);
        } finally {
            $GLOBALS['post'] = $previous;
            if ($previous instanceof \WP_Post) {
                \setup_postdata($previous);
            }
        }
    }

    /**
     * A post's class list as get_post_class hands it back once plugin code
     * filters post_class (WooCommerce drops hentry and adds a product's
     * stock and type): the filter's answer without repeats, in order.
     *
     * @param list<string> $classes
     * @return list<string>
     */
    public static function classes(array $classes, int $postId): array
    {
        if (!Runtime::booted() || !Runtime::hooks()->has('post_class')) {
            return $classes;
        }
        return array_values(array_unique(array_map('strval', (array) \apply_filters('post_class', $classes, [], $postId))));
    }
}
