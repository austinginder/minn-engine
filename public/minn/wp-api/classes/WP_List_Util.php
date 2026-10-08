<?php
/**
 * A list worked on in steps (probe plugin-queue2): each filter, pluck or
 * sort works on what the step before left, kept as the output; the input
 * stays as given. The work lives in Minn\Support\Lists, which the
 * wp_list_* functions share.
 */
#[AllowDynamicProperties]
class WP_List_Util
{
    private $input = [];
    private $output = [];
    private $orderby = [];

    public function __construct($input)
    {
        $this->output = $input;
        $this->input = $input;
    }

    public function get_input()
    {
        return $this->input;
    }

    public function get_output()
    {
        return $this->output;
    }

    public function filter($args = [], $operator = 'AND')
    {
        if (empty($args)) {
            return $this->output;
        }
        $this->output = \Minn\Support\Lists::filter((array) $this->output, (array) $args, (string) $operator);
        return $this->output;
    }

    public function pluck($field, $index_key = null)
    {
        $this->output = \Minn\Support\Lists::pluck((array) $this->output, $field, $index_key);
        return $this->output;
    }

    public function sort($orderby = [], $order = 'ASC', $preserve_keys = false)
    {
        if (empty($orderby)) {
            return $this->output;
        }
        $orderby = is_string($orderby) ? [$orderby => $order] : (array) $orderby;
        $this->orderby = array_map(static fn ($direction) => strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC', $orderby);
        $this->output = \Minn\Support\Lists::sort((array) $this->output, $this->orderby, (bool) $preserve_keys);
        return $this->output;
    }
}
