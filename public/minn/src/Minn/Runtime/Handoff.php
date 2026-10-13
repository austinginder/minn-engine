<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use LogicException;

/**
 * What the engine asks of the layer it runs under, where a plugin may have
 * the last word. WordPress lets a plugin define its own version of the
 * replaceable functions (the current user, users by id or field, hashes,
 * nonces, passwords, mail, avatars, redirects, signing in) before the
 * defaults load, and its user records are that layer's objects; the
 * WordPress layer binds each answer here (the replaceable ones once the
 * plugins have had their chance), so a plugin's version answers the engine
 * as it answers WordPress. Arguments pass as given, so each answer's own
 * defaults hold.
 */
final class Handoff
{
    /** @var array<string, Closure> */
    private static array $answers = [];

    /**
     * Binds answers by name: currentUser, signedIn, user, userBy, userRecord,
     * becomeUser, cacheUsers, authenticate, setPassword, password, hash,
     * createNonce, verifyNonce, mail, avatar, redirect, safeRedirect.
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

    /** Makes a user the request's (wp_set_current_user): the user's record. */
    public static function becomeUser(mixed ...$args): mixed
    {
        return self::answer('becomeUser', ...$args);
    }

    /** Reads users and their meta into the cache together (cache_users). */
    public static function cacheUsers(mixed $ids): void
    {
        self::answer('cacheUsers', $ids);
    }

    /** Signs in by name and password (wp_authenticate): the user, or an error. */
    public static function authenticate(mixed $username, mixed $password): mixed
    {
        return self::answer('authenticate', $username, $password);
    }

    /** Sets a user's password (wp_set_password). */
    public static function setPassword(mixed $password, mixed $userId): mixed
    {
        return self::answer('setPassword', $password, $userId);
    }

    /** A random password (wp_generate_password). */
    public static function password(mixed ...$args): mixed
    {
        return self::answer('password', ...$args);
    }

    /** A hash of the data keyed with the site's salt (wp_hash). */
    public static function hash(mixed ...$args): mixed
    {
        return self::answer('hash', ...$args);
    }

    /** A nonce for an action (wp_create_nonce). */
    public static function createNonce(mixed ...$args): mixed
    {
        return self::answer('createNonce', ...$args);
    }

    /** Whether a nonce holds for an action: 1 or 2 by its age, or false (wp_verify_nonce). */
    public static function verifyNonce(mixed ...$args): mixed
    {
        return self::answer('verifyNonce', ...$args);
    }

    /** Sends an email (wp_mail): whether it was accepted for delivery. */
    public static function mail(mixed ...$args): mixed
    {
        return self::answer('mail', ...$args);
    }

    /** A user's or address's avatar image, or false (get_avatar). */
    public static function avatar(mixed ...$args): mixed
    {
        return self::answer('avatar', ...$args);
    }

    /** Sends the browser elsewhere (wp_redirect): whether it was sent. */
    public static function redirect(mixed ...$args): mixed
    {
        return self::answer('redirect', ...$args);
    }

    /** Sends the browser elsewhere on the site's own hosts, else to the admin (wp_safe_redirect). */
    public static function safeRedirect(mixed ...$args): mixed
    {
        return self::answer('safeRedirect', ...$args);
    }

    private static function answer(string $name, mixed ...$args): mixed
    {
        $answer = self::$answers[$name] ?? throw new LogicException("Nothing answers {$name} yet: the WordPress layer binds it as it loads.");
        return $answer(...$args);
    }
}
