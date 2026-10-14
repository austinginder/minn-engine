<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Content\Blocks;
use Minn\Content\PostRecord;
use Minn\Objects\Post;
use Minn\Runtime\Handoff;
use Minn\Runtime\PostData;
use Minn\Runtime\Records;
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
        return (string) self::withPost($p, static fn () => Runtime::hooks()->filter('the_title', [$p->title, $p->id]));
    }

    /** The rendered content. */
    public static function content(PostRecord $p): string
    {
        $html = Blocks::render($p->content);
        return (string) self::withPost($p, static fn () => Runtime::contentFilter(Runtime::shortcodes()->apply($html)));
    }

    /** The rendered excerpt. */
    public static function excerpt(PostRecord $p): string
    {
        return (string) self::withPost($p, static fn (Post $post) => Runtime::hooks()->filter('the_excerpt', [Runtime::hooks()->filter('get_the_excerpt', [$post->post_excerpt, $post])]));
    }

    /**
     * Runs a render with the post as the global post and its postdata set
     * up, then puts back whatever was there.
     *
     * @param Closure(Post): mixed $render
     */
    /** The post made current and set up for a response that renders none of its text, as the reference sets up every item it prepares. */
    public static function setUp(PostRecord $p): void
    {
        self::withPost($p, static fn (): null => null);
    }

    private static function withPost(PostRecord $p, Closure $render): mixed
    {
        $post = Records::post($p->id);
        if (!$post instanceof Post) {
            return $render(Handoff::newPost((object) ['ID' => $p->id, 'post_title' => $p->title, 'post_content' => $p->content, 'post_excerpt' => $p->excerpt]));
        }
        $previous = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;
        PostData::setup($post);
        try {
            return $render($post);
        } finally {
            $GLOBALS['post'] = $previous;
            if ($previous instanceof Post) {
                PostData::setup($previous);
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
        if (!Runtime::hooks()->has('post_class')) {
            return $classes;
        }
        return array_values(array_unique(array_map('strval', (array) Runtime::hooks()->filter('post_class', [$classes, [], $postId]))));
    }
}
