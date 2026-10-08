<?php
/**
 * Styles suite: the generated global stylesheet against the reference's.
 * Presets and preset classes are data-derived, so they must match exactly;
 * every container class the page renders must have a rule; the engine's
 * block stylesheet must be served.
 */
require_once __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'https://ref.minn.localhost';
$fetch = static function (string $url): string {
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'timeout' => 20]]);
    return (string) @file_get_contents($url, false, $context);
};
$globalStyles = static function (string $html): string {
    return preg_match('/<style id="global-styles-inline-css"[^>]*>(.*?)<\/style>/s', $html, $m) ? $m[1] : '';
};
$presets = static function (string $css): array {
    preg_match_all('/--wp--(?:preset|style--global)--[a-z0-9-]+:\s*[^;]+;/', $css, $m);
    $found = array_map(static fn (string $d) => preg_replace('/\s+/', ' ', trim($d)), $m[0]);
    sort($found);
    return array_values(array_unique($found));
};
$presetClasses = static function (string $css): array {
    preg_match_all('/\.has-[a-z0-9-]+-(?:color|background-color|border-color|gradient-background|font-size|font-family)\{[^}]*\}/', $css, $m);
    $found = array_map(static fn (string $r) => preg_replace('/\s+/', '', $r), $m[0]);
    sort($found);
    return array_values(array_unique($found));
};

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "  ok   $label\n"; } else { $fail++; echo "  FAIL $label" . ($detail === '' ? '' : ": $detail") . "\n"; }
};

$engineHome = $fetch("$ENGINE/");
$engineCss = $globalStyles($engineHome);
$check($engineCss !== '', 'engine emits global-styles-inline-css');

$live = $fetch("$REF/wp-json/") !== '';
if ($live) {
    $refCss = $globalStyles($fetch("$REF/"));
    $missing = array_diff($presets($refCss), $presets($engineCss));
    $extra = array_diff($presets($engineCss), $presets($refCss));
    $check($missing === [] && $extra === [], 'preset custom properties match the reference', 'missing: ' . implode(' | ', array_slice($missing, 0, 3)) . ' extra: ' . implode(' | ', array_slice($extra, 0, 3)));
    $missing = array_diff($presetClasses($refCss), $presetClasses($engineCss));
    $extra = array_diff($presetClasses($engineCss), $presetClasses($refCss));
    $check($missing === [] && $extra === [], 'preset classes match the reference', 'missing: ' . implode(' | ', array_slice($missing, 0, 3)) . ' extra: ' . implode(' | ', array_slice($extra, 0, 3)));
}

preg_match_all('/wp-container-core-[a-z-]+-is-layout-[0-9a-f]{8}/', $engineHome, $m);
$unstyled = array_values(array_filter(array_unique($m[0]), static fn (string $class) => !str_contains($engineCss, ".$class{")));
$check($unstyled === [], 'every rendered container class has a stylesheet rule', implode(', ', $unstyled));

// Numbered style variations print on their own handle, as on the reference: one tag, its rules for every number the page used.
$battery = $fetch("$ENGINE/zz-block-battery-layout/");
preg_match_all('/is-style-[a-z0-9-]+--\d+/', $battery, $m);
preg_match_all('/<style id="block-style-variation-styles-inline-css"[^>]*>(.*?)<\/style>/s', $battery, $tags);
$variationCss = implode('', $tags[1]);
$unstyled = array_values(array_filter(array_unique($m[0]), static fn (string $class) => !str_contains($variationCss, ".$class")));
$check($unstyled === [], 'every numbered style variation has a rule', implode(', ', $unstyled));
$check(count($tags[1]) === 1, 'the variations print in one style tag', (string) count($tags[1]));
$referenceBattery = $fetch("$REF/zz-block-battery-layout/");
preg_match_all('/\.is-style-[a-z0-9-]+--\d+/', $referenceBattery, $referenceNumbered);
preg_match_all('/\.is-style-[a-z0-9-]+--\d+/', $battery, $engineNumbered);
$check(array_values(array_unique($engineNumbered[0])) === array_values(array_unique($referenceNumbered[0])), 'the page numbers the variations the reference does', implode(' ', array_unique($engineNumbered[0])) . ' vs ' . implode(' ', array_unique($referenceNumbered[0])));

[$h, $body] = minn_test_fetch("$ENGINE/minn/assets/blocks.css");
$check($h['status'] === 200 && str_contains($body, '.wp-block-columns'), 'the engine block stylesheet is served');
// Theme stylesheet links are the theme's own to enqueue (functions.php runs through the
// runtime); the engine links none itself, so the two stacks must print the same set.
$themeLinks = static function (string $html): array {
    preg_match_all('#<link[^>]*rel=[\'"]stylesheet[\'"][^>]*href=[\'"]([^\'"]*/wp-content/themes/[^\'"]*)[\'"]#', $html, $m);
    return array_map(static fn (string $href): string => preg_replace('#^https?://[^/]+#', '', $href), $m[1]);
};
$check($themeLinks($engineHome) === $themeLinks($fetch("$REF/")), 'theme stylesheet links match the reference', json_encode($themeLinks($engineHome)) . ' vs ' . json_encode($themeLinks($fetch("$REF/"))));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
