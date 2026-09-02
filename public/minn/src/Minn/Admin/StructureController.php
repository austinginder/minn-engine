<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\Rest\Taxonomies;
use Minn\Rest\Types;

/**
 * The Structure view of minn-admin/v1: post types, taxonomies, and the
 * terms switcher, each with its live counts, answered from the registries.
 */
final readonly class StructureController
{
    public function __construct(
        private Db $db,
        private Types $types,
        private Taxonomies $taxonomies,
        private Caller $caller,
    ) {
    }

    /** The taxonomies of each public type, for the Structure view. */
    #[Route(Method::Get, '/minn-admin/v1/term-taxonomies', policy: new Policy(Access::Floor))]
    public function termTaxonomies(Request $request): Response
    {
        $public = $this->publicTypes();
        $out = [];
        foreach ($this->taxonomies->all() as $slug => $taxonomy) {
            if (!$taxonomy['visibility']['show_ui'] || !$this->caller->can($taxonomy['capabilities']['manage_terms'])) {
                continue;
            }
            if (array_intersect($taxonomy['types'], $public) === []) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'rest' => $taxonomy['rest_base'],
                'label' => html_entity_decode($taxonomy['labels']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'item' => strtolower(html_entity_decode($taxonomy['labels']['singular_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'hierarchical' => (bool) $taxonomy['hierarchical'],
                'canDelete' => $this->caller->can($taxonomy['capabilities']['delete_terms']),
                'canEdit' => $this->caller->can($taxonomy['capabilities']['edit_terms']),
                'count' => $this->termCount($slug),
                'types' => array_values($taxonomy['types']),
            ];
        }
        $rank = ['category' => 0, 'post_tag' => 1];
        usort($out, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['slug']] ?? 2;
            $rb = $rank[$b['slug']] ?? 2;
            return $ra !== $rb ? $ra <=> $rb : strcasecmp($a['label'], $b['label']);
        });
        return Reply::answer($request, $out);
    }

    /** The Structure view's post types: core and site-declared, with their live counts. */
    #[Route(Method::Get, '/minn-admin/v1/post-types', policy: new Policy(Access::Floor))]
    public function postTypes(Request $request): Response
    {
        $this->caller->requireCap('manage_options');
        $admin = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/types-admin.json'), true);
        $out = [];
        foreach ($this->types->all() as $slug => $type) {
            if (str_starts_with($slug, 'wp_') || in_array($slug, ['attachment', 'nav_menu_item'], true)) {
                continue;
            }
            $declared = $this->types->isDeclared($slug);
            $supports = $declared
                ? ['title', 'editor']
                : array_keys(array_filter((array) ($admin[$slug]['supports'] ?? ['title' => true, 'editor' => true])));
            $out[] = [
                'slug' => $slug,
                'plural' => (string) $type['name'],
                'singular' => (string) ($admin[$slug]['labels']['singular_name'] ?? $type['name']),
                'description' => (string) $type['description'],
                'public' => true,
                'hierarchical' => (bool) $type['hierarchical'],
                'has_archive' => (bool) $type['has_archive'],
                'show_in_rest' => true,
                'rest_base' => (string) $type['rest_base'],
                'supports' => $supports,
                // The reference registers post_format on posts; wp/v2/types hides it (not in REST), the Structure view lists it.
                'taxonomies' => array_values($slug === 'post' ? [...$type['taxonomies'], 'post_format'] : $type['taxonomies']),
                'count' => $this->postCount($slug),
                'source' => $declared ? 'minn' : 'core',
                'editable' => false,
            ];
        }
        $catalog = [];
        foreach ($this->taxonomies->all() as $slug => $taxonomy) {
            if ($taxonomy['visibility']['show_ui'] && !in_array($slug, ['nav_menu', 'wp_pattern_category', 'post_format'], true)) {
                $catalog[] = ['slug' => $slug, 'label' => html_entity_decode($taxonomy['labels']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')];
            }
        }
        return Reply::answer($request, ['types' => $out, 'backends' => [], 'taxCatalog' => $catalog]);
    }

    /** Every taxonomy with its counts. */
    #[Route(Method::Get, '/minn-admin/v1/taxonomies', policy: new Policy(Access::Floor))]
    public function taxonomies(Request $request): Response
    {
        $this->caller->requireCap('manage_options');
        $out = [];
        foreach ($this->taxonomies->all() as $slug => $taxonomy) {
            if (!$taxonomy['visibility']['show_ui'] || in_array($slug, ['nav_menu', 'wp_pattern_category', 'post_format'], true)) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'plural' => html_entity_decode($taxonomy['labels']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'singular' => html_entity_decode($taxonomy['labels']['singular_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'public' => (bool) $taxonomy['visibility']['public'],
                'hierarchical' => (bool) $taxonomy['hierarchical'],
                'show_in_rest' => true,
                'object_types' => array_values($taxonomy['types']),
                'count' => $this->termCount($slug),
                'source' => 'core',
                'editable' => false,
            ];
        }
        return Reply::answer($request, ['taxonomies' => $out, 'backends' => []]);
    }

    /** @return list<string> */
    private function publicTypes(): array
    {
        return array_values(array_filter(array_keys($this->types->all()), static fn (string $slug) => !str_starts_with($slug, 'wp_') && $slug !== 'nav_menu_item'));
    }

    private function termCount(string $taxonomy): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ?", [$taxonomy]);
    }

    private function postCount(string $type): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status IN ('publish', 'future', 'draft', 'pending', 'private')",
            [$type],
        );
    }
}
