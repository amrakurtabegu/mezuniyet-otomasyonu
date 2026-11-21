<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();
$liste = $pdo->query("SELECT b.id, o.ogrenci_no, o.ad_soyad, bl.belge_no, bl.eimza_tarihi, m.liste_id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id
    JOIN mezuniyet_belgeleri bl ON bl.basvuru_id = b.id LEFT JOIN mezunlar m ON m.ogrenci_no = o.ogrenci_no WHERE b.durum = 'tamamlandi' ORDER BY bl.eimza_tarihi DESC")->fetchAll();
layout_header('Onaylanan Belgeler', ['alt' => 'E-imza onayı tamamlanmış mezuniyet belgeleri. Aynı belgeler öğrencinin kendi hesabında da görünür; öğrenci ve Bilgi İşlem PDF olarak indirebilir.']);
?>
<section class="kart">
  <div class="arac"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="bg" placeholder="Öğrenci no, ad soyad veya belge no ara" aria-label="Ara"></div></div>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="bg">
    <thead><tr><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th data-sirala>Belge no</th><th>Liste</th><th data-sirala>E-imza tarihi</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $x): $b = BASE_URL . '/pdf/mezuniyet_belgesi.php?basvuru_id=' . (int)$x['id']; ?>
      <tr><td><?= e($x['ogrenci_no']) ?></td><td><b><?= e($x['ad_soyad']) ?></b></td><td><?= e($x['belge_no']) ?></td><td>#<?= (int)$x['liste_id'] ?></td><td data-v="<?= e($x['eimza_tarihi']) ?>"><?= e(tarih_tr($x['eimza_tarihi'], true)) ?></td>
        <td><a class="dugme hayalet kucuk" target="_blank" href="<?= e($b) ?>">Görüntüle</a> <a class="dugme yesil kucuk" href="<?= e($b) ?>&dosya=pdf&indir=1">PDF indir</a></td></tr>
    <?php endforeach; ?>
    <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="6">Henüz tamamlanmış belge yok.</td></tr></tbody></table></div>
  <div class="tablo-alt"><span data-sayac="bg"></span></div>
</section>
<?php layout_footer();
