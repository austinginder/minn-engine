#!/usr/bin/env bash
# Run every engine suite.
#
# The suites drive the TEST site (minn.localhost by default, MINN_TEST_ROOT),
# never the marketing site. minn-engine.localhost holds this repository and
# serves the Minn site theme; only tests/site.test.php reads it, against its
# own parked WordPress on 127.0.0.1:8128. Nothing here writes to it, so a run
# that dies half way can no longer strand the marketing page on another theme.
#
# References: 8123 the test site's, 8124 the dogfood site's, 8128 the
# marketing site's. Each is started below when its port is quiet. A suite
# whose reference is unreachable skips and says so.
set -u
cd "$( dirname "$0" )"

TEST_ROOT="${MINN_TEST_ROOT:-~/Cove/Sites/minn.localhost}"
SITE_ROOT="$( cd .. && pwd )"

# The reference's file layout as placeholders, so plugins that require wp-admin/includes files load.
php tools/site-skeleton.php "$TEST_ROOT" >/dev/null
php tools/site-skeleton.php "$SITE_ROOT" >/dev/null
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
# The test site runs twentytwentyfive, the theme the fixtures were captured
# under, and is left on it. The suites' own pin (tests/lib.php) sees the theme
# already in place and does nothing.
WP=/opt/homebrew/bin/wp
export MINN_TEST_KEEP_THEME=1
# A language switched on for testing (WPLANG or a user's locale meta) would
# change what the reference renders; pin en_US for the run, restore on exit.
prefix="$( cd "$TEST_ROOT/public" && $WP config get table_prefix 2>/dev/null )"; prefix="${prefix:-wp_}"
saved_wplang="$( cd "$TEST_ROOT/public" && $WP option get WPLANG 2>/dev/null )"
saved_locales="$( cd "$TEST_ROOT/public" && $WP db query "SELECT user_id, meta_value FROM ${prefix}usermeta WHERE meta_key = 'locale' AND meta_value <> ''" --skip-column-names 2>/dev/null )"
pin_locale() {
	( cd "$TEST_ROOT/public" && $WP db query "UPDATE ${prefix}usermeta SET meta_value = '' WHERE meta_key = 'locale'" >/dev/null 2>&1; [ -n "$saved_wplang" ] && $WP option update WPLANG "" >/dev/null 2>&1 )
	export MINN_TEST_KEEP_LOCALE=1
}
restore_locale() {
	[ -n "$saved_wplang" ] && ( cd "$TEST_ROOT/public" && $WP option update WPLANG "$saved_wplang" >/dev/null 2>&1 )
	printf '%s\n' "$saved_locales" | while IFS=$'\t' read -r id locale; do
		[ -n "$id" ] && ( cd "$TEST_ROOT/public" && $WP db query "UPDATE ${prefix}usermeta SET meta_value = '$locale' WHERE meta_key = 'locale' AND user_id = $id" >/dev/null 2>&1 )
	done
	return 0
}
cleanup() { stop_references; restore_locale; }
# EXIT alone does not fire when the run is signalled (a killed background
# job, a Ctrl-C, a harness timeout), so catch the signals too and exit through
# the same path, or a run that dies mid-way leaves a locale pinned.
trap cleanup EXIT
trap 'cleanup; trap - EXIT; exit 130' INT
trap 'cleanup; trap - EXIT; exit 143' TERM HUP
pin_locale
start_reference "$TEST_ROOT/wp-reference" 8123
start_reference "$DOGFOOD_REF" 8124
start_reference "$SITE_ROOT/wp-reference" 8128

failed=0
for suite in unit style hooks api runtime rest-gate abilities rest-posts auth application-passwords caps writes login-endpoint rest-parity allow embed minn-v1 comments media settings users terms write-fields editor templates navigation permalinks blocks theme classic styles probes dogfood cli layout hardening security install cron-mail cron reader extensions front-method recovery front-page menus declared-types global-styles reusable-blocks admin-surfaces updates site code-size l10n dropins feeds requests mail html-api; do
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
