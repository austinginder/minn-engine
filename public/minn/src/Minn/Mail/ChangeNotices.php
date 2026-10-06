<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * The notices the reference mails a user when their password or email
 * address changes, as templates: plugins filter them (password_change_email,
 * email_change_email) with the placeholders still in them and the site name
 * still a %s in the subject, and fill() finishes them after.
 */
final class ChangeNotices
{
    /** The password notice: subject and message, placeholders unfilled. @return array{subject: string, message: string} */
    public static function password(): array
    {
        return [
            'subject' => '[%s] Password Changed',
            'message' => "Hi ###USERNAME###,\n\nThis notice confirms that your password was changed on ###SITENAME###.\n\nIf you did not change your password, please contact the Site Administrator at\n###ADMIN_EMAIL###\n\nThis email has been sent to ###EMAIL###\n\nRegards,\nAll at ###SITENAME###\n###SITEURL###",
        ];
    }

    /** The email-address notice, sent to the address being left: subject and message, placeholders unfilled. @return array{subject: string, message: string} */
    public static function email(): array
    {
        return [
            'subject' => '[%s] Email Changed',
            'message' => "Hi ###USERNAME###,\n\nThis notice confirms that your email address on ###SITENAME### was changed to ###NEW_EMAIL###.\n\nIf you did not change your email, please contact the Site Administrator at\n###ADMIN_EMAIL###\n\nThis email has been sent to ###EMAIL###\n\nRegards,\nAll at ###SITENAME###\n###SITEURL###",
        ];
    }

    /** A template's placeholders filled with their values. @param array<string, string> $values placeholder name (USERNAME, ...) => value */
    public static function fill(string $text, array $values): string
    {
        $pairs = [];
        foreach ($values as $name => $value) {
            $pairs["###{$name}###"] = $value;
        }
        return strtr($text, $pairs);
    }
}
