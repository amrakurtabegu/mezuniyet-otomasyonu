<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['komisyon']);
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$liste = BASE_URL . '/komisyon/index.php';

$q = $pdo->prepare("SELECT b.id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.durum NOT IN ('danisman_bekliyor','iade','danisman_red')
    ORDER BY (b.durum = 'komisyon_bekliyor' AND NOT EXISTS (SELECT 1 FROM komisyon_kararlari c WHERE c.basvuru_id = b.id AND c.uye_id = ?)) DESC, o.ogrenci_no");
$q->execute([$u['id']]);
$kuyruk = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
if (!in_array($id, $kuyruk, true)) { http_response_code(404); exit('Başvuru bulunamadı veya henüz komisyon aşamasında değil.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sonraki = kuyruk_sonraki($kuyruk, $id);
    [$ok, $m] = komisyon_oy_ver($pdo, $u['id'], $id, (string)($_POST['karar'] ?? ''), trim((string)($_POST['aciklama'] ?? '')));
    flash($m, $ok ? 'ok' : 'err');
    redirect($ok && $sonraki ? '/komisyon/inceleme.php?id=' . $sonraki : ($ok ? '/komisyon/index.php' : '/komisyon/inceleme.php?id=' . $id));
}

$durum = (string)$pdo->query('SELECT durum FROM basvurular WHERE id = ' . $id)->fetchColumn();
$oy = komisyon_oylari($pdo, [$id])[$id] ?? [];
$benim = $oy[$u['id']] ?? null;
[$on, $re] = oy_sayilari($oy);
layout_header('Komisyon İncelemesi', ['kirinti' => [['Ana Sayfa', $liste], ['Komisyon İncelemesi']]]);
echo gezinti_html($kuyruk, $id, BASE_URL . '/komisyon/inceleme.php', $liste);
?>
<div class="inceleme">
  <div><?= ogrenci_detay_html($pdo, $id, 'tam') ?></div>
  <aside class="inceleme-islem">
    <section class="kart">
      <h2>Oylama durumu</h2>
      <p style="font-size:20px;font-weight:700"><?= $on ?> Onay / <?= $re ?> Ret</p>
      <p class="muted">Karar kuralı: <?= KOMISYON_UYE_SAYISI ?> üyeden en az <b><?= ONAY_ESIGI ?></b> onay = kabul · <b><?= KOMISYON_UYE_SAYISI - ONAY_ESIGI + 1 ?></b> ret = ret. Diğer üyelerin oyları soldaki “Komisyon oylaması” bölümündedir.</p>
    </section>
    <section class="kart">
      <h2>Oyunuz</h2>
      <?php if ($benim): ?>
        <p>Bu başvuru için oyunuzu kullandınız: <?= rozet($benim['karar'] === 'onay' ? 'Onay' : 'Ret', $benim['karar'] === 'onay' ? 'yesil' : 'kirmizi') ?></p><p class="muted">Kullanılan oy değiştirilemez.</p>
      <?php elseif ($durum !== 'komisyon_bekliyor'): ?>
        <p class="muted">Komisyon kararı kesinleşti (<?= e(durum_etiketi($durum)) ?>); yeni oy kullanılamaz.</p>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?>
          <label for="aciklama">Açıklama (isteğe bağlı)</label><textarea id="aciklama" name="aciklama" rows="3" maxlength="500"></textarea>
          <div class="satir">
            <button class="dugme yesil" name="karar" value="onay" onclick="return confirm('Onay oyunuz kaydedilecek ve değiştirilemez. Emin misiniz?')">Onay ver</button>
            <button class="dugme kirmizi" name="karar" value="red" onclick="return confirm('Ret oyunuz kaydedilecek ve değiştirilemez. Emin misiniz?')">Reddet</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php layout_footer();
