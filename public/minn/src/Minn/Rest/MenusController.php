<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Menus;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/**
 * wp/v2/menus, menu-items, and menu-locations. Reads only; writes wait
 * for the Minn Admin menus surface. Viewing needs edit_posts (authors
 * can GET, subscribers cannot); anonymous callers get 401.
 */
final readonly class MenusController
{
    public function __construct(
        private Menus $menus,
        private MenuObject $menuObject,
        private MenuItemObject $itemObject,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/menus')]
    public function listMenus(Request $request): Response
    {
        $this->gate('menus');
        $rows = $this->menus->all();
        $post = (int) $request->query('post', '0');
        if ($post > 0) {
            $owned = [];
            foreach ($this->menus->items() as $item) {
                if ($item->id === $post) {
                    $owned[$item->menuId] = true;
                }
            }
            $rows = array_values(array_filter($rows, static fn (array $row) => isset($owned[(int) $row['term_id']])));
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);
        return Reply::list(
            array_map(fn (array $row) => $this->menuObject->view($row), $slice),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    #[Route(Method::Get, '/wp/v2/menus/{id:\d+}')]
    public function singleMenu(Request $request, string $id): Response
    {
        $this->gate('menus');
        $row = $this->menus->find((int) $id);
        if ($row === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        return Reply::item($this->menuObject->view($row), Fields::fromQuery($request->query));
    }

    #[Route(Method::Get, '/wp/v2/menu-items')]
    public function listItems(Request $request): Response
    {
        $this->gate('menu items');
        $menuId = (int) $request->query('menus', '0');
        $items = $this->menus->items($menuId > 0 ? $menuId : null);
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);
        $edit = $request->query('context') === 'edit';
        return Reply::list(
            array_map(fn ($item) => $this->itemObject->view($item, $edit), $slice),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    #[Route(Method::Get, '/wp/v2/menu-items/{id:\d+}')]
    public function singleItem(Request $request, string $id): Response
    {
        $this->gate('menu items');
        foreach ($this->menus->items() as $item) {
            if ($item->id === (int) $id) {
                $edit = $request->query('context') === 'edit';
                return Reply::item($this->itemObject->view($item, $edit), Fields::fromQuery($request->query));
            }
        }
        throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
    }

    #[Route(Method::Get, '/wp/v2/menu-locations')]
    public function locations(Request $request): Response
    {
        $this->gate('menu locations');
        // Block themes register no classic locations; the reference returns []
        // with no pagination headers.
        return Reply::item([], Fields::fromQuery($request->query));
    }

    private function gate(string $what): void
    {
        $this->caller->require('rest_cannot_view', "Sorry, you are not allowed to view {$what}.", 401);
        if (!$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_cannot_view', "Sorry, you are not allowed to view {$what}.");
        }
    }
}
