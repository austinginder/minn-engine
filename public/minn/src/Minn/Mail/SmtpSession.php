<?php

declare(strict_types=1);

namespace Minn\Mail;

use Closure;

/**
 * One SMTP conversation as the reference's client holds it: the socket,
 * the last reply, the server's extensions from EHLO, and the last error as
 * {error, detail, smtp_code, smtp_code_ex}. Every command names itself in
 * its error ("RCPT TO command failed"); a command sent before connecting
 * fails as "Called X without being connected". Debug text goes to the
 * given closure with its level: 1 client lines, 2 server replies, 3
 * connection events, 4 raw inbound lines. Credentials never reach it.
 */
final class SmtpSession
{
    public const MAX_LINE = 998;
    private const CREDENTIALS = ['User & Password', 'Username', 'Password'];
    private const AUTH_ORDER = ['CRAM-MD5', 'LOGIN', 'PLAIN', 'XOAUTH2'];
    private const TRANSACTION_IDS = [
        '/queued as ([^\s]+)/i',
        '/\bOK id=([^\s]+)/i',
        '/^\d{3} 2\.0\.0 ([^\s]+) Message accepted for delivery/m',
        '/^\d{3} 2\.\d\.0 <?([^\s@>]+)@[^\s]* Queued mail for delivery/m',
        '/Message Queued \(([^)]+)\)/i',
        '/^\d{3} Ok ([^\s]+)/m',
    ];

    /** @var resource|null */
    private $socket = null;
    /** @var array{error: string, detail: string, smtp_code: int|string, smtp_code_ex: string} */
    private array $error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
    private string $heloReply = '';
    /** @var array<string, mixed>|null */
    private ?array $caps = null;
    private string $lastReply = '';
    private string|false|null $transactionId = null;
    private int $timeout = 300;
    private int $timelimit = 300;

    /** @param Closure(string, int): void $debug */
    public function __construct(private readonly Closure $debug)
    {
    }

    /** How long one read may wait and how long a whole reply may take, in seconds. */
    public function limits(int $timeout, int $timelimit): void
    {
        $this->timeout = max(0, $timeout);
        $this->timelimit = max(0, $timelimit);
    }

    /**
     * Opens the connection and reads the greeting.
     *
     * @param array<string, mixed> $options stream context options
     */
    public function connect(string $host, ?int $port, int $timeout, array $options): bool
    {
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Already connected to a server');
            return false;
        }
        $port = $port ?: 25;
        $this->say('Connection: opening to ' . $host . ':' . $port . ', timeout=' . $timeout . ', options=' . str_replace(["\n", ' '], '', var_export($options, true)), 3);
        $socket = $this->open($host, $port, $timeout, $options);
        if ($socket === null) {
            return false;
        }
        $this->say('Connection: opened', 3);
        $this->socket = $socket;
        stream_set_timeout($socket, $timeout);
        $this->lastReply = $this->lines();
        $this->say('SERVER -> CLIENT: ' . $this->lastReply, 2);
        return true;
    }

    /** Asks for TLS and turns it on. */
    public function startTls(): bool
    {
        if (!$this->command('STARTTLS', 'STARTTLS', [220])) {
            return false;
        }
        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        set_error_handler(fn (int $no, string $text, string $file, int $line): bool => $this->failed($no, $text, $file, $line));
        try {
            return stream_socket_enable_crypto($this->socket, true, $method) === true;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Signs in with the named mechanism, or the strongest the server offers
     * when it is empty or not offered.
     *
     * @param string|null $oauth a ready XOAUTH2 token, base64, when there is one
     */
    public function authenticate(string $user, string $password, string $type, ?string $oauth): bool
    {
        if ($this->caps === null) {
            $this->setError('Authentication is not allowed before HELO/EHLO');
            return false;
        }
        $type = $this->mechanism($type, $oauth);
        if ($type === '') {
            return false;
        }
        return match ($type) {
            'PLAIN' => $this->command('AUTH', 'AUTH PLAIN', [334]) && $this->command('User & Password', base64_encode("\0{$user}\0{$password}"), [235]),
            'LOGIN' => $this->command('AUTH', 'AUTH LOGIN', [334]) && $this->command('Username', base64_encode($user), [334]) && $this->command('Password', base64_encode($password), [235]),
            'CRAM-MD5' => $this->command('AUTH', 'AUTH CRAM-MD5', [334]) && $this->command('Username', base64_encode($user . ' ' . hash_hmac('md5', (string) base64_decode(substr($this->lastReply, 4)), $password)), [235]),
            'XOAUTH2' => $this->command('AUTH', 'AUTH XOAUTH2 ' . $oauth, [235]),
            default => $this->unsupported($type),
        };
    }

    /** Whether the connection is open; a socket closed at the far end is closed here too. */
    public function connected(): bool
    {
        if (!is_resource($this->socket)) {
            return false;
        }
        if (stream_get_meta_data($this->socket)['eof']) {
            $this->say('SMTP NOTICE: EOF caught while checking if connected', 1);
            $this->close();
            return false;
        }
        return true;
    }

    /** Closes the socket and forgets the server's greeting and extensions; the last error stays. */
    public function close(): void
    {
        $this->caps = null;
        $this->heloReply = '';
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
            $this->say('Connection: closed', 3);
        }
    }

    /**
     * Sends a message: DATA, the lines (a line past 998 characters split at
     * its last space, or at 997 without one; header continuations indented;
     * leading dots doubled), then the closing dot.
     */
    public function data(string $message): bool
    {
        if (!$this->command('DATA', 'DATA', [354])) {
            return false;
        }
        foreach (DataLines::split($message) as $line) {
            $this->send(($line !== '' && $line[0] === '.' ? '.' : '') . $line . "\r\n", 'DATA');
        }
        $limit = $this->timelimit;
        $this->timelimit = max($limit, 300);
        $ok = $this->command('DATA END', '.', [250]);
        $this->timelimit = $limit;
        $this->transactionId = false;
        foreach (self::TRANSACTION_IDS as $pattern) {
            if (preg_match($pattern, $this->lastReply, $m)) {
                $this->transactionId = trim($m[1]);
                break;
            }
        }
        return $ok;
    }

    /** EHLO, falling back to HELO; the extensions come from the EHLO reply. */
    public function hello(string $host): bool
    {
        return $this->greet('EHLO', $host) || $this->greet('HELO', $host);
    }

    /** MAIL FROM, with the given parameters (" XVERP", " SMTPUTF8") after the address. */
    public function mail(string $from, string $parameters): bool
    {
        return $this->command('MAIL FROM', 'MAIL FROM:<' . $from . '>' . $parameters, [250]);
    }

    /** QUIT; the connection closes when it succeeds, or anyway unless told to keep it. */
    public function quit(string $onError = 'close'): bool
    {
        $ok = $this->command('QUIT', 'QUIT', [221]);
        $error = $this->error;
        if ($ok || $onError === 'close') {
            $this->close();
            $this->error = $error;
        }
        return $ok;
    }

    /** RCPT TO, with the delivery notifications asked for (NEVER, SUCCESS, FAILURE, DELAY). */
    public function recipient(string $address, string $dsn): bool
    {
        $line = 'RCPT TO:<' . $address . '>';
        if ($dsn !== '') {
            $asked = array_values(array_filter(explode(',', strtoupper($dsn)), static fn (string $v): bool => in_array($v, ['NEVER', 'SUCCESS', 'FAILURE', 'DELAY'], true)));
            $line .= ' NOTIFY=' . implode(',', in_array('NEVER', $asked, true) ? ['NEVER'] : $asked);
        }
        return $this->command('RCPT TO', $line, [250, 251]);
    }

    /**
     * XCLIENT with the named attributes that are in $allowed; others are dropped.
     *
     * @param array<string, string> $vars
     * @param list<string> $allowed
     */
    public function xclient(array $vars, array $allowed): bool
    {
        $pairs = [];
        foreach ($vars as $name => $value) {
            if (in_array(strtoupper((string) $name), $allowed, true)) {
                $pairs[] = strtoupper((string) $name) . '=' . $value;
            }
        }
        return $this->command('XCLIENT', 'XCLIENT ' . implode(' ', $pairs), [220]);
    }

    /** TURN is refused here, as the reference refuses it. */
    public function turn(): bool
    {
        $this->setError('The SMTP TURN command is not implemented');
        $this->say('SMTP NOTICE: ' . $this->error['error'], 1);
        return false;
    }

    /**
     * Sends one command and reads the reply; a reply outside $expect fails
     * with "{name} command failed" and the reply's detail and codes.
     *
     * @param list<int> $expect
     */
    public function command(string $name, string $line, array $expect): bool
    {
        if (!$this->connected()) {
            $this->setError("Called {$name} without being connected");
            return false;
        }
        if (strpbrk($line, "\r\n") !== false) {
            $this->setError("Command '{$name}' contained line breaks");
            return false;
        }
        $this->send($line . "\r\n", $name);
        $this->lastReply = $this->lines();
        [$code, $codeEx, $detail] = self::parseReply($this->lastReply);
        $this->say('SERVER -> CLIENT: ' . $this->lastReply, 2);
        if (!in_array($code, $expect, true)) {
            $this->setError("{$name} command failed", $detail, $code, $codeEx);
            $this->say('SMTP ERROR: ' . $this->error['error'] . ': ' . $this->lastReply, 1);
            return false;
        }
        $this->setError('');
        return true;
    }

    /** Writes raw text to the server; credential commands show as hidden in the debug text. */
    public function send(string $data, string $command): int|false
    {
        $this->say('CLIENT -> SERVER: ' . (in_array($command, self::CREDENTIALS, true) ? '[credentials hidden]' : $data), 1);
        if (!is_resource($this->socket)) {
            return false;
        }
        set_error_handler(fn (int $no, string $text, string $file, int $line): bool => $this->failed($no, $text, $file, $line));
        try {
            return fwrite($this->socket, $data);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The last error; every field is empty after a command that succeeded.
     *
     * @return array{error: string, detail: string, smtp_code: int|string, smtp_code_ex: string}
     */
    public function error(): array
    {
        return $this->error;
    }

    /**
     * The extensions from the last EHLO (or the HELO greeting), null before one.
     *
     * @return array<string, mixed>|null
     */
    public function extensions(): ?array
    {
        return $this->caps;
    }

    /** One extension's value: true for a bare one, a string or list for one with arguments, false when absent, null before EHLO. */
    public function extension(string $name): mixed
    {
        if ($this->caps === null) {
            $this->setError('No HELO/EHLO was sent');
            return null;
        }
        if (array_key_exists($name, $this->caps)) {
            return $this->caps[$name];
        }
        if ($name === 'HELO') {
            return $this->caps['EHLO'] ?? null;
        }
        if ($name === 'EHLO' || array_key_exists('EHLO', $this->caps)) {
            return false;
        }
        $this->setError('HELO handshake was used; No information about server extensions available');
        return null;
    }

    /** The server's last reply, every line. */
    public function lastReply(): string
    {
        return $this->lastReply;
    }

    /** The queue id the server gave the last message: null before one was sent, false when none was recognised. */
    public function transactionId(): string|false|null
    {
        return $this->transactionId;
    }

    /**
     * The reply's code, enhanced code (when present) and the text after them.
     *
     * @return array{0: int, 1: string, 2: string}
     */
    public static function parseReply(string $reply): array
    {
        if (preg_match('/^(\d{3})[ -](?:(\d\.\d\.\d{1,3}) )?/', $reply, $m)) {
            $prefix = '/^' . $m[1] . '[ -]' . (isset($m[2]) && $m[2] !== '' ? preg_quote($m[2], '/') . ' ' : '') . '/m';
            return [(int) $m[1], $m[2] ?? '', (string) preg_replace($prefix, '', $reply)];
        }
        return [(int) substr($reply, 0, 3), '', substr($reply, 4)];
    }

    private function greet(string $verb, string $host): bool
    {
        $ok = $this->command($verb, $verb . ' ' . $host, [250]);
        $this->heloReply = $this->lastReply;
        $this->caps = $ok ? self::extensionsFrom($verb, $this->heloReply) : null;
        return $ok;
    }

    /** @return array<string, mixed> */
    private static function extensionsFrom(string $verb, string $reply): array
    {
        $caps = [];
        foreach (explode("\n", $reply) as $index => $line) {
            $fields = explode(' ', trim(substr(trim($line), 4)));
            if ($fields === ['']) {
                continue;
            }
            if ($index === 0) {
                $caps[$verb] = $fields[0];
                continue;
            }
            $name = strtoupper((string) array_shift($fields));
            $caps[$name] = match (true) {
                $name === 'AUTH' => $fields,
                $fields === [] => true,
                default => implode(' ', $fields),
            };
        }
        return $caps;
    }

    private function mechanism(string $type, ?string $oauth): string
    {
        if (!array_key_exists('EHLO', $this->caps ?? [])) {
            return $type !== '' ? strtoupper($type) : 'LOGIN';
        }
        $offered = $this->caps['AUTH'] ?? null;
        if (!is_array($offered)) {
            $this->setError('Authentication is not allowed at this stage');
            return '';
        }
        $type = strtoupper($type);
        if ($type !== '' && in_array($type, $offered, true) && ($type !== 'XOAUTH2' || $oauth !== null)) {
            return $type;
        }
        foreach (self::AUTH_ORDER as $candidate) {
            if (in_array($candidate, $offered, true) && ($candidate !== 'XOAUTH2' || $oauth !== null)) {
                return $candidate;
            }
        }
        $this->setError('No supported authentication methods found');
        return '';
    }

    private function unsupported(string $type): bool
    {
        $this->setError("The requested authentication method \"{$type}\" is not supported by the server");
        return false;
    }

    /**
     * @param array<string, mixed> $options
     * @return resource|null
     */
    private function open(string $host, int $port, int $timeout, array $options)
    {
        $errno = 0;
        $errstr = '';
        set_error_handler(fn (int $no, string $text, string $file, int $line): bool => $this->failed($no, $text, $file, $line));
        try {
            $socket = stream_socket_client($host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, stream_context_create($options));
        } finally {
            restore_error_handler();
        }
        if ($socket !== false) {
            return $socket;
        }
        $this->setError('Failed to connect to server', '', (string) $errno, $errstr);
        $this->say('SMTP ERROR: ' . $this->error['error'] . ": {$errstr} ({$errno})", 1);
        return null;
    }

    private function lines(): string
    {
        if (!is_resource($this->socket)) {
            return '';
        }
        $data = '';
        $ends = time() + $this->timelimit;
        stream_set_timeout($this->socket, $this->timeout);
        while (is_resource($this->socket) && !feof($this->socket)) {
            $line = @fgets($this->socket, 512);
            if ($line === false) {
                break;
            }
            $this->say('SMTP INBOUND: "' . trim($line) . '"', 4);
            $data .= $line;
            if (!isset($line[3]) || in_array($line[3], [' ', "\r", "\n"], true)) {
                break;
            }
            if (stream_get_meta_data($this->socket)['timed_out']) {
                $this->say("SMTP -> get_lines(): stream timed-out ({$this->timeout} sec)", 4);
                break;
            }
            if (time() > $ends) {
                $this->say("SMTP -> get_lines(): timelimit reached ({$this->timelimit} sec)", 4);
                break;
            }
        }
        return $data;
    }

    private function failed(int $no, string $text, string $file, int $line): bool
    {
        $this->say("Connection failed. Error #{$no}: {$text} [{$file} line {$line}]", 3);
        return true;
    }

    private function setError(string $message, string $detail = '', int|string $code = '', string $codeEx = ''): void
    {
        $this->error = ['error' => $message, 'detail' => $detail, 'smtp_code' => $code, 'smtp_code_ex' => $codeEx];
    }

    private function say(string $text, int $level): void
    {
        ($this->debug)($text, $level);
    }
}
