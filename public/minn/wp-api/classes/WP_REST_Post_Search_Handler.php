<?php

require_once __DIR__ . '/WP_REST_Search_Handler.php';

/**
 * Posts in wp/v2/search (probe rest-search-handlers): published posts of the
 * public REST post types (not attachments), by relevance, through
 * rest_post_search_query; each with its title shown unprotected, permalink,
 * type and post type, linked to its REST route and its type.
 */
#[AllowDynamicProperties]
class WP_REST_Post_Search_Handler extends WP_REST_Search_Handler
{
    public function __construct()
    {
        $this->type = 'post';
        $this->subtypes = array_values(array_diff(get_post_types(['public' => true, 'show_in_rest' => true], 'names'), ['attachment']));
    }

    public function search_items(WP_REST_Request $request)
    {
        $args = ['post_type' => _minn_rest_search_subtypes($request, $this->subtypes), 'post_status' => 'publish', 'paged' => (int) $request['page'], 'posts_per_page' => (int) $request['per_page'], 'ignore_sticky_posts' => true];
        if (!empty($request['search'])) {
            $args['s'] = $request['search'];
        }
        if (!empty($request['exclude'])) {
            $args['post__not_in'] = (array) $request['exclude'];
        }
        if (!empty($request['include'])) {
            $args['post__in'] = (array) $request['include'];
        }
        $query = new WP_Query(['fields' => 'ids'] + apply_filters('rest_post_search_query', $args, $request));
        return [self::RESULT_IDS => $query->posts, self::RESULT_TOTAL => $query->found_posts];
    }

    public function prepare_item($id, array $fields)
    {
        $post = get_post($id);
        $data = [];
        if (in_array('id', $fields, true)) {
            $data['id'] = (int) $post->ID;
        }
        if (in_array('title', $fields, true)) {
            add_filter('protected_title_format', [$this, 'protected_title_format']);
            $data['title'] = get_the_title($post->ID);
            remove_filter('protected_title_format', [$this, 'protected_title_format']);
        }
        if (in_array('url', $fields, true)) {
            $data['url'] = get_permalink($post->ID);
        }
        if (in_array('type', $fields, true)) {
            $data['type'] = $this->type;
        }
        if (in_array('subtype', $fields, true)) {
            $data['subtype'] = $post->post_type;
        }
        return $data;
    }

    public function prepare_item_links($id)
    {
        $post = get_post($id);
        $links = [];
        $route = rest_get_route_for_post($post);
        if ($route !== '') {
            $links['self'] = ['href' => rest_url($route), 'embeddable' => true];
        }
        $links['about'] = ['href' => rest_url('wp/v2/types/' . $post->post_type)];
        return $links;
    }

    /** A protected post's title as it is, without the "Protected:" prefix. */
    public function protected_title_format()
    {
        return '%s';
    }

    protected function detect_rest_item_route($post)
    {
        _deprecated_function(__METHOD__, '5.5.0', 'rest_get_route_for_post()');
        return rest_get_route_for_post($post);
    }
}
