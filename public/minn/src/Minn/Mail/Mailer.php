<?php

declare(strict_types=1);

namespace Minn\Mail;

use Minn\Content\Site;
use Minn\Db;
use Minn\Runtime\Runtime;
use Throwable;

/**
 * Sends a Message through the configured transport. Failures are logged
 * and reported as false; nothing here throws into a request.
 */
final readonly class Mailer
{
    public function __construct(private MailSettings $settings, private string $logFile)
    {
    }

    /** A mailer using the site's own settings. */
    public static function forSite(Site $site): self
    {
        return new self(MailSettings::fromSite($site), ABSPATH . 'wp-content/minn-mail.log');
    }

    /**
     * The one-line send: a recipient, a subject, a body, through the site's
     * transport and sender, with nothing to construct at the call site.
     */
    public static function mail(string|array $to, string $subject, string $body): bool
    {
        return self::forSite(new Site(Db::current()))->send(Message::to($to, $subject, $body));
    }

    /** The engine's own notices, worded once, from this site's name and address. */
    public static function noticesFor(Site $site): Notices
    {
        return new Notices(
            (string) ($site->option('blogname') ?? 'Site'),
            rtrim((string) ($site->option('home') ?? ''), '/'),
        );
    }

    /**
     * Sends one message; false on failure. With WordPress's mail function
     * loaded the message goes through wp_mail(), so its filters and the
     * phpmailer_init hook (an SMTP plugin's way in) apply as they do on the
     * reference; otherwise through the configured transport directly.
     */
    public function send(Message $message): bool
    {
        if (Runtime::booted() && function_exists('wp_mail')) {
            $headers = $message->fromEmail !== '' ? ['From: ' . ($message->fromName !== '' ? $message->fromName . ' <' . $message->fromEmail . '>' : $message->fromEmail)] : [];
            return (bool) \wp_mail($message->to, $message->subject, $message->body, $headers);
        }
        $from = $message->fromEmail !== '' ? $message->fromEmail : $this->settings->fromEmail;
        $fromName = $message->fromName !== '' ? $message->fromName : $this->settings->fromName;
        $to = array_values(array_filter($message->to, static fn (string $a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false));
        if ($to === []) {
            return false;
        }
        try {
            return match ($this->settings->transport) {
                'smtp' => $this->viaSmtp($from, $fromName, $to, $message),
                'log' => MailLog::write($this->logFile, $from, $fromName, $to, $message->subject, $message->body),
                default => $this->viaMail($from, $fromName, $to, $message),
            };
        } catch (Throwable $e) {
            error_log('Minn Engine: mail failed: ' . $e->getMessage());
            return false;
        }
    }

    /** @param list<string> $to */
    private function viaSmtp(string $from, string $fromName, array $to, Message $message): bool
    {
        $draft = self::draft('smtp', $from, $fromName, $to, $message);
        $id = bin2hex(random_bytes(16));
        $body = Composer::body($draft, $id);
        $data = Composer::headers($draft, $id, (string) (gethostname() ?: 'localhost'), $body['encoding']) . "\r\n" . $body['body'];
        return (new Smtp($this->settings))->sendRaw($from, $to, $data);
    }

    /** @param list<string> $to */
    private function viaMail(string $from, string $fromName, array $to, Message $message): bool
    {
        $draft = self::draft('mail', $from, $fromName, $to, $message);
        $id = bin2hex(random_bytes(16));
        $body = Composer::body($draft, $id);
        $headers = Composer::headers($draft, $id, (string) (gethostname() ?: 'localhost'), $body['encoding']);
        $subject = $draft->encodeHeader(Composer::secure($message->subject));
        return mail(implode(', ', $to), $subject, $body['body'], rtrim($headers));
    }

    /** @param list<string> $to */
    private static function draft(string $mailer, string $from, string $fromName, array $to, Message $message): Draft
    {
        $pairs = array_map(static fn (string $address): array => [$address, ''], $to);
        return new Draft($mailer, "\r\n", 'UTF-8', 'text/plain', '8bit', $message->subject, $message->body, '', '', $from, $fromName, $pairs, [], [], [], '', '', null, '', '', [], []);
    }
}
