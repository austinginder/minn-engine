<?php
/**
 * Where the engine stands on the reference's hooks, tracked as functions
 * are (compat-status.php). Every hook in contracts/api/hooks.json is one of:
 *
 * - fired: the engine fires it, by name in src/ or wp-api/ (a dynamic name
 *   by its pattern, "pre_option_{$name}" as pre_option_{*}), or seen firing
 *   on the engine by the hook-parity battery;
 * - deadend: fired on the reference only from what Minn leaves behind (the
 *   dead-end functions and classes of public/minn/data/deadend-symbols.json,
 *   the wp-admin screens but admin-ajax.php, XML-RPC, trackbacks, post by
 *   email in wp-mail.php), or named under "hooks" in
 *   contracts/api/omitted.json with its reason;
 * - different: named under "hooks" in contracts/api/different.json: Minn
 *   answers it its own way, by design (Austin decides what goes there);
 * - outside: fired only from code a single site never loads (multisite,
 *   the screens' list tables, signup and activation, wp-includes/rss.php), which the function
 *   and class inventory leaves out too; or listed in contracts/api/
 *   hooks-single-site.json (a function a single site loads fires it only
 *   on a network), while the reference fired it in no scenario of the
 *   battery (probe single-site-inert calls those functions; every request
 *   boots wp-settings.php); not counted;
 * - missing: none of these.
 *
 * Accounted for = (fired + deadend + different) / (total - outside). Proven = the hooks
 * the hook-parity suite holds equal to the reference in at least one
 * scenario (contracts/fixtures/hooks/battery.json, less the differences
 * kept in engine-divergences.json) / (total - deadend - different - outside), counted per hook and
 * weighted by how many installs of the most installed plugins hook each one
 * (contracts/api/hook-weights.json, tests/tools/hook-weights.php).
 *
 * php tests/tools/hook-status.php                    writes contracts/api/hook-status.json, prints the figures
 * php tests/tools/hook-status.php --list=missing     (or unproven, deadend, different, outside), heaviest first
 */

$root = dirname(__DIR__, 2);
$options = getopt('', ['list:']);
$json = static fn (string $path): array => json_decode((string) @file_get_contents("{$root}/{$path}"), true) ?: [];

$inventory = $json('contracts/api/hooks.json');
$deadEnds = $json('public/minn/data/deadend-symbols.json');
$deadFunctions = array_change_key_case((array) ($deadEnds['functions'] ?? []));
$knownFunctions = array_change_key_case($json('contracts/api/functions.json'));
$knownClasses = array_change_key_case($json('contracts/api/classes.json'));
$deadClasses = array_change_key_case((array) ($deadEnds['classes'] ?? []));
$omitted = (array) ($json('contracts/api/omitted.json')['hooks'] ?? []);
$different = (array) ($json('contracts/api/different.json')['hooks'] ?? []);
$weights = (array) ($json('contracts/api/hook-weights.json')['hooks'] ?? []);
$singleSite = (array) ($json('contracts/api/hooks-single-site.json')['hooks'] ?? []);

// ---- what the engine fires by name -------------------------------------------------------
/** A firing call's name as the inventory writes it: interpolations and a joined variable as {*}. */
$normalise = static function (string $quote, string $name, bool $joined): string {
    if ($quote === '"') {
        $name = (string) preg_replace('/\{\$[^}]+\}|\$[a-zA-Z_][a-zA-Z0-9_]*(?:->[a-zA-Z_][a-zA-Z0-9_]*|\[[^\]]*\])*/', '{*}', $name);
    }
    return $joined ? $name . '{*}' : $name;
};
$fired = [];
$firing = '/(?:\b(?:do_action|do_action_ref_array|do_action_deprecated|apply_filters|apply_filters_ref_array|apply_filters_deprecated)|->(?:action|actionRef|actionRefArray|filter|filterWithout|filterRefArray)|EarlyFilters::apply)\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\1\s*(,|\)|\.\s*\$)/';
// The engine's files, and bootstrap.php, which asks the hooks added before boot (Runtime\EarlyFilters) about advanced-cache.php.
$engineFiles = static function () use ($root): Generator {
    yield new SplFileInfo("{$root}/public/minn/bootstrap.php");
    foreach (['public/minn/src', 'public/minn/wp-api'] as $tree) {
        yield from new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$tree}", FilesystemIterator::SKIP_DOTS));
    }
};
foreach ($engineFiles() as $file) {
    if ($file->getExtension() !== 'php' || !preg_match_all($firing, (string) file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($matches as $match) {
        $fired[$normalise($match[1], $match[2], str_starts_with($match[3], '.'))] = true;
    }
}

// A live hook named whole as a string anywhere in the engine but a subscription (add_, has_, remove_, did_,
// doing_) is taken as fired through a helper that takes its name (Runtime\CommentWrites::setAside(..., 'spammed_comment')).
// It only ever keeps a hook from counting as missing: dead ends and hooks outside a single site stay what they are.
$named = [];
foreach (['public/minn/src', 'public/minn/wp-api'] as $tree) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$tree}", FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) preg_replace('/\b(?:add|has|remove|did|doing)_(?:action|filter)s?\s*\(\s*([\'"])(?:[^\'"\\\\]|\\\\.)*\1/', '', (string) file_get_contents($file->getPathname()));
        if (preg_match_all('/([\'"])([a-z_][a-z0-9_\-\/{}$]*)\1/i', $source, $quoted)) {
            foreach ($quoted[2] as $index => $name) {
                $named[$normalise($quoted[1][$index], $name, false)] = true;
            }
        }
    }
}

// ---- what the battery proves -------------------------------------------------------------
// A hook is proven when it agrees in at least one scenario the reference fires it in: fired
// there, not among the engine's differences the hook-parity suite keeps for it.
$battery = (array) (json_decode((string) @file_get_contents("{$root}/contracts/fixtures/hooks/battery.json"), true)['scenarios'] ?? []);
$differs = (array) (json_decode((string) @file_get_contents("{$root}/contracts/fixtures/hooks/engine-divergences.json"), true)['hooks'] ?? []);
$proven = [];
foreach ($battery as $scenario => $hooks) {
    foreach (array_keys((array) $hooks) as $hook) {
        if (!isset($differs[$hook][$scenario])) {
            $proven[(string) $hook] = true;
        }
    }
}
// A hook the engine fires somewhere, if not yet as the reference does (another argument count or kind, or in a
// scenario of its own), is fired: only one never fired anywhere is still owed.
foreach ($differs as $hook => $where) {
    foreach ((array) $where as $how) {
        if ($how !== 'not fired') {
            $fired[(string) $hook] = true;
        }
    }
}

// A hook listed as firing only on a network counts as outside while the reference fired it in no scenario of
// the battery: not in probe single-site-inert, which calls the functions that fire them, nor in any request,
// each of which boots wp-settings.php.
$referenceFired = [];
foreach ($battery as $hooks) {
    $referenceFired += array_fill_keys(array_keys((array) $hooks), true);
}
$inert = [];
foreach (array_keys($singleSite) as $hook) {
    if (isset($battery['probe:single-site-inert']) && !isset($referenceFired[$hook]) && !isset($fired[$hook]) && !isset($proven[$hook])) {
        $inert[(string) $hook] = true;
    }
}

// ---- each hook's status ------------------------------------------------------------------
/** What a firing site is: 'outside' the single-site API, 'dead' (left behind on purpose), or 'live'. */
$siteKind = static function (string $site) use ($deadFunctions, $deadClasses, $knownFunctions, $knownClasses): string {
    if (str_contains($site, '::')) {
        $class = strtolower(explode('::', $site)[0]);
        return !isset($knownClasses[$class]) ? 'outside' : (isset($deadClasses[$class]) ? 'dead' : 'live');
    }
    if (str_ends_with($site, '.php')) {
        // wp-includes/rss.php is loaded by no WordPress code, only by a plugin that asks for it, so its functions
        // are outside the inventory too.
        if (in_array($site, ['wp-signup.php', 'wp-activate.php', 'wp-includes/rss.php'], true)) {
            return 'outside';
        }
        // The engine serves admin-ajax.php itself (Runtime\AjaxController); the rest of wp-admin is Minn Admin's.
        // wp-mail.php is the post-by-email script, which needs the POP3 client omitted.json leaves behind.
        return (str_starts_with($site, 'wp-admin/') && $site !== 'wp-admin/admin-ajax.php') || in_array($site, ['xmlrpc.php', 'wp-trackback.php', 'wp-mail.php'], true) ? 'dead' : 'live';
    }
    $function = strtolower($site);
    return !isset($knownFunctions[$function]) ? 'outside' : (isset($deadFunctions[$function]) ? 'dead' : 'live');
};
$status = [];
foreach ($inventory as $name => $row) {
    $name = (string) $name;
    if ($name === '') {
        continue;
    }
    $sites = (array) ($row['in'] ?? $row['files'] ?? []);
    if (isset($fired[$name]) || isset($proven[$name])) {
        $status[$name] = isset($proven[$name]) ? 'proven' : 'fired';
    } elseif (isset($different[$name])) {
        $status[$name] = 'different';
    } else {
        $kinds = array_unique(array_map($siteKind, $sites));
        $status[$name] = match (true) {
            isset($omitted[$name]) => 'deadend',
            isset($inert[$name]) => 'outside',
            in_array('live', $kinds, true) || $kinds === [] => isset($named[$name]) ? 'fired' : 'missing',
            in_array('dead', $kinds, true) => 'deadend',
            default => 'outside',
        };
    }
}
ksort($status);

// ---- figures -----------------------------------------------------------------------------
$weight = static fn (string $name): int => (int) ($weights[$name][1] ?? 0);
$counts = array_count_values($status) + ['proven' => 0, 'fired' => 0, 'deadend' => 0, 'different' => 0, 'outside' => 0, 'missing' => 0];
$total = count($status) - $counts['outside'];
$live = array_filter($status, static fn (string $s): bool => !in_array($s, ['deadend', 'different', 'outside'], true));
$liveWeight = array_sum(array_map($weight, array_keys($live)));
$provenWeight = array_sum(array_map($weight, array_keys(array_filter($live, static fn (string $s): bool => $s === 'proven'))));
$floor = static fn (float $value): float => floor($value * 10) / 10;
$figures = [
    'total' => $total,
    'proven' => $counts['proven'],
    'fired' => $counts['proven'] + $counts['fired'],
    'deadend' => $counts['deadend'],
    'different' => $counts['different'],
    'missing' => $counts['missing'],
    'outside' => $counts['outside'],
    'accounted' => $floor(100 * ($total - $counts['missing']) / max(1, $total)),
    'provenOfLive' => $floor(100 * $counts['proven'] / max(1, count($live))),
    'provenWeighted' => $floor(100 * $provenWeight / max(1, $liveWeight)),
];

if (isset($options['list'])) {
    $want = (string) $options['list'];
    $rows = array_filter($status, static fn (string $s): bool => $want === 'unproven' ? $s === 'fired' || $s === 'missing' : $s === $want);
    uksort($rows, static fn (string $a, string $b): int => $weight($b) <=> $weight($a) ?: strcmp($a, $b));
    foreach ($rows as $name => $s) {
        printf("%s\t%s\t%d\t%s\t%s\n", $name, $s, $weight($name), $inventory[$name]['kind'] ?? '', implode(',', array_slice((array) ($inventory[$name]['in'] ?? []), 0, 3)));
    }
    exit(0);
}

file_put_contents("{$root}/contracts/api/hook-status.json", json_encode(['figures' => $figures, 'hooks' => $status], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("hooks %d (and %d outside a single site): %d proven, %d fired unproven, %d dead end, %d different, %d missing\n", $total, $counts['outside'], $counts['proven'], $counts['fired'], $counts['deadend'], $counts['different'], $counts['missing']);
printf("accounted for %.1f%%; proven %.1f%% of live hooks, %.1f%% weighted by plugin installs\n", $figures['accounted'], $figures['provenOfLive'], $figures['provenWeighted']);
