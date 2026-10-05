<?php
/**
 * A small SMTP server for the mail suite: it answers one session at a
 * time on 127.0.0.1:<port>, records every line of each session (client,
 * server, and the message data) to <dir>/session-<n>.json, and exits after
 * <idle> seconds without a connection. It offers AUTH PLAIN LOGIN CRAM-MD5
 * (user "probe", password "secret"), refuses any recipient containing
 * "reject", and has no TLS.
 *
 *   php fake-smtp.php <port> <dir> [idle seconds]
 */

[, $port, $dir, $idle] = $argv + [null, null, null, 30];
$server = @stream_socket_server('tcp://127.0.0.1:' . (int) $port, $errno, $error);
if ($server === false) {
    fwrite(STDERR, "cannot listen on {$port}: {$error}\n");
    exit(1);
}
$session = 0;
while (($conn = @stream_socket_accept($server, (float) $idle)) !== false) {
    $session++;
    $log = [];
    $send = static function (string $line) use ($conn, &$log): void {
        fwrite($conn, $line . "\r\n");
        $log[] = ['S', $line];
    };
    $read = static function () use ($conn, &$log): ?string {
        $line = fgets($conn);
        if ($line === false) {
            return null;
        }
        $line = rtrim($line, "\r\n");
        $log[] = ['C', $line];
        return $line;
    };
    $credentials = static fn (string $user, string $pass): bool => $user === 'probe' && $pass === 'secret';
    $send('220 fake.smtp.test ESMTP ready');
    while (($line = $read()) !== null) {
        $verb = strtoupper((string) strtok($line, ' '));
        $rest = trim(substr($line, strlen($verb)));
        if ($verb === 'EHLO') {
            foreach (['250-fake.smtp.test Hello ' . $rest, '250-AUTH PLAIN LOGIN CRAM-MD5', '250-8BITMIME', '250-SIZE 10240000'] as $l) {
                $send($l);
            }
            $send('250 SMTPUTF8');
        } elseif ($verb === 'HELO') {
            $send('250 fake.smtp.test');
        } elseif ($verb === 'AUTH') {
            [$mech, $initial] = array_pad(explode(' ', $rest, 2), 2, null);
            $mech = strtoupper((string) $mech);
            $ok = false;
            if ($mech === 'PLAIN') {
                if ($initial === null) {
                    $send('334 ');
                    $initial = (string) $read();
                }
                $parts = explode("\0", (string) base64_decode((string) $initial));
                $ok = $credentials((string) ($parts[1] ?? ''), (string) ($parts[2] ?? ''));
            } elseif ($mech === 'LOGIN') {
                $send('334 VXNlcm5hbWU6');
                $user = (string) base64_decode((string) $read());
                $send('334 UGFzc3dvcmQ6');
                $ok = $credentials($user, (string) base64_decode((string) $read()));
            } elseif ($mech === 'CRAM-MD5') {
                $challenge = '<12345.67890@fake.smtp.test>';
                $send('334 ' . base64_encode($challenge));
                [$user, $digest] = array_pad(explode(' ', (string) base64_decode((string) $read()), 2), 2, '');
                $ok = $user === 'probe' && $digest === hash_hmac('md5', $challenge, 'secret');
            }
            $send($ok ? '235 2.7.0 Authentication successful' : '535 5.7.8 Authentication failed');
        } elseif ($verb === 'MAIL') {
            $send('250 2.1.0 Ok');
        } elseif ($verb === 'RCPT') {
            $send(stripos($line, 'reject') !== false ? '550 5.1.1 Recipient rejected' : '250 2.1.5 Ok');
        } elseif ($verb === 'DATA') {
            $send('354 End data with <CR><LF>.<CR><LF>');
            $message = '';
            while (($data = fgets($conn)) !== false && $data !== ".\r\n") {
                $message .= $data;
            }
            $log[] = ['DATA', $message];
            $send('250 2.0.0 Ok: queued as FAKE' . $session);
        } elseif ($verb === 'RSET' || $verb === 'NOOP') {
            $send('250 2.0.0 Ok');
        } elseif ($verb === 'VRFY') {
            $send('252 2.0.0 Cannot VRFY user');
        } elseif ($verb === 'STARTTLS') {
            $send('454 4.7.0 TLS not available');
        } elseif ($verb === 'QUIT') {
            $send('221 2.0.0 Bye');
            break;
        } else {
            $send('502 5.5.2 Error: command not recognized');
        }
    }
    fclose($conn);
    file_put_contents("{$dir}/session-{$session}.json", json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
}
