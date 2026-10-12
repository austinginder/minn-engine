<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Auth\PortableHash;
use Minn\Html;
use Minn\I18n\Gettext;
use Minn\Runtime\Runtime;

/**
 * A password-protected post on the front end: its body is the password
 * form, its excerpt a fixed sentence, its title prefixed, exactly as the
 * reference shows them to a reader who has not entered the password.
 */
final class PasswordGate
{
    public const EXCERPT = 'There is no excerpt because this is a protected post.';

    /** True while the post has a password the reader's cookie does not match. */
    public static function is(PostRecord $post): bool
    {
        $password = (string) ($post->password);
        if ($password === '') {
            return false;
        }
        return !self::cookieUnlocks($password, Reader::current()->postPassword);
    }

    /**
     * Whether a wp-postpass cookie opens a post with this password. Only a
     * hash in the setting the reference writes ("$P$B") is tried, as the
     * reference tries it: a cookie naming its own work factor could
     * otherwise make one request hash up to 2^30 times.
     */
    public static function cookieUnlocks(string $password, string $cookie): bool
    {
        return str_starts_with($cookie, '$P$B') && PortableHash::verify($password, $cookie);
    }

    /** "Protected: " for a password, "Private: " for a private post, as the reference prefixes titles. */
    public static function title(PostRecord $post): string
    {
        $prefix = (string) ($post->password) !== '' ? 'Protected: ' : (($post->status) === 'private' ? 'Private: ' : '');
        return $prefix . $post->title;
    }

    /**
     * The form, with the reference's stray closing p after the hidden field;
     * with the message a wrong password earns ($invalid, see invalid()) above
     * the field and named by it.
     */
    public static function form(PostRecord $post, string $siteUrl, string $permalink, string $invalid = ''): string
    {
        $id = $post->id;
        $error = $invalid === '' ? '' : '<div class="post-password-form-invalid-password" role="alert">' . "\n" . '<p id="error-pwbox-' . $id . '">' . $invalid . '</p>' . "\n" . '</div>' . "\n";
        return '<form action="' . Html::attr($siteUrl . '/wp-login.php?action=postpass') . '" class="post-password-form' . ($invalid === '' ? '' : ' password-form-error') . '" method="post">'
            . '<input type="hidden" name="redirect_to" value="' . Html::attr($permalink) . '" /></p>' . "\n"
            . $error
            . '<p>This content is password-protected. To view it, please enter the password below.</p>' . "\n"
            . '<p><label for="pwbox-' . $id . '">Password: <input name="post_password" id="pwbox-' . $id . '" type="password" spellcheck="false" required size="20"' . ($invalid === '' ? '' : ' aria-describedby="error-pwbox-' . $id . '"') . ' /></label>'
            . ' <span class="wp-block-button"><input type="submit" name="Submit" class="wp-block-button__link wp-element-button" value="Enter" /></span></p>' . "\n"
            . '</form>';
    }

    /** The form as the current reader sees it: with the wrong-password message when their own form sent them back. */
    public static function formForReader(PostRecord $post, string $siteUrl, string $permalink): string
    {
        $request = Runtime::current()->request;
        $referer = (string) ($request?->query['_wp_http_referer'] ?? $request?->form['_wp_http_referer'] ?? $request?->header('referer') ?? '');
        return self::form($post, $siteUrl, $permalink, self::invalid($permalink, $referer, Reader::current()->postPassword, static fn (): \WP_Post => new \WP_Post((object) $post->row())));
    }

    /**
     * The message over the form for a reader the post's own page sent back
     * with a password cookie set (the referer is exactly its address), as
     * plugins word it (the_password_form_incorrect_password); '' for anyone
     * else. The cookie is not checked: the form only shows while it fails.
     * $post gives the post the filter is handed, asked for only then.
     *
     * @param \Closure(): mixed $post
     */
    public static function invalid(string $permalink, string $referer, string $cookie, \Closure $post): string
    {
        if ($cookie === '' || $referer === '' || $referer !== $permalink) {
            return '';
        }
        return (string) \apply_filters('the_password_form_incorrect_password', Gettext::text('Invalid password.'), $post());
    }
}
