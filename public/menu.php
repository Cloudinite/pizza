<?php
require __DIR__ . '/app/bootstrap.php';

$menu = menu_load();
$state = ordering_state();
$priced = cart_price(cart_read(), $menu);

// Quantity of plain (no-topping) lines per item, used for the inline steppers.
$plainQty = [];
foreach ($priced['lines'] as $l) {
    if (!$l['tops']) {
        $plainQty[$l['id']] = $l['qty'];
    }
}

$sections = [];
foreach ($menu['categories'] as $c) {
    if (!$c['items']) {
        continue;
    }
    $sections[] = [
        '@type' => 'MenuSection',
        'name' => $c['name'],
        'hasMenuItem' => array_map(static fn ($it) => array_filter([
            '@type' => 'MenuItem',
            'name' => $it['name'],
            'description' => $it['description'] ?: null,
            'offers' => ['@type' => 'Offer', 'price' => number_format($it['price_cents'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR'],
        ]), $c['items']),
    ];
}

page_start([
    'title' => 'Menu a online objednávka – Pizza Slice Pezinok',
    'description' => 'Menu Pizza Slice Pezinok: pizza na kúsky, nápoje a prílohy navyše. Objednajte online a ' . fulfilment_phrase(true) . '.',
    'path' => '/menu',
    'nav' => 'menu',
    'body_class' => 'has-cartbar',
    'jsonld' => [['@context' => 'https://schema.org', '@type' => 'Menu', 'name' => 'Menu Pizza Slice Pezinok', 'url' => abs_url('/menu'), 'inLanguage' => 'sk', 'hasMenuSection' => $sections]],
]);

function add_control(array $it, int $qty): void
{
    $id = $it['id'];
    $name = $it['name'];
    if (!(int) $it['is_available']) {
        echo '<span class="soldout">Vypredané</span>';
        return;
    }
    if ((int) $it['allow_toppings']) {
        // Opens the toppings dialog with JS; without JS it adds one plain piece.
        echo '<form method="post" action="/kosik" data-add data-id="' . $id . '">'
            . '<input type="hidden" name="op" value="add"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-primary btn-add" type="submit" aria-haspopup="dialog" aria-label="Pridať ' . e($name) . ' do košíka">Pridať</button>'
            . '</form>';
        return;
    }
    $key = e($id . ':');
    echo '<form method="post" action="/kosik" class="qtyform" data-qty data-id="' . $id . '">';
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<button class="btn btn-primary btn-add" type="submit" name="op" value="add" data-add-plain'
        . ($qty ? ' hidden' : '') . ' aria-label="Pridať ' . e($name) . ' do košíka">Pridať</button>';
    echo '<div class="stepper" data-stepper' . ($qty ? '' : ' hidden') . '>'
        . '<button type="submit" name="op" value="dec|' . $key . '" data-dec aria-label="Ubrať ' . e($name) . '">−</button>'
        . '<output data-qty-out aria-live="polite">' . $qty . '</output>'
        . '<button type="submit" name="op" value="inc|' . $key . '" data-inc aria-label="Pridať ďalší ' . e($name) . '">+</button>'
        . '</div>';
    echo '</form>';
}
?>
<section class="page-h">
  <div class="wrap">
    <p class="eyebrow">Pezinok · pizza na kúsky</p>
    <h1>Menu a objednávka</h1>
    <p class="lead">Vyber si kúsky, pridaj prílohy a <?= e(fulfilment_phrase()) ?>. Platíš až pri prevzatí.</p>
    <?php if (!$state['ok']): ?>
      <p class="notice notice-warn" role="status"><?= e($state['message']) ?> Menu si môžeš pozrieť aj tak.</p>
    <?php elseif ($state['message'] !== ''): ?>
      <p class="notice" role="status"><?= e($state['message']) ?></p>
    <?php endif; ?>
    <nav class="chips" aria-label="Kategórie menu">
      <?php foreach ($menu['categories'] as $c): if (!$c['items']) { continue; } ?>
        <a href="#<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <?php if ($menu['toppings']): ?><a href="#prilohy">Prílohy</a><?php endif; ?>
    </nav>
  </div>
</section>

<?php foreach ($menu['categories'] as $c): if (!$c['items']) { continue; } ?>
<section class="cat" id="<?= e($c['slug']) ?>" aria-labelledby="h-<?= e($c['slug']) ?>">
  <div class="wrap">
    <div class="cat-h">
      <h2 id="h-<?= e($c['slug']) ?>"><?= e($c['name']) ?></h2>
      <?php if ($c['subtitle'] !== ''): ?><p><?= e($c['subtitle']) ?></p><?php endif; ?>
    </div>
    <ul class="items">
      <?php foreach ($c['items'] as $it): ?>
      <li class="item<?= (int) $it['is_available'] ? '' : ' is-out' ?><?= (int) $c['is_special'] ? ' is-special' : '' ?>" id="item-<?= $it['id'] ?>">
        <div class="thumb">
          <?php if ($it['image']): ?>
            <img src="/uploads/<?= e($it['image']) ?>" width="72" height="72" alt="" loading="lazy" decoding="async">
          <?php else: ?>
            <?= item_icon((int) $c['is_special'] ? 'star' : $c['icon']) ?>
          <?php endif; ?>
        </div>
        <div class="item-body">
          <h3 class="item-name"><?= e($it['name']) ?><?php if ($it['badge'] !== ''): ?> <span class="tag"><?= e($it['badge']) ?></span><?php endif; ?></h3>
          <?php if ($it['description'] !== ''): ?><p class="item-desc"><?= e($it['description']) ?></p><?php endif; ?>
          <div class="item-foot">
            <p class="item-price"><?= e(money($it['price_cents'])) ?><?php if ($it['unit_label'] !== ''): ?> <small>/ <?= e($it['unit_label']) ?></small><?php endif; ?></p>
            <div class="item-act"><?php add_control($it, $plainQty[$it['id']] ?? 0); ?></div>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endforeach; ?>

<?php if ($menu['toppings']): ?>
<section class="cat" id="prilohy" aria-labelledby="h-prilohy">
  <div class="wrap">
    <div class="cat-h">
      <h2 id="h-prilohy">Prílohy navyše</h2>
      <p>Pridáš ich ku každému kúsku pizze po kliknutí na „Pridať“. Cena je za jeden kúsok.</p>
    </div>
    <ul class="tops-list">
      <?php foreach ($menu['toppings'] as $t): ?>
        <li><span><?= e($t['name']) ?></span><b>+<?= e(money($t['price_cents'])) ?></b></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<div class="cartbar" data-cartbar>
  <div class="wrap cartbar-in">
    <p class="cartbar-sum">
      <b data-cart-total><?= e(money($priced['subtotal'])) ?></b>
      <small data-cart-items><?= $priced['count'] ?> <?= plural($priced['count'], 'položka', 'položky', 'položiek') ?> v košíku</small>
    </p>
    <a class="btn btn-primary cartbar-btn<?= $priced['count'] ? '' : ' is-disabled' ?>" href="/kosik" data-cart-go<?= $priced['count'] ? '' : ' aria-disabled="true"' ?>>Do košíka</a>
  </div>
</div>

<dialog class="dlg" id="topdlg" aria-labelledby="dlg-title">
  <form method="dialog" class="dlg-in" data-dlg-form>
    <div class="dlg-head">
      <div>
        <h2 id="dlg-title" data-dlg-name>Pizza</h2>
        <p class="dlg-sub"><span data-dlg-price></span> / kúsok · prílohy max. <?= PS_MAX_TOPPINGS ?></p>
      </div>
      <button class="icon-btn" type="submit" value="cancel" formnovalidate aria-label="Zavrieť">×</button>
    </div>
    <?php if ($menu['toppings']): ?>
    <fieldset class="tops">
      <legend>Prílohy navyše <small>(nepovinné)</small></legend>
      <?php foreach ($menu['toppings'] as $t): ?>
      <label class="top">
        <input type="checkbox" name="t" value="<?= $t['id'] ?>">
        <span class="top-n"><?= e($t['name']) ?></span>
        <span class="top-p">+<?= e(money($t['price_cents'])) ?></span>
      </label>
      <?php endforeach; ?>
    </fieldset>
    <?php endif; ?>
    <div class="dlg-foot">
      <div class="stepper stepper-lg">
        <button type="button" data-dlg-dec aria-label="Menej kúskov">−</button>
        <output data-dlg-qty aria-live="polite">1</output>
        <button type="button" data-dlg-inc aria-label="Viac kúskov">+</button>
      </div>
      <button class="btn btn-primary dlg-add" type="submit" value="add">Pridať · <span data-dlg-total></span></button>
    </div>
  </form>
</dialog>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script type="application/json" id="ps-data"><?= json_encode(menu_js_data($menu), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php page_end();
