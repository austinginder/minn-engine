<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\EngineUpdate;
use Minn\Ops\Releases;
use Minn\Support\Files;
use Throwable;


/**
 * The swap, both ways. Install parks WordPress's own files beside the
 * webroot, lays the engine and its shape files down, and leaves
 * wp-config.php and wp-content untouched; eject puts every parked file
 * back and removes what install wrote. The install record at the webroot
 * (.minn-install.php) is what eject works from. Preflight says what the site will and
 * will not get before anything moves.
 */
final class Installer
{
    /**
     * The install record sits at the webroot, so every site keeps its own
     * (a symlinked minn/ is shared between sites) and an engine update
     * cannot take it. It is PHP that returns first, so a web request for it
     * prints nothing, and it names the webroot it belongs to.
     */
    private const RECORD = '.minn-install.php';
    private const RECORD_HEAD = "<?php return; // Minn Engine's install record: what `wp minn eject` puts back. ?>\n";
    /** Where installs kept it before: read from a site's own minn/ copy, never through a shared symlink. */
    private const LEGACY_RECORD = '.install.json';
    /** The files WordPress keeps at the webroot, moved aside as a set. */
    private const CORE_ENTRIES = [
        'wp-admin', 'wp-includes', 'index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php',
        'wp-config-sample.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php',
        'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 'license.txt', 'readme.html',
    ];
    private const LAYOUT = ['index.php', 'wp-login.php', 'wp-settings.php', 'wp-cli.yml', 'wp-includes/version.php', 'wp-admin/index.php'];
    /** Webroot trees install writes (shape files + require placeholders) and eject deletes before restoring the park. */
    private const WRITTEN_TREES = ['wp-includes', 'wp-admin'];
    /**
     * Leftover webroot copies from an earlier installer that published
     * assets outside minn/. Eject still deletes them.
     */
    private const LEGACY_PUBLISHED = ['minn-admin-asset', 'minn-engine', 'wp-includes/js/jquery'];
    /** Development-only trees inside the engine or the admin bundle that never ship. */
    private const SKIP = ['.git', 'node_modules', 'tests', 'docs', '.DS_Store', self::LEGACY_RECORD];

    /** @var list<string> */
    private array $lines = [];

    private function __construct(private readonly string $engineDir)
    {
    }

    /**
     * The bin entry: runs one command against a webroot and returns the exit code.
     *
     * @param list<string> $argv
     */
    public static function main(array $argv, string $engineDir): int
    {
        $command = $argv[0] ?? 'help';
        $root = isset($argv[1]) && !str_starts_with($argv[1], '--') ? rtrim($argv[1], '/') : getcwd();
        $options = [];
        foreach ($argv as $arg) {
            if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
                $options[$m[1]] = $m[2] ?? true;
            }
        }
        $self = new self($engineDir);
        try {
            $code = match ($command) {
                'preflight' => $self->preflight($root) === 'RED' ? 1 : 0,
                'install' => $self->install($root, $options),
                'eject' => $self->eject($root),
                'status' => $self->status($root),
                'update' => $self->update($options),
                default => $self->help(),
            };
        } catch (Throwable $e) {
            $self->say('Error: ' . $e->getMessage());
            $code = 1;
        }
        fwrite(STDOUT, implode("\n", $self->lines) . "\n");
        return $code;
    }

    /** What the site will and will not get; the worst light decides install. The report lands in the output. */
    public function preflight(string $root): string
    {
        $preflight = new Preflight();
        $light = $preflight->run($root);
        foreach ($preflight->lines() as $line) {
            $this->say($line);
        }
        return $light;
    }

    private function help(): int
    {
        $this->say("Usage: minn <preflight|install|eject|status> <webroot> [--park=<dir>] [--force]");
        $this->say("       minn update [--check]   replace this engine with the latest release");
        return 0;
    }

    /** Replaces this engine with the latest release from the Minn update service; --check only says whether there is one. */
    public function update(array $options): int
    {
        $installed = EngineUpdate::versionOf($this->engineDir);
        $state = null;
        $releases = new Releases(static function () use (&$state): ?string {
            return $state;
        }, static function (string $json) use (&$state): void {
            $state = $json;
        }, $installed);
        if (!$releases->refresh()['answered']) {
            $this->say("The Minn update service did not answer, so it is not known whether a newer Minn than {$installed} is out. Try again later.");
            return 1;
        }
        $offer = $releases->offer();
        if ($offer === null) {
            $this->say("No newer Minn than {$installed} is published.");
            return 0;
        }
        $this->say("Minn {$offer->version} is available (this is {$installed}): {$offer->url}");
        if (isset($options['check'])) {
            return 0;
        }
        $this->say('Installed Minn ' . (new EngineUpdate($this->engineDir))->apply($offer) . '.');
        return 0;
    }

    /** Prints what the webroot is running. */
    public function status(string $root): int
    {
        $state = self::state($root);
        $this->say("{$root}: {$state}");
        if ($state === 'minn') {
            $manifest = self::record($root);
            $this->say('  installed ' . ($manifest['installed'] ?? '?') . ', engine ' . ($manifest['engine_version'] ?? '?') . ', parked at ' . ($manifest['park'] ?? '?'));
        }
        return 0;
    }


    /**
     * Installs the engine into a webroot when the preflight allows it.
     *
     * @param array<string, string|true> $options
     */
    public function install(string $root, array $options): int
    {
        $light = $this->preflight($root);
        if ($light === 'RED' && empty($options['force'])) {
            $this->say('Install refused (RED). Pass --force to install anyway.');
            return 1;
        }
        if (self::state($root) === 'minn') {
            $this->say('Install refused: the engine is already installed here.');
            return 1;
        }
        $park = is_string($options['park'] ?? null) ? rtrim($options['park'], '/') : dirname($root) . '/wp-parked';
        if (is_dir($park) && count(scandir($park) ?: []) > 2) {
            $this->say("Install refused: {$park} exists and is not empty. Pass --park=<dir>.");
            return 1;
        }
        if (!is_dir($park) && !mkdir($park, 0755, true)) {
            $this->say("Install refused: cannot create {$park}.");
            return 1;
        }
        $moved = [];
        foreach (self::CORE_ENTRIES as $entry) {
            if (file_exists("{$root}/{$entry}") || is_link("{$root}/{$entry}")) {
                self::move("{$root}/{$entry}", "{$park}/{$entry}");
                $moved[] = $entry;
            }
        }
        $this->say('Parked ' . count($moved) . " entries in {$park}");

        $target = "{$root}/minn";
        if (realpath($target) !== realpath($this->engineDir)) {
            self::copyTree($this->engineDir, $target);
            $this->say("Copied the engine to {$target}");
        } else {
            $this->say("Engine already at {$target}");
        }
        foreach (self::LAYOUT as $file) {
            $dest = "{$root}/{$file}";
            @mkdir(dirname($dest), 0755, true);
            copy("{$this->engineDir}/layout/{$file}", $dest);
        }
        $this->say('Wrote ' . implode(', ', self::LAYOUT));
        $placeholders = $this->writePlaceholders($root);
        $this->say("Wrote {$placeholders} require placeholders");
        file_put_contents("{$root}/" . self::RECORD, self::RECORD_HEAD . json_encode([
            'root' => realpath($root) ?: $root,
            'installed' => gmdate('c'),
            'engine_version' => self::version(),
            'park' => $park,
            'moved' => $moved,
            'layout' => self::LAYOUT,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->say('Installed. wp-config.php and wp-content were not touched; `minn eject` reverses this.');
        $this->say('Note: a PHP opcode cache may serve the old index.php or wp-settings.php for a few seconds (opcache.revalidate_freq); clear it if the host offers a way.');
        return 0;
    }

    /** Puts WordPress back and removes the engine's files. */
    public function eject(string $root): int
    {
        if (self::state($root) !== 'minn') {
            $this->say('Eject refused: the engine is not installed here.');
            return 1;
        }
        $manifest = self::record($root);
        $park = (string) ($manifest['park'] ?? '');
        if ($park === '' || !is_dir($park)) {
            $this->say("Eject refused: parked files not found at {$park}.");
            return 1;
        }
        foreach ((array) ($manifest['layout'] ?? self::LAYOUT) as $file) {
            if (str_starts_with($file, 'wp-includes/') || str_starts_with($file, 'wp-admin/')) {
                continue;
            }
            @unlink("{$root}/{$file}");
        }
        foreach (self::WRITTEN_TREES as $tree) {
            Files::deleteTree("{$root}/{$tree}");
        }
        foreach ((array) ($manifest['published'] ?? self::LEGACY_PUBLISHED) as $path) {
            Files::deleteTree("{$root}/{$path}");
        }
        $engine = "{$root}/minn";
        if (is_link($engine)) {
            unlink($engine);
        } else {
            Files::deleteTree($engine);
        }
        $restored = 0;
        foreach ((array) ($manifest['moved'] ?? []) as $entry) {
            if (file_exists("{$park}/{$entry}") || is_link("{$park}/{$entry}")) {
                self::move("{$park}/{$entry}", "{$root}/{$entry}");
                $restored++;
            }
        }
        if (count(scandir($park) ?: []) <= 2) {
            rmdir($park);
        }
        @unlink("{$root}/" . self::RECORD);
        $this->say("Ejected. Restored {$restored} entries; the engine and its shape files are gone; wp-config.php and wp-content were not touched.");
        $this->say('Note: a PHP opcode cache may serve the engine\'s wp-settings.php for a few seconds (opcache.revalidate_freq); clear it if the host offers a way.');
        return 0;
    }

    /** What a webroot is running: minn, wordpress, or unknown. */
    public static function state(string $root): string
    {
        if (is_file("{$root}/" . self::RECORD) || self::record($root) !== [] || (is_file("{$root}/minn/bootstrap.php") && !is_file("{$root}/wp-load.php"))) {
            return 'minn';
        }
        if (is_file("{$root}/wp-load.php") && is_file("{$root}/wp-includes/version.php")) {
            return 'wordpress';
        }
        return 'unknown';
    }

    /**
     * The install record for a webroot: its owner, when, the engine
     * version, the park, what moved, what was laid down. Empty when there
     * is none, or when the one found names another webroot (a site that
     * was moved or copied keeps a record its own eject must not act on).
     *
     * @return array<string, mixed>
     */
    public static function record(string $root): array
    {
        $file = "{$root}/" . self::RECORD;
        if (is_file($file)) {
            $text = (string) file_get_contents($file);
            $record = (array) json_decode(str_starts_with($text, self::RECORD_HEAD) ? substr($text, strlen(self::RECORD_HEAD)) : '', true);
            return in_array((string) ($record['root'] ?? ''), ['', realpath($root) ?: $root], true) ? $record : [];
        }
        $legacy = "{$root}/minn/" . self::LEGACY_RECORD;
        return !is_link("{$root}/minn") && is_file($legacy) ? (array) json_decode((string) file_get_contents($legacy), true) : [];
    }

    /**
     * Empty PHP files for every wp-includes/*.php and wp-admin/includes/*.php
     * the reference has, so a plugin `require ABSPATH . 'wp-admin/includes/plugin.php'`
     * resolves. The engine already provides the symbols; the file is the contract.
     */
    private function writePlaceholders(string $root): int
    {
        $list = $this->engineDir . '/data/reference-files.json';
        $files = is_file($list) ? json_decode((string) file_get_contents($list), true) : [];
        $written = 0;
        foreach (is_array($files) ? $files : [] as $file) {
            if (!is_string($file) || $file === '' || str_contains($file, '..')) {
                continue;
            }
            $path = "{$root}/{$file}";
            if (file_exists($path)) {
                continue;
            }
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, "<?php\n// Minn Engine placeholder for the reference's {$file}: the engine provides these symbols itself, so a plugin that requires this file gets nothing and continues.\n");
            $written++;
        }
        return $written;
    }

    /** rename() first; copy+remove when the park is on another filesystem. */
    private static function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }
        if (is_link($from) || !is_dir($from)) {
            if (!@copy($from, $to)) {
                throw new \RuntimeException("Cannot move {$from} to {$to}.");
            }
            unlink($from);
            return;
        }
        self::copyTree($from, $to);
        Files::deleteTree($from);
    }

    private static function copyTree(string $from, string $to): void
    {
        if (is_link($from) && !is_dir($from)) {
            copy($from, $to);
            return;
        }
        if (!is_dir($from)) {
            copy($from, $to);
            return;
        }
        @mkdir($to, 0755, true);
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::SKIP, true)) {
                continue;
            }
            self::copyTree("{$from}/{$entry}", "{$to}/{$entry}");
        }
    }

    private static function version(): string
    {
        return EngineUpdate::versionOf(dirname(__DIR__, 3));
    }

    private function say(string $line): void
    {
        $this->lines[] = $line;
    }
}
