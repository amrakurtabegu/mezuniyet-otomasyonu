<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();
$sekme = ($_GET['sekme'] ?? '') === 'gecisler' ? 'gecisler' : 'kayitlar';
$kayitlar = $pdo->query('SELECT l.*, k.ad_soyad FROM islem_loglari l LEFT JOIN kullanicilar k ON k.id = l.kullanici_id ORDER BY l.id DESC LIMIT 300')->fetchAll();
$gecisler = $pdo->query('SELECT g.*, k.ad_soyad, o.ogrenci_no, o.ad_soyad AS ogrenci FROM basvuru_gecmisi g LEFT JOIN kullanicilar k ON k.id = g.kullanici_id
    JOIN basvurular b ON b.id = g.basvuru_id JOIN ogrenciler o ON o.id = b.ogrenci_id ORDER BY g.id DESC LIMIT 300')->fetchAll();

layout_header('İşlem Geçmişi', ['alt' => 'Sistemdeki önemli işlemler ve başvuru durum geçişleri (son 300 kayıt).']);
?>
<section class="kart">
  <div class="cipler" style="margin-bottom:12px">
    <a class="cip<?= $sekme === 'kayitlar' ? ' aktif' : '' ?>" href="?sekme=kayitlar">İşlem kayıtları<b><?= count($kayitlar) ?></b></a>
    <a class="cip<?= $sekme === 'gecisler' ? ' aktif' : '' ?>" href="?sekme=gecisler">Başvuru durum geçişleri<b><?= count($gecisler) ?></b></a>
  </div>
  <div class="arac"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="log" placeholder="Kayıtlarda ara" aria-label="Ara"></div></div>
  <div class="tablo-kap"><table class="tablo" data-tablo="log">
  <?php if ($sekme === 'kayitlar'): ?>
    <thead><tr><th data-sirala>Tarih</th><th data-sirala>Kullanıcı</th><th data-sirala>Başvuru</th><th>İşlem</th></tr></thead><tbody>
    <?php foreach ($kayitlar as $r) echo '<tr><td data-v="' . e($r['tarih']) . '">' . e(tarih_tr($r['tarih'], true)) . '</td><td>' . e($r['ad_soyad'] ?? 'Sistem') . '</td><td>' . e($r['basvuru_id'] ?? '–') . '</td><td>' . e($r['islem']) . '</td></tr>'; ?>
  <?php else: ?>
    <thead><tr><th data-sirala>Tarih</th><th data-sirala>Öğrenci</th><th>Eski durum</th><th>Yeni durum</th><th data-sirala>Kim</th><th>Açıklama</th></tr></thead><tbody>
    <?php foreach ($gecisler as $r) echo '<tr><td data-v="' . e($r['tarih']) . '">' . e(tarih_tr($r['tarih'], true)) . '</td><td>' . e($r['ogrenci_no']) . ' – ' . e($r['ogrenci']) . '</td><td>' . ($r['eski_durum'] ? durum_rozeti($r['eski_durum']) : '–') . '</td><td>' . durum_rozeti($r['yeni_durum']) . '</td><td>' . e($r['ad_soyad'] ?? 'Öğrenci / Sistem') . '</td><td>' . e($r['aciklama']) . '</td></tr>'; ?>
  <?php endif; ?>
    <tr class="bos-satir" style="display:none"><td colspan="6">Kayıt bulunamadı.</td></tr></tbody></table></div>
  <div class="tablo-alt"><span data-sayac="log"></span></div>
</section>
<?php layout_footer();
