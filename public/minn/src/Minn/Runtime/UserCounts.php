<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\Support\Serialized;

/**
 * The user counts WordPress's user functions give: how many users hold each
 * role (count_users), and how many posts several users wrote
 * (count_many_users_posts), as the reference was measured counting them
 * (probes user-remaining, taxonomy-blocks-user-doubts).
 */
final class UserCounts
{
    /**
     * How many users there are and how many hold each role (count_users).
     * Only a user with a capabilities row counts; "none" is a user whose
     * row names nothing. The time strategy lists the registered roles that
     * have users, in their registered order, then none; any other strategy
     * lists none first, then every role a row names (registered or not), as
     * met user by user. pre_count_users may answer first.
     */
    public static function users(mixed $strategy = 'time', mixed $siteId = null): mixed
    {
        $answer = Runtime::hooks()->filter('pre_count_users', [null, $strategy, $siteId]);
        if ($answer !== null) {
            return $answer;
        }
        // Read through the site's database object, so its query filter hears the count as it hears the reference's.
        $wpdb = $GLOBALS['wpdb'];
        $roles = array_keys((array) Handoff::roles()->get_names());
        $rows = (array) $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY user_id", Runtime::current()->db->prefix() . 'capabilities'));
        $counts = [];
        $none = 0;
        foreach ($rows as $value) {
            $caps = Serialized::decode((string) $value);
            if (!is_array($caps)) {
                continue;
            }
            $none += $caps === [] ? 1 : 0;
            foreach ($strategy === 'time' ? array_intersect(array_keys($caps), $roles) : array_keys($caps) as $role) {
                $counts[$role] = ($counts[$role] ?? 0) + 1;
            }
        }
        $avail = $strategy === 'time'
            ? array_filter(array_merge(array_fill_keys($roles, 0), $counts)) + ['none' => $none]
            : ['none' => $none] + $counts;
        return ['total_users' => count($rows), 'avail_roles' => $avail];
    }

    /**
     * Post counts for several users (count_many_users_posts), as stored, 0
     * for none: published posts, and private ones too unless public only,
     * the viewer's own or every author's for a viewer who may read the
     * type's private posts. pre_count_many_users_posts may answer first.
     *
     * @return array<int|string, int|string>
     */
    public static function manyUsersPosts(mixed $users, mixed $postType = 'post', mixed $publicOnly = false): mixed
    {
        if (empty($users) || !is_array($users)) {
            return [];
        }
        $pre = Runtime::hooks()->filter('pre_count_many_users_posts', [null, $users, $postType, $publicOnly]);
        if ($pre !== null) {
            return $pre;
        }
        $ids = array_map(static fn (mixed $id): int => abs((int) $id), $users);
        $types = array_map('strval', (array) $postType);
        $lookup = new PostLookup(Runtime::current()->db);
        $counts = $lookup->countsByAuthors($ids, $types, ['publish']);
        if (!$publicOnly) {
            $viewer = CurrentUser::id();
            foreach ($types as $type) {
                $cap = (string) (Registered::postType($type)->cap->read_private_posts ?? 'read_private_posts');
                $authors = Can::current($cap) ? $ids : array_values(array_intersect($ids, [$viewer]));
                foreach ($lookup->countsByAuthors($authors, [$type], ['private']) as $id => $count) {
                    $counts[$id] = (string) ((int) ($counts[$id] ?? 0) + (int) $count);
                }
            }
        }
        foreach ($users as $id) {
            $counts[$id] ??= 0;
        }
        return $counts;
    }
}
