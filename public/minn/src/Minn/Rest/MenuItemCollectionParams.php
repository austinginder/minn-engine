<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\RouteParams;

/**
 * The menu items list's parameters as the reference declares them: the
 * nav_menu_item type's post list (its menus term filter among them),
 * a hundred to a page in ascending menu order by default, menu_order
 * among the orderby values and, last, a menu_order filter of its own.
 */
final class MenuItemCollectionParams implements RouteParams
{
    private const TYPE = 'nav_menu_item';

    /** The menu items list's parameters, as the index publishes them and the router judges them. */
    public static function for(array $captures): array
    {
        return self::adjusted(PostCollectionParams::ofType(self::TYPE));
    }

    /**
     * The parameters asked afresh, as the list asks for them each time it
     * runs: rest_nav_menu_item_collection_params runs again.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function listed(): array
    {
        return self::adjusted(PostCollectionParams::listed(self::TYPE));
    }

    /** @param array<string, array<string, mixed>> $params @return array<string, array<string, mixed>> */
    private static function adjusted(array $params): array
    {
        if ($params === []) {
            return [];
        }
        $params['per_page']['default'] = 100;
        $params['order']['default'] = 'asc';
        if (isset($params['orderby'])) {
            $params['orderby']['description'] = 'Sort collection by object attribute.';
            $params['orderby']['default'] = 'menu_order';
            $params['orderby']['enum'] = array_values(array_unique([...(array) ($params['orderby']['enum'] ?? []), 'menu_order']));
        }
        unset($params['menu_order']);
        $params['menu_order'] = ['description' => 'Limit result set to posts with a specific menu_order value.', 'type' => 'integer', 'required' => false];
        return $params;
    }
}
