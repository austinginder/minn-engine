<?php
/**
 * The release gate's token-window scans (/dev-minn-engine-coverage, measures
 * 18 and 23), over tracked files under public/minn (generated stubs left out).
 *
 * - duplicated: stretches of engine and facade PHP that repeat elsewhere in
 *   them once variables and literals are set aside. Each is a refactor
 *   owed: one owner, one path.
 * - engine: the engine itself (public/minn without wp-api/) against the
 *   reference's own files in wp-reference/:
 *   - php.strict: PHP stretches of 40 tokens that match once variable names
 *     are set aside (a translation that renamed them still counts);
 *   - php.sensitive: the same at 30 tokens, which also catches short
 *     passages and some common idioms;
 *   - assets.identical: JS, CSS and HTML files byte for byte the reference's;
 *   - assets.lines: lines of JS, CSS and HTML inside 40-token stretches the
 *     reference has;
 *   - data.strings: strings of 40 characters or more in the JSON data that
 *     the reference also holds (captured text, icons and lists).
 * - facade: wp-api/ (the WordPress-named mapping layer, whose signatures and
 *   shapes the API fixes) against the reference's PHP, in exact 80-token
 *   stretches.
 *
 * Third-party files with their own licence are left out (ALLOWED), and so are
 * the engine constants whose order is itself behaviour (SEQUENCES).
 *
 * php tests/tools/clone-scan.php                 prints the figures
 * php tests/tools/clone-scan.php --json          prints them as JSON (scorecard.php reads it)
 * php tests/tools/clone-scan.php --list          names every stretch and file
 * php tests/tools/clone-scan.php --skip-wordpress    the duplicated scan alone (fast)
 */

ini_set('memory_limit', '-1');
$root = dirname(__DIR__, 2);
$asJson = in_array('--json', $argv, true);
$list = in_array('--list', $argv, true);
$withWordPress = !in_array('--skip-wordpress', $argv, true);

const DUPLICATE_WINDOW = 100;
const STRICT_WINDOW = 40;
const SENSITIVE_WINDOW = 30;
const TEXT_WINDOW = 40;
const FACADE_WINDOW = 80;
const DATA_STRING = 40;

/**
 * Engine constants whose contents must match the reference's, order included, so the scan sets
 * them aside: each named with why its order is behaviour.
 */
const SEQUENCES = [
    'public/minn/src/Minn/Content/Slug.php' => ['SAVE_DROPPED' => 'replaced one after another, so a removal can make the next match'],
    'public/minn/src/Minn/Http/StatusText.php' => ['PHRASES' => 'the IANA status code registry'],
    'public/minn/src/Minn/Rest/CommentListArgs.php' => ['MAPPINGS' => 'the order of the query arguments plugins receive'],
    'public/minn/src/Minn/Rest/PostListArgs.php' => ['MAPPINGS' => 'the order of the query arguments plugins receive'],
    'public/minn/src/Minn/Rest/MenusController.php' => ['NEW_ITEM' => 'the order of the item arguments the save hooks receive'],
    'public/minn/src/Minn/Runtime/MenuEvents.php' => ['ITEM_DEFAULTS' => 'the order of the item arguments the save hooks receive'],
    'public/minn/src/Minn/Rest/Schema.php' => ['ENDPOINT_KEYWORDS' => 'the order of the keywords in every route argument'],
    'public/minn/src/Minn/Runtime/BlockMetadata.php' => ['PROPERTIES' => 'the order of the block type arguments register_block_type_args receives'],
    'public/minn/src/Minn/Runtime/PostSave.php' => ['DATA' => 'the order of the row wp_insert_post_data receives', 'DEFAULTS' => 'the order of the post array the save hooks receive'],
    'public/minn/src/Minn/Runtime/TermQuery.php' => ['DEFAULTS' => 'the order of the query variables the term query hooks receive'],
    'public/minn/src/Minn/Runtime/ThemeSupports.php' => ['HEADER' => 'the order of get_theme_support()', 'BACKGROUND' => 'the order of get_theme_support()'],
    'public/minn/src/Minn/Support/KsesCss.php' => ['CSS_PROPERTIES' => 'the list safe_style_css receives'],
    'public/minn/src/Minn/Theme/GlobalStyles.php' => ['ELEMENT_SELECTORS' => 'the order of the element rules in the stylesheet'],
];

/** Paths left out, each with its reason. */
const ALLOWED = [
    'public/minn/assets/vendor/' => 'jQuery and jQuery Migrate, MIT, shipped with their licence',
    'public/minn/data/api-names.json' => 'names of the API, which the API fixes',
    'public/minn/data/placeholder-symbols.json' => 'names of the API, which the API fixes',
    'public/minn/data/deadend-symbols.json' => 'names of the API, which the API fixes',
    'public/minn/data/reference-files.json' => "the reference's file layout, which hosting tools read",
];

$tracked = array_values(array_filter(
    explode("\n", (string) shell_exec('git -C ' . escapeshellarg($root) . ' ls-files public/minn')),
    static function (string $path): bool {
        foreach (array_keys(ALLOWED) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }
        return $path !== '' && !in_array($path, ['public/minn/wp-api/deadends.php', 'public/minn/wp-api/placeholders.php'], true)
            && !str_starts_with($path, 'public/minn/wp-api/classes/deadends/');
    },
));
$extension = static fn (string $path): string => $path === 'public/minn/bin/minn' ? 'php' : strtolower(pathinfo($path, PATHINFO_EXTENSION));
$isFacade = static fn (string $path): bool => str_starts_with($path, 'public/minn/wp-api/');
$php = array_values(array_filter($tracked, static fn (string $path): bool => $extension($path) === 'php'));
$enginePhp = array_values(array_filter($php, static fn (string $path): bool => !$isFacade($path)));
$facadePhp = array_values(array_filter($php, $isFacade));
$engineText = array_values(array_filter($tracked, static fn (string $path): bool => !$isFacade($path) && in_array($extension($path), ['js', 'css', 'html'], true)));
$engineData = array_values(array_filter($tracked, static fn (string $path): bool => !$isFacade($path) && $extension($path) === 'json'));

/**
 * A PHP file's tokens with layout and comments dropped, each as [text, line]. Names are read
 * case-blind. 'vars' sets variable names aside; 'shape' sets literals aside too.
 *
 * @return list<array{0: string, 1: int}>
 */
$phpTokens = static function (string $code, string $mode, array $setAside = []): array {
    $out = [];
    $line = 1;
    $skipping = false;
    $depth = 0;
    $tokens = @token_get_all($code);
    foreach ($tokens as $index => $token) {
        // A constant set aside: from its name to the semicolon that ends it.
        if (!$skipping && is_array($token) && $token[0] === T_STRING && isset($setAside[$token[1]])) {
            $before = $tokens[$index - 1] ?? null;
            $before2 = $tokens[$index - 2] ?? null;
            if ((is_array($before) && $before[0] === T_CONST) || (is_array($before) && $before[0] === T_WHITESPACE && is_array($before2) && $before2[0] === T_CONST)) {
                $skipping = true;
                $depth = 0;
                array_pop($out);
                continue;
            }
        }
        if ($skipping) {
            $depth += match ($token) { '[', '(', '{' => 1, ']', ')', '}' => -1, default => 0 };
            $skipping = !($token === ';' && $depth === 0);
            continue;
        }
        if (!is_array($token)) {
            $out[] = [$token, $line];
            continue;
        }
        [$id, $text, $line] = $token;
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
            continue;
        }
        if ($mode !== 'exact' && $id === T_VARIABLE) {
            $text = '$';
        } elseif ($mode === 'shape' && in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ENCAPSED_AND_WHITESPACE], true)) {
            $text = 'L';
        } elseif ($id === T_STRING || $id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED) {
            $text = strtolower($text);
        }
        $out[] = [$text, $line];
    }
    return $out;
};

/** A JS, CSS or HTML file's tokens (names, numbers, quoted strings, single characters), each as [text, line]. */
$textTokens = static function (string $code): array {
    $out = [];
    foreach (preg_split('/\R/', $code) as $index => $line) {
        preg_match_all('/[A-Za-z_$][\w$-]*|\d+(?:\.\d+)?|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|\S/', $line, $m);
        foreach ($m[0] as $token) {
            $out[] = [$token, $index + 1];
        }
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
    $p1 = 2147483629;
    $p2 = 2147483587;
    $base = 1000003;
    [$top1, $top2] = [1, 1];
    for ($i = 1; $i < $size; $i++) {
        [$top1, $top2] = [$top1 * $base % $p1, $top2 * $base % $p2];
    }
    $values = array_map(static fn (array $token): int => crc32($token[0]) % $p1, $tokens);
    $hashes = [];
    [$h1, $h2] = [0, 0];
    foreach ($values as $i => $value) {
        if ($i >= $size) {
            $out = $values[$i - $size];
            $h1 = ($h1 - $out * $top1 % $p1 + $p1) % $p1;
            $h2 = ($h2 - $out * $top2 % $p2 + $p2) % $p2;
        }
        $h1 = ($h1 * $base + $value) % $p1;
        $h2 = ($h2 * $base + $value) % $p2;
        if ($i >= $size - 1) {
            $hashes[] = ($h1 << 31) | $h2;
        }
    }
    return $hashes;
};

/**
 * Lines covered by matching windows, merged per file into "path:from-to" stretches.
 *
 * @param array<string, list<int>> $hits path => first-token indexes whose window matched
 * @param array<string, list<array{0: string, 1: int}>> $tokens path => tokens
 * @return array{stretches: list<string>, lines: int}
 */
$stretches = static function (array $hits, array $tokens, int $size): array {
    $out = [];
    $lines = 0;
    foreach ($hits as $path => $starts) {
        $covered = [];
        foreach ($starts as $start) {
            for ($line = $tokens[$path][$start][1]; $line <= $tokens[$path][$start + $size - 1][1]; $line++) {
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

/**
 * The stretches of the given files that share a window with the reference's files.
 *
 * @param list<string> $files repository paths
 * @param list<string> $referenceFiles absolute paths
 */
$against = static function (array $files, array $referenceFiles, Closure $tokenize, int $size) use ($root, $windows, $stretches): array {
    $known = [];
    foreach ($referenceFiles as $path) {
        foreach ($windows($tokenize((string) file_get_contents($path)), $size) as $hash) {
            $known[$hash] = true;
        }
    }
    $tokens = [];
    $hits = [];
    foreach ($files as $path) {
        $tokens[$path] = $tokenize((string) file_get_contents("{$root}/{$path}"), $path);
        foreach ($windows($tokens[$path], $size) as $index => $hash) {
            if (isset($known[$hash])) {
                $hits[$path][] = $index;
            }
        }
    }
    return $stretches($hits, $tokens, $size);
};

// Measure 23: windows that occur in two places in the engine and facade PHP.
$shapes = [];
$seen = [];
foreach ($php as $path) {
    $shapes[$path] = $phpTokens((string) file_get_contents("{$root}/{$path}"), 'shape');
    foreach ($windows($shapes[$path], DUPLICATE_WINDOW) as $index => $hash) {
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
$duplicated = $stretches($duplicateHits, $shapes, DUPLICATE_WINDOW);
unset($shapes);
$codeLines = static fn (array $files): int => array_sum(array_map(static fn (string $path): int => substr_count((string) file_get_contents("{$root}/{$path}"), "\n"), $files));

// Measure 18: what the engine, and the facade, share with the reference's own files.
$engine = null;
$facade = null;
$reference = is_file("{$root}/wp-reference/wp-includes/version.php") ? "{$root}/wp-reference" : null;
if ($withWordPress && $reference !== null) {
    $referenceFiles = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($reference, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!str_contains($file->getPathname(), '/wp-content/')) {
            $referenceFiles[strtolower($file->getExtension())][] = $file->getPathname();
        }
    }
    $referencePhp = $referenceFiles['php'] ?? [];
    $referenceText = array_merge($referenceFiles['js'] ?? [], $referenceFiles['css'] ?? [], $referenceFiles['html'] ?? []);

    $strict = $against($enginePhp, $referencePhp, static fn (string $code, string $path = ''): array => $phpTokens($code, 'vars', SEQUENCES[$path] ?? []), STRICT_WINDOW);
    $sensitive = $against($enginePhp, $referencePhp, static fn (string $code, string $path = ''): array => $phpTokens($code, 'vars', SEQUENCES[$path] ?? []), SENSITIVE_WINDOW);
    $assetLines = $against($engineText, $referenceText, $textTokens, TEXT_WINDOW);

    $referenceHashes = [];
    foreach ($referenceText as $path) {
        $referenceHashes[sha1_file($path)] = $path;
    }
    $identical = [];
    foreach ($engineText as $path) {
        if (isset($referenceHashes[sha1_file("{$root}/{$path}")])) {
            $identical[] = $path . ' = ' . substr($referenceHashes[sha1_file("{$root}/{$path}")], strlen($reference) + 1);
        }
    }

    // Long strings the reference holds: its PHP string literals and its JSON values.
    $referenceStrings = [];
    foreach ($referencePhp as $path) {
        foreach (@token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && strlen($token[1]) >= DATA_STRING + 2) {
                $referenceStrings[stripcslashes(substr($token[1], 1, -1))] = true;
            }
        }
    }
    foreach ($referenceFiles['json'] ?? [] as $path) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            array_walk_recursive($decoded, static function ($value) use (&$referenceStrings): void {
                if (is_string($value) && strlen($value) >= DATA_STRING) {
                    $referenceStrings[$value] = true;
                }
            });
        }
    }
    $dataFiles = [];
    $dataShared = 0;
    $dataTotal = 0;
    foreach ($engineData as $path) {
        $decoded = json_decode((string) file_get_contents("{$root}/{$path}"), true);
        if (!is_array($decoded)) {
            continue;
        }
        $shared = 0;
        array_walk_recursive($decoded, static function ($value) use (&$shared, &$dataTotal, $referenceStrings): void {
            if (is_string($value) && strlen($value) >= DATA_STRING) {
                $dataTotal++;
                $shared += isset($referenceStrings[$value]) ? 1 : 0;
            }
        });
        if ($shared > 0) {
            $dataFiles[] = "{$path}: {$shared}";
            $dataShared += $shared;
        }
    }
    unset($referenceStrings);

    $engine = [
        'of' => $codeLines($enginePhp),
        'php' => [
            'strict' => ['window' => STRICT_WINDOW, 'stretches' => count($strict['stretches']), 'lines' => $strict['lines']],
            'sensitive' => ['window' => SENSITIVE_WINDOW, 'stretches' => count($sensitive['stretches']), 'lines' => $sensitive['lines']],
        ],
        'assets' => ['identical' => count($identical), 'window' => TEXT_WINDOW, 'stretches' => count($assetLines['stretches']), 'lines' => $assetLines['lines']],
        'data' => ['strings' => $dataShared, 'of' => $dataTotal, 'files' => count($dataFiles)],
        'sequences' => array_sum(array_map('count', SEQUENCES)),
    ];
    $facadeScan = $against($facadePhp, $referencePhp, static fn (string $code): array => $phpTokens($code, 'exact'), FACADE_WINDOW);
    $facade = ['of' => $codeLines($facadePhp), 'window' => FACADE_WINDOW, 'stretches' => count($facadeScan['stretches']), 'lines' => $facadeScan['lines']];
}

$figures = [
    'duplicated' => ['window' => DUPLICATE_WINDOW, 'stretches' => count($duplicated['stretches']), 'lines' => $duplicated['lines'], 'of' => $codeLines($php)],
    'engine' => $engine,
    'facade' => $facade,
];
if ($asJson) {
    echo json_encode($figures, JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
printf("duplicated: %d lines in %d stretches, of %d (windows of %d tokens, variables and literals set aside)\n",
    $duplicated['lines'], count($duplicated['stretches']), $figures['duplicated']['of'], DUPLICATE_WINDOW);
if ($engine === null) {
    echo "engine and facade: not compared with the reference\n";
} else {
    printf("engine PHP (%d lines) shared with the reference, variable names set aside: %d lines in %d stretches at %d tokens, %d lines in %d stretches at %d\n",
        $engine['of'], $engine['php']['strict']['lines'], $engine['php']['strict']['stretches'], STRICT_WINDOW,
        $engine['php']['sensitive']['lines'], $engine['php']['sensitive']['stretches'], SENSITIVE_WINDOW);
    printf("engine PHP sequences set aside as behaviour: %d constants (SEQUENCES)\n", $engine['sequences']);
    printf("engine assets: %d files identical to the reference's, %d lines in %d stretches shared (%d tokens)\n",
        $engine['assets']['identical'], $engine['assets']['lines'], $engine['assets']['stretches'], TEXT_WINDOW);
    printf("engine data: %d of %d strings of %d+ characters also in the reference, across %d files\n",
        $engine['data']['strings'], $engine['data']['of'], DATA_STRING, $engine['data']['files']);
    printf("facade PHP (%d lines): %d lines in %d stretches identical to the reference (%d tokens)\n",
        $facade['of'], $facade['lines'], $facade['stretches'], FACADE_WINDOW);
}
if ($list) {
    foreach ($duplicated['stretches'] as $stretch) {
        echo "  duplicated: {$stretch}\n";
    }
    if ($engine !== null) {
        foreach ([['engine php', $sensitive['stretches']], ['engine php (strict)', $strict['stretches']], ['engine asset', $assetLines['stretches']], ['engine asset identical', $identical], ['engine data', $dataFiles], ['facade', $facadeScan['stretches']]] as [$label, $items]) {
            foreach ($items as $item) {
                echo "  {$label}: {$item}\n";
            }
        }
    }
}
