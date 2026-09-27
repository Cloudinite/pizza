<?php
defined('PS_APP') || exit;

$user = require_admin();
$uid = $user['id'];
$errors = [];
$backupShown = null; // backup codes are shown exactly once, right after they are created

function check_password(int $uid, string $password): bool
{
    $u = admin_user_row($uid);
    return $u && password_verify($password, $u['password_hash']);
}

if (is_post()) {
    admin_require_csrf();
    $action = post_str('action', 30);
    $me = admin_user_row($uid);

    if ($action === 'path' && !admin_path_locked()) {
        $slug = strtolower(post_str('slug', 60));
        if (!check_password($uid, (string) ($_POST['password'] ?? ''))) {
            $errors['path'] = 'Heslo nie je správne.';
        } elseif (!admin_slug_ok($slug)) {
            $errors['path'] = 'Adresa: 8–48 znakov, malé písmená bez diakritiky, čísla a pomlčky (nie verejné slová ako menu, admin …).';
        } else {
            save_settings(['admin_path' => $slug, 'admin_path_ack' => '1']);
            redirect('/' . $slug . '/zabezpecenie?ok=moved#adresa');
        }
    } elseif (in_array($action, ['totp_start', 'totp_confirm'], true) && (int) $me['totp_enabled'] === 1) {
        // already on (e.g. the page was reloaded): never replace the key or show the backup codes twice
        redirect(admin_url('zabezpecenie') . '#dvojstupnove');
    } elseif ($action === 'totp_start') {
        $secret = totp_secret_new();
        db()->prepare('UPDATE admin_users SET totp_secret_enc = ?, totp_enabled = 0, totp_last_step = NULL WHERE id = ?')
            ->execute([encrypt_str($secret), $uid]);
        redirect(admin_url('zabezpecenie') . '?nastavit=1#dvojstupnove');
    } elseif ($action === 'totp_confirm') {
        $secret = decrypt_str($me['totp_secret_enc'] ?? null);
        $step = $secret ? totp_verify(base32_decode($secret), preg_replace('/\D/', '', post_str('code', 20)) ?? '') : null;
        if ($step === null) {
            $errors['totp'] = 'Kód nesedí. Skontrolujte, či je čas v telefóne nastavený automaticky, a zadajte najnovší kód.';
        } else {
            db()->prepare('UPDATE admin_users SET totp_enabled = 1, totp_last_step = ? WHERE id = ?')->execute([$step, $uid]);
            $backupShown = admin_backup_codes_new($uid);
        }
    } elseif ($action === 'totp_disable') {
        [$ok] = admin_2fa_check($uid, post_str('code', 20));
        if (!check_password($uid, (string) ($_POST['password'] ?? '')) || !$ok) {
            $errors['totp'] = 'Na vypnutie zadajte správne heslo aj aktuálny kód.';
        } else {
            db()->prepare('UPDATE admin_users SET totp_enabled = 0, totp_secret_enc = NULL, totp_last_step = NULL, backup_codes = NULL WHERE id = ?')->execute([$uid]);
            redirect(admin_url('zabezpecenie') . '?ok=saved#dvojstupnove');
        }
    } elseif ($action === 'backup_new') {
        [$ok] = admin_2fa_check($uid, post_str('code', 20));
        if (!$ok) {
            $errors['totp'] = 'Zadajte aktuálny kód z aplikácie.';
        } else {
            $backupShown = admin_backup_codes_new($uid);
        }
    } elseif ($action === 'session_revoke') {
        db()->prepare('DELETE FROM admin_sessions WHERE id = ? AND user_id = ?')->execute([(int) ($_POST['id'] ?? 0), $uid]);
        redirect(admin_url('zabezpecenie') . '?ok=deleted#zariadenia');
    } elseif ($action === 'sessions_others') {
        db()->prepare('DELETE FROM admin_sessions WHERE user_id = ? AND selector <> ?')->execute([$uid, $user['selector']]);
        redirect(admin_url('zabezpecenie') . '?ok=deleted#zariadenia');
    } elseif ($action === 'password') {
        $pw = (string) ($_POST['new'] ?? '');
        if (!check_password($uid, (string) ($_POST['current'] ?? ''))) {
            $errors['password'] = 'Aktuálne heslo nie je správne.';
        } elseif (($why = ps_password_problem($pw, $user['username'])) !== '') {
            $errors['password'] = $why;
        } elseif ($pw !== (string) ($_POST['new2'] ?? '')) {
            $errors['password'] = 'Nové heslá sa nezhodujú.';
        } else {
            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([ps_password_hash($pw), $uid]);
            db()->prepare('DELETE FROM admin_sessions WHERE user_id = ? AND selector <> ?')->execute([$uid, $user['selector']]);
            redirect(admin_url('zabezpecenie') . '?ok=password#heslo');
        }
    }
}

$me = admin_user_row($uid);
$totpOn = (int) $me['totp_enabled'] === 1;
$setup = !$totpOn && !empty($me['totp_secret_enc']) && (isset($_GET['nastavit']) || isset($errors['totp']));
$setupSecret = $setup ? decrypt_str($me['totp_secret_enc']) : null;
$backupLeft = count(json_decode((string) $me['backup_codes'], true) ?: []);
$sessions = admin_sessions_list($uid);

admin_start('Zabezpečenie', 'settings');
?>
<a class="a-back" href="<?= e(admin_url('nastavenia')) ?>">← Nastavenia</a>

<?php if ($backupShown): ?>
<section class="a-card a-backup" aria-labelledby="bk-h">
  <h2 id="bk-h">🧾 Záložné kódy – uložte si ich teraz</h2>
  <p class="a-muted">Každý kód funguje raz, ak nemáte po ruke telefón. Zobrazujú sa len teraz – vytlačte si ich alebo uložte do správcu hesiel.</p>
  <ol class="a-codes" data-copy-src><?php foreach ($backupShown as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ol>
  <button class="a-btn a-btn-sm" type="button" data-copy>Kopírovať kódy</button>
</section>
<?php endif; ?>

<section class="a-card" id="adresa">
  <h2>🕵️ Tajná adresa administrácie</h2>
  <p class="a-muted">Administrácia nemá verejný odkaz. Kto nepozná túto adresu, uvidí len „stránka neexistuje“.</p>
  <div class="a-url">
    <code data-copy-src><?= e(admin_full_url()) ?></code>
    <button class="a-btn a-btn-sm" type="button" data-copy>Kopírovať</button>
  </div>
  <?php if (admin_path_locked()): ?>
    <p class="a-muted">Adresa je pevne nastavená v súbore <code>app/config.php</code>.</p>
  <?php else: ?>
  <details class="a-details"<?= isset($errors['path']) ? ' open' : '' ?>>
    <summary><b>Zmeniť adresu</b></summary>
    <?php if (isset($errors['path'])): ?><p class="a-err" role="alert"><?= e($errors['path']) ?></p><?php endif; ?>
    <form method="post" class="a-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="path">
      <div class="a-field"><label for="slug">Nová adresa (za lomkou)</label>
        <span class="a-inline">
          <input class="a-input a-code-input" id="slug" name="slug" required minlength="8" maxlength="48" pattern="[a-z0-9][a-z0-9\-]{7,47}" value="<?= e(admin_slug_random()) ?>" autocapitalize="none" spellcheck="false" data-slug-input>
          <button class="a-btn a-btn-sm" type="button" data-gen-slug>Nová</button>
        </span>
      </div>
      <label class="a-field"><span>Vaše heslo (na potvrdenie)</span><input class="a-input" type="password" name="password" required autocomplete="current-password"></label>
      <p class="a-muted">Po zmene sa stará adresa okamžite prestane otvárať. Aplikáciu na ploche telefónu bude treba pridať znova.</p>
      <button class="a-btn a-btn-primary a-btn-block" type="submit">Presunúť administráciu</button>
    </form>
  </details>
  <?php endif; ?>
</section>

<section class="a-card" id="dvojstupnove">
  <div class="a-card-h">
    <h2>📱 Overenie v dvoch krokoch</h2>
    <span class="a-tag<?= $totpOn ? ' a-tag-ok' : '' ?>"><?= $totpOn ? 'zapnuté' : 'vypnuté' ?></span>
  </div>
  <?php if (isset($errors['totp'])): ?><p class="a-err" role="alert"><?= e($errors['totp']) ?></p><?php endif; ?>

  <?php if ($totpOn): ?>
    <p class="a-muted">Pri prihlásení okrem hesla zadáte aj 6-miestny kód z aplikácie v telefóne. Aj keby niekto uhádol heslo, bez telefónu sa neprihlási. Zostávajúce záložné kódy: <b><?= $backupLeft ?></b>.</p>
    <details class="a-details">
      <summary><b>Nové záložné kódy</b></summary>
      <form method="post" class="a-form">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="backup_new">
        <label class="a-field"><span>Aktuálny kód z aplikácie</span><input class="a-input a-code-field" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
        <button class="a-btn" type="submit">Vytvoriť nové kódy</button>
      </form>
    </details>
    <details class="a-details">
      <summary><b>Vypnúť overenie v dvoch krokoch</b></summary>
      <form method="post" class="a-form">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="totp_disable">
        <label class="a-field"><span>Heslo</span><input class="a-input" type="password" name="password" required autocomplete="current-password"></label>
        <label class="a-field"><span>Kód z aplikácie</span><input class="a-input a-code-field" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
        <button class="a-btn a-btn-danger" type="submit">Vypnúť</button>
      </form>
    </details>
  <?php elseif ($setup && $setupSecret): ?>
    <ol class="a-steps">
      <li>Nainštalujte si aplikáciu na overenie – napr. <b>Google Authenticator</b>, <b>Microsoft Authenticator</b> alebo použite Heslá v iPhone.</li>
      <li>Naskenujte QR kód (alebo na tomto telefóne ťuknite na „Otvoriť v aplikácii“).</li>
      <li>Zadajte 6-miestny kód, ktorý aplikácia ukáže.</li>
    </ol>
    <?php $uri = totp_uri($setupSecret, $user['username']); ?>
    <div class="a-qr" data-qr="<?= e($uri) ?>" role="img" aria-label="QR kód pre aplikáciu na overenie"></div>
    <p class="a-secret">Kľúč na ručné zadanie:<br><code data-copy-src><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code></p>
    <div class="a-actions">
      <a class="a-btn a-btn-sm" href="<?= e($uri) ?>">Otvoriť v aplikácii</a>
      <button class="a-btn a-btn-sm" type="button" data-copy>Kopírovať kľúč</button>
    </div>
    <form method="post" class="a-form a-mt">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="totp_confirm">
      <label class="a-field"><span>Kód z aplikácie</span><input class="a-input a-code-field" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="7" placeholder="123456"></label>
      <button class="a-btn a-btn-primary a-btn-block" type="submit">Zapnúť overenie</button>
    </form>
    <script src="<?= e(admin_asset_url('qrcode.js')) ?>" defer></script>
  <?php else: ?>
    <p class="a-muted">Odporúčame: pri prihlásení bude okrem hesla potrebný aj kód z telefónu. Heslo samo o sebe potom na prihlásenie nestačí.</p>
    <form method="post">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="totp_start">
      <button class="a-btn a-btn-primary a-btn-block" type="submit">Zapnúť overenie v dvoch krokoch</button>
    </form>
  <?php endif; ?>
</section>

<section class="a-card" id="zariadenia">
  <div class="a-card-h">
    <h2>💻 Prihlásené zariadenia</h2>
    <?php if (count($sessions) > 1): ?>
    <form method="post" data-confirm="Odhlásiť všetky ostatné zariadenia?">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="sessions_others">
      <button class="a-btn a-btn-sm a-btn-danger" type="submit">Odhlásiť ostatné</button>
    </form>
    <?php endif; ?>
  </div>
  <ul class="a-list">
    <?php foreach ($sessions as $s): $isMe = $s['selector'] === $user['selector']; ?>
    <li class="a-row">
      <div class="a-row-main">
        <b><?= e(device_label((string) $s['user_agent'])) ?><?= $isMe ? ' <span class="a-tag a-tag-ok">toto zariadenie</span>' : '' ?></b>
        <span>naposledy <?= e(date('j. n. Y H:i', strtotime($s['last_seen_at']))) ?> · prihlásené <?= e(date('j. n. Y', strtotime($s['created_at']))) ?></span>
      </div>
      <?php if (!$isMe): ?>
      <form method="post">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="session_revoke">
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <button class="a-btn a-btn-sm" type="submit">Odhlásiť</button>
      </form>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($me['last_login_at']): ?><p class="a-muted a-mt">Posledné prihlásenie: <?= e(date('j. n. Y H:i', strtotime($me['last_login_at']))) ?></p><?php endif; ?>
</section>

<section class="a-card" id="heslo">
  <h2>🔑 Zmena hesla</h2>
  <?php if (isset($errors['password'])): ?><p class="a-err" role="alert"><?= e($errors['password']) ?></p><?php endif; ?>
  <form method="post" class="a-form">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
    <label class="a-field"><span>Aktuálne heslo</span><input class="a-input" type="password" name="current" autocomplete="current-password" required></label>
    <label class="a-field"><span>Nové heslo (min. 10 znakov)</span><input class="a-input" type="password" name="new" minlength="10" autocomplete="new-password" required></label>
    <label class="a-field"><span>Nové heslo znova</span><input class="a-input" type="password" name="new2" minlength="10" autocomplete="new-password" required></label>
    <p class="a-muted">Po zmene hesla sa všetky ostatné zariadenia odhlásia.</p>
    <button class="a-btn a-btn-primary" type="submit">Zmeniť heslo</button>
  </form>
</section>
<?php admin_end();
