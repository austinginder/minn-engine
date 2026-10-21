<?php

declare(strict_types=1);

namespace Minn\Admin;

/**
 * The admin menu's arithmetic, as plugins' menu calls see it on the
 * reference (probe admin-doubts): where a top-level entry is filed, the hook
 * a plugin page runs on, the file a plugin page's address is built on, and
 * the parent of the page being shown. The menu arrays themselves stay the
 * globals plugins read; this class only works things out from them.
 */
final class AdminMenu
{
    /** The hook a plugin page runs on: its type, "_page_", and the page with every ".php" taken out. */
    public static function hookName(string $type, string $page): string
    {
        return $type . '_page_' . str_replace('.php', '', $page);
    }

    /**
     * The key a top-level entry is filed under: null (appended) without a
     * numeric position; the position itself when it is free; a taken one
     * moved on by a fraction drawn from the slug and title, written as text.
     *
     * @param array<int|string, mixed> $menu
     */
    public static function positionKey(array $menu, mixed $position, string $slug, string $title): int|string|null
    {
        if ($position === null || !is_numeric($position)) {
            return null;
        }
        $key = (string) $position;
        if (!array_key_exists($key, $menu)) {
            return $key;
        }
        return (string) ((float) $position + hexdec(substr(md5($slug . $title), -4)) * 0.00001);
    }

    /**
     * The file a plugin page's address is built on: admin.php for a
     * top-level page, one under another plugin page or under none; the
     * parent's own file otherwise; null for a page never registered.
     *
     * @param array<string, string|false> $parents the page => parent map ($_parent_pages)
     */
    public static function pageBase(string $slug, array $parents): ?string
    {
        if (!array_key_exists($slug, $parents)) {
            return null;
        }
        $parent = (string) $parents[$slug];
        return $parent === '' || array_key_exists($parent, $parents) ? 'admin.php' : $parent;
    }

    /**
     * The parent file of the page being shown: for a plugin page, the
     * top-level entry that is the page or the submenu holding it; otherwise
     * the submenu holding the current file (with "?post_type=" and the type
     * when there is one); each mapped through the real parents, '' when
     * nothing holds it.
     *
     * @param array<int|string, mixed> $menu
     * @param array<string, mixed> $submenu
     * @param array<string, string> $realParents
     */
    public static function parentOf(array $menu, array $submenu, string $pagenow, string $pluginPage, string $typenow, array $realParents): string
    {
        $page = $typenow !== '' ? "{$pagenow}?post_type={$typenow}" : $pagenow;
        if ($pluginPage !== '') {
            foreach ($menu as $row) {
                if (is_array($row) && ($row[2] ?? null) === $pluginPage) {
                    return $realParents[$pluginPage] ?? $pluginPage;
                }
            }
            $page = $pluginPage;
        }
        foreach ($submenu as $parent => $items) {
            foreach ((array) $items as $item) {
                if (is_array($item) && ($item[2] ?? null) === $page) {
                    return $realParents[(string) $parent] ?? (string) $parent;
                }
            }
        }
        return '';
    }
}
