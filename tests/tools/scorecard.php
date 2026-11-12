<?php
/**
 * The 0.1.0 maturity scorecard (measures set by the audit of 2026-10-12,
 * re-taken in each docs/audit-<date>.md since): each measure the engine
 * matures against, its figure and its target, gathered from what the repo
 * holds (no site is asked). Writes contracts/scorecard.json, which
 * compat-status.php --site carries into the marketing site's compat.json
 * beside the API figures.
 *
 * - security: the audit's findings, each counted fixed when the check that
 *   pins it is in the repo;
 * - sql: SELECTs per benchmark page against the reference
 *   (contracts/query-count.json, tests/tools/query-count.php --write);
 * - hooks: accounted for, and proven weighted by plugin installs
 *   (contracts/api/hook-status.json, tests/tools/hook-status.php);
 * - wordpressNames: the engine's calls into WordPress functions and into
 *   the hook API, and its references to WordPress classes, all owed Minn
 *   versions before a release (contracts/api/wordpress-names.json,
 *   tests/tools/wordpress-names.php);
 * - coverage: engine line coverage (contracts/line-coverage.json,
 *   tests/tools/line-coverage.php);
 * - leanness: dead-name methods and every other ratchet the style suite
 *   holds, each with the ceiling it started at (the commit that
 *   added it) and whether it has ever risen since (every commit that
 *   changed it, read from git);
 * - open: contracts/runtime.md's open items register;
 * - run: the last full run, when a run-all log is given (--run-log): its
 *   suites, how many were green, the checks they passed, and any red,
 *   named, and the PHP warnings, notices and deprecations engine code wrote
 *   to the test site's error log while it ran (the log's birth to its last
 *   write); without one, the run already recorded stays;
 * - gate: the repository scans of tests/tools/gate-scan.php (dead ends
 *   called or defined by hand, the PHP floor, public-repo hygiene);
 * - clones: tests/tools/clone-scan.php, duplicated engine code and engine
 *   code identical to the reference's source;
 * - facadeLogic: the facade's functions and methods that hold logic
 *   rather than hand over to the engine, and their lines
 *   (contracts/api/facade-logic.json, tests/tools/facade-logic.php), with
 *   the review's progress and the doubts it raised still open (each
 *   settled by a probe: fixed to the reference, or kept with a reason);
 * - behaviour: the hunts for differences no symbol count shows. sideEffects
 *   are the api probes whose database writes differ from the reference's
 *   (contracts/fixtures/effects/divergences.json, tests/tools/side-effects.php);
 *   restFuzz is the differential REST fuzzer's last pass
 *   (contracts/rest-fuzz.json, summary: routes, requests, differences)
 *   and cliFeatures WP-CLI's own feature tests on both stacks
 *   (contracts/cli-features.json, summary: scenarios, passedReference,
 *   passedEngine, passedBoth); either is
 *   null until its tool has written its file;
 * - fixtures: whether the API fixtures still hold at the version the
 *   reference is on (contracts/fixture-currency.json, written by
 *   php tests/api.test.php --reference: wordpress, rows, differing), current
 *   when that version is the one meta.json records and no row differs.
 *
 * php tests/tools/scorecard.php [--run-log=<run-all log>]
 */

// The day a run is measured on is the machine's day, not UTC's.
if (preg_match('#zoneinfo/(.+)$#', (string) @readlink('/etc/localtime'), $zone) === 1) {
    date_default_timezone_set($zone[1]);
}

require_once dirname(__DIR__) . '/local.php';

$root = dirname(__DIR__, 2);
$runLog = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--run-log=')) {
        $runLog = substr($arg, strlen('--run-log='));
    }
}
$json = static fn (string $path): ?array => is_file("{$root}/{$path}") ? json_decode((string) file_get_contents("{$root}/{$path}"), true) : null;
$contains = static fn (string $path, string $text): bool => is_file("{$root}/{$path}") && str_contains((string) file_get_contents("{$root}/{$path}"), $text);

// Every audit's and sweep's security findings, each with the check that pins it.
$findings = [
    'File changes allowed under DISALLOW_FILE_MODS' => ['tests/tools/security-parity-probe.php', 'file_mod_allowed'],
    'Search reached non-public types' => ['tests/tools/security-parity-probe.php', 'search a non-public type, nobody'],
    'Signed-out search found password-protected posts' => ['tests/tools/security-parity-probe.php', 'search a protected post, nobody'],
    'A type\'s own REST controller bypassed' => ['tests/tools/security-parity-probe.php', 'a type with its own controller, nobody'],
    'Comment author, address and status set over REST by anyone' => ['tests/tools/security-parity-probe.php', 'a comment naming user 1'],
    'Redirect sanitizing let //host through' => ['tests/tools/security-parity-probe.php', 'wp_sanitize_redirect'],
    'Password cookies named their own work factor' => ['tests/unit/security-bounds.php', 'cookieUnlocks'],
    'Serialized data looped the decoder or revived objects' => ['tests/unit/security-bounds.php', 'Serialized::decode'],
    // The sweep of 2026-10-13 (docs/security-review-2026-10-13.md).
    'A URL scheme broken by a tab or line break reached links' => ['tests/unit/security-sweep.php', 'judged without the tabs'],
    'Navigation class names reached the markup unescaped' => ['tests/tools/sweep-rules-probe.php', 'class name with a quote in it'],
    'Two requests carrying input could pause a plugin for everyone' => ['tests/security-sweep.test.php', 'do not pause it'],
    'Published posts of types nobody may query were shown' => ['tests/security-sweep.test.php', 'is not shown'],
    'Safe requests reached reserved address ranges' => ['tests/tools/sweep-rules-probe.php', 'addresses a safe request refuses'],
    'A post\'s terms were listed when the post could not be read' => ['tests/tools/rest-terms-for-post-probe.php', "set_query_params(['post' => \$id])"],
    'Comments on a protected post were read and made without its password' => ['tests/tools/rest-protected-comments-probe.php', "'right' => 'pw'"],
    'An unregistered duotone slug reached the markup' => ['tests/tools/sweep-rules-probe.php', "duotone preset"],
    'Passwords of any length were hashed' => ['tests/unit/security-sweep.php', 'never hashed and never matches'],
    'A reset key outlived a new address or password' => ['tests/tools/reset-key-changes-probe.php', 'rest password'],
    'media_handle_upload dropped its overrides' => ['tests/security-sweep.test.php', 'refuses a type its overrides leave out'],
    'A trusted-proxy wildcard made every visitor the proxy' => ['tests/unit/trusted-proxies.php', 'with any proxy trusted (*)'],
    'Built-in types took the posts family\'s capabilities' => ['tests/tools/builtin-type-caps-probe.php', 'read_post private pattern'],
    'A navigation showed a draft or private menu' => ['tests/tools/sweep-rules-probe.php', 'a navigation naming a'],
    'The CLI deleted a file outside the plugins folder' => ['tests/security-sweep.test.php', 'deletes nothing'],
    'The sign-in throttle raced and counted IPv6 by the address' => ['tests/unit/security-sweep.php', 'counts an IPv6 address by its /64'],
    'A SQL mode could switch backslash escaping off' => ['tests/security-sweep.test.php', 'set_sql_mode keeps'],
    'A string author list reached the SQL as typed' => ['tests/tools/sweep-rules-probe.php', "foreach (['author__in', 'author__not_in'] as \$var)"],
    'Archive notes skipped the archive\'s limits' => ['tests/unit/security-sweep.php', '__MACOSX notes are checked'],
    'Cookies for argon2 hashes never changed their key' => ['tests/unit/security-sweep.php', 'the cookie fragment'],
    'An eject followed paths out of the webroot' => ['tests/unit/security-sweep.php', 'eject acts only'],
    'A bound URL attribute kept any scheme' => ['tests/unit/security-sweep.php', 'keeps only an allowed scheme'],
    // The sweep of 2026-10-18 (docs/security-review-2026-10-18.md).
    'A commenter\'s block markup was rendered as blocks' => ['tests/security-sweep.test.php', 'block markup is printed as text, not rendered'],
    'A post that showed its own content rendered it without end' => ['tests/security-sweep.test.php', 'renders it once'],
    'The curl installer installed a download nobody signed' => ['tests/install.test.php', 'refuses a release signed with no trusted key'],
    'A placeholder or shared SECRET_KEY salted plugins\' tokens' => ['tests/security-sweep.test.php', "salts a plugin's scheme, auth and nonce as on the reference"],
    'An eject took any park the install record named' => ['tests/install.test.php', 'eject refuses a park that carries no mark'],
    'A stored object\'s undeclared property ran its __set' => ['tests/unit/stored-objects.php', 'would be set through __set'],
    'A cancelled comment redirect sent the commenter\'s address' => ['tests/runtime.test.php', 'a plugin cancels gets a blank page'],
    'wpdb::_insert_replace_helper built any statement type' => ['tests/tools/sweep-1018-probe.php', '_insert_replace_helper by type'],
    'A symbol walk that never finished ran again on every request' => ['tests/runtime.test.php', 'a walk that never finished holds'],
    'A plugin and a theme of one folder name shared a symbol cache entry' => ['tests/runtime.test.php', "basename(dirname(\$gateDir)) . '/' . basename(\$gateDir)"],
    'A 404\'s address was kept as a page needing the Legacy Pack' => ['tests/ajax.test.php', 'a 404 never names its own address'],
    'Replies to spam and trashed comments were taken' => ['tests/comment-form.test.php', 'a reply to a comment marked spam'],
    'bloginfo() printed the site\'s name and description unescaped' => ['tests/tools/sweep-1018-probe.php', 'bloginfo display filters'],
    // The sweep of 2026-10-20 (docs/security-review-2026-10-20.md).
    'A crafted FLV hung the worker in its parameter set\'s cycle' => ['tests/unit/media-av.php', "'cycle.flv' => [0, false]"],
    'Queries on binary and latin1 tables were refused (Wordfence lockouts lost)' => ['tests/tools/table-charsets-probe.php', 'an address of bytes: written by a query'],
    'The latest-posts block printed protected posts whole' => ['tests/tools/sweep-1020-probe.php', 'latest posts showing'],
    'An ID3 frame of terminators was split in quadratic time' => ['tests/unit/media-av.php', "'terminators.mp3' => [null, false]"],
    'An AVI\'s stream lists were each walked to the end of the file' => ['tests/unit/media-av.php', "'lists.avi' => [null, false]"],
    'A caption attribute was printed without kses' => ['tests/tools/sweep-1020-probe.php', 'a caption attribute with markup the post allowlist refuses'],
    'An MPEG-4 file of empty boxes took memory without bound' => ['tests/unit/media-av.php', "'tracks.mov' => [0.0, false]"],
    'A declared tag length became one allocation of that size' => ['tests/unit/media-av.php', "'claims.mp3' => [null, false]"],
    'A zero rate or a worded duration threw out of the media readers' => ['tests/unit/media-av.php', "'zero.mkv' => [null, false]"],
    'An audio cover was saved with the extension its tag declared' => ['tests/tools/sweep-1020-probe.php', 'a cover that is'],
    'MPEG audio in a wave file was scanned to its end' => ['tests/tools/mpeg-in-wave-probe.php', "'51 at 8, then 16'"],
    'Comments nested deep recursed through kses once per opener' => ['tests/unit/kses.php', 'comment openers 16,000 deep'],
    'An autosave wrote a draft another user had locked' => ['tests/tools/sweep-1020-probe.php', 'locked by {$label}'],
    'admin-post.php ran a visitor\'s handler as a signed-in user' => ['tests/runtime.test.php', 'only the admin cookie, a handler'],
    'create_post_autosave() turned revisions off for the rest of the request' => ['tests/tools/sweep-1020-probe.php', 'create_post_autosave: a slot, and DOING_AUTOSAVE after it'],
    'Refused post, comment and term writes reported success' => ['tests/tools/refused-writes-probe.php', 'a post whose content is refused'],
    'Refused meta writes answered another row\'s id' => ['tests/tools/refused-writes-probe.php', 'a meta value refused: add_post_meta'],
    'nonce_user_logged_out was asked for signed-in users' => ['tests/tools/sweep-1020-probe.php', 'nonce: asked of the filter, then checked for user 1'],
    'The front end\'s cookie signed users in to the blog admin' => ['tests/tools/sweep-1020-probe.php', 'cookie in the blog admin, and outside it'],
    'settings_errors() printed codes and types unescaped' => ['tests/tools/sweep-1020-probe.php', 'settings errors with quotes in their code and type'],
    'do_settings_sections() printed a section\'s wrap raw' => ['tests/tools/sweep-1020-probe.php', 'a section\'s surrounding markup'],
    'REST notes ignored edit_post, and drafts took comments' => ['tests/tools/rest-notes-probe.php', 'notes, by who and where'],
    'list_users alone opened another user\'s edit view' => ['tests/tools/sweep-1020b-probe.php', 'another user in the edit context, with list_users only'],
    'A user deleted with itself as the reassign target' => ['tests/tools/sweep-1020b-probe.php', 'a user reassigned to itself'],
    'A pending password reset outlived a good sign-in' => ['tests/login-endpoint.test.php', 'a good sign-in ends a pending password reset'],
    'A template part kept tagName "script" through kses' => ['tests/tools/sweep-1020b-probe.php', 'a template part tag'],
    'Term data bindings read hidden taxonomies to visitors' => ['tests/tools/sweep-1020b-probe.php', 'term data for a visitor and an administrator'],
    'The read-more block printed its text unkses\'d' => ['tests/tools/sweep-1020b-probe.php', 'read-more text through the post allowlist'],
    'The recent posts widget printed links unescaped' => ['tests/tools/sweep-1020b-probe.php', 'the recent posts widget: an untitled post and a link to escape'],
    'wpdb took any prefix, and its placeholder was guessable' => ['tests/tools/sweep-1020b-probe.php', 'wpdb refuses a prefix, runs no empty query'],
    'Nav menus could be listed as a sitemap' => ['tests/tools/sweep-1020b-probe.php', 'sitemap lists for a taxonomy that is not one of its own'],
    'Active plugins outside the plugins folder were loaded' => ['tests/tools/sweep-1020b-probe.php', 'active plugins loaded'],
    'delete_old_plugin could empty the plugins folder' => ['tests/tools/sweep-1020b-probe.php', 'an old plugin deleted'],
    'An MO translation object ran a PHP file' => ['tests/tools/translation-files-probe.php', 'each class reads its own format'],
    'Sticky, revision-delete and protected-comment checks were looser' => ['tests/tools/sweep-1020b-probe.php', 'deleting a revision of a post the user may not edit'],
    'Enum strings were stored as serialized data' => ['tests/tools/sweep-1020b-probe.php', 'serialized or not'],
    // The probe writes this label with its quote escaped.
    'The RSS widget fetched the site\'s own address' => ['tests/tools/sweep-1020b-probe.php', 'the RSS widget\\\'s address'],
    'Sendmail took "+", "=" and non-ASCII senders' => ['tests/tools/sweep-1020b-probe.php', 'shell-safe sender addresses'],
    // The security review of 2026-10-22 (docs/security-review-2026-10-22.md).
    'A sign-in cookie without its nonce, beside another account\'s application password, acted as the cookie\'s user' => ['tests/application-passwords.test.php', 'a sign-in cookie without its nonce beside another account'],
    'Contributors listed other people\'s editorial notes' => ['tests/tools/sweep-1022-probe.php', 'notes listed to'],
    'REST plugin activation skipped the plugin\'s own capability' => ['tests/tools/sweep-1022-probe.php', 'a plugin whose own activation is refused, over REST'],
    'activate_plugin included a file outside the plugins folder' => ['tests/tools/sweep-1022-probe.php', 'activate_plugin {$plugin}'],
    'delete_plugins removed a folder named without a file, ".." included' => ['tests/tools/sweep-1022-probe.php', 'delete_plugins a folder named without a file'],
    'The curl transports fetched file:// and other protocols' => ['tests/tools/sweep-1022-probe.php', 'WP_Http_Curl asked for a file:// address'],
    'The cookie check skipped the session manager' => ['tests/tools/sweep-1022-probe.php', 'cookies under a manager that refuses every session'],
    'A query block with nothing that lists posts queried anyway, the block renderer included' => ['tests/tools/sweep-1022-probe.php', 'the block renderer asked for a query block'],
    'NAT64, site-local and multicast IPv6 passed the engine client\'s private-address check' => ['tests/unit/http.php', 'a name resolving only to NAT64'],
    'Minn Admin installed languages with file changes off' => ['public/minn/src/Minn/Admin/LanguageController.php', "can('install_languages')"],
    'The engine\'s own quoting ignored a multibyte connection charset (gbk), and set_charset fataled on a collation the charset lacks' => ['tests/security-sweep.test.php', 'a gbk connection'],
    'Distinct synced patterns and post-content loops nested combinatorially with no budget' => ['tests/unit/render-state.php', 'one outermost render opens at most MAX_SOURCES sources'],
    '?rest_route= answered as REST on wp-cron.php and admin-ajax.php, under DOING_CRON and DOING_AJAX' => ['tests/security-sweep.test.php', '?rest_route= is answered as REST only where the reference answers it'],
    'The engine routed its request before the server variables were made whole' => ['public/minn/src/Minn/Engine.php', 'Request::fixServerVars();'],
    'The installer\'s flush read the address with the site\'s plugins loaded and handed it to curl unchecked' => ['install.sh', "--skip-plugins --skip-themes"],
    'The links OPML printed stored link addresses unescaped as addresses' => ['tests/probes.test.php', "'link_name' => 'Unsafe'"],
    'The render budget started over at every outermost render, and a refused pattern was still read' => ['tests/unit/render-state.php', 'holds one budget for the whole request'],
    'A db.php drop-in\'s own multibyte connection let a lead byte close the engine\'s quote' => ['tests/security-sweep.test.php', "a drop-in\\'s own gbk connection"],    'Install\'s default park beside a served folder left a runnable WordPress, setup-config.php included, on the web' => ['tests/install.test.php', 'the default park beside a served folder is refused'],
    'The interactivity script bound a javascript: address from author-written context' => ['tests/browser/interactivity.test.js', 'a javascript: or data: address is never bound'],
    'Style bindings set url() and other CSS kses refuses' => ['tests/browser/interactivity.test.js', 'a style binding sets no url()'],
    'A decoy element with the server data\'s id switched interactivity server state off' => ['tests/browser/interactivity.test.js', 'server state is read from the script element, past an earlier element'],
    'Maintenance mode held neither REST, extension pages, nor public pages under MINN_WP_API auto or off' => ['tests/wp-api-modes.test.php', 'under maintenance a visitor meets the hold'],
    'REST drew an extension\'s blocks without its block gates and content filters' => ['tests/extensions.test.php', 'content.rendered over REST hides what the page hides'],
    'A post\'s author gave a comment on it to another user and read their email' => ['tests/security-sweep.test.php', 'cannot give a comment on it to another user'],
    'An extension\'s sign-up took any role, administrator included' => ['tests/extension-accounts.test.php', 'a sign-up asking for administrator is refused'],
    'An extension\'s sign-out ended the cookie\'s session without a nonce' => ['tests/extension-accounts.test.php', 'a sign-out carrying the cookie without its nonce ends no session'],
    'Recovery blamed a storage or drop-in failure on the plugin that wrote at boot' => ['tests/unit/recovery-blame.php', 'database layer is nobody'],
    'Recovery\'s strikes row grew with every failure and was autoloaded' => ['tests/recovery.test.php', 'the strikes row is not autoloaded'],
    'Recovery\'s self-check sent a fake sign-in cookie' => ['public/minn/src/Minn/Runtime/Recovery.php', '\'Accept-Language: en-US,en;q=0.9\''],
    'advanced-cache.php read the request before the server variables were made whole' => ['tests/dropins.test.php', 'advanced-cache.php reads the request path made whole'],
    'The upgrade folder was unguarded on its first unpack, and a failed install left its package' => ['tests/upgrades.test.php', 'a refused install leaves only the upgrade folder'],
    'minn login sent a live token to the database\'s home, not WP_HOME' => ['tests/cli.test.php', 'minn login builds the link on wp-config.php'],
    'Minn Admin\'s bell listed editorial notes' => ['tests/minn-v1.test.php', 'the bell lists no editorial note'],
    'A query loop\'s page links pointed at another host from a //path' => ['tests/security-sweep.test.php', 'page links never point at another host'],
    'An extension\'s table could take a core table\'s name or another extension\'s' => ['tests/unit/extension-tables.php', 'no extension declares a core table'],
    'Concurrent guesses at a short sign-in token each read the same miss count, and a stored hash opened a link' => ['tests/hardening.test.php', 'one wrong guess burns a seven-character token'],
    'The Minn bar and its nonce followed the cookie, not the reader plugins settle on' => ['tests/security-sweep.test.php', 'the Minn bar follows the reader plugins settle on'],
    'The laid pluggable.php asked for directly answered a 500 naming the engine' => ['tests/security-sweep.test.php', 'wp-includes/pluggable.php asked for directly'],
    'The System view passed MINN_WP_API auto while plugins no longer guarded public pages' => ['tests/wp-api-modes.test.php', 'the System view warns under auto'],
    'PHP_INT_MIN as a page answered 500' => ['tests/security-sweep.test.php', 'answers without a server error'],
    'A hostile pattern-directory answer failed the route for the hour it was kept' => ['tests/security-sweep.test.php', 'a pattern directory item of the wrong shape'],
    'wp_kses_one_attr skipped the 7.1.4 binding check' => ['tests/tools/shortcode-run-probe.php', 'wp_kses_one_attr refused'],
    'REST kept an extension\'s shortcodes and content filters but never ran them' => ['tests/extensions.test.php', 'shortcodes, content filters and block gates run for REST too'],
    'A park an earlier engine left unguarded stayed unguarded after an update' => ['tests/install.test.php', 'a park left without its refusal gets it back'],
    'The served-folder test refused /var/www and missed folders deeper in public_html' => ['tests/install.test.php', 'the served test passes /var/www'],
    'minn login read a commented-out or built WP_HOME' => ['tests/cli.test.php', 'a commented-out WP_HOME is never the one minn login reads'],
    'The server\'s bind printed srcdoc, form and style url() the client refuses' => ['tests/security-sweep.test.php', 'the server binds no style with url() and no srcdoc'],
    'A sign-out on an extension\'s page route could be forged by a cross-site navigation' => ['tests/extension-accounts.test.php', 'a sign-out page reached without the nonce ends no session'],
    'A failed install\'s climbing source path could remove a folder outside the upgrade folder' => ['tests/upgrades.test.php', 'a failed install never removes a folder outside the upgrade folder'],
];
$fixed = count(array_filter($findings, static fn (array $pin): bool => $contains($pin[0], $pin[1])));

$sql = null;
if (($counts = $json('contracts/query-count.json')) !== null) {
    $pages = [];
    foreach ((array) $counts['pages'] as $page => $stacks) {
        $pages[$page] = [(int) $stacks['engine']['Com_select'], (int) $stacks['reference']['Com_select']];
    }
    $sql = ['measured' => $counts['measured'], 'pages' => $pages, 'atOrUnder' => count(array_filter($pages, static fn (array $p): bool => $p[0] <= $p[1]))];
}

$hooks = ($status = $json('contracts/api/hook-status.json')) === null ? null : $status['figures'];
// The WordPress functions, hook calls and classes the engine still uses (tests/tools/wordpress-names.php).
$wordpressNames = ($names = $json('contracts/api/wordpress-names.json')) === null ? null : $names['summary'];
// The logic still in the facade, rather than in the engine (tests/tools/facade-logic.php).
$facadeLogic = ($logic = $json('contracts/api/facade-logic.json')) === null ? null : $logic['summary'];
// How far the review of that logic has come (tests/tools/facade-review.php).
$review = json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/facade-review.php') . ' --json 2>/dev/null'), true);
if ($facadeLogic !== null && is_array($review['summary'] ?? null)) {
    $facadeLogic['review'] = array_intersect_key($review['summary'], array_flip(['reviewed', 'unreviewed', 'stale', 'verdicts', 'keep', 'gates', 'doubts']));
}
// The hunts for behaviour no symbol count shows: probe writes, the REST fuzzer, WP-CLI's own tests.
$effects = $json('contracts/fixtures/effects/divergences.json');
$behaviour = [
    'sideEffects' => $effects === null ? null : ['probes' => count((array) $effects['probes']), 'differing' => count((array) $effects['differences']), 'rows' => array_sum(array_map('count', (array) $effects['differences']))],
    'restFuzz' => $json('contracts/rest-fuzz.json')['summary'] ?? null,
    'cliFeatures' => $json('contracts/cli-features.json')['summary'] ?? null,
];
$currency = $json('contracts/fixture-currency.json');
$fixtures = $currency === null ? null : $currency['summary'] + ['measured' => $currency['measured'], 'current' => ($currency['summary']['wordpress'] ?? '') === ($json('contracts/api/meta.json')['version'] ?? null) && ($currency['summary']['differing'] ?? 1) === 0];
$coverage = ($lines = $json('contracts/line-coverage.json')) === null ? null : ['measured' => $lines['measured'], 'percent' => $lines['percent'], 'lines' => $lines['lines'], 'covered' => $lines['covered'], 'parts' => $lines['parts']];

// The leanness ceilings the style suite holds (each only falls), with the value each started at and whether one ever rose.
$style = (string) file_get_contents("{$root}/tests/style.test.php");
$git = static fn (string $args): string => (string) shell_exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null');
$history = static function (string $variable) use ($git): array {
    $values = [];
    $commits = array_filter(explode("\n", trim($git('log --reverse --format=%h -G' . escapeshellarg('\\$' . $variable . ' = [0-9]+;') . ' -- tests/style.test.php'))));
    foreach ($commits as $commit) {
        if (preg_match('/\$' . $variable . ' = (\d+);/', $git('show ' . escapeshellarg("{$commit}:tests/style.test.php")), $m)) {
            $values[] = [$commit, (int) $m[1]];
        }
    }
    return $values;
};
$ceilings = [];
foreach (['deadNameCeiling' => 'Dead-name methods', 'liveCeiling' => 'Lifecycle branches (live() calls)', 'optionReadCeiling' => 'Option reads beside the store', 'rawOptionWriteCeiling' => 'Raw option writes', 'rawMetaWriteCeiling' => 'Raw meta writes', 'postRowCeiling' => 'Hand-built post rows', 'linkHrefCeiling' => 'Hand-built REST links', 'htmlspecialcharsCeiling' => 'htmlspecialchars outside Html', 'permalinkCallerCeiling' => 'Unfiltered permalink callers', 'bootedCeiling' => 'Runtime::booted() branches', 'wordpressCallCeiling' => 'Calls into WordPress-named functions', 'hookApiCeiling' => 'Calls into the hook API by its WordPress names', 'wordpressClassCeiling' => 'References to WordPress classes', 'longMethodCeiling' => 'Methods over eighty lines', 'bigClassCeiling' => 'Classes over six hundred lines', 'undocumentedCeiling' => 'Public methods without a docblock', 'boolParamCeiling' => 'Boolean parameters', 'wideConstructorCeiling' => 'Constructors with six or more parameters', 'unionCeiling' => 'array|PostRecord bridges', 'bracketCeiling' => 'Bracket reads of post columns', 'userBracketCeiling' => 'Bracket reads of user columns', 'commentBracketCeiling' => 'Bracket reads of comment columns', 'rawJsonCeiling' => 'Raw json_encode and json_decode calls', 'bareRouteCeiling' => 'Routes without a policy', 'leafCeiling' => 'Facade functions over fifteen lines', 'facadeLogicCeiling' => 'Facade functions and methods holding logic', 'facadeLogicLinesCeiling' => 'Lines of logic in the facade', 'wporgRequestCeiling' => 'wordpress.org request addresses'] as $variable => $label) {
    if (preg_match('/\$' . $variable . ' = (\d+);/', $style, $m)) {
        $values = $history($variable);
        $rose = false;
        for ($i = 1; $i < count($values); $i++) {
            $rose = $rose || $values[$i][1] > $values[$i - 1][1];
        }
        $ceilings[$variable] = ['label' => $label, 'ceiling' => (int) $m[1], 'started' => $values[0][1] ?? (int) $m[1], 'since' => $values[0][0] ?? '', 'rose' => $rose];
    }
}

// The last full run: each suite's totals line, green when it failed nothing.
$run = ($json('contracts/scorecard.json') ?? [])['run'] ?? null;
if ($runLog !== null) {
    $text = (string) @file_get_contents($runLog);
    preg_match_all('/^=== (.+?) ===$(.*?)(?=^=== |\z)/ms', $text, $sections, PREG_SET_ORDER);
    $suites = 0;
    $checks = 0;
    $red = [];
    foreach ($sections as [, $name, $body]) {
        $suites++;
        if (preg_match_all('/^(\d+) passed, (\d+) failed\b/m', $body, $totals, PREG_SET_ORDER) === 0) {
            $red[] = $name;
            continue;
        }
        [, $passed, $failed] = end($totals);
        $checks += (int) $passed;
        if ((int) $failed > 0) {
            $red[] = $name;
        }
    }
    // What engine code wrote to the test site's error log between the run's first and last line.
    $started = (int) trim((string) shell_exec('stat -f %B ' . escapeshellarg($runLog) . ' 2>/dev/null'));
    $ended = (int) filemtime($runLog);
    $warnings = [];
    $errorLog = minn_test_site_dir('minn') . '/public/wp-content/debug.log';
    foreach (is_file($errorLog) && $started > 0 ? (array) file($errorLog, FILE_IGNORE_NEW_LINES) : [] as $line) {
        if (preg_match('/^\[([^\]]+)\] (PHP (?:Warning|Notice|Deprecated|Fatal error|Parse error):.*?\/public\/minn\/(?!admin\/).*)$/', $line, $m) !== 1) {
            continue;
        }
        $at = strtotime($m[1]);
        if ($at !== false && $at >= $started && $at <= $ended) {
            $key = (string) preg_replace('#\S*/public/minn/#', 'minn/', $m[2]);
            $warnings[$key] = ($warnings[$key] ?? 0) + 1;
        }
    }
    arsort($warnings);
    $run = ['measured' => date('Y-m-d', $ended), 'suites' => $suites, 'green' => $suites - count($red), 'checks' => $checks, 'red' => $red, 'engineWarnings' => array_sum($warnings), 'warningKinds' => array_slice(array_keys($warnings), 0, 10)];
}

// The repository scans: dead ends called from engine code or defined by hand, the PHP floor, public-repo hygiene.
$gate = json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/gate-scan.php') . ' --json 2>/dev/null'), true);
// Token-window scans: duplicated engine code, and engine code identical to the reference's source.
$clones = json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/clone-scan.php') . ' --json 2>/dev/null'), true);

// The open items register: rows marked open, accepted, done.
$open = null;
$runtime = (string) file_get_contents("{$root}/contracts/runtime.md");
if (preg_match('/^## Open items register\b.*?(?=^## |\z)/ms', $runtime, $register)) {
    preg_match_all('/^\|[^|\n]*\|[^|\n]*\|[^|\n]*\|\s*(open|accepted|done|austin)\b/mi', $register[0], $rows);
    $open = array_count_values(array_map('strtolower', $rows[1])) + ['open' => 0, 'accepted' => 0, 'done' => 0, 'austin' => 0];
}

// The latest audit the measures were re-taken in: the newest docs/audit-<date>.md (the first, 2026-10-12, set the measures).
$audits = glob("{$root}/docs/audit-*.md") ?: [];
sort($audits);
$audit = $audits === [] ? null : ['date' => substr(basename((string) end($audits), '.md'), 6), 'file' => 'docs/' . basename((string) end($audits))];

$meta = $json('contracts/api/meta.json') ?? [];
$card = [
    'wordpress' => $meta['version'] ?? '',
    'audit' => $audit,
    'security' => ['found' => count($findings), 'fixed' => $fixed],
    'sql' => $sql,
    'hooks' => $hooks,
    'wordpressNames' => $wordpressNames,
    'coverage' => $coverage,
    'leanness' => $ceilings,
    'open' => $open,
    'run' => $run,
    'gate' => is_array($gate) ? $gate : null,
    'clones' => is_array($clones) ? $clones : null,
    'facadeLogic' => $facadeLogic,
    'behaviour' => $behaviour,
    'fixtures' => $fixtures,
];
file_put_contents("{$root}/contracts/scorecard.json", json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("security %d/%d fixed; sql %s; hooks %s; coverage %s; open %s\n",
    $fixed, count($findings),
    $sql === null ? 'unmeasured' : "{$sql['atOrUnder']}/" . count($sql['pages']) . ' pages at or under',
    $hooks === null ? 'unmeasured' : "{$hooks['accounted']}% accounted, {$hooks['provenWeighted']}% proven (weighted)",
    $coverage === null ? 'unmeasured' : "{$coverage['percent']}%",
    $open === null ? 'no register' : "{$open['open']} open, {$open['austin']} for Austin, {$open['accepted']} accepted, {$open['done']} done");
printf("leanness: %d ratchets, %d risen; run %s\n",
    count($ceilings), count(array_filter($ceilings, static fn (array $c): bool => $c['rose'])),
    $run === null ? 'not recorded' : "{$run['green']} of {$run['suites']} suites green, {$run['checks']} checks" . ($run['red'] === [] ? '' : ' (red: ' . implode(', ', $run['red']) . ')') . (isset($run['engineWarnings']) ? ", {$run['engineWarnings']} engine warnings" : ''));
printf("WordPress names in the engine: %s\n", $wordpressNames === null ? 'unmeasured' : "{$wordpressNames['functionCalls']} calls to {$wordpressNames['functions']} functions, {$wordpressNames['hookApi']} hook API calls, {$wordpressNames['classReferences']} references to {$wordpressNames['classes']} classes, in {$wordpressNames['files']} files (target 0, 0, 0)");
printf("fixtures: %s\n", $fixtures === null ? 'unmeasured (php tests/api.test.php --reference)' : "{$fixtures['differing']} of {$fixtures['rows']} rows differ on the reference at WordPress {$fixtures['wordpress']} (" . ($fixtures['current'] ? 'current' : 'not current') . ", {$fixtures['measured']})");
printf("behaviour: side effects %s; REST fuzz %s; WP-CLI feature tests %s\n",
    $behaviour['sideEffects'] === null ? 'unmeasured' : "{$behaviour['sideEffects']['differing']} of {$behaviour['sideEffects']['probes']} probes write otherwise ({$behaviour['sideEffects']['rows']} rows)",
    $behaviour['restFuzz'] === null ? 'to build' : json_encode($behaviour['restFuzz']),
    $behaviour['cliFeatures'] === null ? 'to build' : json_encode($behaviour['cliFeatures']));
printf("facade logic: %s\n", $facadeLogic === null ? 'unmeasured' : sprintf('%d functions and %d methods hold logic, %d lines; %d delegates; %d kept on purpose (only falls)%s',
    $facadeLogic['logicFunctions'], $facadeLogic['logicMethods'], $facadeLogic['logicLines'], $facadeLogic['delegates'], $facadeLogic['kept'],
    isset($facadeLogic['review']) ? sprintf('; reviewed %d, %d left (%d move, %d delegate, %d keep, %d remove)', $facadeLogic['review']['reviewed'], $facadeLogic['review']['unreviewed'], $facadeLogic['review']['verdicts']['move'], $facadeLogic['review']['verdicts']['delegate'], $facadeLogic['review']['verdicts']['keep'], $facadeLogic['review']['verdicts']['remove']) . "; {$facadeLogic['review']['doubts']} doubts open" : ''));
printf("gate: %s\n", !is_array($gate) ? 'gate-scan.php failed' : sprintf('%d calls into stubbed dead ends, %d unproven dead ends written by hand, PHP %s floor (%d lint failures, %d names only newer PHP has), %d hygiene hits',
    $gate['deadEndCalls'], $gate['deadEndsByHand'], $gate['floor']['version'], $gate['floor']['lintFailures'], $gate['floor']['unknown'], $gate['hygiene']['hits']));
printf("clones: %s\n", !is_array($clones) ? 'clone-scan.php failed' : sprintf('%d duplicated lines; %s',
    $clones['duplicated']['lines'], $clones['engine'] === null ? 'no reference to compare' : sprintf("engine PHP shares %d lines with the reference (%d at %d tokens), assets %d identical files and %d lines, data %d strings; facade %d lines",
        $clones['engine']['php']['strict']['lines'], $clones['engine']['php']['sensitive']['lines'], $clones['engine']['php']['sensitive']['window'],
        $clones['engine']['assets']['identical'], $clones['engine']['assets']['lines'], $clones['engine']['data']['strings'], $clones['facade']['lines'])));
