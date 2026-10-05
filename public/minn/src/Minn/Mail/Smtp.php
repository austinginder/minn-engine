<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * Delivery over SMTP for the engine's own mail when WordPress's mail
 * function is not loaded: the site's server, SSL or STARTTLS, a sign-in
 * when a user name is set, one message per connection, through the same
 * SmtpSession the PHPMailer SMTP class speaks with.
 */
final readonly class Smtp
{
    public function __construct(private MailSettings $settings)
    {
    }

    /**
     * Sends one composed message (headers, a blank line, the body) to the
     * envelope recipients; false on any refusal.
     *
     * @param list<string> $recipients
     */
    public function sendRaw(string $from, array $recipients, string $data): bool
    {
        $s = $this->settings;
        $session = new SmtpSession(static function (string $text, int $level): void {
        });
        $host = (string) (gethostname() ?: 'localhost');
        if (!$session->connect(($s->encryption === 'ssl' ? 'ssl://' : '') . $s->host, $s->port, 15, [])) {
            return false;
        }
        $ok = $session->hello($host)
            && ($s->encryption !== 'tls' || ($session->startTls() && $session->hello($host)))
            && ($s->username === '' || $session->authenticate($s->username, $s->password, '', null))
            && $session->mail($from, '');
        foreach ($ok ? $recipients : [] as $address) {
            $ok = $session->recipient($address, '') && $ok;
        }
        $ok = $ok && $session->data($data);
        $session->quit();
        return $ok;
    }
}
