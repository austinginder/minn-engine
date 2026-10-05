<?php
/**
 * The pre-namespace names: the Requests class itself (a subclass of
 * WpOrg\Requests\Requests) and WordPress's own hooks class, which forwards
 * each Requests hook to the requests-{$hook} action and curl.before_send
 * to http_api_curl.
 */

// WP-CLI declares its own copy of both before WordPress loads.
if (!class_exists('Requests', false)) {
class Requests extends WpOrg\Requests\Requests
{
    /** The engine's autoloader already knows the classes. */
    public static function register_autoloader()
    {
    }

    public static function autoloader($class)
    {
        return class_exists((string) $class);
    }
}

}

if (!class_exists('WP_HTTP_Requests_Hooks', false)) {
#[AllowDynamicProperties]
class WP_HTTP_Requests_Hooks extends WpOrg\Requests\Hooks
{
    protected $url;
    protected $request = [];

    public function __construct($url, $request)
    {
        $this->url = $url;
        $this->request = $request;
    }

    public function dispatch($hook, $parameters = [])
    {
        $result = parent::dispatch($hook, $parameters);
        if ($hook === 'curl.before_send') {
            do_action_ref_array('http_api_curl', [&$parameters[0], $this->request, $this->url]);
        }
        do_action_ref_array("requests-{$hook}", $parameters);
        return $result;
    }
}
}
