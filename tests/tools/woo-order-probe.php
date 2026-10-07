<?php
/**
 * One WooCommerce order's shape, for the woo suite to compare an order the
 * engine took with one the reference took (both land in the lab's one
 * database): its data, items and meta with ids, dates, keys, hashes and the
 * buyer's address (each stack is reached from a different one) masked, its
 * notes, and the stock and sales of the products it holds. Run under WP-CLI
 * on the reference: wp eval-file woo-order-probe.php <order id>.
 */

$id = (int) ($args[0] ?? 0);
$order = wc_get_order($id);
if (!$order) {
    echo json_encode(['missing' => $id]), "\n";
    return;
}
$mask = static fn ($value) => is_string($value) ? preg_replace(['/wc_order_\w+/', '/\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d(\+\d\d:\d\d)?/', '/\b[0-9a-f]{32}\b/', '/place-order-debug-[0-9a-f]+/'], ['{key}', '{date}', '{hash}', '{debug-source}'], $value) : $value;
$data = $order->get_data();
foreach (['id', 'date_created', 'date_modified', 'date_paid', 'date_completed', 'order_key', 'cart_hash', 'number', 'customer_ip_address', 'customer_user_agent'] as $key) {
    unset($data[$key]);
}
$products = [];
$items = [];
foreach ($order->get_items(['line_item', 'shipping', 'tax', 'fee', 'coupon']) as $item) {
    $row = $item->get_data();
    unset($row['id'], $row['order_id']);
    $row['meta_data'] = array_map(static fn ($meta) => [$meta->key, $meta->value], $row['meta_data']);
    $items[] = $row;
    if ($item instanceof WC_Order_Item_Product && $item->get_product()) {
        $products[$item->get_product()->get_slug()] = [$item->get_product()->get_stock_quantity(), $item->get_product()->get_total_sales()];
    }
}
unset($data['line_items'], $data['shipping_lines'], $data['tax_lines'], $data['fee_lines'], $data['coupon_lines']);
// Meta the checkout's step logger leaves for a scheduled clean-up is there or not by when cron last ran, not by the stack.
$data['meta_data'] = array_values(array_filter(array_map(static fn ($meta) => [$meta->key, $mask($meta->value)], $data['meta_data']), static fn ($meta) => !in_array($meta[0], ['_debug_log_source', '_debug_log_source_pending_deletion'], true)));
usort($data['meta_data'], static fn ($a, $b) => strcmp($a[0], $b[0]));
$notes = array_map(static fn ($note) => [$note->added_by, $mask((string) preg_replace('/#\d+/', '#N', $note->content))], wc_get_order_notes(['order_id' => $id]));
echo json_encode(['order' => array_map($mask, $data), 'items' => $items, 'notes' => $notes, 'products' => $products], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
