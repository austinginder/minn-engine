<?php

/** The error object plugin code passes around; shape from contracts/fixtures/api/functions.json. */
class WP_Error
{
    public $errors = [];
    public $error_data = [];
    protected $additional_data = [];

    public function __construct($code = '', $message = '', $data = '')
    {
        if ($code === '' || $code === null) {
            return;
        }
        $this->add($code, $message, $data);
    }

    public function get_error_codes()
    {
        return $this->has_errors() ? array_keys($this->errors) : [];
    }

    public function get_error_code()
    {
        $codes = $this->get_error_codes();
        return $codes === [] ? '' : $codes[0];
    }

    public function get_error_messages($code = '')
    {
        if ($code === '' || $code === null) {
            $all = [];
            foreach ($this->errors as $messages) {
                $all = array_merge($all, $messages);
            }
            return $all;
        }
        return $this->errors[$code] ?? [];
    }

    public function get_error_message($code = '')
    {
        if ($code === '' || $code === null) {
            $code = $this->get_error_code();
        }
        $messages = $this->get_error_messages($code);
        return $messages === [] ? '' : $messages[0];
    }

    public function get_error_data($code = '')
    {
        if ($code === '' || $code === null) {
            $code = $this->get_error_code();
        }
        return $this->error_data[$code] ?? null;
    }

    public function has_errors()
    {
        return $this->errors !== [];
    }

    public function add($code, $message, $data = '')
    {
        $this->errors[$code][] = $message;
        if ($data !== '' && $data !== null) {
            $this->add_data($data, $code);
        }
        do_action('wp_error_added', $code, $message, $data, $this);
    }

    public function add_data($data, $code = '')
    {
        if ($code === '' || $code === null) {
            $code = $this->get_error_code();
        }
        if (isset($this->error_data[$code])) {
            $this->additional_data[$code][] = $this->error_data[$code];
        }
        $this->error_data[$code] = $data;
    }

    public function get_all_error_data($code = '')
    {
        if ($code === '' || $code === null) {
            $code = $this->get_error_code();
        }
        $data = $this->additional_data[$code] ?? [];
        if (isset($this->error_data[$code])) {
            $data[] = $this->error_data[$code];
        }
        return $data;
    }

    public function remove($code)
    {
        unset($this->errors[$code], $this->error_data[$code], $this->additional_data[$code]);
    }

    public function merge_from(WP_Error $error)
    {
        static::copy_errors($error, $this);
    }

    public function export_to(WP_Error $error)
    {
        static::copy_errors($this, $error);
    }

    protected static function copy_errors(WP_Error $from, WP_Error $to)
    {
        foreach ($from->get_error_codes() as $code) {
            foreach ($from->get_error_messages($code) as $message) {
                $to->add($code, $message);
            }
            foreach ($from->get_all_error_data($code) as $data) {
                $to->add_data($data, $code);
            }
        }
    }
}
