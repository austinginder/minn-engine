<?php
/**
 * Runs a PHP script under phpdbg for tests/tools/line-coverage.php, noting
 * the engine lines (public/minn/src/Minn) it runs: the oplog starts, the
 * script runs as the command line would run it (its arguments shifted into
 * place), and as the process ends the lines go to a file of their own in
 * MINN_COVERAGE_DIR. (phpdbg -qrr does not honour auto_prepend_file, so
 * this wraps the script instead.)
 *
 * MINN_COVERAGE_DIR=<dir> phpdbg -qrr tests/tools/coverage-run.php <script> [args...]
 */

$script = (string) ($argv[1] ?? '');
if ($script === '' || !is_file($script)) {
    fwrite(STDERR, "no script to run\n");
    exit(1);
}
$argv = array_slice($argv, 1);
$argc = count($argv);
$_SERVER['argv'] = $argv;
$_SERVER['argc'] = $argc;
$_SERVER['SCRIPT_FILENAME'] = $script;
// The lines are written from a global object's destructor: under phpdbg -qrr a script's exit() skips the
// shutdown functions, but global objects are still destroyed as the process ends.
final class MinnCoverageDump
{
    public function __destruct()
    {
        $src = realpath(dirname(__DIR__, 2) . '/public/minn/src/Minn') . '/';
        $lines = [];
        foreach ((array) phpdbg_end_oplog() as $file => $ran) {
            $real = realpath((string) $file);
            if ($real !== false && str_starts_with($real, $src)) {
                $lines[substr($real, strlen($src))] = array_map('intval', array_keys((array) $ran));
            }
        }
        file_put_contents(getenv('MINN_COVERAGE_DIR') . '/' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.json', json_encode($lines));
    }
}
if (function_exists('phpdbg_start_oplog') && getenv('MINN_COVERAGE_DIR')) {
    phpdbg_start_oplog();
    $GLOBALS['minnCoverageDump'] = new MinnCoverageDump();
}
require $script;
