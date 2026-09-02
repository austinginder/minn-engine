<?php

declare(strict_types=1);

use Minn\Theme\StyleSettings;
use Minn\Theme\ThemeStyles;

return [
    'preset lists are keyed by the origin that declared them, at the root and under a block' => static fn () => StyleSettings::normalize(
        ['color' => ['palette' => [['slug' => 'a']]], 'blocks' => ['core/button' => ['color' => ['palette' => [['slug' => 'b']]]]]],
        'custom',
    ) === ['color' => ['palette' => ['custom' => [['slug' => 'a']]]], 'blocks' => ['core/button' => ['color' => ['palette' => ['custom' => [['slug' => 'b']]]]]]],
    'a list already keyed by origin is left alone' => static fn () => StyleSettings::normalize(['color' => ['palette' => ['theme' => []]]], 'theme') === ['color' => ['palette' => ['theme' => []]]],
    'appearanceTools becomes its flags in the reference\'s order and the flag itself goes' => static function () {
        $out = StyleSettings::normalize(['appearanceTools' => true, 'color' => ['palette' => []]], 'custom');
        return !isset($out['appearanceTools'])
            && array_keys($out) === ['color', 'background', 'border', 'dimensions', 'position', 'spacing', 'typography']
            && array_keys($out['color']) === ['palette', 'link', 'heading', 'button', 'caption']
            && $out['dimensions'] === ['aspectRatio' => true, 'height' => true, 'minHeight' => true, 'minWidth' => true, 'width' => true]
            && $out['position'] === ['sticky' => true];
    },
    'a flag the node sets itself keeps its value under appearanceTools' => static fn () => StyleSettings::normalize(['appearanceTools' => true, 'color' => ['link' => false]], 'theme')['color']['link'] === false,
    'appearanceTools false changes nothing' => static fn () => StyleSettings::normalize(['appearanceTools' => false], 'theme') === ['appearanceTools' => false],
    'var:preset tokens resolve to their custom properties, anywhere in the tree' => static fn () => StyleSettings::resolved(['color' => ['text' => 'var:preset|color|contrast'], 'blocks' => ['core/code' => ['typography' => ['fontSize' => 'var:preset|font-size|x-large']]], 'keep' => 'var(--wp--preset--color--base)'])
        === ['color' => ['text' => 'var(--wp--preset--color--contrast)'], 'blocks' => ['core/code' => ['typography' => ['fontSize' => 'var(--wp--preset--font-size--x-large)']]], 'keep' => 'var(--wp--preset--color--base)'],
    'without a theme.json the theme styles are the captured defaults alone: appearanceTools off, no variations' => static function () {
        $styles = new ThemeStyles(null, 'bare');
        $settings = $styles->settings();
        return $settings['appearanceTools'] === false
            && count($settings['color']['palette']['default']) === 12
            && !isset($settings['position'])
            && $styles->styles()['spacing']['blockGap'] === '24px'
            && $styles->variations() === []
            && $styles->stylesheet === 'bare';
    },
];
