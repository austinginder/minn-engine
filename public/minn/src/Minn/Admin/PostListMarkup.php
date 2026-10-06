<?php

declare(strict_types=1);

namespace Minn\Admin;

/**
 * The markup a post list writes beside each post, as the reference writes
 * it (probe placeholders-admin): the states after a title (an em dash,
 * then each state in its span, a comma inside every span but the last),
 * and the hidden fields quick edit reads, one div each, in its order.
 */
final class PostListMarkup
{
    /**
     * The states after a post's title, or nothing when it has none.
     *
     * @param array<string|int, string> $states
     */
    public static function states(array $states): string
    {
        if ($states === []) {
            return '';
        }
        $out = ' &mdash; ';
        $last = count($states) - 1;
        foreach (array_values($states) as $i => $state) {
            $out .= "<span class='post-state'>" . $state . ($i < $last ? ', ' : '') . '</span>';
        }
        return $out;
    }

    /**
     * The quick edit fields: the escaped title and slug, the author, the two
     * discussion statuses, the status, the date in its six parts and the
     * password, then what the caller adds (parent, template, order, terms,
     * sticky, format) unbroken, and the closing tag.
     *
     * @param array{title: string, name: string, author: int, comments: string, pings: string, status: string, date: string, password: string} $post
     */
    public static function inline(int $id, array $post, string $more): string
    {
        $time = strtotime($post['date']) ?: 0;
        $out = "\n<div class=\"hidden\" id=\"inline_{$id}\">\n";
        $fields = ['post_title' => $post['title'], 'post_name' => $post['name'], 'post_author' => (string) $post['author'], 'comment_status' => $post['comments'], 'ping_status' => $post['pings'], '_status' => $post['status'], 'jj' => date('d', $time), 'mm' => date('m', $time), 'aa' => date('Y', $time), 'hh' => date('H', $time), 'mn' => date('i', $time), 'ss' => date('s', $time)];
        foreach ($fields as $class => $value) {
            $out .= "\t<div class=\"{$class}\">{$value}</div>\n";
        }
        return $out . "\t<div class=\"post_password\">" . $post['password'] . '</div>' . $more . '</div>';
    }
}
