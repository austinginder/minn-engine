<?php
/**
 * The PHP-FIG interfaces the AI client names, under the prefix the
 * reference ships them with: PSR-7 messages, PSR-14 event dispatching,
 * PSR-16 simple cache, PSR-17 factories, PSR-18 HTTP clients, and the
 * discovery strategy contract. Written from the published standards.
 */

namespace WordPress\AiClientDependencies\Psr\Http\Message {
    interface MessageInterface
    {
        public function getProtocolVersion(): string;
        public function withProtocolVersion(string $version): MessageInterface;
        public function getHeaders(): array;
        public function hasHeader(string $name): bool;
        public function getHeader(string $name): array;
        public function getHeaderLine(string $name): string;
        public function withHeader(string $name, $value): MessageInterface;
        public function withAddedHeader(string $name, $value): MessageInterface;
        public function withoutHeader(string $name): MessageInterface;
        public function getBody(): StreamInterface;
        public function withBody(StreamInterface $body): MessageInterface;
    }

    interface RequestInterface extends MessageInterface
    {
        public function getRequestTarget(): string;
        public function withRequestTarget(string $requestTarget): RequestInterface;
        public function getMethod(): string;
        public function withMethod(string $method): RequestInterface;
        public function getUri(): UriInterface;
        public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface;
    }

    interface ServerRequestInterface extends RequestInterface
    {
        public function getServerParams(): array;
        public function getCookieParams(): array;
        public function withCookieParams(array $cookies): ServerRequestInterface;
        public function getQueryParams(): array;
        public function withQueryParams(array $query): ServerRequestInterface;
        public function getUploadedFiles(): array;
        public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface;
        public function getParsedBody();
        public function withParsedBody($data): ServerRequestInterface;
        public function getAttributes(): array;
        public function getAttribute(string $name, $default = null);
        public function withAttribute(string $name, $value): ServerRequestInterface;
        public function withoutAttribute(string $name): ServerRequestInterface;
    }

    interface ResponseInterface extends MessageInterface
    {
        public function getStatusCode(): int;
        public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;
        public function getReasonPhrase(): string;
    }

    interface StreamInterface
    {
        public function __toString(): string;
        public function close(): void;
        public function detach();
        public function getSize(): ?int;
        public function tell(): int;
        public function eof(): bool;
        public function isSeekable(): bool;
        public function seek(int $offset, int $whence = SEEK_SET): void;
        public function rewind(): void;
        public function isWritable(): bool;
        public function write(string $string): int;
        public function isReadable(): bool;
        public function read(int $length): string;
        public function getContents(): string;
        public function getMetadata(?string $key = null);
    }

    interface UploadedFileInterface
    {
        public function getStream(): StreamInterface;
        public function moveTo(string $targetPath): void;
        public function getSize(): ?int;
        public function getError(): int;
        public function getClientFilename(): ?string;
        public function getClientMediaType(): ?string;
    }

    interface UriInterface
    {
        public function getScheme(): string;
        public function getAuthority(): string;
        public function getUserInfo(): string;
        public function getHost(): string;
        public function getPort(): ?int;
        public function getPath(): string;
        public function getQuery(): string;
        public function getFragment(): string;
        public function withScheme(string $scheme): UriInterface;
        public function withUserInfo(string $user, ?string $password = null): UriInterface;
        public function withHost(string $host): UriInterface;
        public function withPort(?int $port): UriInterface;
        public function withPath(string $path): UriInterface;
        public function withQuery(string $query): UriInterface;
        public function withFragment(string $fragment): UriInterface;
        public function __toString(): string;
    }

    interface RequestFactoryInterface
    {
        public function createRequest(string $method, $uri): RequestInterface;
    }

    interface ResponseFactoryInterface
    {
        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;
    }

    interface ServerRequestFactoryInterface
    {
        public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface;
    }

    interface StreamFactoryInterface
    {
        public function createStream(string $content = ''): StreamInterface;
        public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface;
        public function createStreamFromResource($resource): StreamInterface;
    }

    interface UploadedFileFactoryInterface
    {
        public function createUploadedFile(StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): UploadedFileInterface;
    }

    interface UriFactoryInterface
    {
        public function createUri(string $uri = ''): UriInterface;
    }
}

namespace WordPress\AiClientDependencies\Psr\Http\Client {
    use WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;

    interface ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface;
    }

    interface ClientExceptionInterface extends \Throwable
    {
    }
}

namespace WordPress\AiClientDependencies\Psr\EventDispatcher {
    interface EventDispatcherInterface
    {
        public function dispatch(object $event);
    }
}

namespace WordPress\AiClientDependencies\Psr\SimpleCache {
    interface CacheInterface
    {
        public function get($key, $default = null);
        public function set($key, $value, $ttl = null);
        public function delete($key);
        public function clear();
        public function getMultiple($keys, $default = null);
        public function setMultiple($values, $ttl = null);
        public function deleteMultiple($keys);
        public function has($key);
    }
}

namespace WordPress\AiClientDependencies\Http\Discovery\Strategy {
    interface DiscoveryStrategy
    {
        public static function getCandidates($type);
    }
}
