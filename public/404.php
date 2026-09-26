<?php
if (!defined('PS_APP')) {
    require __DIR__ . '/app/bootstrap.php';
}
http_response_code(404);
page_start([
    'title' => 'Stránka sa nenašla – Pizza Slice Pezinok',
    'noindex' => true,
]);
?>
<section class="wrap confirm">
  <div class="card empty">
    <?= item_icon('slice') ?>
    <h1>Tento kúsok sme nenašli</h1>
    <p>Stránka neexistuje alebo odkaz už nie je platný.</p>
    <div class="card-actions">
      <a class="btn btn-primary" href="/menu">Na menu</a>
      <a class="btn btn-ghost" href="/">Na úvod</a>
    </div>
  </div>
</section>
<?php page_end();
