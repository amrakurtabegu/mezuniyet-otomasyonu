<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$k = $pdo->prepare("SELECT durum FROM basvurular WHERE id = ? AND durum IN ('belge_hazirlaniyor','belge_onayli','listede','eimza_surecinde','tamamlandi')");
$k->execute([$id]);
$durum = $k->fetchColumn();
if ($durum === false) { http_response_code(404); exit('Başvuru bulunamadı veya belge aşamasında değil.'); }

$kuyrukAd = ($_GET['kuyruk'] ?? '') === 'eimza' ? 'eimza' : 'belge_onay';
$kuyruk = kuyruk_idleri($pdo, $kuyrukAd);
if (!in_array($id, $kuyruk, true)) $kuyruk = [$id];
$liste = BASE_URL . ($kuyrukAd === 'eimza' ? '/yonetim/eimza.php' : '/yonetim/belge_onaylari.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sonraki = kuyruk_sonraki($kuyruk, $id);
    $islem = (string)($_POST['islem'] ?? '');
    if ($islem === 'belge_onayla') [$ok, $m] = belge_ilk_onay($pdo, $id, $u['id']);
    elseif ($islem === 'duzeltme') [$ok, $m] = belge_duzeltme_iste($pdo, $id, $u['id'], (string)($_POST['not'] ?? ''));
    elseif ($islem === 'eimza_onayla') [$ok, $m] = eimza_onayla($pdo, $id, $u['id']);
    else { $ok = false; $m = 'Geçersiz işlem.'; }
    flash($m, $ok ? 'ok' : 'err');
    redirect($ok ? ($sonraki ? '/yonetim/inceleme.php?id=' . $sonraki . '&kuyruk=' . $kuyrukAd : ($kuyrukAd === 'eimza' ? '/yonetim/eimza.php' : '/yonetim/belge_onaylari.php')) : '/yonetim/inceleme.php?id=' . $id . '&kuyruk=' . $kuyrukAd);
}
layout_header('Belge İncelemesi', ['kirinti' => [['Ana Sayfa', BASE_URL . '/yonetim/index.php'], [$kuyrukAd === 'eimza' ? 'E-İmza Süreci' : 'Belge Onayları', $liste], ['Belge İncelemesi']]]);
echo gezinti_html($kuyruk, $id, BASE_URL . '/yonetim/inceleme.php', $liste, '&kuyruk=' . $kuyrukAd);
$iframe = '<iframe title="Belge önizleme" src="' . e(BASE_URL) . '/pdf/mezuniyet_belgesi.php?basvuru_id=' . $id . '&dosya=pdf" style="width:100%;height:420px;border:1px solid var(--cizgi);border-radius:8px"></iframe>';
?>
<div class="inceleme">
  <div><?= ogrenci_detay_html($pdo, $id, 'tam') ?></div>
  <aside class="inceleme-islem">
  <?php if ($durum === 'belge_hazirlaniyor'): ?>
    <section class="kart"><h2>Belge onayı</h2><?= $iframe ?>
      <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="belge_onayla"><button class="dugme yesil" onclick="return confirm('Belge onaylansın mı?')">Belgeyi onayla</button></form></section>
    <section class="kart"><h2>Düzeltme talebi</h2><p class="muted">Belge hatalıysa açıklamayla sekreterliğe geri gönderin; öğrenci “Belge hazırlama” listesinde notla görünür.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="duzeltme"><label for="not">Açıklama (zorunlu)</label><textarea id="not" name="not" rows="3"></textarea>
        <div class="satir"><button class="dugme turuncu">Düzeltme iste</button></div></form></section>
  <?php elseif ($durum === 'eimza_surecinde'): ?>
    <section class="kart"><h2>E-imza son onayı</h2><div class="uyari-kutu">E-imza adımı <b>simülasyondur</b>; hukuken geçerli değildir.</div><?= $iframe ?>
      <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="eimza_onayla"><button class="dugme yesil" onclick="return confirm('E-imza (simülasyon) onayı verilsin mi? Belge öğrencinin hesabına eklenecek.')">E-imza onayını ver</button></form></section>
  <?php else: ?>
    <section class="kart"><h2>Belge</h2><p><?= durum_rozeti($durum) ?></p>
      <a class="dugme hayalet" target="_blank" href="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= $id ?>">Belgeyi görüntüle</a>
      <?php if ($durum === 'tamamlandi'): ?><a class="dugme yesil" href="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= $id ?>&dosya=pdf&indir=1">PDF indir</a><?php endif; ?></section>
  <?php endif; ?>
  </aside>
</div>
<?php layout_footer();
