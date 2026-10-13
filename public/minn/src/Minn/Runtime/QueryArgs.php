<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Request;
use Minn\Support\Escape;
use Minn\Support\Text;
use Minn\Support\Url;

/**
 * Query arguments added to and taken from an address the way WordPress's
 * add_query_arg() and remove_query_arg() do, the current request's address
 * when none is given, and an action's nonce added to one.
 */
final class QueryArgs
{
    /**
     * An address with arguments added (add_query_arg): a key and value, or a
     * list of them, then the address (the request's when false or left
     * out). Values are URL-encoded; an empty one leaves its key bare, a
     * false or null one takes the key out.
     */
    public static function add(mixed ...$args): string
    {
        $current = self::requestUri();
        if (is_array($args[0])) {
            $uri = count($args) < 2 || $args[1] === false ? $current : (string) $args[1];
            $new = $args[0];
        } else {
            $uri = count($args) < 3 || $args[2] === false ? $current : (string) $args[2];
            $new = [$args[0] => $args[1] ?? null];
        }
        return Url::withQuery($uri, $new, static fn (array $q): array => Text::mapDeep($q, 'urlencode'), static fn (array $q): string => (string) preg_replace('#=(&|$)#', '$1', Url::buildQuery($q)));
    }

    /** An address with an argument, or each of a list, taken out (remove_query_arg); an empty list gives back what was given. */
    public static function remove(mixed $key, mixed $query = false): mixed
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $query = self::add($k, false, $query);
            }
            return $query;
        }
        return self::add($key, false, $query);
    }

    /** An address carrying a nonce for the action (wp_nonce_url), escaped for HTML. */
    public static function withNonce(mixed $actionUrl, mixed $action = -1, mixed $name = '_wpnonce'): string
    {
        return Escape::html(self::add($name, Handoff::createNonce($action), str_replace('&amp;', '&', (string) $actionUrl)));
    }

    /** The request's address as PHP was given it; a runtime built without one gives its request's path and query. */
    public static function requestUri(): string
    {
        $uri = Request::serverVariable('REQUEST_URI');
        if ($uri !== null) {
            return (string) $uri;
        }
        $request = Runtime::current()->request;
        if ($request === null) {
            return '';
        }
        $query = http_build_query($request->query);
        return $request->path . ($query === '' ? '' : '?' . $query);
    }
}
