<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Content\PostRecord;
use Minn\Objects\Post;
use Minn\Runtime\Runtime;

/**
 * What a post read sets up around its rendering, as the reference does for
 * the one request: an excerpt_length given (declared on a single post,
 * read on a list too) answers the excerpt_length filter as the integer PHP
 * reads it as, so the excerpt is cut there; and a protected post read with
 * its own password, or in the edit context, has post_password_required
 * lifted for it, so its excerpt is the text and not the password form.
 * Each is taken down once the object is built, whatever happened.
 */
final class ItemRender
{
    /** The priority the request's own excerpt length answers at, after a theme's usual 10. */
    private const LENGTH_PRIORITY = 20;

    /**
     * What $render builds, with the request's excerpt length (null when none was given).
     *
     * @template T
     * @param Closure(): T $render
     * @return T
     */
    public static function around(Closure $render, mixed $length): mixed
    {
        if ($length === null) {
            return $render();
        }
        $cut = match (true) {
            is_array($length) => (int) ($length !== []),
            is_scalar($length) => (int) $length,
            default => 0,
        };
        $asked = static fn (): int => $cut;
        Runtime::hooks()->add('excerpt_length', $asked, self::LENGTH_PRIORITY);
        try {
            return $render();
        } finally {
            Runtime::hooks()->remove('excerpt_length', $asked, self::LENGTH_PRIORITY);
        }
    }

    /**
     * What $render builds with the post's password lifted, for a reader the
     * reference lets past it (the right password, or the edit context).
     *
     * @template T
     * @param Closure(): T $render
     * @return T
     */
    public static function opened(Closure $render, PostRecord $post): mixed
    {
        if (!$post->isProtected()) {
            return $render();
        }
        $id = $post->id;
        $open = static fn (mixed $required, mixed $subject = null): mixed => $subject instanceof Post && $subject->ID === $id ? false : $required;
        Runtime::hooks()->add('post_password_required', $open, 10, 2);
        try {
            return $render();
        } finally {
            Runtime::hooks()->remove('post_password_required', $open, 10);
        }
    }
}
