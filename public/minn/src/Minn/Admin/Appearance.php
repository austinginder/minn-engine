<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Users;
use Minn\Support\Serialized;

/**
 * A person's Minn Admin appearance: the colour scheme and its custom
 * token maps, stored where the app stores them (user meta
 * minn_admin_appearance, a serialized array) so the choice survives an
 * eject. The two WordPress-era switches, "Minn is the default admin" and
 * "Minn admin bar on the site", are always on here: there is no other
 * admin and no other bar, so the engine reports them true and ignores
 * writes to them.
 */
final readonly class Appearance
{
    public const META = 'minn_admin_appearance';

    public const SCHEMES = ['minn', 'ocean', 'forest', 'amber', 'rose', 'coral', 'teal', 'slate', 'dusk'];

    /** Scheme slots in the order the app lists them. */
    public const SLOTS = ['bg', 'bg2', 'panel', 'panel2', 'hover', 'border', 'border2', 'text', 'text2', 'text3', 'accent', 'accent2', 'accentFg'];

    /** The app's own Minn tokens, the fill for incomplete custom maps. */
    public const BASE = [
        'dark' => [
            'bg' => '#0b0b0d', 'bg2' => '#101013', 'panel' => '#151518', 'panel2' => '#1b1b1f', 'hover' => '#202027',
            'border' => '#242429', 'border2' => '#31313a', 'text' => '#ececed', 'text2' => '#9d9da7', 'text3' => '#63636d',
            'accent' => '#6e62f5', 'accent2' => '#8a80f8', 'accentFg' => '#ffffff',
        ],
        'light' => [
            'bg' => '#f6f6f7', 'bg2' => '#ffffff', 'panel' => '#ffffff', 'panel2' => '#f4f4f6', 'hover' => '#eeeef1',
            'border' => '#e7e7ea', 'border2' => '#dadade', 'text' => '#1a1a1f', 'text2' => '#5e5e69', 'text3' => '#9696a0',
            'accent' => '#6a5ef2', 'accent2' => '#5a4ef0', 'accentFg' => '#ffffff',
        ],
    ];

    public function __construct(private Users $users)
    {
    }

    /** @return array{scheme: string, custom: array, defaultAdmin: bool, frontBar: bool} */
    public function read(int $userId): array
    {
        $stored = Serialized::decode((string) ($this->users->meta($userId, self::META) ?? ''));
        return self::normalise(is_array($stored) ? $stored : []);
    }

    /** Merges the given keys over the stored map and writes it back; returns the normalised result. */
    public function save(int $userId, array $raw): array
    {
        $current = $this->read($userId);
        $merged = self::normalise([
            'scheme' => array_key_exists('scheme', $raw) ? $raw['scheme'] : $current['scheme'],
            'custom' => array_key_exists('custom', $raw) ? $raw['custom'] : $current['custom'],
        ]);
        $this->users->setMeta($userId, self::META, Serialized::encode($merged));
        return $merged;
    }

    public static function normalise(array $raw): array
    {
        $scheme = strtolower(trim((string) ($raw['scheme'] ?? 'minn')));
        if ($scheme !== 'custom' && !in_array($scheme, self::SCHEMES, true)) {
            $scheme = 'minn';
        }
        $customIn = is_array($raw['custom'] ?? null) ? $raw['custom'] : [];
        $custom = [];
        foreach (['dark', 'light'] as $mode) {
            $tokens = is_array($customIn[$mode] ?? null) ? $customIn[$mode] : [];
            foreach (self::SLOTS as $slot) {
                $hex = self::hex((string) ($tokens[$slot] ?? ''));
                $custom[$mode][$slot] = $hex === '' ? self::BASE[$mode][$slot] : $hex;
            }
        }
        return ['scheme' => $scheme, 'custom' => $custom, 'defaultAdmin' => true, 'frontBar' => true];
    }

    /** #rgb or #rrggbb to lowercase #rrggbb, or '' for anything else. */
    public static function hex(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) {
            return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }
        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : '';
    }
}
