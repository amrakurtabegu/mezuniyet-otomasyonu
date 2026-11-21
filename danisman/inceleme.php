<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['danisman']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$liste = BASE_URL . '/danisman/index.php';

$q = $pdo->prepare("SELECT b.id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE o.danisman_id = ? ORDER BY (b.durum = 'danisman_bekliyor') DESC, o.ogrenci_no");
$q->execute([$u['id']]);
$kuyruk = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
if (!in_array($id, $kuyruk, true)) { http_response_code(404); exit('Başvuru bulunamadı veya size atanmış değil.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sonraki = kuyruk_sonraki($kuyruk, $id);
    [$ok, $m] = danisman_karar_uygula($pdo, $u['id'], $id, (string)($_POST['karar'] ?? ''), trim((string)($_POST['gerekce'] ?? '')));
    flash($m, $ok ? 'ok' : 'err');
    redirect($ok ? ($sonraki ? '/danisman/inceleme.php?id=' . $sonraki : '/danisman/index.php') : '/danisman/inceleme.php?id=' . $id);
}

$durum = (string)$pdo->query('SELECT durum FROM basvurular WHERE id = ' . $id)->fetchColumn();
$ogrId = (int)$pdo->query('SELECT ogrenci_id FROM basvurular WHERE id = ' . $id)->fetchColumn();
$uygun = uygunluk_raporu($pdo, $ogrId)['uygun'];
$eksikOnay = !$uygun && uygunluk_politikasi($pdo) === 'izin_ver';   // eksik koşullu başvuru gerekçeyle onaylanabilir
layout_header('Başvuru İncelemesi', ['kirinti' => [['Ana Sayfa', $liste], ['Başvuru İncelemesi']]]);
echo gezinti_html($kuyruk, $id, BASE_URL . '/danisman/inceleme.php', $liste);
?>
<div class="inceleme">
  <div><?= ogrenci_detay_html($pdo, $id, 'tam') ?></div>
  <aside class="inceleme-islem">
    <section class="kart">
      <h2>Danışman kararı</h2>
      <?php if ($durum === 'danisman_bekliyor'): ?>
        <form method="post"><?= csrf_field() ?>
          <?php if (!$uygun): ?><div class="uyari-kutu">Mezuniyet koşulları <b>eksik</b>. <?= $eksikOnay ? 'Yine de komisyona göndermek isterseniz <b>gerekçe yazmanız zorunludur</b>; eksikler tamamlanmış sayılmaz ve komisyona görünür.' : 'Bu başvuru onaylanamaz; reddedebilir veya düzeltme için iade edebilirsiniz.' ?></div><?php endif; ?>
          <label for="gerekce">Gerekçe / açıklama (ret ve iade için zorunlu; öğrenci panelinde görünür)</label>
          <textarea id="gerekce" name="gerekce" rows="4"></textarea>
          <div class="satir">
            <button class="dugme yesil" name="karar" value="onay" <?= ($uygun || $eksikOnay) ? '' : 'disabled' ?>><?= $uygun ? 'Onayla ve komisyona gönder' : 'Eksiklere rağmen onayla (gerekçeli)' ?></button>
            <button class="dugme turuncu" name="karar" value="iade">Düzeltme için iade et</button>
            <button class="dugme kirmizi" name="karar" value="red" onclick="return confirm('Başvuru reddedilecek ve gerekçe öğrenciye gösterilecek. Emin misiniz?')">Reddet</button>
          </div>
        </form>
      <?php else: ?><p>Bu başvuru için karar verildi: <?= durum_rozeti($durum) ?></p><p class="muted">Karar geçmişi soldaki “Başvuru geçmişi” bölümündedir.</p><?php endif; ?>
    </section>
  </aside>
</div>
<?php layout_footer();
