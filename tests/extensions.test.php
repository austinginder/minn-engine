<?php

declare(strict_types=1);

/**
 * The extension contract: manifests found and activated, every seam
 * firing, unknown shortcodes left alone, a failing extension skipped, and
 * the first port (block visibility) deciding blocks the way the plugin's
 * output showed for each device, reader, and screen size.
 */

$ENGINE = 'https://minn.localhost';
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/lib.php';

$pass = 0;
$fail = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n      " . substr($detail, 0, 400) : '') . "\n";
    }
}
function wp(string $command): string
{
    global $ROOT;
    return trim((string) shell_exec('cd ' . escapeshellarg(minn_test_site_root() . '/wp-reference') . " && wp $command 2>/dev/null"));
}
function fetch(string $url, array $headers = []): string
{
    $args = ['-sk', $url];
    foreach ($headers as $h) {
        array_push($args, '-H', $h);
    }
    return (string) shell_exec('/usr/bin/curl ' . implode(' ', array_map('escapeshellarg', $args)));
}
$created = [];
register_shutdown_function(static function () use (&$created): void {
    foreach ($created as $id) {
        wp("post delete $id --force");
    }
    wp("option update minn_active_extensions '[\"minn-block-visibility\"]'");
});
$make = static function (string $title, string $content) use (&$created): string {
    $id = (int) wp("post create --post_status=publish --post_title=" . escapeshellarg($title) . " --post_content=" . escapeshellarg($content) . ' --porcelain');
    $created[] = $id;
    return wp("post get $id --field=post_name");
};

echo "extensions suite: $ENGINE\n";
wp("option update minn_active_extensions '[\"minn-block-visibility\",\"minn-test-seams\"]'");

// 1. The seams.
$slug = $make('zz seams', '<!-- wp:paragraph --><p>seam-mark here [seam_probe name="n"]inner[/seam_probe] and [seam_probe bare] and [[seam_probe escaped]] and [unknown_tag x="1"]</p><!-- /wp:paragraph --><!-- wp:paragraph {"className":"seam-gated"} --><p class="seam-gated">gated away</p><!-- /wp:paragraph -->');
$page = fetch("$ENGINE/$slug/");
check(str_contains($page, '<span class="seam-probe" data-name="n">inner</span>'), 'a registered shortcode renders with attributes and content');
check(str_contains($page, '<span class="seam-probe" data-name="bare">no content</span>'), 'a bare positional attribute and self-closing form render');
check(str_contains($page, '[seam_probe escaped]'), 'a double-bracketed shortcode is the escaped literal');
check(str_contains($page, '[unknown_tag x=&#8221;1&#8243;]'), 'an unregistered shortcode stays as written (texturized, as the reference leaves it)');
check(str_contains($page, 'seam-marked here'), 'a block filter rewrote the paragraph');
check(!str_contains($page, 'gated away'), 'a block gate dropped the gated block');
check(preg_match('/<!-- seam:content post-\d+ -->/', $page) === 1, 'the content filter ran after blocks and shortcodes');
check(str_contains($page, '<meta name="seam-probe" content="head">'), 'the head seam contributed to the document head');
check(str_contains($page, '<!-- seam:footer reader-0 -->'), 'the footer seam contributed before the body closes, with the reader');
check(preg_match('/<body class="[^"]*\bseam-body\b/', $page) === 1, 'the body class seam');
$feed = fetch("$ENGINE/feed/");
check(str_contains($feed, 'data-name="n"') && str_contains($feed, 'seam:content'), 'shortcodes and content filters run for feeds too');
wp("option update minn_active_extensions '[\"minn-block-visibility\"]'");
check(!str_contains(fetch("$ENGINE/$slug/"), 'seam-probe'), 'an extension not activated does nothing');

// 2. Manifest discovery and status.
$status = trim((string) shell_exec('cd ' . escapeshellarg(minn_test_site_root() . '/public') . ' && wp minn info 2>&1'));
check(str_contains($status, 'Extensions: minn-block-visibility'), 'wp minn info lists the active extensions', $status);

// 3. Block visibility, decided as the plugin's output showed.
$desktop = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128 Safari/537.36';
$phone = 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
$tablet = 'User-Agent: Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
$device = static fn (string $value, bool $hideOnMatch = true): string => '"blockVisibility":{"controlSets":[{"id":1,"enable":true,"controls":{"browserDevice":{"ruleSets":[{"enable":true,"rules":[{"field":"deviceType","operator":"any","value":["' . $value . '"]}]}],"hideOnRuleSets":' . ($hideOnMatch ? 'true' : 'false') . '}}}]}';
$slug = $make('zz visibility', '<!-- wp:paragraph {"blockVisibility":{"hideBlock":true}} --><p>hidden-always</p><!-- /wp:paragraph -->'
    . '<!-- wp:paragraph {"blockVisibility":{"hideBlock":false}} --><p>shown-always</p><!-- /wp:paragraph -->'
    . '<!-- wp:group {"anchor":"mobile",' . $device('other') . '} --><div id="mobile" class="wp-block-group"><p>only-mobile</p></div><!-- /wp:group -->'
    . '<!-- wp:group {"anchor":"desktop",' . $device('mobile') . '} --><div id="desktop" class="wp-block-group"><p>only-desktop</p></div><!-- /wp:group -->'
    . '<!-- wp:paragraph {' . $device('mobile', false) . '} --><p>show-when-mobile</p><!-- /wp:paragraph -->'
    . '<!-- wp:paragraph {"blockVisibility":{"controlSets":[{"id":1,"enable":true,"controls":{"screenSize":{"hideOnScreenSize":{"small":true,"large":true}}}}]}} --><p>screen-sized</p><!-- /wp:paragraph -->'
    . '<!-- wp:paragraph {"blockVisibility":{"controlSets":[{"id":1,"enable":true,"controls":{"userRole":{"visibilityByRole":"logged-in"}}}]}} --><p>members-only</p><!-- /wp:paragraph -->'
    . '<!-- wp:paragraph {"blockVisibility":{"controlSets":[{"id":1,"enable":true,"controls":{"userRole":{"visibilityByRole":"logged-out"}}}]}} --><p>guests-only</p><!-- /wp:paragraph -->'
    . '<!-- wp:paragraph {"blockVisibility":{"controlSets":[{"id":1,"enable":true,"controls":{"dateTime":{"hideOnSchedules":false,"schedules":[{"enable":true,"start":"2000-01-01 00:00:00","end":"2001-01-01 00:00:00"}]}}}]}} --><p>expired-schedule</p><!-- /wp:paragraph -->'
    . '<!-- wp:paragraph {"blockVisibility":{"controlSets":[{"id":1,"enable":false,"controls":{"browserDevice":{"ruleSets":[{"enable":true,"rules":[{"field":"deviceType","operator":"any","value":["other"]}]}],"hideOnRuleSets":true}}}]}} --><p>disabled-set</p><!-- /wp:paragraph -->');
$onDesktop = fetch("$ENGINE/$slug/", [$desktop]);
$onPhone = fetch("$ENGINE/$slug/", [$phone]);
$onTablet = fetch("$ENGINE/$slug/", [$tablet]);
check(!str_contains($onDesktop, 'hidden-always') && str_contains($onDesktop, 'shown-always'), 'hideBlock hides; hideBlock false shows');
check(!str_contains($onDesktop, 'only-mobile') && str_contains($onDesktop, 'only-desktop'), 'a desktop reader gets the desktop group only');
check(str_contains($onPhone, 'only-mobile') && !str_contains($onPhone, 'only-desktop'), 'a phone gets the mobile group only');
check(str_contains($onTablet, 'only-mobile') && !str_contains($onTablet, 'only-desktop'), 'a tablet counts as mobile, as observed');
check(!str_contains($onDesktop, 'show-when-mobile') && str_contains($onPhone, 'show-when-mobile'), 'hideOnRuleSets false shows only when the rules match');
check(preg_match('/<p class="[^"]*block-visibility-hide-large-screen block-visibility-hide-small-screen[^"]*">screen-sized/', $onDesktop) === 1, 'screen sizes become the hide classes', $onDesktop);
check(str_contains($onDesktop, 'id="block-visibility-screen-size-styles-inline-css"') && str_contains($onDesktop, '.block-visibility-hide-medium-screen'), 'the breakpoint stylesheet is in the head');
check(!str_contains($onDesktop, 'members-only') && str_contains($onDesktop, 'guests-only'), 'role rules for a signed-out reader');
$mint = (array) json_decode((string) shell_exec('wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg("$ROOT/tests/tools/mint-session.php") . ' 2 2>/dev/null'), true);
$signedIn = fetch("$ENGINE/$slug/", ['Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; wordpress_logged_in_' . md5($ENGINE) . '=' . $mint['cookie']]);
check(str_contains($signedIn, 'members-only') && !str_contains($signedIn, 'guests-only'), 'role rules for a signed-in reader');
check(!str_contains($onDesktop, 'expired-schedule'), 'a schedule that has ended hides the block');
check(str_contains($onDesktop, 'disabled-set'), 'a disabled control set is ignored');

// 4. The other ports, on the dev site with their data staged.
wp("option update minn_active_extensions '[\"minn-block-visibility\",\"minn-simple-custom-css\",\"minn-ga-google-analytics\",\"minn-wp-retina-2x\",\"minn-gallery-custom-links\"]'");
wp("option update sccss_settings '{\"sccss-content\":\"body.zz-sccss { color: red; }\"}' --format=json");
wp("option update gap_options '{\"gap_id\":\"G-ZZTEST1234\",\"gap_location\":\"header\",\"gap_anonymize\":1}' --format=json");
$image = (int) wp("db query \"SELECT ID FROM wp_posts WHERE post_type='attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID ASC LIMIT 1\" --skip-column-names");
$file = wp("post meta get $image _wp_attached_file");
$uploads = minn_test_site_root() . '/public/wp-content/uploads';
$retina = preg_replace('/\.([a-z0-9]+)$/i', '@2x.$1', $file);
copy("$uploads/$file", "$uploads/$retina");
wp("post meta update $image _gallery_link_url https://example.test/linked");
wp("post meta update $image _gallery_link_target _blank");
$slug = $make('zz ports', '<!-- wp:image {"id":' . $image . ',"sizeSlug":"full"} --><figure class="wp-block-image size-full"><img src="' . rtrim(wp('option get home'), '/') . '/wp-content/uploads/' . $file . '" alt="" class="wp-image-' . $image . '"/></figure><!-- /wp:image -->');
$page = fetch("$ENGINE/$slug/");
check(str_contains($page, '<style id="sccss">body.zz-sccss { color: red; }'), 'simple-custom-css: the option is the head stylesheet');
check(str_contains($page, 'googletagmanager.com/gtag/js?id=G-ZZTEST1234') && str_contains($page, "gtag('config', 'G-ZZTEST1234', { 'anonymize_ip': true });"), 'ga-google-analytics: the gtag snippet with the anonymize flag');
check(preg_match('/srcset="[^"]*' . preg_quote(basename($retina), '/') . ' \d+w"/', $page) === 1, 'wp-retina-2x: the @2x file joins the srcset at twice the width', substr($page, strpos($page, 'srcset='), 300));
check(str_contains($page, '<a href="https://example.test/linked" class="custom-link no-lightbox"') && str_contains($page, 'target="_blank"') && preg_match('/<!-- Gallery Custom Links: \d+ images scanned, 1 linked \(HtmlDomParser\)\. -->/', $page) === 1, 'gallery-custom-links: the anchor from attachment meta and the count', substr($page, strpos($page, 'Gallery Custom') - 20, 120));
check(str_contains($page, 'viewbox=') || !str_contains($page, 'viewBox='), 'gallery-custom-links: attribute names lower-cased once something was linked');
@unlink("$uploads/$retina");
wp("post meta delete $image _gallery_link_url");
wp("post meta delete $image _gallery_link_target");
wp('option delete sccss_settings');
wp('option delete gap_options');
wp("option update minn_active_extensions '[\"minn-block-visibility\"]'");

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
