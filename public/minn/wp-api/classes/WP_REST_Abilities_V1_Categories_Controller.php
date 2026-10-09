<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp-abilities/v1/categories (probe abilities-rest-controllers): every
 * ability category, paged with X-WP-Total, X-WP-TotalPages and Link; one
 * category by slug. Reading needs a signed-in caller. An item's links
 * travel on the response and lead to the abilities in the category.
 * Schemas are the reference's, captured.
 */
class WP_REST_Abilities_V1_Categories_Controller extends WP_REST_Controller
{
    protected $namespace = 'wp-abilities/v1';
    protected $rest_base = 'categories';

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['slug' => SchemaData::of('ability-category-slug')]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items($request)
    {
        $categories = array_values(wp_get_ability_categories());
        $per_page = max(1, (int) $request['per_page']);
        $page = max(1, (int) $request['page']);
        $items = [];
        foreach (array_slice($categories, ($page - 1) * $per_page, $per_page) as $category) {
            $items[] = $this->prepare_response_for_collection($this->prepare_item_for_response($category, $request));
        }
        return $this->paged($items, count($categories), $page, $per_page, $request);
    }

    public function get_item($request)
    {
        $category = wp_has_ability_category((string) $request['slug']) ? wp_get_ability_category((string) $request['slug']) : null;
        if ($category === null) {
            return new WP_Error('rest_ability_category_not_found', 'Ability category not found.', ['status' => 404]);
        }
        return $this->prepare_item_for_response($category, $request);
    }

    public function get_items_permissions_check($request)
    {
        return is_user_logged_in();
    }

    public function get_item_permissions_check($request)
    {
        return is_user_logged_in();
    }

    public function prepare_item_for_response($category, $request)
    {
        $data = ['slug' => $category->get_slug(), 'label' => $category->get_label(), 'description' => $category->get_description(), 'meta' => $category->get_meta()];
        $response = rest_ensure_response($this->filter_response_by_context($data, $request['context'] ?? 'view'));
        if (rest_is_field_included('_links', $this->get_fields_for_response($request))) {
            $response->add_links([
                'self' => ['href' => rest_url("{$this->namespace}/{$this->rest_base}/" . $category->get_slug())],
                'collection' => ['href' => rest_url("{$this->namespace}/{$this->rest_base}")],
                'abilities' => ['href' => add_query_arg('category', $category->get_slug(), rest_url("{$this->namespace}/abilities"))],
            ]);
        }
        return $response;
    }

    public function get_item_schema(): array
    {
        return SchemaData::of('ability-category');
    }

    public function get_collection_params(): array
    {
        return SchemaData::of('ability-category-collection');
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
