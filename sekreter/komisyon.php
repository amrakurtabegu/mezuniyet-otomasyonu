<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['sekreter']);
$pdo = db();
$liste = $pdo->query("SELECT b.id, b.durum, o.ogrenci_no, o.ad_soyad, dk.ad_soyad AS danisman_ad, a.gano,
    (SELECT COUNT(*) FROM komisyon_kararlari c WHERE c.basvuru_id = b.id AND c.karar = 'onay') AS onay,
    (SELECT COUNT(*) FROM komisyon_kararlari c WHERE c.basvuru_id = b.id AND c.karar = 'red') AS ret,
    (SELECT MAX(c.karar_tarihi) FROM komisyon_kararlari c WHERE c.basvuru_id = b.id) AS karar_tarihi
    FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id JOIN kullanicilar dk ON dk.id = o.danisman_id LEFT JOIN akademik_kayitlari a ON a.ogrenci_id = o.id
    WHERE b.durum NOT IN ('danisman_bekliyor','iade','danisman_red','komisyon_bekliyor','komisyon_red') ORDER BY karar_tarihi DESC, o.ogrenci_no")->fetchAll();
layout_header('Komisyon Onayı Tamamlananlar', ['alt' => 'Komisyonun kabul ettiği öğrenciler ve şu an bulundukları aşama. İşlem yapılacak olanlar “İlişik Kesme İşlemleri” ekranında sizi bekler.']);
?>
<section class="kart">
  <div class="arac"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="kom" placeholder="Öğrenci no veya ad soyad ara" aria-label="Ara"></div>
    <a class="dugme" href="<?= e(BASE_URL) ?>/sekreter/iliski.php">İlişik kesme işlemlerine git</a></div>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="kom">
    <thead><tr><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th data-sirala>Danışman</th><th data-sirala>GANO</th><th data-sirala>Komisyon oyu</th><th data-sirala>Karar tarihi</th><th>Şu anki aşama</th></tr></thead><tbody>
    <?php foreach ($liste as $x): ?>
      <tr><td><?= e($x['ogrenci_no']) ?></td><td><b><?= e($x['ad_soyad']) ?></b></td><td><?= e($x['danisman_ad']) ?></td><td><?= $x['gano'] !== null ? e(number_format((float)$x['gano'], 2)) : '–' ?></td>
        <td data-v="<?= (int)$x['onay'] ?>"><?= (int)$x['onay'] ?> onay / <?= (int)$x['ret'] ?> ret</td><td data-v="<?= e($x['karar_tarihi']) ?>"><?= e(tarih_tr($x['karar_tarihi'], true)) ?></td><td><?= durum_rozeti($x['durum']) ?></td></tr>
    <?php endforeach; ?>
    <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="7">Komisyonca onaylanmış öğrenci yok.</td></tr></tbody></table></div>
  <div class="tablo-alt"><span data-sayac="kom"></span></div>
</section>
<?php layout_footer();
