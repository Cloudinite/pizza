<?php
defined('PS_APP') || exit;

/*
 * Discount coupons.
 *   percent        value = 1–100 (% off the food, not the delivery fee)
 *   amount         value = cents off (never more than the food subtotal)
 *   free_delivery  delivery fee becomes 0 (only matters for delivery orders)
 * Optional rules: minimum order, total number of uses, valid from/to dates, once per phone number.
 * The browser only shows a preview; the server re-checks everything when the order is placed.
 */

const COUPON_TYPES = [
    'percent'       => 'Percentuálna zľava',
    'amount'        => 'Zľava v €',
    'free_delivery' => 'Donáška zdarma',
];
const COUPON_COOKIE = 'ps_coupon';

/** Thrown by order_create() when a limited coupon ran out between checking and saving. */
class CouponException extends RuntimeException
{
}

function coupon_normalize(string $code): string
{
    return strtoupper(preg_replace('/\s+/', '', $code) ?? '');
}

function coupon_find(string $code): ?array
{
    $code = coupon_normalize($code);
    if (!preg_match('/^[A-Z0-9_-]{3,32}$/', $code)) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM coupons WHERE code = ?');
    $st->execute([$code]);
    $c = $st->fetch();
    return $c ?: null;
}

/** Short customer-facing label: "−10 %", "−2,00 €", "Donáška zdarma". */
function coupon_label(array $c): string
{
    return match ($c['type']) {
        'percent' => '−' . (int) $c['value'] . ' %',
        'amount' => '−' . money((int) $c['value']),
        default => 'Donáška zdarma',
    };
}

/** Conditions summary for the admin list / customer hint. */
function coupon_conditions(array $c): string
{
    $parts = [];
    if ((int) $c['min_order_cents'] > 0) {
        $parts[] = 'od ' . money((int) $c['min_order_cents']);
    }
    if ($c['valid_from']) {
        $parts[] = 'od ' . date('j. n. Y', strtotime($c['valid_from']));
    }
    if ($c['valid_to']) {
        $parts[] = 'do ' . date('j. n. Y', strtotime($c['valid_to']));
    }
    if ((int) $c['once_per_customer']) {
        $parts[] = 'raz na zákazníka';
    }
    return implode(' · ', $parts);
}

/**
 * Can this coupon be used at all right now (ignoring the cart)?
 * @return string '' when usable, otherwise a customer-facing reason
 */
function coupon_unusable_reason(?array $c): string
{
    if (!$c || !(int) $c['is_active']) {
        return 'Tento zľavový kód neexistuje alebo už neplatí.';
    }
    $today = date('Y-m-d');
    if ($c['valid_from'] && $today < $c['valid_from']) {
        return 'Tento kód platí až od ' . date('j. n. Y', strtotime($c['valid_from'])) . '.';
    }
    if ($c['valid_to'] && $today > $c['valid_to']) {
        return 'Platnosť tohto kódu už skončila.';
    }
    if ($c['max_uses'] !== null && (int) $c['used_count'] >= (int) $c['max_uses']) {
        return 'Tento kód už bol vyčerpaný.';
    }
    return '';
}

/** Discount in cents for a cart subtotal. Integer maths so PHP and the browser agree to the cent. */
function coupon_discount(?array $c, int $subtotal, string $fulfillment, int $deliveryFee): int
{
    if (!$c || $subtotal < (int) $c['min_order_cents']) {
        return 0;
    }
    return match ($c['type']) {
        'percent' => intdiv($subtotal * min(100, (int) $c['value']) + 50, 100),
        'amount' => min((int) $c['value'], $subtotal),
        'free_delivery' => $fulfillment === 'delivery' ? $deliveryFee : 0,
        default => 0,
    };
}

/** Minimal data the checkout script needs to preview the discount live. */
function coupon_js(?array $c): ?array
{
    if (!$c) {
        return null;
    }
    return ['code' => $c['code'], 'type' => $c['type'], 'value' => (int) $c['value'], 'min' => (int) $c['min_order_cents'], 'label' => coupon_label($c)];
}

/** Coupon the visitor applied (cookie), if it still exists. */
function coupon_from_cookie(): ?array
{
    $code = $_COOKIE[COUPON_COOKIE] ?? '';
    return is_string($code) && $code !== '' ? coupon_find($code) : null;
}

function coupon_cookie_set(?string $code): void
{
    set_cookie(COUPON_COOKIE, $code ?? '', $code ? 86400 : -1, ['samesite' => 'Lax']);
    $_COOKIE[COUPON_COOKIE] = $code ?? '';
}

/** Same person → same hash, whether they type 0901 234 567 or +421 901 234 567. */
function coupon_phone_hash(string $phone): string
{
    $p = preg_replace('/[^\d+]/', '', $phone) ?? '';
    if (str_starts_with($p, '00')) {
        $p = '+' . substr($p, 2);
    } elseif (preg_match('/^0\d{9}$/', $p)) {
        $p = '+421' . substr($p, 1);
    }
    return hmac('phone|' . $p);
}

function coupon_used_by_phone(int $couponId, string $phone): bool
{
    $st = db()->prepare('SELECT 1 FROM coupon_uses WHERE coupon_id = ? AND phone_hash = ? LIMIT 1');
    $st->execute([$couponId, coupon_phone_hash($phone)]);
    return (bool) $st->fetchColumn();
}

/** Totals for both pickup and delivery, so the checkout can switch between them without a reload. */
function checkout_totals(int $subtotal, ?array $coupon): array
{
    $fee = setting_bool('delivery_enabled') ? setting_int('delivery_fee_cents') : 0;
    $out = [];
    foreach (['pickup' => 0, 'delivery' => $fee] as $mode => $modeFee) {
        $discount = coupon_discount($coupon, $subtotal, $mode, $modeFee);
        $out[$mode] = ['fee' => $modeFee, 'discount' => $discount, 'total' => max(0, $subtotal - $discount + $modeFee)];
    }
    return $out;
}
