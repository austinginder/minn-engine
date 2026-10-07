<?php
/**
 * WooCommerce parity on the lab (minnwoo.localhost, its parked WordPress on
 * 127.0.0.1:8126; roadmap item W): the store's addresses (status, where a
 * move goes, the body classes WooCommerce and themes key off, the title),
 * the Store API's reads, and a guest checkout taken on each stack through
 * the Store API the block checkout uses: the cart after each step, the
 * coupon, the checkout's answer, the order each stack wrote (data, items,
 * notes; both land in the lab's one database), the stock and sales it
 * moved, the order-received page, and the two emails it sent.
 *
 * The lab's database is put back from its baseline before the run and
 * after it (private/woo-baseline.sql beside the site; a killed run is
 * healed by the next one).
 *
 *   (cd ~/Cove/Sites/minnwoo.localhost/wp-reference && php -S 127.0.0.1:8126 router.php &)
 *   php tests/woo.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$SITE = getenv('MINN_WOO_ROOT') ?: '~/Cove/Sites/minnwoo.localhost';
$ENGINE = rtrim(getenv('MINN_WOO_URL') ?: 'https://minnwoo.localhost', '/');
$REF = rtrim(getenv('MINN_WOO_REF') ?: 'http://127.0.0.1:8126', '/');
$MAILPIT = getenv('MINN_MAILPIT') ?: 'https://cove.localhost/mail-api/v1';
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg("{$SITE}/wp-reference");
$BASELINE = "{$SITE}/private/woo-baseline.sql";

[$probe] = minn_test_fetch("{$REF}/wp-json/wc/store/v1/products?per_page=1", 5);
if (($probe['status'] ?? 0) !== 200) {
    echo "SKIP: the lab's reference is not running at {$REF}\n";
    exit(0);
}
if (!is_file($BASELINE)) {
    echo "  FAIL the lab's baseline is missing ({$BASELINE})\n";
    exit(1);
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
$restore = static function () use ($WP, $BASELINE): void {
    shell_exec("{$WP} db import " . escapeshellarg($BASELINE) . ' --skip-plugins --skip-themes 2>/dev/null');
};
$restore();
register_shutdown_function($restore);

/** Both stacks' own addresses as one placeholder; each spells its links with the host it was asked on. */
$plain = static function (string $text) use ($ENGINE, $REF): string {
    return str_replace([$ENGINE, $REF, str_replace('/', '\/', $ENGINE), str_replace('/', '\/', $REF)], ['{site}', '{site}', '{site}', '{site}'], $text);
};

/** A request with headers and a JSON body: [status, headers (lower-cased names), body]. */
$request = static function (string $url, string $method = 'GET', ?array $json = null, array $headers = []): array {
    $ch = curl_init($url);
    $lines = [];
    foreach ($headers as $name => $value) {
        $lines[] = "{$name}: {$value}";
    }
    if ($json !== null) {
        $lines[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($json));
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $lines]);
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $out = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $out[strtolower(trim($name))] = trim($value);
        }
    }
    return [$status, $out, substr($raw, $size)];
};

// The store's addresses.
$page = static function (string $base, string $path) use ($request, $plain): array {
    [$status, $headers, $body] = $request($base . $path);
    // The whole list in its order: WooCommerce's and the theme's tokens ride on the core ones.
    $classes = preg_match('/<body[^>]*class="([^"]*)"/', $body, $m) ? explode(' ', $m[1]) : [];
    preg_match('#<title>(.*?)</title>#s', $body, $t);
    return ['status' => $status, 'location' => $plain($headers['location'] ?? ''), 'classes' => $classes, 'title' => trim($t[1] ?? '')];
};
echo "store pages\n";
foreach (['/', '/shop/', '/shop/page/2/', '/shop/page/9/', '/shop/?orderby=price', '/shop/?orderby=price-desc', '/shop/?orderby=popularity', '/product/hoodie/', '/product/beanie/', '/product/album/', '/product-category/clothing/', '/product-category/clothing/accessories/', '/product-category/nope/', '/product-tag/nope/', '/cart/', '/checkout/', '/my-account/', '/my-account/orders/', '/my-account/edit-address/', '/my-account/lost-password/', '/?s=hoodie&post_type=product', '/?s=zzzz&post_type=product', '/?s=&post_type=product', '/shop/?s=hoodie', '/product-category/clothing/?s=hoodie', '/?s=hoodie&product_cat=clothing', '/?post_type=product', '/?post_type=product&product_cat=clothing', '/?product_cat=clothing', '/?product_cat=clothing&orderby=price', '/?product_cat=clothing&cat=1', '/?product_cat=bogus', '/?taxonomy=product_cat&term=clothing', '/?product=beanie', '/?product=bogus', '/?post_type=product&name=beanie', '/?p=15', '/?p=15&foo=bar&post_type=product'] as $path) {
    $e = $page($ENGINE, $path);
    $r = $page($REF, $path);
    $check("{$path}: status, move, body classes and title", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
}

// The Store API's reads.
echo "store api reads\n";
$json = static fn (string $body) => json_decode($body, true);
foreach (['/wp-json/wc/store/v1/products?per_page=100', '/wp-json/wc/store/v1/products/12', '/wp-json/wc/store/v1/products/15', '/wp-json/wc/store/v1/products?category=clothing&orderby=price&order=desc', '/wp-json/wc/store/v1/products?search=hoodie', '/wp-json/wc/store/v1/products/categories', '/wp-json/wc/store/v1/products/attributes', '/wp-json/wc/store/v1/products/999999'] as $path) {
    [$es, , $eb] = $request($ENGINE . $path);
    [$rs, , $rb] = $request($REF . $path);
    $diff = minn_test_diff($json($plain($eb)), $json($plain($rb)));
    $check("{$path}: status and answer", $es === $rs && $diff === null, "{$es} vs {$rs} " . ($diff ?? ''));
}

// A guest checkout on each stack: two beanies and a t-shirt, the coupon, cash on delivery.
echo "checkout\n";
$run = bin2hex(random_bytes(4));
$state = static function () use ($WP): array {
    $out = shell_exec("{$WP} eval 'echo json_encode([wc_get_product(15)->get_stock_quantity(), wc_get_product(15)->get_total_sales(), wc_get_product(14)->get_total_sales()]);' 2>/dev/null");
    return (array) json_decode((string) $out, true);
};
$cartShape = static function (string $body) use ($json, $plain): array {
    $cart = (array) $json($plain($body));
    unset($cart['extensions']);
    return $cart;
};
$checkout = static function (string $base, string $stack) use ($request, $run, $cartShape, $state): array {
    $steps = [];
    [$status, $headers, $body] = $request("{$base}/wp-json/wc/store/v1/cart");
    $auth = ['Nonce' => $headers['nonce'] ?? '', 'Cart-Token' => $headers['cart-token'] ?? ''];
    $steps['empty cart'] = [$status, $cartShape($body), $auth['Nonce'] !== '' && $auth['Cart-Token'] !== ''];
    [$status, , $body] = $request("{$base}/wp-json/wc/store/v1/cart/add-item", 'POST', ['id' => 15, 'quantity' => 2], $auth);
    $steps['two beanies'] = [$status, $cartShape($body)];
    [$status, , $body] = $request("{$base}/wp-json/wc/store/v1/cart/add-item", 'POST', ['id' => 14, 'quantity' => 1], $auth);
    $steps['and a t-shirt'] = [$status, $cartShape($body)];
    [$status, , $body] = $request("{$base}/wp-json/wc/store/v1/cart/apply-coupon", 'POST', ['code' => 'minn10'], $auth);
    $steps['the coupon'] = [$status, $cartShape($body)];
    [$status, , $body] = $request("{$base}/wp-json/wc/store/v1/cart/apply-coupon", 'POST', ['code' => 'nope'], $auth);
    $steps['a coupon that does not exist'] = [$status, $cartShape($body)];
    $before = $state();
    $address = ['first_name' => 'Ada', 'last_name' => 'Probe', 'address_1' => '1 Main St', 'city' => 'Strasburg', 'state' => 'PA', 'postcode' => '17579', 'country' => 'US'];
    [$status, , $body] = $request("{$base}/wp-json/wc/store/v1/checkout", 'POST', ['billing_address' => $address + ['email' => "ada.{$stack}.{$run}@example.test", 'phone' => '5555550100'], 'shipping_address' => $address, 'payment_method' => 'cod', 'customer_note' => 'Leave it at the door.'], $auth);
    $answer = (array) json_decode($body, true);
    return ['steps' => $steps, 'status' => $status, 'answer' => $answer, 'before' => $before, 'after' => $state()];
};
$order = static function (int $id) use ($WP): array {
    $out = shell_exec("{$WP} eval-file " . escapeshellarg(__DIR__ . '/tools/woo-order-probe.php') . " {$id} 2>/dev/null");
    $shape = (array) json_decode((string) $out, true);
    unset($shape['products']);
    return json_decode((string) preg_replace('/ada\.(eng|ref)\.[0-9a-f]+@/', 'ada.{stack}@', (string) json_encode($shape, JSON_UNESCAPED_SLASHES)), true) ?? [];
};
$received = static function (string $base, array $answer) use ($page): array {
    $url = (string) ($answer['payment_result']['redirect_url'] ?? '');
    $path = (string) preg_replace('#^https?://[^/]+#', '', $url);
    return $page($base, $path);
};
// Each stack shops from the same baseline (they share the lab's database), so each order is the first since it.
$e = $checkout($ENGINE, 'eng');
$eo = $order((int) ($e['answer']['order_id'] ?? 0));
$erec = $received($ENGINE, $e['answer']);
$restore();
$r = $checkout($REF, 'ref');
$ro = $order((int) ($r['answer']['order_id'] ?? 0));
$rrec = $received($REF, $r['answer']);
foreach ($r['steps'] as $label => $step) {
    $diff = minn_test_diff($e['steps'][$label] ?? null, $step);
    $check("cart: {$label}", $diff === null, $diff ?? '');
}
/** A checkout's answer without what differs by order (its id, number and key) or by buyer. */
$answerShape = static function (array $answer) use ($plain): array {
    $text = $plain((string) json_encode($answer, JSON_UNESCAPED_SLASHES));
    $text = (string) preg_replace(['/wc_order_\w+/', '/ada\.(eng|ref)\.[0-9a-f]+@/'], ['{key}', 'ada.{stack}@'], $text);
    $shape = (array) json_decode($text, true);
    foreach (['order_id', 'order_number'] as $key) {
        $shape[$key] = isset($shape[$key]) ? '{order}' : null;
    }
    return json_decode((string) preg_replace('#order-received/\d+/#', 'order-received/{order}/', (string) json_encode($shape, JSON_UNESCAPED_SLASHES)), true);
};
$diff = minn_test_diff($answerShape($e['answer']), $answerShape($r['answer']));
$check('checkout: status and answer', $e['status'] === $r['status'] && $diff === null, "{$e['status']} vs {$r['status']} " . ($diff ?? ''));
$check('checkout: both took an order', ($e['answer']['order_id'] ?? 0) > 0 && ($r['answer']['order_id'] ?? 0) > 0, json_encode([$e['answer']['order_id'] ?? null, $r['answer']['order_id'] ?? null]));
$moved = static fn (array $run) => [($run['after'][0] ?? 0) - ($run['before'][0] ?? 0), ($run['after'][1] ?? 0) - ($run['before'][1] ?? 0), ($run['after'][2] ?? 0) - ($run['before'][2] ?? 0)];
$check('checkout: stock and sales moved alike', $moved($e) === $moved($r) && $moved($r) === [-2, 2, 1], json_encode(['engine' => $moved($e), 'reference' => $moved($r)]));

foreach (['order', 'items', 'notes'] as $part) {
    $diff = minn_test_diff($eo[$part] ?? null, $ro[$part] ?? null);
    $check("order: {$part}", isset($ro[$part]) && $diff === null, $diff ?? '');
}

$check('order received: the page answers alike', $erec === $rrec && $rrec['status'] === 200, json_encode([$erec, $rrec], JSON_UNESCAPED_SLASHES));

// The emails the orders sent: the buyer's and the store's, by subject.
$mailpit = static function (string $path) use ($MAILPIT): array {
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    return (array) json_decode((string) @file_get_contents("{$MAILPIT}/{$path}", false, $context), true);
};
$mail = static function (string $stack) use ($mailpit, $run): array {
    $subjects = [];
    // The buyer's address is in both emails: the buyer's own To, and the store's body.
    foreach ((array) ($mailpit('search?query=' . rawurlencode("ada.{$stack}.{$run}"))['messages'] ?? []) as $message) {
        $toBuyer = str_contains((string) json_encode($message['To'] ?? []), "ada.{$stack}.{$run}");
        $subjects[] = ($toBuyer ? 'buyer: ' : 'store: ') . preg_replace('/#\d+\b/', '{order}', (string) $message['Subject']);
    }
    sort($subjects);
    return $subjects;
};
usleep(500000);
$em = $mail('eng');
$rm = $mail('ref');
$check('emails: the buyer and the store were told alike', $em === $rm && count($rm) === 2, json_encode(['engine' => $em, 'reference' => $rm], JSON_UNESCAPED_SLASHES));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
