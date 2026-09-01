# `Minn\Http`

request, response, routing, and the outgoing client

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Client`](#client) | final class | 98 | The engine's outgoing HTTP transport over curl. Redirects are followed by |
| [`Exchange`](#exchange) | final readonly class | 28 | What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error. |
| [`Failure`](#failure) | final class | 112 | What the public sees when the engine cannot answer: a plain page with no |
| [`Kernel`](#kernel) | final readonly class | 35 | The edge. Turns a request into a response through the router and turns |
| [`Method`](#method) | enum | 32 |  |
| [`Outbound`](#outbound) | final readonly class | 35 | One outgoing HTTP request, normalised: the client below needs nothing else. |
| [`Request`](#request) | final readonly class | 113 | An immutable picture of the incoming request. Built once from the PHP |
| [`Response`](#response) | final readonly class | 62 | What a handler returns. Nothing is written to the client until the |
| [`Route`](#route) | final readonly class | 30 | Declares a handler method as a route. The capability requirement lives |
| [`Router`](#router) | final class | 62 | Matches a request to a #[Route] on one of the registered handler |

## Client

`final class Minn\Http\Client` · `public/minn/src/Minn/Http/Client.php`

The engine's outgoing HTTP transport over curl. Redirects are followed by
curl, so the header lines of every hop arrive in order; only the last
response's block is kept, the way plugin code expects to read it.

### static `get(string $url, array $headers = array ( ), float $timeout = 5.0): Minn\Http\Exchange`

The common cases, so a one-off request needs no Outbound at the call site.

### static `post(string $url, ?string $body = NULL, array $headers = array ( ), float $timeout = 5.0): Minn\Http\Exchange`

### static `head(string $url, array $headers = array ( ), float $timeout = 5.0): Minn\Http\Exchange`

### static `send(Minn\Http\Outbound $request): Minn\Http\Exchange`


## Exchange

`final readonly class Minn\Http\Exchange` · `public/minn/src/Minn/Http/Exchange.php`

What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error.

```php
__construct(int $code, array $headers, array $cookies, string $body, ?string $error = NULL)
```
- `@param array<string, string|list<string>> $headers`
- `@param list<string> $cookies raw Set-Cookie header values`

- readonly `int $code`
- readonly `array $headers`
- readonly `array $cookies`
- readonly `string $body`
- readonly `?string $error`

### `failed(): bool`

### `ok(): bool`

A response arrived and it was a 2xx.

### `json(): mixed`

The body decoded as JSON, or null when it is not JSON.


## Failure

`final class Minn\Http\Failure` · `public/minn/src/Minn/Http/Failure.php`

What the public sees when the engine cannot answer: a plain page with no
detail, while the detail goes to the log. Installed once per request,
it turns display_errors off (unless the site's own WP_DEBUG_DISPLAY asks
for them) and catches the fatal errors PHP would otherwise print.

### static `onFatal(callable $recorder): void`

What to do with a fatal beyond showing the page: the engine records
the extension it came from so the next request loads without it.
Installed once the runtime is up, since it needs the database.

- `@param callable(array{type:int,file:string,line:int,message:string}): void $recorder`

### static `install(): void`

### static `report(Throwable $error): Minn\Http\Response`

Logs the cause; the response says only that something went wrong.

### static `internal(): Minn\Http\Response`

### static `databaseUnavailable(): Minn\Http\Response`

### static `detailed(string $class, string $message, string $file, int $line): Minn\Http\Response`

The same page with the cause on it, for a site that asked to see
errors. Only ever reached when WP_DEBUG_DISPLAY (or WP_DEBUG) is on:
a site that has not asked never learns this much from a response.


## Kernel

`final readonly class Minn\Http\Kernel` · `public/minn/src/Minn/Http/Kernel.php`

The edge. Turns a request into a response through the router and turns
a RestError thrown anywhere underneath into the WordPress error shape.

```php
__construct(Minn\Http\Router $router)
```

### `handle(Minn\Http\Request $request): ?Minn\Http\Response`


## Method

`enum Minn\Http\Method` · `public/minn/src/Minn/Http/Method.php`

Cases: `Get` = `'GET'`, `Head` = `'HEAD'`, `Post` = `'POST'`, `Put` = `'PUT'`, `Patch` = `'PATCH'`, `Delete` = `'DELETE'`, `Options` = `'OPTIONS'`, `Any` = `'*'`

### static `fromName(string $name): self`

### `matches(self $declared): bool`

HEAD is served by GET handlers; the kernel drops the body.

### `canonicalRedirects(): bool`

Canonical redirects (trailing slash, pretty-URL mapping, 404 guessing)
run only for reads; every other method renders the URL as typed.


## Outbound

`final readonly class Minn\Http\Outbound` · `public/minn/src/Minn/Http/Outbound.php`

One outgoing HTTP request, normalised: the client below needs nothing else.

```php
__construct(string $method, string $url, array $headers = array ( ), ?string $body = NULL, float $timeout = 5.0, int $redirects = 5, bool $verifySsl = true, string $userAgent = '', ?string $caInfo = NULL, bool $blocking = true)
```
- `@param list<string> $headers "Name: value" lines`

- readonly `string $method`
- readonly `string $url`
- readonly `array $headers`
- readonly `?string $body`
- readonly `float $timeout`
- readonly `int $redirects`
- readonly `bool $verifySsl`
- readonly `string $userAgent`
- readonly `?string $caInfo`
- readonly `bool $blocking`

### static `get(string $url, array $headers = array ( ), float $timeout = 5.0): self`

- `@param list<string> $headers "Name: value" lines`

### static `post(string $url, ?string $body = NULL, array $headers = array ( ), float $timeout = 5.0): self`

- `@param list<string> $headers "Name: value" lines`

### static `head(string $url, array $headers = array ( ), float $timeout = 5.0): self`

- `@param list<string> $headers "Name: value" lines`


## Request

`final readonly class Minn\Http\Request` · `public/minn/src/Minn/Http/Request.php`

An immutable picture of the incoming request. Built once from the PHP
globals at the edge; handlers only ever see this object.

```php
__construct(Minn\Http\Method $method, string $path, array $query, array $headers, array $cookies, string $body, bool $secure, string $host, array $form = array ( ), array $files = array ( ), string $remoteAddress = '', array $server = array ( ))
```
- `@param array<string, string|array> $query`
- `@param array<string, string> $headers lower-cased names`
- `@param array<string, string> $cookies`
- `@param array<string, mixed> $form decoded form fields, for bodies that are not JSON`
- `@param array<string, array> $files uploaded files, keyed by field`

- readonly `Minn\Http\Method $method`
- readonly `string $path`
- readonly `array $query`
- readonly `array $headers`
- readonly `array $cookies`
- readonly `string $body`
- readonly `bool $secure`
- readonly `string $host`
- readonly `array $form`
- readonly `array $files`
- readonly `string $remoteAddress`
- readonly `array $server` — what the server says about itself: software, protocol, and address; diagnostics only

### static `fromGlobals(): self`

### `json(): array`

The JSON body as an array, or the form fields when the body is empty.

### `withPath(string $path): self`

The same request addressed to another path (a REST route carried in ?rest_route=).

### `query(string $key, ?string $default = NULL): ?string`

### `has(string $key): bool`

### `header(string $name): ?string`

### `cookie(string $name): ?string`

### `segments(): array`

The path split into non-empty segments: "/a/b/" becomes ["a", "b"].

### `queryStringWithout(string ...$keys): string`

The query string with the given keys removed, ready to append to a redirect.


## Response

`final readonly class Minn\Http\Response` · `public/minn/src/Minn/Http/Response.php`

What a handler returns. Nothing is written to the client until the
kernel calls send(), so a response can be inspected, wrapped, or
replaced on the way out.

```php
__construct(int $status = 200, array $headers = array ( ), string $body = '', array $cookies = array ( ))
```
- `@param array<string, string> $headers`
- `@param list<array{0: string, 1: string, 2: array}> $cookies name, value, setcookie options`

- readonly `int $status`
- readonly `array $headers`
- readonly `string $body`
- readonly `array $cookies`

### static `html(string $body, int $status = 200): self`

### static `json(mixed $payload, int $status = 200): self`

### static `redirect(string $location, int $status = 301): self`

### `withHeader(string $name, string $value): self`

### `withCookie(string $name, string $value, array $options): self`

- `@param array<string, mixed> $options setcookie options: expires, path, secure, httponly, samesite`

### `withoutBody(): self`

### `send(): never`


## Route

`final readonly class Minn\Http\Route` · `public/minn/src/Minn/Http/Route.php`

Declares a handler method as a route. The capability requirement lives
here, as metadata the router enforces before the handler runs, so the
authorization surface of the engine is a grep away.

Patterns: "/wp/v2/posts/{id}" captures one segment, "{id:\d+}" constrains
it, and "/{path*}" captures the rest of the path (slashes included).

```php
__construct(Minn\Http\Method $method, string $pattern, ?string $cap = NULL)
```

- readonly `Minn\Http\Method $method`
- readonly `string $pattern`
- readonly `?string $cap`

### `regex(): string`


## Router

`final class Minn\Http\Router` · `public/minn/src/Minn/Http/Router.php`

Matches a request to a #[Route] on one of the registered handler
objects, enforces the declared capability, and invokes the method with
the request plus the named pattern captures.

```php
__construct(?Closure $gate = NULL)
```
- `@param Closure(string $cap, Request $request): void $gate throws RestError when the capability is missing`

### `register(object ...$handlers): self`

### `routes(): array`

The registered routes, for an index: pattern => methods. @return array<string, list<string>>

- `@return array<string, list<string>>`

### `dispatch(Minn\Http\Request $request): ?Minn\Http\Response`

Null when nothing matched, so the caller can fall through.

