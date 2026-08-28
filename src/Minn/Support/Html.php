<?php

declare(strict_types=1);

namespace Minn\Support;

final class Html
{
    public static function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function attr(?string $value): string
    {
        return self::esc($value);
    }
}
