<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Auth\Capabilities;
use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Json;
use Minn\Login\OneTimeLink;
use mysqli;
use Throwable;

/**
 * `minn login <webroot> [<user>]`: the one-time sign-in link, minted by the
 * engine alone. wp-config.php is read as text, never run, so neither
 * WP-CLI, the WordPress runtime nor the site's plugins take part, and the
 * link works whatever MINN_WP_API says. With no user named it signs in the
 * first administrator by login. The link opens the engine's own sign-in
 * page (`wp user login` keeps wp-login.php, the address hosting tools
 * expect); --to lands somewhere other than the admin.
 */
final class LoginLink
{
    /** @var list<string> */
    private array $lines = [];

    private function __construct(private readonly Users $users, private readonly Capabilities $capabilities, private readonly Permalinks $permalinks)
    {
    }

    /**
     * Mints a link for a webroot and hands back the exit code and what to
     * print: the address alone, or with --format=json the address, the user,
     * their roles and when the link expires.
     *
     * @param array<string, string|true> $options
     * @return array{code: int, lines: list<string>}
     */
    public static function run(string $root, ?string $who, array $options): array
    {
        $config = is_file("{$root}/wp-config.php") ? Preflight::readConfig("{$root}/wp-config.php") : [];
        if (!isset($config['DB_NAME'], $config['DB_USER'], $config['DB_HOST'])) {
            return ['code' => 1, 'lines' => ["Error: {$root} has no wp-config.php with database constants Minn can read."]];
        }
        try {
            $connection = new mysqli($config['DB_HOST'], $config['DB_USER'], $config['DB_PASSWORD'] ?? '', $config['DB_NAME']);
            $connection->set_charset('utf8mb4');
        } catch (Throwable $e) {
            return ['code' => 1, 'lines' => ["Error: the database in {$root}/wp-config.php did not answer: {$e->getMessage()}"]];
        }
        $db = new Db($connection, $config['prefix']);
        $self = new self(new Users($db), Capabilities::fromDb($db), Permalinks::fromDb($db));
        $code = $self->mint($who, $options);
        return ['code' => $code, 'lines' => $self->lines];
    }

    /** @param array<string, string|true> $options */
    private function mint(?string $who, array $options): int
    {
        $to = is_string($options['to'] ?? null) ? $options['to'] : '';
        if ($to !== '' && (!str_starts_with($to, '/') || str_starts_with($to, '//') || preg_match('/[\x00-\x20\x7f\\\\]/', $to) === 1)) {
            $this->lines[] = 'Error: --to is a path on the site, such as /minn-admin/content.';
            return 1;
        }
        $user = $who !== null ? $this->users->identify($who) : $this->firstAdministrator();
        if ($user === null) {
            $this->lines[] = $who !== null ? "Error: User not found: {$who}" : 'Error: the site has no administrator; name a user.';
            return 1;
        }
        $url = $this->permalinks->url('/minn-admin/login') . '?' . (new OneTimeLink($this->users))->mint($user);
        if ($to !== '') {
            $url .= '&redirect_to=' . rawurlencode($this->permalinks->url($to));
        }
        $this->lines[] = ($options['format'] ?? '') === 'json' ? Json::encode([
            'url' => $url,
            'id' => $user->id,
            'user' => $user->login,
            'roles' => $this->capabilities->rolesOf($user->id),
            'expires' => time() + OneTimeLink::LIFETIME,
        ]) : $url;
        return 0;
    }

    /** The first user by login holding the administrator role, as `wp user list --role=administrator` lists them. */
    private function firstAdministrator(): ?UserRecord
    {
        foreach ($this->users->ids(0) as $id) {
            if (in_array('administrator', $this->capabilities->rolesOf($id), true)) {
                return $this->users->find($id);
            }
        }
        return null;
    }
}
