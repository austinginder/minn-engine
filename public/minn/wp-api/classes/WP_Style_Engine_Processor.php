<?php

/** CSS rules and stores gathered into one stylesheet, a selector's rules merged (probe style-engine). */
#[AllowDynamicProperties]
class WP_Style_Engine_Processor
{
    protected $stores = [];
    protected $css_rules = [];

    public function add_store($store)
    {
        if (!$store instanceof WP_Style_Engine_CSS_Rules_Store) {
            _doing_it_wrong(__METHOD__, __('$store must be an instance of WP_Style_Engine_CSS_Rules_Store'), '6.1.0');
            return $this;
        }
        $this->stores[$store->get_name()] = $store;
        return $this;
    }

    public function add_rules($css_rules)
    {
        foreach (is_array($css_rules) ? $css_rules : [$css_rules] as $rule) {
            if (!$rule instanceof WP_Style_Engine_CSS_Rule) {
                continue;
            }
            $group = $rule->get_rules_group();
            $key = $group === '' ? $rule->get_selector() : "{$group} {$rule->get_selector()}";
            if (isset($this->css_rules[$key])) {
                $this->css_rules[$key]->add_declarations($rule->get_declarations());
                continue;
            }
            $this->css_rules[$key] = $rule;
        }
        return $this;
    }

    public function get_css($options = [])
    {
        $options = wp_parse_args($options, ['optimize' => true, 'prettify' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG]);
        foreach ($this->stores as $store) {
            $this->add_rules($store->get_all_rules());
        }
        $css = '';
        foreach ($this->css_rules as $rule) {
            $css .= $rule->get_css((bool) $options['prettify']) . ($options['prettify'] ? "\n" : '');
        }
        return $css;
    }
}
