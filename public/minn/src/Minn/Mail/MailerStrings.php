<?php

declare(strict_types=1);

namespace Minn\Mail;

use Minn\Json;

/**
 * The mailer's English messages, keyed as plugins look them up
 * ("authenticate", "provide_address", ...): the base set, and the set
 * WordPress's own mailer subclass loads, which words four of them
 * differently. Captured from the reference into data/mailer-strings.json.
 */
final class MailerStrings
{
    /** @var array<string, array<string, string>> */
    private static array $sets = [];

    /**
     * The base mailer's messages.
     *
     * @return array<string, string>
     */
    public static function base(): array
    {
        return self::set('base');
    }

    /**
     * The messages WordPress's mailer subclass loads.
     *
     * @return array<string, string>
     */
    public static function wordpress(): array
    {
        return self::set('wordpress');
    }

    /**
     * One message by key from a loaded set (PHPMailer::lang): the key itself
     * when the set lacks it; a failed SMTP connect points to the
     * troubleshooting page.
     *
     * @param array<string, string> $language
     */
    public static function word(array $language, string $key): string
    {
        $text = (string) ($language[$key] ?? $key);
        return $key === 'smtp_connect_failed' ? $text . ' https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting' : $text;
    }

    /** @return array<string, string> */
    private static function set(string $name): array
    {
        if (self::$sets === []) {
            self::$sets = (array) Json::file(MINN_ENGINE_DIR . '/data/mailer-strings.json');
        }
        return (array) (self::$sets[$name] ?? []);
    }
}
