<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Post types, taxonomies, and statuses as plugin code registers and reads
 * them. The built-in set is data/registry.json, captured from the
 * reference; registrations derive their defaults the way the content
 * probe observed (contracts/fixtures/api/content.json).
 */
final class Registry
{
    /** @var array<string, array<string, mixed>> */
    private array $postTypes;
    /** @var array<string, array<string, mixed>> */
    private array $taxonomies;
    /** @var array<string, array<string, mixed>> */
    private array $statuses;
    /** @var array<string, mixed> */
    public readonly array $queryVars;
    /** @var list<string> */
    public readonly array $publicQueryVars;

    /** @var array<string, array<string, mixed>> features declared for types not yet registered */
    private array $pendingSupports = [];

    public function __construct(string $engineDir)
    {
        $data = json_decode((string) file_get_contents($engineDir . '/data/registry.json'), true) ?: [];
        $this->postTypes = $data['post_types'] ?? [];
        foreach ($this->postTypes as &$type) {
            if (isset($type['supports']) && array_is_list($type['supports'])) {
                $type['supports'] = array_fill_keys($type['supports'], true);
            }
        }
        unset($type);
        $this->taxonomies = $data['taxonomies'] ?? [];
        $this->statuses = $data['statuses'] ?? [];
        $this->queryVars = $data['query_vars'] ?? [];
        $this->publicQueryVars = $data['public_query_vars'] ?? [];
    }

    /**
     * Every post type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function postTypes(): array
    {
        return $this->postTypes;
    }

    /** One post type, or null. */
    public function postType(string $name): ?array
    {
        return $this->postTypes[$name] ?? null;
    }

    /**
     * Every taxonomy.
     *
     * @return array<string, array<string, mixed>>
     */
    public function taxonomies(): array
    {
        return $this->taxonomies;
    }

    /** One taxonomy, or null. */
    public function taxonomy(string $name): ?array
    {
        return $this->taxonomies[$name] ?? null;
    }

    /**
     * Every post status.
     *
     * @return array<string, array<string, mixed>>
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /** One post status, or null. */
    public function status(string $name): ?array
    {
        return $this->statuses[$name] ?? null;
    }

    /**
     * Registers a post type with the reference's defaults filled in.
     *
     * @param array<string, mixed> $args
     */
    public function registerPostType(string $name, array $args): array
    {
        $post = $this->postTypes['post'];
        $label = (string) ($args['label'] ?? ($args['labels']['name'] ?? $name));
        $labels = $post['labels'];
        $labels['name'] = $label;
        $labels['singular_name'] = (string) ($args['labels']['singular_name'] ?? $label);
        $labels['menu_name'] = (string) ($args['labels']['menu_name'] ?? $label);
        $labels['name_admin_bar'] = (string) ($args['labels']['name_admin_bar'] ?? $labels['singular_name']);
        $labels['all_items'] = (string) ($args['labels']['all_items'] ?? $labels['menu_name']);
        $labels['archives'] = (string) ($args['labels']['archives'] ?? $labels['all_items']);
        foreach ((array) ($args['labels'] ?? []) as $key => $value) {
            $labels[$key] = $value;
        }
        $public = (bool) ($args['public'] ?? false);
        $capabilityType = $args['capability_type'] ?? 'post';
        $hasArchive = $args['has_archive'] ?? false;
        $publiclyQueryable = (bool) ($args['publicly_queryable'] ?? $public);
        $showUi = (bool) ($args['show_ui'] ?? $public);
        $showInMenu = $args['show_in_menu'] ?? $showUi;
        // A rewrite settles into its full form, public or not; feeds only where there is an archive (probe registry-rewrites).
        $rewrite = $args['rewrite'] ?? true;
        if ($rewrite !== false && self::settlesRewrites()) {
            $rewrite = is_array($rewrite) ? $rewrite : [];
            $rewrite += ['slug' => $name, 'with_front' => true, 'pages' => true, 'feeds' => (bool) $hasArchive, 'ep_mask' => 1];
            if (!$hasArchive) {
                $rewrite['feeds'] = false;
            }
        }
        $type = [
            'name' => $name,
            'label' => $label,
            'labels' => $labels,
            'description' => (string) ($args['description'] ?? ''),
            'public' => $public,
            'hierarchical' => (bool) ($args['hierarchical'] ?? false),
            'exclude_from_search' => (bool) ($args['exclude_from_search'] ?? !$public),
            'publicly_queryable' => $publiclyQueryable,
            'embeddable' => (bool) ($args['embeddable'] ?? $publiclyQueryable),
            'show_ui' => $showUi,
            'show_in_menu' => $showInMenu,
            'show_in_nav_menus' => (bool) ($args['show_in_nav_menus'] ?? $public),
            'show_in_admin_bar' => (bool) ($args['show_in_admin_bar'] ?? (bool) $showInMenu),
            'menu_position' => $args['menu_position'] ?? null,
            'menu_icon' => $args['menu_icon'] ?? null,
            'capability_type' => $capabilityType,
            'map_meta_cap' => (bool) ($args['map_meta_cap'] ?? true),
            'taxonomies' => array_values(array_map('strval', (array) ($args['taxonomies'] ?? []))),
            'has_archive' => $hasArchive,
            'query_var' => $args['query_var'] ?? true,
            'can_export' => (bool) ($args['can_export'] ?? true),
            'delete_with_user' => $args['delete_with_user'] ?? null,
            'template' => (array) ($args['template'] ?? []),
            'template_lock' => $args['template_lock'] ?? false,
            '_builtin' => false,
            '_edit_link' => (string) ($args['_edit_link'] ?? 'post.php?post=%d'),
            'cap' => self::capabilities($capabilityType, (array) ($args['capabilities'] ?? []), (bool) ($args['map_meta_cap'] ?? true)),
            'rewrite' => $rewrite,
            'show_in_rest' => (bool) ($args['show_in_rest'] ?? false),
            'rest_base' => $args['rest_base'] ?? false,
            'rest_namespace' => $args['rest_namespace'] ?? 'wp/v2',
            'rest_controller_class' => $args['rest_controller_class'] ?? false,
            'revisions_rest_controller_class' => $args['revisions_rest_controller_class'] ?? false,
            'autosave_rest_controller_class' => $args['autosave_rest_controller_class'] ?? false,
            'late_route_registration' => (bool) ($args['late_route_registration'] ?? false),
            'supports' => array_key_exists('supports', $args) ? self::supportsFrom((array) $args['supports']) : ['title' => true, 'editor' => true],
        ];
        if ($type['query_var'] === true) {
            $type['query_var'] = $name;
        }
        $type['supports'] += $this->pendingSupports[$name] ?? [];
        unset($this->pendingSupports[$name]);
        $this->postTypes[$name] = $type;
        foreach ($type['taxonomies'] as $taxonomy) {
            $this->addObjectType($taxonomy, $name);
        }
        return $type;
    }

    /** @return array<string, mixed> feature => true or the arguments given */
    private static function supportsFrom(array $supports): array
    {
        $out = [];
        foreach ($supports as $key => $value) {
            if (is_array($value)) {
                $out[(string) $key] = [$value];
            } else {
                $out[(string) $value] = true;
            }
        }
        return $out;
    }

    /** @param array<string, string> $overrides */
    private static function capabilities(string|array $capabilityType, array $overrides, bool $mapMetaCap): array
    {
        [$singular, $plural] = is_array($capabilityType) ? [$capabilityType[0], $capabilityType[1] ?? $capabilityType[0] . 's'] : [$capabilityType, $capabilityType . 's'];
        $caps = [
            'edit_post' => "edit_{$singular}",
            'read_post' => "read_{$singular}",
            'delete_post' => "delete_{$singular}",
            'edit_posts' => "edit_{$plural}",
            'edit_others_posts' => "edit_others_{$plural}",
            'delete_posts' => "delete_{$plural}",
            'publish_posts' => "publish_{$plural}",
            'read_private_posts' => "read_private_{$plural}",
        ];
        if ($mapMetaCap) {
            $caps += [
                'read' => 'read',
                'delete_private_posts' => "delete_private_{$plural}",
                'delete_published_posts' => "delete_published_{$plural}",
                'delete_others_posts' => "delete_others_{$plural}",
                'edit_private_posts' => "edit_private_{$plural}",
                'edit_published_posts' => "edit_published_{$plural}",
            ];
        }
        $caps['create_posts'] = $overrides['create_posts'] ?? $caps['edit_posts'];
        foreach ($overrides as $key => $value) {
            $caps[$key] = $value;
        }
        return $caps;
    }

    /** Forgets a non-builtin post type. */
    public function unregisterPostType(string $name): bool
    {
        if (!isset($this->postTypes[$name]) || !empty($this->postTypes[$name]['_builtin'])) {
            return false;
        }
        unset($this->postTypes[$name]);
        foreach ($this->taxonomies as &$taxonomy) {
            $taxonomy['object_type'] = array_values(array_diff($taxonomy['object_type'], [$name]));
        }
        return true;
    }

    /**
     * Support declared before the type registers waits aside (the reference
     * keeps features apart from the type objects, so declaring one does not
     * make the type exist) and merges in when register_post_type arrives.
     */
    public function addSupport(string $type, string $feature, array $args): void
    {
        $value = $args === [] ? true : [$args[0] ?? $args];
        if (!isset($this->postTypes[$type])) {
            $this->pendingSupports[$type][$feature] = $value;
            return;
        }
        $this->postTypes[$type]['supports'][$feature] = $value;
    }

    /** Removes a feature from a post type. */
    public function removeSupport(string $type, string $feature): void
    {
        unset($this->postTypes[$type]['supports'][$feature], $this->pendingSupports[$type][$feature]);
    }

    /**
     * The features a post type supports.
     *
     * @return array<string, mixed> the features a type supports, registered or declared ahead
     */
    public function supports(string $type): array
    {
        return ($this->postTypes[$type]['supports'] ?? []) + ($this->pendingSupports[$type] ?? []);
    }

    /**
     * Registers a taxonomy with the reference's defaults filled in.
     *
     * @param list<string> $objectTypes @param array<string, mixed> $args
     */
    public function registerTaxonomy(string $name, array $objectTypes, array $args): array
    {
        $hierarchical = (bool) ($args['hierarchical'] ?? false);
        $base = $this->taxonomies[$hierarchical ? 'category' : 'post_tag'];
        $label = (string) ($args['label'] ?? ($args['labels']['name'] ?? $name));
        $labels = $base['labels'];
        $labels['name'] = $label;
        $labels['singular_name'] = (string) ($args['labels']['singular_name'] ?? $label);
        $labels['menu_name'] = (string) ($args['labels']['menu_name'] ?? $label);
        $labels['all_items'] = (string) ($args['labels']['all_items'] ?? $label);
        $labels['name_admin_bar'] = $labels['singular_name'];
        foreach ((array) ($args['labels'] ?? []) as $key => $value) {
            $labels[$key] = $value;
        }
        $public = (bool) ($args['public'] ?? true);
        $publiclyQueryable = (bool) ($args['publicly_queryable'] ?? $public);
        $showUi = (bool) ($args['show_ui'] ?? $public);
        // Settled whether queryable or not, the given keys merged over the defaults; a rewrite of true leaves an
        // empty "0" entry behind, as the reference's merge does (probe registry-rewrites).
        $rewrite = $args['rewrite'] ?? true;
        if ($rewrite !== false && self::settlesRewrites()) {
            $rewrite = array_merge(['with_front' => true, 'hierarchical' => false, 'ep_mask' => 0], is_array($rewrite) ? $rewrite : ['']);
            $rewrite['slug'] ??= $name;
        }
        $caps = ['manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'edit_posts'];
        foreach ((array) ($args['capabilities'] ?? []) as $key => $value) {
            $caps[$key] = $value;
        }
        $taxonomy = [
            'name' => $name,
            'label' => $label,
            'labels' => $labels,
            'description' => (string) ($args['description'] ?? ''),
            'public' => $public,
            'publicly_queryable' => $publiclyQueryable,
            'hierarchical' => $hierarchical,
            'show_ui' => $showUi,
            'show_in_menu' => (bool) ($args['show_in_menu'] ?? $showUi),
            'show_in_nav_menus' => (bool) ($args['show_in_nav_menus'] ?? $public),
            'show_tagcloud' => (bool) ($args['show_tagcloud'] ?? $showUi),
            'show_in_quick_edit' => (bool) ($args['show_in_quick_edit'] ?? $showUi),
            'show_admin_column' => (bool) ($args['show_admin_column'] ?? false),
            'object_type' => array_values(array_map('strval', $objectTypes)),
            'cap' => $caps,
            'rewrite' => $rewrite,
            'query_var' => $publiclyQueryable ? ($args['query_var'] ?? true) : false,
            'show_in_rest' => (bool) ($args['show_in_rest'] ?? false),
            'rest_base' => $args['rest_base'] ?? false,
            'rest_namespace' => $args['rest_namespace'] ?? 'wp/v2',
            'rest_controller_class' => $args['rest_controller_class'] ?? false,
            'default_term' => $args['default_term'] ?? null,
            'sort' => $args['sort'] ?? null,
            'args' => $args['args'] ?? null,
            '_builtin' => false,
        ];
        if ($taxonomy['query_var'] === true) {
            $taxonomy['query_var'] = $name;
        }
        $this->taxonomies[$name] = $taxonomy;
        return $taxonomy;
    }

    /** Whether a registered rewrite settles into its full form: with pretty permalinks or in the admin; otherwise it stays as given. */
    public static function settlesRewrites(): bool
    {
        return !Runtime::booted() || !\function_exists('get_option') || \is_admin() || (string) \get_option('permalink_structure') !== '';
    }

    /** Forgets a non-builtin taxonomy. */
    public function unregisterTaxonomy(string $name): bool
    {
        if (!isset($this->taxonomies[$name]) || !empty($this->taxonomies[$name]['_builtin'])) {
            return false;
        }
        unset($this->taxonomies[$name]);
        return true;
    }

    /** Attaches a taxonomy to a post type. */
    public function addObjectType(string $taxonomy, string $type): bool
    {
        if (!isset($this->taxonomies[$taxonomy])) {
            return false;
        }
        if (!in_array($type, $this->taxonomies[$taxonomy]['object_type'], true)) {
            $this->taxonomies[$taxonomy]['object_type'][] = $type;
        }
        return true;
    }

    /** Detaches a taxonomy from a post type. */
    public function removeObjectType(string $taxonomy, string $type): bool
    {
        if (!isset($this->taxonomies[$taxonomy]) || !in_array($type, $this->taxonomies[$taxonomy]['object_type'], true)) {
            return false;
        }
        $this->taxonomies[$taxonomy]['object_type'] = array_values(array_diff($this->taxonomies[$taxonomy]['object_type'], [$type]));
        return true;
    }

    /**
     * Registers a post status.
     *
     * @param array<string, mixed> $args
     */
    public function registerStatus(string $name, array $args): array
    {
        $label = (string) ($args['label'] ?? $name);
        $public = (bool) ($args['public'] ?? false);
        $internal = (bool) ($args['internal'] ?? false);
        $status = [
            'label' => $label,
            'label_count' => $args['label_count'] ?? ['0' => $label . ' <span class="count">(%s)</span>', '1' => $label . ' <span class="count">(%s)</span>', 'singular' => $label . ' <span class="count">(%s)</span>', 'plural' => $label . ' <span class="count">(%s)</span>', 'context' => null, 'domain' => null],
            'exclude_from_search' => (bool) ($args['exclude_from_search'] ?? $internal),
            '_builtin' => false,
            'public' => $public,
            'internal' => $internal,
            'protected' => (bool) ($args['protected'] ?? false),
            'private' => (bool) ($args['private'] ?? false),
            'publicly_queryable' => (bool) ($args['publicly_queryable'] ?? $public),
            'show_in_admin_status_list' => (bool) ($args['show_in_admin_status_list'] ?? !$internal),
            'show_in_admin_all_list' => (bool) ($args['show_in_admin_all_list'] ?? !$internal),
            'date_floating' => (bool) ($args['date_floating'] ?? false),
            'name' => $name,
        ];
        $this->statuses[$name] = $status;
        return $status;
    }
}
