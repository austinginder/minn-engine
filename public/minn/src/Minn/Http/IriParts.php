<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * An IRI (a URL that may carry non-ASCII text) split into its parts and
 * written back normalized: scheme and host in lower case, a scheme's
 * default port dropped, dot segments removed, and every character a URL
 * may not hold percent-encoded. The IRI form keeps non-ASCII characters;
 * the URI form encodes them too.
 */
final readonly class IriParts
{
    private const PORTS = ['acap' => 674, 'dict' => 2628, 'file' => null, 'http' => 80, 'https' => 443];

    public function __construct(
        public ?string $scheme,
        public ?string $userinfo,
        public ?string $host,
        public ?int $port,
        public string $path,
        public ?string $query,
        public ?string $fragment,
    ) {
    }

    /** The parts of an IRI (RFC 3986 appendix B). */
    public static function parse(string $iri): self
    {
        preg_match('%^(?:([^:/?#]+):)?(?://([^/?#]*))?([^?#]*)(?:\?([^#]*))?(?:#(.*))?$%s', $iri, $m, PREG_UNMATCHED_AS_NULL);
        $userinfo = $host = $port = null;
        if (isset($m[2])) {
            $authority = $m[2];
            if (($at = strrpos($authority, '@')) !== false) {
                [$userinfo, $authority] = [substr($authority, 0, $at), substr($authority, $at + 1)];
            }
            if (preg_match('/^(.*):(\d*)$/', $authority, $p) && !str_ends_with($authority, ']')) {
                [$authority, $port] = [$p[1], $p[2] === '' ? null : (int) $p[2]];
            }
            $host = $authority;
        }
        $path = (string) ($m[3] ?? '');
        return new self(isset($m[1]) ? strtolower($m[1]) : null, $userinfo, $host === null ? null : strtolower($host), $port, isset($m[1]) ? self::removeDots($path) : $path, $m[4] ?? null, $m[5] ?? null);
    }

    /** Whether the parts make a well-formed IRI: a valid scheme when there is one, no "//" path without an authority. */
    public function valid(): bool
    {
        if ($this->scheme !== null && !preg_match('/^[a-z][a-z0-9+.-]*$/', $this->scheme)) {
            return false;
        }
        return $this->host !== null || !str_starts_with($this->path, '//');
    }

    /** The reference resolved against this base (RFC 3986 section 5.2), or null when the base has no scheme. */
    public function resolve(self $ref): ?self
    {
        if ($this->scheme === null || !$this->valid()) {
            return null;
        }
        if ($ref->scheme !== null) {
            return $ref->withPath(self::removeDots($ref->path));
        }
        if ($ref->host !== null) {
            return new self($this->scheme, $ref->userinfo, $ref->host, $ref->port, self::removeDots($ref->path), $ref->query, $ref->fragment);
        }
        if ($ref->path === '') {
            return new self($this->scheme, $this->userinfo, $this->host, $this->port, $this->path, $ref->query ?? $this->query, $ref->fragment);
        }
        $path = $ref->path[0] === '/' ? $ref->path : $this->mergePath($ref->path);
        return new self($this->scheme, $this->userinfo, $this->host, $this->port, self::removeDots($path), $ref->query, $ref->fragment);
    }

    /** The same IRI with another host. */
    public function withHost(?string $host): self
    {
        return new self($this->scheme, $this->userinfo, $host, $this->port, $this->path, $this->query, $this->fragment);
    }

    /** The same IRI with another path. */
    public function withPath(string $path): self
    {
        return new self($this->scheme, $this->userinfo, $this->host, $this->port, $path, $this->query, $this->fragment);
    }

    /** The normalized IRI: non-ASCII characters stay as they are. */
    public function toIri(): string
    {
        return $this->write('\x80-\xFF');
    }

    /** The normalized URI: non-ASCII characters percent-encoded too. */
    public function toUri(): string
    {
        return $this->write('');
    }

    /** The text, with $keep naming the extra byte ranges left unencoded. */
    private function write(string $keep): string
    {
        $out = $this->scheme === null ? '' : $this->scheme . ':';
        if ($this->host !== null) {
            $port = $this->port !== null && $this->port !== (self::PORTS[(string) $this->scheme] ?? null) ? ':' . $this->port : '';
            $out .= '//' . ($this->userinfo === null ? '' : self::encode($this->userinfo, ':', $keep) . '@') . self::encode($this->host, '[]:', $keep) . $port;
        }
        $path = $this->scheme !== null ? self::removeDots($this->path) : $this->path;
        if ($this->host !== null && $path === '' && in_array($this->scheme, ['http', 'https'], true)) {
            $path = '/';
        }
        $out .= self::encode($path, '/:@', $keep);
        $out .= $this->query === null ? '' : '?' . self::encode($this->query, '/:@?', $keep);
        return $out . ($this->fragment === null ? '' : '#' . self::encode($this->fragment, '/:@?', $keep));
    }

    private function mergePath(string $path): string
    {
        if ($this->host !== null && $this->path === '') {
            return '/' . $path;
        }
        $at = strrpos($this->path, '/');
        return ($at === false ? '' : substr($this->path, 0, $at + 1)) . $path;
    }

    private static function removeDots(string $path): string
    {
        $out = [];
        $segments = explode('/', $path);
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($segment === '.' || $segment === '..') {
                if ($segment === '..' && (count($out) > 1 || (count($out) === 1 && $out[0] !== ''))) {
                    array_pop($out);
                }
                if ($i === $last) {
                    $out[] = '';
                }
                continue;
            }
            $out[] = $segment;
        }
        return implode('/', $out);
    }

    private static function encode(string $text, string $allowed, string $keep): string
    {
        $pattern = '/%(?![0-9A-Fa-f]{2})|[^A-Za-z0-9\-._~!$&\'()*+,;=%' . preg_quote($allowed, '/') . $keep . ']/';
        return (string) preg_replace_callback($pattern, static fn (array $m): string => rawurlencode($m[0]), $text);
    }
}
