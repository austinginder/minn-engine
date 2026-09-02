<?php

declare(strict_types=1);

use Minn\Theme\StylePresets;

return [
    'a preset list is a plain list or one keyed by origin, flattened default, theme, custom' => static fn () => StylePresets::presetList([['slug' => 'a']]) === [['slug' => 'a']]
        && StylePresets::presetList(['theme' => [['slug' => 't']], 'default' => [['slug' => 'd']], 'custom' => [['slug' => 'c']]]) === [['slug' => 'd'], ['slug' => 't'], ['slug' => 'c']]
        && StylePresets::presetList('nope') === [],
    'a fluid size becomes the reference\'s clamp' => static fn () => StylePresets::fluidFontSize(['size' => '1rem', 'fluid' => ['min' => '1rem', 'max' => '1.125rem']], ['layout' => ['wideSize' => '1340px']])
        === 'clamp(1rem, 1rem + ((1vw - 0.2rem) * 0.196), 1.125rem)'
        && StylePresets::fluidFontSize(['size' => '2rem'], []) === '2rem',
    'preset properties and the has-* classes' => static function () {
        $presets = ['color' => [['slug' => 'ink', 'value' => '#000']], 'gradient' => [], 'font-size' => [], 'font-family' => []];
        return StylePresets::presetProperties($presets) === '--wp--preset--color--ink: #000;'
            && str_contains(StylePresets::presetClasses($presets), '.has-ink-color{color: var(--wp--preset--color--ink) !important;}');
    },
];
