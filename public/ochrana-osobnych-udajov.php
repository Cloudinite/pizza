<?php
require __DIR__ . '/app/bootstrap.php';

$s = settings();
$operator = $s['company_name'] !== '' ? $s['company_name'] : $s['site_name'];
$days = max(7, setting_int('retention_days'));

page_start([
    'title' => 'Ochrana osobných údajov – Pizza Slice Pezinok',
    'description' => 'Ako Pizza Slice Pezinok spracúva osobné údaje pri online objednávkach: rozsah, účel, doba uchovávania a vaše práva podľa GDPR.',
    'path' => '/ochrana-osobnych-udajov',
]);
?>
<section class="page-h page-h-sm">
  <div class="wrap">
    <p class="eyebrow">Informácie</p>
    <h1>Ochrana osobných údajov</h1>
  </div>
</section>
<div class="wrap">
  <article class="prose">
    <p>Tieto zásady vysvetľujú, ako spracúvame osobné údaje pri používaní webu a online objednávok v súlade s nariadením (EÚ) 2016/679 (GDPR) a zákonom č. 18/2018 Z. z. o ochrane osobných údajov.</p>

    <h2>1. Prevádzkovateľ</h2>
    <p>
      <b><?= e($operator) ?></b><br>
      <?php if ($s['company_ico'] !== ''): ?>IČO: <?= e($s['company_ico']) ?><br><?php endif; ?>
      <?php if ($s['company_address'] !== ''): ?>Sídlo: <?= e($s['company_address']) ?><br><?php endif; ?>
      Prevádzka: <?= e(address_line() !== '' ? address_line() : $s['city']) ?><br>
      <?php if ($s['email'] !== ''): ?>E-mail: <a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a><br><?php endif; ?>
      <?php if ($s['phone'] !== ''): ?>Telefón: <a href="<?= e(phone_href($s['phone'])) ?>"><?= e($s['phone']) ?></a><?php endif; ?>
    </p>

    <h2>2. Aké údaje spracúvame</h2>
    <ul>
      <li><b>Pri objednávke:</b> meno, telefónne číslo, e-mail (nepovinný), adresa doručenia (len pri donáške), poznámka k objednávke, obsah objednávky, zvolený čas a spôsob platby.</li>
      <li><b>Technické údaje:</b> pseudonymizovaný (hashovaný) identifikátor IP adresy na ochranu pred zneužitím formulárov. Samotnú IP adresu v databáze neukladáme.</li>
    </ul>
    <p>Na webe nepoužívame analytické ani reklamné nástroje a nevytvárame profily návštevníkov.</p>

    <h2>3. Účel a právny základ</h2>
    <ul>
      <li><b>Prijatie a vybavenie objednávky</b> vrátane kontaktovania v prípade potreby – plnenie zmluvy (čl. 6 ods. 1 písm. b) GDPR).</li>
      <li><b>Ochrana webu pred zneužitím</b> (obmedzenie počtu objednávok a pokusov o prihlásenie) – oprávnený záujem (čl. 6 ods. 1 písm. f) GDPR).</li>
    </ul>

    <h2>4. Ako dlho údaje uchovávame</h2>
    <p>Kontaktné údaje z objednávky uchovávame <?= $days ?> dní od jej vytvorenia, potom ich systém automaticky vymaže a v evidencii ostane len anonymný obsah objednávky (položky a suma). Pseudonymizované technické záznamy sa mažú do 24 hodín.</p>

    <h2>5. Kto má k údajom prístup</h2>
    <p>K údajom má prístup iba personál prevádzky cez zabezpečenú administráciu. Údaje sú uložené na serveroch poskytovateľa webhostingu WebSupport s.r.o. (Bratislava, Slovensko), ktorý ich spracúva ako sprostredkovateľ. Údaje neposkytujeme tretím stranám na marketingové účely a neprenášame ich mimo Európskej únie.</p>

    <h2>6. Zabezpečenie</h2>
    <p>Celá komunikácia s webom je šifrovaná (HTTPS). Kontaktné údaje z objednávok sú v databáze uložené šifrovane a formuláre sú chránené proti podvrhnutiu (CSRF) a automatizovanému zneužitiu.</p>

    <h2>7. Vaše práva</h2>
    <p>Máte právo na prístup k svojim údajom, ich opravu, vymazanie, obmedzenie spracúvania, prenosnosť a právo namietať proti spracúvaniu založenému na oprávnenom záujme. Stačí nás kontaktovať<?= $s['email'] !== '' ? ' na <a href="mailto:' . e($s['email']) . '">' . e($s['email']) . '</a>' : '' ?>.</p>
    <p>Ak sa domnievate, že spracúvanie je v rozpore s predpismi, môžete podať sťažnosť dozornému orgánu: Úrad na ochranu osobných údajov Slovenskej republiky, Hraničná 12, 820 07 Bratislava 27, <a href="https://dataprotection.gov.sk" rel="noopener" target="_blank">dataprotection.gov.sk</a>.</p>

    <h2>8. Automatizované rozhodovanie</h2>
    <p>Nevykonávame automatizované rozhodovanie ani profilovanie.</p>

    <h2>9. Cookies</h2>
    <p>Používame iba technicky nevyhnutné cookies. Podrobnosti nájdete v <a href="/cookies">zásadách používania cookies</a>.</p>
  </article>
</div>
<?php page_end();
