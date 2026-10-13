<?php
/**
 * The release gate's two token-window scans (/dev-minn-engine-coverage,
 * measures 18 and 23), over the engine's tracked PHP (public/minn, the
 * generated stubs left out):
 * - duplicated: stretches of engine code that repeat elsewhere in the engine
 *   once variables and literals are set aside (a copy with its names
 *   changed still counts). Each is a refactor owed: one owner, one path;
 * - wordpress: stretches of engine code identical, token for token (names,
 *   variables and literals kept, layout and comments dropped), to the
 *   reference's own PHP in wp-reference/. The engine is written from
 *   behaviour, never from that source, so any hit is read by a person.
 *
 * php tests/tools/clone-scan.php                 prints both figures
 * php tests/tools/clone-scan.php --json          prints them as JSON (scorecard.php reads it)
 * php tests/tools/clone-scan.php --list          names every stretch
 * php tests/tools/clone-scan.php --skip-wordpress    the duplicated scan alone (fast)
 */

ini_set('memory_limit', '-1');
$root = dirname(__DIR__, 2);
$asJson = in_array('--json', $argv, true);
$list = in_array('--list', $argv, true);
$withWordPress = !in_array('--skip-wordpress', $argv, true);

// Token windows: long enough that a shared signature or a common idiom never makes one alone.
const DUPLICATE_WINDOW = 100;
const WORDPRESS_WINDOW = 80;

$tracked = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($root) . ' ls-files public/minn')));
$engineFiles = array_values(array_filter($tracked, static fn (string $path): bool => str_ends_with($path, '.php')
    && !in_array($path, ['public/minn/wp-api/deadends.php', 'public/minn/wp-api/placeholders.php'], true)
    && !str_starts_with($path, 'public/minn/wp-api/classes/deadends/')));

/**
 * A file's tokens with layout and comments dropped: each as [text, line].
 * Normalized, variables become "$" and literals "L".
 *
 * @return list<array{0: string, 1: int}>
 */
$tokens = static function (string $code, bool $normalize): array {
    $out = [];
    $line = 1;
    foreach (@token_get_all($code) as $token) {
        if (!is_array($token)) {
            $out[] = [$token, $line];
            continue;
        }
        [$id, $text, $line] = $token;
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
            continue;
        }
        if ($normalize && $id === T_VARIABLE) {
            $text = '$';
        } elseif ($normalize && in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ENCAPSED_AND_WHITESPACE], true)) {
            $text = 'L';
        } elseif ($id === T_STRING || $id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED) {
            $text = strtolower($text);
        }
        $out[] = [$text, $line];
    }
    return $out;
};

/**
 * The hash of every window of a token list, by its first token's index: two rolling hashes modulo
 * two primes under 2^31, joined into one 62-bit key, so millions of windows never collide by chance.
 *
 * @return list<int>
 */
$windows = static function (array $tokens, int $size): array {
    static $powers = [];
    $p1 = 2147483629;
    $p2 = 2147483587;
    $base = 1000003;
    if (!isset($powers[$size])) {
        [$a, $b] = [1, 1];
        for ($i = 1; $i < $size; $i++) {
            [$a, $b] = [$a * $base % $p1, $b * $base % $p2];
        }
        $powers[$size] = [$a, $b];
    }
    [$top1, $top2] = $powers[$size];
    $values = array_map(static fn (array $token): int => crc32($token[0]) % $p1, $tokens);
    $count = count($values);
    $hashes = [];
    [$h1, $h2] = [0, 0];
    for ($i = 0; $i < $count; $i++) {
        if ($i >= $size) {
            $out = $values[$i - $size];
            $h1 = ($h1 - $out * $top1 % $p1 + $p1) % $p1;
            $h2 = ($h2 - $out * $top2 % $p2 + $p2) % $p2;
        }
        $h1 = ($h1 * $base + $values[$i]) % $p1;
        $h2 = ($h2 * $base + $values[$i]) % $p2;
        if ($i >= $size - 1) {
            $hashes[] = ($h1 << 31) | $h2;
        }
    }
    return $hashes;
};

/**
 * Stretches of engine lines covered by a matching window, merged per file: "path:from-to".
 *
 * @param array<string, list<int>> $hits path => first-token indexes whose window matched
 * @param array<string, list<array{0: string, 1: int}>> $engine path => tokens
 * @return array{stretches: list<string>, lines: int}
 */
$stretches = static function (array $hits, array $engine, int $size): array {
    $out = [];
    $lines = 0;
    foreach ($hits as $path => $starts) {
        sort($starts);
        $covered = [];
        foreach ($starts as $start) {
            for ($line = $engine[$path][$start][1]; $line <= $engine[$path][$start + $size - 1][1]; $line++) {
                $covered[$line] = true;
            }
        }
        ksort($covered);
        $lines += count($covered);
        $from = $previous = null;
        foreach (array_keys($covered) as $line) {
            if ($from !== null && $line !== $previous + 1) {
                $out[] = "{$path}:{$from}-{$previous}";
                $from = null;
            }
            $from ??= $line;
            $previous = $line;
        }
        if ($from !== null) {
            $out[] = "{$path}:{$from}-{$previous}";
        }
    }
    return ['stretches' => $out, 'lines' => $lines];
};

// Measure 23: windows that occur in two places in the engine.
$normalized = [];
$seen = [];
foreach ($engineFiles as $path) {
    $normalized[$path] = $tokens((string) file_get_contents("{$root}/{$path}"), true);
    foreach ($windows($normalized[$path], DUPLICATE_WINDOW) as $index => $hash) {
        $seen[$hash][] = [$path, $index];
    }
}
$duplicateHits = [];
foreach ($seen as $places) {
    // A window repeated only inside its own stretch (a run of identical lines) is one place.
    if (count($places) < 2 || count(array_unique(array_column($places, 0))) === 1 && max(array_column($places, 1)) - min(array_column($places, 1)) < DUPLICATE_WINDOW) {
        continue;
    }
    foreach ($places as [$path, $index]) {
        $duplicateHits[$path][] = $index;
    }
}
unset($seen);
$duplicated = $stretches($duplicateHits, $normalized, DUPLICATE_WINDOW);
$engineLines = 0;
foreach ($engineFiles as $path) {
    $engineLines += substr_count((string) file_get_contents("{$root}/{$path}"), "\n");
}

// Measure 18: windows the engine shares with the reference's source.
$wordpress = null;
$reference = minn_reference_dir($root);
if ($withWordPress && $reference !== null) {
    $known = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($reference, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = $file->getPathname();
        if (!str_ends_with($path, '.php') || str_contains($path, '/wp-content/')) {
            continue;
        }
        foreach ($windows($tokens((string) file_get_contents($path), false), WORDPRESS_WINDOW) as $hash) {
            $known[$hash] = true;
        }
    }
    $exact = [];
    $wordpressHits = [];
    foreach ($engineFiles as $path) {
        $exact[$path] = $tokens((string) file_get_contents("{$root}/{$path}"), false);
        foreach ($windows($exact[$path], WORDPRESS_WINDOW) as $index => $hash) {
            if (isset($known[$hash])) {
                $wordpressHits[$path][] = $index;
            }
        }
    }
    unset($known);
    $wordpress = $stretches($wordpressHits, $exact, WORDPRESS_WINDOW);
}

/** The reference copy the marketing site keeps beside the repository, when there is one. */
function minn_reference_dir(string $root): ?string
{
    return is_file("{$root}/wp-reference/wp-includes/version.php") ? "{$root}/wp-reference" : null;
}

$figures = [
    'duplicated' => ['window' => DUPLICATE_WINDOW, 'stretches' => count($duplicated['stretches']), 'lines' => $duplicated['lines'], 'engineLines' => $engineLines],
    'wordpress' => $wordpress === null ? null : ['window' => WORDPRESS_WINDOW, 'stretches' => count($wordpress['stretches']), 'lines' => $wordpress['lines']],
];
if ($asJson) {
    echo json_encode($figures, JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
printf("duplicated: %d lines in %d stretches (of %d engine lines; windows of %d tokens, names kept, variables and literals set aside)\n",
    $duplicated['lines'], count($duplicated['stretches']), $engineLines, DUPLICATE_WINDOW);
printf("wordpress: %s\n", $wordpress === null ? 'not scanned' : sprintf('%d lines in %d stretches identical to the reference (windows of %d tokens)', $wordpress['lines'], count($wordpress['stretches']), WORDPRESS_WINDOW));
if ($list) {
    foreach ($duplicated['stretches'] as $stretch) {
        echo "  duplicated: {$stretch}\n";
    }
    foreach ($wordpress['stretches'] ?? [] as $stretch) {
        echo "  wordpress: {$stretch}\n";
    }
}
