<?php

require_once __DIR__ . '/WP_REST_Search_Handler.php';

/**
 * Terms in wp/v2/search (probe rest-search-handlers): terms of the public
 * REST taxonomies, empty ones too, by name; the total as wp_count_terms
 * gives it. Each item's type is its taxonomy; linked to its REST route and
 * its taxonomy.
 */
#[AllowDynamicProperties]
class WP_REST_Term_Search_Handler extends WP_REST_Search_Handler
{
    public function __construct()
    {
        $this->type = 'term';
        $this->subtypes = array_values(get_taxonomies(['public' => true, 'show_in_rest' => true], 'names'));
    }

    public function search_items(WP_REST_Request $request)
    {
        $perPage = (int) $request['per_page'];
        $args = ['taxonomy' => _minn_rest_search_subtypes($request, $this->subtypes), 'hide_empty' => false, 'offset' => ($perPage * ((int) $request['page'] - 1)), 'number' => $perPage];
        if (!empty($request['search'])) {
            $args['search'] = $request['search'];
        }
        if (!empty($request['exclude'])) {
            $args['exclude'] = (array) $request['exclude'];
        }
        if (!empty($request['include'])) {
            $args['include'] = (array) $request['include'];
        }
        $args = apply_filters('rest_term_search_query', $args, $request);
        $ids = get_terms($args + ['fields' => 'ids']);
        unset($args['offset'], $args['number']);
        return [self::RESULT_IDS => is_array($ids) ? array_map('intval', $ids) : [], self::RESULT_TOTAL => wp_count_terms($args)];
    }

    public function prepare_item($id, array $fields)
    {
        $term = get_term($id);
        $data = [];
        if (in_array('id', $fields, true)) {
            $data['id'] = (int) $id;
        }
        if (in_array('title', $fields, true)) {
            $data['title'] = $term->name;
        }
        if (in_array('url', $fields, true)) {
            $data['url'] = get_term_link((int) $id);
        }
        if (in_array('type', $fields, true)) {
            $data['type'] = $term->taxonomy;
        }
        return $data;
    }

    public function prepare_item_links($id)
    {
        $term = get_term($id);
        $links = [];
        $route = rest_get_route_for_term($term);
        if ($route !== '') {
            $links['self'] = ['href' => rest_url($route), 'embeddable' => true];
        }
        $links['about'] = ['href' => rest_url('wp/v2/taxonomies/' . $term->taxonomy)];
        return $links;
    }
}
