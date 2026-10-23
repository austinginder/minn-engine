<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Links;
use Minn\Support\Lists;

/**
 * The site's links as the link functions find them (get_bookmarks): the
 * Links repository asked with the caller's arguments, each row an object,
 * through the get_bookmarks filter.
 */
final class Bookmarks
{
    private const DEFAULTS = [
        'orderby' => 'name',
        'order' => 'ASC',
        'limit' => -1,
        'category' => '',
        'category_name' => '',
        'hide_invisible' => 1,
        'show_updated' => 0,
        'include' => '',
        'exclude' => '',
        'search' => '',
    ];

    /**
     * The links asked for (get_bookmarks): a category by name narrows them to
     * that category, or to none when no link category has the name; a list
     * of links to include sets any category aside.
     *
     * @return mixed what get_bookmarks filters, a list of link objects unless a plugin says otherwise
     */
    public static function find(mixed $args = ''): mixed
    {
        $asked = Lists::args($args, self::DEFAULTS);
        if (!empty($asked['category_name'])) {
            $category = TermLookup::by('name', $asked['category_name'], 'link_category');
            if (!$category) {
                return [];
            }
            $asked['category'] = $category->term_id;
        }
        $categories = empty($asked['include']) ? array_values(array_filter(Lists::ids($asked['category']))) : [];
        $rows = (new Links(Runtime::current()->db))->matching($asked, $categories, (int) Runtime::options()->filtered('links_recently_updated_time'));
        return Runtime::hooks()->filter('get_bookmarks', [array_map(static fn (array $row): object => (object) $row, $rows), $asked]);
    }
}
