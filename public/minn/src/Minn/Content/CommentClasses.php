<?php

declare(strict_types=1);

namespace Minn\Content;

/** The class tokens a rendered comment carries: its type, its author, odd/even and thread alternation, depth, then the caller's extras. */
final class CommentClasses
{
    /**
     * The class list a comment's list item carries, in the reference's order.
     *
     * @param list<string> $extra
     * @return list<string>
     */
    public static function build(string $type, ?string $authorClass, bool $byPostAuthor, int $alt, int $depth, int $threadAlt, array $extra): array
    {
        $classes = [$type === '' ? 'comment' : $type];
        if ($authorClass !== null) {
            $classes[] = 'byuser';
            $classes[] = 'comment-author-' . $authorClass;
            if ($byPostAuthor) {
                $classes[] = 'bypostauthor';
            }
        }
        array_push($classes, ...($alt % 2 ? ['odd', 'alt'] : ['even']));
        if ($depth === 1) {
            array_push($classes, ...($threadAlt % 2 ? ['thread-odd', 'thread-alt'] : ['thread-even']));
        }
        $classes[] = 'depth-' . $depth;
        return [...$classes, ...$extra];
    }
}
