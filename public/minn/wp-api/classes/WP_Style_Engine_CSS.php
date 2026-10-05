<?php
/** The style engine's declaration and rule holders; css strings match the probed shapes. */
#[AllowDynamicProperties]
class WP_Style_Engine_CSS_Declarations
{
    protected $declarations = [];

    public function __construct($declarations = [])
    {
        $this->add_declarations((array) $declarations);
    }

    /** Declarations run through the kses css filter: an unknown property or a broken value drops (probed: a-b goes, --my-var and url() stay, an injected close-brace tail is cut). */
    public function add_declaration($property, $value)
    {
        $property = strtolower(trim((string) $property));
        if ($property === '' || !is_scalar($value) || trim((string) $value) === '') {
            return $this;
        }
        $filtered = safecss_filter_attr($property . ':' . trim((string) $value));
        if ($filtered === '' || !str_contains($filtered, ':')) {
            return $this;
        }
        [$keptProperty, $keptValue] = array_map('trim', explode(':', $filtered, 2));
        if ($keptProperty !== '' && $keptValue !== '') {
            $this->declarations[$keptProperty] = $keptValue;
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
        unset($this->declarations[(string) $property]);
        return $this;
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
        $glue = $should_prettify ? ': ' : ':';
        $out = [];
        foreach ($this->declarations as $property => $value) {
            $out[] = $indent . $property . $glue . $value . ';';
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

    public function get_css($should_prettify = false, $indent_count = 0)
    {
        $indent = $should_prettify ? str_repeat("\t", (int) $indent_count) : '';
        $open = $should_prettify ? " {\n" : '{';
        $close = $should_prettify ? "\n" . $indent . '}' : '}';
        $body = $this->declarations->get_declarations_string($should_prettify, (int) $indent_count + 1);
        if ($body === '') {
            return '';
        }
        $css = $indent . $this->selector . $open . $body . $close;
        if ($this->rules_group !== '') {
            $css = $this->rules_group . ($should_prettify ? " {\n" : '{') . $css . ($should_prettify ? "\n}" : '}');
        }
        return $css;
    }
}
