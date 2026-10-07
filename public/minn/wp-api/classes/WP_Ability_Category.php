<?php
/** One registered ability category: its slug, label, description and meta (probe abilities-registry). */
class WP_Ability_Category
{
    protected $slug;
    protected $label;
    protected $description;
    protected $meta;

    public function __construct($slug, $args = [])
    {
        $this->slug = (string) $slug;
        $this->label = (string) ($args['label'] ?? '');
        $this->description = (string) ($args['description'] ?? '');
        $this->meta = (array) ($args['meta'] ?? []);
    }

    public function get_slug()
    {
        return $this->slug;
    }

    public function get_label()
    {
        return $this->label;
    }

    public function get_description()
    {
        return $this->description;
    }

    public function get_meta()
    {
        return $this->meta;
    }
}
