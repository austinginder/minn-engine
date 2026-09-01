<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The JSON object a block delimiter carries, read in place. Rewriting one
 * block's attributes must not disturb the rest of the markup, so callers
 * take the object's exact substring rather than reserializing the document.
 */
final readonly class Attributes
{
    /** The JSON object starting at $at, brace-matched through any strings; null when there is none. */
    public static function objectAt(string $markup, int $at): ?string
    {
        if (($markup[$at] ?? '') !== '{') {
            return null;
        }
        $depth = 0;
        $inString = false;
        for ($i = $at, $length = strlen($markup); $i < $length; $i++) {
            $char = $markup[$i];
            if ($inString) {
                $inString = $char === '"' ? false : $inString;
                $i += $char === '\\' ? 1 : 0;
                continue;
            }
            match ($char) {
                '"' => $inString = true,
                '{' => $depth++,
                '}' => $depth--,
                default => null,
            };
            if ($depth === 0 && $char === '}') {
                return substr($markup, $at, $i - $at + 1);
            }
        }
        return null;
    }
}
