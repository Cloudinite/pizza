<?php
require dirname(__DIR__) . '/app/admin_bootstrap.php';

require_admin();
$error = '';

function slugify(string $s): string
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l', 'ľ' => 'l',
        'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z']);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return $s !== '' ? substr($s, 0, 50) : 'kategoria';
}

if (is_post()) {
    admin_require_csrf();
    $pdo = db();
    $action = post_str('action', 30);
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'topping_save' || $action === 'topping_add') {
        $name = post_str('name', 60);
        $price = parse_money(post_str('price', 20));
        if ($name === '' || $price === null) {
            $error = 'Príloha potrebuje názov a cenu (napr. 0,50).';
        } elseif ($action === 'topping_add') {
            $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM toppings')->fetchColumn();
            $pdo->prepare('INSERT INTO toppings (name, price_cents, is_available, sort_order) VALUES (?, ?, 1, ?)')->execute([$name, $price, $sort]);
            redirect('/admin/menu.php?ok=added#toppings');
        } else {
            $pdo->prepare('UPDATE toppings SET name = ?, price_cents = ?, sort_order = ? WHERE id = ?')
                ->execute([$name, $price, (int) ($_POST['sort_order'] ?? 0), $id]);
            redirect('/admin/menu.php?ok=saved#toppings');
        }
    } elseif ($action === 'topping_delete') {
        $pdo->prepare('DELETE FROM toppings WHERE id = ?')->execute([$id]);
        redirect('/admin/menu.php?ok=deleted#toppings');
    } elseif ($action === 'category_save' || $action === 'category_add') {
        $name = post_str('name', 80);
        $icon = in_array($_POST['icon'] ?? '', ['slice', 'drink', 'star'], true) ? $_POST['icon'] : 'slice';
        if ($name === '') {
            $error = 'Kategória potrebuje názov.';
        } elseif ($action === 'category_add') {
            $slug = slugify($name);
            $exists = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = ?');
            $exists->execute([$slug]);
            if ((int) $exists->fetchColumn()) {
                $slug .= '-' . random_int(10, 99);
            }
            $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories')->fetchColumn();
            $pdo->prepare('INSERT INTO categories (slug, name, subtitle, icon, is_special, is_visible, sort_order) VALUES (?, ?, ?, ?, 0, 1, ?)')
                ->execute([$slug, $name, post_str('subtitle', 120), $icon, $sort]);
            redirect('/admin/menu.php?ok=added#categories');
        } else {
            $pdo->prepare('UPDATE categories SET name = ?, subtitle = ?, icon = ?, is_special = ?, is_visible = ?, sort_order = ? WHERE id = ?')
                ->execute([$name, post_str('subtitle', 120), $icon, isset($_POST['is_special']) ? 1 : 0, isset($_POST['is_visible']) ? 1 : 0, (int) ($_POST['sort_order'] ?? 0), $id]);
            redirect('/admin/menu.php?ok=saved#categories');
        }
    } elseif ($action === 'category_delete') {
        $n = $pdo->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id = ?');
        $n->execute([$id]);
        if ((int) $n->fetchColumn() > 0) {
            $error = 'Kategóriu s položkami nemožno zmazať. Najprv presuňte alebo zmažte jej položky.';
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            redirect('/admin/menu.php?ok=deleted#categories');
        }
    }
}

$menu = menu_load(true);
admin_start('Menu', 'menu');

function item_rows(array $items): void
{
    if (!$items) {
        echo '<p class="a-muted">Zatiaľ prázdne.</p>';
        return;
    }
    echo '<ul class="a-list">';
    foreach ($items as $it) {
        $on = (int) $it['is_available'];
        echo '<li class="a-row' . ($on ? '' : ' is-off') . '">'
            . '<a class="a-row-main" href="/admin/item.php?id=' . $it['id'] . '">'
            . '<b>' . e($it['name']) . ($it['badge'] !== '' ? ' <span class="a-tag">' . e($it['badge']) . '</span>' : '') . '</b>'
            . '<span>' . e(money($it['price_cents'])) . ($it['unit_label'] !== '' ? ' · ' . e($it['unit_label']) : '') . ((int) $it['allow_toppings'] ? ' · s prílohami' : '') . '</span></a>'
            . '<label class="a-switch" title="Dostupné"><input type="checkbox" data-toggle="item" data-id="' . $it['id'] . '"' . ($on ? ' checked' : '') . '>'
            . '<span class="a-switch-ui" aria-hidden="true"></span><span class="sr">Dostupné: ' . e($it['name']) . '</span></label>'
            . '</li>';
    }
    echo '</ul>';
}
?>
<?php if ($error !== ''): ?><p class="a-err" role="alert"><?= e($error) ?></p><?php endif; ?>

<?php foreach ($menu['categories'] as $c): if (!(int) $c['is_special']) { continue; } ?>
<section class="a-card a-card-special">
  <div class="a-card-h">
    <h2>⭐ <?= e($c['name']) ?></h2>
    <a class="a-btn a-btn-sm a-btn-primary" href="/admin/item.php?cat=<?= (int) $c['id'] ?>">+ Pridať</a>
  </div>
  <p class="a-muted">Zobrazuje sa na úvodnej stránke. Vypnutá špecialita sa na webe skryje.</p>
  <?php item_rows($c['items']); ?>
</section>
<?php endforeach; ?>

<?php foreach ($menu['categories'] as $c): if ((int) $c['is_special']) { continue; } ?>
<section class="a-card">
  <div class="a-card-h">
    <h2><?= e($c['name']) ?><?= (int) $c['is_visible'] ? '' : ' <span class="a-tag">skrytá</span>' ?></h2>
    <a class="a-btn a-btn-sm" href="/admin/item.php?cat=<?= (int) $c['id'] ?>">+ Pridať</a>
  </div>
  <?php item_rows($c['items']); ?>
</section>
<?php endforeach; ?>

<section class="a-card" id="toppings">
  <div class="a-card-h"><h2>Prílohy navyše</h2></div>
  <p class="a-muted">Cena je za jeden kúsok. Prepínačom dočasne vypnete prílohu, ktorá došla.</p>
  <ul class="a-list">
    <?php foreach ($menu['toppings'] as $t): ?>
    <li class="a-row a-row-form<?= (int) $t['is_available'] ? '' : ' is-off' ?>">
      <form method="post" class="a-inline">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="topping_save">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <input type="hidden" name="sort_order" value="<?= (int) $t['sort_order'] ?>">
        <input class="a-input" name="name" value="<?= e($t['name']) ?>" aria-label="Názov prílohy" required maxlength="60">
        <input class="a-input a-input-price" name="price" value="<?= e(number_format($t['price_cents'] / 100, 2, ',', '')) ?>" aria-label="Cena v eurách" inputmode="decimal" required>
        <button class="a-btn a-btn-sm" type="submit">Uložiť</button>
      </form>
      <label class="a-switch" title="Dostupné"><input type="checkbox" data-toggle="topping" data-id="<?= $t['id'] ?>"<?= (int) $t['is_available'] ? ' checked' : '' ?>><span class="a-switch-ui" aria-hidden="true"></span><span class="sr">Dostupné: <?= e($t['name']) ?></span></label>
      <form method="post" data-confirm="Zmazať prílohu <?= e($t['name']) ?>?">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="topping_delete">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <button class="a-icon-btn" type="submit" aria-label="Zmazať <?= e($t['name']) ?>">🗑</button>
      </form>
    </li>
    <?php endforeach; ?>
  </ul>
  <form method="post" class="a-inline a-add">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="topping_add">
    <input class="a-input" name="name" placeholder="Nová príloha" aria-label="Názov novej prílohy" required maxlength="60">
    <input class="a-input a-input-price" name="price" placeholder="0,50" aria-label="Cena novej prílohy" inputmode="decimal" required>
    <button class="a-btn a-btn-sm a-btn-primary" type="submit">Pridať</button>
  </form>
</section>

<section class="a-card" id="categories">
  <div class="a-card-h"><h2>Kategórie</h2></div>
  <?php foreach ($menu['categories'] as $c): ?>
  <details class="a-details">
    <summary><b><?= e($c['name']) ?></b> <span class="a-muted"><?= count($c['items']) ?> položiek</span></summary>
    <form method="post" class="a-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="category_save">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <label class="a-field"><span>Názov</span><input class="a-input" name="name" value="<?= e($c['name']) ?>" required maxlength="80"></label>
      <label class="a-field"><span>Podnadpis</span><input class="a-input" name="subtitle" value="<?= e($c['subtitle']) ?>" maxlength="120"></label>
      <div class="a-grid2">
        <label class="a-field"><span>Ikona</span>
          <select class="a-input" name="icon">
            <?php foreach (['slice' => 'Pizza', 'drink' => 'Nápoj', 'star' => 'Hviezda'] as $k => $lbl): ?>
              <option value="<?= $k ?>"<?= $c['icon'] === $k ? ' selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="a-field"><span>Poradie</span><input class="a-input" name="sort_order" type="number" value="<?= (int) $c['sort_order'] ?>"></label>
      </div>
      <label class="a-check"><input type="checkbox" name="is_visible"<?= (int) $c['is_visible'] ? ' checked' : '' ?>> Zobraziť na webe</label>
      <label class="a-check"><input type="checkbox" name="is_special"<?= (int) $c['is_special'] ? ' checked' : '' ?>> Špecialita (zvýraznená na úvode)</label>
      <div class="a-actions">
        <button class="a-btn a-btn-primary" type="submit">Uložiť</button>
      </div>
    </form>
    <?php if (!$c['items']): ?>
    <form method="post" data-confirm="Zmazať kategóriu <?= e($c['name']) ?>?">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="category_delete">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <button class="a-btn a-btn-danger a-btn-sm" type="submit">Zmazať kategóriu</button>
    </form>
    <?php endif; ?>
  </details>
  <?php endforeach; ?>
  <form method="post" class="a-inline a-add">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="category_add">
    <input type="hidden" name="icon" value="slice">
    <input class="a-input" name="name" placeholder="Nová kategória, napr. Dezerty" aria-label="Názov novej kategórie" required maxlength="80">
    <button class="a-btn a-btn-sm a-btn-primary" type="submit">Pridať</button>
  </form>
</section>
<?php admin_end();
