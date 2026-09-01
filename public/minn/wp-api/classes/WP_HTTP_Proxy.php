<?php

/**
 * The outbound proxy the WP_PROXY_* constants describe. Nothing is configured
 * unless the site's own config says so, and a request to the local host never
 * goes through a proxy even when one is.
 */
class WP_HTTP_Proxy
{
    public function is_enabled()
    {
        return defined('WP_PROXY_HOST') && defined('WP_PROXY_PORT');
    }

    public function use_authentication()
    {
        return defined('WP_PROXY_USERNAME') && defined('WP_PROXY_PASSWORD');
    }

    public function host()
    {
        return defined('WP_PROXY_HOST') ? WP_PROXY_HOST : '';
    }

    public function port()
    {
        return defined('WP_PROXY_PORT') ? WP_PROXY_PORT : '';
    }

    public function username()
    {
        return defined('WP_PROXY_USERNAME') ? WP_PROXY_USERNAME : '';
    }

    public function password()
    {
        return defined('WP_PROXY_PASSWORD') ? WP_PROXY_PASSWORD : '';
    }

    public function authentication()
    {
        return $this->username() . ':' . $this->password();
    }

    public function authentication_header()
    {
        return 'Proxy-Authorization: Basic ' . base64_encode($this->authentication());
    }

    public function send_through_proxy($uri)
    {
        // Only the name localhost and the site's own host skip the proxy; a
        // loopback address written out in full does not.
        $host = parse_url((string) $uri, PHP_URL_HOST);
        $home = parse_url(get_option('home'), PHP_URL_HOST);
        if ($host === 'localhost' || ($home !== null && $host === $home)) {
            return false;
        }
        if (!defined('WP_PROXY_BYPASS_HOSTS')) {
            return true;
        }
        $bypass = array_map('trim', explode(',', (string) WP_PROXY_BYPASS_HOSTS));
        foreach ($bypass as $pattern) {
            if ($pattern === $host) {
                return false;
            }
            if (str_starts_with($pattern, '*.') && str_ends_with((string) $host, substr($pattern, 1))) {
                return false;
            }
        }
        return true;
    }
}
