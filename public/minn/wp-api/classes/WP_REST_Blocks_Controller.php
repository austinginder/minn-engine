<?php

require_once __DIR__ . '/WP_REST_Posts_Controller.php';

/**
 * Patterns (wp_block) as a class (probe rest-revisions): readable only by
 * someone who may read the post, their title and content raw in every
 * context, and the sync status beside meta rather than in it.
 */
class WP_REST_Blocks_Controller extends WP_REST_Posts_Controller
{

    public function check_read_permission($post)
    {
        if (!current_user_can('read_post', $post->ID)) {
            return false;
        }
        return parent::check_read_permission($post);
    }

    public function filter_response_by_context($data, $context)
    {
        // No rendered title or content; the sync status moves out of meta, empty when there is none.
        $data = parent::filter_response_by_context($data, $context);
        unset($data['title']['rendered'], $data['content']['rendered']);
        $data['wp_pattern_sync_status'] = $data['meta']['wp_pattern_sync_status'] ?? '';
        unset($data['meta']['wp_pattern_sync_status']);
        return $data;
    }

    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        // The posts schema, the title and content raw only and readable in view.
        $schema = parent::get_item_schema();
        unset($schema['properties']['title']['properties']['rendered'], $schema['properties']['content']['properties']['rendered']);
        $schema['properties']['title']['properties']['raw']['context'] = ['view', 'edit'];
        $schema['properties']['content']['properties']['raw']['context'] = ['view', 'edit'];
        $this->schema = $schema;
        return $this->add_additional_fields_schema($this->schema);
    }
}
