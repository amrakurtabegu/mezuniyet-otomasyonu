<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();
$sayi = $pdo->query('SELECT durum, COUNT(*) FROM basvurular GROUP BY durum')->fetchAll(PDO::FETCH_KEY_PAIR);
$acikDonem = (int)$pdo->query("SELECT COUNT(*) FROM basvuru_donemleri WHERE durum = 'acik'")->fetchColumn();
$acikListe = (int)$pdo->query("SELECT COUNT(*) FROM mezuniyet_listeleri WHERE durum = 'olusturuldu'")->fetchColumn();
$son = $pdo->query('SELECT g.*, k.ad_soyad, o.ogrenci_no FROM basvuru_gecmisi g LEFT JOIN kullanicilar k ON k.id = g.kullanici_id JOIN basvurular b ON b.id = g.basvuru_id JOIN ogrenciler o ON o.id = b.ogrenci_id ORDER BY g.id DESC LIMIT 8')->fetchAll();
$asama = [
    ['Belge Onayları', (int)($sayi['belge_hazirlaniyor'] ?? 0), 'Sekreterlikten gelen belgeleri onayla', '/yonetim/belge_onaylari.php', 'oy', 'turuncu'],
    ['Başvuru Dönemleri', $acikDonem, 'Açık dönem; kapatınca liste oluşturulabilir', '/yonetim/donemler.php', 'takvim', 'mavi'],
    ['Mezuniyet Listeleri', (int)($sayi['belge_onayli'] ?? 0), 'Listeye alınmayı bekleyen öğrenci', '/yonetim/listeler.php', 'liste', 'mavi'],
    ['E-İmza Süreci', (int)($sayi['eimza_surecinde'] ?? 0), 'Liste kapandı; son onay bekliyor', '/yonetim/eimza.php', 'imza', 'turuncu'],
    ['Belgeler', (int)($sayi['tamamlandi'] ?? 0), 'Tamamlanan e-imzalı belgeler', '/yonetim/belgeler.php', 'belge', 'yesil'],
];
layout_header('Bölüm Başkanı / Bilgi İşlem Paneli', ['alt' => 'Süreç beş ayrı ekrana bölünmüştür. Her kutu ilgili aşamanın ekranını açar ve bekleyen sayısını gösterir.']);
?>
<div class="kpi-izgara">
  <?php foreach ($asama as [$ad, $n, $not, $url, $ik, $ton]) echo '<a href="' . e(BASE_URL . $url) . '" style="text-decoration:none;color:inherit">' . kpi_karti($ad, $n, $not, $ton, $ik) . '</a>'; ?>
</div>
<section class="kart">
  <div class="kart-baslik"><h2><?= ikon('surec') ?> Süreç hattı</h2><span class="muted">Sol → sağ: her aşama bir öncekinin çıktısıyla çalışır</span></div>
  <ol class="zc" style="grid-template-columns:repeat(5,minmax(0,1fr))">
    <li class="<?= ($sayi['belge_hazirlaniyor'] ?? 0) ? 'devam' : 'tamam' ?>"><span class="zc-nokta">1</span><span class="zc-ad">Belge onayı</span><span class="zc-durum"><?= (int)($sayi['belge_hazirlaniyor'] ?? 0) ?> bekliyor</span></li>
    <li class="<?= $acikDonem ? 'devam' : 'tamam' ?>"><span class="zc-nokta">2</span><span class="zc-ad">Başvuru dönemi</span><span class="zc-durum"><?= $acikDonem ?> açık dönem</span></li>
    <li class="<?= ($sayi['belge_onayli'] ?? 0) || $acikListe ? 'devam' : 'bekliyor' ?>"><span class="zc-nokta">3</span><span class="zc-ad">Liste oluştur / kapat</span><span class="zc-durum"><?= (int)($sayi['belge_onayli'] ?? 0) ?> aday · <?= $acikListe ?> açık liste</span></li>
    <li class="<?= ($sayi['eimza_surecinde'] ?? 0) ? 'devam' : 'bekliyor' ?>"><span class="zc-nokta">4</span><span class="zc-ad">E-imza onayı</span><span class="zc-durum"><?= (int)($sayi['eimza_surecinde'] ?? 0) ?> bekliyor</span></li>
    <li class="<?= ($sayi['tamamlandi'] ?? 0) ? 'tamam' : 'bekliyor' ?>"><span class="zc-nokta">5</span><span class="zc-ad">Öğrenciye teslim</span><span class="zc-durum"><?= (int)($sayi['tamamlandi'] ?? 0) ?> tamamlandı</span></li>
  </ol>
</section>
<section class="kart"><div class="kart-baslik"><h2><?= ikon('gecmis') ?> Son işlemler</h2><a class="dugme hayalet kucuk" href="<?= e(BASE_URL) ?>/yonetim/loglar.php">İşlem geçmişi</a></div>
  <div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Tarih</th><th>Öğrenci</th><th>Yeni durum</th><th>Kim</th><th>Açıklama</th></tr></thead><tbody>
    <?php foreach ($son as $r) echo '<tr><td>' . e(tarih_tr($r['tarih'], true)) . '</td><td>' . e($r['ogrenci_no']) . '</td><td>' . durum_rozeti($r['yeni_durum']) . '</td><td>' . e($r['ad_soyad'] ?? 'Sistem') . '</td><td>' . e($r['aciklama']) . '</td></tr>'; ?>
  </tbody></table></div></section>
<?php layout_footer();
