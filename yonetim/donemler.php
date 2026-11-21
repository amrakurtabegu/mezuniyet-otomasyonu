<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'donem_durum') {
    $id = (int)($_POST['donem_id'] ?? 0);
    $yeni = ($_POST['yeni'] ?? '') === 'acik' ? 'acik' : 'kapali';
    $pdo->prepare('UPDATE basvuru_donemleri SET durum = ? WHERE id = ?')->execute([$yeni, $id]);
    log_islem(null, "Başvuru dönemi #$id durumu: $yeni", $u['id']);
    flash($yeni === 'acik' ? 'Başvuru dönemi açıldı; öğrenciler başvuru yapabilir.' : 'Başvuru dönemi kapatıldı; artık yeni başvuru alınmaz ve bu dönem için liste oluşturulabilir.');
    redirect('/yonetim/donemler.php');
}
for ($m = 0; $m < 3; $m++) {   // bu ayın ve sonraki 2 ayın başvuru dönemleri yoksa otomatik oluşturulur
    $ay = new DateTime('first day of this month'); $ay->modify("+$m month");
    donem_getir($pdo, (clone $ay)->setDate((int)$ay->format('Y'), (int)$ay->format('n'), 1));
    donem_getir($pdo, (clone $ay)->setDate((int)$ay->format('Y'), (int)$ay->format('n'), 16));
}
$bugun = donem_getir($pdo);
$donemler = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM basvurular b WHERE b.donem_id = d.id) AS basvuru,
    (SELECT COUNT(*) FROM basvurular b WHERE b.donem_id = d.id AND b.durum = 'belge_onayli') AS aday,
    (SELECT COUNT(*) FROM mezuniyet_listeleri l WHERE l.donem_id = d.id) AS liste FROM basvuru_donemleri d ORDER BY d.baslangic DESC LIMIT 12")->fetchAll();

layout_header('Başvuru Dönemleri', ['alt' => 'Aşama A: dönemi açın, başvurular alınsın. Dönemi kapattığınızda başvuru alımı durur ve “Mezuniyet Listeleri” ekranında o dönem için liste oluşturabilirsiniz.']);
?>
<section class="kart">
  <p>Şu anki dönem: <b><?= e(donem_etiketi($bugun)) ?></b> — <?= $bugun['durum'] === 'acik' ? rozet('Başvurular açık', 'yesil') : rozet('Başvurular KAPALI', 'kirmizi') ?></p>
  <div class="tablo-kap"><table class="tablo nw"><thead><tr><th>Dönem</th><th>Durum</th><th>Başvuru</th><th>Liste bekleyen (belge onaylı)</th><th>Liste sayısı</th><th></th></tr></thead><tbody>
    <?php foreach ($donemler as $d): ?>
      <tr><td><?= e(donem_etiketi($d)) ?></td><td><?= rozet($d['durum'] === 'acik' ? 'Açık' : 'Kapalı', $d['durum'] === 'acik' ? 'yesil' : 'gri') ?></td><td><?= (int)$d['basvuru'] ?></td><td><?= (int)$d['aday'] ?></td><td><?= (int)$d['liste'] ?></td>
        <td class="sag"><form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="donem_durum"><input type="hidden" name="donem_id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="yeni" value="<?= $d['durum'] === 'acik' ? 'kapali' : 'acik' ?>">
          <button class="dugme kucuk <?= $d['durum'] === 'acik' ? 'turuncu' : 'yesil' ?>" <?= $d['durum'] === 'acik' ? "onclick=\"return confirm('Dönem kapatılsın mı? Yeni başvuru alınmaz.')\"" : '' ?>><?= $d['durum'] === 'acik' ? 'Dönemi kapat' : 'Dönemi aç' ?></button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php layout_footer();
