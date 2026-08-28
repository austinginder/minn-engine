<?php

declare(strict_types=1);

namespace Minn\Ext\BlockVisibility;

use Minn\Content\Reader;
use Minn\Content\Site;

/**
 * Evaluates a block's blockVisibility attribute. A block is hidden when
 * hideBlock is set, or when any enabled control set decides so: a control
 * with rule sets hides the block when its rules match and hideOnRuleSets
 * is on, or when they do not match and it is off.
 */
final readonly class Rules
{
    public function __construct(private string $device, private Reader $reader, private Site $site)
    {
    }

    public function visible(array $attrs): bool
    {
        $visibility = $attrs['blockVisibility'] ?? null;
        if (!is_array($visibility)) {
            return true;
        }
        if (!empty($visibility['hideBlock'])) {
            return false;
        }
        foreach ((array) ($visibility['controlSets'] ?? []) as $set) {
            if (!is_array($set) || empty($set['enable'])) {
                continue;
            }
            foreach ((array) ($set['controls'] ?? []) as $name => $control) {
                if (is_array($control) && !$this->controlAllows((string) $name, $control)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @return list<string> the screen-size classes the block carries */
    public function screenClasses(array $attrs): array
    {
        $classes = [];
        foreach ((array) ($attrs['blockVisibility']['controlSets'] ?? []) as $set) {
            $sizes = (array) ($set['controls']['screenSize']['hideOnScreenSize'] ?? []);
            foreach (['large', 'medium', 'small'] as $size) {
                if (!empty($sizes[$size])) {
                    $classes[] = "block-visibility-hide-{$size}-screen";
                }
            }
        }
        return array_values(array_unique($classes));
    }

    private function controlAllows(string $name, array $control): bool
    {
        return match ($name) {
            'browserDevice' => $this->ruleSetsAllow($control, fn (array $rule): ?bool => $this->deviceRule($rule)),
            'userRole' => $this->userRoleAllows($control),
            'dateTime' => $this->scheduleAllows($control),
            default => true,
        };
    }

    /** @param callable(array): ?bool $test true when a rule matches, null when it is not one this control understands */
    private function ruleSetsAllow(array $control, callable $test): bool
    {
        $matched = false;
        $any = false;
        foreach ((array) ($control['ruleSets'] ?? []) as $ruleSet) {
            if (!is_array($ruleSet) || empty($ruleSet['enable'])) {
                continue;
            }
            $all = true;
            $counted = false;
            foreach ((array) ($ruleSet['rules'] ?? []) as $rule) {
                $result = is_array($rule) ? $test($rule) : null;
                if ($result === null) {
                    continue;
                }
                $counted = true;
                $all = $all && $result;
            }
            if ($counted) {
                $any = true;
                $matched = $matched || $all;
            }
        }
        if (!$any) {
            return true;
        }
        return !empty($control['hideOnRuleSets']) ? !$matched : $matched;
    }

    private function deviceRule(array $rule): ?bool
    {
        if (($rule['field'] ?? '') !== 'deviceType') {
            return null;
        }
        $values = array_map('strval', (array) ($rule['value'] ?? []));
        $in = in_array($this->device, $values, true);
        return ($rule['operator'] ?? 'any') === 'none' ? !$in : $in;
    }

    private function userRoleAllows(array $control): bool
    {
        $mode = (string) ($control['visibilityByRole'] ?? 'public');
        $loggedIn = $this->reader->loggedIn();
        return match ($mode) {
            'logged-in' => $loggedIn,
            'logged-out' => !$loggedIn,
            'user-role' => $loggedIn && array_intersect(array_map('strval', (array) ($control['restrictedRoles'] ?? [])), $this->reader->roles) !== [],
            default => true,
        };
    }

    private function scheduleAllows(array $control): bool
    {
        $now = strtotime($this->site->localNow()) ?: time();
        foreach ((array) ($control['schedules'] ?? []) as $schedule) {
            if (!is_array($schedule) || empty($schedule['enable'])) {
                continue;
            }
            $start = isset($schedule['start']) ? strtotime((string) $schedule['start']) : false;
            $end = isset($schedule['end']) ? strtotime((string) $schedule['end']) : false;
            $inside = ($start === false || $now >= $start) && ($end === false || $now <= $end);
            if (!empty($control['hideOnSchedules']) ? $inside : !$inside) {
                return false;
            }
        }
        return true;
    }
}
