#!/usr/bin/env bash
# Run every engine suite.
#
# The suites drive the TEST site (minn.localhost by default, MINN_TEST_ROOT),
# never the marketing site. minn-engine.localhost holds this repository and
# serves the Minn site theme; only tests/site.test.php reads it, against its
# own parked WordPress, at its Cove twin. Nothing here writes to it, so a
# run that dies half way can no longer strand the marketing page on another
# theme.
#
# References: each site's parked WordPress (wp-reference/) is served by Cove
# as the site's twin. wp.<site>.localhost browses as a site of its own;
# ref.<site>.localhost answers as the site itself, over HTTPS, and that is
# what the suites diff against (set up once per site with
# `cove twin <site> add --as-site=ref.<site>.localhost`): the test site's,
# the dogfood site's, the WooCommerce lab's, the marketing site's, and the
# round-trip site's. Cove serves them, so nothing is started here; a suite
# whose twin is missing skips and says so.
set -u
cd "$( dirname "$0" )"

TEST_ROOT="${MINN_TEST_ROOT:-~/Cove/Sites/minn.localhost}"
SITE_ROOT="$( cd .. && pwd )"

# The reference's file layout as placeholders, so plugins that require wp-admin/includes files load.
php tools/site-skeleton.php "$TEST_ROOT" >/dev/null
php tools/site-skeleton.php "$SITE_ROOT" >/dev/null
[ -d ~/Cove/Sites/dogfood.localhost/public ] && php tools/site-skeleton.php ~/Cove/Sites/dogfood.localhost >/dev/null
WOO_ROOT="${MINN_WOO_ROOT:-~/Cove/Sites/minnwoo.localhost}"
[ -d "$WOO_ROOT/public" ] && php tools/site-skeleton.php "$WOO_ROOT" >/dev/null

# Each reference is the site's Cove twin; say which are missing up front.
check_twin() {
	curl -sk -o /dev/null --max-time 30 "https://ref.$1.localhost/" \
		|| echo "no twin answers for $1.localhost (cove twin $1 add --as-site=ref.$1.localhost); its suites will skip"
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
cleanup() { restore_locale; }
# EXIT alone does not fire when the run is signalled (a killed background
# job, a Ctrl-C, a harness timeout), so catch the signals too and exit through
# the same path, or a run that dies mid-way leaves a locale pinned.
trap cleanup EXIT
trap 'cleanup; trap - EXIT; exit 130' INT
trap 'cleanup; trap - EXIT; exit 143' TERM HUP
pin_locale
for twin_site in minn dogfood minn-engine; do check_twin "$twin_site"; done
# The WooCommerce lab and the round trip's site are optional; their suites skip without them.
[ -f "$WOO_ROOT/private/woo-baseline.sql" ] && check_twin minnwoo
ROUNDTRIP_ROOT="${MINN_ROUNDTRIP_ROOT:-~/Cove/Sites/cove-minn.localhost}"
[ -f "$ROUNDTRIP_ROOT/private/round-trip.json" ] && check_twin cove-minn

failed=0
for suite in unit http style hooks api runtime ajax hook-trace front-lifecycle rest-gate abilities rest-posts auth identity application-passwords caps writes login-endpoint login-hooks rest-parity allow embed minn-v1 comments media settings users terms write-fields editor templates navigation permalinks blocks theme classic styles probes dogfood cli layout hardening security install cron-mail cron reader extensions front-method recovery front-page menus declared-types global-styles reusable-blocks admin-surfaces updates site code-size l10n dropins feeds requests mail html-api comment-form feed-hooks sitemap-hooks embed-template attachment-pages canonical-hooks request-vars plugin-rules rewrite-endpoints rest-envelope round-trip woo; do
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
