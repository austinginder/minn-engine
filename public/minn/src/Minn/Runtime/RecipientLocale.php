<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Objects\User;

/**
 * An email written in its reader's language, as the reference writes each:
 * the send run inside switch_to_user_locale for the account it goes to (the
 * site's locale when the address belongs to none) and the locale restored
 * after. The facade binds how a switch is made (WP_Locale_Switcher).
 */
final class RecipientLocale
{
    /** @var (Closure(int, Closure): mixed)|null runs a closure in a user's locale, the site's for 0 */
    private static ?Closure $switcher = null;

    /** How a switch is made (the facade's switch_to_user_locale and restore_previous_locale). */
    public static function bind(Closure $switcher): void
    {
        self::$switcher = $switcher;
    }

    /** What a send answers, run in a user's language (the site's for 0). */
    public static function forUser(int $userId, Closure $send): mixed
    {
        return self::$switcher === null ? $send() : (self::$switcher)($userId, $send);
    }

    /** What a send answers, run in the language of the account an address belongs to. */
    public static function forAddress(string $email, Closure $send): mixed
    {
        $user = Handoff::userBy('email', $email);
        return self::forUser($user instanceof User ? (int) $user->ID : 0, $send);
    }

    /** What a send to the site's address (admin_email) answers, run in its account's language. */
    public static function forSite(Closure $send): mixed
    {
        return self::forAddress((string) Runtime::options()->filtered('admin_email'), $send);
    }
}
