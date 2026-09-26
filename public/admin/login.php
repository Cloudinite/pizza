<?php
require dirname(__DIR__) . '/app/admin_bootstrap.php';

if (admin_current()) {
    redirect('/admin/');
}
csrf_token(); // issue the CSRF cookie before any output
$error = '';
$username = '';
if (is_post()) {
    $username = post_str('username', 50);
    if (!csrf_valid($_POST['csrf'] ?? null) || !same_origin_request()) {
        $error = 'Platnosť formulára vypršala, skúste to znova.';
    } else {
        [$uid, $error] = admin_attempt($username, (string) ($_POST['password'] ?? ''));
        if ($uid) {
            admin_login($uid);
            redirect('/admin/');
        }
    }
}
admin_start('Prihlásenie', 'login');
?>
<div class="a-login">
  <img src="/assets/img/logo-360.webp" width="180" height="165" alt="Pizza Slice">
  <h2>Administrácia prevádzky</h2>
  <?php if ($error !== ''): ?><p class="a-err" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="a-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label class="a-field"><span>Meno</span>
      <input class="a-input" name="username" autocomplete="username" autocapitalize="none" required value="<?= e($username) ?>">
    </label>
    <label class="a-field"><span>Heslo</span>
      <input class="a-input" name="password" type="password" autocomplete="current-password" required>
    </label>
    <button class="a-btn a-btn-primary a-btn-block" type="submit">Prihlásiť sa</button>
  </form>
  <p class="a-hint">Tip: po prihlásení si pridajte aplikáciu na plochu telefónu.</p>
</div>
<?php admin_end();
