<?php
/**
 * The WordPress side of the AI client (probe ai-client-adapters): a PSR-16
 * cache over the object cache's wp_ai_client group, a PSR-14 dispatcher that
 * fires a wp_ai_client_ action for each event, a PSR-18 client over the HTTP
 * API, and the discovery strategy that hands the SDK that client.
 */

use WordPress\AiClient\Providers\Http\Abstracts\AbstractClientDiscoveryStrategy;
use WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Exception\NetworkException;
use WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory;
use WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface;
use WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface;
use WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface;

/** The SDK's cache: the object cache's wp_ai_client group. A stored null or false is found as stored; a TTL passes to the cache as given. */
class WP_AI_Client_Cache implements CacheInterface
{
    private function get_cache_group()
    {
        return apply_filters('wp_ai_client_cache_group', 'wp_ai_client');
    }

    public function get($key, $default_value = null)
    {
        $found = false;
        $value = wp_cache_get((string) $key, $this->get_cache_group(), false, $found);
        return $found ? $value : $default_value;
    }

    public function set($key, $value, $ttl = null): bool
    {
        return wp_cache_set((string) $key, $value, $this->get_cache_group(), $this->ttl_to_seconds($ttl));
    }

    public function delete($key): bool
    {
        return wp_cache_delete((string) $key, $this->get_cache_group());
    }

    public function clear(): bool
    {
        return wp_cache_flush_group($this->get_cache_group());
    }

    public function getMultiple($keys, $default_value = null): array
    {
        $values = [];
        foreach ($this->iterable_to_array($keys) as $key) {
            $values[$key] = $this->get($key, $default_value);
        }
        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $stored = true;
        foreach ($this->iterable_to_array($values) as $key => $value) {
            $stored = $this->set($key, $value, $ttl) && $stored;
        }
        return $stored;
    }

    public function deleteMultiple($keys): bool
    {
        $deleted = true;
        foreach ($this->iterable_to_array($keys) as $key) {
            $deleted = $this->delete($key) && $deleted;
        }
        return $deleted;
    }

    public function has($key): bool
    {
        $found = false;
        wp_cache_get((string) $key, $this->get_cache_group(), false, $found);
        return (bool) $found;
    }

    /** A TTL in seconds: none for null, an interval's length from now. */
    private function ttl_to_seconds($ttl)
    {
        if ($ttl instanceof DateInterval) {
            return (new DateTime())->add($ttl)->getTimestamp() - time();
        }
        return (int) $ttl;
    }

    private function iterable_to_array($items)
    {
        return is_array($items) ? $items : iterator_to_array($items);
    }
}

/** Each SDK event as the action wp_ai_client_{its class name, snake case, without "Event"}, given the event. */
class WP_AI_Client_Event_Dispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        do_action('wp_ai_client_' . $this->get_hook_name_portion_for_event($event), $event);
        return $event;
    }

    private function get_hook_name_portion_for_event(object $event)
    {
        $name = substr((string) strrchr('\\' . get_class($event), '\\'), 1);
        $name = str_ends_with($name, 'Event') ? substr($name, 0, -5) : $name;
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name));
    }
}

/**
 * The SDK's HTTP client over wp_safe_remote_request: the request's method,
 * headers (joined by commas), body and protocol, and the options' timeout and
 * redirect limit; the answer as a PSR-7 response, a failure as a
 * NetworkException.
 */
class WP_AI_Client_HTTP_Client implements ClientInterface, ClientWithOptionsInterface
{
    private $response_factory;
    private $stream_factory;

    public function __construct(ResponseFactoryInterface $response_factory, StreamFactoryInterface $stream_factory)
    {
        $this->response_factory = $response_factory;
        $this->stream_factory = $stream_factory;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->minn_send($request, $this->prepare_wp_args($request));
    }

    public function sendRequestWithOptions(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        return $this->minn_send($request, $this->prepare_wp_args($request, $options));
    }

    private function minn_send(RequestInterface $request, array $args): ResponseInterface
    {
        $response = wp_safe_remote_request((string) $request->getUri(), $args);
        if (is_wp_error($response)) {
            throw new NetworkException(sprintf('Network error occurred while sending %s request to %s: %s', $request->getMethod(), (string) $request->getUri(), $response->get_error_message()));
        }
        return $this->create_psr_response($response);
    }

    private function prepare_wp_args(RequestInterface $request, ?RequestOptions $options = null)
    {
        $args = ['method' => $request->getMethod(), 'headers' => $this->prepare_headers($request), 'body' => $this->prepare_body($request), 'httpversion' => $request->getProtocolVersion()];
        if ($options !== null && $options->getTimeout() !== null) {
            $args['timeout'] = $options->getTimeout();
        }
        if ($options !== null && $options->getMaxRedirects() !== null) {
            $args['redirection'] = $options->getMaxRedirects();
        }
        return $args;
    }

    private function prepare_headers(RequestInterface $request)
    {
        return array_map(static fn (array $values) => implode(', ', $values), $request->getHeaders());
    }

    private function prepare_body(RequestInterface $request)
    {
        $body = (string) $request->getBody();
        return $body === '' ? null : $body;
    }

    private function create_psr_response(array $wp_response)
    {
        $response = $this->response_factory->createResponse((int) wp_remote_retrieve_response_code($wp_response), (string) wp_remote_retrieve_response_message($wp_response));
        foreach (wp_remote_retrieve_headers($wp_response) as $name => $value) {
            $response = $response->withHeader((string) $name, $value);
        }
        return $response->withBody($this->stream_factory->createStream((string) wp_remote_retrieve_body($wp_response)));
    }
}

/** The discovery strategy that offers the SDK WP_AI_Client_HTTP_Client. */
class WP_AI_Client_Discovery_Strategy extends AbstractClientDiscoveryStrategy
{
    protected static function createClient(Psr17Factory $psr17_factory): ClientInterface
    {
        return new WP_AI_Client_HTTP_Client($psr17_factory, $psr17_factory);
    }
}
