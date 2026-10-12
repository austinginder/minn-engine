#!/usr/bin/env bash
# `wp` for a measured run (tests/tools/line-coverage.php --parts=suites): put
# first on the PATH, it runs WP-CLI under phpdbg through
# tests/tools/coverage-run.php when the command is for the test site's
# webroot (the engine answers there), so the engine lines it runs are noted,
# and hands any other command to the real wp. WP-CLI refuses a SAPI other
# than the command line's in its own boot files, so the tool boots an
# unpacked copy past that check (MINN_COVERAGE_WPCLI).
target="$PWD"
for arg in "$@"; do
	case "$arg" in --path=*) target="${arg#--path=}" ;; esac
done
real="$( cd "$target" 2>/dev/null && pwd -P )"
case "$real/" in
	"$MINN_COVERAGE_SITE_PUBLIC"/*)
		exec "$MINN_COVERAGE_PHPDBG" -qrr -d memory_limit=2G "$MINN_COVERAGE_RUNNER" "$MINN_COVERAGE_WPCLI" "$@"
		;;
esac
exec "$MINN_COVERAGE_REAL_WP" "$@"
