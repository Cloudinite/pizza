<?php
defined('PS_APP') || exit;
// Shown by the service worker when there is no connection (cached once, so no login needed here).
send_security_headers();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#b41116">
<meta name="robots" content="noindex, nofollow">
<title>Offline – Pizza Slice Admin</title>
<link rel="stylesheet" href="<?= e(admin_url('assets/admin.css')) ?>">
</head>
<body class="adm is-guest">
<main class="a-main">
  <div class="a-login">
    <img src="/assets/img/logo-104.webp" width="104" height="95" alt="">
    <h2>Ste offline</h2>
    <p class="a-muted">Nie je pripojenie na internet. Objednávky sa zobrazia hneď, ako sa pripojenie obnoví.</p>
    <a class="a-btn a-btn-primary a-btn-block" href="<?= e(admin_url()) ?>">Skúsiť znova</a>
  </div>
</main>
</body>
</html>
