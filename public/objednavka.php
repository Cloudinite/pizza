<?php
require __DIR__ . '/app/bootstrap.php';

$code = strtoupper((string) ($_GET['code'] ?? ''));
$token = (string) ($_GET['k'] ?? '');
$order = order_find_public($code, $token);
if (!$order) {
    not_found();
}
header('Referrer-Policy: no-referrer');

$s = settings();

if (!empty($order['expired'])) {
    http_response_code(410);
    page_start(['title' => 'Odkaz vypršal – Pizza Slice Pezinok', 'noindex' => true]);
    ?>
<section class="wrap confirm">
  <div class="card empty">
    <?= item_icon('slice') ?>
    <h1>Odkaz na sledovanie vypršal</h1>
    <p>Stav objednávky sa dá sledovať <?= ORDER_LINK_HOURS ?> hodín od jej vytvorenia. Ak potrebuješ niečo k staršej objednávke, zavolaj nám.</p>
    <div class="card-actions">
      <a class="btn btn-primary" href="/menu">Nová objednávka</a>
      <?php if ($s['phone'] !== ''): ?><a class="btn btn-ghost" href="<?= e(phone_href($s['phone'])) ?>">Zavolať</a><?php endif; ?>
    </div>
  </div>
</section>
    <?php
    page_end();
    exit;
}

$status = $order['status'];
$delivery = $order['fulfillment'] === 'delivery';
$steps = order_steps($order['fulfillment']);
$keys = array_keys($steps);
$current = array_search($status, $keys, true);
$validUntil = date('j. n. H:i', strtotime($order['created_at']) + ORDER_LINK_HOURS * 3600);

page_start([
    'title' => 'Objednávka č. ' . $order['daily_no'] . ' – Pizza Slice Pezinok',
    'path' => '/objednavka/' . $order['code'],
    'noindex' => true,
    'no_track_bar' => true,
]);
?>
<section class="wrap confirm">
  <div class="card confirm-card" data-order data-code="<?= e($order['code']) ?>" data-token="<?= e($token) ?>" data-status="<?= e($status) ?>">
    <p class="eyebrow">Ďakujeme za objednávku</p>
    <h1>Objednávka č. <span class="order-no"><?= (int) $order['daily_no'] ?></span></h1>
    <p class="order-code">Kód #<?= e($order['code']) ?> · <?= e(date('j. n. Y H:i', strtotime($order['created_at']))) ?> · <?= $delivery ? 'Donáška' : 'Osobný odber' ?></p>

    <p class="status-text" data-status-text aria-live="polite"><?= e(order_status_text($status, $order['fulfillment'])) ?></p>
    <ol class="timeline<?= $status === 'cancelled' ? ' is-cancelled' : '' ?>" data-progress aria-label="Priebeh objednávky">
      <?php foreach ($keys as $i => $k):
          $cls = '';
          if ($status !== 'cancelled' && $current !== false) {
              $cls = $i < $current || $status === 'completed' ? 'is-done' : ($i === $current ? 'is-current' : '');
          } ?>
      <li class="<?= $cls ?>" data-step="<?= e($k) ?>"><span><?= e($steps[$k]) ?></span></li>
      <?php endforeach; ?>
    </ol>

    <dl class="kv">
      <div><dt><?= $delivery ? 'Doručenie' : 'Vyzdvihnutie' ?></dt><dd><?= $order['requested_time'] ? 'dnes o ' . e($order['requested_time']) : 'čo najskôr' ?></dd></div>
      <?php if (!$delivery): ?>
      <div><dt>Kde</dt><dd><?= e($s['site_name']) ?><?= address_line() !== '' ? ', ' . e(address_line()) : '' ?></dd></div>
      <?php endif; ?>
      <div><dt>Platba</dt><dd><?= e(PAYMENT_METHODS[$order['payment_method']] ?? '') ?></dd></div>
    </dl>

    <ul class="lines lines-ro">
      <?php foreach ($order['items'] as $it): ?>
      <li class="line">
        <div class="line-main">
          <p class="line-name"><?= (int) $it['qty'] ?>× <?= e($it['name']) ?></p>
          <?php if ($it['toppings'] !== ''): ?><p class="line-tops">+ <?= e($it['toppings']) ?></p><?php endif; ?>
        </div>
        <p class="line-total"><?= e(money((int) $it['line_cents'])) ?></p>
      </li>
      <?php endforeach; ?>
    </ul>
    <dl class="sum">
      <?php if ((int) $order['delivery_cents']): ?>
      <div><dt>Donáška</dt><dd><?= e(money((int) $order['delivery_cents'])) ?></dd></div>
      <?php endif; ?>
      <div class="sum-total"><dt>Spolu</dt><dd><?= e(money((int) $order['total_cents'])) ?></dd></div>
    </dl>

    <p class="fineprint">Stav sa na tejto stránke aktualizuje sám. Odkaz na sledovanie platí do <?= e($validUntil) ?> – nájdeš ho aj na úvodnej stránke webu.</p>
    <div class="card-actions">
      <a class="btn btn-ghost" href="/menu">Späť na menu</a>
      <?php if ($s['phone'] !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(phone_href($s['phone'])) ?>"><svg class="ico" aria-hidden="true"><use href="#i-phone"/></svg>Zavolať</a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_end();
