<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\MenuItem;

/** The wp/v2/menu-items resource. */
final readonly class MenuItemObject
{
    private const TYPE_LABEL = [
        'page' => 'Page',
        'post' => 'Post',
        'category' => 'Category',
        'post_tag' => 'Tag',
        'custom' => 'Custom Link',
    ];

    public function __construct(
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** The wp/v2 menu-item shape. */
    public function view(MenuItem $item, Context $context): array
    {
        $edit = $context->isEdit();
        $canWrite = $this->caller->can('edit_theme_options');
        $allow = $canWrite ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET'];
        $title = $edit
            ? ['raw' => $item->title, 'rendered' => $item->title]
            : ['rendered' => $item->title];
        $links = [
            'self' => [['href' => $this->url->to("/wp/v2/menu-items/{$item->id}"), 'targetHints' => ['allow' => $allow]]],
            'collection' => [['href' => $this->url->to('/wp/v2/menu-items')]],
            'about' => [['href' => $this->url->to('/wp/v2/types/nav_menu_item')]],
            'wp:term' => [['taxonomy' => 'nav_menu', 'embeddable' => true, 'href' => $this->url->to('/wp/v2/menus', ['post' => $item->id])]],
        ];
        if ($item->type === 'post_type') {
            $base = $item->object === 'page' ? 'pages' : 'posts';
            $links['wp:menu-item-object'] = [['post_type' => $item->object, 'embeddable' => true, 'href' => $this->url->to("/wp/v2/{$base}/{$item->objectId}")]];
        } elseif ($item->type === 'taxonomy') {
            $base = $item->object === 'category' ? 'categories' : 'tags';
            $links['wp:menu-item-object'] = [['taxonomy' => $item->object, 'embeddable' => true, 'href' => $this->url->to("/wp/v2/{$base}/{$item->objectId}")]];
        }
        if ($edit && $canWrite) {
            $self = $this->url->to("/wp/v2/menu-items/{$item->id}");
            $links['wp:action-publish'] = [['href' => $self]];
            $links['wp:action-unfiltered-html'] = [['href' => $self]];
            $links['wp:action-create-menus'] = [['href' => $self]];
            $links['wp:action-assign-menus'] = [['href' => $self]];
        }
        $links['curies'] = RestUrl::curies();
        $object = [
            'id' => $item->id,
            'title' => $title,
            'status' => $item->status,
            'url' => $item->url,
            'attr_title' => $item->attrTitle,
            'description' => $item->description,
            'type' => $item->type,
            'type_label' => self::TYPE_LABEL[$item->object] ?? ucfirst($item->object),
            'object' => $item->object,
            'object_id' => $item->objectId,
            'parent' => $item->parent,
            'menu_order' => $item->menuOrder,
            'target' => $item->target,
            'classes' => $item->classes,
            'xfn' => $item->xfn,
            'invalid' => $item->invalid,
            'meta' => [],
            'menus' => $item->menuId,
        ];
        if ($edit) {
            $object['minn_modified'] = false;
            $object['minn_lock'] = null;
        }
        $object['_links'] = $links;
        return $object;
    }
}
