<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Site;
use Minn\Http\Outbound;
use Minn\Http\Transport;
use Minn\Json;
use Minn\Support\Serialized;

/**
 * Recovery from a fatal in someone else's code. When a plugin or theme
 * kills the boot of a request, the file it died in names it (or, when that
 * file is the engine's or core's, the first plugin or theme file the trace
 * passes through); the failure is noted, and the engine asks the site once
 * itself (check()): one request to the home page carrying nothing a visitor
 * chose. If that request fails at boot on the same extension, it is paused,
 * and the next request loads without it: the site comes back on its own
 * instead of staying down until a human reads the log. A visitor's request
 * never pauses anything by itself: a pause is a site-wide decision, and a
 * request's path, headers and their values are the visitor's to choose, so
 * an extension that fails on some input fails for that input alone.
 * Failures after boot (a route, a template) never pause anything; see
 * Failure::armRecovery().
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

    /** Whether the site is in recovery mode (wp_is_recovery_mode): never, as the engine keeps no recovery-mode session; its own recovery pauses the failing plugin instead. */
    public static function inMode(): bool
    {
        return false;
    }
    public const STRIKES_OPTION = 'minn_recovery_strikes';
    /** The engine's own check in flight: the extension it asks about, its token, when the token lapses. */
    public const CHECK_OPTION = 'minn_recovery_check';
    /** The header the engine's own check carries its token in. */
    public const CHECK_HEADER = 'x-minn-recovery-check';
    /** Seconds a strike is remembered: one check of the site per extension inside it. */
    public const WINDOW = 600;
    /** Seconds a check's token is good for: one request to the site, no longer. */
    private const CHECK_TTL = 60;

    public function __construct(private Site $site, private string $contentDir)
    {
    }

    /**
     * The extension a file belongs to: a plugin as the active `folder/file.php`
     * whose folder holds the file (or the bare file of a single-file plugin),
     * a theme as its slug. The name is the one the loader checks against the
     * paused list, so a fatal deep inside a plugin's subfolders pauses the
     * plugin and not a folder nothing is called by. Anything outside the
     * plugin and theme folders belongs to nobody, and is never paused: a
     * fatal in the engine or in core is not a plugin's fault and pausing
     * something would not fix it. A symlinked plugin reports its real
     * location; the realpath map (real folder to the folder under plugins/)
     * brings it home first.
     *
     * @param array<string, string> $realpaths
     * @param list<string>|null $active the active plugin files; read from the site when omitted
     * @return array{kind: 'plugin'|'theme', name: string}|null
     */
    public function blame(string $file, array $realpaths = [], ?array $active = null): ?array
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
            return ['kind' => 'plugin', 'name' => $this->pluginFile($parts, $active ?? $this->activePlugins())];
        }
        return null;
    }

    /**
     * The extension a failure belongs to: the file it died in, else the
     * first plugin or theme file its trace passes through, innermost first.
     * A plugin's hook that fails inside engine or core code (an engine call
     * it made, a core function) is still the plugin's failure.
     *
     * @param list<string> $trace the files of the failure's stack frames, innermost first
     * @param array<string, string> $realpaths
     * @return array{kind: 'plugin'|'theme', name: string}|null
     */
    public function blameFailure(string $file, array $trace, array $realpaths = []): ?array
    {
        // A failure in the engine's own storage or a drop-in (a full disk, a deadlock, a cache backend down) is the
        // site's state, not the plugin that happened to write at boot: only its own file is asked (security sweep
        // 2026-11-12, finding 2.2).
        $frames = self::storageFailure($file) ? [$file] : [$file, ...$trace];
        foreach ($frames as $frame) {
            $blamed = $this->blame($frame, $realpaths);
            if ($blamed !== null) {
                return $blamed;
            }
        }
        return null;
    }

    /** Whether a failure's own file is the engine's storage (database, options, transients) or a drop-in. */
    private static function storageFailure(string $file): bool
    {
        $file = str_replace('\\', '/', $file);
        foreach (['/src/Minn/Db.php', '/src/Minn/Runtime/Options.php', '/src/Minn/Runtime/OptionWrites.php', '/src/Minn/Runtime/Transients.php', '/wp-content/db.php', '/wp-content/object-cache.php', '/wp-content/advanced-cache.php'] as $storage) {
            if (str_ends_with($file, $storage)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The active plugin file a path under plugins/ belongs to: the entry in
     * the folder the path starts with, the bare file itself for a single-file
     * plugin, and the folder alone when no active entry claims it.
     *
     * @param list<string> $parts the path segments under plugins/
     * @param list<string> $active
     */
    private function pluginFile(array $parts, array $active): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }
        foreach ($active as $file) {
            if (dirname($file) === $parts[0]) {
                return $file;
            }
        }
        return $parts[0];
    }

    /** @return list<string> */
    private function activePlugins(): array
    {
        return Serialized::stringList($this->site->option('active_plugins'));
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
     * Notes a boot failure against an extension. True when one is already
     * noted inside the window, which is when the caller has asked the site
     * about it once already; the first is when it asks (check()). One time
     * is kept per extension, written only when it is new, so failing
     * requests never grow the option; it is not autoloaded.
     *
     * @param array{kind: string, name: string} $blamed
     */
    public function strike(array $blamed): bool
    {
        $now = time();
        $key = $blamed['kind'] . ':' . $blamed['name'];
        $strikes = array_filter($this->strikes(), static fn (int $at): bool => $at > $now - self::WINDOW);
        if (isset($strikes[$key])) {
            return true;
        }
        $strikes[$key] = $now;
        $this->site->setOption(self::STRIKES_OPTION, Json::encode($strikes), 'off');
        return false;
    }

    /**
     * Asks the site once about an extension a request saw fail at boot: a
     * token is kept for it, then one GET of the home page goes out carrying
     * the token and nothing a visitor chose (no body, no cookie, the headers
     * a browser sends; a fresh query steps past a page cache). If that
     * request fails at boot on the same extension, its own recovery pauses
     * it (confirmed()). The token is claimed, so of requests failing at
     * once only one asks. The address is the site's own, so its certificate
     * is not checked, as the reference sends its requests to itself (a
     * local certificate authority the server's own PHP does not know would
     * refuse it); the request carries nothing but a token good once, for a
     * minute. Null when the site answered or another request is asking,
     * else why not.
     *
     * @param array{kind: string, name: string} $blamed
     */
    public function check(array $blamed): ?string
    {
        $home = rtrim((string) ($this->site->option('home') ?? ''), '/');
        $host = (string) parse_url($home, PHP_URL_HOST);
        if ($host === '') {
            return 'the site has no home address';
        }
        $kept = Json::decode((string) ($this->site->option(self::CHECK_OPTION) ?? ''), or: null);
        if (is_array($kept) && (int) ($kept['expires'] ?? 0) < time()) {
            $this->site->deleteOption(self::CHECK_OPTION);
        }
        $token = bin2hex(random_bytes(16));
        if (!$this->site->claimOption(self::CHECK_OPTION, Json::encode(['key' => $blamed['kind'] . ':' . $blamed['name'], 'token' => $token, 'expires' => time() + self::CHECK_TTL]), 'off')) {
            return null;
        }
        $headers = [self::CHECK_HEADER . ': ' . $token, 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.9'];
        $answer = Transport::send(new Outbound('GET', $home . '/?minn-recovery-check=' . bin2hex(random_bytes(4)), $headers, null, 10.0, 0, false, 'Minn Engine recovery check'));
        return $answer->code > 0 ? null : (string) ($answer->error ?? 'no answer');
    }

    /**
     * Whether a request's token is the one check() kept for this extension
     * and still good. A matching token is spent, so it answers once; a
     * wrong one changes nothing and lapses with the minute.
     *
     * @param array{kind: string, name: string} $blamed
     */
    public function confirmed(array $blamed, string $token): bool
    {
        $kept = Json::decode((string) ($this->site->option(self::CHECK_OPTION) ?? ''), or: null);
        if (!is_array($kept) || !is_string($kept['token'] ?? null) || !hash_equals($kept['token'], $token)) {
            return false;
        }
        $this->site->deleteOption(self::CHECK_OPTION);
        return ($kept['key'] ?? null) === $blamed['kind'] . ':' . $blamed['name'] && (int) ($kept['expires'] ?? 0) >= time();
    }

    /**
     * The time each extension was first noted inside the window; a list an
     * older engine kept reads as its earliest time.
     *
     * @return array<string, int>
     */
    private function strikes(): array
    {
        $decoded = Json::decode((string) ($this->site->option(self::STRIKES_OPTION) ?? ''), or: null);
        return is_array($decoded) ? array_map(static fn ($at): int => is_array($at) ? (int) (min($at ?: [0])) : (int) $at, $decoded) : [];
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
        $this->site->setOption(self::STRIKES_OPTION, Json::encode($strikes));
    }

    /**
     * The plugins recovery mode has paused.
     *
     * @return array<string, array<string, mixed>>
     */
    public function pausedPlugins(): array
    {
        return $this->paused('plugin');
    }

    /**
     * The themes recovery mode has paused.
     *
     * @return array<string, array<string, mixed>>
     */
    public function pausedThemes(): array
    {
        return $this->paused('theme');
    }

    /**
     * The extensions of one kind ('plugin' or 'theme') recovery has paused.
     *
     * @return array<string, array<string, mixed>>
     */
    public function paused(string $kind): array
    {
        return $this->read($kind === 'theme' ? self::THEMES_OPTION : self::PLUGINS_OPTION);
    }

    /** Lets every extension of one kind load again. Returns how many were released. */
    public function resumeKind(string $kind): int
    {
        $released = 0;
        foreach (array_keys($this->paused($kind)) as $name) {
            $released += (int) $this->resume($kind, (string) $name);
        }
        return $released;
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

    /** Lets everything load again, every strike forgotten as resume() forgets one's. Returns how many were released. */
    public function resumeAll(): int
    {
        $count = count($this->read(self::PLUGINS_OPTION)) + count($this->read(self::THEMES_OPTION));
        $this->site->deleteOption(self::PLUGINS_OPTION);
        $this->site->deleteOption(self::THEMES_OPTION);
        $this->site->deleteOption(self::STRIKES_OPTION);
        $this->site->deleteOption(self::CHECK_OPTION);
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
