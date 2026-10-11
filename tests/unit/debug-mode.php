<?php

declare(strict_types=1);

use Minn\Runtime\DebugMode;

// The reference's answers, one boot per case (tests/tools/debug-mode-capture.php).
$fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/contracts/fixtures/debug-mode.json'), true);
$cases = [];
foreach ($fixture['cases'] as $row) {
    $cases["debug mode: {$row['label']}"] = static function () use ($row, $fixture) {
        $constants = $row['constants'];
        $chosen = DebugMode::settings($constants, $constants['WP_CONTENT_DIR'] ?? '{root}/wp-content');
        $settings = ['error_reporting' => $chosen['level']] + $chosen['ini'] + $fixture['baseline'];
        if (DebugMode::answersMachine($constants, $row['request'])) {
            $settings['display_errors'] = '0';
        }
        $settings = array_intersect_key($settings, $row['settings']);
        ksort($settings);
        $expected = $row['settings'];
        ksort($expected);
        return $settings === $expected ?: json_encode(['engine' => $settings, 'reference' => $expected], JSON_UNESCAPED_SLASHES);
    };
}
return $cases;
