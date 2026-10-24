<?php

declare(strict_types=1);

use Minn\Theme\GlobalStyles;
use Minn\Theme\Theme;

/**
 * Block gap support, as the reference prints it (probe block-gap-support):
 * gap rules only when the theme turns block gaps on, else a 0.5em gap for
 * flex and grid and nothing for flow layouts, the site's blocks or a
 * block's own gap.
 */
$css = static function (array $settings): string {
    $dir = sys_get_temp_dir() . '/minn-gap-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents("{$dir}/theme.json", json_encode(['version' => 3, 'settings' => $settings]));
    try {
        return (new GlobalStyles(new Theme('gap-probe', $dir, 'https://example.test/wp-content/themes/gap-probe')))->stylesheet(['styles']);
    } finally {
        unlink("{$dir}/theme.json");
        rmdir($dir);
    }
};
$gaps = static fn (string $css): bool => str_contains($css, ':root :where(.is-layout-flow) > *{margin-block-start: 24px;')
    && str_contains($css, ':where(.wp-site-blocks) > * { margin-block-start: 24px;')
    && str_contains($css, '--wp--style--block-gap: 24px');
$none = static fn (string $css): bool => !str_contains($css, 'is-layout-flow) > *{margin-block-start')
    && !str_contains($css, ':where(.wp-site-blocks) > *')
    && !str_contains($css, '--wp--style--block-gap:')
    && !str_contains($css, 'wp-block-columns-is-layout-flow')
    && str_contains($css, ':where(.is-layout-flex){gap: 0.5em;}:where(.is-layout-grid){gap: 0.5em;}');

return [
    'a theme without block gap support prints no gap rules, only the flex and grid fallback' => static fn () => $none($css(['appearanceTools' => false])),
    'a theme whose blockGap is null has no support either' => static fn () => $none($css(['spacing' => ['blockGap' => null]])),
    'appearanceTools turns block gaps on' => static fn () => $gaps($css(['appearanceTools' => true])),
    'blockGap true turns them on without appearanceTools' => static fn () => $gaps($css(['spacing' => ['blockGap' => true]])),
    'blockGap false keeps them off under appearanceTools' => static fn () => $none($css(['appearanceTools' => true, 'spacing' => ['blockGap' => false]])),
    'the support test reads the settings as the reference does' => static fn () => GlobalStyles::gapSupported(['appearanceTools' => true, 'spacing' => ['blockGap' => null]])
        && !GlobalStyles::gapSupported([])
        && !GlobalStyles::gapSupported(['spacing' => ['blockGap' => 'yes']]),
];
