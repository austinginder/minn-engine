<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/icons (probe icons): the registered icons, narrowed by collection
 * (a collection nobody registered is rest_icon_collection_not_found) and
 * search, and one icon by name. Reading needs edit_posts. An icon whose
 * file is missing comes without content. Schemas are the reference's,
 * captured.
 */
class WP_REST_Icons_Controller extends WP_REST_Controller
{
    private const NAME_PATTERN = '[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?';

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'icons';
    }

    public function register_routes()
    {
        $list = ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check']];
        register_rest_route($this->namespace, '/' . $this->rest_base, [$list + ['args' => $this->get_collection_params()], 'schema' => [$this, 'get_public_item_schema']]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<collection>' . self::NAME_PATTERN . ')', [$list + ['args' => ['collection' => SchemaData::of('icon-collection-param')] + $this->get_collection_params()], 'schema' => [$this, 'get_public_item_schema']]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<name>' . self::NAME_PATTERN . '/' . self::NAME_PATTERN . ')', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['name' => SchemaData::of('icon-name'), 'context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        return current_user_can('edit_posts') ? true : new WP_Error('rest_cannot_view', 'Sorry, you are not allowed to view the registered icons.', ['status' => rest_authorization_required_code()]);
    }

    public function get_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
    }

    public function get_items($request)
    {
        $collection = $request['collection'] ?? null;
        if (is_string($collection) && $collection !== '' && !WP_Icon_Collections_Registry::get_instance()->is_registered($collection)) {
            return new WP_Error('rest_icon_collection_not_found', sprintf('Icon collection not found: "%s".', $collection), ['status' => 404]);
        }
        $items = [];
        foreach (WP_Icons_Registry::get_instance()->get_registered_icons((string) ($request['search'] ?? '')) as $icon) {
            if (!is_string($collection) || $collection === '' || $icon['collection'] === $collection) {
                $items[] = $this->prepare_response_for_collection($this->prepare_item_for_response($icon, $request));
            }
        }
        return rest_ensure_response($items);
    }

    public function get_item($request)
    {
        $icon = $this->get_icon((string) $request['name']);
        return is_wp_error($icon) ? $icon : $this->prepare_item_for_response($icon, $request);
    }

    public function get_icon($name)
    {
        return WP_Icons_Registry::get_instance()->get_registered_icon($name) ?? new WP_Error('rest_icon_not_found', sprintf('Icon not found: "%s".', $name), ['status' => 404]);
    }

    public function prepare_item_for_response($item, $request)
    {
        $fields = $this->get_fields_for_response($request);
        $data = [];
        foreach (['name', 'label', 'content', 'collection'] as $field) {
            if (isset($item[$field]) && rest_is_field_included($field, $fields)) {
                $data[$field] = $item[$field];
            }
        }
        return rest_ensure_response($this->filter_response_by_context($data, $request['context'] ?? 'view'));
    }

    public function get_item_schema()
    {
        return SchemaData::of('icon');
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])] + parent::get_collection_params() + ['collection' => SchemaData::of('icon-collection-param')];
    }
}
