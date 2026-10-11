<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\TermRecord;
use Minn\Content\Menus;

/** The wp/v2/menus resource: a nav_menu term plus locations and auto_add. */
final readonly class MenuObject
{
    public function __construct(
        private Menus $menus,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /**
     * The wp/v2 menu shape.
     *
     * @param array<string, mixed> $term
     */
    public function view(TermRecord $term): array
    {
        $id = (int) $term['term_id'];
        $canWrite = $this->caller->can('edit_theme_options');
        $allow = $canWrite ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET'];
        $locations = $this->menus->locationsFor($id);
        // Each location the menu is assigned to, embeddable (probe rest-menus-controller).
        $located = array_map(fn (string $location): array => ['embeddable' => true, 'href' => $this->url->to('/wp/v2/menu-locations/' . $location)], $locations);
        return [
            'id' => $id,
            'description' => (string) ($term['description'] ?? ''),
            'name' => (string) $term['name'],
            'slug' => (string) $term['slug'],
            'meta' => [],
            'locations' => $locations,
            'auto_add' => $this->menus->autoAdd($id),
            '_links' => [
                'self' => [['href' => $this->url->to("/wp/v2/menus/{$id}"), 'targetHints' => ['allow' => $allow]]],
                'collection' => [['href' => $this->url->to('/wp/v2/menus')]],
                'about' => [['href' => $this->url->to('/wp/v2/taxonomies/nav_menu')]],
                'wp:post_type' => [['href' => $this->url->to('/wp/v2/menu-items', ['menus' => $id])]],
            ] + ($located === [] ? [] : ['wp:menu-location' => $located]) + [
                'curies' => RestUrl::curies(),
            ],
        ];
    }
}
