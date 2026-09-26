<?php
defined('PS_APP') || exit;

const PS_MAX_LINES = 30;
const PS_MAX_QTY = 20;
const PS_MAX_ITEMS = 60;
const PS_MAX_TOPPINGS = 6;

/**
 * Loads categories, items and toppings.
 * $admin = false → public view: hidden categories dropped, unavailable specials dropped,
 * unavailable toppings dropped (other unavailable items stay visible as "sold out").
 */
function menu_load(bool $admin = false): array
{
    static $cache = [];
    $key = $admin ? 'admin' : 'public';
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $pdo = db();
    $cats = [];
    foreach ($pdo->query('SELECT * FROM categories ORDER BY sort_order, id') as $c) {
        if (!$admin && !(int) $c['is_visible']) {
            continue;
        }
        $c['items'] = [];
        $cats[(int) $c['id']] = $c;
    }
    $items = [];
    foreach ($pdo->query('SELECT * FROM menu_items ORDER BY sort_order, id') as $it) {
        $cid = (int) $it['category_id'];
        if (!isset($cats[$cid])) {
            continue;
        }
        if (!$admin && (int) $cats[$cid]['is_special'] && !(int) $it['is_available']) {
            continue;
        }
        $it['id'] = (int) $it['id'];
        $it['price_cents'] = (int) $it['price_cents'];
        $cats[$cid]['items'][] = $it;
        $items[$it['id']] = $it;
    }
    $tops = [];
    foreach ($pdo->query('SELECT * FROM toppings ORDER BY sort_order, id') as $t) {
        if (!$admin && !(int) $t['is_available']) {
            continue;
        }
        $t['id'] = (int) $t['id'];
        $t['price_cents'] = (int) $t['price_cents'];
        $tops[$t['id']] = $t;
    }
    return $cache[$key] = ['categories' => $cats, 'items' => $items, 'toppings' => $tops];
}

/** Items in visible "special" categories (for the homepage highlight). */
function menu_specials(array $menu): array
{
    $out = [];
    foreach ($menu['categories'] as $c) {
        if ((int) $c['is_special']) {
            array_push($out, ...$c['items']);
        }
    }
    return $out;
}

/** Compact data the browser needs to show prices in the cart and toppings dialog. */
function menu_js_data(array $menu): array
{
    $items = [];
    foreach ($menu['items'] as $id => $it) {
        $items[$id] = ['n' => $it['name'], 'p' => $it['price_cents'], 't' => (int) $it['allow_toppings'], 'a' => (int) $it['is_available']];
    }
    $tops = [];
    foreach ($menu['toppings'] as $id => $t) {
        $tops[$id] = ['n' => $t['name'], 'p' => $t['price_cents']];
    }
    return ['items' => $items, 'toppings' => $tops, 'maxTop' => PS_MAX_TOPPINGS, 'maxQty' => PS_MAX_QTY, 'maxItems' => PS_MAX_ITEMS];
}

/* ---------- Cart (stored client-side in the ps_cart cookie: "id:qty:t1.t2|id:qty") ---------- */

/** @return array<int, array{id:int, qty:int, tops:int[]}> */
function cart_parse(string $raw): array
{
    $lines = [];
    foreach (explode('|', substr($raw, 0, 1500)) as $chunk) {
        if (!preg_match('/^(\d{1,9}):(\d{1,2})(?::([\d.]{0,80}))?$/', $chunk, $m)) {
            continue;
        }
        $qty = (int) $m[2];
        if ($qty < 1) {
            continue;
        }
        $tops = [];
        foreach (explode('.', $m[3] ?? '') as $t) {
            if ($t !== '' && ctype_digit($t)) {
                $tops[(int) $t] = true;
            }
        }
        $tops = array_keys($tops);
        sort($tops);
        $lines[] = ['id' => (int) $m[1], 'qty' => min($qty, PS_MAX_QTY), 'tops' => array_slice($tops, 0, PS_MAX_TOPPINGS)];
        if (count($lines) >= PS_MAX_LINES) {
            break;
        }
    }
    return $lines;
}

function cart_serialize(array $lines): string
{
    $parts = [];
    foreach ($lines as $l) {
        $parts[] = $l['id'] . ':' . $l['qty'] . ($l['tops'] ? ':' . implode('.', $l['tops']) : '');
    }
    return implode('|', $parts);
}

function cart_read(): array
{
    $raw = $_COOKIE['ps_cart'] ?? '';
    return is_string($raw) ? cart_parse($raw) : [];
}

function cart_write(array $lines): void
{
    $value = cart_serialize($lines);
    $_COOKIE['ps_cart'] = $value;
    set_cookie('ps_cart', $value, $value === '' ? -1 : 172800, ['httponly' => false]);
}

/** Adds a line (merging with an identical one) and returns the new lines. */
function cart_add(array $lines, int $id, int $qty, array $tops = []): array
{
    sort($tops);
    foreach ($lines as &$l) {
        if ($l['id'] === $id && $l['tops'] === $tops) {
            $l['qty'] = min(PS_MAX_QTY, $l['qty'] + $qty);
            return $lines;
        }
    }
    unset($l);
    if (count($lines) < PS_MAX_LINES) {
        $lines[] = ['id' => $id, 'qty' => min(PS_MAX_QTY, $qty), 'tops' => $tops];
    }
    return $lines;
}

/**
 * Prices the cart against the live menu (never trusts client prices).
 * Lines that are no longer orderable are dropped and 'changed' is set.
 */
function cart_price(array $lines, array $menu): array
{
    $out = [];
    $changed = false;
    $subtotal = 0;
    $count = 0;
    $seen = [];
    foreach ($lines as $l) {
        $item = $menu['items'][$l['id']] ?? null;
        if (!$item || !(int) $item['is_available']) {
            $changed = true;
            continue;
        }
        $tops = $l['tops'];
        if ($tops && !(int) $item['allow_toppings']) {
            $tops = [];
            $changed = true;
        }
        $topNames = [];
        $unit = $item['price_cents'];
        foreach ($tops as $i => $tid) {
            if (!isset($menu['toppings'][$tid])) {
                unset($tops[$i]);
                $changed = true;
                continue;
            }
            $topNames[] = $menu['toppings'][$tid]['name'];
            $unit += $menu['toppings'][$tid]['price_cents'];
        }
        $tops = array_values($tops);
        $key = $l['id'] . ':' . implode('.', $tops);
        $qty = $l['qty'];
        if ($count + $qty > PS_MAX_ITEMS) {
            $qty = PS_MAX_ITEMS - $count;
            $changed = true;
        }
        if ($qty < 1 || isset($seen[$key])) {
            $changed = true;
            continue;
        }
        $seen[$key] = true;
        $out[] = [
            'id' => $l['id'], 'qty' => $qty, 'tops' => $tops, 'name' => $item['name'],
            'top_names' => $topNames, 'unit' => $unit, 'line' => $unit * $qty,
        ];
        $subtotal += $unit * $qty;
        $count += $qty;
    }
    return ['lines' => $out, 'subtotal' => $subtotal, 'count' => $count, 'changed' => $changed];
}

/** Quick count for the header badge (no DB needed). */
function cart_count_quick(): int
{
    $n = 0;
    foreach (cart_read() as $l) {
        $n += $l['qty'];
    }
    return $n;
}
