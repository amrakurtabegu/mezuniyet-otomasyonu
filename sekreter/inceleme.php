<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['sekreter']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$k = $pdo->prepare("SELECT durum FROM basvurular WHERE id = ? AND durum IN ('komisyon_onay','iliski_kesme_tamam','belge_hazirlaniyor','belge_onayli','listede','eimza_surecinde','tamamlandi')");
$k->execute([$id]);
$durum = $k->fetchColumn();
if ($durum === false) { http_response_code(404); exit('Başvuru bulunamadı veya henüz sekreterlik aşamasında değil.'); }

$kuyrukAd = (string)($_GET['kuyruk'] ?? '');
if (!in_array($kuyrukAd, ['iliski', 'engel', 'belge'], true)) $kuyrukAd = $durum === 'iliski_kesme_tamam' ? 'belge' : 'iliski';
$kuyruk = kuyruk_idleri($pdo, $kuyrukAd);
if (!in_array($id, $kuyruk, true)) $kuyruk = [$id];                     // tamamlanmış öğrenci: yalnızca görüntüleme
$liste = $kuyrukAd === 'belge' ? BASE_URL . '/sekreter/belge.php' : BASE_URL . '/sekreter/iliski.php' . ($kuyrukAd === 'engel' ? '?sekme=engel' : '');
$self = BASE_URL . '/sekreter/inceleme.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sonraki = kuyruk_sonraki($kuyruk, $id);                            // öğrenci kuyruktan çıkmadan ÖNCE sıradaki belirlenir
    $git = !empty($_POST['git']);
    $hedefSonraki = $sonraki ? '/sekreter/inceleme.php?id=' . $sonraki . '&kuyruk=' . $kuyrukAd : ($kuyrukAd === 'belge' ? '/sekreter/belge.php' : '/sekreter/iliski.php');
    if (($_POST['islem'] ?? '') === 'iliski_kaydet') {
        $b = [];
        foreach (array_keys(ILISKI_BIRIMLERI) as $birim) $b[$birim] = ['durum' => (string)($_POST['durum'][$birim] ?? 'bekliyor'), 'aciklama' => (string)($_POST['aciklama'][$birim] ?? '')];
        [$ok, $m, $tamam] = iliski_kesme_kaydet($pdo, $id, $b, $u['id']);
        flash($m, $ok ? 'ok' : 'err');
        redirect(!$ok ? '/sekreter/inceleme.php?id=' . $id . '&kuyruk=' . $kuyrukAd : (($tamam || $git) ? $hedefSonraki : '/sekreter/inceleme.php?id=' . $id . '&kuyruk=' . $kuyrukAd));
    }
    if (($_POST['islem'] ?? '') === 'belge_hazirla') {
        [$ok, $m] = belge_hazirla($pdo, $id, $u['id']);
        flash($m, $ok ? 'ok' : 'err');
        redirect($ok ? $hedefSonraki : '/sekreter/inceleme.php?id=' . $id . '&kuyruk=' . $kuyrukAd);
    }
    flash('Geçersiz işlem.', 'err');
    redirect('/sekreter/inceleme.php?id=' . $id . '&kuyruk=' . $kuyrukAd);
}

if ($durum === 'komisyon_onay') iliski_kesme_olustur($pdo, $id);
layout_header('Öğrenci İncelemesi', ['kirinti' => [['Ana Sayfa', BASE_URL . '/sekreter/index.php'], [$kuyrukAd === 'belge' ? 'Belge Hazırlama' : 'İlişik Kesme İşlemleri', $liste], ['Öğrenci İncelemesi']]]);
echo gezinti_html($kuyruk, $id, $self, $liste, '&kuyruk=' . $kuyrukAd);
?>
<div class="inceleme">
  <div><?= ogrenci_detay_html($pdo, $id, 'sekreter') ?></div>
  <aside class="inceleme-islem">
  <?php if ($durum === 'komisyon_onay'):
      $ik = $pdo->prepare('SELECT * FROM iliski_kesme_kontrolleri WHERE basvuru_id = ?'); $ik->execute([$id]);
      $kayit = []; foreach ($ik->fetchAll() as $r) $kayit[$r['birim']] = $r; ?>
    <section class="kart"><h2>İlişik kesme kontrolü</h2>
      <p class="muted">Her birim ayrı ayrı işaretlenir; hiçbir birim otomatik tamamlanmış sayılmaz. “Engel var” için açıklama zorunludur. Tüm birimler tamamlanınca öğrenci bu listeden çıkar ve belge hazırlama aşamasına geçer.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="iliski_kaydet">
        <?php foreach (ILISKI_BIRIMLERI as $birim => $ad): $r = $kayit[$birim] ?? ['durum' => 'bekliyor', 'aciklama' => '']; ?>
          <div class="birim-satir" style="grid-template-columns:1fr">
            <b><?= e($ad) ?></b>
            <select name="durum[<?= e($birim) ?>]" data-birim aria-label="<?= e($ad) ?> durumu">
              <option value="bekliyor"<?= $r['durum'] === 'bekliyor' ? ' selected' : '' ?>>Bekliyor</option>
              <option value="tamam"<?= $r['durum'] === 'tamam' ? ' selected' : '' ?>>Tamamlandı</option>
              <option value="engel"<?= $r['durum'] === 'engel' ? ' selected' : '' ?>>Engel var</option></select>
            <input name="aciklama[<?= e($birim) ?>]" value="<?= e($r['aciklama'] ?? '') ?>" maxlength="255" placeholder="Açıklama (engel için zorunlu)" aria-label="<?= e($ad) ?> açıklaması">
          </div>
        <?php endforeach; ?>
        <div class="satir"><button type="button" class="dugme hayalet" data-tumu-tamam>Tümünü “Tamamlandı” yap</button></div>
        <div class="satir"><button class="dugme">Kaydet</button><button class="dugme yesil" name="git" value="1">Kaydet ve sonrakine geç</button></div>
      </form></section>
  <?php elseif ($durum === 'iliski_kesme_tamam'):
      $not = $pdo->prepare("SELECT aciklama FROM basvuru_gecmisi WHERE basvuru_id = ? AND yeni_durum = 'iliski_kesme_tamam' ORDER BY id DESC LIMIT 1"); $not->execute([$id]); $not = (string)$not->fetchColumn(); ?>
    <section class="kart"><h2>Belge hazırlama</h2>
      <p class="muted">Belge öğrencinin bilgileriyle otomatik oluşturulur. Hazırladığınızda DOĞRUDAN Bilgi İşlem onayına gider; öğrenci sekreterliğe geri dönmez.</p>
      <?php if (strpos($not, 'Bilgi İşlem belge düzeltmesi') === 0): ?><div class="uyari-kutu"><b>Düzeltme talebi:</b> <?= e($not) ?></div><?php endif; ?>
      <iframe title="Belge önizleme" src="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= $id ?>&dosya=pdf" style="width:100%;height:420px;border:1px solid var(--cizgi);border-radius:8px"></iframe>
      <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="belge_hazirla"><button class="dugme yesil">Belgeyi hazırla ve Bilgi İşlem'e gönder</button></form></section>
  <?php else: ?>
    <section class="kart"><h2>İşlem durumu</h2><p><?= durum_rozeti($durum) ?></p><p class="muted">Bu öğrenci için sekreterlik tarafında yapılacak işlem kalmadı; yeniden işlem gerekmez.</p></section>
  <?php endif; ?>
  </aside>
</div>
<?php layout_footer();
