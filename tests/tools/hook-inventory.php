<?php
/**
 * Hook inventory: every hook name the reference fires, with kind and
 * argument count, found by scanning for the firing calls. Names are
 * interface; nothing else is read. Run: php tests/tools/hook-inventory.php
 */

$root = dirname(__DIR__, 2) . '/wp-reference/';
$out = dirname(__DIR__, 2) . '/contracts/api/hooks.json';
$hooks = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($root));
    if (!str_starts_with($rel, 'wp-includes/') && !str_starts_with($rel, 'wp-admin/') && !in_array($rel, ['wp-settings.php', 'wp-load.php', 'wp-login.php', 'wp-cron.php', 'wp-comments-post.php', 'xmlrpc.php', 'wp-signup.php', 'wp-activate.php', 'wp-mail.php', 'wp-trackback.php'], true)) {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (preg_match_all('/\b(do_action|apply_filters|do_action_ref_array|apply_filters_ref_array|do_action_deprecated|apply_filters_deprecated)\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\2\s*(,|\))/', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $hit) {
            $fn = $hit[1][0];
            $name = $hit[3][0];
            $kind = str_starts_with($fn, 'do_action') ? 'action' : 'filter';
            // Count the arguments after the name up to the matching paren.
            $pos = $hit[0][1] + strlen($hit[0][0]);
            $depth = $hit[4][0] === ')' ? 0 : 1;
            $args = $hit[4][0] === ')' ? 0 : 1;
            $i = $pos;
            $inStr = null;
            while ($depth > 0 && $i < strlen($src)) {
                $ch = $src[$i];
                if ($inStr !== null) {
                    if ($ch === '\\') { $i++; } elseif ($ch === $inStr) { $inStr = null; }
                } elseif ($ch === '"' || $ch === "'") {
                    $inStr = $ch;
                } elseif ($ch === '(' || $ch === '[') {
                    $depth++;
                } elseif ($ch === ')' || $ch === ']') {
                    $depth--;
                } elseif ($ch === ',' && $depth === 1) {
                    $args++;
                }
                $i++;
            }
            if (str_ends_with($fn, '_ref_array')) {
                $args = null; // an array of args; count unknown statically
            }
            $row = $hooks[$name] ?? ['kind' => $kind, 'args' => $args, 'files' => []];
            if (!in_array($rel, $row['files'], true)) {
                $row['files'][] = $rel;
            }
            if ($args !== null && ($row['args'] === null || $args > $row['args'])) {
                $row['args'] = $args;
            }
            if (str_contains($fn, 'deprecated')) {
                $row['deprecated'] = true;
            }
            $hooks[$name] = $row;
        }
    }
    // Dynamic names: "pre_option_{$option}" and 'update_option_' . $option.
    if (preg_match_all('/\b(do_action|apply_filters)\s*\(\s*"([^"]*\{\$[^"]*)"/', $src, $m)) {
        foreach ($m[2] as $i => $tpl) {
            $name = preg_replace('/\{\$[^}]+\}/', '{*}', $tpl);
            $kind = $m[1][$i] === 'do_action' ? 'action' : 'filter';
            $row = $hooks[$name] ?? ['kind' => $kind, 'args' => null, 'files' => [], 'dynamic' => true];
            if (!in_array($rel, $row['files'], true)) {
                $row['files'][] = $rel;
            }
            $hooks[$name] = $row;
        }
    }
}
ksort($hooks);
foreach ($hooks as &$row) {
    sort($row['files']);
}
file_put_contents($out, json_encode($hooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$actions = count(array_filter($hooks, static fn ($h) => $h['kind'] === 'action'));
fwrite(STDERR, sprintf("hooks %d (actions %d, filters %d, dynamic %d)\n", count($hooks), $actions, count($hooks) - $actions, count(array_filter($hooks, static fn ($h) => !empty($h['dynamic'])))));
