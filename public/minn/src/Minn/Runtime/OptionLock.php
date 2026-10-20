<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A lock the reference keeps as an option row (the revision upgrade's
 * "revision-upgrade-{id}", the comment type batch's
 * "update_comment_type.lock"; tests/tools/side-effects.php): inserted only
 * when no row holds the name, its value the time it was taken; one held
 * for less than an hour keeps others out, an older one is taken over. The
 * holder writes the time again through update_option, and deletes the row
 * when done.
 */
final class OptionLock
{
    /** How long a held lock keeps others out, in seconds. */
    private const HELD_SECONDS = 3600;

    /** Takes the lock, inserting its row with the autoload value the reference gives it; false while another holds it. */
    public static function take(string $name, string $autoload): bool
    {
        $now = time();
        $options = Runtime::options();
        if (!$options->claim($name, (string) $now, $autoload) && (int) $options->filtered($name) > $now - self::HELD_SECONDS) {
            return false;
        }
        OptionWrites::update($name, $now);
        return true;
    }

    /** Lets the lock go. */
    public static function release(string $name): void
    {
        OptionWrites::delete($name);
    }
}
