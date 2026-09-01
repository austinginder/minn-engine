<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Auth\PortableHash;
use Minn\Support\Html;

/**
 * A password-protected post on the front end: its body is the password
 * form, its excerpt a fixed sentence, its title prefixed, exactly as the
 * reference shows them to a reader who has not entered the password.
 */
final class PasswordGate
{
    public const EXCERPT = 'There is no excerpt because this is a protected post.';

    /** True while the post has a password the reader's cookie does not match. */
    public static function is(array|PostRecord $post): bool
    {
        $password = (string) ($post['post_password'] ?? '');
        if ($password === '') {
            return false;
        }
        $cookie = Reader::current()->postPassword;
        return $cookie === '' || !PortableHash::verify($password, $cookie);
    }

    /** "Protected: " for a password, "Private: " for a private post, as the reference prefixes titles. */
    public static function title(array|PostRecord $post): string
    {
        $prefix = (string) ($post['post_password'] ?? '') !== '' ? 'Protected: ' : (($post['post_status'] ?? '') === 'private' ? 'Private: ' : '');
        return $prefix . (string) $post['post_title'];
    }

    /** The form, with the reference's stray closing p after the hidden field. */
    public static function form(array|PostRecord $post, string $siteUrl, string $permalink): string
    {
        $id = (int) $post['ID'];
        return '<form action="' . Html::attr($siteUrl . '/wp-login.php?action=postpass') . '" class="post-password-form" method="post">'
            . '<input type="hidden" name="redirect_to" value="' . Html::attr($permalink) . '" /></p>' . "\n"
            . '<p>This content is password-protected. To view it, please enter the password below.</p>' . "\n"
            . '<p><label for="pwbox-' . $id . '">Password: <input name="post_password" id="pwbox-' . $id . '" type="password" spellcheck="false" required size="20" /></label>'
            . ' <span class="wp-block-button"><input type="submit" name="Submit" class="wp-block-button__link wp-element-button" value="Enter" /></span></p>' . "\n"
            . '</form>';
    }
}
