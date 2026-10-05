<?php

declare(strict_types=1);

namespace Minn\Mail;

use Minn\Content\Site;
use Minn\Support\Serialized;

/**
 * How mail leaves the site, from the minn_mail option (JSON or a serialized
 * array): the PHP mail
 * function by default, SMTP when configured, or a log file for development.
 * The sender defaults to the site name at a no-reply address on the home host.
 */
final readonly class MailSettings
{
    public const OPTION = 'minn_mail';

    /**
     * @param 'mail'|'smtp'|'log' $transport
     * @param 'none'|'ssl'|'tls' $encryption
     */
    public function __construct(
        public string $transport = 'mail',
        public string $host = '',
        public int $port = 587,
        public string $encryption = 'tls',
        public string $username = '',
        public string $password = '',
        public string $fromEmail = '',
        public string $fromName = '',
    ) {
    }

    /**
     * What the minn_mail option holds, without defaults: empty when the site
     * has not configured mail.
     *
     * @return array<string, mixed>
     */
    public static function stored(Site $site): array
    {
        // Stored as JSON by the engine's settings, or serialized by `wp option update --format=json`.
        $raw = (string) ($site->option(self::OPTION) ?? '');
        $stored = json_decode($raw, true);
        if (!is_array($stored)) {
            $stored = Serialized::decode($raw);
        }
        return is_array($stored) ? $stored : [];
    }

    /** The site's mail settings from its minn_mail option, or the defaults. */
    public static function fromSite(Site $site): self
    {
        $stored = self::stored($site);
        $host = (string) parse_url((string) ($site->option('home') ?? ''), PHP_URL_HOST) ?: 'localhost';
        $transport = (string) ($stored['transport'] ?? 'mail');
        $encryption = (string) ($stored['encryption'] ?? 'tls');
        return new self(
            in_array($transport, ['mail', 'smtp', 'log'], true) ? $transport : 'mail',
            (string) ($stored['host'] ?? ''),
            (int) ($stored['port'] ?? 587),
            in_array($encryption, ['none', 'ssl', 'tls'], true) ? $encryption : 'tls',
            (string) ($stored['username'] ?? ''),
            (string) ($stored['password'] ?? ''),
            (string) ($stored['from_email'] ?? '') ?: 'no-reply@' . preg_replace('/^www\./', '', $host),
            (string) ($stored['from_name'] ?? '') ?: (string) ($site->option('blogname') ?? ''),
        );
    }

    /** The option's JSON, secrets included, for the settings surface. */
    public function toArray(): array
    {
        return [
            'transport' => $this->transport,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->password,
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
        ];
    }
}
