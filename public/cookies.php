<?php
require __DIR__ . '/app/bootstrap.php';

page_start([
    'title' => 'Zásady používania cookies – Pizza Slice Pezinok',
    'description' => 'Pizza Slice Pezinok používa iba technicky nevyhnutné cookies pre košík a bezpečnosť objednávok. Žiadna analytika ani reklama.',
    'path' => '/cookies',
]);
?>
<section class="page-h page-h-sm">
  <div class="wrap">
    <p class="eyebrow">Informácie</p>
    <h1>Zásady používania cookies</h1>
  </div>
</section>
<div class="wrap">
  <article class="prose">
    <p>Cookies sú malé textové súbory, ktoré si web ukladá vo vašom prehliadači. Náš web používa <b>iba technicky nevyhnutné cookies</b>, bez ktorých by nefungoval košík a bezpečné odoslanie objednávky. Nepoužívame analytické, reklamné ani sledovacie cookies tretích strán, preto od vás nežiadame súhlas (§ 109 ods. 8 zákona č. 452/2021 Z. z. o elektronických komunikáciách).</p>

    <h2>Zoznam cookies</h2>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th scope="col">Názov</th><th scope="col">Účel</th><th scope="col">Platnosť</th></tr></thead>
        <tbody>
          <tr><td><code>ps_cart</code></td><td>Obsah vášho košíka (ID položiek, počet kusov a prílohy).</td><td>2 dni</td></tr>
          <tr><td><code>ps_coupon</code></td><td>Zľavový kód, ktorý ste zadali v košíku.</td><td>1 deň</td></tr>
          <tr><td><code>ps_track</code></td><td>Odkaz na sledovanie vašej poslednej objednávky (kód objednávky a tajný kľúč).</td><td>24 hodín</td></tr>
          <tr><td><code>ps_csrf</code></td><td>Bezpečnostný token, ktorý chráni objednávkový formulár pred podvrhnutím.</td><td>do zatvorenia prehliadača</td></tr>
          <tr><td><code>__Host-ps_admin</code></td><td>Prihlásenie personálu do administrácie. Návštevníkov webu sa netýka.</td><td>30 dní</td></tr>
          <tr><td><code>ps_2fa</code></td><td>Krátkodobé potvrdenie prvého kroku prihlásenia personálu (overenie v dvoch krokoch).</td><td>5 minút</td></tr>
        </tbody>
      </table>
    </div>

    <h2>Odkazy na sociálne siete</h2>
    <p>Odkazy na Facebook a Instagram sú obyčajné odkazy – na našom webe nevkladáme ich skripty ani tlačidlá. Cookies týchto služieb sa môžu uložiť až potom, ako na ich stránku prejdete. Ich spracúvanie sa riadi zásadami spoločnosti Meta.</p>

    <h2>Ako cookies spravovať</h2>
    <p>Cookies môžete kedykoľvek vymazať alebo zablokovať v nastaveniach svojho prehliadača. Ak nevyhnutné cookies zablokujete, košík a online objednávka nebudú fungovať – objednať si však môžete priamo na prevádzke.</p>

    <p>Viac o spracúvaní osobných údajov nájdete v <a href="/ochrana-osobnych-udajov">zásadách ochrany osobných údajov</a>.</p>
  </article>
</div>
<?php page_end();
