<?php
/**
 * Which of a site's plugins the runtime would load, and what the others
 * are missing. MINN_SITE_ROOT=<site root> php tests/tools/runtime-report.php
 */
require __DIR__ . '/engine-runtime.php';
$active = $runtime->options()->get('active_plugins');
$content = $runtime->contentDir();
$report = [];
foreach (is_array($active) ? $active : [] as $plugin) {
    $file = $content . '/plugins/' . $plugin;
    if (!is_file($file)) {
        $report[$plugin] = ['state' => 'missing'];
        continue;
    }
    $dir = str_contains($plugin, '/') ? dirname($file) : $file;
    $missing = Minn\Runtime\Symbols::missing($dir, Minn\Runtime\Runtime::options());
    $report[$plugin] = ['state' => ($missing['functions'] === [] && $missing['classes'] === [] && !$missing['truncated']) ? 'loads' : 'skipped'] + $missing;
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
