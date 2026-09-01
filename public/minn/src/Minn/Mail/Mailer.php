<?php

declare(strict_types=1);

namespace Minn\Mail;

use Minn\Content\Site;
use Minn\Db;
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
        return self::forSite(new Site(Db::shared()))->send(Message::to($to, $subject, $body));
    }

    /** The engine's own notices, worded once, from this site's name and address. */
    public static function noticesFor(Site $site): Notices
    {
        return new Notices(
            (string) ($site->option('blogname') ?? 'Site'),
            rtrim((string) ($site->option('home') ?? ''), '/'),
        );
    }

    public function send(Message $message): bool
    {
        $from = $message->fromEmail !== '' ? $message->fromEmail : $this->settings->fromEmail;
        $fromName = $message->fromName !== '' ? $message->fromName : $this->settings->fromName;
        $to = array_values(array_filter($message->to, static fn (string $a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false));
        if ($to === []) {
            return false;
        }
        try {
            return match ($this->settings->transport) {
                'smtp' => (new Smtp($this->settings))->send($from, $fromName, $to, $message->subject, $message->body),
                'log' => $this->log($from, $fromName, $to, $message),
                default => $this->viaMail($from, $fromName, $to, $message),
            };
        } catch (Throwable $e) {
            error_log('Minn Engine: mail failed: ' . $e->getMessage());
            return false;
        }
    }

    /** @param list<string> $to */
    private function viaMail(string $from, string $fromName, array $to, Message $message): bool
    {
        $headers = 'From: ' . self::address($from, $fromName) . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n";
        return mail(implode(', ', $to), self::encodeHeader($message->subject), $message->body, $headers);
    }

    /** @param list<string> $to */
    private function log(string $from, string $fromName, array $to, Message $message): bool
    {
        $line = json_encode([
            'time' => gmdate('c'),
            'from' => self::address($from, $fromName),
            'to' => $to,
            'subject' => $message->subject,
            'body' => $message->body,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX) !== false;
    }

    public static function address(string $email, string $name): string
    {
        $name = trim(str_replace(["\r", "\n"], '', $name));
        return $name === '' ? $email : self::encodeHeader($name) . " <{$email}>";
    }

    /** A header value: no line breaks, encoded when not plain ASCII. */
    public static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }
}
