<?php

declare(strict_types=1);

namespace Minn\Ext\BlockVisibility;

/** The breakpoint rules behind the hide-on-screen-size classes, in the plugin's style element. */
final class ScreenSizes
{
    public const LARGE = 992;
    public const MEDIUM = 768;

    public static function stylesheet(): string
    {
        $large = self::LARGE;
        $medium = self::MEDIUM;
        $belowLarge = $large - 0.02;
        $belowMedium = $medium - 0.02;
        return '<style id="block-visibility-screen-size-styles-inline-css">' . "\n"
            . "@media (min-width: {$large}px) { .block-visibility-hide-large-screen { display: none !important; } }\n"
            . "@media (min-width: {$medium}px) and (max-width: {$belowLarge}px) { .block-visibility-hide-medium-screen { display: none !important; } }\n"
            . "@media (max-width: {$belowMedium}px) { .block-visibility-hide-small-screen { display: none !important; } }\n"
            . '</style>' . "\n";
    }
}
