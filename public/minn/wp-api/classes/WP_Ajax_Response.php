<?php
/**
 * The XML envelope legacy admin-ajax handlers build. Shapes probed from the
 * reference: each add() renders one <response action='{action}_{id}'>
 * element; send() wraps them in the <wp_ajax> document and dies.
 */
class WP_Ajax_Response
{
    public $responses = [];

    public function __construct($args = '')
    {
        if (!empty($args)) {
            $this->add($args);
        }
    }

    public function add($args = '')
    {
        $defaults = ['what' => 'object', 'action' => false, 'id' => '0', 'old_id' => false, 'position' => 1, 'data' => '', 'supplemental' => []];
        $parsed = wp_parse_args($args, $defaults);
        $position = (string) preg_replace('/[^a-z0-9:_-]/i', '', (string) $parsed['position']);
        $id = $parsed['id'];
        if (is_wp_error($id)) {
            $data = '';
            foreach ((array) $id->get_error_codes() as $code) {
                $errorData = $id->get_error_data($code);
                $data .= "<wp_error code='" . esc_attr((string) $code) . "'><![CDATA[" . $id->get_error_message($code) . ']]></wp_error>';
                if ($errorData) {
                    $data .= "<wp_error_data code='" . esc_attr((string) $code) . "'><![CDATA[" . maybe_serialize($errorData) . ']]></wp_error_data>';
                }
            }
            $id = 0;
        } else {
            $data = is_wp_error($parsed['data'])
                ? "<wp_error code='" . esc_attr((string) $parsed['data']->get_error_code()) . "'><![CDATA[" . $parsed['data']->get_error_message() . ']]></wp_error>'
                : '<response_data><![CDATA[' . $parsed['data'] . ']]></response_data>';
        }
        $supplemental = '';
        foreach ((array) $parsed['supplemental'] as $key => $value) {
            $supplemental .= '<' . $key . '><![CDATA[' . $value . ']]></' . $key . '>';
        }
        $action = $parsed['action'] ? (string) $parsed['action'] : (string) ($_POST['action'] ?? '');
        $response = "<response action='" . $action . '_' . $id . "'>"
            . "<{$parsed['what']} id='{$id}'" . ($parsed['old_id'] !== false ? " old_id='{$parsed['old_id']}'" : '') . " position='{$position}'>"
            . $data . '<supplemental>' . $supplemental . '</supplemental>'
            . "</{$parsed['what']}></response>";
        $this->responses[] = $response;
        return $response;
    }

    public function send()
    {
        header('Content-Type: text/xml; charset=' . get_option('blog_charset'));
        echo "<?xml version='1.0' encoding='" . get_option('blog_charset') . "' standalone='yes'?><wp_ajax>";
        foreach ($this->responses as $response) {
            echo $response;
        }
        echo '</wp_ajax>';
        wp_die();
    }
}
