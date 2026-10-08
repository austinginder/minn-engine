<?php

declare(strict_types=1);

namespace Minn\Html;

/** An attribute value that comes already escaped (a URL through esc_url), written as it is. */
final readonly class Escaped
{
    public function __construct(public string $value)
    {
    }
}
