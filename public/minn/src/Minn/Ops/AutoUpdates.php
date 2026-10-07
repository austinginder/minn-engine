<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;

/**
 * Whether per-item auto-updates apply to plugins or themes, as the
 * reference decides it (contracts/rest/minn-admin-v1.md "Auto-updates"):
 * never when file mods are off (DISALLOW_FILE_MODS through file_mod_allowed,
 * context automatic_updater), never when the updater is disabled
 * (AUTOMATIC_UPDATER_DISABLED through automatic_updater_disabled, which a
 * plugin may turn back), and then the type's own filter has the last word.
 * Only plugins and themes can be on.
 */
final readonly class AutoUpdates
{
    /** @param Closure(string, mixed, mixed...): mixed $filter applies a filter, as apply_filters does */
    public function __construct(private Closure $filter)
    {
    }

    /** The gate as the site's plugins see it: through their filters once the runtime's hooks exist, as configured otherwise. */
    public static function forSite(): self
    {
        return new self(function_exists('apply_filters') ? \apply_filters(...) : static fn (string $hook, mixed $value): mixed => $value);
    }

    /** Whether per-item auto-updates apply to a type ("plugin" or "theme"; anything else is never on). */
    public function enabledFor(string $type): bool
    {
        if ($type !== 'plugin' && $type !== 'theme') {
            return false;
        }
        return (bool) ($this->filter)("{$type}s_auto_update_enabled", !$this->updaterOff());
    }

    /** Whether the automatic updater is off for the whole site: file mods refused, or the updater disabled. */
    private function updaterOff(): bool
    {
        $fileMods = (bool) ($this->filter)('file_mod_allowed', !(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS), 'automatic_updater');
        return !$fileMods || (bool) ($this->filter)('automatic_updater_disabled', defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED);
    }
}
