<?php

declare(strict_types=1);

namespace Minn\Admin;

/**
 * One screen's meta boxes as add_meta_box files them on the reference
 * (probe admin-doubts): context => priority => id => box, a removed box
 * kept as false so it stays removed.
 */
final class MetaBoxes
{
    private const PRIORITIES = ['high', 'core', 'default', 'low'];

    /**
     * The screen's boxes with one filed. The context is made first. A box
     * filed already is taken out of wherever it was, except at "core": a
     * removed box stays removed, one filed at "default" moves to "core" as
     * it was, and any other is left alone. At "sorted" the box keeps its
     * own title, callback and arguments.
     *
     * @param array<string, mixed> $boxes
     * @param array{id: string, title: mixed, callback: mixed, args: mixed} $box
     * @return array<string, mixed>
     */
    public static function add(array $boxes, string $context, string $priority, string $id, array $box): array
    {
        $boxes[$context] ??= [];
        foreach (array_keys($boxes) as $filed) {
            foreach (self::PRIORITIES as $at) {
                if (!isset($boxes[$filed][$at][$id])) {
                    continue;
                }
                $existing = $boxes[$filed][$at][$id];
                if ($priority === 'core') {
                    if ($existing !== false && $at === 'default') {
                        $boxes[$filed]['core'][$id] = $existing;
                        unset($boxes[$filed]['default'][$id]);
                    }
                    return $boxes;
                }
                if ($priority === 'sorted' && is_array($existing)) {
                    $box = ['id' => $id, 'title' => $existing['title'], 'callback' => $existing['callback'], 'args' => $existing['args']];
                }
                unset($boxes[$filed][$at][$id]);
            }
        }
        $boxes[$context][$priority][$id] = $box;
        return $boxes;
    }

    /**
     * The screen's boxes with one removed from a context: false at every
     * priority, so a later add at "core" leaves it out.
     *
     * @param array<string, mixed> $boxes
     * @return array<string, mixed>
     */
    public static function remove(array $boxes, string $context, string $id): array
    {
        foreach (self::PRIORITIES as $priority) {
            $boxes[$context][$priority][$id] = false;
        }
        return $boxes;
    }
}
