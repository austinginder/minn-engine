<?php

declare(strict_types=1);

/**
 * A classic theme with only an index.php that still calls get_header(),
 * get_sidebar() and get_footer(): the reference supplies its deprecated
 * theme-compat header, sidebar and footer, noted as deprecated, and the
 * engine its own. A throwaway theme is made active on the reference for the
 * suite (template and stylesheet, restored on shutdown), with a bookmark
 * and a throwaway editor made for it and removed after, and each page type
 * is fetched from both stacks and compared: the head the header prints
 * before wp_head, and the whole body (the engine's own stylesheets, its
 * comment reply script's address and the emoji loader it leaves out set
 * aside). The theme's functions.php prints each deprecation notice heard,
 * and its query switches (?zz...) turn on registration, a widget sidebar, a
 * marked-up site name, two posts a page, and no canonical redirect, all
 * through filters, so no option is written.
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$WP = '/opt/homebrew/bin/wp --skip-plugins --skip-themes --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference');

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail === '' ? '' : "\n       " . substr($detail, 0, 900)) . "\n";
    }
};

[$head] = minn_test_fetch("{$REF}/?rest_route=/wp/v2/posts", 3);
if ($head['status'] !== 200) {
    echo "SKIP: reference not running at {$REF}\n";
    exit(0);
}
echo "theme-compat suite: {$ENGINE} (engine) / {$REF} (oracle)\n";

$wp = static fn (string $command): string => trim((string) shell_exec($WP . ' ' . $command . ' 2>/dev/null'));
$dir = minn_test_site_root() . '/wp-reference/wp-content/themes/zz-compat-suite';
$template = $wp('option get template');
$stylesheet = $wp('option get stylesheet');
@mkdir($dir);
file_put_contents("{$dir}/style.css", "/*\nTheme Name: ZZ Compat Suite\n*/\n");
file_put_contents("{$dir}/index.php", "<?php get_header(); ?>\n<main>ZZCOMPAT\n<?php while ( have_posts() ) { the_post(); the_title( '<h2>', '</h2>' ); } ?>\n</main>\n<?php get_sidebar(); get_footer();\n");
file_put_contents("{$dir}/functions.php", <<<'PHP'
<?php
add_action( 'deprecated_file_included', static function ( ...$a ) { echo '<!--ZZDEP ' . json_encode( $a ) . '-->'; }, 10, 4 );
add_filter( 'deprecated_file_trigger_error', '__return_false' );
add_action( 'pre_get_posts', static function ( $q ) { if ( isset( $_GET['zzpp'] ) && $q->is_main_query() ) { $q->set( 'posts_per_page', 2 ); } } );
add_filter( 'option_users_can_register', static fn ( $v ) => isset( $_GET['zzreg'] ) ? '1' : $v );
add_filter( 'option_blogname', static fn ( $v ) => isset( $_GET['zzname'] ) ? 'Z&amp;Z "Q" <i>it</i>' : $v );
if ( isset( $_GET['zznocanon'] ) ) {
	add_filter( 'redirect_canonical', '__return_false' );
}
add_action( 'wp_meta', static function () { echo '<!--ZZMETA-->'; } );
if ( isset( $_GET['zzwidgets'] ) ) {
	register_sidebar( array( 'id' => 'sidebar-1', 'name' => 'ZZ Sidebar' ) );
	add_filter( 'sidebars_widgets', static function ( $w ) { $w['sidebar-1'] = array( 'block-2' ); return $w; } );
}
PHP);
$bookmark = (int) $wp('eval ' . escapeshellarg('require_once ABSPATH . "wp-admin/includes/bookmark.php"; echo wp_insert_link(array("link_url" => "https://example.invalid/zz", "link_name" => "ZZ Link"));'));
$password = bin2hex(random_bytes(12));
$user = (int) $wp('user create zzcompat zzcompat@example.invalid --role=editor --porcelain --user_pass=' . $password);
$jars = [];
register_shutdown_function(static function () use ($wp, $dir, $template, $stylesheet, $bookmark, $user, &$jars): void {
    $wp('option update template ' . escapeshellarg($template));
    $wp('option update stylesheet ' . escapeshellarg($stylesheet));
    $wp('cache flush');
    foreach (['style.css', 'index.php', 'functions.php'] as $file) {
        @unlink("{$dir}/{$file}");
    }
    @rmdir($dir);
    if ($bookmark > 0) {
        $wp('eval ' . escapeshellarg('require_once ABSPATH . "wp-admin/includes/bookmark.php"; wp_delete_link(' . $bookmark . ');'));
    }
    if ($user > 1) {
        $wp('user delete ' . $user . ' --yes');
    }
    foreach ($jars as $jar) {
        @unlink($jar);
    }
});
$check($bookmark > 0 && $user > 1, 'a bookmark and an editor are made on the reference', "{$bookmark} {$user}");
$wp('option update template zz-compat-suite');
$wp('option update stylesheet zz-compat-suite');
$wp('cache flush');

// The part the compat files print: the head up to wp_head's first line, and the body.
$part = static function (string $html): string {
    $head = preg_match('#\A(.*?)<meta name=.robots#s', $html, $m) ? $m[1] : '';
    $body = preg_match('#(<body.*)\z#s', $html, $m) ? $m[1] : '';
    $body = (string) preg_replace('#<script id="wp-emoji-settings".*?</script>\n<script type="module">.*?</script>\n?#s', '', $body);
    $body = (string) preg_replace('#src="[^"]*comment-reply[^"]*"#', 'src="CR"', $body);
    return $head . "\n=====\n" . (string) preg_replace('/_wpnonce=[0-9a-f]{10}/', '_wpnonce=N', $body);
};
$deprecations = static fn (string $html): array => preg_match_all('#<!--ZZDEP (.*?)-->#', $html, $m) ? array_map(static fn ($json) => json_decode($json, true)[0] ?? null, $m[1]) : [];
$firstDifference = static function (string $a, string $b): string {
    $a = explode("\n", $a);
    $b = explode("\n", $b);
    foreach ($a as $n => $line) {
        if (($b[$n] ?? null) !== $line) {
            return 'line ' . ($n + 1) . "\n       engine:    " . $line . "\n       reference: " . ($b[$n] ?? '(none)');
        }
    }
    return 'the reference has more lines';
};

$pages = [
    '/' => 'the posts page',
    '/hello-world/' => 'a single post (its comment reply script in the footer)',
    '/sample-page/' => 'a page (its bookmarks and Meta box)',
    '/category/uncategorized/' => 'a category archive',
    '/2026/' => 'a year archive',
    '/2026/08/' => 'a month archive',
    '/2026/08/28/' => 'a day archive',
    '/author/admin/' => 'an author archive',
    '/?s=%3Cb%3E%27x%22%26amp' => 'a search with markup in its terms',
    '/no-such-page-zz/' => 'an error page',
    '/page/2/?zzpp=1' => 'the posts page\'s second page',
    '/category/uncategorized/page/2/?zzpp=1' => 'a category\'s second page',
    '/?zzpp=1&paged=2&zznocanon=1' => 'a second page asked for as ?paged',
    '/?zzreg=1' => 'registration open (Register in the Meta box)',
    '/?zzwidgets=1' => 'a sidebar with widgets',
    '/?zzname=1' => 'a site name with markup',
];
foreach ($pages as $path => $label) {
    [$e, $engine] = minn_test_fetch($ENGINE . $path, 30);
    [$r, $reference] = minn_test_fetch($REF . $path, 30);
    $same = $e['status'] === $r['status'] && $part($engine) === $part($reference);
    $check($same, "{$label}: the compat header, sidebar and footer print as on the reference", $same ? '' : "{$e['status']}/{$r['status']} " . $firstDifference($part($engine), $part($reference)));
}
[, $reference] = minn_test_fetch("{$REF}/", 30);
[, $engine] = minn_test_fetch("{$ENGINE}/", 30);
$names = ['Theme without header.php', 'Theme without sidebar.php', 'Theme without footer.php'];
$check($deprecations($reference) === $names && $deprecations($engine) === $names, 'each compat file is noted as deprecated, in the order loaded', json_encode([$deprecations($engine), $deprecations($reference)]));

// Signed in: Site Admin and Log out in the Meta box (the toolbar is each stack's own).
$sidebar = static fn (string $html): string => preg_match('#<div id="sidebar".*?\n\t</div>\n#s', $html, $m) ? (string) preg_replace('/_wpnonce=[0-9a-f]{10}/', '_wpnonce=N', $m[0]) : '';
$signedIn = [];
foreach (['engine' => $ENGINE, 'reference' => $REF] as $stack => $base) {
    $jar = $jars[] = (string) tempnam(sys_get_temp_dir(), 'zzcompat');
    shell_exec('/usr/bin/curl -sk -m 30 -o /dev/null -c ' . escapeshellarg($jar) . ' -b wordpress_test_cookie=WP%20Cookie%20check --data-urlencode log=zzcompat --data-urlencode pwd=' . escapeshellarg($password) . ' -d testcookie=1 ' . escapeshellarg("{$base}/wp-login.php"));
    $signedIn[$stack] = $sidebar((string) shell_exec('/usr/bin/curl -sk -m 30 -b ' . escapeshellarg($jar) . ' ' . escapeshellarg("{$base}/")));
}
$check($signedIn['engine'] !== '' && $signedIn['engine'] === $signedIn['reference'] && str_contains($signedIn['reference'], 'Site Admin'), 'signed in: the sidebar offers Site Admin and Log out, as on the reference', $firstDifference($signedIn['engine'], $signedIn['reference']));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
