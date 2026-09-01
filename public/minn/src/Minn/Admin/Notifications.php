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
 * The bell feed: pending and recent comments, translation and core update
 * offers, the core auto-update notice, and new registrations. Plugin and
 * theme update rows need an extension inventory the engine does not have
 * (a recorded gap); on the reference database those sections are empty,
 * so parity holds by construction.
 */
final readonly class Notifications
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Users $users,
        private Capabilities $capabilities,
        private Dashboard $dashboard,
        private Updates $updates,
    ) {
    }

    public function items(int $userId): array
    {
        $now = time();
        $offset = $this->site->gmtOffset();
        $readAt = (int) ($this->users->meta($userId, 'minn_admin_notif_read_at') ?? 0);
        $items = [];
        $comments = $this->db->table('comments');

        if ($this->capabilities->can($userId, 'moderate_comments')) {
            $pending = $this->db->rows(
                "SELECT comment_ID, comment_post_ID, comment_author, comment_date_gmt FROM {$comments}
                 WHERE comment_approved = '0' ORDER BY comment_date_gmt DESC LIMIT 5",
            );
            foreach ($pending as $comment) {
                if ($this->dashboard->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                    $items[] = $this->commentItem($comment, 'New comment from %s awaiting moderation on “%s”');
                }
            }
        }
        $approved = $this->db->rows(
            "SELECT comment_ID, comment_post_ID, comment_author, comment_date_gmt FROM {$comments}
             WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 3",
        );
        foreach ($approved as $comment) {
            if ($this->dashboard->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                $items[] = $this->commentItem($comment, '%s commented on “%s”');
            }
        }

        if ($this->capabilities->can($userId, 'update_plugins')) {
            $checked = (int) ($this->updates->state()['checked'] ?? $now);
            $names = $this->updates->pluginNames();
            foreach ($this->updates->pluginOffers() as $file => $version) {
                $name = ($names[$file] ?? '') !== '' ? $names[$file] : $file;
                $items[] = [
                    'id' => 'plugin-' . $file . '-' . $version,
                    'kind' => 'updates',
                    'icon' => '⬆',
                    'title' => sprintf('%1$s %2$s is available to install', $name, $version),
                    'time' => $checked,
                    'update' => ['type' => 'plugin', 'plugin' => $file, 'version' => $version, 'name' => $name],
                ];
            }
        }
        if ($this->capabilities->can($userId, 'update_themes')) {
            $checked = (int) ($this->updates->state()['checked'] ?? $now);
            $headers = $this->updates->themeHeaders();
            foreach ($this->updates->themeOffers() as $stylesheet => $version) {
                $name = ($headers[$stylesheet]['Theme Name'] ?? '') !== '' ? $headers[$stylesheet]['Theme Name'] : $stylesheet;
                $items[] = [
                    'id' => 'theme-' . $stylesheet . '-' . $version,
                    'kind' => 'updates',
                    'icon' => '⬆',
                    'title' => sprintf('%1$s theme %2$s is available to install', $name, $version),
                    'time' => $checked,
                    'update' => ['type' => 'theme', 'stylesheet' => $stylesheet, 'version' => $version, 'name' => $name],
                ];
            }
        }
        if ($this->capabilities->can($userId, 'update_plugins') || $this->capabilities->can($userId, 'update_themes')) {
            $count = $this->translationCount();
            if ($count > 0) {
                $items[] = [
                    'id' => 'translations-' . $count,
                    'kind' => 'updates',
                    'icon' => '⬆',
                    'title' => sprintf($count === 1 ? '%d translation is available to update' : '%d translations are available to update', $count),
                    'time' => $now,
                    'update' => ['type' => 'translations', 'count' => $count],
                ];
            }
        }

        if ($this->capabilities->can($userId, 'update_core')) {
            $core = $this->site->option('_site_transient_update_core');
            if ($core !== null && Serialized::field($core, 'response') === 'upgrade') {
                $version = (string) Serialized::field($core, 'version');
                $items[] = [
                    'id' => 'core-' . $version,
                    'kind' => 'system',
                    'icon' => '🛡',
                    'title' => sprintf('WordPress %s is available', $version),
                    'time' => (int) Serialized::field($core, 'last_checked'),
                    'update' => ['type' => 'core', 'version' => $version, 'name' => 'WordPress'],
                ];
            }
            $auto = $this->site->option('auto_core_update_notified');
            if ($auto !== null && Serialized::field($auto, 'type') === 'success') {
                $version = (string) Serialized::field($auto, 'version');
                $stamp = (int) Serialized::field($auto, 'timestamp');
                if ($version !== '' && ($now - $stamp) < 14 * 86400) {
                    $items[] = [
                        'id' => 'core-auto-' . $version,
                        'kind' => 'system',
                        'icon' => '🛡',
                        'title' => sprintf('WordPress updated itself to %s', $version),
                        'time' => $stamp,
                    ];
                }
            }
        }

        if ($this->capabilities->can($userId, 'list_users')) {
            $since = gmdate('Y-m-d H:i:s', $now + $offset - 7 * 86400);
            foreach ($this->users->registeredAfter($since, 2) as $user) {
                $items[] = [
                    'id' => 'user-' . $user->id,
                    'kind' => 'system',
                    'icon' => '👤',
                    'title' => sprintf('New user registered: %s', $user->displayName),
                    'time' => (int) strtotime($user->registered . ' UTC'),
                ];
            }
        }

        usort($items, static fn (array $a, array $b) => $b['time'] - $a['time']);
        $readIds = Serialized::stringList($this->users->meta($userId, 'minn_admin_notif_read_ids'));
        // Site-local midnight, expressed back in GMT epoch.
        $today = strtotime(gmdate('Y-m-d', $now + $offset) . ' 00:00:00 UTC') - $offset;
        foreach ($items as &$item) {
            $item['unread'] = $item['time'] > $readAt && !in_array($item['id'], $readIds, true);
            $item['group'] = $item['time'] >= $today ? 'Today' : 'Earlier';
            $item['ago'] = Format::humanTimeDiff($item['time'], $now) . ' ago';
        }
        unset($item);
        return ['items' => $items];
    }

    /** An id marks one item read; an empty id marks everything read. */
    public function markRead(int $userId, string $id): void
    {
        if ($id !== '') {
            $ids = Serialized::stringList($this->users->meta($userId, 'minn_admin_notif_read_ids'));
            $ids[] = $id;
            $ids = array_slice(array_values(array_unique($ids)), -200);
            $this->users->setMeta($userId, 'minn_admin_notif_read_ids', Serialized::serializeStringList($ids));
            return;
        }
        $this->users->setMeta($userId, 'minn_admin_notif_read_at', (string) time());
        $this->users->deleteMeta($userId, 'minn_admin_notif_read_ids');
    }

    private function commentItem(array $comment, string $template): array
    {
        return [
            'id' => 'comment-' . $comment['comment_ID'],
            'kind' => 'comments',
            'icon' => '💬',
            'title' => sprintf(
                $template,
                $comment['comment_author'] !== '' ? $comment['comment_author'] : 'Anonymous',
                Texturize::html($this->dashboard->postTitle((int) $comment['comment_post_ID'])),
            ),
            'time' => (int) strtotime($comment['comment_date_gmt'] . ' UTC'),
        ];
    }

    /** Total translation offers across the three update transients. */
    private function translationCount(): int
    {
        $total = 0;
        foreach (['_site_transient_update_plugins', '_site_transient_update_themes', '_site_transient_update_core'] as $name) {
            $blob = $this->site->option($name);
            if ($blob !== null && preg_match('/s:12:"translations";a:(\d+):/', $blob, $m)) {
                $total += (int) $m[1];
            }
        }
        return $total;
    }
}
