<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Personal data as the privacy tools hand it over (probe privacy): an
 * exported item is a labelled group of name and value rows in which a row
 * without a value is left out, and an export file is old once it was last
 * written before the cut-off (the folder's index.php and hidden files are
 * never old).
 */
final class PersonalData
{
    /**
     * One exported item, its rows in the order given, a row whose value is empty left out.
     *
     * @param array<string, mixed> $rows name => value
     * @return array{group_id: string, group_label: string, group_description: string, item_id: string, data: list<array{name: string, value: mixed}>}
     */
    public static function item(string $groupId, string $groupLabel, string $groupDescription, string $itemId, array $rows): array
    {
        $data = [];
        foreach ($rows as $name => $value) {
            if ($value !== null && $value !== '' && $value !== false) {
                $data[] = ['name' => (string) $name, 'value' => $value];
            }
        }
        return ['group_id' => $groupId, 'group_label' => $groupLabel, 'group_description' => $groupDescription, 'item_id' => $itemId, 'data' => $data];
    }

    /**
     * The export files in $dir last written before $before.
     *
     * @return list<string>
     */
    public static function expiredExports(string $dir, int $before): array
    {
        $old = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
            $file = rtrim($dir, '/') . '/' . $name;
            if ($name !== 'index.php' && !str_starts_with($name, '.') && is_file($file) && (int) filemtime($file) < $before) {
                $old[] = $file;
            }
        }
        return $old;
    }
}
