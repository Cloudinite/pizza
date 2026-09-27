<?php
defined('PS_APP') || exit;

$legacy = defined('PS_LEGACY_LOGIN'); // reached through the old /admin/ address (one-time move)
$after = $legacy ? admin_url('?presunute=1') : admin_url();
$self = $legacy ? '/admin/' : admin_url('prihlasenie');

if (admin_current()) {
    redirect($after);
}
csrf_token(); // issue the CSRF cookie before any output
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');

$step = 'password';
$error = '';
$username = '';
$pendingUser = admin_2fa_pending();

if (is_post()) {
    if (!csrf_valid($_POST['csrf'] ?? null) || !same_origin_request()) {
        $error = 'Platnosť formulára vypršala, skúste to znova.';
    } elseif (post_str('step', 10) === 'code' && $pendingUser) {
        [$ok, $error] = admin_2fa_check($pendingUser, post_str('code', 20));
        if ($ok) {
            admin_2fa_pending_clear();
            admin_login($pendingUser);
            redirect($after);
        }
        $step = 'code';
    } elseif (post_str('step', 10) === 'code') {
        $error = 'Prihlásenie vypršalo, zadajte heslo znova.';
    } else {
        $username = post_str('username', 50);
        [$uid, $error] = admin_attempt($username, (string) ($_POST['password'] ?? ''));
        if ($uid) {
            if (admin_totp_enabled($uid)) {
                admin_2fa_pending_set($uid);
                $step = 'code';
            } else {
                admin_login($uid);
                redirect($after);
            }
        }
    }
} elseif ($pendingUser && ($_GET['krok'] ?? '') === 'kod') {
    $step = 'code';
}

admin_start('Prihlásenie', 'login');
?>
<div class="a-login<?= $error !== '' ? ' has-error' : '' ?>">
  <div class="a-login-logo">
    <img src="/assets/img/logo-360.webp" width="150" height="138" alt="Pizza Slice">
  </div>
  <?php if ($step === 'code'): ?>
    <h2>Overovací kód</h2>
    <p class="a-muted">Otvorte aplikáciu na overenie (napr. Google Authenticator) a zadajte 6-miestny kód pre Pizza Slice. Môžete použiť aj záložný kód.</p>
  <?php else: ?>
    <h2>Prihlásenie do prevádzky</h2>
    <p class="a-muted">Zabezpečené šifrované spojenie <span class="a-lock" aria-hidden="true">🔒</span></p>
  <?php endif; ?>

  <?php if ($error !== ''): ?><p class="a-err" role="alert"><?= e($error) ?></p><?php endif; ?>

  <form method="post" action="<?= e($self) ?>" class="a-form a-login-form" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($step === 'code'): ?>
      <input type="hidden" name="step" value="code">
      <label class="a-field"><span>Kód z aplikácie</span>
        <input class="a-input a-code-field" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="11" autofocus placeholder="123 456" spellcheck="false" autocapitalize="off">
      </label>
      <button class="a-btn a-btn-primary a-btn-block" type="submit">Overiť a prihlásiť</button>
      <a class="a-back a-center" href="<?= e($self) ?>">← Späť na heslo</a>
    <?php else: ?>
      <input type="hidden" name="step" value="password">
      <label class="a-field"><span>Meno</span>
        <input class="a-input" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required value="<?= e($username) ?>"<?= $username === '' ? ' autofocus' : '' ?>>
      </label>
      <div class="a-field"><label for="pw">Heslo</label>
        <span class="a-pass">
          <input class="a-input" id="pw" name="password" type="password" autocomplete="current-password" required<?= $username !== '' ? ' autofocus' : '' ?> data-password>
          <button type="button" class="a-pass-toggle" data-pass-toggle aria-label="Zobraziť heslo" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path class="a-slash" d="M4 4l16 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
        </span>
        <small class="a-caps" data-caps>Pozor, máte zapnutý Caps Lock.</small>
      </div>
      <button class="a-btn a-btn-primary a-btn-block" type="submit">Prihlásiť sa</button>
    <?php endif; ?>
  </form>
  <p class="a-hint">Po prihlásení si aplikáciu pridajte na plochu telefónu.</p>
</div>
<?php admin_end();
