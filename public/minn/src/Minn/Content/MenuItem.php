<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * One classic nav_menu_item, fields resolved from the post, its
 * `_menu_item_*` meta, and the object it points at.
 *
 * @param list<string> $classes
 * @param list<string> $xfn
 */
final readonly class MenuItem
{
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
        public string $type,
        public string $object,
        public int $objectId,
        public int $parent,
        public int $menuOrder,
        public string $target,
        public array $classes,
        public array $xfn,
        public string $attrTitle,
        public string $description,
        public string $status,
        public int $menuId,
        public bool $invalid,
    ) {
    }
}
