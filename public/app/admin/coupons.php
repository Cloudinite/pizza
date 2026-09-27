<?php
defined('PS_APP') || exit;

require_admin();
$pdo = db();
$errors = [];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$edit = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $st->execute([$id]);
    $edit = $st->fetch() ?: null;
    if (!$edit) {
        redirect(admin_url('kupony'));
    }
}

$v = $edit ?? [
    'code' => '', 'type' => 'percent', 'value' => 10, 'min_order_cents' => 0, 'max_uses' => null,
    'valid_from' => null, 'valid_to' => null, 'once_per_customer' => 0, 'is_active' => 1, 'note' => '',
];
$valueStr = $v['type'] === 'amount' ? number_format((int) $v['value'] / 100, 2, ',', '') : (string) (int) $v['value'];
$minStr = (int) $v['min_order_cents'] ? number_format((int) $v['min_order_cents'] / 100, 2, ',', '') : '';

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

if (is_post()) {
    admin_require_csrf();
    $action = post_str('action', 20);

    if ($action === 'delete' && $edit) {
        $pdo->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
        redirect(admin_url('kupony') . '?ok=deleted');
    }

    if ($action === 'save') {
        $v['code'] = coupon_normalize(post_str('code', 40));
        $v['type'] = isset(COUPON_TYPES[$_POST['type'] ?? '']) ? $_POST['type'] : 'percent';
        $valueStr = post_str('value', 20);
        $minStr = post_str('min_order', 20);
        $maxUses = post_str('max_uses', 10);
        $v['valid_from'] = post_str('valid_from', 10) ?: null;
        $v['valid_to'] = post_str('valid_to', 10) ?: null;
        $v['once_per_customer'] = isset($_POST['once_per_customer']) ? 1 : 0;
        $v['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $v['note'] = post_str('note', 120);

        if (!preg_match('/^[A-Z0-9_-]{3,32}$/', $v['code'])) {
            $errors[] = 'Kód: 3–32 znakov – písmená bez diakritiky, čísla, pomlčka alebo podčiarkovník.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM coupons WHERE code = ? AND id <> ?');
            $dup->execute([$v['code'], $id]);
            if ($dup->fetchColumn()) {
                $errors[] = 'Kupón s kódom ' . $v['code'] . ' už existuje.';
            }
        }
        if ($v['type'] === 'percent') {
            $pct = ctype_digit($valueStr) ? (int) $valueStr : 0;
            if ($pct < 1 || $pct > 100) {
                $errors[] = 'Percentuálna zľava musí byť 1 až 100 %.';
            }
            $v['value'] = $pct;
        } elseif ($v['type'] === 'amount') {
            $cents = parse_money($valueStr);
            if ($cents === null || $cents < 1) {
                $errors[] = 'Zadajte sumu zľavy v eurách, napr. 2,00.';
            }
            $v['value'] = $cents ?? 0;
        } else {
            $v['value'] = 0;
        }
        $min = $minStr === '' ? 0 : parse_money($minStr);
        if ($min === null) {
            $errors[] = 'Minimálna objednávka musí byť suma v eurách, napr. 10,00.';
        }
        $v['min_order_cents'] = $min ?? 0;
        if ($maxUses === '') {
            $v['max_uses'] = null;
        } elseif (ctype_digit($maxUses) && (int) $maxUses >= 1) {
            $v['max_uses'] = (int) $maxUses;
        } else {
            $errors[] = 'Počet použití musí byť celé číslo (alebo nechajte prázdne = neobmedzene).';
        }
        foreach (['valid_from', 'valid_to'] as $k) {
            if ($v[$k] !== null && !valid_date($v[$k])) {
                $errors[] = 'Neplatný dátum.';
                $v[$k] = null;
            }
        }
        if ($v['valid_from'] && $v['valid_to'] && $v['valid_to'] < $v['valid_from']) {
            $errors[] = 'Dátum „platí do“ musí byť po dátume „platí od“.';
        }

        if (!$errors) {
            $params = [$v['code'], $v['type'], $v['value'], $v['min_order_cents'], $v['max_uses'], $v['valid_from'], $v['valid_to'],
                $v['once_per_customer'], $v['is_active'], $v['note']];
            if ($edit) {
                $pdo->prepare('UPDATE coupons SET code = ?, type = ?, value = ?, min_order_cents = ?, max_uses = ?, valid_from = ?, valid_to = ?,
                                once_per_customer = ?, is_active = ?, note = ? WHERE id = ?')->execute([...$params, $id]);
            } else {
                $pdo->prepare('INSERT INTO coupons (code, type, value, min_order_cents, max_uses, valid_from, valid_to, once_per_customer,
                                is_active, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([...$params, now_str()]);
            }
            redirect(admin_url('kupony') . '?ok=' . ($edit ? 'saved' : 'added'));
        }
    }
}

$coupons = $pdo->query('SELECT * FROM coupons ORDER BY is_active DESC, id DESC')->fetchAll();
admin_start('Zľavové kupóny', 'settings');
?>
<a class="a-back" href="<?= e(admin_url('nastavenia')) ?>">← Nastavenia</a>

<section class="a-card">
  <div class="a-card-h">
    <h2>Zľavové kupóny</h2>
    <?php if ($edit): ?><a class="a-btn a-btn-sm a-btn-primary" href="<?= e(admin_url('kupony')) ?>#form">+ Nový</a><?php endif; ?>
  </div>
  <p class="a-muted">Zákazník zadá kód v košíku. Zľava sa počíta z ceny jedla, nie z donášky. Prepínačom kupón dočasne vypnete.</p>
  <?php if (!$coupons): ?>
    <p class="a-empty">Zatiaľ žiadne kupóny. Vytvorte prvý nižšie.</p>
  <?php else: ?>
  <ul class="a-list">
    <?php foreach ($coupons as $c):
        $expired = $c['valid_to'] && $c['valid_to'] < date('Y-m-d');
        $usedUp = $c['max_uses'] !== null && (int) $c['used_count'] >= (int) $c['max_uses'];
        $info = array_filter([coupon_label($c), coupon_conditions($c),
            'použité ' . (int) $c['used_count'] . ($c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '') . '×']); ?>
    <li class="a-row<?= (int) $c['is_active'] ? '' : ' is-off' ?><?= $edit && (int) $edit['id'] === (int) $c['id'] ? ' is-editing' : '' ?>">
      <a class="a-row-main" href="<?= e(admin_url('kupony')) ?>?id=<?= (int) $c['id'] ?>#form">
        <b><span class="a-code"><?= e($c['code']) ?></span>
          <?php if ($expired): ?><span class="a-tag a-tag-err">vypršal</span><?php elseif ($usedUp): ?><span class="a-tag a-tag-err">vyčerpaný</span><?php endif; ?></b>
        <span><?= e(implode(' · ', $info)) ?><?= $c['note'] !== '' ? ' · ' . e($c['note']) : '' ?></span>
      </a>
      <label class="a-switch" title="Aktívny"><input type="checkbox" data-toggle="coupon" data-id="<?= (int) $c['id'] ?>"<?= (int) $c['is_active'] ? ' checked' : '' ?>><span class="a-switch-ui" aria-hidden="true"></span><span class="sr">Aktívny: <?= e($c['code']) ?></span></label>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>

<form method="post" class="a-card a-form" id="form" action="<?= e(admin_url('kupony')) ?><?= $edit ? '?id=' . (int) $edit['id'] : '' ?>">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
  <h2><?= $edit ? 'Upraviť kupón ' . e($edit['code']) : 'Nový kupón' ?></h2>
  <?php foreach ($errors as $err): ?><p class="a-err" role="alert"><?= e($err) ?></p><?php endforeach; ?>

  <div class="a-field"><label for="cp-code">Kód, ktorý zákazník zadá</label>
    <span class="a-inline">
      <input class="a-input a-code-input" id="cp-code" name="code" required maxlength="32" value="<?= e($v['code']) ?>" placeholder="napr. PIZZA10" autocapitalize="characters" autocomplete="off" spellcheck="false" data-code-input>
      <button class="a-btn a-btn-sm" type="button" data-gen-code>Vygenerovať</button>
    </span>
  </div>
  <div class="a-grid2">
    <label class="a-field"><span>Typ zľavy</span>
      <select class="a-input" name="type" data-coupon-type>
        <?php foreach (COUPON_TYPES as $k => $label): ?>
          <option value="<?= $k ?>"<?= $v['type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="a-field" data-coupon-value<?= $v['type'] === 'free_delivery' ? ' hidden' : '' ?>><span data-value-label><?= $v['type'] === 'amount' ? 'Zľava (€)' : 'Zľava (%)' ?></span>
      <input class="a-input" name="value" inputmode="decimal" value="<?= e($valueStr) ?>" placeholder="<?= $v['type'] === 'amount' ? '2,00' : '10' ?>">
    </label>
  </div>
  <div class="a-grid2">
    <label class="a-field"><span>Minimálna objednávka (€)</span>
      <input class="a-input" name="min_order" inputmode="decimal" value="<?= e($minStr) ?>" placeholder="bez minima">
    </label>
    <label class="a-field"><span>Max. počet použití</span>
      <input class="a-input" name="max_uses" inputmode="numeric" value="<?= e($v['max_uses'] === null ? '' : (string) $v['max_uses']) ?>" placeholder="neobmedzene">
    </label>
  </div>
  <div class="a-grid2">
    <label class="a-field"><span>Platí od</span><input class="a-input" type="date" name="valid_from" value="<?= e((string) $v['valid_from']) ?>"></label>
    <label class="a-field"><span>Platí do</span><input class="a-input" type="date" name="valid_to" value="<?= e((string) $v['valid_to']) ?>"></label>
  </div>
  <label class="a-check"><input type="checkbox" name="once_per_customer"<?= (int) $v['once_per_customer'] ? ' checked' : '' ?>> Každý zákazník (telefónne číslo) ho môže použiť len raz</label>
  <label class="a-check"><input type="checkbox" name="is_active"<?= (int) $v['is_active'] ? ' checked' : '' ?>> Aktívny</label>
  <label class="a-field"><span>Poznámka pre vás <small>(zákazník ju nevidí)</small></span>
    <input class="a-input" name="note" maxlength="120" value="<?= e($v['note']) ?>" placeholder="napr. akcia na Instagrame">
  </label>
  <div class="a-actions a-actions-sticky">
    <button class="a-btn a-btn-primary a-btn-block" type="submit"><?= $edit ? 'Uložiť kupón' : 'Vytvoriť kupón' ?></button>
  </div>
</form>

<?php if ($edit): ?>
<form method="post" class="a-danger-zone" action="<?= e(admin_url('kupony')) ?>?id=<?= (int) $edit['id'] ?>" data-confirm="Zmazať kupón <?= e($edit['code']) ?>? Staré objednávky si kód ponechajú.">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
  <input type="hidden" name="action" value="delete">
  <button class="a-btn a-btn-danger a-btn-block" type="submit">Zmazať kupón</button>
</form>
<?php endif; ?>
<?php admin_end();
