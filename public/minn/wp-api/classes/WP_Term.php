<?php

/** A term joined with its taxonomy row; get_the_category adds the legacy category_* names. */
#[AllowDynamicProperties]
final class WP_Term implements \Minn\Objects\Term
{
    public $term_id;
    public $name = '';
    public $slug = '';
    public $term_group = '';
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
        return _minn_term_numbers(new self((object) $row));
    }

    /** A term from the fields given, as given (a fetched term has its numbers made integers by the fetch). */
    public function __construct($term)
    {
        foreach (get_object_vars($term) as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /** Puts the term's fields in a context (display, edit, db and the rest) in place. */
    public function filter($filter)
    {
        sanitize_term($this, $this->taxonomy, $filter);
    }

    public function to_array()
    {
        return get_object_vars($this);
    }

    public function __get($key)
    {
        // The term's columns, then filter raw: a fetched term's to_array() matches it.
        if ($key === 'data') {
            $data = new stdClass();
            foreach (['term_id', 'name', 'slug', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description', 'parent', 'count'] as $k) {
                $data->{$k} = $this->{$k};
            }
            $data->filter = 'raw';
            return $data;
        }
        return null;
    }
}
