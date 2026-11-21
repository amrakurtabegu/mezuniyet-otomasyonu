<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();
$sekme = ($_GET['sekme'] ?? '') === 'onayli' ? 'onayli' : 'bekleyen';
$fListe = (int)($_GET['liste'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secili = $_POST['secili'] ?? [];
    if (($_POST['islem'] ?? '') === 'toplu_eimza' && is_array($secili) && $secili) toplu_sonuc_kaydet(eimza_toplu_onayla($pdo, $u['id'], $secili));
    else flash('Toplu e-imza için en az bir öğrenci seçmelisiniz.', 'err');
    redirect('/yonetim/eimza.php?sekme=' . $sekme . ($fListe ? '&liste=' . $fListe : ''));
}
$durum = $sekme === 'bekleyen' ? 'eimza_surecinde' : 'tamamlandi';
$sayilar = ['bekleyen' => (int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum = 'eimza_surecinde'")->fetchColumn(), 'onayli' => (int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum = 'tamamlandi'")->fetchColumn()];
$s = $pdo->prepare('SELECT b.id, b.durum, o.ogrenci_no, o.ad_soyad, bl.belge_no, bl.eimza_tarihi, m.liste_id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id
    LEFT JOIN mezuniyet_belgeleri bl ON bl.basvuru_id = b.id LEFT JOIN mezunlar m ON m.ogrenci_no = o.ogrenci_no WHERE b.durum = ?' . ($fListe ? ' AND m.liste_id = ?' : '') . ' ORDER BY o.ogrenci_no');
$s->execute($fListe ? [$durum, $fListe] : [$durum]);
$liste = $s->fetchAll();
$listeler = $pdo->query("SELECT l.id, o.planlanan_tarih FROM mezuniyet_listeleri l JOIN oturumlar o ON o.id = l.oturum_id WHERE l.durum = 'kapali' ORDER BY l.id DESC")->fetchAll();

layout_header('E-İmza Süreci', ['alt' => 'Liste kapandıktan sonra öğrencilerin belgelerine e-imza (SİMÜLASYON) son onayı verilir. Onay anında belge tamamlanır ve öğrencinin hesabına eklenir.']);
?>
<section class="kart">
  <div class="cipler" style="margin-bottom:12px">
    <a class="cip<?= $sekme === 'bekleyen' ? ' aktif' : '' ?>" href="?sekme=bekleyen<?= $fListe ? '&liste=' . $fListe : '' ?>">Onay bekleyenler<b><?= $sayilar['bekleyen'] ?></b></a>
    <a class="cip<?= $sekme === 'onayli' ? ' aktif' : '' ?>" href="?sekme=onayli<?= $fListe ? '&liste=' . $fListe : '' ?>">E-imza onaylananlar<b><?= $sayilar['onayli'] ?></b></a>
  </div>
  <form method="get" class="arac"><input type="hidden" name="sekme" value="<?= e($sekme) ?>">
    <div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="ei" placeholder="Öğrenci no veya ad soyad ara" aria-label="Ara"></div>
    <select name="liste" aria-label="Liste" style="width:auto"><option value="">Tüm listeler</option><?php foreach ($listeler as $l) echo '<option value="' . (int)$l['id'] . '"' . ($fListe === (int)$l['id'] ? ' selected' : '') . '>Liste #' . (int)$l['id'] . ' · ' . e(tarih_tr($l['planlanan_tarih'])) . '</option>'; ?></select>
    <button class="dugme">Filtrele</button></form>
  <form method="post" data-toplu-form><?= csrf_field() ?>
  <?php if ($sekme === 'bekleyen'): ?>
    <div class="toplu-bar"><label style="margin:0;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" data-hepsi="sec"> Tümünü seç</label><span data-secili-sayac>0 kayıt seçili</span>
      <button class="dugme yesil" name="islem" value="toplu_eimza" data-toplu data-onay="Seçilen {n} öğrenci için TOPLU E-İMZA (simülasyon) onayı verilecek; belgeler tamamlanıp öğrencilerin hesabına eklenecek. Belgesi eksik veya bu aşamada olmayanlar atlanır. Devam edilsin mi?">Toplu e-imza onayı</button></div>
  <?php endif; ?>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="ei">
    <thead><tr><?= $sekme === 'bekleyen' ? '<th></th>' : '' ?><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th>Liste</th><th>Belge no</th><th><?= $sekme === 'bekleyen' ? 'Durum' : 'E-imza tarihi' ?></th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $x): $url = BASE_URL . '/yonetim/inceleme.php?id=' . (int)$x['id'] . '&kuyruk=eimza'; ?>
      <tr<?= $sekme === 'bekleyen' ? ' data-href="' . e($url) . '" style="cursor:pointer"' : '' ?>>
        <?php if ($sekme === 'bekleyen') echo '<td><input type="checkbox" class="sec" name="secili[]" value="' . (int)$x['id'] . '" aria-label="Seç"></td>'; ?>
        <td><?= e($x['ogrenci_no']) ?></td><td><b><?= e($x['ad_soyad']) ?></b></td><td>#<?= (int)$x['liste_id'] ?></td><td><?= e($x['belge_no'] ?? '–') ?></td>
        <td><?= $sekme === 'bekleyen' ? rozet('E-imza bekliyor', 'turuncu') : e(tarih_tr($x['eimza_tarihi'], true)) ?></td>
        <td><a class="dugme hayalet kucuk" target="_blank" href="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= (int)$x['id'] ?>">Belge önizle</a>
          <?= $sekme === 'bekleyen' ? ' <a class="dugme kucuk" href="' . e($url) . '">İncele / onayla</a>' : ' <a class="dugme yesil kucuk" href="' . e(BASE_URL) . '/pdf/mezuniyet_belgesi.php?basvuru_id=' . (int)$x['id'] . '&dosya=pdf&indir=1">PDF indir</a>' ?></td></tr>
    <?php endforeach; ?>
    <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="7">Bu sekmede öğrenci yok.</td></tr></tbody></table></div>
  </form>
  <div class="tablo-alt"><span data-sayac="ei"></span></div>
</section>
<?php layout_footer();
