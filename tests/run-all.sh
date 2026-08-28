#!/usr/bin/env bash
# Run every engine suite. Suites needing the reference SKIP cleanly when it
# is not running; start it with:  (cd wp-reference && php -S 127.0.0.1:8123)
set -u
cd "$( dirname "$0" )"

failed=0
for suite in rest-posts auth caps writes login-endpoint rest-parity; do
	printf '\n=== %s ===\n' "$suite"
	php "$suite.test.php" || failed=1
done

printf '\n'
if [ "$failed" -eq 0 ]; then
	echo "All suites passed."
else
	echo "One or more suites FAILED."
fi
exit "$failed"
