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
    /**
     * @param array<string, mixed> $extra keys added beside status inside data (the whole of data when it names status)
     * @param array<string, mixed> $topLevel keys added beside code/message/data
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $extra = [],
        public readonly array $topLevel = [],
        private readonly bool $bare = false,
    ) {
        parent::__construct($message);
    }

    /** The reference's 404 for a route nothing answers. */
    public static function noRoute(): self
    {
        return new self('rest_no_route', 'No route was found matching the URL and request method.', 404);
    }

    /**
     * The reference's 400 for required parameters that did not arrive.
     *
     * @param list<string> $params
     */
    public static function missingParams(array $params): self
    {
        return new self('rest_missing_callback_param', 'Missing parameter(s): ' . implode(', ', $params), 400, ['params' => $params]);
    }

    /**
     * The reference's 400 for one parameter its schema refuses: the message
     * under params, and under details with the refusal's code and data, as
     * WP_REST_Request::has_valid_params reports it.
     */
    public static function invalidParam(string $param, string $message, string $code = 'rest_not_in_enum', mixed $data = null): self
    {
        return new self('rest_invalid_param', "Invalid parameter(s): {$param}", 400, ['params' => [$param => $message], 'details' => [$param => ['code' => $code, 'message' => $message, 'data' => $data]]]);
    }

    /** A statusless core error as REST serves it: HTTP 500 with data null. */
    public static function bare(string $code, string $message): self
    {
        return new self($code, $message, 500, bare: true);
    }

    /** The error as the reference's JSON body: code, message, data. */
    public function payload(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            // Data that carries its own status keeps its order (a plugin's WP_Error data, as it was given).
            'data' => $this->bare ? null : (array_key_exists('status', $this->extra) ? $this->extra : ['status' => $this->status] + $this->extra),
        ] + $this->topLevel;
    }
}
