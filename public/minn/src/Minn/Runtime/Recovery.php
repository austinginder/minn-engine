<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Site;
use Minn\Support\Serialized;

/**
 * Recovery from a fatal in someone else's code. When a plugin or theme
 * kills the boot of a request, the file it died in names it; a second
 * such failure inside ten minutes records it as paused, and the next
 * request loads without it: the site comes back on its own instead of
 * staying down until a human reads the log. One failure is only noted:
 * a pause is a site-wide decision, and a single request can be a
 * crafted one. Failures after boot (a route, a template) never pause
 * anything; see Failure::armRecovery().
 *
 * The paused list is stored where WordPress stores it, in the same shape
 * (`paused_plugins` keyed by plugin file, `paused_themes` by stylesheet,
 * each holding type, file, line and message), so a site that ejects back
 * to WordPress finds the pause it left with. The strikes live in the
 * engine's own `minn_recovery_strikes` option.
 */
final readonly class Recovery
{
    public const PLUGINS_OPTION = 'paused_plugins';
    public const THEMES_OPTION = 'paused_themes';
    public const STRIKES_OPTION = 'minn_recovery_strikes';
    /** Seconds inside which a second boot failure pauses the extension. */
    public const WINDOW = 600;

    public function __construct(private Site $site, private string $contentDir)
    {
    }

    /**
     * The extension a file belongs to: a plugin as its `folder/file.php`
     * (or bare file for a single-file plugin), a theme as its slug.
     * Anything outside the plugin and theme folders belongs to nobody, and
     * is never paused: a fatal in the engine or in core is not a plugin's
     * fault and pausing something would not fix it. A symlinked plugin
     * reports its real location; the realpath map (real folder to the
     * folder under plugins/) brings it home first.
     *
     * @param array<string, string> $realpaths
     * @return array{kind: 'plugin'|'theme', name: string}|null
     */
    public function blame(string $file, array $realpaths = []): ?array
    {
        $file = str_replace('\\', '/', $file);
        foreach ($realpaths as $real => $expected) {
            $real = rtrim(str_replace('\\', '/', (string) $real), '/') . '/';
            if (str_starts_with($file, $real)) {
                $file = rtrim(str_replace('\\', '/', $expected), '/') . '/' . substr($file, strlen($real));
                break;
            }
        }
        foreach ([['plugin', '/plugins/'], ['theme', '/themes/']] as [$kind, $folder]) {
            $root = str_replace('\\', '/', rtrim($this->contentDir, '/')) . $folder;
            if (!str_starts_with($file, $root)) {
                continue;
            }
            $relative = substr($file, strlen($root));
            $parts = explode('/', $relative);
            if ($parts === [] || $parts[0] === '') {
                continue;
            }
            if ($kind === 'theme') {
                return ['kind' => 'theme', 'name' => $parts[0]];
            }
            return ['kind' => 'plugin', 'name' => count($parts) > 1 ? $parts[0] . '/' . $parts[1] : $parts[0]];
        }
        return null;
    }

    /**
     * Records an extension as paused. Returns false when it was already
     * paused, so a caller can tell a fresh failure from a repeat and only
     * notify once.
     *
     * @param array{kind: string, name: string} $blamed
     * @param array{type: int, file: string, line: int, message: string} $error
     */
    public function pause(array $blamed, array $error): bool
    {
        $option = $blamed['kind'] === 'theme' ? self::THEMES_OPTION : self::PLUGINS_OPTION;
        $paused = $this->read($option);
        $this->forgetStrikes($blamed);
        if (isset($paused[$blamed['name']])) {
            return false;
        }
        $paused[$blamed['name']] = [
            'type' => $error['type'],
            'file' => $error['file'],
            'line' => $error['line'],
            'message' => $error['message'],
        ];
        $this->write($option, $paused);
        return true;
    }

    /**
     * Notes a boot failure against an extension. True when it is the
     * second inside the window, which is when the caller pauses it; the
     * first is only remembered. Strikes older than the window are dropped
     * as they are read, so the option never grows.
     *
     * @param array{kind: string, name: string} $blamed
     */
    public function strike(array $blamed): bool
    {
        $now = time();
        $strikes = $this->strikes();
        foreach ($strikes as $key => $times) {
            $strikes[$key] = array_values(array_filter($times, static fn (int $at): bool => $at > $now - self::WINDOW));
            if ($strikes[$key] === []) {
                unset($strikes[$key]);
            }
        }
        $key = $blamed['kind'] . ':' . $blamed['name'];
        $second = isset($strikes[$key]);
        $strikes[$key][] = $now;
        $this->site->setOption(self::STRIKES_OPTION, (string) json_encode($strikes));
        return $second;
    }

    /** @return array<string, list<int>> */
    private function strikes(): array
    {
        $decoded = json_decode((string) ($this->site->option(self::STRIKES_OPTION) ?? ''), true);
        return is_array($decoded) ? array_map(static fn ($times): array => array_map('intval', (array) $times), $decoded) : [];
    }

    /** @param array{kind: string, name: string} $blamed */
    private function forgetStrikes(array $blamed): void
    {
        $strikes = $this->strikes();
        unset($strikes[$blamed['kind'] . ':' . $blamed['name']]);
        if ($strikes === []) {
            $this->site->deleteOption(self::STRIKES_OPTION);
            return;
        }
        $this->site->setOption(self::STRIKES_OPTION, (string) json_encode($strikes));
    }

    /**
     * The plugins recovery mode has paused.
     *
     * @return array<string, array<string, mixed>>
     */
    public function pausedPlugins(): array
    {
        return $this->read(self::PLUGINS_OPTION);
    }

    /**
     * The themes recovery mode has paused.
     *
     * @return array<string, array<string, mixed>>
     */
    public function pausedThemes(): array
    {
        return $this->read(self::THEMES_OPTION);
    }

    /** Lets an extension load again. Returns false when it was not paused. */
    public function resume(string $kind, string $name): bool
    {
        $option = $kind === 'theme' ? self::THEMES_OPTION : self::PLUGINS_OPTION;
        $paused = $this->read($option);
        $this->forgetStrikes(['kind' => $kind, 'name' => $name]);
        if (!isset($paused[$name])) {
            return false;
        }
        unset($paused[$name]);
        $this->write($option, $paused);
        return true;
    }

    /** Lets everything load again. Returns how many were released. */
    public function resumeAll(): int
    {
        $count = count($this->read(self::PLUGINS_OPTION)) + count($this->read(self::THEMES_OPTION));
        $this->site->deleteOption(self::PLUGINS_OPTION);
        $this->site->deleteOption(self::THEMES_OPTION);
        return $count;
    }

    /** @return array<string, array<string, mixed>> */
    private function read(string $option): array
    {
        $raw = $this->site->option($option);
        $value = $raw === null ? null : Serialized::decode($raw);
        return is_array($value) ? $value : [];
    }

    /** @param array<string, array<string, mixed>> $paused */
    private function write(string $option, array $paused): void
    {
        if ($paused === []) {
            $this->site->deleteOption($option);
            return;
        }
        $this->site->setOption($option, Serialized::encode($paused));
    }
}
