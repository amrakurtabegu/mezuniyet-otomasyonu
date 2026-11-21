<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['sekreter']);
$pdo = db();
$sayi = $pdo->query('SELECT durum, COUNT(*) FROM basvurular GROUP BY durum')->fetchAll(PDO::FETCH_KEY_PAIR);
$iliski = kuyruk_idleri($pdo, 'iliski');
$engel = kuyruk_idleri($pdo, 'engel');
$belge = kuyruk_idleri($pdo, 'belge');

/** Kuyruktaki ilk öğrencileri kısa liste olarak getirir. */
function ilk_ogrenciler(PDO $pdo, array $ids, int $n = 5): array {
    $ids = array_slice($ids, 0, $n);
    if (!$ids) return [];
    $s = $pdo->prepare('SELECT b.id, o.ogrenci_no, o.ad_soyad FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY o.ogrenci_no');
    $s->execute($ids);
    return $s->fetchAll();
}
$duzeltme = (int)$pdo->query("SELECT COUNT(*) FROM basvurular b WHERE b.durum = 'iliski_kesme_tamam' AND (SELECT g.aciklama FROM basvuru_gecmisi g WHERE g.basvuru_id = b.id AND g.yeni_durum = 'iliski_kesme_tamam' ORDER BY g.id DESC LIMIT 1) LIKE 'Bilgi İşlem belge düzeltmesi%'")->fetchColumn();

layout_header('Sekreterlik Paneli', ['alt' => 'Her aşama ayrı bir ekrandır: önce ilişik kesme, tamamlanan öğrenci otomatik olarak belge hazırlamaya geçer, belge doğrudan Bilgi İşlem onayına gider.']);
?>
<div class="kpi-izgara">
  <?= kpi_karti('Komisyon incelemesinde', (int)($sayi['komisyon_bekliyor'] ?? 0), 'Henüz sizde değil', 'mavi', 'oy') ?>
  <?= kpi_karti('İlişik kesme bekleyen', count($iliski), 'İşleminizi bekliyor', 'turuncu', 'surec') ?>
  <?= kpi_karti('Engel bulunan', count($engel), 'Tek tek incelenmeli', count($engel) ? 'kirmizi' : 'gri', 'kapat') ?>
  <?= kpi_karti('Belge hazırlama bekleyen', count($belge), $duzeltme . ' tanesi düzeltme talebi', 'turuncu', 'belge') ?>
  <?= kpi_karti('Bilgi İşlem onayında', (int)($sayi['belge_hazirlaniyor'] ?? 0), 'Belgesi gönderildi', 'mavi', 'imza') ?>
  <?= kpi_karti('Tamamlanan', (int)($sayi['tamamlandi'] ?? 0), 'E-imzalı belge öğrencide', 'yesil', 'tik') ?>
</div>
<div class="iki-kolon">
  <section class="kart"><div class="kart-baslik"><h2><?= ikon('surec') ?> İlişik kesme sırası</h2><a class="dugme hayalet kucuk" href="<?= e(BASE_URL) ?>/sekreter/iliski.php">Tüm liste</a></div>
    <?php foreach (ilk_ogrenciler($pdo, $iliski) as $x) echo '<p><a href="' . e(BASE_URL . '/sekreter/inceleme.php?id=' . $x['id'] . '&kuyruk=iliski') . '">' . e($x['ogrenci_no']) . ' - ' . e($x['ad_soyad']) . '</a></p>'; ?>
    <?php if ($iliski): ?><a class="dugme" href="<?= e(BASE_URL) ?>/sekreter/inceleme.php?id=<?= (int)$iliski[0] ?>&kuyruk=iliski">İncelemeye başla (sıradaki öğrenci)</a><?php else: ?><p class="muted">Bekleyen öğrenci yok.</p><?php endif; ?></section>
  <section class="kart"><div class="kart-baslik"><h2><?= ikon('belge') ?> Belge hazırlama sırası</h2><a class="dugme hayalet kucuk" href="<?= e(BASE_URL) ?>/sekreter/belge.php">Tüm liste</a></div>
    <?php foreach (ilk_ogrenciler($pdo, $belge) as $x) echo '<p><a href="' . e(BASE_URL . '/sekreter/inceleme.php?id=' . $x['id'] . '&kuyruk=belge') . '">' . e($x['ogrenci_no']) . ' - ' . e($x['ad_soyad']) . '</a></p>'; ?>
    <?php if ($belge): ?><a class="dugme" href="<?= e(BASE_URL) ?>/sekreter/inceleme.php?id=<?= (int)$belge[0] ?>&kuyruk=belge">Belge hazırlamaya başla</a><?php else: ?><p class="muted">Belge hazırlanacak öğrenci yok.</p><?php endif; ?></section>
</div>
<?php layout_footer();
