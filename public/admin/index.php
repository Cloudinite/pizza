<?php
require dirname(__DIR__) . '/app/admin_bootstrap.php';

require_admin();
$initial = [
    'view' => 'active',
    'orders' => orders_admin_list('active'),
    'stats' => orders_today_stats(),
    'ordering' => setting_bool('ordering_enabled'),
];
$ordering = setting_bool('ordering_enabled');

admin_start('Objednávky', 'orders', [
    'top_right' => '<label class="a-switch a-switch-top" title="Prijímať online objednávky">'
        . '<input type="checkbox" data-ordering' . ($ordering ? ' checked' : '') . '>'
        . '<span class="a-switch-ui" aria-hidden="true"></span><span class="a-switch-t" data-ordering-label>' . ($ordering ? 'Prijímame' : 'Pozastavené') . '</span></label>',
]);
?>
<section class="a-stats" aria-label="Dnes">
  <div><b data-stat-count><?= $initial['stats']['count'] ?></b><span>objednávok dnes</span></div>
  <div><b data-stat-total><?= e(money($initial['stats']['total'])) ?></b><span>tržba dnes</span></div>
  <button type="button" class="a-refresh" data-refresh aria-label="Obnoviť objednávky"><b data-stat-clock><?= date('H:i') ?></b><span data-conn>↻ obnoviť</span></button>
</section>

<div class="a-tools">
  <button class="a-chip" type="button" data-notify>🔔 Zapnúť zvuk a upozornenia</button>
  <button class="a-chip" type="button" data-wake hidden>Displej nezhasína: vyp.</button>
  <button class="a-chip" type="button" data-install hidden>Nainštalovať aplikáciu</button>
</div>

<div class="a-seg" role="tablist" aria-label="Zobrazenie objednávok">
  <button type="button" role="tab" aria-selected="true" data-view="active">Aktívne <b data-count-active></b></button>
  <button type="button" role="tab" aria-selected="false" data-view="done">Vybavené dnes</button>
  <button type="button" role="tab" aria-selected="false" data-view="history">História</button>
</div>

<section class="a-orders" data-orders aria-live="polite" aria-busy="false"></section>
<p class="a-empty" data-empty hidden>Zatiaľ žiadne objednávky. Nové sa tu zobrazia automaticky.</p>

<script type="application/json" id="orders-data"><?= json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php admin_end();
