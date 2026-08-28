<?php

declare(strict_types=1);

namespace Minn;

use RuntimeException;

/**
 * A WordPress-shaped error, thrown from anywhere and rendered once by the
 * kernel: {"code": ..., "message": ..., "data": {"status": ...}}.
 */
final class RestError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public static function noRoute(): self
    {
        return new self('rest_no_route', 'No route was found matching the URL and request method.', 404);
    }

    public function payload(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'data' => ['status' => $this->status] + $this->extra,
        ];
    }
}
