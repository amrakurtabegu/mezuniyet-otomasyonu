<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani']);   // yalnızca bölüm başkanı tüm mezun bilgilerini görür
$pdo = db();

$where = []; $p = [];
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') { $where[] = '(m.ogrenci_no LIKE ? OR m.ad_soyad LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
if (($_GET['bolum'] ?? '') !== '') { $where[] = 'm.bolum = ?'; $p[] = (string)$_GET['bolum']; }
if (($_GET['oturum'] ?? '') !== '') { $where[] = 'm.oturum_id = ?'; $p[] = (int)$_GET['oturum']; }
$calisma = (string)($_GET['calisma'] ?? '');
if ($calisma === 'calisan') $where[] = "m.calisma_durumu NOT LIKE 'çalışmıyor%' AND m.calisma_durumu NOT LIKE 'calismiyor%'";
if ($calisma === 'calismayan') $where[] = "(m.calisma_durumu LIKE 'çalışmıyor%' OR m.calisma_durumu LIKE 'calismiyor%')";
$s = $pdo->prepare('SELECT m.*, o.planlanan_tarih FROM mezunlar m JOIN oturumlar o ON o.id = m.oturum_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY m.mezuniyet_tarihi DESC, m.ogrenci_no');
$s->execute($p);
$liste = $s->fetchAll();
$bolumler = $pdo->query('SELECT DISTINCT bolum FROM mezunlar ORDER BY bolum')->fetchAll(PDO::FETCH_COLUMN);
$oturumlar = $pdo->query("SELECT id, planlanan_tarih FROM oturumlar WHERE durum = 'kapali' ORDER BY planlanan_tarih DESC")->fetchAll();

layout_header('Mezunlar', ['alt' => 'Mezuniyet süreci tamamlanan öğrencilerin kayıtları. Mezun izleme ve iletişim için kullanılır.']);
?>
<section class="kart">
  <form method="get" class="arac">
    <div class="arama"><?= ikon('ara', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Öğrenci no veya ad soyad" aria-label="Ara"></div>
    <select name="bolum" aria-label="Bölüm" style="width:auto"><option value="">Tüm bölümler</option><?php foreach ($bolumler as $b) echo '<option' . (($_GET['bolum'] ?? '') === $b ? ' selected' : '') . '>' . e($b) . '</option>'; ?></select>
    <select name="oturum" aria-label="Mezuniyet oturumu" style="width:auto"><option value="">Tüm oturumlar</option><?php foreach ($oturumlar as $o) echo '<option value="' . (int)$o['id'] . '"' . (($_GET['oturum'] ?? '') == $o['id'] ? ' selected' : '') . '>' . e(tarih_tr($o['planlanan_tarih'])) . '</option>'; ?></select>
    <select name="calisma" aria-label="Çalışma durumu" style="width:auto"><option value="">Tüm çalışma durumları</option><option value="calisan"<?= $calisma === 'calisan' ? ' selected' : '' ?>>Çalışıyor</option><option value="calismayan"<?= $calisma === 'calismayan' ? ' selected' : '' ?>>Çalışmıyor</option></select>
    <button class="dugme" type="submit">Filtrele</button><a class="dugme hayalet" href="<?= e(BASE_URL) ?>/yonetim/mezunlar.php">Temizle</a>
  </form>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="mezunlar">
    <thead><tr><th data-sirala>No</th><th data-sirala>Ad soyad</th><th data-sirala>Bölüm</th><th data-sirala>GANO</th><th data-sirala>Çalışma</th><th>E-posta</th><th>Telefon</th><th data-sirala>Oturum</th><th data-sirala>Mezuniyet tarihi</th></tr></thead>
    <tbody>
    <?php foreach ($liste as $r): ?>
      <tr><td><?= e($r['ogrenci_no']) ?></td><td><b><?= e($r['ad_soyad']) ?></b></td><td><?= e($r['bolum']) ?></td><td><?= e(number_format((float)$r['gano'], 2)) ?></td><td><?= e($r['calisma_durumu']) ?></td>
        <td><?= e($r['eposta']) ?></td><td><?= e($r['telefon']) ?></td><td data-v="<?= e($r['planlanan_tarih']) ?>"><?= e(tarih_tr($r['planlanan_tarih'])) ?></td><td data-v="<?= e($r['mezuniyet_tarihi']) ?>"><?= e(tarih_tr($r['mezuniyet_tarihi'])) ?></td></tr>
    <?php endforeach; ?>
      <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="9">Kayıt bulunamadı.</td></tr>
    </tbody></table></div>
  <div class="tablo-alt"><span data-sayac="mezunlar"></span></div>
</section>
<?php layout_footer();
