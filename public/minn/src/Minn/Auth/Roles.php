<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Db;

/**
 * Role definitions from the site's {prefix}user_roles option, parsed by a
 * bounded scan of the serialized blob, with the seeded contract copy as the
 * fallback for a fresh install.
 *
 * @phpstan-type RoleMap array<string, array{name: string, capabilities: array<string, bool>}>
 */
final class Roles
{
    private ?array $roles = null;

    public function __construct(private readonly Db $db)
    {
    }

    private const LEVELS = ['administrator' => 10, 'editor' => 7, 'author' => 2, 'contributor' => 1, 'subscriber' => 0];

    /** The wp_user_level stored beside the capabilities meta. */
    public static function level(string $role): int
    {
        return self::LEVELS[$role] ?? 0;
    }

    /** The {role: true} capabilities meta in its serialized form. */
    public static function serializeSingle(string $role): string
    {
        return 'a:1:{s:' . strlen($role) . ':"' . $role . '";b:1;}';
    }

    /** Drops the parsed map so the next read sees a rewritten option. */
    public function forget(): void
    {
        $this->roles = null;
    }

    /**
     * Every role with its name and capabilities, from the option or the shipped defaults.
     *
     * @return RoleMap
     */
    public function all(): array
    {
        return $this->roles ??= self::parse($this->db->option($this->db->prefix() . 'user_roles'))
            ?? (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/roles.json'), true);
    }

    /**
     * Each role is a string key over a two-entry array of "name" and
     * "capabilities"; the capabilities map is {cap: bool}. Null when the
     * blob has another shape, so the caller can fall back.
     *
     * @return RoleMap|null
     */
    public static function parse(?string $blob): ?array
    {
        if ($blob === null || $blob === '' || !str_starts_with($blob, 'a:')) {
            return null;
        }
        $pattern = '/s:\d+:"([a-z0-9_-]+)";a:2:\{s:4:"name";s:\d+:"(.*?)";s:12:"capabilities";a:\d+:\{(.*?)\}\}/s';
        if (!preg_match_all($pattern, $blob, $matches, PREG_SET_ORDER)) {
            return null;
        }
        $roles = [];
        foreach ($matches as $role) {
            $capabilities = [];
            if (preg_match_all('/s:\d+:"([^"]+)";b:([01]);/', $role[3], $caps, PREG_SET_ORDER)) {
                foreach ($caps as $cap) {
                    $capabilities[$cap[1]] = $cap[2] === '1';
                }
            }
            $roles[$role[1]] = ['name' => $role[2], 'capabilities' => $capabilities];
        }
        return $roles === [] ? null : $roles;
    }
}
