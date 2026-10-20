<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A post's edit lock (_edit_lock, "time:user"): who holds it while it is
 * fresh, for the editor and the autosave that must not write under it.
 */
final class PostLocks
{
    /**
     * The user who holds a post's lock other than the one asking
     * (wp_check_post_lock): the lock's user, or the post's last editor when
     * it names none, while it is newer than the window
     * (wp_check_post_lock_window, 150 seconds); 0 otherwise.
     */
    public static function holder(int $postId, int $askingId): int
    {
        $lock = (string) MetaData::get('post', $postId, '_edit_lock', true);
        if ($lock === '') {
            return 0;
        }
        $parts = explode(':', $lock);
        $time = (int) $parts[0];
        $user = isset($parts[1]) ? (int) $parts[1] : (int) MetaData::get('post', $postId, '_edit_last', true);
        if ($user === 0 || $time === 0) {
            return 0;
        }
        $window = (int) Runtime::hooks()->filter('wp_check_post_lock_window', [150]);
        return $time > time() - $window && $user !== $askingId ? $user : 0;
    }
}
