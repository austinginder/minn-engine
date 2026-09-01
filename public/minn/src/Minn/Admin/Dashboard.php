<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Content\Site;
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
        private ActivityChart $chart,
        private ActivityFeed $feed,
        private string $uploadsDir,
    ) {
    }

    /** The stat cards, the metric catalog and layout, the chart, and the feed. */
    public function overview(int $userId, int $days): array
    {
        $now = time();
        $offset = $this->site->gmtOffset();
        $posts = $this->statusCounts('post');
        $pages = $this->statusCounts('page');
        $media = $this->statusCounts('attachment');
        $comments = $this->commentCounts();

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
        $catalog = $this->metricCatalog($userId, $stats, $posts, $pages, $comments, $media, $seesDrafts, $moderates);
        $layout = $this->metricLayout($userId, $catalog, $stats);
        $hour = (int) gmdate('G', $now + $offset);
        return [
            'stats' => $stats,
            'metrics' => $catalog,
            'metricKeys' => $layout['keys'],
            'metricDefaults' => $layout['defaults'],
            'metricCustom' => $layout['custom'],
            'canSetMetricDefaults' => $this->capabilities->can($userId, 'manage_options'),
            'chart' => $this->chart->bars($days, $now),
            'traffic' => null,
            'activity' => $this->feed->recent($userId, $now, $offset),
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

    /** The events behind one chart bar, (from, to] GMT. */
    public function activity(int $userId, string $from, string $to): array
    {
        return ['items' => $this->feed->between($userId, $from, $to, time())];
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
