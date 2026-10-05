<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The settings-page allowlist plugins extend: option group => the option
 * names a settings form in that group may save. The engine renders no
 * settings pages, so the list is only recorded, never enforced.
 */
final class AllowedOptions
{
    /**
     * The allowlist with each new name appended to its group once, groups
     * created as needed. A group handed as anything but a list adds nothing.
     *
     * @param array<string, mixed> $add
     * @param array<string, list<string>> $into
     * @return array<string, list<string>>
     */
    public static function merge(array $add, array $into): array
    {
        foreach ($add as $group => $names) {
            if (!is_array($names)) {
                continue;
            }
            $into[$group] ??= [];
            foreach ($names as $name) {
                if (!in_array($name, $into[$group], true)) {
                    $into[$group][] = $name;
                }
            }
        }
        return $into;
    }
}
