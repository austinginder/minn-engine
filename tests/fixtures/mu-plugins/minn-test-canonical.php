<?php
/**
 * Plugin Name: Minn test canonical
 * Description: Fixture for the canonical-hooks suite, loaded by the engine and the reference alike. A request that carries X-Minn-Canonical naming a run the suite opened (wp-content/minn-canonical/<run>.open exists) gets a plugin on the canonical redirect, as SEO and multilingual plugins are; X-Minn-Canonical-Mode says how: "note" notes what redirect_canonical was handed, "off" refuses every move, "swap" sends it elsewhere, "unhook" takes redirect_canonical off template_redirect. The notes go to <run>.log. Without such a run the header does nothing.
 * License: MIT
 */

$minnCanonicalRun = (string) ($_SERVER['HTTP_X_MINN_CANONICAL'] ?? '');
$minnCanonicalDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-canonical';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnCanonicalRun) !== 1 || !is_file("{$minnCanonicalDir}/{$minnCanonicalRun}.open")) {
    return;
}
$minnCanonicalMode = (string) ($_SERVER['HTTP_X_MINN_CANONICAL_MODE'] ?? 'note');
$minnCanonicalNote = static function (string $what) use ($minnCanonicalDir, $minnCanonicalRun): void {
    // The stacks answer under different hosts and schemes; each is written as {site}.
    file_put_contents("{$minnCanonicalDir}/{$minnCanonicalRun}.log", preg_replace('#https?://[^/"]+#', '{site}', $what) . "\n", FILE_APPEND);
};
add_filter('redirect_canonical', static function ($to, $from) use ($minnCanonicalNote, $minnCanonicalMode) {
    $minnCanonicalNote('redirect_canonical ' . json_encode([$to, $from], JSON_UNESCAPED_SLASHES));
    return match ($minnCanonicalMode) {
        'off' => false,
        'swap' => $to ? home_url('/zz-swapped/') : $to,
        default => $to,
    };
}, 10, 2);
add_action('template_redirect', static function () use ($minnCanonicalNote): void {
    $minnCanonicalNote('template_redirect ' . json_encode([is_singular(), is_404(), get_queried_object_id()]));
}, 1);
if ($minnCanonicalMode === 'unhook') {
    add_action('init', static fn () => remove_action('template_redirect', 'redirect_canonical'));
}
