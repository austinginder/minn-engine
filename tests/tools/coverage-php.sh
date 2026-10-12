#!/usr/bin/env bash
# `php` for a measured run (tests/tools/line-coverage.php --parts=suites):
# put first on the PATH, it runs the engine's own command (public/minn/bin/minn)
# and the api probe runner (which suites like html-api call) under phpdbg
# through tests/tools/coverage-run.php, so the engine lines they run are
# noted, and hands anything else to the real php untouched. Probes sweep
# their leftovers as they start, so phpdbg skipping shutdown work on exit()
# costs nothing the next one does not put right.
case "${1:-}" in
	*/minn/bin/minn|*/tests/tools/run-api-probe.php)
		exec "$MINN_COVERAGE_PHPDBG" -qrr -d memory_limit=2G "$MINN_COVERAGE_RUNNER" "$@"
		;;
esac
exec "$MINN_COVERAGE_REAL_PHP" "$@"
