<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp-abilities/v1/abilities (probe abilities-rest-controllers): the
 * abilities shown in REST, narrowed by category, namespace or meta (every
 * field given must match exactly), paged with X-WP-Total,
 * X-WP-TotalPages and Link; one ability by name. Reading needs a
 * signed-in caller. An item's data leaves out its links, which travel on
 * the response; _fields does not narrow the data, but leaves the links
 * out unless it names them. Schemas are the reference's, captured.
 */
class WP_REST_Abilities_V1_List_Controller extends WP_REST_Controller
{
    protected $namespace = 'wp-abilities/v1';
    protected $rest_base = 'abilities';

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<name>[a-zA-Z0-9\-\/]+)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['name' => SchemaData::of('ability-name')]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items($request)
    {
        $abilities = array_values(array_filter(wp_get_abilities(), fn (WP_Ability $ability) => $this->listed($ability, $request)));
        $per_page = max(1, (int) $request['per_page']);
        $page = max(1, (int) $request['page']);
        $items = [];
        foreach (array_slice($abilities, ($page - 1) * $per_page, $per_page) as $ability) {
            $items[] = $this->prepare_response_for_collection($this->prepare_item_for_response($ability, $request));
        }
        return $this->paged($items, count($abilities), $page, $per_page, $request);
    }

    public function get_item($request)
    {
        $ability = wp_has_ability((string) $request['name']) ? wp_get_ability((string) $request['name']) : null;
        if ($ability === null || !$ability->get_meta_item('show_in_rest')) {
            return new WP_Error('rest_ability_not_found', 'Ability not found.', ['status' => 404]);
        }
        return $this->prepare_item_for_response($ability, $request);
    }

    public function get_items_permissions_check($request)
    {
        return is_user_logged_in();
    }

    public function get_item_permissions_check($request)
    {
        return is_user_logged_in();
    }

    public function prepare_item_for_response($ability, $request)
    {
        $data = [
            'name' => $ability->get_name(),
            'label' => $ability->get_label(),
            'description' => $ability->get_description(),
            'category' => $ability->get_category(),
            'input_schema' => $ability->get_input_schema(),
            'output_schema' => $ability->get_output_schema(),
            'meta' => $ability->get_meta(),
        ];
        $response = rest_ensure_response($this->filter_response_by_context($data, $request['context'] ?? 'view'));
        if (rest_is_field_included('_links', $this->get_fields_for_response($request))) {
            $item = rest_url("{$this->namespace}/{$this->rest_base}/" . $ability->get_name());
            $response->add_links(['self' => ['href' => $item], 'collection' => ['href' => rest_url("{$this->namespace}/{$this->rest_base}")], 'wp:action-run' => ['href' => $item . '/run']]);
        }
        return $response;
    }

    public function get_item_schema(): array
    {
        return SchemaData::of('ability');
    }

    public function get_collection_params(): array
    {
        return apply_filters('rest_abilities_collection_params', SchemaData::of('ability-collection'));
    }

    /** Whether the catalogue lists an ability: shown in REST, and matching the request's category, namespace and meta. */
    private function listed(WP_Ability $ability, $request): bool
    {
        $category = (string) ($request['category'] ?? '');
        $namespace = (string) ($request['namespace'] ?? '');
        return $ability->get_meta_item('show_in_rest') === true
            && ($category === '' || $ability->get_category() === $category)
            && ($namespace === '' || str_starts_with($ability->get_name(), $namespace . '/'))
            && self::matches($ability->get_meta(), (array) ($request['meta'] ?? []));
    }

    /** Whether every field the filter names is in the meta with exactly its value, nested arrays field by field. */
    private static function matches(array $meta, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            $ok = is_array($value) ? is_array($meta[$key] ?? null) && self::matches($meta[$key], $value) : array_key_exists($key, $meta) && $meta[$key] === $value;
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** The page as a response, with the totals and the previous and next pages' links. */
    private function paged(array $items, int $total, int $page, int $per_page, $request)
    {
        $pages = (int) ceil($total / $per_page);
        $response = rest_ensure_response($items);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $pages);
        $base = add_query_arg(urlencode_deep($request->get_query_params()), rest_url("{$this->namespace}/{$this->rest_base}"));
        foreach (['prev' => $page > 1 ? $page - 1 : 0, 'next' => $page < $pages ? $page + 1 : 0] as $rel => $to) {
            if ($to > 0) {
                $response->link_header($rel, add_query_arg('page', $to, $base));
            }
        }
        return $response;
    }
}
