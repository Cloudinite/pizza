<?php
defined('PS_APP') || exit;

require_admin();
if (is_post() && post_str('action', 20) === 'ack_path') {
    admin_require_csrf();
    save_settings(['admin_path_ack' => '1']); // from now on the old /admin/ address is a plain 404
    redirect(admin_url() . '?ok=saved');
}
$showMoved = setting('admin_path_ack') !== '1';
$initial = [
    'view' => 'active',
    'orders' => orders_admin_list('active'),
    'stats' => orders_today_stats(),
    'ordering' => setting_bool('ordering_enabled'),
];
$ordering = $initial['ordering'];
$activeCount = count($initial['orders']);
// preferences live in cookies, so buttons are drawn in their final state (nothing changes after load)
$soundOn = ($_COOKIE['ps_sound'] ?? '') === '1';
$wakeOn = ($_COOKIE['ps_wake'] ?? '') === '1';

admin_start('Objednávky', 'orders');
?>
<?php if ($showMoved): ?>
<section class="a-card a-moved" aria-labelledby="moved-h">
  <h2 id="moved-h">🔒 Administrácia má novú tajnú adresu</h2>
  <p>Stará adresa <code>/admin/</code> je pre roboty aj útočníkov známa, preto je aplikácia teraz schovaná na adrese, ktorú poznáte len vy:</p>
  <div class="a-url">
    <code data-copy-src><?= e(admin_full_url()) ?></code>
    <button class="a-btn a-btn-sm" type="button" data-copy>Kopírovať</button>
  </div>
  <p class="a-muted">Uložte si ju do záložiek a znova pridajte aplikáciu na plochu telefónu (ikona na ploche so starou adresou už nebude fungovať). Po potvrdení bude <code>/admin/</code> vracať len „stránka neexistuje“.</p>
  <form method="post">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="ack_path">
    <button class="a-btn a-btn-primary a-btn-block" type="submit">Mám ju uloženú – skryť starú adresu</button>
  </form>
</section>
<?php endif; ?>
<div class="a-ordering<?= $ordering ? '' : ' is-off' ?>" data-ordering-card>
  <div>
    <b>Online objednávky</b>
    <span data-ordering-label><?= $ordering ? 'Prijímame – web berie objednávky' : 'Pozastavené – web objednávky neberie' ?></span>
  </div>
  <label class="a-switch" title="Prijímať online objednávky">
    <input type="checkbox" data-ordering<?= $ordering ? ' checked' : '' ?>>
    <span class="a-switch-ui" aria-hidden="true"></span>
    <span class="sr">Prijímať online objednávky</span>
  </label>
</div>

<section class="a-stats" aria-label="Dnes">
  <div><b data-stat-count><?= $initial['stats']['count'] ?></b><span>objednávok dnes</span></div>
  <div><b data-stat-total><?= e(money($initial['stats']['total'])) ?></b><span>tržba dnes</span></div>
  <button type="button" class="a-refresh" data-refresh aria-label="Obnoviť objednávky"><b data-stat-clock><?= date('H:i') ?></b><span data-conn><i class="spin" aria-hidden="true">↻</i> obnoviť</span></button>
</section>

<div class="a-tools">
  <button class="a-chip<?= $soundOn ? ' is-on' : '' ?>" type="button" data-notify><?= $soundOn ? '🔔 Zvuk zapnutý' : '🔔 Zapnúť zvuk a upozornenia' ?></button>
  <button class="a-chip<?= $wakeOn ? ' is-on' : '' ?>" type="button" data-wake hidden>Displej nezhasína: <?= $wakeOn ? 'zap.' : 'vyp.' ?></button>
  <button class="a-chip" type="button" data-install hidden>Nainštalovať aplikáciu</button>
</div>

<div class="a-seg" role="tablist" aria-label="Zobrazenie objednávok">
  <button type="button" role="tab" aria-selected="true" data-view="active">Aktívne <b data-count-active><?= $activeCount ?: '' ?></b></button>
  <button type="button" role="tab" aria-selected="false" data-view="done">Vybavené dnes</button>
  <button type="button" role="tab" aria-selected="false" data-view="history">História</button>
</div>

<section class="a-orders" data-orders aria-live="polite" aria-busy="false"></section>
<p class="a-empty" data-empty<?= $activeCount ? ' hidden' : '' ?>>Zatiaľ žiadne objednávky. Nové sa tu zobrazia automaticky.</p>

<script type="application/json" id="orders-data"><?= json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php admin_end();
