<?php
require __DIR__ . '/app/bootstrap.php';

$s = settings();
$menu = menu_load();
$specials = menu_specials($menu);
$state = open_state();
$today = week_hours()[(int) date('N')];

// Headline facts come from the live menu so they stay correct when prices change.
$slicePrices = [];
$sliceCount = 0;
foreach ($menu['categories'] as $c) {
    if (!(int) $c['is_special'] && $c['icon'] === 'slice') {
        foreach ($c['items'] as $it) {
            $slicePrices[] = $it['price_cents'];
            $sliceCount++;
        }
    }
}

page_start([
    'title' => 'Pizza Slice Pezinok – pizza na kúsky, vždy čerstvá',
    'description' => 'Pizza na kúsky v Pezinku. ' . ($sliceCount ? "$sliceCount druhov, " : '') . 'vždy čerstvá, každý deň. Objednajte online a ' . fulfilment_phrase(true) . '.',
    'path' => '/',
    'jsonld' => [schema_restaurant()],
    'preload' => [['/assets/img/logo-360.webp', 'image', '/assets/img/logo-360.webp 360w, /assets/img/logo-720.webp 720w', '(min-width: 900px) 360px, 240px']],
]);
?>
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-txt">
      <p class="eyebrow">Pezinok · pizza na kúsky</p>
      <h1>Pizza Slice <span class="accent">Pezinok</span></h1>
      <p class="lead"><?= e($s['tagline']) ?>. Objednaj online a <?= e(fulfilment_phrase()) ?>.</p>
      <p class="status"><span class="dot<?= $state['open'] ? ' is-open' : '' ?>" aria-hidden="true"></span><?= e($state['text']) ?></p>
      <div class="cta">
        <a class="btn btn-primary btn-lg" href="/menu">Objednať online</a>
        <a class="btn btn-ghost btn-lg" href="#cennik">Cenník</a>
      </div>
      <ul class="facts">
        <?php if ($slicePrices): ?>
        <li><b><?= e(money(min($slicePrices))) ?></b><span>kúsok pizze</span></li>
        <li><b><?= $sliceCount ?></b><span><?= plural($sliceCount, 'druh', 'druhy', 'druhov') ?> pizze</span></li>
        <?php endif; ?>
        <li><b><?= $today ? e(short_time($today[0]) . '–' . short_time($today[1])) : '—' ?></b><span><?= $today ? 'dnes otvorené' : 'dnes zatvorené' ?></span></li>
      </ul>
    </div>
    <div class="hero-logo">
      <img src="/assets/img/logo-360.webp" srcset="/assets/img/logo-360.webp 360w, /assets/img/logo-720.webp 720w"
           sizes="(min-width: 900px) 360px, 240px" width="360" height="330" alt="Logo Pizza Slice – Always fresh everyday" fetchpriority="high">
    </div>
  </div>
</section>
<?php brand_band('band-hero'); ?>

<?php if ($specials): ?>
<section class="section special" aria-labelledby="special-h">
  <div class="wrap">
    <h2 id="special-h" class="section-h"><span class="eyebrow">Práve teraz</span>Aktuálna špecialita</h2>
    <div class="special-list">
      <?php foreach ($specials as $sp): ?>
      <article class="special-card">
        <div class="special-media">
          <?php if ($sp['image']): ?>
            <img src="/uploads/<?= e($sp['image']) ?>" width="120" height="120" alt="<?= e($sp['name']) ?>" loading="lazy">
          <?php else: ?>
            <?= item_icon('star') ?>
          <?php endif; ?>
        </div>
        <div class="special-body">
          <?php if ($sp['badge'] !== ''): ?><p class="tag"><?= e($sp['badge']) ?></p><?php endif; ?>
          <h3><?= e($sp['name']) ?></h3>
          <?php if ($sp['description'] !== ''): ?><p><?= e($sp['description']) ?></p><?php endif; ?>
        </div>
        <div class="special-buy">
          <p class="price"><?= e(money($sp['price_cents'])) ?><?php if ($sp['unit_label'] !== ''): ?> <small>/ <?= e($sp['unit_label']) ?></small><?php endif; ?></p>
          <a class="btn btn-primary" href="/menu#item-<?= $sp['id'] ?>">Objednať</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="cennik" aria-labelledby="cennik-h">
  <div class="wrap">
    <h2 id="cennik-h" class="section-h"><span class="eyebrow">Cenník</span>Čo dnes pečieme</h2>
    <?php foreach ($menu['categories'] as $c):
        if ((int) $c['is_special'] || !$c['items']) {
            continue;
        }
        $prices = array_column($c['items'], 'price_cents');
        $same = count(array_unique($prices)) === 1;
        $isDrink = $c['icon'] === 'drink';
        ?>
    <article class="board" aria-labelledby="board-<?= e($c['slug']) ?>">
      <div class="board-main">
        <p class="eyebrow">Cenník</p>
        <h3 class="board-h" id="board-<?= e($c['slug']) ?>"><?= e($c['name']) ?></h3>
        <ol class="board-list">
          <?php foreach ($c['items'] as $i => $it): ?>
          <li<?= (int) $it['is_available'] ? '' : ' class="is-out"' ?>>
            <span class="n"><?= sprintf('%02d', $i + 1) ?></span>
            <span class="nm"><?= e($it['name']) ?><?= (int) $it['is_available'] ? '' : ' <small>(vypredané)</small>' ?></span>
            <?php if (!$same): ?><span class="pr"><?= e(money($it['price_cents'])) ?></span><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <aside class="board-side">
        <img src="/assets/img/logo-208.webp" width="104" height="95" alt="" loading="lazy">
        <div>
          <p class="board-unit"><?= $isDrink ? '1 nápoj / drink' : '1 kus / slice' ?></p>
          <p class="board-price"><?= $same ? '' : '<small>od</small> ' ?><?= e(money(min($prices))) ?></p>
          <?php if ($c['subtitle'] !== ''): ?><p class="board-sub"><?= e($c['subtitle']) ?></p><?php endif; ?>
        </div>
      </aside>
    </article>
    <?php endforeach; ?>
    <p class="center"><a class="btn btn-primary btn-lg" href="/menu">Vybrať a objednať</a></p>
  </div>
</section>

<section class="section how" aria-labelledby="how-h">
  <div class="wrap">
    <h2 id="how-h" class="section-h"><span class="eyebrow">Online objednávka</span>Hotovo za pár klikov</h2>
    <ol class="steps-grid">
      <li><b>1</b><h3>Vyber si kúsky</h3><p>Každý kúsok môžeš mať iný – ochutnaj viac druhov naraz.</p></li>
      <li><b>2</b><h3>Pridaj prílohy</h3><p>Extra syr, šunka, olivy či feferóny – podľa chuti.</p></li>
      <?php if (setting_bool('delivery_enabled')): ?>
      <li><b>3</b><h3>Vyzdvihni alebo si nechaj doniesť</h3><p>Zvoľ čas a sleduj stav objednávky online. Platíš pri prevzatí hotovosťou alebo kartou.</p></li>
      <?php else: ?>
      <li><b>3</b><h3>Vyzdvihni bez čakania</h3><p>Zvoľ čas a sleduj stav objednávky online. Platíš pri prevzatí hotovosťou alebo kartou.</p></li>
      <?php endif; ?>
    </ol>
  </div>
</section>

<section class="section info" id="kontakt" aria-labelledby="kontakt-h">
  <div class="wrap">
    <h2 id="kontakt-h" class="section-h"><span class="eyebrow">Kontakt</span>Príď na kúsok</h2>
    <div class="info-grid">
      <div class="card">
        <h3><svg class="ico" aria-hidden="true"><use href="#i-clock"/></svg>Otváracie hodiny</h3>
        <?php hours_table(); ?>
        <p class="status-inline"><span class="dot<?= $state['open'] ? ' is-open' : '' ?>" aria-hidden="true"></span><?= e($state['text']) ?></p>
      </div>
      <div class="card">
        <h3><svg class="ico" aria-hidden="true"><use href="#i-pin"/></svg>Kde nás nájdete</h3>
        <address>
          <b><?= e($s['site_name']) ?></b><br>
          <?= e(address_line() !== '' ? address_line() : 'Pezinok') ?>
        </address>
        <div class="card-actions">
          <?php if ($s['maps_url'] !== ''): ?>
            <a class="btn btn-ghost" href="<?= e($s['maps_url']) ?>" rel="noopener" target="_blank">Navigovať</a>
          <?php endif; ?>
          <?php if ($s['phone'] !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(phone_href($s['phone'])) ?>"><svg class="ico" aria-hidden="true"><use href="#i-phone"/></svg><?= e($s['phone']) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="card">
        <h3>Sledujte nás</h3>
        <p>Nové druhy, špeciality a akcie zverejňujeme ako prvé na sociálnych sieťach.</p>
        <?php social_links('social social-lg'); ?>
      </div>
    </div>
  </div>
</section>
<?php page_end();
