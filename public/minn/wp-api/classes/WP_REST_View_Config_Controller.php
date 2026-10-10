<?php

use Minn\Json;
use Minn\Rest\Schema;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/view-config (probe rest-view-config): how the admin's data views
 * show an entity (a post type shown in REST, a taxonomy shown in REST, or
 * any other kind), for someone who holds the capability the entity asks:
 * a type's edit_posts, a taxonomy's manage_terms, manage_options for the
 * root, edit_posts otherwise. The configuration starts from the
 * reference's (data/view-configs.json: core types of their own, a generic
 * one for other types, another for every other kind), its first view
 * titled with the type's all-items label; patterns list their categories,
 * templates the theme, template parts their areas. Plugins patch it
 * through WP_View_Config_Data; empty objects are cast by the schema.
 */
class WP_REST_View_Config_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'view-config';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => ['kind' => ['description' => __('Entity kind.'), 'type' => 'string', 'required' => true], 'name' => ['description' => __('Entity name.'), 'type' => 'string', 'required' => true]]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    /** An entity that is not shown is not found; otherwise the caller needs the capability it asks. */
    public function get_items_permissions_check($request)
    {
        $capability = $this->get_required_capability((string) $request['kind'], (string) $request['name']);
        if ($capability === null) {
            return new WP_Error('rest_view_config_invalid_entity', __('Invalid entity kind or name.'), ['status' => 404]);
        }
        if (!current_user_can($capability)) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to read view config.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** The capability an entity asks; null for a post type or taxonomy that is missing or hidden from REST. */
    protected function get_required_capability($kind, $name)
    {
        if ($kind === 'postType' || $kind === 'taxonomy') {
            $object = $kind === 'postType' ? get_post_type_object($name) : get_taxonomy($name);
            if (!$object || empty($object->show_in_rest)) {
                return null;
            }
            return $kind === 'postType' ? $object->cap->edit_posts : $object->cap->manage_terms;
        }
        return $kind === 'root' ? 'manage_options' : 'edit_posts';
    }

    public function get_items($request)
    {
        $kind = (string) $request['kind'];
        $name = (string) $request['name'];
        $config = (new WP_View_Config_Data(['version' => 1] + $this->minn_defaults($kind, $name)))->apply_filters($kind, $name);
        return rest_ensure_response($this->cast_empty_objects(['kind' => $kind, 'name' => $name, 'version' => 1] + $config, $this->get_item_schema()));
    }

    /** A value with each empty array the schema calls an object made one (Minn\Rest\Schema::castEmptyObjects). */
    protected function cast_empty_objects($value, $schema)
    {
        return Schema::castEmptyObjects($value, (array) $schema);
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('view-config');
        return $this->add_additional_fields_schema($this->schema);
    }

    protected function get_view_base_schema()
    {
        return SchemaData::of('view-config-view-base');
    }

    protected function get_column_style_schema()
    {
        return SchemaData::of('view-config-column-style');
    }

    protected function get_table_layout_schema()
    {
        return SchemaData::of('view-config-table-layout');
    }

    protected function get_list_layout_schema()
    {
        return SchemaData::of('view-config-list-layout');
    }

    protected function get_combined_layout_schema()
    {
        return SchemaData::of('view-config-combined-layout');
    }

    protected function get_grid_layout_schema()
    {
        return SchemaData::of('view-config-grid-layout');
    }

    protected function get_form_layout_schema()
    {
        return SchemaData::of('view-config-form-layout');
    }

    protected function get_form_field_schema()
    {
        return SchemaData::of('view-config-form-field');
    }

    protected function get_form_schema()
    {
        return SchemaData::of('view-config-form');
    }

    /** The configuration an entity starts from, its views filled in from what is registered. @return array<string, mixed> */
    private function minn_defaults(string $kind, string $name): array
    {
        static $all = null;
        $all ??= (array) Json::file(MINN_ENGINE_DIR . '/data/view-configs.json');
        if ($kind !== 'postType') {
            return $all['default'];
        }
        $config = $all[$name] ?? $all['postType'];
        $config['view_list'] = match ($name) {
            'wp_block' => array_merge($config['view_list'], $this->minn_pattern_views()),
            'wp_template' => array_merge($config['view_list'], $this->minn_template_views()),
            'wp_template_part' => array_merge($config['view_list'], $this->minn_area_views()),
            default => $config['view_list'],
        };
        if (($config['view_list'][0]['title'] ?? '') === '{all_items}') {
            $type = get_post_type_object($name);
            $config['view_list'][0]['title'] = $type ? $type->labels->all_items : __('All items');
        }
        return $config;
    }

    /** A view per pattern category, Uncategorized among them, by title regardless of case. @return list<array<string, string>> */
    private function minn_pattern_views(): array
    {
        $views = [];
        foreach (WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $category) {
            $views[] = ['title' => $category['label'], 'slug' => $category['name']];
        }
        $views[] = ['title' => __('Uncategorized'), 'slug' => 'uncategorized'];
        usort($views, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
        return $views;
    }

    /** A view for the active theme's templates, filtered by its name. @return list<array<string, mixed>> */
    private function minn_template_views(): array
    {
        $author = (string) wp_get_theme()->get('Name');
        return [['title' => $author, 'slug' => $author, 'view' => ['filters' => [['field' => 'author', 'operator' => 'is', 'value' => $author, 'isLocked' => true]]]]];
    }

    /** A view per template part area, General last. @return list<array<string, mixed>> */
    private function minn_area_views(): array
    {
        $areas = get_allowed_block_template_part_areas();
        usort($areas, static fn ($a, $b) => ($a['area'] === 'uncategorized') <=> ($b['area'] === 'uncategorized'));
        return array_map(static fn ($area) => ['title' => $area['label'], 'slug' => $area['area'], 'view' => ['filters' => [['field' => 'area', 'operator' => 'is', 'value' => $area['area'], 'isLocked' => true]]]], $areas);
    }
}
