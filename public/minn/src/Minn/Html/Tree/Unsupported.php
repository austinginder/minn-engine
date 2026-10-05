<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

use RuntimeException;

/**
 * Where the reference's HTML processor stops: markup whose tree it does
 * not build (foster parenting, the adoption agency's hard cases, PLAINTEXT,
 * content after </html>), with the token it stopped at and the state then.
 */
final class Unsupported extends RuntimeException
{
    /**
     * @param list<string> $stack the stack of open elements, root first
     * @param list<string> $formatting the active formatting elements
     */
    public function __construct(
        string $message,
        public readonly Token $token,
        public readonly string $tokenText,
        public readonly array $stack,
        public readonly array $formatting,
    ) {
        parent::__construct($message);
    }
}
