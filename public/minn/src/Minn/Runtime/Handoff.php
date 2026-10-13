<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use LogicException;

/**
 * What the engine asks of the layer it runs under, where a plugin may have
 * the last word. WordPress lets a plugin define its own wp_get_current_user,
 * get_userdata, get_user_by or is_user_logged_in before the defaults load,
 * and its user records are that layer's objects; the WordPress layer binds
 * each answer here (the replaceable ones once the plugins have had their
 * chance), so a plugin's version answers the engine as it answers WordPress.
 */
final class Handoff
{
    /** @var array<string, Closure> */
    private static array $answers = [];

    /**
     * Binds answers by name: currentUser, signedIn, user, userBy, userRecord.
     *
     * @param array<string, Closure> $answers
     */
    public static function bind(array $answers): void
    {
        self::$answers = array_replace(self::$answers, $answers);
    }

    /** The user the request is (wp_get_current_user). */
    public static function currentUser(): mixed
    {
        return self::answer('currentUser');
    }

    /** Whether someone is signed in (is_user_logged_in). */
    public static function signedIn(): bool
    {
        return (bool) self::answer('signedIn');
    }

    /** A user by id, or false (get_userdata). */
    public static function user(mixed $id): mixed
    {
        return self::answer('user', $id);
    }

    /** A user by a field (id, ID, slug, email or login), or false (get_user_by). */
    public static function userBy(mixed $field, mixed $value): mixed
    {
        return self::answer('userBy', $field, $value);
    }

    /** The value when it is a user record, else the record of nobody (user id 0). */
    public static function userRecord(mixed $user): object
    {
        return self::answer('userRecord', $user);
    }

    private static function answer(string $name, mixed ...$args): mixed
    {
        $answer = self::$answers[$name] ?? throw new LogicException("Nothing answers {$name} yet: the WordPress layer binds it as it loads.");
        return $answer(...$args);
    }
}
