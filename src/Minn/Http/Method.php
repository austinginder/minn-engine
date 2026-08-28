<?php

declare(strict_types=1);

namespace Minn\Http;

enum Method: string
{
    case Get = 'GET';
    case Head = 'HEAD';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
    case Options = 'OPTIONS';
    /** For a route that accepts every method and sorts them out itself. */
    case Any = '*';

    public static function fromName(string $name): self
    {
        return self::tryFrom(strtoupper($name)) ?? self::Get;
    }

    /** HEAD is served by GET handlers; the kernel drops the body. */
    public function matches(self $declared): bool
    {
        return $declared === self::Any || $this === $declared || ($this === self::Head && $declared === self::Get);
    }
}
