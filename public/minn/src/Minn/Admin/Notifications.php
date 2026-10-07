<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Ops\Releases;
use Minn\Ops\Updates;
use Minn\Content\CommentRecord;
use Minn\Auth\Capabilities;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Db;
use Minn\Support\Serialized;

/**
 * The bell feed: pending and recent comments, translation offers and a
 * newer Minn, and new registrations. Plugin and theme update rows need an
 * extension inventory the engine does not have
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
        private ActivityFeed $feed,
        private Updates $updates,
        private Releases $releases,
    ) {
    }

    /** The bell feed for a user, newest first, grouped and marked read or unread. */
    public function items(int $userId): array
    {
        $now = time();
        $offset = $this->site->gmtOffset();
        $items = [
            ...$this->commentItems($userId),
            ...$this->updateItems($userId, $now),
            ...$this->coreItems($userId, $now),
            ...$this->registrationItems($userId, $now, $offset),
        ];
        usort($items, static fn (array $a, array $b) => $b['time'] - $a['time']);
        $readAt = (int) ($this->users->meta($userId, 'minn_admin_notif_read_at') ?? 0);
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

    /**
     * Up to five pending comments for a moderator, then the three latest
     * approved ones, each only when its post is visible to the caller.
     *
     * @return list<array>
     */
    private function commentItems(int $userId): array
    {
        $comments = $this->db->table('comments');
        $columns = 'comment_ID, comment_post_ID, comment_author, comment_date_gmt';
        $items = [];
        if ($this->capabilities->can($userId, 'moderate_comments')) {
            $pending = $this->db->rows("SELECT {$columns} FROM {$comments} WHERE comment_approved = '0' ORDER BY comment_date_gmt DESC LIMIT 5");
            foreach ($pending as $comment) {
                if ($this->feed->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                    $items[] = $this->commentItem($comment, 'New comment from %s awaiting moderation on “%s”');
                }
            }
        }
        $approved = $this->db->rows("SELECT {$columns} FROM {$comments} WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 3");
        foreach ($approved as $comment) {
            if ($this->feed->commentVisible($userId, (int) $comment['comment_post_ID'])) {
                $items[] = $this->commentItem($comment, '%s commented on “%s”');
            }
        }
        return $items;
    }

    /**
     * Plugin and theme offers for a caller who may apply them, stamped with
     * the last check, then the translation total.
     *
     * @return list<array>
     */
    private function updateItems(int $userId, int $now): array
    {
        $plugins = $this->capabilities->can($userId, 'update_plugins');
        $themes = $this->capabilities->can($userId, 'update_themes');
        if (!$plugins && !$themes) {
            return [];
        }
        $checked = (int) ($this->updates->state()['checked'] ?? $now);
        $items = [];
        if ($plugins) {
            $names = $this->updates->pluginNames();
            foreach ($this->updates->pluginOffers() as $file => $version) {
                $name = ($names[$file] ?? '') !== '' ? $names[$file] : $file;
                $items[] = self::updateItem("plugin-{$file}-{$version}", sprintf('%1$s %2$s is available to install', $name, $version), $checked, ['type' => 'plugin', 'plugin' => $file, 'version' => $version, 'name' => $name]);
            }
        }
        if ($themes) {
            $headers = $this->updates->themeHeaders();
            foreach ($this->updates->themeOffers() as $stylesheet => $version) {
                $name = ($headers[$stylesheet]['Theme Name'] ?? '') !== '' ? $headers[$stylesheet]['Theme Name'] : $stylesheet;
                $items[] = self::updateItem("theme-{$stylesheet}-{$version}", sprintf('%1$s theme %2$s is available to install', $name, $version), $checked, ['type' => 'theme', 'stylesheet' => $stylesheet, 'version' => $version, 'name' => $name]);
            }
        }
        $count = $this->translationCount();
        if ($count > 0) {
            $title = sprintf($count === 1 ? '%d translation is available to update' : '%d translations are available to update', $count);
            $items[] = self::updateItem("translations-{$count}", $title, $now, ['type' => 'translations', 'count' => $count]);
        }
        return $items;
    }

    private static function updateItem(string $id, string $title, int $time, array $update): array
    {
        return ['id' => $id, 'kind' => 'updates', 'icon' => '⬆', 'title' => $title, 'time' => $time, 'update' => $update];
    }

    /**
     * A newer Minn on offer (Ops\Releases). WordPress's own core offer and
     * its auto-update notice are not shown: on Minn they could only speak
     * of a parked copy, which is not what serves the site.
     *
     * @return list<array>
     */
    private function coreItems(int $userId, int $now): array
    {
        $offer = $this->capabilities->can($userId, 'update_core') ? $this->releases->offer() : null;
        if ($offer === null) {
            return [];
        }
        return [[
            'id' => 'minn-' . $offer->version,
            'kind' => 'system',
            'icon' => '🛡',
            'title' => sprintf('Minn %s is available', $offer->version),
            'time' => (int) (strtotime($offer->published) ?: $now),
            'update' => ['type' => 'core', 'version' => $offer->version, 'name' => 'Minn'],
        ]];
    }

    /**
     * The two newest registrations of the last week, for a caller who lists users.
     *
     * @return list<array>
     */
    private function registrationItems(int $userId, int $now, int $offset): array
    {
        if (!$this->capabilities->can($userId, 'list_users')) {
            return [];
        }
        $items = [];
        $since = gmdate('Y-m-d H:i:s', $now + $offset - 7 * 86400);
        foreach ($this->users->registeredAfter($since, 2) as $user) {
            $items[] = ['id' => 'user-' . $user->id, 'kind' => 'system', 'icon' => '👤', 'title' => sprintf('New user registered: %s', $user->displayName), 'time' => (int) strtotime($user->registered . ' UTC')];
        }
        return $items;
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

    private function commentItem(array|CommentRecord $comment, string $template): array
    {
        return [
            'id' => 'comment-' . $comment['comment_ID'],
            'kind' => 'comments',
            'icon' => '💬',
            'title' => sprintf(
                $template,
                $comment['comment_author'] !== '' ? $comment['comment_author'] : 'Anonymous',
                Texturize::html($this->feed->postTitle((int) $comment['comment_post_ID'])),
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
