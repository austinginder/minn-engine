<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\TermRecord;
use Minn\Content\Menus;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Refusal;
use Minn\Support\Kses;

/**
 * wp/v2/menus, menu-items, and menu-locations. Viewing needs edit_posts;
 * writes need edit_theme_options. Anonymous callers get 401.
 */
final readonly class MenusController
{
    public function __construct(
        private Menus $menus,
        private MenuObject $menuObject,
        private MenuItemObject $itemObject,
        private Caller $caller,
        private RestUrl $url,
    ) {
    }

    /** Every classic menu. */
    #[Route(Method::Get, '/wp/v2/menus', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_view', signInMessage: 'Sorry, you are not allowed to view menus.', refuse: 'rest_cannot_view', message: 'Sorry, you are not allowed to view menus.'))]
    public function listMenus(Request $request): Response
    {
        $rows = $this->menus->all();
        $post = (int) $request->query('post', '0');
        if ($post > 0) {
            $owned = [];
            foreach ($this->menus->items() as $item) {
                if ($item->id === $post) {
                    $owned[$item->menuId] = true;
                }
            }
            $rows = array_values(array_filter($rows, static fn (TermRecord $row) => isset($owned[$row->id])));
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);
        return Reply::list(
            array_map(fn (TermRecord $row) => $this->menuObject->view($row), $slice),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    /** One menu. */
    #[Route(Method::Get, '/wp/v2/menus/{id:\d+}', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_view', signInMessage: 'Sorry, you are not allowed to view menus.', refuse: 'rest_cannot_view', message: 'Sorry, you are not allowed to view menus.'))]
    public function singleMenu(Request $request, string $id): Response
    {
        $row = $this->menus->find((int) $id);
        if ($row === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        return Reply::item($this->menuObject->view($row), Fields::fromQuery($request->query));
    }

    /** Creates a menu. */
    #[Route(Method::Post, '/wp/v2/menus', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create terms in this taxonomy.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create terms in this taxonomy.'))]
    public function createMenu(Request $request): Response
    {
        $body = $request->json();
        if (!array_key_exists('name', $body)) {
            throw RestError::missingParams(['name']);
        }
        $name = $this->plain((string) $body['name']);
        $this->refuse($this->menus->refuseName($name));
        $id = $this->menus->createMenu($name, $this->plain((string) ($body['description'] ?? '')));
        $row = $this->menus->find($id);
        return Reply::item($this->menuObject->view($row ?? []), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->url->to("/wp/v2/menus/{$id}"));
    }

    /** Renames or re-describes a menu. */
    #[Route(Method::Post, '/wp/v2/menus/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this term.'))]
    #[Route(Method::Put, '/wp/v2/menus/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this term.'))]
    #[Route(Method::Patch, '/wp/v2/menus/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this term.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this term.'))]
    public function updateMenu(Request $request, string $id): Response
    {
        $row = $this->menus->find((int) $id);
        if ($row === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        $body = $request->json();
        $name = array_key_exists('name', $body) ? $this->plain((string) $body['name']) : null;
        if ($name !== null) {
            $this->refuse($this->menus->refuseName($name, (int) $id));
        }
        $this->menus->updateMenu(
            (int) $id,
            $name,
            array_key_exists('description', $body) ? $this->plain((string) $body['description']) : null,
        );
        return Reply::item($this->menuObject->view($this->menus->find((int) $id) ?? $row), Fields::fromQuery($request->query));
    }

    /** Deletes a menu and its items. */
    #[Route(Method::Delete, '/wp/v2/menus/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this term.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this term.'))]
    public function deleteMenu(Request $request, string $id): Response
    {
        $row = $this->menus->find((int) $id);
        if ($row === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (!$request->flag('force')) {
            throw new RestError('rest_trash_not_supported', "Menus do not support trashing. Set 'force=true' to delete.", 501);
        }
        $previous = $this->menuObject->view($row);
        unset($previous['_links']);
        $this->menus->deleteMenu((int) $id);
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }

    /** The items of a menu. */
    #[Route(Method::Get, '/wp/v2/menu-items', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_view', signInMessage: 'Sorry, you are not allowed to view menu items.', refuse: 'rest_cannot_view', message: 'Sorry, you are not allowed to view menu items.'))]
    public function listItems(Request $request): Response
    {
        $menuId = (int) $request->query('menus', '0');
        $items = $this->menus->items($menuId > 0 ? $menuId : null);
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);
        $context = Context::of($request);
        $edit = $context->isEdit();
        return Reply::list(
            array_map(fn ($item) => $this->itemObject->view($item, $context), $slice),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    /** One menu item. */
    #[Route(Method::Get, '/wp/v2/menu-items/{id:\d+}', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_view', signInMessage: 'Sorry, you are not allowed to view menu items.', refuse: 'rest_cannot_view', message: 'Sorry, you are not allowed to view menu items.'))]
    public function singleItem(Request $request, string $id): Response
    {
        $item = $this->menus->findItem((int) $id);
        if ($item === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        $context = Context::of($request);
        $edit = $context->isEdit();
        return Reply::item($this->itemObject->view($item, $context), Fields::fromQuery($request->query));
    }

    /** Creates a menu item. */
    #[Route(Method::Post, '/wp/v2/menu-items', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create posts as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create posts as this user.'))]
    public function createItem(Request $request): Response
    {
        $body = $request->json();
        $type = (string) ($body['type'] ?? 'custom');
        $title = $this->titleFrom($body);
        if ($type === 'custom' && $title === '') {
            throw new RestError('rest_title_required', 'The title is required when using a custom menu item type.', 400);
        }
        $id = $this->menus->createItem([
            'title' => $title,
            'url' => $this->urlFrom($body),
            'type' => $type,
            'object' => (string) ($body['object'] ?? ($type === 'custom' ? 'custom' : '')),
            'objectId' => (int) ($body['object_id'] ?? 0),
            'parent' => (int) ($body['parent'] ?? 0),
            'menuOrder' => (int) ($body['menu_order'] ?? 1),
            'target' => (string) ($body['target'] ?? ''),
            'status' => (string) ($body['status'] ?? 'publish'),
            'menuId' => (int) ($body['menus'] ?? 0),
            'attrTitle' => (string) ($body['attr_title'] ?? ''),
            'description' => (string) ($body['description'] ?? ''),
            'authorId' => $this->caller->id(),
        ]);
        $item = $this->menus->findItem($id);
        return Reply::item($this->itemObject->view($item, Context::Edit), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->url->to("/wp/v2/menu-items/{$id}"));
    }

    /** Updates a menu item. */
    #[Route(Method::Post, '/wp/v2/menu-items/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Put, '/wp/v2/menu-items/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Patch, '/wp/v2/menu-items/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_update', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_update', message: 'Sorry, you are not allowed to edit this post.'))]
    public function updateItem(Request $request, string $id): Response
    {
        $item = $this->menus->findItem((int) $id);
        if ($item === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        $body = $request->json();
        $fields = [];
        if (array_key_exists('title', $body)) {
            $fields['title'] = $this->titleFrom($body);
        }
        if (array_key_exists('url', $body)) {
            $fields['url'] = $this->urlFrom($body);
        }
        if (array_key_exists('type', $body)) {
            $fields['type'] = (string) $body['type'];
        }
        if (array_key_exists('object', $body)) {
            $fields['object'] = (string) $body['object'];
        }
        if (array_key_exists('object_id', $body)) {
            $fields['objectId'] = (int) $body['object_id'];
        }
        if (array_key_exists('parent', $body)) {
            $fields['parent'] = (int) $body['parent'];
        }
        if (array_key_exists('menu_order', $body)) {
            $fields['menuOrder'] = (int) $body['menu_order'];
        }
        if (array_key_exists('target', $body)) {
            $fields['target'] = (string) $body['target'];
        }
        if (array_key_exists('status', $body)) {
            $fields['status'] = (string) $body['status'];
        }
        if (array_key_exists('menus', $body)) {
            $fields['menuId'] = (int) $body['menus'];
        }
        if (array_key_exists('attr_title', $body)) {
            $fields['attrTitle'] = (string) $body['attr_title'];
        }
        if (array_key_exists('description', $body)) {
            $fields['description'] = (string) $body['description'];
        }
        $this->menus->updateItem((int) $id, $fields);
        return Reply::item($this->itemObject->view($this->menus->findItem((int) $id), Context::Edit), Fields::fromQuery($request->query));
    }

    /** Deletes a menu item. */
    #[Route(Method::Delete, '/wp/v2/menu-items/{id:\d+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this post.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this post.'))]
    public function deleteItem(Request $request, string $id): Response
    {
        $item = $this->menus->findItem((int) $id);
        if ($item === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        if (!$request->flag('force')) {
            throw new RestError('rest_trash_not_supported', "Menu items do not support trashing. Set 'force=true' to delete.", 501);
        }
        $previous = $this->itemObject->view($item, Context::View);
        unset($previous['_links']);
        $this->menus->deleteItem((int) $id);
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }

    /** The theme's menu locations. */
    #[Route(Method::Get, '/wp/v2/menu-locations', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_view', signInMessage: 'Sorry, you are not allowed to view menu locations.', refuse: 'rest_cannot_view', message: 'Sorry, you are not allowed to view menu locations.'))]
    public function locations(Request $request): Response
    {
        // Block themes register no classic locations; the reference returns []
        // with no pagination headers.
        return Reply::item([], Fields::fromQuery($request->query));
    }

    /** @param array<string, mixed> $body */
    private function titleFrom(array $body): string
    {
        $title = $body['title'] ?? '';
        if (is_array($title)) {
            $title = $title['raw'] ?? $title['rendered'] ?? '';
        }
        return $this->plain((string) $title);
    }

    /** @param array<string, mixed> $body */
    private function urlFrom(array $body): string
    {
        $url = (string) ($body['url'] ?? '');
        if ($url === '') {
            return '';
        }
        return $this->caller->can('unfiltered_html') ? $url : Kses::url($url);
    }

    /** A refused menu name, in the shape the reference's REST error carries. */
    private function refuse(?Refusal $refusal): void
    {
        if ($refusal === null) {
            return;
        }
        $extra = $refusal->data === null ? [] : ['term_id' => $refusal->data];
        $top = $refusal->data === null ? [] : ['additional_data' => [$refusal->data]];
        throw new RestError($refusal->code, $refusal->message, 400, $extra, $top);
    }

    private function plain(string $value): string
    {
        return $this->caller->can('unfiltered_html') ? $value : Kses::text($value);
    }
}
