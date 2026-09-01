<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Db;
use Minn\Support\Serialized;

/**
 * The overview payload: stat cards, the activity chart, and the recent
 * activity feed, plus the per-bar activity drill-down.
 */
final readonly class Dashboard
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Users $users,
        private Capabilities $capabilities,
        private string $uploadsDir,
    ) {
    }

    /**
     * Oracle-caught quirk: the reference's recent-activity query runs with a
     * multi-type post_type plus perm=editable, and on that combination it
     * restricts EVERY status to post_author = caller, admins included,
     * published posts included. Authorless posts appear for nobody. The
     * engine reproduces the observed behavior, not the intent.
     */
    public function overview(int $userId, int $days): array
    {
        $now = time();
        $offset = $this->site->gmtOffset();
        $posts = $this->statusCounts('post');
        $pages = $this->statusCounts('page');
        $media = $this->statusCounts('attachment');
        $comments = $this->commentCounts();

        $drafts = $posts['draft'] ?? 0;
        // The count deltas are cap-gated the way the matching views are: a
        // site-wide draft total belongs to edit_others_posts, the moderation
        // queue size to moderate_comments.
        $seesDrafts = $this->capabilities->can($userId, 'edit_others_posts');
        $moderates = $this->capabilities->can($userId, 'moderate_comments');
        $stats = [
            $this->postsCard($posts, $seesDrafts),
            $this->pagesCard($pages),
            ($comments['approved'] === 0 && $comments['moderated'] === 0 && $this->capabilities->can($userId, 'list_users'))
                ? $this->usersCard()
                : $this->commentsCard($comments, $moderates),
            $this->mediaCard($media),
        ];

        // Activity chart: published posts plus all comments per bucket.
        $bucketDays = $days > 45 ? 7 : 1;
        $buckets = (int) ceil($days / $bucketDays);
        $series = array_fill(0, $buckets, 0);
        $since = gmdate('Y-m-d H:i:s', $now - $days * 86400);
        $dates = array_merge(
            array_column($this->db->rows(
                "SELECT post_date_gmt FROM {$this->db->table('posts')}
                 WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_date_gmt >= ?",
                [$since],
            ), 'post_date_gmt'),
            array_column($this->db->rows(
                "SELECT comment_date_gmt FROM {$this->db->table('comments')} WHERE comment_date_gmt >= ?",
                [$since],
            ), 'comment_date_gmt'),
        );
        foreach ($dates as $date) {
            $age = $now - strtotime($date . ' UTC');
            $index = $buckets - 1 - (int) floor($age / ($bucketDays * 86400));
            if ($index >= 0 && $index < $buckets) {
                $series[$index]++;
            }
        }
        $chart = [];
        foreach ($series as $index => $count) {
            $offsetDays = ($buckets - 1 - $index) * $bucketDays;
            $chart[] = [
                'label' => $bucketDays === 1
                    ? gmdate('M j', $now - $offsetDays * 86400 + $offset)
                    : 'Week of ' . gmdate('M j', $now - ($offsetDays + $bucketDays - 1) * 86400 + $offset),
                'value' => $count,
                'from' => gmdate('Y-m-d H:i:s', $now - ($buckets - $index) * $bucketDays * 86400),
                'to' => gmdate('Y-m-d H:i:s', $now - ($buckets - 1 - $index) * $bucketDays * 86400),
            ];
        }

        // Recent activity: the caller's own posts (see the quirk above),
        // merged from a by-modified and a by-date window, then recent comments.
        $activity = [];
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
            $activity[] = [
                'text' => sprintf($template, $this->displayName((int) $post['post_author']), Format::plainTitle((string) $post['post_title'])),
                'time' => $time,
                'color' => match ($post['post_status']) { 'publish' => 'green', 'future' => 'blue', default => 'accent' },
                'goto' => ['kind' => 'editor', 'type' => $post['post_type'] === 'page' ? 'pages' : 'posts', 'id' => (int) $post['ID']],
            ];
        }

        $where = $this->capabilities->can($userId, 'moderate_comments') ? "comment_approved IN ('0','1')" : "comment_approved = '1'";
        $recent = $this->db->rows(
            "SELECT comment_ID, comment_post_ID, comment_author, comment_approved, comment_date_gmt
             FROM {$this->db->table('comments')} WHERE {$where} ORDER BY comment_date_gmt DESC LIMIT 3",
        );
        foreach ($recent as $comment) {
            if (!$this->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                continue;
            }
            $pending = $comment['comment_approved'] === '0';
            $activity[] = [
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
        usort($activity, static fn (array $a, array $b) => $b['time'] - $a['time']);
        $activity = array_slice($activity, 0, 4);
        foreach ($activity as &$item) {
            $item['time'] = Format::humanTimeDiff($item['time'], $now) . ' ago';
        }
        unset($item);

        $hour = (int) gmdate('G', $now + $offset);
        $catalog = $this->metricCatalog($userId, $stats, $posts, $pages, $comments, $media, $seesDrafts, $moderates);
        $layout = $this->metricLayout($userId, $catalog, $stats);
        return [
            'stats' => $stats,
            'metrics' => $catalog,
            'metricKeys' => $layout['keys'],
            'metricDefaults' => $layout['defaults'],
            'metricCustom' => $layout['custom'],
            'canSetMetricDefaults' => $this->capabilities->can($userId, 'manage_options'),
            'chart' => $chart,
            'traffic' => null,
            'activity' => $activity,
            // No extension runtime yet: the store and traffic sections are honestly absent.
            'store' => null,
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'),
        ];
    }

    /** @param array<string, int> $posts */
    private function postsCard(array $posts, bool $seesDrafts): array
    {
        $drafts = $posts['draft'] ?? 0;
        return [
            'key' => 'posts',
            'group' => 'content',
            'label' => 'Published posts',
            'value' => Format::number($posts['publish'] ?? 0),
            'delta' => $seesDrafts ? Format::number($drafts) . ($drafts === 1 ? ' draft' : ' drafts') : 'published',
            'up' => null,
            'goto' => 'content:posts',
        ];
    }

    /** @param array<string, int> $pages */
    private function pagesCard(array $pages): array
    {
        return ['key' => 'pages', 'group' => 'content', 'label' => 'Pages', 'value' => Format::number($pages['publish'] ?? 0), 'delta' => 'published', 'up' => null, 'goto' => 'content:pages'];
    }

    private function usersCard(): array
    {
        return ['key' => 'users', 'group' => 'people', 'label' => 'Users', 'value' => Format::number($this->users->count()), 'delta' => 'registered', 'up' => null, 'goto' => 'users'];
    }

    /** @param array<string, int> $comments */
    private function commentsCard(array $comments, bool $moderates): array
    {
        return [
            'key' => 'comments',
            'group' => 'content',
            'label' => 'Comments',
            'value' => Format::number($comments['approved']),
            'delta' => $moderates ? Format::number($comments['moderated']) . ' pending' : 'approved',
            'up' => $moderates && $comments['moderated'] > 0 ? 'warn' : null,
            'goto' => 'comments',
        ];
    }

    /** @param array<string, int> $media */
    private function mediaCard(array $media): array
    {
        return ['key' => 'media', 'group' => 'content', 'label' => 'Media files', 'value' => Format::number($media['inherit'] ?? 0), 'delta' => Format::size($this->uploadsSize()) . ' used', 'up' => null, 'goto' => 'media'];
    }

    /**
     * The full metric catalog behind the Overview cards: the default layout
     * first, then every cap-gated card the caller may pick. The store group
     * needs the WooCommerce runtime and is honestly absent.
     *
     * @param list<array> $stats
     * @param array<string, int> $posts
     * @param array<string, int> $pages
     * @param array<string, int> $comments
     * @param array<string, int> $media
     * @return list<array>
     */
    private function metricCatalog(int $userId, array $stats, array $posts, array $pages, array $comments, array $media, bool $seesDrafts, bool $moderates): array
    {
        $catalog = [];
        $have = [];
        $add = static function (array $row) use (&$catalog, &$have): void {
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || isset($have[$key])) {
                return;
            }
            $have[$key] = true;
            $catalog[] = $row;
        };
        foreach ($stats as $row) {
            $add($row);
        }
        $add($this->postsCard($posts, $seesDrafts));
        if ($seesDrafts) {
            $drafts = $posts['draft'] ?? 0;
            $add(['key' => 'drafts', 'group' => 'content', 'label' => 'Drafts', 'value' => Format::number($drafts), 'delta' => 'posts', 'up' => $drafts > 0 ? 'warn' : null, 'goto' => 'content:posts']);
        }
        $add($this->pagesCard($pages));
        $add($this->commentsCard($comments, $moderates));
        if ($moderates) {
            $add(['key' => 'comments_pending', 'group' => 'content', 'label' => 'Pending comments', 'value' => Format::number($comments['moderated']), 'delta' => 'awaiting review', 'up' => $comments['moderated'] > 0 ? 'warn' : null, 'goto' => 'comments:hold']);
        }
        $add($this->mediaCard($media));
        if ($this->capabilities->can($userId, 'list_users')) {
            $add($this->usersCard());
        }
        return $catalog;
    }

    /**
     * Which cards show: the user's saved picks over the site default over
     * the built-in layout, unknown keys dropped and slots refilled.
     *
     * @param list<array> $catalog
     * @param list<array> $stats
     * @return array{keys: list<string>, defaults: list<string>, custom: bool}
     */
    public function metricLayout(int $userId, array $catalog, array $stats): array
    {
        $allowed = [];
        foreach ($catalog as $row) {
            if (!empty($row['key'])) {
                $allowed[(string) $row['key']] = true;
            }
        }
        $builtin = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['key'] ?? ''), $stats)));
        $site = self::overlayMetricKeys(self::cleanMetricKeys(Serialized::decode((string) ($this->db->option('minn_admin_overview_metric_defaults') ?? ''))), $builtin, $allowed);
        $raw = $this->users->meta($userId, 'minn_admin_overview_metrics');
        $saved = $raw === null ? null : Serialized::decode($raw);
        $custom = is_array($saved);
        return [
            'keys' => $custom ? self::overlayMetricKeys(self::cleanMetricKeys($saved), $site, $allowed) : $site,
            'defaults' => $site,
            'custom' => $custom,
        ];
    }

    /** At most six unique sanitize_key metric ids; anything else drops. */
    public static function cleanMetricKeys(mixed $keys): array
    {
        if (!is_array($keys)) {
            return [];
        }
        $out = [];
        foreach ($keys as $key) {
            if (!is_string($key) && !is_numeric($key)) {
                continue;
            }
            $key = (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
            if ($key === '' || strlen($key) > 32 || in_array($key, $out, true)) {
                continue;
            }
            $out[] = $key;
            if (count($out) >= 6) {
                break;
            }
        }
        return $out;
    }

    /**
     * Overlay saved keys onto a fallback of the same length, dropping keys
     * outside $allowed and de-duplicating left to right.
     *
     * @param list<string> $saved
     * @param list<string> $fallback
     * @param array<string, true> $allowed
     * @return list<string>
     */
    private static function overlayMetricKeys(array $saved, array $fallback, array $allowed): array
    {
        $keys = array_values($fallback);
        foreach ($saved as $i => $key) {
            if ($i >= count($keys)) {
                break;
            }
            if (isset($allowed[$key])) {
                $keys[$i] = $key;
            }
        }
        $seen = [];
        foreach ($keys as $i => $key) {
            if ($key !== '' && empty($seen[$key]) && isset($allowed[$key])) {
                $seen[$key] = true;
                continue;
            }
            $next = null;
            foreach ($fallback as $fill) {
                if ($fill !== '' && empty($seen[$fill]) && isset($allowed[$fill])) {
                    $next = $fill;
                    break;
                }
            }
            if ($next === null && isset($fallback[$i])) {
                $next = $fallback[$i];
            }
            $keys[$i] = (string) $next;
            if ($next !== null && $next !== '') {
                $seen[$next] = true;
            }
        }
        return $keys;
    }

    /**
     * The events behind one chart bar, (from, to] GMT. Post rows decode the
     * RAW stored title; comment rows decode the texturized one (the
     * reference's asymmetry, kept).
     */
    public function activity(int $userId, string $from, string $to): array
    {
        $now = time();
        $items = [];
        $posts = $this->db->rows(
            "SELECT ID, post_title, post_type, post_author, post_date_gmt FROM {$this->db->table('posts')}
             WHERE post_status = 'publish' AND post_type IN ('post','page')
             AND post_date_gmt > ? AND post_date_gmt <= ?
             ORDER BY post_date_gmt DESC LIMIT 100",
            [$from, $to],
        );
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
        $approvedOnly = !$this->capabilities->can($userId, 'moderate_comments');
        $comments = $this->db->rows(
            "SELECT comment_ID, comment_author, comment_post_ID, comment_date_gmt, comment_approved
             FROM {$this->db->table('comments')}
             WHERE comment_date_gmt > ? AND comment_date_gmt <= ?" . ($approvedOnly ? " AND comment_approved = '1'" : '') . "
             AND comment_type IN ( '', 'comment' )
             ORDER BY comment_date_gmt DESC LIMIT 300",
            [$from, $to],
        );
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
        usort($items, static fn (array $a, array $b) => $b['time'] - $a['time']);
        $items = array_slice($items, 0, 100);
        foreach ($items as &$item) {
            $item['ago'] = Format::humanTimeDiff($item['time'], $now) . ' ago';
        }
        unset($item);
        return ['items' => $items];
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

    public function postTitle(int $postId): string
    {
        return (string) ($this->db->value("SELECT post_title FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [$postId]) ?? '');
    }

    public function displayName(int $userId): string
    {
        return (string) ($this->users->find($userId)['display_name'] ?? '');
    }

    /** @return array<string, int> counts by status for one post type */
    private function statusCounts(string $type): array
    {
        $counts = [];
        $rows = $this->db->rows("SELECT post_status, COUNT(*) AS c FROM {$this->db->table('posts')} WHERE post_type = ? GROUP BY post_status", [$type]);
        foreach ($rows as $row) {
            $counts[$row['post_status']] = (int) $row['c'];
        }
        return $counts;
    }

    /** @return array{approved: int, moderated: int} */
    private function commentCounts(): array
    {
        $counts = ['approved' => 0, 'moderated' => 0];
        foreach ($this->db->rows("SELECT comment_approved, COUNT(*) AS c FROM {$this->db->table('comments')} GROUP BY comment_approved") as $row) {
            if ($row['comment_approved'] === '1') {
                $counts['approved'] = (int) $row['c'];
            } elseif ($row['comment_approved'] === '0') {
                $counts['moderated'] = (int) $row['c'];
            }
        }
        return $counts;
    }

    /** The uploads footprint: the plugin's cached transient when fresh, else a walk. */
    private function uploadsSize(): int
    {
        $cached = $this->site->option('_transient_minn_admin_uploads_size');
        $timeout = $this->site->option('_transient_timeout_minn_admin_uploads_size');
        if ($cached !== null && ($timeout === null || (int) $timeout >= time())) {
            return (int) $cached;
        }
        $size = 0;
        if (is_dir($this->uploadsDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->uploadsDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}
