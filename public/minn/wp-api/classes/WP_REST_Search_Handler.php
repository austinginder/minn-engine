<?php

/** What wp/v2/search asks of each kind of thing it finds: the ids and total for a search, and each item and its links (probe rest-search-handlers). */
#[AllowDynamicProperties]
abstract class WP_REST_Search_Handler
{
    const RESULT_IDS = 'ids';
    const RESULT_TOTAL = 'total';

    protected $type = '';
    protected $subtypes = [];

    public function get_type()
    {
        return $this->type;
    }

    public function get_subtypes()
    {
        return $this->subtypes;
    }

    abstract public function search_items(WP_REST_Request $request);

    abstract public function prepare_item($id, array $fields);

    abstract public function prepare_item_links($id);
}
