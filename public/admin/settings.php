<?php
require dirname(__DIR__) . '/app/admin_bootstrap.php';

$user = require_admin();
$errors = [];
$pwErrors = [];

/** <option>s every 15 minutes (plus the stored value if it is off-grid). */
function time_options(string $selected): string
{
    $times = [];
    for ($m = 0; $m < 24 * 60; $m += 15) {
        $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
    if (!in_array($selected, $times, true)) {
        $times[] = $selected;
        sort($times);
    }
    $out = '';
    foreach ($times as $t) {
        $out .= '<option' . ($t === $selected ? ' selected' : '') . '>' . $t . '</option>';
    }
    return $out;
}

function valid_url(string $u): bool
{
    return $u === '' || (bool) preg_match('#^https://[^\s<>"]+$#i', $u);
}

if (is_post()) {
    admin_require_csrf();
    $action = post_str('action', 20);

    if ($action === 'settings') {
        $new = [];
        foreach (['site_name' => 80, 'tagline' => 140, 'announcement' => 200, 'phone' => 30, 'email' => 120, 'street' => 120,
                  'zip' => 10, 'city' => 60, 'maps_url' => 500, 'facebook_url' => 300, 'instagram_url' => 300,
                  'company_name' => 120, 'company_ico' => 20, 'company_address' => 200, 'delivery_area' => 120] as $k => $len) {
            $new[$k] = post_str($k, $len);
        }
        foreach (['maps_url', 'facebook_url', 'instagram_url'] as $k) {
            if (!valid_url($new[$k])) {
                $errors[] = 'Odkaz musí začínať https:// (' . $k . ').';
            }
        }
        if ($new['email'] !== '' && !filter_var($new['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail nie je v správnom tvare.';
        }
        if ($new['site_name'] === '') {
            $new['site_name'] = 'Pizza Slice Pezinok';
        }
        $new['ordering_enabled'] = isset($_POST['ordering_enabled']) ? '1' : '0';
        $new['delivery_enabled'] = isset($_POST['delivery_enabled']) ? '1' : '0';
        $new['prep_minutes'] = (string) max(0, min(180, (int) ($_POST['prep_minutes'] ?? 15)));
        $new['last_order_minutes'] = (string) max(0, min(180, (int) ($_POST['last_order_minutes'] ?? 15)));
        $new['retention_days'] = (string) max(7, min(3650, (int) ($_POST['retention_days'] ?? 90)));
        foreach (['delivery_fee_cents' => 'delivery_fee', 'delivery_min_cents' => 'delivery_min'] as $k => $field) {
            $c = parse_money(post_str($field, 20) ?: '0');
            if ($c === null) {
                $errors[] = 'Neplatná suma pri donáške.';
            }
            $new[$k] = (string) ($c ?? 0);
        }
        $hours = [];
        foreach (PS_DAYS as $d => $label) {
            $open = post_str("open_$d", 5);
            $close = post_str("close_$d", 5);
            if (isset($_POST["closed_$d"])) {
                $hours[$d] = null;
                continue;
            }
            if (!preg_match('/^\d{2}:\d{2}$/', $open) || !preg_match('/^\d{2}:\d{2}$/', $close) || $open >= $close) {
                $errors[] = "$label: zatvárací čas musí byť neskôr ako otvárací.";
                continue;
            }
            $hours[$d] = [$open, $close];
        }
        $new['hours'] = json_encode($hours);
        if (!$errors) {
            save_settings($new);
            redirect('/admin/settings.php?ok=saved');
        }
    } elseif ($action === 'password') {
        $st = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $st->execute([$user['id']]);
        $hash = (string) $st->fetchColumn();
        $current = (string) ($_POST['current'] ?? '');
        $pw = (string) ($_POST['new'] ?? '');
        if (!password_verify($current, $hash)) {
            $pwErrors[] = 'Aktuálne heslo nie je správne.';
        } elseif (strlen($pw) < 10) {
            $pwErrors[] = 'Nové heslo musí mať aspoň 10 znakov.';
        } elseif ($pw !== (string) ($_POST['new2'] ?? '')) {
            $pwErrors[] = 'Nové heslá sa nezhodujú.';
        } else {
            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
            db()->prepare('DELETE FROM admin_sessions WHERE user_id = ? AND selector <> ?')->execute([$user['id'], $user['selector']]);
            redirect('/admin/settings.php?ok=password');
        }
    }
}

$s = settings();
$week = week_hours();
if ($errors && isset($new)) {
    // keep what was typed when validation fails
    $s = array_merge($s, $new);
    foreach (json_decode($new['hours'], true) as $d => $h) {
        $week[(int) $d] = $h;
    }
}
admin_start('Nastavenia', 'settings');
?>
<?php foreach ($errors as $err): ?><p class="a-err" role="alert"><?= e($err) ?></p><?php endforeach; ?>

<section class="a-card">
  <h2>Vzhľad aplikácie</h2>
  <p class="a-muted">Platí len pre toto zariadenie. Tmavý režim šetrí oči večer a batériu telefónu.</p>
  <?php $theme = admin_theme(); ?>
  <div class="a-seg" role="group" aria-label="Vzhľad aplikácie">
    <button type="button" data-theme-set="light" aria-pressed="<?= $theme === 'light' ? 'true' : 'false' ?>">☀️ Svetlý</button>
    <button type="button" data-theme-set="dark" aria-pressed="<?= $theme === 'dark' ? 'true' : 'false' ?>">🌙 Tmavý</button>
    <button type="button" data-theme-set="auto" aria-pressed="<?= $theme === 'auto' ? 'true' : 'false' ?>">📱 Ako telefón</button>
  </div>
</section>

<form method="post" class="a-form">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="action" value="settings">

  <section class="a-card">
    <h2>Prevádzka</h2>
    <label class="a-check a-check-lg"><input type="checkbox" name="ordering_enabled"<?= $s['ordering_enabled'] === '1' ? ' checked' : '' ?>> Prijímať online objednávky</label>
    <label class="a-field"><span>Oznam na webe <small>(napr. „Dnes zatvárame o 18:00“ – prázdne = skryté)</small></span>
      <input class="a-input" name="announcement" maxlength="200" value="<?= e($s['announcement']) ?>">
    </label>
    <div class="a-grid2">
      <label class="a-field"><span>Príprava (min)</span><input class="a-input" type="number" min="0" max="180" name="prep_minutes" value="<?= e($s['prep_minutes']) ?>"></label>
      <label class="a-field"><span>Posledná objednávka pred zatvorením (min)</span><input class="a-input" type="number" min="0" max="180" name="last_order_minutes" value="<?= e($s['last_order_minutes']) ?>"></label>
    </div>
  </section>

  <section class="a-card">
    <h2>Otváracie hodiny</h2>
    <div class="a-hours">
      <?php foreach (PS_DAYS as $d => $label): $h = $week[$d]; ?>
      <div class="a-hours-row">
        <b><?= e($label) ?></b>
        <select class="a-input" name="open_<?= $d ?>" aria-label="<?= e($label) ?> otvára"><?= time_options($h[0] ?? '10:00') ?></select>
        <select class="a-input" name="close_<?= $d ?>" aria-label="<?= e($label) ?> zatvára"><?= time_options($h[1] ?? '21:00') ?></select>
        <label class="a-check"><input type="checkbox" name="closed_<?= $d ?>"<?= $h ? '' : ' checked' ?>> Zatv.</label>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="a-card">
    <h2>Kontakt a adresa</h2>
    <div class="a-grid2">
      <label class="a-field"><span>Telefón</span><input class="a-input" name="phone" type="tel" value="<?= e($s['phone']) ?>" placeholder="0901 234 567"></label>
      <label class="a-field"><span>E-mail</span><input class="a-input" name="email" type="email" value="<?= e($s['email']) ?>"></label>
    </div>
    <label class="a-field"><span>Ulica a číslo</span><input class="a-input" name="street" value="<?= e($s['street']) ?>" placeholder="napr. Holubyho 1"></label>
    <div class="a-grid2">
      <label class="a-field"><span>PSČ</span><input class="a-input" name="zip" value="<?= e($s['zip']) ?>"></label>
      <label class="a-field"><span>Mesto</span><input class="a-input" name="city" value="<?= e($s['city']) ?>"></label>
    </div>
    <label class="a-field"><span>Odkaz na Google Maps</span><input class="a-input" name="maps_url" type="url" value="<?= e($s['maps_url']) ?>" placeholder="https://maps.app.goo.gl/…"></label>
  </section>

  <section class="a-card">
    <h2>Sociálne siete</h2>
    <label class="a-field"><span>Facebook</span><input class="a-input" name="facebook_url" type="url" value="<?= e($s['facebook_url']) ?>"></label>
    <label class="a-field"><span>Instagram</span><input class="a-input" name="instagram_url" type="url" value="<?= e($s['instagram_url']) ?>"></label>
  </section>

  <section class="a-card">
    <h2>Donáška</h2>
    <label class="a-check a-check-lg"><input type="checkbox" name="delivery_enabled"<?= $s['delivery_enabled'] === '1' ? ' checked' : '' ?>> Ponúkať donášku</label>
    <div class="a-grid2">
      <label class="a-field"><span>Poplatok (€)</span><input class="a-input" name="delivery_fee" inputmode="decimal" value="<?= e(number_format((int) $s['delivery_fee_cents'] / 100, 2, ',', '')) ?>"></label>
      <label class="a-field"><span>Minimálna objednávka (€)</span><input class="a-input" name="delivery_min" inputmode="decimal" value="<?= e(number_format((int) $s['delivery_min_cents'] / 100, 2, ',', '')) ?>"></label>
    </div>
    <label class="a-field"><span>Oblasť donášky</span><input class="a-input" name="delivery_area" value="<?= e($s['delivery_area']) ?>"></label>
  </section>

  <section class="a-card">
    <h2>Web a firma</h2>
    <label class="a-field"><span>Názov prevádzky</span><input class="a-input" name="site_name" value="<?= e($s['site_name']) ?>"></label>
    <label class="a-field"><span>Slogan</span><input class="a-input" name="tagline" value="<?= e($s['tagline']) ?>"></label>
    <p class="a-muted">Údaje prevádzkovateľa sa zobrazia v zásadách ochrany osobných údajov a v pätičke.</p>
    <label class="a-field"><span>Obchodné meno</span><input class="a-input" name="company_name" value="<?= e($s['company_name']) ?>" placeholder="napr. Pizza Slice s.r.o."></label>
    <div class="a-grid2">
      <label class="a-field"><span>IČO</span><input class="a-input" name="company_ico" value="<?= e($s['company_ico']) ?>"></label>
      <label class="a-field"><span>Uchovávať údaje zákazníkov (dni)</span><input class="a-input" type="number" min="7" max="3650" name="retention_days" value="<?= e($s['retention_days']) ?>"></label>
    </div>
    <label class="a-field"><span>Sídlo</span><input class="a-input" name="company_address" value="<?= e($s['company_address']) ?>"></label>
  </section>

  <div class="a-actions a-actions-sticky">
    <button class="a-btn a-btn-primary a-btn-block" type="submit">Uložiť nastavenia</button>
  </div>
</form>

<section class="a-card" id="password">
  <h2>Zmena hesla</h2>
  <?php foreach ($pwErrors as $err): ?><p class="a-err" role="alert"><?= e($err) ?></p><?php endforeach; ?>
  <form method="post" class="a-form">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
    <label class="a-field"><span>Aktuálne heslo</span><input class="a-input" type="password" name="current" autocomplete="current-password" required></label>
    <label class="a-field"><span>Nové heslo (min. 10 znakov)</span><input class="a-input" type="password" name="new" minlength="10" autocomplete="new-password" required></label>
    <label class="a-field"><span>Nové heslo znova</span><input class="a-input" type="password" name="new2" minlength="10" autocomplete="new-password" required></label>
    <button class="a-btn" type="submit">Zmeniť heslo</button>
  </form>
</section>

<form method="post" action="/admin/logout.php" class="a-logout">
  <?= admin_csrf_field() ?>
  <p class="a-muted">Prihlásený ako <b><?= e($user['username']) ?></b></p>
  <button class="a-btn a-btn-danger a-btn-block" type="submit">Odhlásiť sa</button>
</form>
<?php admin_end();
