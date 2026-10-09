<?php

/**
 * A block template or template part as the reference describes one. The
 * REST controller hangs author_text and original_source on an instance
 * without the class declaring them, so the declared set stays as it is.
 */
#[\AllowDynamicProperties]
class WP_Block_Template
{
    public $type;
    public $theme;
    public $slug;
    public $id;
    public $title = '';
    public $content = '';
    public $description = '';
    public $source = 'theme';
    public $origin;
    public $wp_id;
    public $status;
    public $has_theme_file;
    public $is_custom = true;
    public $author;
    public $plugin;
    public $post_types;
    public $area;
    public $modified;
    public $date;
}

/** The registry of templates plugins register; storage in Minn\Runtime\BlockTemplates. */
final class WP_Block_Templates_Registry
{
    private static $instance = null;

    public static function get_instance()
    {
        return self::$instance ??= new self();
    }

    /** A plugin template, or a WP_Error (with a notice) for a name that is not a string, lacks a namespace, has capitals, or is taken. */
    public function register($template_name, $args = [])
    {
        $row = is_string($template_name) ? \Minn\Runtime\Runtime::blockTemplates()->register($template_name, (array) $args) : 'template_name_no_string';
        if (is_string($row)) {
            $messages = ['template_name_no_string' => __('Template names must be strings.'), 'template_no_prefix' => __('Template names must contain a namespace prefix. Example: my-plugin//my-custom-template'), 'template_name_no_uppercase' => __('Template names must not contain uppercase characters.'), 'template_already_registered' => sprintf(__('Template "%s" is already registered.'), $template_name)];
            _doing_it_wrong(__METHOD__, $messages[$row], '6.7.0');
            return new WP_Error($row, $messages[$row]);
        }
        return _minn_registered_block_template($row);
    }

    public function get_all_registered()
    {
        return array_map('_minn_registered_block_template', \Minn\Runtime\Runtime::blockTemplates()->all());
    }

    public function get_registered($template_name)
    {
        $row = \Minn\Runtime\Runtime::blockTemplates()->get((string) $template_name);
        return $row === null ? null : _minn_registered_block_template($row);
    }

    public function get_by_slug($template_slug)
    {
        $row = \Minn\Runtime\Runtime::blockTemplates()->bySlug((string) $template_slug);
        return $row === null ? null : _minn_registered_block_template($row);
    }

    public function get_by_query($query = [])
    {
        $out = [];
        foreach ($this->get_all_registered() as $name => $template) {
            if (!empty($query['slug__in']) && !in_array($template->slug, (array) $query['slug__in'], true)) {
                continue;
            }
            if (!empty($query['slug__not_in']) && in_array($template->slug, (array) $query['slug__not_in'], true)) {
                continue;
            }
            if (!empty($query['post_type']) && !in_array($query['post_type'], (array) $template->post_types, true)) {
                continue;
            }
            $out[$name] = $template;
        }
        return $out;
    }

    public function is_registered($template_name)
    {
        return \Minn\Runtime\Runtime::blockTemplates()->get((string) $template_name) !== null;
    }

    public function unregister($template_name)
    {
        $row = \Minn\Runtime\Runtime::blockTemplates()->unregister((string) $template_name);
        if ($row === null) {
            $message = sprintf(__('Template "%s" is not registered.'), $template_name);
            _doing_it_wrong(__METHOD__, $message, '6.7.0');
            return new WP_Error('template_not_registered', $message);
        }
        return _minn_registered_block_template($row);
    }
}
