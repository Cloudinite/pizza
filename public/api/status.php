<?php
require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Robots-Tag: noindex');
$order = order_find_public(strtoupper((string) ($_GET['code'] ?? '')), (string) ($_GET['k'] ?? ''));
if (!$order) {
    json_out(['error' => 'not_found'], 404);
}
if (!empty($order['expired'])) {
    json_out(['error' => 'expired'], 410);
}
json_out([
    'status' => $order['status'],
    'text' => order_status_text($order['status'], $order['fulfillment']),
]);
