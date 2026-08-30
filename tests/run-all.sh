#!/usr/bin/env bash
# Run every engine suite. The parity suites need the reference WordPress on
# 127.0.0.1:8123 (wp-reference/) and the dogfood suites need the dogfood
# site's own reference on 127.0.0.1:8124; both are started below when they
# are not already running. A suite whose reference is unreachable skips
# and says so, which is why the servers are started here.
set -u
cd "$( dirname "$0" )"

# The reference's file layout as placeholders, so plugins that require wp-admin/includes files load.
php tools/site-skeleton.php .. >/dev/null
[ -d ~/Cove/Sites/dogfood.localhost/public ] && php tools/site-skeleton.php ~/Cove/Sites/dogfood.localhost >/dev/null

# Start a reference server when its port is quiet, so no suite skips on the
# dev box. Servers started here are stopped on exit; ones already running are
# left alone. Set MINN_NO_AUTOSTART=1 to run against whatever is up.
DOGFOOD_REF="${MINN_DOGFOOD_REF_DIR:-$HOME/Cove/Sites/dogfood.localhost/wp-reference}"
started=()
start_reference() {
	local dir="$1" port="$2"
	[ -n "${MINN_NO_AUTOSTART:-}" ] && return
	curl -s -o /dev/null "http://127.0.0.1:$port/" && return
	[ -f "$dir/router.php" ] || { echo "reference for :$port not found at $dir; its suites will skip"; return; }
	( cd "$dir" && php -S "127.0.0.1:$port" router.php >"/tmp/minn-ref-$port.log" 2>&1 ) &
	started+=("$!")
	until curl -s -o /dev/null "http://127.0.0.1:$port/"; do sleep 0.5; done
	echo "started reference on :$port from $dir"
}
stop_references() {
	for pid in "${started[@]:-}"; do [ -n "$pid" ] && pkill -P "$pid" 2>/dev/null; kill "$pid" 2>/dev/null; done
}
# The dev site runs the Minn site theme; the fixtures were captured under
# twentytwentyfive. Pin it once for the whole run (the suites' own pin in
# tests/lib.php is skipped through MINN_TEST_KEEP_THEME) and restore on exit.
WP=/opt/homebrew/bin/wp
saved_template="$( cd ../public && $WP option get template 2>/dev/null )"
saved_stylesheet="$( cd ../public && $WP option get stylesheet 2>/dev/null )"
pin_theme() {
	( cd ../public && $WP option update template twentytwentyfive >/dev/null 2>&1 && $WP option update stylesheet twentytwentyfive >/dev/null 2>&1 )
	export MINN_TEST_KEEP_THEME=1
}
restore_theme() {
	[ -n "$saved_template" ] && ( cd ../public && $WP option update template "$saved_template" >/dev/null 2>&1 && $WP option update stylesheet "$saved_stylesheet" >/dev/null 2>&1 )
}
cleanup() { stop_references; restore_theme; }
trap cleanup EXIT
pin_theme
start_reference "$PWD/../wp-reference" 8123
start_reference "$DOGFOOD_REF" 8124

failed=0
for suite in style hooks api runtime rest-posts auth application-passwords caps writes login-endpoint rest-parity embed minn-v1 comments media settings users terms write-fields editor permalinks blocks theme styles probes dogfood cli layout hardening security install cron-mail reader extensions front-page menus declared-types admin-surfaces updates site lexicon code-size; do
	printf '\n=== %s ===\n' "$suite"
	php "$suite.test.php" || failed=1
done

# Browser test (Minn Admin boot). Needs the app symlink + system Chrome.
if [ -d browser/node_modules ]; then
	printf "\n=== browser: minn-admin boot ===\n"
	MINN_ADMIN_PASS="${MINN_ADMIN_PASS:-password}" node browser/boot.test.js || failed=1
	printf "\n=== browser: geometry (dev site) ===\n"
	node browser/geometry.test.js --dev || failed=1
	printf "\n=== browser: geometry (dogfood) ===\n"
	node browser/geometry.test.js || failed=1
	printf "\n=== browser: admin views (dogfood) ===\n"
	node browser/admin-views.test.js || failed=1
	printf "\n=== browser: interactivity (dogfood) ===\n"
	node browser/interactivity.test.js || failed=1
fi

printf '\n'
if [ "$failed" -eq 0 ]; then
	echo "All suites passed."
else
	echo "One or more suites FAILED."
fi
exit "$failed"
