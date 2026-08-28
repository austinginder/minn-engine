#!/usr/bin/env bash
# Run every engine suite. Suites needing the reference SKIP cleanly when it
# is not running; start it with:  (cd wp-reference && php -S 127.0.0.1:8123 router.php)
set -u
cd "$( dirname "$0" )"

failed=0
for suite in style rest-posts auth caps writes login-endpoint rest-parity minn-v1 comments media settings users terms write-fields editor permalinks blocks theme styles; do
	printf '\n=== %s ===\n' "$suite"
	php "$suite.test.php" || failed=1
done

# Browser test (Minn Admin boot). Needs the app symlink + system Chrome.
if [ -d browser/node_modules ]; then
	printf "\n=== browser: minn-admin boot ===\n"
	MINN_ADMIN_PASS="${MINN_ADMIN_PASS:-password}" node browser/boot.test.js || failed=1
fi

printf '\n'
if [ "$failed" -eq 0 ]; then
	echo "All suites passed."
else
	echo "One or more suites FAILED."
fi
exit "$failed"
