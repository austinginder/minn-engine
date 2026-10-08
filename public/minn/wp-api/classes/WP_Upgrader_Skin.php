<?php

/**
 * The upgrader skins (probe upgrader): what an upgrader tells the person or
 * code that started it. The base skin prints each line as a paragraph; the
 * Automatic skin keeps them as messages instead (cut to a, br, em and
 * strong, trimmed, blank ones dropped), and the Ajax skin also gathers the
 * errors. The skins that draw wp-admin's install and update screens keep
 * the reference's names, properties and lines, but draw none of the
 * screen around them (headings, return links, progress scripts): Minn has
 * no wp-admin. One file, so each class finds its parent already declared.
 */
#[AllowDynamicProperties]
class WP_Upgrader_Skin
{
    public $upgrader;
    public $done_header = false;
    public $done_footer = false;
    public $result = false;
    public $options = [];

    public function __construct($args = [])
    {
        $this->options = wp_parse_args($args, ['url' => '', 'nonce' => '', 'title' => '', 'context' => false]);
    }

    public function set_upgrader(&$upgrader)
    {
        if (is_object($upgrader)) {
            $this->upgrader =& $upgrader;
        }
        $this->add_strings();
    }

    public function add_strings()
    {
    }

    public function set_result($result)
    {
        $this->result = $result;
    }

    public function request_filesystem_credentials($error = false, $context = '', $allow_relaxed_file_ownership = false)
    {
        $url = (string) $this->options['url'];
        if (!$context) {
            $context = $this->options['context'];
        }
        if (!empty($this->options['nonce'])) {
            $url = wp_nonce_url($url, $this->options['nonce']);
        }
        return request_filesystem_credentials($url, '', $error, $context, [], $allow_relaxed_file_ownership);
    }

    public function header()
    {
        if ($this->done_header) {
            return;
        }
        $this->done_header = true;
        echo '<div class="wrap"><h1>' . $this->options['title'] . '</h1>';
    }

    public function footer()
    {
        if ($this->done_footer) {
            return;
        }
        $this->done_footer = true;
        echo '</div>';
    }

    /** A string is one of the upgrader's lines (or said as it is); an error says each message, with its data when that is text. */
    public function error($errors)
    {
        if (!$this->done_header) {
            $this->header();
        }
        if (is_string($errors)) {
            $this->feedback($errors);
            return;
        }
        if (!is_wp_error($errors) || !$errors->has_errors()) {
            return;
        }
        foreach ($errors->get_error_codes() as $code) {
            $data = $errors->get_error_data($code);
            foreach ($errors->get_error_messages($code) as $message) {
                $this->feedback(is_string($data) && $data !== '' ? $message . ' ' . esc_html(strip_tags($data)) : $message);
            }
        }
    }

    /** One line: an upgrader string by its key (or as given), its %s filled with the arguments, tags stripped and escaped. */
    public function feedback($feedback, ...$args)
    {
        $feedback = _minn_upgrader_line($this->upgrader, $feedback, $args);
        if (!$feedback) {
            return;
        }
        show_message($feedback);
    }

    public function before()
    {
    }

    public function after()
    {
    }

    /** The update-count script wp-admin's screens run; Minn has no such screen. */
    protected function decrement_update_count($type)
    {
    }

    public function bulk_header()
    {
    }

    public function bulk_footer()
    {
    }

    public function hide_process_failed($wp_error)
    {
        return false;
    }
}

class Automatic_Upgrader_Skin extends WP_Upgrader_Skin
{
    protected $messages = [];

    /** Asked without the credentials form showing: whatever it would print is swallowed. */
    public function request_filesystem_credentials($error = false, $context = '', $allow_relaxed_file_ownership = false)
    {
        if ($context) {
            $this->options['context'] = $context;
        }
        ob_start();
        $result = parent::request_filesystem_credentials($error, $context, $allow_relaxed_file_ownership);
        ob_end_clean();
        return $result;
    }

    public function get_upgrade_messages()
    {
        return $this->messages;
    }

    public function feedback($feedback, ...$args)
    {
        $line = is_wp_error($feedback) ? $feedback->get_error_message() : _minn_upgrader_line($this->upgrader, $feedback, $args);
        $line = trim(wp_kses((string) $line, ['a' => ['href' => true], 'br' => true, 'em' => true, 'strong' => true]));
        if ($line !== '') {
            $this->messages[] = $line;
        }
    }

    /** What the run prints between header and footer becomes one more message. */
    public function header()
    {
        ob_start();
    }

    public function footer()
    {
        $printed = ob_get_clean();
        if (!empty($printed)) {
            $this->feedback($printed);
        }
    }
}

class WP_Ajax_Upgrader_Skin extends Automatic_Upgrader_Skin
{
    public $plugin_info = [];
    public $theme_info = false;
    protected $errors;

    public function __construct($args = [])
    {
        parent::__construct($args);
        $this->errors = new WP_Error();
    }

    public function get_errors()
    {
        return $this->errors;
    }

    /** Every gathered error as one line, each with its data when that is text, separated by commas. */
    public function get_error_messages()
    {
        $messages = [];
        foreach ($this->errors->get_error_codes() as $code) {
            $data = $this->errors->get_error_data($code);
            $messages[] = $this->errors->get_error_message($code) . (is_string($data) && $data !== '' ? ' ' . esc_html(strip_tags($data)) : '');
        }
        return implode(', ', $messages);
    }

    /** A string error is kept under unknown_upgrade_error_<n>; an error object's codes are kept as they are. */
    public function error($errors, ...$args)
    {
        if (is_string($errors)) {
            $line = _minn_upgrader_line($this->upgrader, $errors, $args, false);
            $this->errors->add('unknown_upgrade_error_' . (count($this->errors->get_error_codes()) + 1), $line);
        } elseif (is_wp_error($errors)) {
            foreach ($errors->get_error_codes() as $code) {
                $this->errors->add($code, $errors->get_error_message($code), $errors->get_error_data($code));
            }
        }
        parent::error($errors, ...$args);
    }

    public function feedback($feedback, ...$args)
    {
        if (is_wp_error($feedback)) {
            foreach ($feedback->get_error_codes() as $code) {
                $this->errors->add($code, $feedback->get_error_message($code), $feedback->get_error_data($code));
            }
        }
        parent::feedback($feedback, ...$args);
    }
}

class Plugin_Upgrader_Skin extends WP_Upgrader_Skin
{
    public $plugin = '';
    public $plugin_active = false;
    public $plugin_network_active = false;

    public function __construct($args = [])
    {
        $args = wp_parse_args($args, ['url' => '', 'plugin' => '', 'nonce' => '', 'title' => __('Update Plugin')]);
        $this->plugin = $args['plugin'];
        $this->plugin_active = $this->plugin !== '' && is_plugin_active($this->plugin);
        parent::__construct($args);
    }

    public function after()
    {
    }
}

class Theme_Upgrader_Skin extends WP_Upgrader_Skin
{
    public $theme = '';

    public function __construct($args = [])
    {
        $args = wp_parse_args($args, ['url' => '', 'theme' => '', 'nonce' => '', 'title' => __('Update Theme')]);
        $this->theme = $args['theme'];
        parent::__construct($args);
    }

    public function after()
    {
    }
}

class Plugin_Installer_Skin extends WP_Upgrader_Skin
{
    public $api;
    public $type;
    public $url;
    public $overwrite;
    private $is_downgrading = false;

    public function __construct($args = [])
    {
        $args = wp_parse_args($args, ['type' => 'web', 'url' => '', 'plugin' => '', 'nonce' => '', 'title' => '', 'overwrite' => '']);
        $this->type = $args['type'];
        $this->url = $args['url'];
        $this->api = $args['api'] ?? [];
        $this->overwrite = $args['overwrite'];
        parent::__construct($args);
    }

    public function before()
    {
    }

    /** An upload that found its folder taken is offered a replacement on wp-admin's screen, not reported as a failure. */
    public function hide_process_failed($wp_error)
    {
        return $this->type === 'upload' && $this->overwrite === '' && is_wp_error($wp_error) && $wp_error->get_error_code() === 'folder_exists';
    }

    public function after()
    {
    }

    private function do_overwrite()
    {
        return false;
    }
}

class Theme_Installer_Skin extends WP_Upgrader_Skin
{
    public $api;
    public $type;
    public $url;
    public $overwrite;
    private $is_downgrading = false;

    public function __construct($args = [])
    {
        $args = wp_parse_args($args, ['type' => 'web', 'url' => '', 'theme' => '', 'nonce' => '', 'title' => '', 'overwrite' => '']);
        $this->type = $args['type'];
        $this->url = $args['url'];
        $this->api = $args['api'] ?? [];
        $this->overwrite = $args['overwrite'];
        parent::__construct($args);
    }

    public function before()
    {
    }

    public function hide_process_failed($wp_error)
    {
        return $this->type === 'upload' && $this->overwrite === '' && is_wp_error($wp_error) && $wp_error->get_error_code() === 'folder_exists';
    }

    public function after()
    {
    }

    private function do_overwrite()
    {
        return false;
    }
}

class Bulk_Upgrader_Skin extends WP_Upgrader_Skin
{
    public $in_loop = false;
    public $error = false;

    public function __construct($args = [])
    {
        parent::__construct(wp_parse_args($args, ['url' => '', 'nonce' => '']));
    }

    public function add_strings()
    {
    }

    public function feedback($feedback, ...$args)
    {
        parent::feedback($feedback, ...$args);
    }

    public function header()
    {
    }

    public function footer()
    {
    }

    public function error($errors)
    {
        if (is_string($errors) && isset($this->upgrader->strings[$errors])) {
            $this->error = $this->upgrader->strings[$errors];
        } elseif (is_wp_error($errors)) {
            $this->error = implode(', ', $errors->get_error_messages());
        }
        parent::error($errors);
    }

    public function bulk_header()
    {
    }

    public function bulk_footer()
    {
    }

    public function before($title = '')
    {
        $this->in_loop = true;
    }

    public function after($title = '')
    {
        $this->reset();
    }

    public function reset()
    {
        $this->in_loop = false;
        $this->error = false;
    }

    public function flush_output()
    {
        wp_ob_end_flush_all();
        flush();
    }
}

class Bulk_Plugin_Upgrader_Skin extends Bulk_Upgrader_Skin
{
    public $plugin_info = [];

    public function add_strings()
    {
    }

    public function before($title = '')
    {
        parent::before($this->plugin_info['Title'] ?? $title);
    }

    public function after($title = '')
    {
        parent::after($this->plugin_info['Title'] ?? $title);
    }

    public function bulk_footer()
    {
    }
}

class Bulk_Theme_Upgrader_Skin extends Bulk_Upgrader_Skin
{
    public $theme_info = false;

    public function add_strings()
    {
    }

    public function before($title = '')
    {
        parent::before($title);
    }

    public function after($title = '')
    {
        parent::after($title);
    }

    public function bulk_footer()
    {
    }
}

class Language_Pack_Upgrader_Skin extends WP_Upgrader_Skin
{
    public $language_update;
    public $done_header = false;
    public $done_footer = false;
    public $display_footer_actions = true;

    public function __construct($args = [])
    {
        $args = wp_parse_args($args, ['url' => '', 'nonce' => '', 'title' => __('Update Translations'), 'skip_header_footer' => false]);
        if ($args['skip_header_footer']) {
            $this->done_header = true;
            $this->done_footer = true;
            $this->display_footer_actions = false;
        }
        parent::__construct($args);
    }

    public function before()
    {
    }

    public function error($errors)
    {
        parent::error($errors);
    }

    public function after()
    {
    }

    public function bulk_footer()
    {
    }
}

/**
 * An upgrader line: the upgrader's string for a key (or the text as given),
 * its placeholders filled with the arguments, each stripped of tags and,
 * unless asked otherwise, escaped (probe upgrader).
 *
 * @internal
 */
function _minn_upgrader_line($upgrader, $feedback, array $args, bool $escape = true)
{
    if (is_string($feedback) && isset($upgrader->strings[$feedback])) {
        $feedback = $upgrader->strings[$feedback];
    }
    if (is_string($feedback) && str_contains($feedback, '%') && $args !== []) {
        $args = array_map('strip_tags', array_map('strval', $args));
        $feedback = vsprintf($feedback, $escape ? array_map('esc_html', $args) : $args);
    }
    return $feedback;
}
