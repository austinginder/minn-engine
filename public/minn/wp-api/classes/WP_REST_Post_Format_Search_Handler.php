<?php

require_once __DIR__ . '/WP_REST_Search_Handler.php';

/**
 * Post formats in wp/v2/search (probe rest-search-handlers): the formats
 * whose term exists (empty or not), whose label holds the search (the query
 * arguments through rest_post_format_search_query), sliced by page; each
 * with its label, archive link (false without one) and the post-format
 * type, and no links.
 */
#[AllowDynamicProperties]
class WP_REST_Post_Format_Search_Handler extends WP_REST_Search_Handler
{
    public function __construct()
    {
        $this->type = 'post-format';
    }

    public function search_items(WP_REST_Request $request)
    {
        $args = empty($request['search']) ? [] : ['search' => $request['search']];
        $args = apply_filters('rest_post_format_search_query', $args, $request);
        $search = (string) ($args['search'] ?? '');
        $labels = get_post_format_strings();
        $found = [];
        foreach ((array) get_terms(['taxonomy' => 'post_format', 'hide_empty' => false, 'fields' => 'slugs']) as $slug) {
            $format = str_replace('post-format-', '', (string) $slug);
            if (isset($labels[$format]) && ($search === '' || stripos($labels[$format], $search) !== false)) {
                $found[] = $format;
            }
        }
        $perPage = (int) $request['per_page'];
        return [self::RESULT_IDS => array_slice($found, $perPage * ((int) $request['page'] - 1), $perPage), self::RESULT_TOTAL => count($found)];
    }

    public function prepare_item($id, array $fields)
    {
        $data = [];
        if (in_array('id', $fields, true)) {
            $data['id'] = $id;
        }
        if (in_array('title', $fields, true)) {
            $data['title'] = get_post_format_string($id);
        }
        if (in_array('url', $fields, true)) {
            $data['url'] = get_post_format_link($id);
        }
        if (in_array('type', $fields, true)) {
            $data['type'] = 'post-format';
        }
        return $data;
    }

    public function prepare_item_links($id)
    {
        return [];
    }
}
