<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\Users;

/**
 * The session_tokens usermeta store: {sha256(token): {expiration, ip, ua,
 * login}}. Read by a bounded scan of the serialized blob and written by
 * serializing it ourselves, so stored data is never executed.
 */
final readonly class Sessions
{
    public function __construct(private Users $users)
    {
    }

    /** A 43-character token; only sha256(token) is ever stored or compared. */
    public static function generateToken(): string
    {
        return substr(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), 0, 43);
    }

    /** Whether a session token is still stored and unexpired for a user. */
    public function isLive(int $userId, string $token): bool
    {
        $want = hash('sha256', $token);
        $blob = $this->users->meta($userId, 'session_tokens');
        if ($blob === null || $blob === '') {
            return false;
        }
        if (!preg_match_all('/s:64:"([0-9a-f]{64})";a:\d+:\{(.*?)\}(?=s:64:|$|\}$)/s', $blob, $matches, PREG_SET_ORDER)) {
            $position = strpos($blob, 's:64:"' . $want . '"');
            if ($position === false) {
                return false;
            }
            if (preg_match('/s:10:"expiration";i:(\d+);/', substr($blob, $position), $m)) {
                return (int) $m[1] >= time();
            }
            return true;
        }
        foreach ($matches as $entry) {
            if (!hash_equals($entry[1], $want)) {
                continue;
            }
            if (preg_match('/s:10:"expiration";i:(\d+);/', $entry[2], $m)) {
                return (int) $m[1] >= time();
            }
            return true;
        }
        return false;
    }

    /** Creates a session, pruning expired ones, and returns the raw token. */
    public function create(int $userId, int $expiration, string $ip, string $userAgent): string
    {
        $token = self::generateToken();
        $sessions = $this->prune($this->read($userId));
        // The store is read back by shape, so the request-supplied fields carry
        // nothing that looks like the shape.
        $plain = static fn (string $v): string => substr((string) preg_replace('/[^\x20-\x7e]|["{};]/', '', $v), 0, 255);
        // A request without a User-Agent stores no ua key, as the reference does.
        $sessions[hash('sha256', $token)] = ['expiration' => $expiration, 'ip' => $plain($ip)]
            + ($userAgent === '' ? [] : ['ua' => $plain($userAgent)])
            + ['login' => time()];
        $this->write($userId, $sessions);
        return $token;
    }

    /** Ends every session of the user (a password change). */
    public function destroyAll(int $userId): void
    {
        $this->write($userId, []);
    }

    /** Removes one session; true when it existed. */
    public function destroy(int $userId, string $token): bool
    {
        return $this->destroyKey($userId, hash('sha256', $token));
    }

    /** Removes the session stored under a key (the sha256 of its token); true when it existed. */
    public function destroyKey(int $userId, string $key): bool
    {
        $sessions = $this->read($userId);
        if (!isset($sessions[$key])) {
            return false;
        }
        unset($sessions[$key]);
        $this->write($userId, $sessions);
        return true;
    }

    /** Ends every session of the user except the one stored under the given key. */
    public function destroyOthers(int $userId, string $keepKey): void
    {
        $sessions = $this->read($userId);
        $this->write($userId, isset($sessions[$keepKey]) ? [$keepKey => $sessions[$keepKey]] : []);
    }

    /**
     * Every stored session of a user, keyed by token hash. Each entry keeps
     * its stored text under raw, so writing the store back leaves sessions
     * this request did not make exactly as they were, including anything a
     * plugin attached to them.
     *
     * @return array<string, array{expiration: int, ip: string, ua: string, login: int, raw: string}>
     */
    public function read(int $userId): array
    {
        $blob = $this->users->meta($userId, 'session_tokens');
        if ($blob === null || $blob === '') {
            return [];
        }
        $sessions = [];
        if (preg_match_all('/s:64:"([0-9a-f]{64})";(a:\d+:\{(.*?)\})(?=s:64:|\}$)/s', $blob, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $entry) {
                $sessions[$entry[1]] = self::parseEntry($entry[3]) + ['raw' => $entry[2]];
            }
        }
        return $sessions;
    }

    private static function parseEntry(string $inner): array
    {
        $entry = ['expiration' => 0, 'ip' => '', 'ua' => '', 'login' => 0];
        if (preg_match('/s:10:"expiration";i:(\d+);/', $inner, $m)) {
            $entry['expiration'] = (int) $m[1];
        }
        if (preg_match('/s:2:"ip";s:\d+:"(.*?)";/s', $inner, $m)) {
            $entry['ip'] = $m[1];
        }
        if (preg_match('/s:2:"ua";s:\d+:"(.*?)";/s', $inner, $m)) {
            $entry['ua'] = $m[1];
        }
        if (preg_match('/s:5:"login";i:(\d+);/', $inner, $m)) {
            $entry['login'] = (int) $m[1];
        }
        return $entry;
    }

    private function prune(array $sessions): array
    {
        $now = time();
        return array_filter($sessions, static fn (array $entry) => ($entry['expiration'] ?? 0) >= $now);
    }

    private function write(int $userId, array $sessions): void
    {
        $this->users->setMeta($userId, 'session_tokens', self::serialize($sessions));
    }

    /** The map in PHP's serialized form: stored entries as they were read, new ones key by key in the order given. */
    public static function serialize(array $sessions): string
    {
        $out = 'a:' . count($sessions) . ':{';
        foreach ($sessions as $key => $entry) {
            $out .= self::string((string) $key) . ($entry['raw'] ?? self::entry($entry));
        }
        return $out . '}';
    }

    /** One new session: integers for the times, strings for the rest. @param array<string, int|string> $entry */
    private static function entry(array $entry): string
    {
        $fields = '';
        foreach ($entry as $name => $value) {
            $fields .= self::string((string) $name) . (is_int($value) ? 'i:' . $value . ';' : self::string((string) $value));
        }
        return 'a:' . count($entry) . ':{' . $fields . '}';
    }

    private static function string(string $value): string
    {
        return 's:' . strlen($value) . ':"' . $value . '";';
    }
}
