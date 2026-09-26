<?php
require dirname(__DIR__) . '/app/admin_bootstrap.php';
require PS_APPDIR . '/lib/images.php';

require_admin();
$pdo = db();
$menu = menu_load(true);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $id ? ($menu['items'][$id] ?? null) : null;
if ($id && !$item) {
    redirect('/admin/menu.php');
}
$catId = (int) ($item['category_id'] ?? $_GET['cat'] ?? array_key_first($menu['categories']));
$cat = $menu['categories'][$catId] ?? reset($menu['categories']);
$errors = [];

$v = $item ?? [
    'name' => '', 'description' => '', 'badge' => (int) $cat['is_special'] ? 'Špecialita' : '',
    'unit_label' => $cat['icon'] === 'slice' ? '1 kúsok' : '', 'price_cents' => 0, 'image' => null,
    'allow_toppings' => $cat['icon'] === 'slice' ? 1 : 0, 'is_available' => 1, 'sort_order' => 0,
    'category_id' => $cat['id'],
];
$priceStr = $item ? number_format($item['price_cents'] / 100, 2, ',', '') : '';

if (is_post()) {
    admin_require_csrf();
    if (post_str('action', 10) === 'delete' && $item) {
        $pdo->prepare('DELETE FROM menu_items WHERE id = ?')->execute([$id]);
        image_delete($item['image']);
        redirect('/admin/menu.php?ok=deleted');
    }

    $v['name'] = post_str('name', 100);
    $v['description'] = post_str('description', 300);
    $v['badge'] = post_str('badge', 30);
    $v['unit_label'] = post_str('unit_label', 30);
    $v['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $v['allow_toppings'] = isset($_POST['allow_toppings']) ? 1 : 0;
    $v['is_available'] = isset($_POST['is_available']) ? 1 : 0;
    $v['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $priceStr = post_str('price', 20);
    $price = parse_money($priceStr);

    if ($v['name'] === '') {
        $errors[] = 'Zadajte názov.';
    }
    if ($price === null || $price <= 0) {
        $errors[] = 'Zadajte cenu v eurách, napr. 2,50.';
    }
    if (!isset($menu['categories'][$v['category_id']])) {
        $errors[] = 'Vyberte kategóriu.';
    }
    $newImage = null;
    if (!$errors) {
        [$newImage, $imgErr] = image_store_upload($_FILES['image'] ?? [], 'item');
        if ($imgErr !== '') {
            $errors[] = $imgErr;
        }
    }
    if (!$errors) {
        $image = $v['image'];
        if ($newImage) {
            image_delete($image);
            $image = $newImage;
        } elseif (isset($_POST['remove_image'])) {
            image_delete($image);
            $image = null;
        }
        $now = now_str();
        if ($item) {
            $pdo->prepare('UPDATE menu_items SET category_id = ?, name = ?, description = ?, badge = ?, unit_label = ?, price_cents = ?,
                            image = ?, allow_toppings = ?, is_available = ?, sort_order = ?, updated_at = ? WHERE id = ?')
                ->execute([$v['category_id'], $v['name'], $v['description'], $v['badge'], $v['unit_label'], $price,
                    $image, $v['allow_toppings'], $v['is_available'], $v['sort_order'], $now, $id]);
        } else {
            if ($v['sort_order'] === 0) {
                $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM menu_items WHERE category_id = ?');
                $st->execute([$v['category_id']]);
                $v['sort_order'] = (int) $st->fetchColumn();
            }
            $pdo->prepare('INSERT INTO menu_items (category_id, name, description, badge, unit_label, price_cents, image, allow_toppings,
                            is_available, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$v['category_id'], $v['name'], $v['description'], $v['badge'], $v['unit_label'], $price,
                    $image, $v['allow_toppings'], $v['is_available'], $v['sort_order'], $now, $now]);
        }
        redirect('/admin/menu.php?ok=' . ($item ? 'saved' : 'added'));
    }
}

admin_start($item ? 'Upraviť položku' : 'Nová položka', 'menu');
?>
<a class="a-back" href="/admin/menu.php">← Späť na menu</a>
<?php foreach ($errors as $err): ?><p class="a-err" role="alert"><?= e($err) ?></p><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="a-card a-form">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <label class="a-field"><span>Názov *</span>
    <input class="a-input" name="name" required maxlength="100" value="<?= e($v['name']) ?>" placeholder="napr. Quattro formaggi">
  </label>
  <div class="a-grid2">
    <label class="a-field"><span>Cena (€) *</span>
      <input class="a-input" name="price" required inputmode="decimal" value="<?= e($priceStr) ?>" placeholder="2,50">
    </label>
    <label class="a-field"><span>Jednotka</span>
      <input class="a-input" name="unit_label" maxlength="30" value="<?= e($v['unit_label']) ?>" placeholder="1 kúsok / 0,5 l">
    </label>
  </div>
  <label class="a-field"><span>Kategória</span>
    <select class="a-input" name="category_id">
      <?php foreach ($menu['categories'] as $c): ?>
        <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === (int) $v['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="a-field"><span>Popis / zloženie</span>
    <textarea class="a-input" name="description" rows="3" maxlength="300" placeholder="Paradajková omáčka, mozzarella, …"><?= e($v['description']) ?></textarea>
  </label>
  <div class="a-grid2">
    <label class="a-field"><span>Štítok</span>
      <input class="a-input" name="badge" maxlength="30" value="<?= e($v['badge']) ?>" placeholder="Novinka / Len dnes">
    </label>
    <label class="a-field"><span>Poradie</span>
      <input class="a-input" name="sort_order" type="number" value="<?= (int) $v['sort_order'] ?>">
    </label>
  </div>
  <label class="a-check"><input type="checkbox" name="is_available"<?= (int) $v['is_available'] ? ' checked' : '' ?>> Dostupné (dá sa objednať)</label>
  <label class="a-check"><input type="checkbox" name="allow_toppings"<?= (int) $v['allow_toppings'] ? ' checked' : '' ?>> Zákazník si môže pridať prílohy</label>

  <div class="a-field">
    <span>Fotka (nepovinné)</span>
    <?php if ($v['image']): ?>
      <div class="a-photo">
        <img src="/uploads/<?= e($v['image']) ?>" width="96" height="96" alt="">
        <label class="a-check"><input type="checkbox" name="remove_image"> Odstrániť fotku</label>
      </div>
    <?php endif; ?>
    <input class="a-input a-file" type="file" name="image" accept="image/jpeg,image/png,image/webp">
    <small class="a-muted">Orežeme ju na štvorec a zmenšíme, aby sa web načítal rýchlo.</small>
  </div>

  <div class="a-actions a-actions-sticky">
    <button class="a-btn a-btn-primary a-btn-block" type="submit"><?= $item ? 'Uložiť zmeny' : 'Pridať do menu' ?></button>
  </div>
</form>

<?php if ($item): ?>
<form method="post" class="a-danger-zone" data-confirm="Naozaj zmazať <?= e($item['name']) ?>? Ak ju chcete len dočasne skryť, vypnite „Dostupné“.">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <input type="hidden" name="action" value="delete">
  <button class="a-btn a-btn-danger a-btn-block" type="submit">Zmazať položku</button>
</form>
<?php endif; ?>
<?php admin_end();
