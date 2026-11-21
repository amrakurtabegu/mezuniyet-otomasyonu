<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['sekreter']);
$pdo = db();
$sekme = in_array($_GET['sekme'] ?? '', ['bekleyen', 'engel', 'tamam'], true) ? $_GET['sekme'] : 'bekleyen';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secili = $_POST['secili'] ?? [];
    if (($_POST['islem'] ?? '') === 'toplu_tamamla' && is_array($secili) && $secili) toplu_sonuc_kaydet(iliski_kesme_toplu_tamamla($pdo, $u['id'], $secili));
    else flash('Toplu işlem için en az bir öğrenci seçmelisiniz.', 'err');
    redirect('/sekreter/iliski.php?sekme=' . $sekme);
}

$tamamDurumlari = "'iliski_kesme_tamam','belge_hazirlaniyor','belge_onayli','listede','eimza_surecinde','tamamlandi'";
$ids = ['bekleyen' => kuyruk_idleri($pdo, 'iliski'), 'engel' => kuyruk_idleri($pdo, 'engel')];
$sayilar = ['bekleyen' => count($ids['bekleyen']), 'engel' => count($ids['engel']),
            'tamam' => (int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum IN ($tamamDurumlari)")->fetchColumn()];
$kosul = $sekme === 'tamam' ? "b.durum IN ($tamamDurumlari)" : ($ids[$sekme] ? 'b.id IN (' . implode(',', $ids[$sekme]) . ')' : '0');
$liste = $pdo->query("SELECT b.id, b.durum, o.ogrenci_no, o.ad_soyad, dk.ad_soyad AS danisman_ad,
    (SELECT COUNT(*) FROM komisyon_kararlari c WHERE c.basvuru_id = b.id AND c.karar = 'onay') AS onay,
    (SELECT COUNT(*) FROM komisyon_kararlari c WHERE c.basvuru_id = b.id AND c.karar = 'red') AS ret,
    (SELECT MAX(i.kontrol_tarihi) FROM iliski_kesme_kontrolleri i WHERE i.basvuru_id = b.id) AS son
    FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id JOIN kullanicilar dk ON dk.id = o.danisman_id WHERE $kosul ORDER BY o.ogrenci_no")->fetchAll();
$birimler = iliski_durumlari($pdo, array_column($liste, 'id'));

layout_header('İlişik Kesme İşlemleri', ['alt' => 'Komisyonun onayladığı öğrenciler burada sizi bekler. Tamamlanan öğrenci bu listeden otomatik çıkar ve belge hazırlama aşamasına geçer.']);
?>
<section class="kart">
  <div class="cipler" style="margin-bottom:12px">
    <?php foreach (['bekleyen' => 'Bekleyenler', 'engel' => 'Engel bulunanlar', 'tamam' => 'Tamamlananlar'] as $k => $ad): ?>
      <a class="cip<?= $sekme === $k ? ' aktif' : '' ?>" href="?sekme=<?= e($k) ?>"><?= e($ad) ?><b><?= (int)$sayilar[$k] ?></b></a>
    <?php endforeach; ?>
  </div>
  <div class="arac"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="iliski" placeholder="Öğrenci no veya ad soyad ara" aria-label="Ara"></div></div>
  <form method="post" data-toplu-form><?= csrf_field() ?>
  <?php if ($sekme === 'bekleyen'): ?>
    <div class="toplu-bar"><label style="margin:0;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" data-hepsi="sec"> Tümünü seç</label><span data-secili-sayac>0 kayıt seçili</span>
      <button class="dugme yesil" name="islem" value="toplu_tamamla" data-toplu data-onay="Seçilen {n} öğrencinin TÜM ilişik kesme birimleri 'Tamamlandı' olarak işaretlenecek ve öğrenciler belge hazırlama aşamasına geçecek. Engel kaydı olanlar otomatik atlanır. Devam edilsin mi?">Seçilenlerin ilişik kesmesini tamamla</button></div>
  <?php endif; ?>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="iliski">
    <thead><tr><?= $sekme === 'bekleyen' ? '<th></th>' : '' ?><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th data-sirala>Danışman</th><th data-sirala>Komisyon sonucu</th><th>İlişik kesme birimleri</th><th data-sirala>Son kontrol</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $x): $url = BASE_URL . '/sekreter/inceleme.php?id=' . (int)$x['id'] . '&kuyruk=' . ($sekme === 'engel' ? 'engel' : 'iliski'); ?>
      <tr<?= $sekme !== 'tamam' ? ' data-href="' . e($url) . '" style="cursor:pointer"' : '' ?>>
        <?php if ($sekme === 'bekleyen') echo '<td><input type="checkbox" class="sec" name="secili[]" value="' . (int)$x['id'] . '" aria-label="Seç"></td>'; ?>
        <td><?= e($x['ogrenci_no']) ?></td><td><b><?= e($x['ad_soyad']) ?></b></td><td><?= e($x['danisman_ad']) ?></td>
        <td><?= rozet('Onaylandı', 'yesil') ?> <small><?= (int)$x['onay'] ?> onay · <?= (int)$x['ret'] ?> ret</small></td>
        <td><?= iliski_ozet_html($birimler[(int)$x['id']] ?? []) ?></td><td data-v="<?= e($x['son']) ?>"><?= e(tarih_tr($x['son'], true)) ?></td>
        <td><?= $sekme === 'tamam' ? durum_rozeti($x['durum']) : '<a class="dugme kucuk" href="' . e($url) . '">İncele</a>' ?></td></tr>
    <?php endforeach; ?>
    <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="8">Bu sekmede öğrenci yok.</td></tr></tbody></table></div>
  </form>
  <div class="tablo-alt"><span data-sayac="iliski"></span></div>
</section>
<?php layout_footer();
