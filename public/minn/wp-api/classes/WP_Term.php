<?php

/** A term joined with its taxonomy row; get_the_category adds the legacy category_* names. */
#[AllowDynamicProperties]
final class WP_Term
{
    public $term_id;
    public $name = '';
    public $slug = '';
    public $term_group = 0;
    public $term_taxonomy_id = 0;
    public $taxonomy = '';
    public $description = '';
    public $parent = 0;
    public $count = 0;
    public $filter = 'raw';

    public static function get_instance($term_id, $taxonomy = null)
    {
        $term_id = (int) $term_id;
        if ($term_id <= 0) {
            return false;
        }
        $row = _minn_term_row($term_id, $taxonomy === null || $taxonomy === '' ? null : (string) $taxonomy);
        if ($row === null) {
            return false;
        }
        return new self((object) $row);
    }

    public function __construct($term)
    {
        foreach (get_object_vars($term) as $key => $value) {
            $this->{$key} = $value;
        }
        foreach (['term_id', 'term_group', 'term_taxonomy_id', 'parent', 'count'] as $int) {
            $this->{$int} = (int) $this->{$int};
        }
    }

    public function filter($filter)
    {
        return $this;
    }

    public function to_array()
    {
        return get_object_vars($this);
    }

    public function __get($key)
    {
        if ($key === 'data') {
            $data = new stdClass();
            foreach (['term_id', 'name', 'slug', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description', 'parent', 'count'] as $k) {
                $data->{$k} = $this->{$k};
            }
            return $data;
        }
        return null;
    }
}
