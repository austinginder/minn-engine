<?php
/** The style engine's declaration and rule holders; css strings match the probed shapes. */
#[AllowDynamicProperties]
class WP_Style_Engine_CSS_Declarations
{
    protected $declarations = [];
    protected $declaration_options = [];

    /** A non-array adds nothing. */
    public function __construct($declarations = [])
    {
        if (is_array($declarations)) {
            $this->add_declarations($declarations);
        }
    }

    /**
     * Keeps a declaration as given (probe style-engine-classes): the property
     * cleaned to lower case letters, digits and dashes, a string value
     * trimmed (anything else, or nothing left, is not kept), and options
     * given as an array kept beside it. The kses css filter judges it only
     * when the string is printed.
     */
    public function add_declaration($property, $value, $options = [])
    {
        $property = $this->sanitize_property((string) $property);
        if ($property === '' || !is_string($value) || ($value = trim($value)) === '') {
            return $this;
        }
        $this->declarations[$property] = $value;
        if (is_array($options) && $options !== []) {
            $this->declaration_options[$property] = $options;
        }
        return $this;
    }

    public function add_declarations($declarations)
    {
        foreach ((array) $declarations as $property => $value) {
            $this->add_declaration((string) $property, $value);
        }
        return $this;
    }

    public function remove_declaration($property)
    {
        unset($this->declarations[(string) $property], $this->declaration_options[(string) $property]);
        return $this;
    }

    /** The options each declaration was added with, by property. */
    public function get_declaration_options()
    {
        return $this->declaration_options;
    }

    /** One declaration through the kses css filter, the spacer after its colon; '' when the filter refuses it. */
    protected static function filter_declaration($property, $value, $spacer = '', $options = [])
    {
        return safecss_filter_attr("{$property}:{$spacer}{$value}");
    }

    /** A property name in lower case, keeping letters, digits and dashes. */
    protected function sanitize_property($property)
    {
        return (string) preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string) $property)));
    }

    public function remove_declarations($properties = [])
    {
        foreach ((array) $properties as $property) {
            $this->remove_declaration((string) $property);
        }
        return $this;
    }

    public function get_declarations()
    {
        return $this->declarations;
    }

    /** Pretty with no indent joins on one spaced line; pretty inside a rule (indent > 0) stacks lines (both probed). */
    public function get_declarations_string($should_prettify = false, $indent_count = 0)
    {
        $indent = $should_prettify && (int) $indent_count > 0 ? str_repeat("\t", (int) $indent_count) : '';
        $out = [];
        foreach ($this->declarations as $property => $value) {
            $filtered = static::filter_declaration($property, $value, $should_prettify ? ' ' : '', $this->declaration_options[$property] ?? []);
            if ($filtered !== '') {
                $out[] = $indent . $filtered . ';';
            }
        }
        if (!$should_prettify) {
            return implode('', $out);
        }
        return implode((int) $indent_count > 0 ? "\n" : ' ', $out);
    }
}

#[AllowDynamicProperties]
class WP_Style_Engine_CSS_Rule
{
    protected $selector = '';
    protected $declarations;
    protected $rules_group = '';

    public function __construct($selector = '', $declarations = [], $rules_group = '')
    {
        $this->set_selector($selector);
        $this->declarations = $declarations instanceof WP_Style_Engine_CSS_Declarations ? $declarations : new WP_Style_Engine_CSS_Declarations($declarations);
        $this->rules_group = (string) $rules_group;
    }

    public function set_selector($selector)
    {
        $this->selector = (string) $selector;
        return $this;
    }

    public function add_declarations($declarations)
    {
        $this->declarations->add_declarations($declarations instanceof WP_Style_Engine_CSS_Declarations ? $declarations->get_declarations() : (array) $declarations);
        return $this;
    }

    public function set_rules_group($rules_group)
    {
        $this->rules_group = (string) $rules_group;
        return $this;
    }

    public function get_rules_group()
    {
        return $this->rules_group;
    }

    public function get_declarations()
    {
        return $this->declarations;
    }

    public function get_selector()
    {
        return $this->selector;
    }

    /** A grouped rule nests a level deeper inside its group, both indented from $indent_count (probe style-engine). */
    public function get_css($should_prettify = false, $indent_count = 0)
    {
        $depth = (int) $indent_count + ($this->rules_group !== '' ? 1 : 0);
        $indent = $should_prettify ? str_repeat("\t", $depth) : '';
        $body = $this->declarations->get_declarations_string($should_prettify, $depth + 1);
        if ($body === '') {
            return '';
        }
        $css = $should_prettify ? "{$indent}{$this->selector} {\n{$body}\n{$indent}}" : "{$this->selector}{{$body}}";
        if ($this->rules_group === '') {
            return $css;
        }
        $outer = $should_prettify ? str_repeat("\t", (int) $indent_count) : '';
        return $should_prettify ? "{$outer}{$this->rules_group} {\n{$css}\n{$outer}}" : "{$this->rules_group}{{$css}}";
    }
}
