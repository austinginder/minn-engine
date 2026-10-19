<?php
/**
 * What each api probe writes, on both stacks. For the pass the tool puts a
 * trigger on every table of the test site's database (the reference and
 * the engine share it), so every row a probe inserts, updates or deletes is
 * seen, a row made and deleted again within the probe included. Each probe
 * runs on the reference (wp eval-file) and then on the engine
 * (run-api-probe.php); after each run the rows it changed are put back as
 * they were (each row read back to check), so both runs start from the same
 * database. The tables a run makes or drops, and the files under
 * wp-content it leaves made or changed, are noted too.
 *
 * Each row's history (the states it went through, consecutive repeats
 * dropped) is compared between the stacks. Rows a run makes are named by
 * their table and the order they were made in (posts#1), and so are the
 * columns pointing at them; dates, timestamps and hashes are masked, and
 * serialized values compared decoded. What a stack writes only by
 * starting (probe side-effects-baseline) is set aside on that stack. The
 * rows whose histories differ are the probe's side-effect differences.
 *
 * Only the probe's own connection is recorded (it is marked: side-effects-
 * tag.php on the reference, side-effects-runner.php on the engine), so a
 * request to the site while a probe runs is neither counted nor put back.
 *
 * The differences found are held to contracts/fixtures/effects/
 * divergences.json, which only shrinks: a probe it covers that differs in a
 * row it does not name (or ends otherwise where it only passed otherwise)
 * fails, and --snapshot refuses to write such a growth. A difference that
 * contracts/fixtures/effects/accepted.json names (settled elsewhere, or the
 * tool's own doing) is set aside, with its reason.
 *
 * A probe that differs anew runs once more, and only what differs both
 * times counts.
 *
 *   php tests/tools/side-effects.php [--only=probe-a,probe-b] [--show] [--json=out.json] [--snapshot]
 *   php tests/tools/side-effects.php --from=out.json [--snapshot]   (a saved report, nothing run)
 *   php tests/tools/side-effects.php --uninstall   (takes the triggers off after a crash)
 */

require __DIR__ . '/../lib.php';

const AUDIT = 'zz_side_effects';
/** The session variable that marks a probe's connection (side-effects-tag.php, side-effects-runner.php). */
const TAG = '@zz_side_effects_probe';

$options = getopt('', ['only:', 'show', 'json:', 'uninstall', 'snapshot', 'from:']);
$snapshotFile = dirname(__DIR__, 2) . '/contracts/fixtures/effects/divergences.json';
$acceptedFile = dirname(__DIR__, 2) . '/contracts/fixtures/effects/accepted.json';
$root = dirname(__DIR__, 2);
$siteRoot = minn_test_site_root();
$config = str_replace("require_once ABSPATH . 'wp-settings.php';", '', (string) file_get_contents($siteRoot . '/public/wp-config.php'));
define('ABSPATH', $siteRoot . '/public/');
eval('?>' . $config);
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$db->set_charset('utf8mb4');
$prefix = $table_prefix;

/** The site's base tables, the audit table apart. */
function fx_tables(mysqli $db): array
{
    $rows = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> '" . AUDIT . "' ORDER BY TABLE_NAME")->fetch_all();
    return array_map(static fn (array $row): string => (string) $row[0], $rows);
}

/** @return array{columns: list<string>, key: list<string>, auto: ?string} */
function fx_shape(mysqli $db, string $table): array
{
    static $shapes = [];
    if (isset($shapes[$table])) {
        return $shapes[$table];
    }
    $columns = [];
    $auto = null;
    $stmt = $db->prepare('SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all() as [$name, $extra]) {
        $columns[] = (string) $name;
        $auto = str_contains((string) $extra, 'auto_increment') ? (string) $name : $auto;
    }
    $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $key = array_map(static fn (array $row): string => (string) $row[0], $stmt->get_result()->fetch_all());
    return $shapes[$table] = ['columns' => $columns, 'key' => $key, 'auto' => $auto];
}

function fx_trigger(string $table, string $op): string
{
    return 'zzfx_' . substr(md5($table), 0, 12) . '_' . $op;
}

/** Takes every trigger of the tool off, then its audit table. */
function fx_uninstall(mysqli $db): int
{
    $dropped = 0;
    foreach ($db->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE 'zzfx\\_%'")->fetch_all() as [$name]) {
        $db->query("DROP TRIGGER IF EXISTS `{$name}`");
        $dropped++;
    }
    $db->query('DROP TABLE IF EXISTS `' . AUDIT . '`');
    return $dropped;
}

function fx_install(mysqli $db): void
{
    fx_uninstall($db);
    $db->query('CREATE TABLE `' . AUDIT . '` (seq BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tbl VARCHAR(191) NOT NULL, op CHAR(1) NOT NULL, conn BIGINT UNSIGNED NOT NULL, old_row LONGTEXT NULL, new_row LONGTEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    foreach (fx_tables($db) as $table) {
        $columns = fx_shape($db, $table)['columns'];
        $json = static fn (string $row): string => 'JSON_OBJECT(' . implode(', ', array_map(static fn (string $column): string => "'" . $db->real_escape_string($column) . "', {$row}.`{$column}`", $columns)) . ')';
        $name = $db->real_escape_string($table);
        foreach (['i' => ['INSERT', 'NULL', $json('NEW')], 'u' => ['UPDATE', $json('OLD'), $json('NEW')], 'd' => ['DELETE', $json('OLD'), 'NULL']] as $op => [$event, $old, $new]) {
            $sql = 'CREATE TRIGGER `' . fx_trigger($table, $op) . "` AFTER {$event} ON `{$table}` FOR EACH ROW BEGIN IF " . TAG . " IS NOT NULL THEN INSERT INTO `" . AUDIT . "` (tbl, op, conn, old_row, new_row) VALUES ('{$name}', '{$op}', CONNECTION_ID(), {$old}, {$new}); END IF; END";
            if (!$db->query($sql)) {
                fx_uninstall($db);
                fwrite(STDERR, "side-effects: could not put a trigger on {$table}: {$db->error}\n");
                exit(1);
            }
        }
    }
}

if (isset($options['uninstall'])) {
    echo 'took off ' . fx_uninstall($db) . " triggers\n";
    exit(0);
}

/**
 * Puts back each row a run changed, as it was before the run: rows it
 * made are deleted, rows it deleted made again, rows it changed changed
 * back (deletes first, then updates, then inserts, so a unique key a
 * replaced row held is free again).
 *
 * @param list<array{tbl: string, op: string, old: ?array, new: ?array}> $events
 */
function fx_revert(mysqli $db, array $events): array
{
    $first = [];
    $last = [];
    foreach ($events as $event) {
        $shape = fx_shape($db, $event['tbl']);
        if ($shape['key'] === []) {
            continue;
        }
        $row = $event['new'] ?? $event['old'];
        $id = $event['tbl'] . '|' . json_encode(array_map(static fn (string $column) => $row[$column] ?? null, $shape['key']));
        if (!array_key_exists($id, $first)) {
            $first[$id] = [$event['tbl'], $event['op'] === 'i' ? null : $event['old']];
        }
        $last[$id] = $event['op'] === 'd' ? null : $event['new'];
    }
    $deletes = $updates = $inserts = [];
    $expected = [];
    foreach ($first as $id => [$table, $before]) {
        $expected[$id] = [$table, $before, $last[$id]];
        $after = $last[$id];
        if (json_encode($before) === json_encode($after)) {
            continue;
        }
        match (true) {
            $before === null => $deletes[] = [$table, $after],
            $after === null => $inserts[] = [$table, $before],
            default => $updates[] = [$table, $before, $after],
        };
    }
    $run = static function (string $sql, array $values) use ($db): void {
        $stmt = $db->prepare($sql);
        if ($values !== []) {
            $stmt->bind_param(str_repeat('s', count($values)), ...array_map(static fn ($value) => $value === null ? null : (is_scalar($value) ? (string) $value : json_encode($value)), $values));
        }
        if (!$stmt->execute()) {
            fwrite(STDERR, "side-effects: could not put a row back ({$sql}): {$stmt->error}\n");
        }
    };
    $where = static fn (array $key): string => implode(' AND ', array_map(static fn (string $column): string => "`{$column}` = ?", $key));
    foreach ($deletes as [$table, $after]) {
        $key = fx_shape($db, $table)['key'];
        $run("DELETE FROM `{$table}` WHERE " . $where($key), array_map(static fn (string $column) => $after[$column], $key));
    }
    foreach ($updates as [$table, $before, $after]) {
        $key = fx_shape($db, $table)['key'];
        $columns = array_keys($before);
        $run("UPDATE `{$table}` SET " . implode(', ', array_map(static fn (string $column): string => "`{$column}` = ?", $columns)) . ' WHERE ' . $where($key), [...array_values($before), ...array_map(static fn (string $column) => $after[$column], $key)]);
    }
    foreach ($inserts as [$table, $before]) {
        $columns = array_keys($before);
        $run("INSERT INTO `{$table}` (`" . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')', array_values($before));
    }
    return $expected;
}

/**
 * The rows a put-back did not bring back as they were: each row the run
 * touched read again by its key and compared with its state before the run.
 *
 * @param array<string, array{0: string, 1: ?array, 2: ?array}> $expected
 * @return list<string>
 */
function fx_unrestored(mysqli $db, array $expected): array
{
    $out = [];
    foreach ($expected as $id => [$table, $before, $after]) {
        $shape = fx_shape($db, $table);
        $row = $before ?? $after;
        $json = 'JSON_OBJECT(' . implode(', ', array_map(static fn (string $column): string => "'" . $db->real_escape_string($column) . "', `{$column}`", $shape['columns'])) . ')';
        $stmt = $db->prepare("SELECT {$json} FROM `{$table}` WHERE " . implode(' AND ', array_map(static fn (string $column): string => "`{$column}` = ?", $shape['key'])));
        $values = array_map(static fn (string $column) => is_scalar($row[$column] ?? null) ? (string) $row[$column] : null, $shape['key']);
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        $stmt->execute();
        $now = $stmt->get_result()->fetch_row();
        $now = $now === null ? null : json_decode((string) $now[0], true);
        if (json_encode($now) !== json_encode($before)) {
            $out[] = $id;
        }
    }
    return $out;
}

/** Runs a command to its end and gives its exit code and the end of what it said on stderr. */
function fx_exec(string $command, string $cwd): array
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($process), trim(substr((string) $stderr, -400))];
}

/**
 * One probe on one stack: the rows it touched (in order), the tables it
 * made or dropped, the files under wp-content it left made or changed,
 * then everything put back.
 */
function fx_run(mysqli $db, string $stack, string $probe, string $root, string $siteRoot): array
{
    $db->query('TRUNCATE TABLE `' . AUDIT . '`');
    $tablesBefore = fx_tables($db);
    $marker = tempnam(sys_get_temp_dir(), 'minn-fx');
    touch($marker, time() - 1);
    [$code, $stderr] = $stack === 'reference'
        // WordPress alone: the engine's probe runner loads no plugins either (the must-use ones still load).
        ? fx_exec('/opt/homebrew/bin/wp eval-file --skip-plugins --require=' . escapeshellarg("{$root}/tests/tools/side-effects-tag.php") . ' ' . escapeshellarg("{$root}/tests/tools/{$probe}"), "{$siteRoot}/wp-reference")
        : fx_exec('php ' . escapeshellarg("{$root}/tests/tools/side-effects-runner.php") . ' ' . escapeshellarg($probe), $root);
    $events = [];
    $connections = [];
    foreach ($db->query('SELECT tbl, op, conn, old_row, new_row FROM `' . AUDIT . '` ORDER BY seq')->fetch_all(MYSQLI_ASSOC) as $row) {
        $events[] = ['tbl' => (string) $row['tbl'], 'op' => (string) $row['op'], 'old' => $row['old_row'] === null ? null : json_decode((string) $row['old_row'], true), 'new' => $row['new_row'] === null ? null : json_decode((string) $row['new_row'], true)];
        $connections[(int) $row['conn']] = true;
    }
    $tablesAfter = fx_tables($db);
    $files = [];
    foreach (["{$siteRoot}/public/wp-content", "{$siteRoot}/wp-reference/wp-content"] as $content) {
        foreach (array_filter(explode("\n", (string) shell_exec('find ' . escapeshellarg($content) . ' -type f -newer ' . escapeshellarg($marker) . " ! -name debug.log 2>/dev/null"))) as $file) {
            $files[] = 'wp-content' . substr($file, strlen($content));
        }
    }
    @unlink($marker);
    $unrestored = fx_unrestored($db, fx_revert($db, $events));
    return [
        'code' => $code,
        'stderr' => $stderr,
        'events' => $events,
        'connections' => count($connections),
        'tables' => ['made' => array_values(array_diff($tablesAfter, $tablesBefore)), 'dropped' => array_values(array_diff($tablesBefore, $tablesAfter))],
        'files' => array_values(array_unique($files)),
        'unrestored' => $unrestored,
    ];
}

/** The tables a column points into (core tables, by their unprefixed names). */
const FX_POINTS = [
    'posts' => ['post_parent' => 'posts', 'post_author' => 'users'],
    'postmeta' => ['post_id' => 'posts'],
    'comments' => ['comment_post_ID' => 'posts', 'comment_parent' => 'comments', 'user_id' => 'users'],
    'commentmeta' => ['comment_id' => 'comments'],
    'term_taxonomy' => ['term_id' => 'terms', 'parent' => 'terms'],
    'term_relationships' => ['object_id' => 'posts', 'term_taxonomy_id' => 'term_taxonomy'],
    'termmeta' => ['term_id' => 'terms'],
    'usermeta' => ['user_id' => 'users'],
    'links' => ['link_owner' => 'users'],
];
/** A row's natural identity, where its key is a surrogate. */
const FX_IDENTITY = [
    'options' => ['option_name'],
    'postmeta' => ['post_id', 'meta_key'],
    'usermeta' => ['user_id', 'meta_key'],
    'termmeta' => ['term_id', 'meta_key'],
    'commentmeta' => ['comment_id', 'meta_key'],
];
const FX_SURROGATE = ['meta_id', 'umeta_id', 'option_id'];

/** A value with what differs from run to run masked: dates, timestamps, hashes, made rows' ids. */
function fx_mask(mixed $value, array $made): mixed
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $masked = is_string($key) || is_int($key) ? fx_mask_scalar($key, $made) : $key;
            $name = is_scalar($masked) ? (string) $masked : (string) $key;
            $base = $name;
            for ($n = 2; array_key_exists($name, $out); $n++) {
                $name = "{$base}#{$n}";
            }
            $out[$name] = fx_mask($item, $made);
        }
        return $out;
    }
    if (is_string($value) && $value !== '' && preg_match('/^(?:a|O|s|i|b|d):|^N;$/', $value) === 1) {
        $decoded = @unserialize($value, ['allowed_classes' => false]);
        if ($decoded !== false || $value === 'b:0;') {
            return ['php' => fx_mask(json_decode((string) json_encode($decoded, JSON_PARTIAL_OUTPUT_ON_ERROR), true), $made)];
        }
    }
    if (is_string($value) && ($value[0] ?? '') !== '' && in_array($value[0], ['{', '['], true)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return ['json' => fx_mask($decoded, $made)];
        }
    }
    return fx_mask_scalar($value, $made);
}

function fx_mask_scalar(mixed $value, array $made): mixed
{
    // A whole value that is the id of a row this run made (a term id in category_children, a menu item's object).
    if ((is_int($value) || is_string($value) && ctype_digit($value)) && isset($made['*'][(int) $value])) {
        return $made['*'][(int) $value];
    }
    $now = time();
    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && preg_match('/^\d{10}(?:\.\d+)?$/', $value) === 1)) {
        return (float) $value > $now - 20 * 31536000 && (float) $value < $now + 20 * 31536000 && (float) $value >= 1e9 ? '<time>' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $value) === 1 && !str_starts_with($value, '0000-00-00')) {
        return '<date>';
    }
    $value = (string) preg_replace(['/\$(?:wp)?\$?(?:2y|P|H)\$[.\/A-Za-z0-9$]{20,}/', '/\$generic\$[A-Za-z0-9_\-]{20,}/', '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '/\b[0-9a-f]{32,}\b/i', '/\b1[5-9]\d{8}(?:\.\d+)?\b/'], ['<passhash>', '<passhash>', '<uuid>', '<hash>', '<time>'], $value);
    // A made row's id inside a text (a link naming a post, a revision's slug).
    return (string) preg_replace_callback('/(?<![\d.])\d{3,}(?![\d.])/', static fn (array $m): string => $made['*'][(int) $m[0]] ?? $m[0], $value);
}

/**
 * Each row's history in a run: identity => the states it went through,
 * masked, consecutive repeats dropped; rows that end as they began are
 * left out.
 *
 * @return array<string, list<mixed>>
 */
function fx_histories(mysqli $db, array $events, string $prefix): array
{
    // The rows this run made, named by table and order.
    $made = [];
    foreach ($events as $event) {
        $auto = fx_shape($db, $event['tbl'])['auto'];
        if ($event['op'] === 'i' && $auto !== null) {
            $short = str_starts_with($event['tbl'], $prefix) ? substr($event['tbl'], strlen($prefix)) : $event['tbl'];
            $made[$short][(int) $event['new'][$auto]] ??= $short . '#' . (count($made[$short] ?? []) + 1);
        }
    }
    // Made ids from 100 up stand for their rows anywhere in a value; where two tables made the same id, the first of these.
    $all = [];
    foreach (['posts', 'terms', 'comments', 'users', 'term_taxonomy', 'links', ...array_keys($made)] as $table) {
        foreach ($made[$table] ?? [] as $id => $token) {
            $all[$id] ??= $token;
        }
    }
    $made['*'] = array_filter($all, static fn (int $id): bool => $id >= 100, ARRAY_FILTER_USE_KEY);
    $histories = [];
    foreach ($events as $event) {
        $table = $event['tbl'];
        $short = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
        $shape = fx_shape($db, $table);
        $shapeRow = static function (?array $row) use ($short, $shape, $made): ?array {
            if ($row === null) {
                return null;
            }
            $out = [];
            foreach ($row as $column => $value) {
                if (in_array($column, FX_SURROGATE, true)) {
                    continue;
                }
                $target = $column === $shape['auto'] ? $short : (FX_POINTS[$short][$column] ?? null);
                $out[$column] = $target !== null && is_numeric($value) && isset($made[$target][(int) $value]) ? $made[$target][(int) $value] : fx_mask($value, $made);
            }
            return $out;
        };
        $old = fx_without_admin_rule($shapeRow($event['old']));
        $new = fx_without_admin_rule($shapeRow($event['new']));
        $row = $new ?? $old;
        $keyColumns = FX_IDENTITY[$short] ?? ($shape['key'] !== [] ? $shape['key'] : array_keys($row));
        $identity = $short . ':' . implode('|', array_map(static fn (string $column): string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : (string) json_encode($row[$column] ?? null), $keyColumns));
        $history = $histories[$identity] ?? [$old];
        if (json_encode(end($history)) !== json_encode($new)) {
            $history[] = $new;
        }
        $histories[$identity] = $history;
    }
    return array_filter($histories, static fn (array $history): bool => count($history) > 1 && json_encode($history[0]) !== json_encode(end($history)) || count($history) > 2);
}

/**
 * How two histories of a row differ: "net" when it ends otherwise than it
 * began on one stack and not the same way on the other; "states" when it
 * is put in a state on one stack it never takes on the other (a row made
 * and deleted again included); null when they differ only in order or in
 * how often a state recurs.
 */
function fx_level(?array $ref, ?array $eng): ?string
{
    $net = static fn (?array $history): string => $history === null || json_encode($history[0]) === json_encode(end($history)) ? 'unchanged' : (string) json_encode([$history[0], end($history)]);
    if ($net($ref) !== $net($eng)) {
        return 'net';
    }
    $states = static function (?array $history): array {
        $out = array_values(array_unique(array_map(static fn ($state): string => (string) json_encode($state), array_slice($history ?? [], 1))));
        sort($out);
        return $out;
    };
    return $states($ref) === $states($eng) ? null : 'states';
}

/**
 * Rewrite rules without Minn Admin's own: the engine has it built in, and
 * the reference only from the plugin, which this pass does not load.
 */
function fx_without_admin_rule(?array $row): ?array
{
    if (($row['option_name'] ?? null) === 'rewrite_rules' && is_array($row['option_value']['php'] ?? null)) {
        unset($row['option_value']['php']['^minn-admin(/.*)?$']);
    }
    return $row;
}

/** A row's name with made rows' order left out (posts#3 is posts#n), as the snapshot names it. */
function fx_named(string $identity): string
{
    // A cache named by a hash of what it holds (an oEmbed response) is one row, whatever was hashed.
    return (string) preg_replace(['/\b([a-z_]+)#\d+\b/', '/(?<![0-9a-f])[0-9a-f]{32,}(?![0-9a-f])/i'], ['$1#n', '<hash>'], $identity);
}

/** Where two values differ, as "path: a -> b" lines (at most $limit). */
function fx_diff(mixed $a, mixed $b, string $path = '', int $limit = 8): array
{
    if (json_encode($a) === json_encode($b)) {
        return [];
    }
    if (is_array($a) && is_array($b)) {
        $out = [];
        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $key) {
            foreach (fx_diff($a[$key] ?? null, $b[$key] ?? null, $path === '' ? (string) $key : "{$path}.{$key}", $limit) as $line) {
                $out[] = $line;
                if (count($out) >= $limit) {
                    return [...$out, '...'];
                }
            }
        }
        return $out;
    }
    $short = static fn ($value): string => substr((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 160);
    return ["{$path}: " . $short($a) . ' -> ' . $short($b)];
}

/**
 * Two histories for reading: a row only one side touched, in full; else
 * where each side's last state differs, and the states only one side
 * takes, by the columns that vary.
 */
function fx_describe(?array $ref, ?array $eng): string
{
    $compact = static fn ($value): string => substr((string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500);
    $lastRow = static function (?array $history) {
        foreach (array_reverse($history ?? []) as $state) {
            if ($state !== null) {
                return $state;
            }
        }
        return null;
    };
    if ($ref === null || $eng === null) {
        [$side, $history] = $ref === null ? ['engine', $eng] : ['reference', $ref];
        $states = implode(' -> ', array_map(static fn ($state): string => $state === null ? 'none' : 'row', $history));
        return "      only the {$side} touches it ({$states}): " . $compact($lastRow($history)) . "\n";
    }
    if (!is_array($lastRow($ref)) || !is_array($lastRow($eng))) {
        return '      reference: ' . $compact($ref) . "\n      engine:    " . $compact($eng) . "\n";
    }
    $out = '      last states differ: ' . (fx_diff($lastRow($ref), $lastRow($eng)) === [] ? 'no (the states between differ)' : '') . "\n";
    foreach (fx_diff($lastRow($ref), $lastRow($eng)) as $line) {
        $out .= "        {$line}\n";
    }
    $out .= '      states: ' . implode(' -> ', array_map(static fn ($state): string => $state === null ? 'none' : 'row', $ref)) . ' on the reference, ' . implode(' -> ', array_map(static fn ($state): string => $state === null ? 'none' : 'row', $eng)) . " on the engine\n";
    foreach (['reference' => [$ref, $eng], 'engine' => [$eng, $ref]] as $side => [$mine, $theirs]) {
        $theirStates = array_map(static fn ($state): string => (string) json_encode($state), $theirs);
        foreach (array_slice($mine, 1) as $state) {
            if (is_array($state) && !in_array(json_encode($state), $theirStates, true)) {
                $nearest = $lastRow($theirs);
                $out .= "      a state only the {$side} takes, against the other's last:\n";
                foreach (fx_diff($nearest, $state) as $line) {
                    $out .= "        {$line}\n";
                }
                break;
            }
        }
    }
    return $out;
}

// The probes the api suite compares, in its order.
preg_match_all("/'([\\w-]+)' => '([\\w-]+-probe\\.php)'/", (string) file_get_contents("{$root}/tests/api.test.php"), $m, PREG_SET_ORDER);
$probes = array_values(array_unique(array_map(static fn (array $match): string => $match[2], $m)));
if (isset($options['only'])) {
    $only = array_map(static fn (string $name): string => str_ends_with($name, '-probe.php') ? $name : "{$name}-probe.php", explode(',', (string) $options['only']));
    $probes = array_values(array_intersect($probes, $only)) ?: $only;
}

$accepted = is_file($acceptedFile) ? (array) (json_decode((string) file_get_contents($acceptedFile), true)['rules'] ?? []) : [];

/**
 * One probe on both stacks: the rows whose histories differ (accepted ones
 * set aside), with what the runs noted.
 */
$compare = static function (string $probe, array $baseline) use ($db, $root, $siteRoot, $prefix, $accepted): array {
    $runs = [];
    $histories = [];
    foreach (['reference', 'engine'] as $stack) {
        $runs[$stack] = fx_run($db, $stack, $probe, $root, $siteRoot);
        // What the stack writes only by starting is not the probe's.
        $histories[$stack] = array_filter(fx_histories($db, $runs[$stack]['events'], $prefix), static fn (array $history, string $identity): bool => json_encode($baseline[$stack][$identity] ?? null) !== json_encode($history), ARRAY_FILTER_USE_BOTH);
    }
    $differences = [];
    foreach (array_unique([...array_keys($histories['reference']), ...array_keys($histories['engine'])]) as $identity) {
        $ref = $histories['reference'][$identity] ?? null;
        $eng = $histories['engine'][$identity] ?? null;
        $level = fx_level($ref, $eng);
        if ($level !== null) {
            $differences[$identity] = ['level' => $level, 'reference' => $ref, 'engine' => $eng];
        }
    }
    foreach (['tables', 'files'] as $kind) {
        if (json_encode($runs['reference'][$kind]) !== json_encode($runs['engine'][$kind])) {
            $differences["({$kind})"] = ['level' => 'net', 'reference' => $runs['reference'][$kind], 'engine' => $runs['engine'][$kind]];
        }
    }
    // Differences settled elsewhere, or of the tool's own making, are set aside.
    $set = 0;
    foreach (array_keys($differences) as $identity) {
        foreach ($accepted as $rule) {
            if (preg_match('/' . str_replace('/', '\\/', (string) $rule['match']) . '/', fx_named($identity)) === 1) {
                unset($differences[$identity]);
                $set++;
                break;
            }
        }
    }
    $notes = [];
    foreach ($runs as $stack => $run) {
        $notes[] = $run['code'] !== 0 ? "{$stack} exited {$run['code']}" : null;
        $notes[] = $run['unrestored'] !== [] ? "{$stack} left " . implode(', ', $run['unrestored']) . ' changed after the put-back' : null;
    }
    return ['rows' => ['reference' => count($histories['reference']), 'engine' => count($histories['engine'])], 'differences' => $differences, 'accepted' => $set, 'notes' => array_values(array_filter($notes)), 'stderr' => ['reference' => $runs['reference']['code'] !== 0 ? $runs['reference']['stderr'] : '', 'engine' => $runs['engine']['code'] !== 0 ? $runs['engine']['stderr'] : '']];
};

if (isset($options['from'])) {
    // A saved report (--json) read again: the snapshot checked or written without running anything.
    $report = (array) json_decode((string) file_get_contents((string) $options['from']), true);
} else {
    fx_install($db);
    $cleanup = static function () use ($db): void {
        fx_uninstall($db);
    };
    register_shutdown_function($cleanup);
    if (function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, static function () use ($cleanup): void {
                $cleanup();
                exit(130);
            });
        }
    }
    $baseline = [];
    foreach (['reference', 'engine'] as $stack) {
        $run = fx_run($db, $stack, 'side-effects-baseline-probe.php', $root, $siteRoot);
        $baseline[$stack] = fx_histories($db, $run['events'], $prefix);
    }
    $report = ['baseline' => $baseline, 'probes' => []];
    foreach ($probes as $probe) {
        $result = $compare($probe, $baseline);
        $report['probes'][$probe] = $result;
        $differences = $result['differences'];
        $nets = count(array_filter($differences, static fn (array $difference): bool => $difference['level'] === 'net'));
        printf("%-48s %4d / %-4d rows  %s%s\n", $probe, $result['rows']['reference'], $result['rows']['engine'], $differences === [] ? 'same' : "{$nets} end otherwise, " . (count($differences) - $nets) . ' pass otherwise', $result['notes'] === [] ? '' : '  (' . implode('; ', $result['notes']) . ')');
        if (isset($options['show'])) {
            foreach ($differences as $identity => ['level' => $level, 'reference' => $ref, 'engine' => $eng]) {
                echo "    {$identity} ({$level})\n" . fx_describe($ref, $eng);
            }
        }
    }
    $differing = count(array_filter($report['probes'], static fn (array $result): bool => $result['differences'] !== []));
    $set = array_sum(array_map(static fn (array $result): int => $result['accepted'], $report['probes']));
    echo "\n" . count($report['probes']) . " probes, {$differing} with side effects that differ ({$set} accepted differences set aside)\n";
    if (isset($options['json'])) {
        file_put_contents((string) $options['json'], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }
}

// Each probe's differences by row name (made rows as table#n), the worse level kept.
$found = [];
foreach ($report['probes'] as $probe => ['differences' => $differences]) {
    $found[$probe] = [];
    foreach ($differences as $identity => ['level' => $level]) {
        $name = fx_named($identity);
        $found[$probe][$name] = ($found[$probe][$name] ?? null) === 'net' ? 'net' : $level;
    }
    ksort($found[$probe]);
}
$known = is_file($snapshotFile) ? (array) json_decode((string) file_get_contents($snapshotFile), true) : [];
$covered = array_flip((array) ($known['probes'] ?? []));
$grown = [];
$agreesNow = 0;
foreach ($found as $probe => $names) {
    if (!isset($covered[$probe])) {
        continue;
    }
    $was = (array) ($known['differences'][$probe] ?? []);
    foreach ($names as $name => $level) {
        if (!isset($was[$name]) || $was[$name] === 'states' && $level === 'net') {
            $grown[] = "{$probe}: {$name} ({$level})";
        }
    }
    $agreesNow += count(array_diff_key($was, $names));
}
// A probe that differs anew runs once more; only what differs both times counts (a cron event that falls due, a cache that expires).
if ($grown !== [] && !isset($options['from'])) {
    $again = array_values(array_unique(array_map(static fn (string $line): string => explode(':', $line)[0], $grown)));
    foreach ($again as $probe) {
        $second = $compare($probe, $report['baseline']);
        $names = [];
        foreach ($second['differences'] as $identity => ['level' => $level]) {
            $names[fx_named($identity)] = $level;
        }
        $found[$probe] = array_intersect_key($found[$probe], $names);
    }
    $grown = array_values(array_filter($grown, static function (string $line) use ($found): bool {
        [$probe, $rest] = explode(': ', $line, 2);
        return isset($found[$probe][(string) preg_replace('/ \((?:net|states)\)$/', '', $rest)]);
    }));
}
if (isset($options['snapshot'])) {
    if ($grown !== []) {
        echo "refused: the snapshot would grow:\n  " . implode("\n  ", array_slice($grown, 0, 40)) . "\n";
        exit(1);
    }
    $differencesOut = array_merge((array) ($known['differences'] ?? []), $found);
    $differencesOut = array_filter($differencesOut, static fn (array $names): bool => $names !== []);
    ksort($differencesOut);
    $probesOut = array_values(array_unique([...array_keys($covered), ...array_keys($found)]));
    sort($probesOut);
    if (!is_dir(dirname($snapshotFile))) {
        mkdir(dirname($snapshotFile), 0755, true);
    }
    file_put_contents($snapshotFile, json_encode(['method' => 'tests/tools/side-effects.php: per api probe, the rows (made ones as table#n) whose writes differ between the reference and the engine; "net" where the row ends otherwise, "states" where it passes through a state the other stack never puts it in. Only shrinks.', 'probes' => $probesOut, 'differences' => $differencesOut], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'wrote the snapshot: ' . array_sum(array_map('count', $differencesOut)) . ' differences in ' . count($differencesOut) . " probes\n";
    exit(0);
}
foreach ($grown as $line) {
    echo "  FAIL {$line}\n";
}
if ($agreesNow > 0) {
    echo "  --   {$agreesNow} recorded differences agree now: --snapshot drops them\n";
}
$failedProbes = count(array_unique(array_map(static fn (string $line): string => explode(':', $line)[0], $grown)));
echo "\n" . (count($found) - $failedProbes) . " passed, {$failedProbes} failed\n";
exit($grown === [] ? 0 : 1);
