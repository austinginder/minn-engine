<?php
/**
 * The 0.1.0 maturity scorecard (audit 2026-10-12): each measure the engine
 * matures against, its figure and its target, gathered from what the repo
 * holds (no site is asked). Writes contracts/scorecard.json, which
 * compat-status.php --site carries into the marketing site's compat.json
 * beside the API figures.
 *
 * - security: the audit's findings, each counted fixed when the check that
 *   pins it is in the repo;
 * - sql: SELECTs per benchmark page against the reference
 *   (contracts/query-count.json, tests/tools/query-count.php --write);
 * - hooks: accounted for, and proven weighted by plugin installs
 *   (contracts/api/hook-status.json, tests/tools/hook-status.php);
 * - coverage: engine line coverage (contracts/line-coverage.json,
 *   tests/tools/line-coverage.php);
 * - leanness: dead-name methods and the leanness ratchets, as the style
 *   suite holds them;
 * - open: contracts/runtime.md's open items register.
 *
 * php tests/tools/scorecard.php
 */

$root = dirname(__DIR__, 2);
$json = static fn (string $path): ?array => is_file("{$root}/{$path}") ? json_decode((string) file_get_contents("{$root}/{$path}"), true) : null;
$contains = static fn (string $path, string $text): bool => is_file("{$root}/{$path}") && str_contains((string) file_get_contents("{$root}/{$path}"), $text);

// The audit's security findings, each with the check that pins it.
$findings = [
    'File changes allowed under DISALLOW_FILE_MODS' => ['tests/tools/security-parity-probe.php', 'file_mod_allowed'],
    'Search reached non-public types' => ['tests/tools/security-parity-probe.php', 'search a non-public type, nobody'],
    'Signed-out search found password-protected posts' => ['tests/tools/security-parity-probe.php', 'search a protected post, nobody'],
    'A type\'s own REST controller bypassed' => ['tests/tools/security-parity-probe.php', 'a type with its own controller, nobody'],
    'Comment author, address and status set over REST by anyone' => ['tests/tools/security-parity-probe.php', 'a comment naming user 1'],
    'Redirect sanitizing let //host through' => ['tests/tools/security-parity-probe.php', 'wp_sanitize_redirect'],
    'Password cookies named their own work factor' => ['tests/unit/security-bounds.php', 'cookieUnlocks'],
    'Serialized data looped the decoder or revived objects' => ['tests/unit/security-bounds.php', 'Serialized::decode'],
];
$fixed = count(array_filter($findings, static fn (array $pin): bool => $contains($pin[0], $pin[1])));

$sql = null;
if (($counts = $json('contracts/query-count.json')) !== null) {
    $pages = [];
    foreach ((array) $counts['pages'] as $page => $stacks) {
        $pages[$page] = [(int) $stacks['engine']['Com_select'], (int) $stacks['reference']['Com_select']];
    }
    $sql = ['measured' => $counts['measured'], 'pages' => $pages, 'atOrUnder' => count(array_filter($pages, static fn (array $p): bool => $p[0] <= $p[1]))];
}

$hooks = ($status = $json('contracts/api/hook-status.json')) === null ? null : $status['figures'];
$coverage = ($lines = $json('contracts/line-coverage.json')) === null ? null : ['measured' => $lines['measured'], 'percent' => $lines['percent'], 'lines' => $lines['lines'], 'covered' => $lines['covered'], 'parts' => $lines['parts']];

// The leanness ceilings the style suite holds (each only falls).
$style = (string) file_get_contents("{$root}/tests/style.test.php");
$ceilings = [];
foreach (['deadNameCeiling' => 'Dead-name methods', 'liveCeiling' => 'Lifecycle branches (->live())', 'optionReadCeiling' => 'Option reads beside the store', 'rawOptionWriteCeiling' => 'Raw option writes', 'rawMetaWriteCeiling' => 'Raw meta writes', 'postRowCeiling' => 'Hand-built post rows', 'linkHrefCeiling' => 'Hand-built REST links', 'htmlspecialcharsCeiling' => 'htmlspecialchars outside Html', 'permalinkCallerCeiling' => 'Unfiltered permalink callers', 'bootedCeiling' => 'Runtime::booted() branches', 'wordpressCallCeiling' => 'Calls into WordPress-named functions'] as $variable => $label) {
    if (preg_match('/\$' . $variable . ' = (\d+);/', $style, $m)) {
        $ceilings[$variable] = ['label' => $label, 'ceiling' => (int) $m[1]];
    }
}

// The open items register: rows marked open, accepted, done.
$open = null;
$runtime = (string) file_get_contents("{$root}/contracts/runtime.md");
if (preg_match('/^## Open items register\b.*?(?=^## |\z)/ms', $runtime, $register)) {
    preg_match_all('/^\|[^|\n]*\|[^|\n]*\|[^|\n]*\|\s*(open|accepted|done|austin)\b/mi', $register[0], $rows);
    $open = array_count_values(array_map('strtolower', $rows[1])) + ['open' => 0, 'accepted' => 0, 'done' => 0, 'austin' => 0];
}

$meta = $json('contracts/api/meta.json') ?? [];
$card = [
    'wordpress' => $meta['version'] ?? '',
    'security' => ['found' => count($findings), 'fixed' => $fixed],
    'sql' => $sql,
    'hooks' => $hooks,
    'coverage' => $coverage,
    'leanness' => $ceilings,
    'open' => $open,
];
file_put_contents("{$root}/contracts/scorecard.json", json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("security %d/%d fixed; sql %s; hooks %s; coverage %s; open %s\n",
    $fixed, count($findings),
    $sql === null ? 'unmeasured' : "{$sql['atOrUnder']}/" . count($sql['pages']) . ' pages at or under',
    $hooks === null ? 'unmeasured' : "{$hooks['accounted']}% accounted, {$hooks['provenWeighted']}% proven (weighted)",
    $coverage === null ? 'unmeasured' : "{$coverage['percent']}%",
    $open === null ? 'no register' : "{$open['open']} open, {$open['austin']} for Austin, {$open['accepted']} accepted, {$open['done']} done");
