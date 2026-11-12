<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Json;
use Minn\Ops\EngineUpdate;
use Minn\Ops\Releases;
use Minn\Ops\ShapeFiles;
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
    /** The mark install leaves in the park, naming the webroot parked there; eject takes no park without it. */
    private const PARK_MARK = '.minn-park';
    /** Apache's refusal for the whole park, for a host whose park still sits under a served folder (nginx ignores it). */
    private const PARK_GUARD = '.htaccess';
    private const PARK_GUARD_TEXT = "# Minn: WordPress's own files, parked by the engine. Nothing here is served.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n";
    /** What marks a folder as a site's own webroot, and the names hosts give one. */
    private const SERVED_FILES = ['index.php', 'index.html', 'index.htm', 'wp-load.php', '.htaccess', 'web.config'];
    /**
     * Names hosts give a served folder, read on the webroot's parent and the folders above it, where the usual
     * layouts (/var/www/html, /www/site/public, ~/Sites/site/public) never carry one; www alone is left out, as the
     * unserved parent of /var/www/html.
     */
    private const SERVED_NAMES = ['public_html', 'htdocs', 'httpdocs', 'wwwroot', 'html', 'public', 'web'];
    private const RECORD_HEAD = "<?php return; // Minn Engine's install record: what `wp minn eject` puts back. ?>\n";
    /** Where installs kept it before: read from a site's own minn/ copy, never through a shared symlink. */
    private const LEGACY_RECORD = '.install.json';
    /** The files WordPress keeps at the webroot, moved aside as a set. */
    private const CORE_ENTRIES = [
        'wp-admin', 'wp-includes', 'index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php',
        'wp-config-sample.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php',
        'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 'license.txt', 'readme.html',
    ];
    /** Webroot trees install writes (shape files + require placeholders) and eject deletes before restoring the park. */
    private const WRITTEN_TREES = ['wp-includes', 'wp-admin'];
    /**
     * Leftover webroot copies from an earlier installer that published
     * assets outside minn/. Eject still deletes them.
     */
    private const LEGACY_PUBLISHED = ['minn-admin-asset', 'minn-engine', 'wp-includes/js/jquery'];
    /** Development-only trees inside the engine or the admin bundle that never ship. */
    private const SKIP = ['.git', 'node_modules', 'tests', 'docs', '.DS_Store', self::LEGACY_RECORD];
    /** The options each command reads; any other option refuses the command before it does anything. */
    private const OPTIONS = ['preflight' => [], 'install' => ['park', 'force'], 'eject' => ['park'], 'status' => [], 'update' => ['check'], 'login' => ['to', 'format']];

    /** @var list<string> */
    private array $lines = [];

    private function __construct(private readonly string $engineDir)
    {
    }

    /**
     * The bin entry: runs one command against a webroot, prints its report
     * and returns the exit code.
     *
     * @param list<string> $argv
     */
    public static function main(array $argv, string $engineDir): int
    {
        ['code' => $code, 'lines' => $lines] = self::run($argv, $engineDir);
        fwrite(STDOUT, implode("\n", $lines) . "\n");
        return $code;
    }

    /**
     * Runs one command against a webroot and hands back its exit code and
     * report, printing nothing: how Minn Admin switches a WordPress site
     * from a web request, where there is no STDOUT.
     *
     * @param list<string> $argv
     * @return array{code: int, lines: list<string>}
     */
    public static function run(array $argv, string $engineDir): array
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
        // Asking for help, or an option the command does not read, runs nothing: `minn install --help` once installed.
        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            return ['code' => $self->help(), 'lines' => $self->lines];
        }
        $unknown = isset(self::OPTIONS[$command]) ? array_diff(array_keys($options), self::OPTIONS[$command]) : [];
        if ($unknown !== []) {
            $self->say("minn {$command} does not take --" . implode(', --', $unknown) . '; nothing was changed.');
            $self->help();
            return ['code' => 1, 'lines' => $self->lines];
        }
        try {
            $code = match ($command) {
                'preflight' => $self->preflight($root) === 'RED' ? 1 : 0,
                'install' => $self->install($root, $options),
                'eject' => $self->eject($root, $options),
                'status' => $self->status($root),
                'update' => $self->update($options),
                'login' => $self->login($root, $argv, $options),
                default => $self->help(),
            };
        } catch (Throwable $e) {
            $self->say('Error: ' . $e->getMessage());
            $code = 1;
        }
        return ['code' => $code, 'lines' => $self->lines];
    }

    /** What the site will and will not get; the worst light decides install. The report lands in the output. */
    public function preflight(string $root): string
    {
        $preflight = new Preflight($this->engineDir);
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
        $this->say("       minn login <webroot> [<user>] [--to=<path>] [--format=json]   a one-time sign-in link");
        return 0;
    }

    /**
     * A one-time sign-in link (LoginLink), for the user named after the
     * webroot or the first administrator.
     *
     * @param list<string> $argv
     * @param array<string, string|true> $options
     */
    public function login(string $root, array $argv, array $options): int
    {
        $positional = array_values(array_filter(array_slice($argv, 1), static fn (string $arg): bool => !str_starts_with($arg, '--')));
        ['code' => $code, 'lines' => $lines] = LoginLink::run($root, $positional[1] ?? null, $options);
        foreach ($lines as $line) {
            $this->say($line);
        }
        return $code;
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
            // The engine running now, which an update moves on; the record keeps the one the install laid down.
            $running = is_file("{$root}/minn/bootstrap.php") ? EngineUpdate::versionOf("{$root}/minn") : '?';
            $this->say("  engine {$running}, installed " . ($manifest['installed'] ?? '?') . ' with ' . ($manifest['engine_version'] ?? '?') . ', parked at ' . ($manifest['park'] ?? '?'));
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
        // The default park sits beside the webroot. Where that folder is itself served (an addon domain inside
        // public_html), WordPress's parked core, setup-config.php and all, would answer at /wp-parked/.
        $served = is_string($options['park'] ?? null) ? null : self::served(dirname($root));
        if ($served !== null) {
            $this->say('Install refused: the default park, ' . $park . ', would sit in ' . dirname($root) . ", which looks served ({$served}), so WordPress's parked files could be reached from the web. Pass --park=<dir> naming a folder outside every site's webroot.");
            return 1;
        }
        if (is_dir($park) && count(scandir($park) ?: []) > 2) {
            $this->say("Install refused: {$park} exists and is not empty. Pass --park=<dir>.");
            return 1;
        }
        if (!is_dir($park) && !@mkdir($park, 0755, true)) {
            $this->say("Install refused: cannot create {$park}, as this user cannot write to " . dirname($park) . '. Pass --park=<dir> with a folder this user can write, outside the webroot.');
            return 1;
        }
        file_put_contents("{$park}/" . self::PARK_MARK, (realpath($root) ?: $root) . "\n");
        file_put_contents("{$park}/" . self::PARK_GUARD, self::PARK_GUARD_TEXT);
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
        $laid = ShapeFiles::lay($this->engineDir, $root);
        $this->say('Wrote ' . implode(', ', ShapeFiles::LAYOUT));
        $this->say("Wrote {$laid['entries']} entry points (wp-cron.php, wp-admin/admin-ajax.php, ...) so hosts that run only files on disk reach the engine");
        $this->say("Wrote {$laid['placeholders']} require placeholders");
        file_put_contents("{$root}/" . self::RECORD, self::RECORD_HEAD . Json::encode([
            'root' => realpath($root) ?: $root,
            'installed' => gmdate('c'),
            'engine_version' => self::version(),
            'park' => $park,
            'moved' => $moved,
            'layout' => ShapeFiles::LAYOUT,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->say('Installed. wp-config.php and wp-content were not touched; `minn eject` reverses this.');
        $this->say('The first request puts Minn Admin in wp-content/plugins/minn-admin and activates it (an older copy there is replaced, a newer one kept).');
        $this->say('Note: a PHP opcode cache may serve the old index.php or wp-settings.php for up to a minute (opcache.revalidate_freq); clear it if the host offers a way.');
        $this->say('Note: a page cache keeps serving WordPress\'s pages until purged; once the site answers as Minn, `wp cache flush` runs the host\'s purge hooks (the curl installer does this itself).');
        return 0;
    }

    /**
     * Why a folder looks served, or null: it holds a file only a webroot
     * holds, or it or a folder above it carries a host's webroot name
     * (public_html/sites/ is served under public_html's address).
     */
    private static function served(string $folder): ?string
    {
        foreach (self::SERVED_FILES as $file) {
            if (file_exists("{$folder}/{$file}")) {
                return "it holds {$file}";
            }
        }
        for ($above = $folder; $above !== dirname($above); $above = dirname($above)) {
            if (in_array(strtolower(basename($above)), self::SERVED_NAMES, true)) {
                return 'it lies in ' . $above . ', a folder hosts serve';
            }
        }
        return null;
    }

    /**
     * Guards a park an earlier engine left without it: the Apache refusal
     * written when the install record names a park carrying this webroot's
     * mark and holding none. Run once per engine version (AdminPlugin::settle),
     * so a site installed by 0.1.0 is guarded by its first request after an
     * update (fix-delta review of the 2026-11-12 sweep).
     */
    public static function guardPark(string $root): void
    {
        $park = self::recordedPark($root);
        if ($park !== null && !file_exists("{$park}/" . self::PARK_GUARD)) {
            @file_put_contents("{$park}/" . self::PARK_GUARD, self::PARK_GUARD_TEXT);
        }
    }

    /**
     * What the System view says of this webroot's park: none on record; not
     * checked (beyond open_basedir, or its mark not this webroot's as PHP
     * sees it here); in a folder that looks served; without its refusal; or
     * fine. A pass only when the park was read.
     *
     * @return array{string, string} status, text
     */
    public static function parkStatus(string $root): array
    {
        $named = rtrim((string) (self::record($root)['park'] ?? ''), '/');
        if ($named === '') {
            return ['pass', 'No park on record: minn install did not park WordPress here'];
        }
        $park = self::recordedPark($root);
        if ($park === null) {
            return ['warn', "Not checked: {$named} is out of PHP's reach here (open_basedir) or does not carry this site's mark"];
        }
        $served = self::served(dirname($park));
        if ($served !== null) {
            return ['warn', "WordPress's parked files sit in a folder that looks served ({$served}); move them outside every site's webroot (minn eject, then install with --park=<dir>)"];
        }
        return file_exists("{$park}/" . self::PARK_GUARD) ? ['pass', 'Outside every folder that looks served, and refusing web requests'] : ['warn', "The park at {$park} has no web refusal ({$park}/.htaccess); this PHP user could not write it"];
    }

    /** The park the install record names, when PHP may reach it and it is there carrying this webroot's mark. */
    private static function recordedPark(string $root): ?string
    {
        $park = rtrim((string) (self::record($root)['park'] ?? ''), '/');
        if ($park === '' || !str_starts_with($park, '/') || !\Minn\Ops\EngineUpdate::reachable($park) || !is_dir($park) || is_link($park)) {
            return null;
        }
        return trim((string) @file_get_contents("{$park}/" . self::PARK_MARK)) === (realpath($root) ?: $root) ? $park : null;
    }

    /**
     * Puts WordPress back and removes the engine's files. The park is the
     * one the install record names only when it carries the mark install
     * left there for this webroot: the record is a file in the webroot, so
     * anything able to write there could otherwise point an eject at any
     * folder the operator can read. --park=<dir> names it outright.
     *
     * @param array<string, string|true> $options
     */
    public function eject(string $root, array $options): int
    {
        if (self::state($root) !== 'minn') {
            $this->say('Eject refused: the engine is not installed here.');
            return 1;
        }
        $manifest = self::record($root);
        $named = is_string($options['park'] ?? null);
        $park = rtrim($named ? (string) $options['park'] : (string) ($manifest['park'] ?? ''), '/');
        if ($park === '' || !is_dir($park) || is_link($park)) {
            $this->say("Eject refused: parked files not found at {$park}.");
            return 1;
        }
        if (!$named && trim((string) @file_get_contents("{$park}/" . self::PARK_MARK)) !== (realpath($root) ?: $root)) {
            $this->say("Eject refused: {$park} does not carry this install's mark (" . self::PARK_MARK . '). Pass --park=<dir> naming the folder WordPress was parked in.');
            return 1;
        }
        foreach ((array) ($manifest['layout'] ?? ShapeFiles::LAYOUT) as $file) {
            if (!self::inside((string) $file) || str_starts_with($file, 'wp-includes/') || str_starts_with($file, 'wp-admin/')) {
                continue;
            }
            @unlink("{$root}/{$file}");
        }
        foreach (ShapeFiles::laidEntries($this->engineDir, $root) as $file) {
            unlink("{$root}/{$file}");
        }
        foreach (self::WRITTEN_TREES as $tree) {
            Files::deleteTree("{$root}/{$tree}");
        }
        foreach ((array) ($manifest['published'] ?? self::LEGACY_PUBLISHED) as $path) {
            if (self::inside((string) $path)) {
                Files::deleteTree("{$root}/{$path}");
            }
        }
        $engine = "{$root}/minn";
        if (is_link($engine)) {
            unlink($engine);
        } else {
            Files::deleteTree($engine);
        }
        $restored = 0;
        foreach ((array) ($manifest['moved'] ?? []) as $entry) {
            if (self::inside((string) $entry) && (file_exists("{$park}/{$entry}") || is_link("{$park}/{$entry}"))) {
                self::move("{$park}/{$entry}", "{$root}/{$entry}");
                $restored++;
            }
        }
        @unlink("{$park}/" . self::PARK_MARK);
        if (@file_get_contents("{$park}/" . self::PARK_GUARD) === self::PARK_GUARD_TEXT) {
            @unlink("{$park}/" . self::PARK_GUARD);
        }
        if (count(scandir($park) ?: []) <= 2 && !@rmdir($park)) {
            $this->say("Left the empty {$park}: this user cannot remove it.");
        }
        @unlink("{$root}/" . self::RECORD);
        $this->say("Ejected. Restored {$restored} entries; the engine and its shape files are gone; wp-config.php and wp-content were not touched.");
        $this->say('Minn Admin stays in wp-content/plugins/minn-admin, so WordPress keeps it.');
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
            $record = (array) Json::decode(str_starts_with($text, self::RECORD_HEAD) ? substr($text, strlen(self::RECORD_HEAD)) : '', or: null);
            return in_array((string) ($record['root'] ?? ''), ['', realpath($root) ?: $root], true) ? $record : [];
        }
        $legacy = "{$root}/minn/" . self::LEGACY_RECORD;
        return !is_link("{$root}/minn") && is_file($legacy) ? (array) Json::file($legacy, or: null) : [];
    }

    /**
     * Whether a path from the install record stays inside the webroot (or
     * the park): relative, with no climbing segment and no NUL. The record
     * is a file in the webroot, so anything able to write there could
     * otherwise point an eject's removals anywhere the operator can write.
     */
    private static function inside(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        return !in_array('..', explode('/', $path), true);
    }

    /** rename() first; copy+remove when the park is on another filesystem, a link copied as the link it is. */
    private static function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }
        if (is_link($from)) {
            if (!@symlink((string) readlink($from), $to)) {
                throw new \RuntimeException("Cannot move {$from} to {$to}.");
            }
            unlink($from);
            return;
        }
        if (!is_dir($from)) {
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
        if (is_link($from)) {
            symlink((string) readlink($from), $to);
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
