<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['sekreter']);
$pdo = db();
$sekme = in_array($_GET['sekme'] ?? '', ['bekleyen', 'onayda', 'tamam'], true) ? $_GET['sekme'] : 'bekleyen';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secili = $_POST['secili'] ?? [];
    if (($_POST['islem'] ?? '') === 'toplu_hazirla' && is_array($secili) && $secili) toplu_sonuc_kaydet(belge_toplu_hazirla($pdo, $u['id'], $secili));
    else flash('Toplu işlem için en az bir öğrenci seçmelisiniz.', 'err');
    redirect('/sekreter/belge.php?sekme=' . $sekme);
}

$gruplar = ['bekleyen' => "'iliski_kesme_tamam'", 'onayda' => "'belge_hazirlaniyor'", 'tamam' => "'belge_onayli','listede','eimza_surecinde','tamamlandi'"];
$sayilar = [];
foreach ($gruplar as $k => $d) $sayilar[$k] = (int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum IN ($d)")->fetchColumn();
$liste = $pdo->query("SELECT b.id, b.durum, o.ogrenci_no, o.ad_soyad, bl.belge_no, bl.hazirlama_tarihi,
    (SELECT g.aciklama FROM basvuru_gecmisi g WHERE g.basvuru_id = b.id AND g.yeni_durum = 'iliski_kesme_tamam' ORDER BY g.id DESC LIMIT 1) AS son_not
    FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id LEFT JOIN mezuniyet_belgeleri bl ON bl.basvuru_id = b.id WHERE b.durum IN ({$gruplar[$sekme]}) ORDER BY o.ogrenci_no")->fetchAll();

layout_header('Belge Hazırlama', ['alt' => 'Belge öğrenci bilgileriyle otomatik oluşturulur ve hazırlandığı anda DOĞRUDAN Bilgi İşlem onayına gider; öğrenci sekreterliğe geri dönmez.']);
?>
<section class="kart">
  <div class="cipler" style="margin-bottom:12px">
    <?php foreach (['bekleyen' => 'Hazırlanacaklar', 'onayda' => 'Bilgi İşlem onayında', 'tamam' => 'Onaylanan / tamamlanan'] as $k => $ad): ?>
      <a class="cip<?= $sekme === $k ? ' aktif' : '' ?>" href="?sekme=<?= e($k) ?>"><?= e($ad) ?><b><?= (int)$sayilar[$k] ?></b></a>
    <?php endforeach; ?>
  </div>
  <div class="arac"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="belge" placeholder="Öğrenci no veya ad soyad ara" aria-label="Ara"></div></div>
  <form method="post" data-toplu-form><?= csrf_field() ?>
  <?php if ($sekme === 'bekleyen'): ?>
    <div class="toplu-bar"><label style="margin:0;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" data-hepsi="sec"> Tümünü seç</label><span data-secili-sayac>0 kayıt seçili</span>
      <button class="dugme yesil" name="islem" value="toplu_hazirla" data-toplu data-onay="Seçilen {n} öğrencinin mezuniyet belgesi hazırlanacak ve doğrudan Bilgi İşlem onayına gönderilecek. Devam edilsin mi?">Belgeleri hazırla ve Bilgi İşlem'e gönder</button></div>
  <?php endif; ?>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="belge">
    <thead><tr><?= $sekme === 'bekleyen' ? '<th></th>' : '' ?><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th>Belge</th><th>Durum</th><th>Not</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $x): $url = BASE_URL . '/sekreter/inceleme.php?id=' . (int)$x['id'] . '&kuyruk=belge'; $prev = BASE_URL . '/pdf/mezuniyet_belgesi.php?basvuru_id=' . (int)$x['id']; ?>
      <tr<?= $sekme === 'bekleyen' ? ' data-href="' . e($url) . '" style="cursor:pointer"' : '' ?>>
        <?php if ($sekme === 'bekleyen') echo '<td><input type="checkbox" class="sec" name="secili[]" value="' . (int)$x['id'] . '" aria-label="Seç"></td>'; ?>
        <td><?= e($x['ogrenci_no']) ?></td><td><b><?= e($x['ad_soyad']) ?></b></td><td><?= e($x['belge_no'] ?? '–') ?></td><td><?= durum_rozeti($x['durum']) ?></td>
        <td><?php if ($x['durum'] === 'iliski_kesme_tamam' && strpos((string)$x['son_not'], 'Bilgi İşlem belge düzeltmesi') === 0) echo rozet('Düzeltme istendi', 'kirmizi', $x['son_not']) . ' <small>' . e($x['son_not']) . '</small>'; ?></td>
        <td><?php if (in_array($x['durum'], ['iliski_kesme_tamam', 'belge_hazirlaniyor'], true)): ?><a class="dugme hayalet kucuk" target="_blank" href="<?= e($prev) ?>">Belge önizle</a> <?php endif; ?>
          <?php if ($sekme === 'bekleyen') echo '<a class="dugme kucuk" href="' . e($url) . '">İncele / hazırla</a>'; ?></td></tr>
    <?php endforeach; ?>
    <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="7">Bu sekmede öğrenci yok.</td></tr></tbody></table></div>
  </form>
  <div class="tablo-alt"><span data-sayac="belge"></span></div>
</section>
<?php layout_footer();
