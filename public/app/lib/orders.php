<?php
defined('PS_APP') || exit;

const ORDER_STATUSES = [
    'new'        => 'Nová',
    'accepted'   => 'Pripravujeme',
    'ready'      => 'Pripravená',
    'delivering' => 'Na ceste',
    'completed'  => 'Vybavená',
    'cancelled'  => 'Zrušená',
];
const ORDER_ACTIVE = ['new', 'accepted', 'ready', 'delivering'];
/** The customer's tracking link works this long after ordering. */
const ORDER_LINK_HOURS = 24;
const PAYMENT_METHODS = [
    'cash' => 'Hotovosť pri prevzatí',
    'card' => 'Kartou pri prevzatí',
];

/**
 * Validates the checkout form against the priced cart and current opening state.
 * @return array{0: array, 1: array<string,string>} [clean data, errors keyed by field]
 */
function order_validate(array $priced, array $state): array
{
    $errors = [];
    $d = [
        'name'        => post_str('name', 60),
        'phone'       => post_str('phone', 30),
        'email'       => post_str('email', 120),
        'fulfillment' => post_str('fulfillment', 10) === 'delivery' ? 'delivery' : 'pickup',
        'address'     => post_str('address', 150),
        'time'        => post_str('time', 5),
        'payment'     => post_str('payment', 10),
        'note'        => post_str('note', 300),
    ];

    if (!$state['ok']) {
        $errors['form'] = $state['message'];
    }
    if (!$priced['lines']) {
        $errors['form'] = 'Košík je prázdny.';
    }
    if (mb_strlen($d['name']) < 2) {
        $errors['name'] = 'Zadajte svoje meno.';
    }
    $phone = preg_replace('/[\s\-\/().]/', '', $d['phone']) ?? '';
    if (str_starts_with($phone, '00')) {
        $phone = '+' . substr($phone, 2);
    }
    if (!preg_match('/^\+?\d{9,15}$/', $phone)) {
        $errors['phone'] = 'Zadajte platné telefónne číslo, napr. 0901 234 567.';
    }
    $d['phone'] = $phone;
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'E-mail nie je v správnom tvare.';
    }
    if ($d['fulfillment'] === 'delivery') {
        if (!setting_bool('delivery_enabled')) {
            $d['fulfillment'] = 'pickup';
        } else {
            if (mb_strlen($d['address']) < 5) {
                $errors['address'] = 'Zadajte adresu doručenia.';
            }
            $min = setting_int('delivery_min_cents');
            if ($priced['subtotal'] < $min) {
                $errors['fulfillment'] = 'Minimálna objednávka pri donáške je ' . money($min) . '.';
            }
        }
    }
    if ($d['fulfillment'] === 'pickup') {
        $d['address'] = '';
    }
    if ($d['time'] === 'asap' || $d['time'] === '') {
        $d['time'] = 'asap';
        if ($state['ok'] && !$state['asap']) {
            $errors['time'] = 'Vyberte čas vyzdvihnutia.';
        }
    } elseif (!in_array($d['time'], $state['slots'], true)) {
        $errors['time'] = 'Vybraný čas už nie je dostupný, vyberte iný.';
    }
    if (!isset(PAYMENT_METHODS[$d['payment']])) {
        $errors['payment'] = 'Vyberte spôsob platby.';
    }
    return [$d, $errors];
}

/**
 * Inserts the order in a transaction (and uses up the coupon, if one applies).
 * @return array{code:string, token:string, daily_no:int}
 * @throws CouponException when the coupon ran out in the meantime
 */
function order_create(array $priced, array $d, ?array $coupon = null): array
{
    $pdo = db();
    $delivery = $d['fulfillment'] === 'delivery' ? setting_int('delivery_fee_cents') : 0;
    $discount = coupon_discount($coupon, $priced['subtotal'], $d['fulfillment'], $delivery);
    if ($discount <= 0) {
        $coupon = null; // not applicable to this order (e.g. below minimum) → not recorded, not used up
    }
    $total = max(0, $priced['subtotal'] - $discount + $delivery);
    $customer = encrypt_str(json_encode([
        'name' => $d['name'], 'phone' => $d['phone'], 'email' => $d['email'],
        'address' => $d['address'], 'note' => $d['note'],
    ], JSON_UNESCAPED_UNICODE));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $token = bin2hex(random_bytes(16));
        $now = now_str();
        $today = date('Y-m-d');
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT COALESCE(MAX(daily_no), 0) + 1 FROM orders WHERE order_date = ? FOR UPDATE');
            $st->execute([$today]);
            $dailyNo = (int) $st->fetchColumn();

            $pdo->prepare(
                'INSERT INTO orders (code, view_token_hash, order_date, daily_no, status, fulfillment, requested_time,
                    payment_method, customer_enc, item_count, subtotal_cents, delivery_cents, coupon_code, discount_cents,
                    total_cents, created_at, updated_at)
                 VALUES (?, ?, ?, ?, \'new\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $code, hash('sha256', $token), $today, $dailyNo, $d['fulfillment'],
                $d['time'] === 'asap' ? null : $d['time'], $d['payment'], $customer,
                $priced['count'], $priced['subtotal'], $delivery, $coupon['code'] ?? null, $discount, $total, $now, $now,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            if ($coupon) {
                // atomic: never exceeds max_uses even if two orders arrive at the same moment
                $use = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1
                    WHERE id = ? AND is_active = 1 AND (max_uses IS NULL OR used_count < max_uses)');
                $use->execute([(int) $coupon['id']]);
                if ($use->rowCount() !== 1) {
                    $pdo->rollBack();
                    throw new CouponException('Tento zľavový kód bol medzičasom vyčerpaný.');
                }
                $pdo->prepare('INSERT INTO coupon_uses (coupon_id, phone_hash, order_id, created_at) VALUES (?, ?, ?, ?)')
                    ->execute([(int) $coupon['id'], coupon_phone_hash($d['phone']), $orderId, $now]);
            }
            $ins = $pdo->prepare(
                'INSERT INTO order_items (order_id, item_id, name, toppings, unit_cents, qty, line_cents) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($priced['lines'] as $l) {
                $ins->execute([$orderId, $l['id'], $l['name'], implode(', ', $l['top_names']), $l['unit'], $l['qty'], $l['line']]);
            }
            $pdo->commit();
            return ['code' => $code, 'token' => $token, 'daily_no' => $dailyNo];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($e->errorInfo[1] ?? 0) !== 1062) { // retry only on duplicate key (code / daily number race)
                throw $e;
            }
        }
    }
    throw new RuntimeException('Could not allocate order code');
}

/**
 * Customer-side lookup; requires the secret token from the confirmation link.
 * Returns null for unknown/invalid links and ['expired' => true] once the link is older than ORDER_LINK_HOURS.
 */
function order_find_public(string $code, string $token): ?array
{
    if (!preg_match('/^[A-Z0-9]{6}$/', $code) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM orders WHERE code = ?');
    $st->execute([$code]);
    $o = $st->fetch();
    if (!$o || !hash_equals($o['view_token_hash'], hash('sha256', $token))) {
        return null;
    }
    if (strtotime($o['created_at']) < time() - ORDER_LINK_HOURS * 3600) {
        return ['expired' => true];
    }
    $o['items'] = order_items([(int) $o['id']])[(int) $o['id']] ?? [];
    return $o;
}

/** Steps shown on the customer's tracking page (keys are statuses). */
function order_steps(string $fulfillment): array
{
    return $fulfillment === 'delivery'
        ? ['new' => 'Prijatá', 'accepted' => 'Pripravujeme', 'ready' => 'Pripravená', 'delivering' => 'Na ceste', 'completed' => 'Doručená']
        : ['new' => 'Prijatá', 'accepted' => 'Pripravujeme', 'ready' => 'Na vyzdvihnutie', 'completed' => 'Vyzdvihnutá'];
}

/** @return array<int, array> order_id → items */
function order_items(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $st->execute(array_values($ids));
    $out = [];
    foreach ($st as $row) {
        $out[(int) $row['order_id']][] = $row;
    }
    return $out;
}

/** Orders for the admin app, with decrypted customer details. */
function orders_admin_list(string $view): array
{
    $pdo = db();
    if ($view === 'done') {
        $st = $pdo->prepare("SELECT * FROM orders WHERE status IN ('completed','cancelled') AND order_date = ? ORDER BY updated_at DESC LIMIT 150");
        $st->execute([date('Y-m-d')]);
    } elseif ($view === 'history') {
        $st = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 200');
    } else {
        $st = $pdo->query("SELECT * FROM orders WHERE status IN ('new','accepted','ready','delivering') ORDER BY id ASC LIMIT 150");
    }
    $orders = $st->fetchAll();
    $items = order_items(array_map(static fn ($o) => (int) $o['id'], $orders));
    $out = [];
    foreach ($orders as $o) {
        $c = json_decode((string) decrypt_str($o['customer_enc']), true) ?: [];
        $out[] = [
            'id' => (int) $o['id'],
            'code' => $o['code'],
            'no' => (int) $o['daily_no'],
            'date' => $o['order_date'],
            'status' => $o['status'],
            'fulfillment' => $o['fulfillment'],
            'time' => $o['requested_time'],
            'payment' => PAYMENT_METHODS[$o['payment_method']] ?? $o['payment_method'],
            'total' => (int) $o['total_cents'],
            'delivery' => (int) $o['delivery_cents'],
            'coupon' => $o['coupon_code'] ?? null,
            'discount' => (int) ($o['discount_cents'] ?? 0),
            'created' => $o['created_at'],
            'customer' => [
                'name' => $c['name'] ?? '(anonymizované)',
                'phone' => $c['phone'] ?? '',
                'email' => $c['email'] ?? '',
                'address' => $c['address'] ?? '',
                'note' => $c['note'] ?? '',
            ],
            'items' => array_map(static fn ($i) => [
                'name' => $i['name'], 'qty' => (int) $i['qty'], 'toppings' => $i['toppings'], 'line' => (int) $i['line_cents'],
            ], $items[(int) $o['id']] ?? []),
        ];
    }
    return $out;
}

function orders_today_stats(): array
{
    $st = db()->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(total_cents), 0) AS total FROM orders WHERE order_date = ? AND status <> 'cancelled'");
    $st->execute([date('Y-m-d')]);
    $r = $st->fetch();
    return ['count' => (int) $r['n'], 'total' => (int) $r['total']];
}

function order_set_status(int $id, string $status): bool
{
    if (!isset(ORDER_STATUSES[$status])) {
        return false;
    }
    $st = db()->prepare('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?');
    $st->execute([$status, now_str(), $id]);
    return $st->rowCount() > 0;
}

/** GDPR retention: wipe customer details from old orders, drop stale sessions. Cheap; run occasionally. */
function orders_cleanup(): void
{
    $days = max(7, setting_int('retention_days'));
    $pdo = db();
    $pdo->prepare('UPDATE orders SET customer_enc = NULL, anonymized_at = ? WHERE customer_enc IS NOT NULL AND created_at < ?')
        ->execute([now_str(), date('Y-m-d H:i:s', time() - $days * 86400)]);
    $pdo->prepare('DELETE FROM admin_sessions WHERE expires_at < ?')->execute([now_str()]);
    $pdo->prepare('DELETE FROM coupon_uses WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - $days * 86400)]);
    $pdo->prepare('DELETE FROM rate_limits WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

function order_status_text(string $status, string $fulfillment): string
{
    return match ($status) {
        'new' => 'Objednávku sme prijali a čaká na potvrdenie prevádzkou.',
        'accepted' => 'Potvrdené! Tvoju objednávku práve pripravujeme.',
        'ready' => $fulfillment === 'delivery' ? 'Pizza je hotová a čoskoro vyrazí k tebe.' : 'Hotovo! Objednávka je pripravená na vyzdvihnutie.',
        'delivering' => 'Kuriér je na ceste k tebe. Maj pripravený telefón.',
        'completed' => $fulfillment === 'delivery' ? 'Doručené. Dobrú chuť!' : 'Vyzdvihnuté. Dobrú chuť!',
        'cancelled' => 'Objednávka bola zrušená. Ak ide o omyl, zavolaj nám prosím.',
        default => '',
    };
}
