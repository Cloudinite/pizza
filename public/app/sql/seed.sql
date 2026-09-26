-- Pizza Slice Pezinok – starting menu (from the in-store price boards).
-- Pizza descriptions and topping prices are editable in the admin app.

SET NAMES utf8mb4;

INSERT INTO categories (id, slug, name, subtitle, icon, is_special, is_visible, sort_order) VALUES
  (1, 'specialita', 'Špecialita',     'Aktuálna ponuka – len na chvíľu', 'star',  1, 1, 0),
  (2, 'pizza',      'Pizza na kúsky', '10 druhov · vždy čerstvé',        'slice', 0, 1, 10),
  (3, 'napoje',     'Nápoje',         '6 druhov · chladené',             'drink', 0, 1, 20);

INSERT INTO menu_items (category_id, name, description, badge, unit_label, price_cents, allow_toppings, is_available, sort_order, created_at, updated_at) VALUES
  (1, 'Quattro formaggi', 'Ukážková špecialita – štyri druhy syra. Uprav alebo zmaž v administrácii.', 'Špecialita', '1 kúsok', 290, 1, 1, 0, NOW(), NOW()),

  (2, 'Margherita',            'Paradajková omáčka, mozzarella, bazalka',                     '', '1 kúsok', 250, 1, 1, 10,  NOW(), NOW()),
  (2, 'Hawai',                 'Paradajková omáčka, mozzarella, šunka, ananás',               '', '1 kúsok', 250, 1, 1, 20,  NOW(), NOW()),
  (2, 'Vegetariánska',         'Paradajková omáčka, mozzarella, zelenina, šampiňóny, olivy',  '', '1 kúsok', 250, 1, 1, 30,  NOW(), NOW()),
  (2, 'Salámová',              'Paradajková omáčka, mozzarella, saláma',                      '', '1 kúsok', 250, 1, 1, 40,  NOW(), NOW()),
  (2, 'Šunka & kukurica',      'Paradajková omáčka, mozzarella, šunka, kukurica',             '', '1 kúsok', 250, 1, 1, 50,  NOW(), NOW()),
  (2, 'Šunka & šampiňóny',     'Paradajková omáčka, mozzarella, šunka, šampiňóny',            '', '1 kúsok', 250, 1, 1, 60,  NOW(), NOW()),
  (2, 'Šampiňóny & kukurica',  'Paradajková omáčka, mozzarella, šampiňóny, kukurica',         '', '1 kúsok', 250, 1, 1, 70,  NOW(), NOW()),
  (2, 'Pikantná',              'Paradajková omáčka, mozzarella, pikantná saláma, feferóny',   '', '1 kúsok', 250, 1, 1, 80,  NOW(), NOW()),
  (2, 'Sedliacka',             'Paradajková omáčka, mozzarella, slanina, klobása, cibuľa',    '', '1 kúsok', 250, 1, 1, 90,  NOW(), NOW()),
  (2, 'Tuniaková',             'Paradajková omáčka, mozzarella, tuniak, cibuľa',              '', '1 kúsok', 250, 1, 1, 100, NOW(), NOW()),

  (3, 'Targa Florio',          'Talianska limonáda', '', '', 220, 0, 1, 10, NOW(), NOW()),
  (3, 'Kofola',                '',                   '', '', 220, 0, 1, 20, NOW(), NOW()),
  (3, 'Royal Crown',           'Cola',               '', '', 220, 0, 1, 30, NOW(), NOW()),
  (3, 'Orange',                '',                   '', '', 220, 0, 1, 40, NOW(), NOW()),
  (3, 'Nesýtená voda Rajec',   '',                   '', '', 220, 0, 1, 50, NOW(), NOW()),
  (3, 'Rajec 321',             '',                   '', '', 220, 0, 1, 60, NOW(), NOW());

INSERT INTO toppings (name, price_cents, is_available, sort_order) VALUES
  ('Extra syr',   50, 1, 10),
  ('Šunka',       60, 1, 20),
  ('Saláma',      60, 1, 30),
  ('Slanina',     60, 1, 40),
  ('Tuniak',      70, 1, 50),
  ('Šampiňóny',   40, 1, 60),
  ('Kukurica',    40, 1, 70),
  ('Olivy',       40, 1, 80),
  ('Ananás',      40, 1, 90),
  ('Feferóny',    40, 1, 100),
  ('Jalapeño',    40, 1, 110),
  ('Cibuľa',      30, 1, 120);
