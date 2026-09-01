<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Db;

/**
 * What happened lately, as the overview and the bell tell it: the caller's
 * own recent posts and the latest comments, and the events behind one
 * chart bar. Also the visibility rule every comment row is put through.
 */
final readonly class ActivityFeed
{
    public function __construct(
        private Db $db,
        private Users $users,
        private Capabilities $capabilities,
    ) {
    }

    /**
     * The overview's four most recent items, times already worded ("2 hours ago").
     *
     * Oracle-caught quirk: the reference's recent-activity query runs with a
     * multi-type post_type plus perm=editable, and on that combination it
     * restricts EVERY status to post_author = caller, admins included,
     * published posts included. Authorless posts appear for nobody. The
     * engine reproduces the observed behavior, not the intent.
     *
     * @return list<array>
     */
    public function recent(int $userId, int $now, int $offset): array
    {
        $items = [...$this->ownPosts($userId, $offset), ...$this->latestComments($userId)];
        usort($items, static fn (array $a, array $b) => $b['time'] - $a['time']);
        $items = array_slice($items, 0, 4);
        foreach ($items as &$item) {
            $item['time'] = Format::humanTimeDiff($item['time'], $now) . ' ago';
        }
        unset($item);
        return $items;
    }

    /**
     * The events behind one chart bar, (from, to] GMT, newest first. Post
     * rows decode the RAW stored title; comment rows decode the texturized
     * one (the reference's asymmetry, kept).
     *
     * @return list<array>
     */
    public function between(int $userId, string $from, string $to, int $now): array
    {
        $items = [...$this->publishedBetween($from, $to), ...$this->commentsBetween($userId, $from, $to)];
        usort($items, static fn (array $a, array $b) => $b['time'] - $a['time']);
        $items = array_slice($items, 0, 100);
        foreach ($items as &$item) {
            $item['ago'] = Format::humanTimeDiff($item['time'], $now) . ' ago';
        }
        unset($item);
        return $items;
    }

    /** May this caller see a comment row that names its post? */
    public function commentVisible(int $userId, int $postId): bool
    {
        if ($this->capabilities->can($userId, 'edit_post', $postId)) {
            return true;
        }
        $password = $this->db->value("SELECT post_password FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [$postId]);
        if ($password === null || (string) $password !== '') {
            return false;
        }
        return $this->capabilities->can($userId, 'read_post', $postId);
    }

    /** A post's stored title, empty when the post is gone. */
    public function postTitle(int $postId): string
    {
        return (string) ($this->db->value("SELECT post_title FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [$postId]) ?? '');
    }

    /** A user's display name, empty when the user is gone. */
    public function displayName(int $userId): string
    {
        return $this->users->find($userId)?->displayName ?? '';
    }

    /**
     * The caller's own posts, merged from a by-modified and a by-date window.
     *
     * @return list<array>
     */
    private function ownPosts(int $userId, int $offset): array
    {
        $rows = [];
        foreach (['post_modified', 'post_date'] as $order) {
            $found = $this->db->rows(
                "SELECT ID, post_type, post_status, post_author, post_title, post_date, post_modified_gmt
                 FROM {$this->db->table('posts')}
                 WHERE post_type IN ('post','page') AND post_status IN ('publish','draft','future','pending') AND post_author = ?
                 ORDER BY {$order} DESC LIMIT 5",
                [$userId],
            );
            foreach ($found as $post) {
                $rows[(int) $post['ID']] ??= $post;
            }
        }
        $items = [];
        foreach ($rows as $post) {
            if (!$this->capabilities->can($userId, 'read_post', (int) $post['ID'])) {
                continue;
            }
            $time = strtotime($post['post_modified_gmt'] . ' UTC');
            if (!$time || $time < 0) {
                // A never-updated draft zeroes both GMT columns; post_date
                // (site-local) is the only truthful stamp.
                $time = strtotime($post['post_date'] . ' UTC') - $offset;
            }
            if (!$time || $time < 0) {
                continue;
            }
            $template = match ($post['post_status']) {
                'publish' => '%1$s published “%2$s”',
                'future' => '%1$s scheduled “%2$s”',
                default => '%1$s drafted “%2$s”',
            };
            $items[] = [
                'text' => sprintf($template, $this->displayName((int) $post['post_author']), Format::plainTitle((string) $post['post_title'])),
                'time' => $time,
                'color' => match ($post['post_status']) { 'publish' => 'green', 'future' => 'blue', default => 'accent' },
                'goto' => ['kind' => 'editor', 'type' => $post['post_type'] === 'page' ? 'pages' : 'posts', 'id' => (int) $post['ID']],
            ];
        }
        return $items;
    }

    /** @return list<array> */
    private function latestComments(int $userId): array
    {
        $where = $this->capabilities->can($userId, 'moderate_comments') ? "comment_approved IN ('0','1')" : "comment_approved = '1'";
        $recent = $this->db->rows(
            "SELECT comment_ID, comment_post_ID, comment_author, comment_approved, comment_date_gmt
             FROM {$this->db->table('comments')} WHERE {$where} ORDER BY comment_date_gmt DESC LIMIT 3",
        );
        $items = [];
        foreach ($recent as $comment) {
            if (!$this->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                continue;
            }
            $pending = $comment['comment_approved'] === '0';
            $items[] = [
                'text' => sprintf(
                    $pending ? 'Comment from %s awaiting moderation on “%s”' : '%s commented on “%s”',
                    $comment['comment_author'] !== '' ? $comment['comment_author'] : 'Anonymous',
                    Format::plainTitle($this->postTitle((int) $comment['comment_post_ID'])),
                ),
                'time' => (int) strtotime($comment['comment_date_gmt'] . ' UTC'),
                'color' => $pending ? 'amber' : 'blue',
                'goto' => ['kind' => 'comments', 'tab' => $pending ? 'hold' : 'approve'],
            ];
        }
        return $items;
    }

    /** @return list<array> */
    private function publishedBetween(string $from, string $to): array
    {
        $posts = $this->db->rows(
            "SELECT ID, post_title, post_type, post_author, post_date_gmt FROM {$this->db->table('posts')}
             WHERE post_status = 'publish' AND post_type IN ('post','page')
             AND post_date_gmt > ? AND post_date_gmt <= ?
             ORDER BY post_date_gmt DESC LIMIT 100",
            [$from, $to],
        );
        $items = [];
        foreach ($posts as $post) {
            $author = $this->displayName((int) $post['post_author']);
            $items[] = [
                'kind' => 'post',
                'id' => (int) $post['ID'],
                'type' => $post['post_type'] === 'page' ? 'pages' : 'posts',
                'text' => sprintf('%1$s published “%2$s”', $author !== '' ? $author : 'Someone', html_entity_decode($post['post_title'] !== '' ? $post['post_title'] : '(no title)', ENT_QUOTES)),
                'time' => (int) strtotime($post['post_date_gmt'] . ' UTC'),
                'color' => 'green',
            ];
        }
        return $items;
    }

    /** @return list<array> */
    private function commentsBetween(int $userId, string $from, string $to): array
    {
        $approvedOnly = !$this->capabilities->can($userId, 'moderate_comments');
        $comments = $this->db->rows(
            "SELECT comment_ID, comment_author, comment_post_ID, comment_date_gmt, comment_approved
             FROM {$this->db->table('comments')}
             WHERE comment_date_gmt > ? AND comment_date_gmt <= ?" . ($approvedOnly ? " AND comment_approved = '1'" : '') . "
             AND comment_type IN ( '', 'comment' )
             ORDER BY comment_date_gmt DESC LIMIT 300",
            [$from, $to],
        );
        $items = [];
        foreach ($comments as $comment) {
            if (!$this->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                continue;
            }
            $pending = $comment['comment_approved'] === '0';
            $title = Texturize::html($this->postTitle((int) $comment['comment_post_ID']));
            $items[] = [
                'kind' => 'comment',
                'id' => (int) $comment['comment_ID'],
                'text' => sprintf(
                    $pending ? 'Comment from %1$s awaiting moderation on “%2$s”' : '%1$s commented on “%2$s”',
                    $comment['comment_author'] !== '' ? $comment['comment_author'] : 'Anonymous',
                    html_entity_decode($title !== '' ? $title : '(no title)', ENT_QUOTES),
                ),
                'time' => (int) strtotime($comment['comment_date_gmt'] . ' UTC'),
                'color' => $pending ? 'amber' : 'blue',
            ];
        }
        return $items;
    }
}
