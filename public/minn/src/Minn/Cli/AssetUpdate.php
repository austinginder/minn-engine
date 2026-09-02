<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\Packages;
use Minn\Ops\Updates;
use Minn\Content\Inventory;
use Minn\Engine;
use Minn\RestError;
use WP_CLI;
use WP_CLI\Formatter;

/**
 * Shared wording for `wp plugin update` and `wp theme update`. Captured
 * from the reference: missing plugins warn and count as failed; a missing
 * theme is a fatal Error and stops. json/csv print only the rows (empty
 * stdout when nothing changed). `--exclude` always prints the skipped
 * line in table/summary, even when the list is empty.
 */
final class AssetUpdate
{
    public function __construct(
        private readonly string $kind,
        private readonly Updates $updates,
        private readonly Inventory $inventory,
    ) {
    }

    /** The updater for one kind of asset, with the runtime up. */
    public static function boot(string $kind): self
    {
        $runtime = Runtime::boot();
        $contentDir = rtrim(ABSPATH, '/') . '/wp-content';
        $site = $runtime->site;
        $inventory = new Inventory($contentDir, $site);
        $packages = new Packages($site, $contentDir);
        $updates = new Updates($site, $inventory, $packages, $contentDir, $runtime->permalinks->url('/'), Engine::WP_VERSION);
        return new self($kind, $updates, $inventory);
    }

    /**
     * Updates the named assets, or all of them.
     *
     * @param list<string> $names
     */
    public function run(array $names, array $assocArgs): void
    {
        $all = isset($assocArgs['all']);
        if ($names === [] && !$all) {
            WP_CLI::error("Please specify one or more {$this->kind}s, or use --all.");
        }
        $this->updates->refresh();
        $excluded = array_key_exists('exclude', $assocArgs)
            ? array_map(trim(...), explode(',', (string) $assocArgs['exclude']))
            : null;
        $exclude = $excluded ?? [];
        $format = (string) ($assocArgs['format'] ?? 'table');
        $quiet = $format === 'json' || $format === 'csv';
        $dry = isset($assocArgs['dry-run']);
        $offers = $this->offers($assocArgs);
        $targets = $all ? array_keys($offers) : $names;
        $rows = [];
        $done = 0;
        $failed = 0;
        foreach ($targets as $slug) {
            if (in_array($slug, $exclude, true)) {
                continue;
            }
            if (!$all && !isset($offers[$slug])) {
                if (!$this->installed($slug)) {
                    $failed++;
                    $this->missing($slug);
                    continue;
                }
                continue;
            }
            $offer = $offers[$slug] ?? null;
            if ($offer === null) {
                continue;
            }
            if ($dry) {
                $rows[] = $this->previewRow($offer);
                $done++;
                continue;
            }
            $row = $this->apply($offer, $quiet);
            if ($row === null) {
                $failed++;
                continue;
            }
            $rows[] = $row;
            $done++;
        }
        $total = $all ? $done + $failed : count($names);
        $this->render($rows, $assocArgs, $format, $quiet, $dry, $done, $failed, $total, $excluded);
    }

    /** @param array<string, mixed> $assocArgs @return array<string, array<string, string>> */
    private function offers(array $assocArgs): array
    {
        $out = [];
        if ($this->kind === 'plugin') {
            $versions = $this->updates->pluginVersions();
            $titles = $this->updates->pluginNames();
            $status = $this->statusByName();
            $state = $this->updates->state();
            foreach ($this->updates->pluginOffers() as $file => $new) {
                $slug = $this->pluginSlug($file);
                $from = $versions[$file] ?? '';
                if (!$this->channel($from, $new, $assocArgs)) {
                    continue;
                }
                $out[$slug] = [
                    'name' => $slug,
                    'file' => $file,
                    'title' => ($titles[$file] ?? '') !== '' ? $titles[$file] : $slug,
                    'status' => $status[$slug] ?? 'inactive',
                    'version' => $from,
                    'update_version' => $new,
                    'package' => (string) ($state['plugins'][$file]['package'] ?? ''),
                ];
            }
            return $out;
        }
        $headers = $this->updates->themeHeaders();
        $status = $this->statusByName();
        $state = $this->updates->state();
        foreach ($this->updates->themeOffers() as $slug => $new) {
            $from = $headers[$slug]['Version'] ?? '';
            if (!$this->channel($from, $new, $assocArgs)) {
                continue;
            }
            $out[$slug] = [
                'name' => $slug,
                'file' => $slug,
                'title' => $headers[$slug]['Theme Name'] !== '' ? $headers[$slug]['Theme Name'] : $slug,
                'status' => $status[$slug] ?? 'inactive',
                'version' => $from,
                'update_version' => $new,
                'package' => (string) ($state['themes'][$slug]['package'] ?? ''),
            ];
        }
        return $out;
    }

    /** @param array<string, string> $offer @return array<string, string> */
    private function previewRow(array $offer): array
    {
        return [
            'name' => $offer['name'],
            'title' => $offer['title'],
            'status' => $offer['status'],
            'version' => $offer['version'],
            'update_version' => $offer['update_version'],
        ];
    }

    /** @param array<string, string> $offer @return array<string, string>|null */
    private function apply(array $offer, bool $quiet): ?array
    {
        if (!$quiet) {
            WP_CLI::log("Downloading update from {$offer['package']}...");
            WP_CLI::log('Unpacking the update...');
            WP_CLI::log('Installing the latest version...');
            WP_CLI::log("Removing the old version of the {$this->kind}...");
        }
        try {
            $new = $this->kind === 'plugin'
                ? $this->updates->updatePlugin($offer['file'])
                : $this->updates->updateTheme($offer['name']);
        } catch (RestError $error) {
            WP_CLI::warning($offer['name'] . ': ' . $error->getMessage());
            return null;
        }
        if (!$quiet) {
            WP_CLI::log(ucfirst($this->kind) . ' updated successfully.');
        }
        return [
            'name' => $offer['name'],
            'title' => $offer['title'],
            'old_version' => $offer['version'],
            'new_version' => $new !== '' ? $new : $offer['update_version'],
            'status' => 'Updated',
        ];
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, mixed> $assocArgs
     * @param list<string>|null $exclude
     */
    private function render(
        array $rows,
        array $assocArgs,
        string $format,
        bool $quiet,
        bool $dry,
        int $done,
        int $failed,
        int $total,
        ?array $exclude,
    ): void {
        $label = $this->kind;
        if ($dry) {
            if ($rows === []) {
                if (!$quiet) {
                    WP_CLI::log("No {$label} updates available.");
                }
            } else {
                if (!$quiet) {
                    WP_CLI::log("Available {$label} updates:");
                }
                $this->emit($rows, $assocArgs, $format, true);
            }
            $this->skipped($exclude, $quiet);
            return;
        }
        if ($rows !== []) {
            $this->emit($rows, $assocArgs, $format, false);
        }
        if (!$quiet) {
            if ($failed > 0 && $done === 0) {
                WP_CLI::error("No {$label}s updated ({$failed} failed).");
            }
            if ($failed > 0) {
                WP_CLI::error("Only updated {$done} of {$total} {$label}s ({$failed} failed).");
            }
            if ($done > 0) {
                WP_CLI::success("Updated {$done} of {$total} {$label}s.");
            } else {
                WP_CLI::success(ucfirst($label) . ' already updated.');
            }
        } elseif ($failed > 0) {
            WP_CLI::error($done === 0
                ? "No {$label}s updated ({$failed} failed)."
                : "Only updated {$done} of {$total} {$label}s ({$failed} failed).");
        }
        $this->skipped($exclude, $quiet);
    }

    /** @param list<array<string, string>> $rows @param array<string, mixed> $assocArgs */
    private function emit(array $rows, array $assocArgs, string $format, bool $dry): void
    {
        if ($format === 'summary') {
            foreach ($rows as $row) {
                $title = $row['title'] ?? $this->titleFor($row['name']);
                if ($dry) {
                    WP_CLI::log("{$title} update from version {$row['version']} to version {$row['update_version']}");
                } else {
                    WP_CLI::log("{$title} updated successfully from version {$row['old_version']} to version {$row['new_version']}");
                }
            }
            return;
        }
        $fields = $dry
            ? ['name', 'status', 'version', 'update_version']
            : ['name', 'old_version', 'new_version', 'status'];
        $assocArgs['format'] = $format === '' ? 'table' : $format;
        (new Formatter($assocArgs, $fields))->display_items($rows);
    }

    /** @param list<string>|null $exclude */
    private function skipped(?array $exclude, bool $quiet): void
    {
        if ($quiet || $exclude === null) {
            return;
        }
        WP_CLI::log('Skipped updates for: ' . implode(', ', $exclude));
    }

    private function missing(string $slug): void
    {
        $message = "The '{$slug}' {$this->kind} could not be found.";
        if ($this->kind === 'theme') {
            WP_CLI::error($message);
        }
        WP_CLI::warning($message);
    }

    private function installed(string $slug): bool
    {
        if ($this->kind === 'theme') {
            return is_dir(rtrim(ABSPATH, '/') . '/wp-content/themes/' . $slug);
        }
        foreach (array_keys($this->updates->pluginVersions()) as $file) {
            if ($this->pluginSlug($file) === $slug) {
                return true;
            }
        }
        return false;
    }

    private function titleFor(string $slug): string
    {
        if ($this->kind === 'theme') {
            return $this->updates->themeHeaders()[$slug]['Theme Name'] ?? $slug;
        }
        foreach ($this->updates->pluginNames() as $file => $name) {
            if ($this->pluginSlug($file) === $slug) {
                return $name !== '' ? $name : $slug;
            }
        }
        return $slug;
    }

    /** @return array<string, string> */
    private function statusByName(): array
    {
        $out = [];
        $items = $this->kind === 'plugin' ? $this->inventory->plugins() : $this->inventory->themes();
        foreach ($items as $item) {
            $out[(string) $item['name']] = (string) $item['status'];
        }
        return $out;
    }

    /** @param array<string, mixed> $assocArgs */
    private function channel(string $from, string $to, array $assocArgs): bool
    {
        if (isset($assocArgs['patch'])) {
            return $this->samePrefix($from, $to, 2);
        }
        if (isset($assocArgs['minor'])) {
            return $this->samePrefix($from, $to, 1);
        }
        return true;
    }

    private function samePrefix(string $from, string $to, int $parts): bool
    {
        $a = explode('.', $from);
        $b = explode('.', $to);
        for ($i = 0; $i < $parts; $i++) {
            if (($a[$i] ?? '0') !== ($b[$i] ?? '0')) {
                return false;
            }
        }
        return true;
    }

    private function pluginSlug(string $file): string
    {
        return str_contains($file, '/') ? dirname($file) : basename($file, '.php');
    }
}
