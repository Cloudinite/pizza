<?php
require __DIR__ . '/app/bootstrap.php';

csrf_token(); // issue the CSRF cookie before any output
$menu = menu_load();
$state = ordering_state();
$lines = cart_read();
$errors = [];
$form = ['name' => '', 'phone' => '', 'email' => '', 'fulfillment' => 'pickup', 'address' => '', 'time' => 'asap', 'payment' => 'cash', 'note' => ''];

if (is_post()) {
    $op = post_str('op', 120);

    // Cart edits (used when JavaScript is off; with JS the cookie is edited in the browser).
    if ($op === 'add') {
        $id = (int) ($_POST['id'] ?? 0);
        if (isset($menu['items'][$id])) {
            cart_write(cart_add($lines, $id, 1));
        }
        redirect('/menu#item-' . $id);
    }
    if (preg_match('/^(inc|dec|del)\|(\d{1,9}):([\d.]{0,80})$/', $op, $m)) {
        $key = $m[2] . ':' . $m[3];
        foreach ($lines as $i => $l) {
            if ($l['id'] . ':' . implode('.', $l['tops']) !== $key) {
                continue;
            }
            if ($m[1] === 'inc') {
                $lines[$i]['qty'] = min(PS_MAX_QTY, $l['qty'] + 1);
            } elseif ($m[1] === 'dec' && $l['qty'] > 1) {
                $lines[$i]['qty'] = $l['qty'] - 1;
            } else {
                unset($lines[$i]);
            }
        }
        cart_write(array_values($lines));
        $back = ($_POST['back'] ?? '') === 'menu' ? '/menu#item-' . (int) $m[2] : '/kosik';
        redirect($back);
    }

    if ($op === 'order') {
        $priced = cart_price($lines, $menu);
        if (!csrf_valid($_POST['csrf'] ?? null) || !same_origin_request()) {
            $errors['form'] = 'Platnosť formulára vypršala. Skontrolujte údaje a odošlite ho prosím znova.';
        } elseif (post_str('website') !== '' || !form_ts_ok($_POST['ts'] ?? null, 3)) {
            $errors['form'] = 'Formulár sa nepodarilo overiť. Počkajte chvíľu a skúste ho odoslať znova.';
        } elseif (!rate_allow('order', ip_ident(), 6, 900, false)) {
            $errors['form'] = 'Príliš veľa objednávok za krátky čas. Skúste to o pár minút alebo nám zavolajte.';
        } elseif ($priced['changed']) {
            cart_write(array_map(static fn ($l) => ['id' => $l['id'], 'qty' => $l['qty'], 'tops' => $l['tops']], $priced['lines']));
            $errors['form'] = 'Niektoré položky sa medzičasom zmenili alebo už nie sú dostupné. Skontrolujte košík a odošlite objednávku znova.';
        } elseif (cart_serialize(cart_parse(post_str('cart', 1500))) !== cart_serialize($lines)) {
            // the cart changed (e.g. in another tab) after this page was shown – never charge a total the customer did not see
            $errors['form'] = 'Košík sa medzičasom zmenil. Skontrolujte prosím súhrn a odošlite objednávku znova.';
        }
        [$clean, $fieldErrors] = order_validate($priced, $state);
        $form = array_merge($form, $clean);
        if (!$errors) {
            $errors = $fieldErrors;
        }
        if (!$errors) {
            $result = order_create($priced, $clean);
            rate_record('order', ip_ident());
            cart_write([]);
            // lets the site show a "track your order" bar for the next 24 hours
            set_cookie('ps_track', $result['code'] . '.' . $result['token'], ORDER_LINK_HOURS * 3600);
            redirect('/objednavka/' . $result['code'] . '?k=' . $result['token']);
        }
        $lines = cart_read();
    }
}

$priced = cart_price($lines, $menu);
if ($priced['changed'] && !is_post()) {
    cart_write(array_map(static fn ($l) => ['id' => $l['id'], 'qty' => $l['qty'], 'tops' => $l['tops']], $priced['lines']));
}
$deliveryOn = setting_bool('delivery_enabled');
$fee = setting_int('delivery_fee_cents');
$s = settings();

page_start([
    'title' => 'Košík a objednávka – Pizza Slice Pezinok',
    'path' => '/kosik',
    'nav' => 'cart',
    'noindex' => true,
]);

function field_error(array $errors, string $key): void
{
    if (isset($errors[$key])) {
        echo '<p class="field-err" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>';
    }
}

function err_attr(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}
?>
<section class="page-h page-h-sm">
  <div class="wrap">
    <h1>Košík a objednávka</h1>
  </div>
</section>

<div class="wrap">
<?php if (!$priced['lines']): ?>
  <div class="empty card">
    <?= item_icon('slice') ?>
    <h2>Košík je zatiaľ prázdny</h2>
    <p>Vyber si z našich kúskov pizze a nápojov.</p>
    <a class="btn btn-primary btn-lg" href="/menu">Prejsť na menu</a>
  </div>
<?php else: ?>
  <div class="checkout<?= $form['fulfillment'] === 'delivery' ? ' is-delivery' : '' ?>" data-checkout data-fee="<?= $deliveryOn ? $fee : 0 ?>">
    <section class="card co-cart" aria-labelledby="cart-h">
      <h2 id="cart-h">Tvoja objednávka</h2>
      <?php if ($priced['changed'] && !isset($errors['form'])): ?>
        <p class="notice notice-warn">Niektoré položky už nie sú dostupné, košík sme upravili.</p>
      <?php endif; ?>
      <form method="post" action="/kosik" data-cartform>
        <ul class="lines">
          <?php foreach ($priced['lines'] as $l):
              $key = $l['id'] . ':' . implode('.', $l['tops']); ?>
          <li class="line" data-line="<?= e($key) ?>">
            <div class="line-main">
              <p class="line-name"><?= e($l['name']) ?></p>
              <?php if ($l['top_names']): ?><p class="line-tops">+ <?= e(implode(', ', $l['top_names'])) ?></p><?php endif; ?>
              <p class="line-unit"><?= e(money($l['unit'])) ?> / ks</p>
            </div>
            <button class="line-del" type="submit" name="op" value="del|<?= e($key) ?>" aria-label="Odstrániť <?= e($l['name']) ?> z košíka">×</button>
            <div class="stepper">
              <button type="submit" name="op" value="dec|<?= e($key) ?>" aria-label="Ubrať jeden kus <?= e($l['name']) ?>">−</button>
              <output aria-live="polite" data-line-qty><?= $l['qty'] ?></output>
              <button type="submit" name="op" value="inc|<?= e($key) ?>" aria-label="Pridať jeden kus <?= e($l['name']) ?>">+</button>
            </div>
            <p class="line-total" data-line-total><?= e(money($l['line'])) ?></p>
          </li>
          <?php endforeach; ?>
        </ul>
      </form>
      <dl class="sum">
        <div><dt>Medzisúčet</dt><dd data-subtotal><?= e(money($priced['subtotal'])) ?></dd></div>
        <?php if ($deliveryOn): ?>
        <div class="sum-delivery"><dt>Donáška</dt><dd><?= e(money($fee)) ?></dd></div>
        <?php endif; ?>
        <div class="sum-total"><dt>Spolu</dt>
          <dd><span class="t-pickup" data-total-pickup><?= e(money($priced['subtotal'])) ?></span><?php if ($deliveryOn): ?><span class="t-delivery" data-total-delivery><?= e(money($priced['subtotal'] + $fee)) ?></span><?php endif; ?></dd>
        </div>
      </dl>
      <a class="link-add" href="/menu">+ Pridať ďalšie kúsky</a>
    </section>

    <section class="card co-form" aria-labelledby="form-h">
      <h2 id="form-h">Údaje k objednávke</h2>
      <?php if (!$state['ok']): ?>
        <p class="notice notice-warn" role="alert"><?= e($state['message']) ?></p>
      <?php elseif ($state['message'] !== ''): ?>
        <p class="notice"><?= e($state['message']) ?></p>
      <?php endif; ?>
      <?php if (isset($errors['form'])): ?>
        <p class="notice notice-err" role="alert"><?= e($errors['form']) ?></p>
      <?php elseif ($errors): ?>
        <p class="notice notice-err" role="alert">Skontrolujte prosím označené polia.</p>
      <?php endif; ?>

      <form method="post" action="/kosik" class="form" data-orderform>
        <input type="hidden" name="op" value="order">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="ts" value="<?= e(form_ts()) ?>">
        <input type="hidden" name="cart" value="<?= e(cart_serialize(array_map(static fn ($l) => ['id' => $l['id'], 'qty' => $l['qty'], 'tops' => $l['tops']], $priced['lines']))) ?>">
        <div class="hp" aria-hidden="true">
          <label for="f-website">Nevypĺňajte</label>
          <input id="f-website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <?php if ($deliveryOn): ?>
        <fieldset class="field">
          <legend>Spôsob prevzatia</legend>
          <div class="choices">
            <label class="choice"><input type="radio" name="fulfillment" value="pickup" id="f-pickup"<?= $form['fulfillment'] === 'pickup' ? ' checked' : '' ?>><span><b>Osobný odber</b><small><?= e(address_line() !== '' ? address_line() : 'na prevádzke') ?></small></span></label>
            <label class="choice"><input type="radio" name="fulfillment" value="delivery" id="f-delivery"<?= $form['fulfillment'] === 'delivery' ? ' checked' : '' ?>><span><b>Donáška · <?= e(money($fee)) ?></b><small><?= e($s['delivery_area']) ?>, min. <?= e(money(setting_int('delivery_min_cents'))) ?></small></span></label>
          </div>
          <?php field_error($errors, 'fulfillment'); ?>
        </fieldset>
        <div class="field addr">
          <label for="f-address">Adresa doručenia</label>
          <input class="input" id="f-address" name="address" type="text" maxlength="150" autocomplete="street-address" value="<?= e($form['address']) ?>"<?= err_attr($errors, 'address') ?>>
          <?php field_error($errors, 'address'); ?>
        </div>
        <?php endif; ?>

        <div class="field">
          <label for="f-time">Čas <?= $deliveryOn ? 'prevzatia' : 'vyzdvihnutia' ?></label>
          <select class="input" id="f-time" name="time"<?= err_attr($errors, 'time') ?><?= $state['ok'] ? '' : ' disabled' ?>>
            <?php if ($state['asap']): ?>
              <option value="asap"<?= $form['time'] === 'asap' ? ' selected' : '' ?>>Čo najskôr (cca <?= setting_int('prep_minutes') ?> min)</option>
            <?php endif; ?>
            <?php foreach ($state['slots'] as $slot): ?>
              <option value="<?= e($slot) ?>"<?= $form['time'] === $slot ? ' selected' : '' ?>>Dnes o <?= e($slot) ?></option>
            <?php endforeach; ?>
          </select>
          <?php field_error($errors, 'time'); ?>
        </div>

        <div class="field">
          <label for="f-name">Meno</label>
          <input class="input" id="f-name" name="name" type="text" required minlength="2" maxlength="60" autocomplete="name" value="<?= e($form['name']) ?>"<?= err_attr($errors, 'name') ?>>
          <?php field_error($errors, 'name'); ?>
        </div>
        <div class="field">
          <label for="f-phone">Telefón</label>
          <input class="input" id="f-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" inputmode="tel" placeholder="0901 234 567" value="<?= e($form['phone']) ?>"<?= err_attr($errors, 'phone') ?>>
          <?php field_error($errors, 'phone'); ?>
        </div>
        <div class="field">
          <label for="f-email">E-mail <small>(nepovinné)</small></label>
          <input class="input" id="f-email" name="email" type="email" maxlength="120" autocomplete="email" value="<?= e($form['email']) ?>"<?= err_attr($errors, 'email') ?>>
          <?php field_error($errors, 'email'); ?>
        </div>
        <div class="field">
          <label for="f-note">Poznámka <small>(nepovinné)</small></label>
          <textarea class="input" id="f-note" name="note" rows="2" maxlength="300" placeholder="Napr. nekrájať, bez cibule…"><?= e($form['note']) ?></textarea>
        </div>

        <fieldset class="field">
          <legend>Platba</legend>
          <div class="choices">
            <?php foreach (PAYMENT_METHODS as $k => $label): ?>
            <label class="choice"><input type="radio" name="payment" value="<?= e($k) ?>"<?= $form['payment'] === $k ? ' checked' : '' ?>><span><b><?= e($label) ?></b></span></label>
            <?php endforeach; ?>
            <label class="choice is-disabled"><input type="radio" name="payment" value="online" disabled><span><b>Online platba kartou</b><small>Pripravujeme</small></span></label>
          </div>
          <?php field_error($errors, 'payment'); ?>
        </fieldset>

        <button class="btn btn-primary btn-lg btn-block" type="submit" data-submit<?= $state['ok'] ? '' : ' disabled' ?>>
          Odoslať objednávku ·&nbsp;<span class="t-pickup" data-total-pickup><?= e(money($priced['subtotal'])) ?></span><?php if ($deliveryOn): ?><span class="t-delivery" data-total-delivery><?= e(money($priced['subtotal'] + $fee)) ?></span><?php endif; ?>
        </button>
        <p class="fineprint">Spojenie je šifrované (HTTPS). Platíš až pri prevzatí. Údaje použijeme len na vybavenie objednávky – viac v <a href="/ochrana-osobnych-udajov">zásadách ochrany osobných údajov</a>.</p>
      </form>
    </section>
  </div>
<?php endif; ?>
</div>
<script type="application/json" id="ps-data"><?= json_encode(menu_js_data($menu), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php page_end();
