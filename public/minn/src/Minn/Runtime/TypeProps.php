<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A post type's or taxonomy's properties from the arguments it was built
 * with, as WP_Post_Type::set_props and WP_Taxonomy::set_props fill them
 * (probe type-object-methods): the reference's defaults where an argument
 * is missing, a given value kept as given.
 */
final class TypeProps
{
    /**
     * A post type's properties from its arguments (probe type-object-methods):
     * the reference's defaults where an argument is missing, a given value
     * kept as given. The labels are the type's own; get_post_type_labels
     * makes them whole on the object.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function postType(string $name, array $args): array
    {
        $public = $args['public'] ?? false;
        $capabilityType = $args['capability_type'] ?? 'post';
        // Meta capabilities are mapped by default only for the post and page families (probe rest-plugin-caps).
        $mapMetaCap = $args['map_meta_cap'] ?? in_array($capabilityType, ['post', 'page'], true);
        $hasArchive = $args['has_archive'] ?? false;
        $publiclyQueryable = $args['publicly_queryable'] ?? $public;
        $showUi = $args['show_ui'] ?? $public;
        $showInMenu = $args['show_in_menu'] ?? $showUi;
        $showInRest = $args['show_in_rest'] ?? false;
        $queryVar = $args['query_var'] ?? true;
        return [
            // With no label of its own a type takes the post (or page) labels.
            'label' => $args['label'] ?? null,
            'labels' => (array) ($args['labels'] ?? []),
            'description' => $args['description'] ?? '',
            'public' => $public,
            'hierarchical' => $args['hierarchical'] ?? false,
            'exclude_from_search' => $args['exclude_from_search'] ?? !$public,
            'publicly_queryable' => $publiclyQueryable,
            'embeddable' => $args['embeddable'] ?? $publiclyQueryable,
            'show_ui' => $showUi,
            'show_in_menu' => $showInMenu,
            'show_in_nav_menus' => $args['show_in_nav_menus'] ?? $public,
            'show_in_admin_bar' => $args['show_in_admin_bar'] ?? (bool) $showInMenu,
            'menu_position' => $args['menu_position'] ?? null,
            'menu_icon' => $args['menu_icon'] ?? null,
            // A singular and plural pair is kept as its singular; the capabilities use both.
            'capability_type' => is_array($capabilityType) ? ($capabilityType[0] ?? 'post') : $capabilityType,
            'map_meta_cap' => $mapMetaCap,
            'register_meta_box_cb' => $args['register_meta_box_cb'] ?? null,
            'taxonomies' => $args['taxonomies'] ?? [],
            'has_archive' => $hasArchive,
            'query_var' => $queryVar === true ? $name : $queryVar,
            'can_export' => $args['can_export'] ?? true,
            'delete_with_user' => $args['delete_with_user'] ?? null,
            'template' => $args['template'] ?? [],
            'template_lock' => $args['template_lock'] ?? false,
            '_builtin' => $args['_builtin'] ?? false,
            // A type kept out of the admin has no edit screen to link to.
            '_edit_link' => $args['_edit_link'] ?? ($showUi ? 'post.php?post=%d' : ''),
            'cap' => Registry::capabilities($capabilityType, (array) ($args['capabilities'] ?? []), (bool) $mapMetaCap),
            'rewrite' => self::typeRewrite($name, $args['rewrite'] ?? true, $hasArchive),
            'supports' => $args['supports'] ?? [],
            'show_in_rest' => $showInRest,
            'rest_base' => $args['rest_base'] ?? false,
            // A type hidden from REST has no namespace unless it names one (probe rest-registry-controllers).
            'rest_namespace' => $args['rest_namespace'] ?? ($showInRest ? 'wp/v2' : false),
            'rest_controller_class' => $args['rest_controller_class'] ?? false,
            'revisions_rest_controller_class' => $args['revisions_rest_controller_class'] ?? false,
            'autosave_rest_controller_class' => $args['autosave_rest_controller_class'] ?? false,
            'late_route_registration' => $args['late_route_registration'] ?? false,
        ];
    }

    /** A type's rewrite settles into its full form, public or not; feeds only where there is an archive (probe registry-rewrites). */
    private static function typeRewrite(string $name, mixed $rewrite, mixed $hasArchive): mixed
    {
        if ($rewrite === false || !Registry::settlesRewrites()) {
            return $rewrite;
        }
        $rewrite = (is_array($rewrite) ? $rewrite : []) + ['slug' => $name, 'with_front' => true, 'pages' => true, 'feeds' => (bool) $hasArchive, 'ep_mask' => 1];
        if (!$hasArchive) {
            $rewrite['feeds'] = false;
        }
        return $rewrite;
    }

    /**
     * A taxonomy's properties from the types it belongs to and its
     * arguments (probe type-object-methods): the reference's defaults where
     * an argument is missing, a given value kept as given. The meta box is
     * the category checklist for a hierarchical taxonomy and the tag box for
     * any other; its input is read as checkboxes for the checklist and as
     * text otherwise. A default term gets a name, slug and description.
     *
     * @param list<mixed> $objectTypes
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function taxonomy(string $name, array $objectTypes, array $args): array
    {
        $hierarchical = $args['hierarchical'] ?? false;
        $public = $args['public'] ?? true;
        $publiclyQueryable = $args['publicly_queryable'] ?? $public;
        $showUi = $args['show_ui'] ?? $public;
        $showInRest = $args['show_in_rest'] ?? false;
        // Only false keeps the query var out: a given "0" still has one.
        $queryVar = $publiclyQueryable !== false ? ($args['query_var'] ?? true) : false;
        $metaBox = $args['meta_box_cb'] ?? ($hierarchical ? 'post_categories_meta_box' : 'post_tags_meta_box');
        $defaultTerm = $args['default_term'] ?? null;
        return [
            'label' => isset($args['label']) ? (string) $args['label'] : null,
            'labels' => (array) ($args['labels'] ?? []),
            'description' => $args['description'] ?? '',
            'public' => $public,
            'publicly_queryable' => $publiclyQueryable,
            'hierarchical' => $hierarchical,
            'show_ui' => $showUi,
            'show_in_menu' => $args['show_in_menu'] ?? $showUi,
            'show_in_nav_menus' => $args['show_in_nav_menus'] ?? $public,
            'show_tagcloud' => $args['show_tagcloud'] ?? $showUi,
            'show_in_quick_edit' => $args['show_in_quick_edit'] ?? $showUi,
            'show_admin_column' => $args['show_admin_column'] ?? false,
            'meta_box_cb' => $metaBox,
            'meta_box_sanitize_cb' => $args['meta_box_sanitize_cb'] ?? ($metaBox === 'post_categories_meta_box' ? 'taxonomy_meta_box_sanitize_cb_checkboxes' : 'taxonomy_meta_box_sanitize_cb_input'),
            'object_type' => $objectTypes,
            'cap' => array_replace(['manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'edit_posts'], (array) ($args['capabilities'] ?? [])),
            'rewrite' => self::taxonomyRewrite($name, $args['rewrite'] ?? true),
            'query_var' => $queryVar === true ? $name : $queryVar,
            'update_count_callback' => $args['update_count_callback'] ?? '',
            'show_in_rest' => $showInRest,
            'rest_base' => $args['rest_base'] ?? false,
            // A taxonomy hidden from REST has no namespace unless it names one (probe rest-registry-controllers).
            'rest_namespace' => $args['rest_namespace'] ?? ($showInRest ? 'wp/v2' : false),
            'rest_controller_class' => $args['rest_controller_class'] ?? false,
            'default_term' => $defaultTerm === null ? null : array_merge(['name' => '', 'slug' => '', 'description' => ''], is_array($defaultTerm) ? $defaultTerm : ['name' => (string) $defaultTerm]),
            'sort' => $args['sort'] ?? null,
            'args' => $args['args'] ?? null,
            '_builtin' => $args['_builtin'] ?? false,
        ];
    }

    /**
     * A taxonomy's rewrite settles, whether queryable or not, the given keys
     * merged over the defaults; a rewrite of true leaves an empty "0" entry
     * behind, as the reference's merge does (probe registry-rewrites).
     */
    private static function taxonomyRewrite(string $name, mixed $rewrite): mixed
    {
        if ($rewrite === false || !Registry::settlesRewrites()) {
            return $rewrite;
        }
        $rewrite = array_merge(['with_front' => true, 'hierarchical' => false, 'ep_mask' => 0], is_array($rewrite) ? $rewrite : ['']);
        $rewrite['slug'] ??= $name;
        return $rewrite;
    }
}
