<?php
/**
 * PSR-7 messages and the PSR-17 factory, under the names the reference's AI
 * client ships them with (Nyholm's): immutable requests, responses, streams
 * over php://temp, URIs, server requests and uploaded files. A request takes
 * its Host header from its URI, first among its headers, unless it was given
 * one. Written from the PSR-7 standard.
 */

namespace WordPress\AiClientDependencies\Nyholm\Psr7 {
    use WordPress\AiClientDependencies\Psr\Http\Message\MessageInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\ServerRequestInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\UploadedFileInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;

    trait MessageTrait
    {
        /** @var array<string, list<string>> */
        private array $headers = [];
        /** @var array<string, string> lower-case name => name as given */
        private array $headerNames = [];
        private string $protocol = '1.1';
        private ?StreamInterface $stream = null;

        public function getProtocolVersion(): string
        {
            return $this->protocol;
        }

        public function withProtocolVersion(string $version): MessageInterface
        {
            $new = clone $this;
            $new->protocol = $version;
            return $new;
        }

        public function getHeaders(): array
        {
            return $this->headers;
        }

        public function hasHeader(string $name): bool
        {
            return isset($this->headerNames[strtolower($name)]);
        }

        public function getHeader(string $name): array
        {
            $name = $this->headerNames[strtolower($name)] ?? null;
            return $name === null ? [] : $this->headers[$name];
        }

        public function getHeaderLine(string $name): string
        {
            return implode(', ', $this->getHeader($name));
        }

        public function withHeader(string $name, $value): MessageInterface
        {
            $new = $this->withoutHeader($name);
            $new->headerNames[strtolower($name)] = $name;
            $new->headers[$name] = self::values($value);
            return $new;
        }

        public function withAddedHeader(string $name, $value): MessageInterface
        {
            if (!$this->hasHeader($name)) {
                return $this->withHeader($name, $value);
            }
            $new = clone $this;
            $given = $new->headerNames[strtolower($name)];
            $new->headers[$given] = [...$new->headers[$given], ...self::values($value)];
            return $new;
        }

        public function withoutHeader(string $name): MessageInterface
        {
            $new = clone $this;
            $given = $new->headerNames[strtolower($name)] ?? null;
            if ($given !== null) {
                unset($new->headers[$given], $new->headerNames[strtolower($name)]);
            }
            return $new;
        }

        public function getBody(): StreamInterface
        {
            return $this->stream ??= Stream::create('');
        }

        public function withBody(StreamInterface $body): MessageInterface
        {
            $new = clone $this;
            $new->stream = $body;
            return $new;
        }

        /** @return list<string> */
        private static function values($value): array
        {
            return array_values(array_map(static fn ($item) => trim((string) $item, " \t"), is_array($value) ? $value : [$value]));
        }

        private function setHeaders(array $headers): void
        {
            foreach ($headers as $name => $value) {
                $this->headerNames[strtolower((string) $name)] = (string) $name;
                $this->headers[(string) $name] = self::values($value);
            }
        }
    }

    final class Stream implements StreamInterface
    {
        /** @var resource|null */
        private $resource;

        /** @param resource $resource */
        private function __construct($resource)
        {
            $this->resource = $resource;
        }

        /** A stream of the string, the resource, or the stream given. */
        public static function create($body = ''): StreamInterface
        {
            if ($body instanceof StreamInterface) {
                return $body;
            }
            if (is_resource($body)) {
                return new self($body);
            }
            $resource = fopen('php://temp', 'r+');
            fwrite($resource, (string) $body);
            rewind($resource);
            return new self($resource);
        }

        public function __toString(): string
        {
            if ($this->resource === null) {
                return '';
            }
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        }

        public function close(): void
        {
            if (is_resource($this->resource)) {
                fclose($this->resource);
            }
            $this->resource = null;
        }

        public function detach()
        {
            [$resource, $this->resource] = [$this->resource, null];
            return $resource;
        }

        public function getSize(): ?int
        {
            return $this->resource === null ? null : (fstat($this->resource)['size'] ?? null);
        }

        public function tell(): int
        {
            return $this->resource === null ? 0 : (int) ftell($this->resource);
        }

        public function eof(): bool
        {
            return $this->resource === null || feof($this->resource);
        }

        public function isSeekable(): bool
        {
            return (bool) $this->getMetadata('seekable');
        }

        public function seek(int $offset, int $whence = SEEK_SET): void
        {
            if ($this->resource === null || fseek($this->resource, $offset, $whence) === -1) {
                throw new \RuntimeException('Unable to seek to stream position "' . $offset . '"');
            }
        }

        public function rewind(): void
        {
            $this->seek(0);
        }

        public function isWritable(): bool
        {
            $mode = (string) $this->getMetadata('mode');
            return strpbrk($mode, 'waxc+') !== false;
        }

        public function write(string $string): int
        {
            if ($this->resource === null || ($written = fwrite($this->resource, $string)) === false) {
                throw new \RuntimeException('Unable to write to stream');
            }
            return $written;
        }

        public function isReadable(): bool
        {
            $mode = (string) $this->getMetadata('mode');
            return strpbrk($mode, 'r+') !== false;
        }

        public function read(int $length): string
        {
            if ($this->resource === null || ($read = fread($this->resource, $length)) === false) {
                throw new \RuntimeException('Unable to read from stream');
            }
            return $read;
        }

        public function getContents(): string
        {
            return $this->resource === null ? '' : (string) stream_get_contents($this->resource);
        }

        public function getMetadata(?string $key = null)
        {
            $meta = $this->resource === null ? [] : stream_get_meta_data($this->resource);
            return $key === null ? $meta : ($meta[$key] ?? null);
        }
    }

    final class Uri implements UriInterface
    {
        private string $scheme = '';
        private string $userInfo = '';
        private string $host = '';
        private ?int $port = null;
        private string $path = '';
        private string $query = '';
        private string $fragment = '';

        public function __construct(string $uri = '')
        {
            $parts = $uri === '' ? [] : parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException('Unable to parse URI: ' . $uri);
            }
            $this->scheme = strtolower($parts['scheme'] ?? '');
            $this->userInfo = ($parts['user'] ?? '') . (isset($parts['pass']) ? ':' . $parts['pass'] : '');
            $this->host = strtolower($parts['host'] ?? '');
            $this->port = isset($parts['port']) ? $this->filterPort((int) $parts['port']) : null;
            [$this->path, $this->query, $this->fragment] = [$parts['path'] ?? '', $parts['query'] ?? '', $parts['fragment'] ?? ''];
        }

        public function getScheme(): string
        {
            return $this->scheme;
        }

        public function getAuthority(): string
        {
            if ($this->host === '') {
                return '';
            }
            return ($this->userInfo !== '' ? $this->userInfo . '@' : '') . $this->host . ($this->port !== null ? ':' . $this->port : '');
        }

        public function getUserInfo(): string
        {
            return $this->userInfo;
        }

        public function getHost(): string
        {
            return $this->host;
        }

        public function getPort(): ?int
        {
            return $this->port;
        }

        public function getPath(): string
        {
            return $this->path;
        }

        public function getQuery(): string
        {
            return $this->query;
        }

        public function getFragment(): string
        {
            return $this->fragment;
        }

        public function withScheme(string $scheme): UriInterface
        {
            $new = clone $this;
            $new->scheme = strtolower($scheme);
            $new->port = $new->port === null ? null : $new->filterPort($new->port);
            return $new;
        }

        public function withUserInfo(string $user, ?string $password = null): UriInterface
        {
            $new = clone $this;
            $new->userInfo = $user . ($password !== null && $password !== '' ? ':' . $password : '');
            return $new;
        }

        public function withHost(string $host): UriInterface
        {
            $new = clone $this;
            $new->host = strtolower($host);
            return $new;
        }

        public function withPort(?int $port): UriInterface
        {
            $new = clone $this;
            $new->port = $port === null ? null : $new->filterPort($port);
            return $new;
        }

        public function withPath(string $path): UriInterface
        {
            $new = clone $this;
            $new->path = $path;
            return $new;
        }

        public function withQuery(string $query): UriInterface
        {
            $new = clone $this;
            $new->query = ltrim($query, '?');
            return $new;
        }

        public function withFragment(string $fragment): UriInterface
        {
            $new = clone $this;
            $new->fragment = ltrim($fragment, '#');
            return $new;
        }

        public function __toString(): string
        {
            $authority = $this->getAuthority();
            $path = $authority !== '' && $this->path !== '' && $this->path[0] !== '/' ? '/' . $this->path : $this->path;
            return ($this->scheme !== '' ? $this->scheme . ':' : '') . ($authority !== '' ? '//' . $authority : '') . $path
                . ($this->query !== '' ? '?' . $this->query : '') . ($this->fragment !== '' ? '#' . $this->fragment : '');
        }

        /** A port the scheme uses by default is left out. */
        private function filterPort(int $port): ?int
        {
            return (['http' => 80, 'https' => 443][$this->scheme] ?? null) === $port ? null : $port;
        }
    }

    class Request implements RequestInterface
    {
        use MessageTrait;

        private string $method;
        private UriInterface $uri;
        private ?string $requestTarget = null;

        public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
        {
            $this->method = $method;
            $this->uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
            $this->protocol = $version;
            $this->setHeaders($headers);
            if (!$this->hasHeader('Host')) {
                $this->updateHostFromUri();
            }
            if ($body !== null && $body !== '') {
                $this->stream = Stream::create($body);
            }
        }

        public function getRequestTarget(): string
        {
            if ($this->requestTarget !== null) {
                return $this->requestTarget;
            }
            $target = $this->uri->getPath() === '' ? '/' : $this->uri->getPath();
            return $target . ($this->uri->getQuery() !== '' ? '?' . $this->uri->getQuery() : '');
        }

        public function withRequestTarget(string $requestTarget): RequestInterface
        {
            $new = clone $this;
            $new->requestTarget = $requestTarget;
            return $new;
        }

        public function getMethod(): string
        {
            return $this->method;
        }

        public function withMethod(string $method): RequestInterface
        {
            $new = clone $this;
            $new->method = $method;
            return $new;
        }

        public function getUri(): UriInterface
        {
            return $this->uri;
        }

        public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
        {
            $new = clone $this;
            $new->uri = $uri;
            if (!$preserveHost || !$new->hasHeader('Host')) {
                $new->updateHostFromUri();
            }
            return $new;
        }

        /** The Host header from the URI, first among the headers. */
        private function updateHostFromUri(): void
        {
            $host = $this->uri->getHost();
            if ($host === '') {
                return;
            }
            $host .= $this->uri->getPort() !== null ? ':' . $this->uri->getPort() : '';
            $given = $this->headerNames['host'] ?? 'Host';
            unset($this->headers[$given]);
            $this->headerNames['host'] = 'Host';
            $this->headers = ['Host' => [$host]] + $this->headers;
        }
    }

    class Response implements ResponseInterface
    {
        use MessageTrait;

        private int $statusCode;
        private string $reasonPhrase;

        public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
        {
            $this->statusCode = $status;
            $this->reasonPhrase = (string) $reason;
            $this->protocol = $version;
            $this->setHeaders($headers);
            if ($body !== null && $body !== '') {
                $this->stream = Stream::create($body);
            }
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
        {
            $new = clone $this;
            [$new->statusCode, $new->reasonPhrase] = [$code, $reasonPhrase];
            return $new;
        }

        public function getReasonPhrase(): string
        {
            return $this->reasonPhrase;
        }
    }

    class ServerRequest extends Request implements ServerRequestInterface
    {
        private array $serverParams;
        private array $cookieParams = [];
        private array $queryParams = [];
        private array $uploadedFiles = [];
        private $parsedBody = null;
        private array $attributes = [];

        public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])
        {
            parent::__construct($method, $uri, $headers, $body, $version);
            $this->serverParams = $serverParams;
        }

        public function getServerParams(): array
        {
            return $this->serverParams;
        }

        public function getCookieParams(): array
        {
            return $this->cookieParams;
        }

        public function withCookieParams(array $cookies): ServerRequestInterface
        {
            $new = clone $this;
            $new->cookieParams = $cookies;
            return $new;
        }

        public function getQueryParams(): array
        {
            return $this->queryParams;
        }

        public function withQueryParams(array $query): ServerRequestInterface
        {
            $new = clone $this;
            $new->queryParams = $query;
            return $new;
        }

        public function getUploadedFiles(): array
        {
            return $this->uploadedFiles;
        }

        public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
        {
            $new = clone $this;
            $new->uploadedFiles = $uploadedFiles;
            return $new;
        }

        public function getParsedBody()
        {
            return $this->parsedBody;
        }

        public function withParsedBody($data): ServerRequestInterface
        {
            $new = clone $this;
            $new->parsedBody = $data;
            return $new;
        }

        public function getAttributes(): array
        {
            return $this->attributes;
        }

        public function getAttribute(string $name, $default = null)
        {
            return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
        }

        public function withAttribute(string $name, $value): ServerRequestInterface
        {
            $new = clone $this;
            $new->attributes[$name] = $value;
            return $new;
        }

        public function withoutAttribute(string $name): ServerRequestInterface
        {
            $new = clone $this;
            unset($new->attributes[$name]);
            return $new;
        }
    }

    class UploadedFile implements UploadedFileInterface
    {
        public function __construct(private StreamInterface $stream, private ?int $size, private int $error, private ?string $clientFilename = null, private ?string $clientMediaType = null)
        {
        }

        public function getStream(): StreamInterface
        {
            return $this->stream;
        }

        public function moveTo(string $targetPath): void
        {
            if (file_put_contents($targetPath, (string) $this->stream) === false) {
                throw new \RuntimeException('Unable to write to "' . $targetPath . '"');
            }
        }

        public function getSize(): ?int
        {
            return $this->size;
        }

        public function getError(): int
        {
            return $this->error;
        }

        public function getClientFilename(): ?string
        {
            return $this->clientFilename;
        }

        public function getClientMediaType(): ?string
        {
            return $this->clientMediaType;
        }
    }
}

namespace WordPress\AiClientDependencies\Nyholm\Psr7\Factory {
    use WordPress\AiClientDependencies\Nyholm\Psr7\Request;
    use WordPress\AiClientDependencies\Nyholm\Psr7\Response;
    use WordPress\AiClientDependencies\Nyholm\Psr7\ServerRequest;
    use WordPress\AiClientDependencies\Nyholm\Psr7\Stream;
    use WordPress\AiClientDependencies\Nyholm\Psr7\UploadedFile;
    use WordPress\AiClientDependencies\Nyholm\Psr7\Uri;
    use WordPress\AiClientDependencies\Psr\Http\Message as Message;

    final class Psr17Factory implements Message\RequestFactoryInterface, Message\ResponseFactoryInterface, Message\ServerRequestFactoryInterface, Message\StreamFactoryInterface, Message\UploadedFileFactoryInterface, Message\UriFactoryInterface
    {
        public function createRequest(string $method, $uri): Message\RequestInterface
        {
            return new Request($method, $uri);
        }

        public function createResponse(int $code = 200, string $reasonPhrase = ''): Message\ResponseInterface
        {
            return new Response($code, [], null, '1.1', $reasonPhrase);
        }

        public function createStream(string $content = ''): Message\StreamInterface
        {
            return Stream::create($content);
        }

        public function createStreamFromFile(string $filename, string $mode = 'r'): Message\StreamInterface
        {
            $resource = @fopen($filename, $mode);
            if ($resource === false) {
                throw new \RuntimeException('The file "' . $filename . '" cannot be opened.');
            }
            return Stream::create($resource);
        }

        public function createStreamFromResource($resource): Message\StreamInterface
        {
            return Stream::create($resource);
        }

        public function createUploadedFile(Message\StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): Message\UploadedFileInterface
        {
            return new UploadedFile($stream, $size ?? $stream->getSize(), $error, $clientFilename, $clientMediaType);
        }

        public function createUri(string $uri = ''): Message\UriInterface
        {
            return new Uri($uri);
        }

        public function createServerRequest(string $method, $uri, array $serverParams = []): Message\ServerRequestInterface
        {
            return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
        }
    }
}
