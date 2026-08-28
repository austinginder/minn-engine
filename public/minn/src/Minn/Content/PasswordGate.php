<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Support\Html;

/**
 * A password-protected post on the front end: its body is the password
 * form, its excerpt a fixed sentence, its title prefixed, exactly as the
 * reference shows them to a reader who has not entered the password.
 */
final class PasswordGate
{
    public const EXCERPT = 'There is no excerpt because this is a protected post.';

    public static function is(array $post): bool
    {
        return (string) ($post['post_password'] ?? '') !== '';
    }

    public static function title(array $post): string
    {
        return (self::is($post) ? 'Protected: ' : '') . (string) $post['post_title'];
    }

    /** The form, with the reference's stray closing p after the hidden field. */
    public static function form(array $post, string $siteUrl, string $permalink): string
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
