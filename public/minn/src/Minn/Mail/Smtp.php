<?php

declare(strict_types=1);

namespace Minn\Mail;

use RuntimeException;

/** A small SMTP client: SSL or STARTTLS, AUTH LOGIN or PLAIN, one message per connection. */
final readonly class Smtp
{
    public function __construct(private MailSettings $settings)
    {
    }

    /**
     * Sends one message over SMTP; false on failure.
     *
     * @param list<string> $to
     */
    public function send(string $from, string $fromName, array $to, string $subject, string $body): bool
    {
        $headers = 'From: ' . Mailer::address($from, $fromName) . "\r\n"
            . 'To: ' . implode(', ', $to) . "\r\n"
            . 'Subject: ' . Mailer::encodeHeader($subject) . "\r\n"
            . 'Date: ' . date(DATE_RFC2822) . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->settings->host . ">\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
        $text = (string) preg_replace('/\r?\n/', "\r\n", $body);
        return $this->sendRaw($from, $to, $headers . "\r\n" . $text);
    }

    /** One raw MIME message (headers and body) to the listed envelope recipients. @param list<string> $recipients */
    public function sendRaw(string $from, array $recipients, string $data): bool
    {
        $s = $this->settings;
        $scheme = $s->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($scheme . $s->host . ':' . $s->port, $errno, $error, 15);
        if ($socket === false) {
            throw new RuntimeException("SMTP connect to {$s->host}:{$s->port} failed: {$error}");
        }
        stream_set_timeout($socket, 15);
        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . (gethostname() ?: 'localhost'), 250);
            if ($s->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS failed');
                }
                $this->command($socket, 'EHLO ' . (gethostname() ?: 'localhost'), 250);
            }
            if ($s->username !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($s->username), 334);
                $this->command($socket, base64_encode($s->password), 235);
            }
            $this->command($socket, "MAIL FROM:<{$from}>", 250);
            foreach ($recipients as $address) {
                $this->command($socket, "RCPT TO:<{$address}>", [250, 251]);
            }
            $this->command($socket, 'DATA', 354);
            fwrite($socket, (string) preg_replace('/^\./m', '..', $data) . "\r\n.\r\n");
            $this->expect($socket, 250);
            $this->command($socket, 'QUIT', 221);
            return true;
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket @param int|list<int> $codes */
    private function command($socket, string $line, int|array $codes): void
    {
        fwrite($socket, $line . "\r\n");
        $this->expect($socket, $codes);
    }

    /** @param resource $socket @param int|list<int> $codes */
    private function expect($socket, int|array $codes): void
    {
        $reply = '';
        do {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new RuntimeException('SMTP connection closed');
            }
            $reply .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $codes, true)) {
            throw new RuntimeException('SMTP: ' . trim($reply));
        }
    }
}
