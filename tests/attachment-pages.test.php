<?php
/**
 * Attachment-page parity: the addresses an attachment's page answers to,
 * on both stacks. The suite makes two attachments (one belonging to Hello
 * world, one loose) and asks for each by every form the reference knows:
 * its own address, another post's or no post's address before its slug,
 * "attachment/" between, no trailing slash, ?attachment_id=, ?attachment=,
 * ?p= and ?page_id=, its /embed/ page, and addresses that find nothing;
 * with attachment pages off (the default: each answers as typed) and on
 * (a stray address moves to the attachment's own). Compared are the
 * status, where a move goes and the page's body classes. The attachments
 * and the setting are put back when the suite ends.
 *
 *   php tests/attachment-pages.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');

[$ph] = minn_test_fetch($REF . '/?rest_route=/', 3);
if (($ph['status'] ?? 0) !== 200) {
    echo "SKIP: reference WordPress not running at {$REF}\n";
    exit(0);
}

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n       {$detail}" : '') . "\n";
    }
};

$titles = "['Zz Att Attached', 'Zz Att Loose']";
$sweep = "global \$wpdb; foreach (\$wpdb->get_col(\"SELECT ID FROM {\$wpdb->posts} WHERE post_title IN ('Zz Att Attached', 'Zz Att Loose')\") as \$id) { if (in_array(get_post_field('post_title', (int) \$id, 'raw'), {$titles}, true)) { wp_delete_post((int) \$id, true); } }";
$setting = trim((string) shell_exec("{$WP} option get wp_attachment_pages_enabled 2>/dev/null"));
register_shutdown_function(static function () use ($WP, $sweep, $setting): void {
    shell_exec("{$WP} eval " . escapeshellarg($sweep) . ' >/dev/null 2>&1');
    shell_exec("{$WP} option update wp_attachment_pages_enabled " . escapeshellarg($setting === '' ? '0' : $setting) . ' >/dev/null 2>&1');
});
$made = (string) shell_exec("{$WP} eval " . escapeshellarg($sweep . ' $a = wp_insert_attachment(["post_title" => "Zz Att Attached", "post_name" => "zz-att-attached", "post_mime_type" => "image/png", "post_status" => "inherit"], "zz-att.png", 1); update_post_meta($a, "_wp_attached_file", "zz-att.png"); $b = wp_insert_attachment(["post_title" => "Zz Att Loose", "post_name" => "zz-att-loose", "post_mime_type" => "application/pdf", "post_status" => "inherit"], "zz-att.pdf", 0); update_post_meta($b, "_wp_attached_file", "zz-att.pdf"); echo json_encode([$a, $b]);') . ' 2>/dev/null');
[$attached, $loose] = json_decode($made, true) ?: [0, 0];
if (!$attached || !$loose) {
    echo "FAIL: could not make the suite's attachments\n";
    exit(1);
}

/** Status, where a move goes, and the page's body classes, for one address on one stack. */
$ask = static function (string $base, string $path) use ($ENGINE, $REF, $attached, $loose): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    $raw = (string) curl_exec($ch);
    $head = substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    preg_match('/^location:\s*(\S+)/mi', $head, $l);
    preg_match('/<body class="([^"]*)"/', $raw, $b);
    $mask = static fn (string $s): string => str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE, (string) $attached, (string) $loose], ['{site}', '{site}', '{site}', '{attached}', '{loose}'], $s);
    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $mask($l[1] ?? ''), $mask($b[1] ?? '')];
};

$paths = ['/hello-world/zz-att-attached/', '/zz-att-loose/', '/zz-att-loose', '/hello-world/zz-att-attached', "/?attachment_id={$attached}", "/?attachment_id={$loose}", '/?attachment=zz-att-loose',
    '/hello-world/attachment/zz-att-attached/', '/hello-world/attachment/zz-att-loose/', '/nope/zz-att-attached/', '/sample-page/zz-att-attached/', '/zz-att-attached/',
    "/?p={$attached}", "/?page_id={$loose}", '/zz-att-loose/embed/', "/?attachment_id={$attached}&embed=true", '/?attachment_id=999999999', '/hello-world/zz-att-attached/attachment/x/'];
foreach (['0' => 'attachment pages off', '1' => 'attachment pages on'] as $value => $label) {
    shell_exec("{$WP} option update wp_attachment_pages_enabled {$value} >/dev/null 2>&1");
    echo "{$label}\n";
    foreach ($paths as $path) {
        $e = $ask($ENGINE, $path);
        $r = $ask($REF, $path);
        $check("{$path}: status, move and body classes", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
