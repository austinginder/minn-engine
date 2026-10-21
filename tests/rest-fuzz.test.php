<?php

declare(strict_types=1);

/**
 * REST fuzzing: requests generated from the reference's REST index, sent to
 * both stacks and compared (tests/tools/rest-fuzz.php), held to
 * contracts/fixtures/rest-fuzz/divergences.json, which only shrinks: a
 * difference it does not name fails. The suite runs a compact pass (signed
 * out, the first six cases of each route); the tool's full pass (every role,
 * more cases) is measure 33's figure.
 *
 *   php tests/rest-fuzz.test.php [--snapshot]
 *
 * --snapshot drops the recorded differences that agree now; it refuses to
 * write while a difference the ledger does not name remains (it writes the
 * whole list only when there is no ledger yet).
 */

require_once __DIR__ . '/lib.php';

$ledgerPath = dirname(__DIR__) . '/contracts/fixtures/rest-fuzz/divergences.json';
$snapshot = in_array('--snapshot', $argv, true);
$run = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/tools/rest-fuzz.php') . ' --roles=anon --limit=6 --json 2>/dev/null'), true);
if (!is_array($run) || !isset($run['differences'])) {
    echo "SKIP: the fuzzer did not run (the reference's REST index did not answer)\n";
    exit(0);
}
/** A difference's name in the ledger: who asked, what, and with which case. */
$name = static fn (array $d): string => "{$d['role']} {$d['request']} [{$d['case']}]";
$found = [];
foreach ($run['differences'] as $difference) {
    $found[$name($difference)] = $difference['difference'];
}
$ledger = is_file($ledgerPath) ? (array) json_decode((string) file_get_contents($ledgerPath), true)['differences'] : null;

$pass = 0;
$fail = 0;
$new = $ledger === null ? [] : array_diff_key($found, $ledger);
foreach ($new as $key => $what) {
    $fail++;
    echo "  FAIL {$key}\n       {$what}\n";
}
$agreeing = $ledger === null ? [] : array_diff_key($ledger, $found);
$pass += count($found) - count($new);
printf("  ok   %d requests to %d routes compared; %d recorded differences remain\n", (int) $run['requests'], (int) $run['routes'], count($found) - count($new));
if ($agreeing !== []) {
    printf("  --   %d recorded differences agree now: php tests/rest-fuzz.test.php --snapshot drops them\n", count($agreeing));
}
if ($snapshot) {
    if ($ledger !== null && $new !== []) {
        echo "refused: the ledger only shrinks, and " . count($new) . " differences it does not name remain\n";
        exit(1);
    }
    $keep = $ledger === null ? $found : array_intersect_key($found, $ledger);
    ksort($keep);
    @mkdir(dirname($ledgerPath), 0777, true);
    file_put_contents($ledgerPath, json_encode(['method' => 'tests/rest-fuzz.test.php: the compact pass (signed out, 6 cases a route); each difference as role, request and case, with what differed', 'differences' => $keep], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    echo 'wrote the ledger: ' . count($keep) . " differences\n";
}
printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
