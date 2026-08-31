<?php

declare(strict_types=1);

namespace Minn\Support;

/** File-system path and permission spellings. */
final class Paths
{
    /**
     * The trailing-slashed directory holding a text domain's {domain}-{locale}.mo:
     * a custom path first, then the language dir's plugins and themes folders,
     * then the dir itself for the default domain; false when none has the file.
     */
    public static function translationDir(string $domain, string $locale, string $langDir, ?string $customPath): string|false
    {
        if ($customPath !== null && file_exists("{$customPath}/{$domain}-{$locale}.mo")) {
            return $customPath . '/';
        }
        if ($langDir === '') {
            return false;
        }
        foreach (["{$langDir}/plugins/{$domain}-{$locale}.mo", "{$langDir}/themes/{$domain}-{$locale}.mo"] as $candidate) {
            if (file_exists($candidate)) {
                return dirname($candidate) . '/';
            }
        }
        if ($domain === 'default' && file_exists("{$langDir}/{$locale}.mo")) {
            return $langDir . '/';
        }
        return false;
    }

    /** Forward slashes only, runs collapsed, a stream wrapper kept, a Windows drive letter upper-cased. */
    public static function normalize(string $path): string
    {
        $wrapper = '';
        if (preg_match('#^([a-zA-Z0-9+.-]+)://#', $path, $m) && !preg_match('/^[a-zA-Z]:/', $path)) {
            $wrapper = $m[1] . '://';
            $path = substr($path, strlen($wrapper));
        }
        $path = (string) preg_replace('|(?<=.)/+|', '/', str_replace('\\', '/', $path));
        if (preg_match('/^[a-z]:/', $path)) {
            $path = ucfirst($path);
        }
        return $wrapper . $path;
    }

    /** The `ls -l` spelling of a mode: type letter, then rwx for owner, group and world with the setuid, setgid and sticky bits folded in. */
    public static function symbolicMode(int $mode): string
    {
        $type = match (true) {
            ($mode & 0xC000) === 0xC000 => 's',
            ($mode & 0xA000) === 0xA000 => 'l',
            ($mode & 0x8000) === 0x8000 => '-',
            ($mode & 0x6000) === 0x6000 => 'b',
            ($mode & 0x4000) === 0x4000 => 'd',
            ($mode & 0x2000) === 0x2000 => 'c',
            ($mode & 0x1000) === 0x1000 => 'p',
            default => 'u',
        };
        $triplet = static function (int $read, int $write, int $execute, int $special, string $on, string $off) use ($mode): string {
            return (($mode & $read) ? 'r' : '-') . (($mode & $write) ? 'w' : '-')
                . (($mode & $execute) ? (($mode & $special) ? $on : 'x') : (($mode & $special) ? $off : '-'));
        };
        return $type
            . $triplet(0x0100, 0x0080, 0x0040, 0x0800, 's', 'S')
            . $triplet(0x0020, 0x0010, 0x0008, 0x0400, 's', 'S')
            . $triplet(0x0004, 0x0002, 0x0001, 0x0200, 't', 'T');
    }
}
