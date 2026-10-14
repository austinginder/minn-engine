<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use LogicException;
use Minn\Objects\Comment;
use Minn\Objects\CommentsQuery;
use Minn\Objects\Errors;
use Minn\Objects\Post;
use Minn\Objects\PostType;
use Minn\Objects\Query;
use Minn\Objects\RestRequest;
use Minn\Objects\RestResponse;
use Minn\Objects\Taxonomy;
use Minn\Objects\Term;
use Minn\Objects\Theme;
use Minn\Objects\TermsQuery;
use Minn\Objects\User;
use Minn\Objects\UsersQuery;

/**
 * What the engine asks of the layer it runs under, where a plugin may have
 * the last word. WordPress lets a plugin define its own version of the
 * replaceable functions (the current user, users by id or field, hashes,
 * nonces, passwords, mail, avatars, redirects, signing in) before the
 * defaults load; its user records are that layer's objects, and it ends a
 * request with a message its own way (handlers plugins choose); the
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
     * Binds answers by name: currentUser, signedIn, user, userBy,
     * becomeUser, cacheUsers, authenticate, setPassword, password, hash,
     * createNonce, verifyNonce, mail, avatar, redirect, safeRedirect, stop, escSql, cacheGet, cacheSet, cacheDelete, and the record
     * factories newError, newPost, postInstance, newTerm, newComment,
     * commentInstance, newUser, and the query factories newQuery,
     * newCommentQuery, newUserQuery, newTermQuery, newMetaQuery, newDateQuery,
     * and the REST ones newRestRequest, restRequestFromUrl, newRestResponse,
     * restController, newTheme, and the empty registered-type objects
     * blankPostType and blankTaxonomy.
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

    /** Ends the request with a message (wp_die), as the handler plugins choose draws it. */
    public static function stop(mixed ...$args): void
    {
        self::answer('stop', ...$args);
    }

    /** A value from the object cache (wp_cache_get), $found told whether it was there. */
    public static function cacheGet(mixed $key, mixed $group = '', mixed $force = false, mixed &$found = null): mixed
    {
        $answer = self::$answers['cacheGet'] ?? throw new LogicException('Nothing answers cacheGet yet: the WordPress layer binds it as it loads.');
        return $answer($key, $group, $force, $found);
    }

    /** A value stored in the object cache (wp_cache_set). */
    public static function cacheSet(mixed ...$args): mixed
    {
        return self::answer('cacheSet', ...$args);
    }

    /** A value removed from the object cache (wp_cache_delete). */
    public static function cacheDelete(mixed ...$args): mixed
    {
        return self::answer('cacheDelete', ...$args);
    }

    /** A value, or each in a list, escaped for SQL by the database object plugins query with (esc_sql). */
    public static function escSql(mixed $data): mixed
    {
        return self::answer('escSql', $data);
    }

    /** A list of errors, the first given (new WP_Error). */
    public static function newError(mixed ...$args): Errors
    {
        return self::answer('newError', ...$args);
    }

    /** A post object made from a row or another post (new WP_Post). */
    public static function newPost(mixed $post): Post
    {
        return self::answer('newPost', $post);
    }

    /** The post object for an id, or false (WP_Post::get_instance). */
    public static function postInstance(mixed $id): mixed
    {
        return self::answer('postInstance', $id);
    }

    /** A term object made from a row (new WP_Term). */
    public static function newTerm(mixed $term): Term
    {
        return self::answer('newTerm', $term);
    }

    /** A comment object made from a row (new WP_Comment). */
    public static function newComment(mixed $comment): Comment
    {
        return self::answer('newComment', $comment);
    }

    /** The comment object for an id, or false (WP_Comment::get_instance). */
    public static function commentInstance(mixed $id): mixed
    {
        return self::answer('commentInstance', $id);
    }

    /** A user object by id (or login, with no id), nobody for 0 (new WP_User). */
    public static function newUser(mixed ...$args): User
    {
        return self::answer('newUser', ...$args);
    }

    /** A posts query, run at once when given its variables (new WP_Query). */
    public static function newQuery(mixed ...$args): Query
    {
        return self::answer('newQuery', ...$args);
    }

    /** A comments query, run at once when given its variables (new WP_Comment_Query). */
    public static function newCommentQuery(mixed ...$args): CommentsQuery
    {
        return self::answer('newCommentQuery', ...$args);
    }

    /** A users query, run at once when given its variables (new WP_User_Query). */
    public static function newUserQuery(mixed ...$args): UsersQuery
    {
        return self::answer('newUserQuery', ...$args);
    }

    /** A terms query, run at once when given its variables (new WP_Term_Query). */
    public static function newTermQuery(mixed ...$args): TermsQuery
    {
        return self::answer('newTermQuery', ...$args);
    }

    /** A meta query from its clauses (new WP_Meta_Query). */
    public static function newMetaQuery(mixed ...$args): object
    {
        return self::answer('newMetaQuery', ...$args);
    }

    /** A date query from its clauses and column (new WP_Date_Query). */
    public static function newDateQuery(mixed ...$args): object
    {
        return self::answer('newDateQuery', ...$args);
    }

    /** A REST request for a method and route (new WP_REST_Request). */
    public static function newRestRequest(mixed ...$args): RestRequest
    {
        return self::answer('newRestRequest', ...$args);
    }

    /** A REST request for an address on this site's API, or false (WP_REST_Request::from_url). */
    public static function restRequestFromUrl(mixed $url): mixed
    {
        return self::answer('restRequestFromUrl', $url);
    }

    /** A REST response with data, a status and headers (new WP_REST_Response). */
    public static function newRestResponse(mixed ...$args): RestResponse
    {
        return self::answer('newRestResponse', ...$args);
    }

    /** One of the REST controllers a filter is handed, by kind: menus, menu-items or menu-locations. */
    public static function restController(string $kind, mixed ...$args): object
    {
        return self::answer('restController', $kind, ...$args);
    }

    /** A post type's object with nothing set yet, for the registry to fill (WP_Post_Type, built without its constructor). */
    public static function blankPostType(): PostType
    {
        return self::answer('blankPostType');
    }

    /** A taxonomy's object with nothing set yet, for the registry to fill (WP_Taxonomy, built without its constructor). */
    public static function blankTaxonomy(): Taxonomy
    {
        return self::answer('blankTaxonomy');
    }

    /** A theme by its folder and root (new WP_Theme). */
    public static function newTheme(mixed ...$args): Theme
    {
        return self::answer('newTheme', ...$args);
    }

    private static function answer(string $name, mixed ...$args): mixed
    {
        $answer = self::$answers[$name] ?? throw new LogicException("Nothing answers {$name} yet: the WordPress layer binds it as it loads.");
        return $answer(...$args);
    }
}
