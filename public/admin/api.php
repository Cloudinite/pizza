<?php
/** JSON endpoints used by the admin app (orders polling, status changes, quick toggles). */
require dirname(__DIR__) . '/app/admin_bootstrap.php';

header('X-Robots-Tag: noindex');
require_admin(true);
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

/** Unconfirmed orders (for alerts on every admin screen) + counters. */
function pending_summary(): array
{
    $rows = db()->query("SELECT id, daily_no, total_cents, item_count FROM orders WHERE status = 'new' ORDER BY id LIMIT 50")->fetchAll();
    $active = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('new','accepted','ready','delivering')")->fetchColumn();
    return [
        'new_orders' => array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'no' => (int) $r['daily_no'], 'total' => (int) $r['total_cents'], 'count' => (int) $r['item_count'],
        ], $rows),
        'counts' => ['new' => count($rows), 'active' => $active],
        'time' => date('H:i'),
    ];
}

if (!is_post() && ($action === 'orders' || $action === 'ping')) {
    if (random_int(1, 40) === 1) {
        orders_cleanup();
    }
    $out = pending_summary();
    if ($action === 'orders') {
        $view = in_array($_GET['view'] ?? '', ['active', 'done', 'history'], true) ? $_GET['view'] : 'active';
        $out += [
            'view' => $view,
            'orders' => orders_admin_list($view),
            'stats' => orders_today_stats(),
            'ordering' => setting_bool('ordering_enabled'),
        ];
    }
    json_out($out);
}

if (!is_post()) {
    json_out(['error' => 'method'], 405);
}
admin_require_csrf(true);

switch ($action) {
    case 'status':
        $ok = order_set_status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
        json_out(['ok' => $ok], $ok ? 200 : 400);

    case 'ordering':
        save_settings(['ordering_enabled' => ($_POST['enabled'] ?? '') === '1' ? '1' : '0']);
        json_out(['ok' => true, 'ordering' => setting_bool('ordering_enabled')]);

    case 'item_toggle':
        $st = db()->prepare('UPDATE menu_items SET is_available = ?, updated_at = ? WHERE id = ?');
        $st->execute([($_POST['on'] ?? '') === '1' ? 1 : 0, now_str(), (int) ($_POST['id'] ?? 0)]);
        json_out(['ok' => true]);

    case 'coupon_toggle':
        $st = db()->prepare('UPDATE coupons SET is_active = ? WHERE id = ?');
        $st->execute([($_POST['on'] ?? '') === '1' ? 1 : 0, (int) ($_POST['id'] ?? 0)]);
        json_out(['ok' => true]);

    case 'topping_toggle':
        $st = db()->prepare('UPDATE toppings SET is_available = ? WHERE id = ?');
        $st->execute([($_POST['on'] ?? '') === '1' ? 1 : 0, (int) ($_POST['id'] ?? 0)]);
        json_out(['ok' => true]);
}
json_out(['error' => 'unknown_action'], 400);
