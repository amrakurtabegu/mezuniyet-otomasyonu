<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();
$sekme = ($_GET['sekme'] ?? '') === 'onayli' ? 'onayli' : 'bekleyen';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secili = $_POST['secili'] ?? [];
    if (($_POST['islem'] ?? '') === 'toplu_onay' && is_array($secili) && $secili) toplu_sonuc_kaydet(belge_toplu_onayla($pdo, $u['id'], $secili));
    else flash('Toplu onay için en az bir belge seçmelisiniz.', 'err');
    redirect('/yonetim/belge_onaylari.php?sekme=' . $sekme);
}
$gruplar = ['bekleyen' => "'belge_hazirlaniyor'", 'onayli' => "'belge_onayli'"];
$sayilar = [];
foreach ($gruplar as $k => $d) $sayilar[$k] = (int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum IN ($d)")->fetchColumn();
$liste = $pdo->query("SELECT b.id, b.durum, o.ogrenci_no, o.ad_soyad, bl.belge_no, bl.bilgi_islem_gonderim_tarihi AS gonderim, bl.ilk_onay_tarihi, h.ad_soyad AS hazirlayan
    FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id LEFT JOIN mezuniyet_belgeleri bl ON bl.basvuru_id = b.id LEFT JOIN kullanicilar h ON h.id = bl.hazirlayan_id
    WHERE b.durum IN ({$gruplar[$sekme]}) ORDER BY o.ogrenci_no")->fetchAll();

layout_header('Belge Onayları', ['alt' => 'Sekreterliğin hazırlayıp gönderdiği belgeler. Onaylanan belgeler “mezuniyet listesi bekleyenler” aşamasına geçer ve bu listeye bir daha düşmez.']);
?>
<section class="kart">
  <div class="cipler" style="margin-bottom:12px">
    <a class="cip<?= $sekme === 'bekleyen' ? ' aktif' : '' ?>" href="?sekme=bekleyen">Onay bekleyenler<b><?= $sayilar['bekleyen'] ?></b></a>
    <a class="cip<?= $sekme === 'onayli' ? ' aktif' : '' ?>" href="?sekme=onayli">Onaylanan belgeler<b><?= $sayilar['onayli'] ?></b></a>
  </div>
  <div class="arac"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="bo" placeholder="Öğrenci no veya ad soyad ara" aria-label="Ara"></div></div>
  <form method="post" data-toplu-form><?= csrf_field() ?>
  <?php if ($sekme === 'bekleyen'): ?>
    <div class="toplu-bar"><label style="margin:0;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" data-hepsi="sec"> Tümünü seç</label><span data-secili-sayac>0 kayıt seçili</span>
      <button class="dugme yesil" name="islem" value="toplu_onay" data-toplu data-onay="Seçilen {n} belge ONAYLANACAK ve öğrenciler mezuniyet listesi bekleyenlere geçecek. Hazırlanmamış veya bu aşamada olmayan kayıtlar otomatik atlanır. Devam edilsin mi?">Seçilen belgeleri toplu onayla</button></div>
  <?php endif; ?>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="bo">
    <thead><tr><?= $sekme === 'bekleyen' ? '<th></th>' : '' ?><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th>Belge no</th><th>Hazırlayan</th><th data-sirala><?= $sekme === 'bekleyen' ? 'Gönderim' : 'Onay tarihi' ?></th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $x): $url = BASE_URL . '/yonetim/inceleme.php?id=' . (int)$x['id'] . '&kuyruk=belge_onay'; $tarih = $sekme === 'bekleyen' ? $x['gonderim'] : $x['ilk_onay_tarihi']; ?>
      <tr<?= $sekme === 'bekleyen' ? ' data-href="' . e($url) . '" style="cursor:pointer"' : '' ?>>
        <?php if ($sekme === 'bekleyen') echo '<td><input type="checkbox" class="sec" name="secili[]" value="' . (int)$x['id'] . '" aria-label="Seç"></td>'; ?>
        <td><?= e($x['ogrenci_no']) ?></td><td><b><?= e($x['ad_soyad']) ?></b></td><td><?= e($x['belge_no'] ?? '–') ?></td><td><?= e($x['hazirlayan'] ?? '–') ?></td><td data-v="<?= e($tarih) ?>"><?= e(tarih_tr($tarih, true)) ?></td>
        <td><a class="dugme hayalet kucuk" target="_blank" href="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= (int)$x['id'] ?>">Belge önizle</a>
          <?= $sekme === 'bekleyen' ? ' <a class="dugme kucuk" href="' . e($url) . '">İncele</a>' : '' ?></td></tr>
    <?php endforeach; ?>
    <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="7">Bu sekmede belge yok.</td></tr></tbody></table></div>
  </form>
  <div class="tablo-alt"><span data-sayac="bo"></span></div>
</section>
<?php layout_footer();
