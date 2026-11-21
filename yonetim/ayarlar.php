<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);   // yalnızca yetkili rol; bu sürümde ayarlar salt okunurdur (kural değişikliği hocadan teyitle veritabanında yapılır)
$pdo = db();
$kurallar = $pdo->query('SELECT * FROM bolum_kurallari ORDER BY kod')->fetchAll();
$demo = (int)$pdo->query('SELECT COUNT(*) FROM kullanicilar WHERE demo_hesap = 1')->fetchColumn();
$mock = (int)$pdo->query("SELECT COUNT(*) FROM akademik_kayitlari WHERE kaynak = 'mock'")->fetchColumn();
layout_header('Ayarlar', ['alt' => 'Sistem kuralları ve veri kaynağı bilgisi (salt okunur).']);
?>
<section class="kart"><div class="kart-baslik"><h2><?= ikon('ayar') ?> Mezuniyet kuralları</h2></div>
  <div class="tablo-kap"><table class="tablo"><thead><tr><th>Kural</th><th>Değer</th><th>Açıklama</th><th>Doğrulama</th></tr></thead><tbody>
    <?php foreach ($kurallar as $r) echo '<tr><td><code>' . e($r['kod']) . '</code></td><td><b>' . e($r['deger'] ?? '–') . '</b></td><td>' . e($r['aciklama']) . '</td><td>' . rozet($r['dogrulandi'] ? 'Doğrulandı' : 'Hocadan teyit bekliyor', $r['dogrulandi'] ? 'yesil' : 'turuncu') . '</td></tr>'; ?>
  </tbody></table></div></section>
<div class="iki-kolon">
  <section class="kart"><div class="kart-baslik"><h2>Komisyon</h2></div>
    <dl class="ozet-izgara" style="border:0;padding:0;margin:0"><div><dt>Üye sayısı</dt><dd><?= (int)KOMISYON_UYE_SAYISI ?></dd></div><div><dt>Gereken onay</dt><dd><?= (int)ONAY_ESIGI ?></dd></div><div><dt>Ret eşiği</dt><dd><?= (int)(KOMISYON_UYE_SAYISI - ONAY_ESIGI + 1) ?></dd></div></dl></section>
  <section class="kart"><div class="kart-baslik"><h2>Veri kaynağı</h2></div>
    <p>Demo hesap sayısı: <b><?= $demo ?></b> · Simülasyon (DEMO) akademik kaydı: <b><?= $mock ?></b></p>
    <p class="muted">Öğrenci verileri şu an <code>includes/mock_servis.php</code> üzerinden simüle edilir. E-imza adımı gerçek değil, simülasyondur. Sekreterin mezun iletişim bilgisini görmesi: <b><?= SEKRETER_ILETISIM_GORUR ? 'açık' : 'kapalı' ?></b> (<code>config/db.php</code>).</p></section>
</div>
<?php layout_footer();
