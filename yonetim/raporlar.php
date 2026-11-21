<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani', 'sekreter']);   // yalnızca toplu sayılar; kişisel veri içermez
$pdo = db();
$dagilim = $pdo->query('SELECT durum, COUNT(*) FROM basvurular GROUP BY durum')->fetchAll(PDO::FETCH_KEY_PAIR);
$toplam = array_sum($dagilim);
$donem = $pdo->query("SELECT d.*, COUNT(b.id) AS basvuru, SUM(b.durum = 'tamamlandi') AS tamam, SUM(b.mezuniyete_uygun = 1) AS uygun
    FROM basvuru_donemleri d LEFT JOIN basvurular b ON b.donem_id = d.id GROUP BY d.id ORDER BY d.baslangic DESC LIMIT 12")->fetchAll();
$oturum = $pdo->query("SELECT o.planlanan_tarih, o.durum, l.erken_olusturuldu, (SELECT COUNT(*) FROM mezunlar m WHERE m.oturum_id = o.id) AS mezun
    FROM oturumlar o LEFT JOIN mezuniyet_listeleri l ON l.oturum_id = o.id ORDER BY o.planlanan_tarih DESC LIMIT 12")->fetchAll();
$mezun = (int)$pdo->query('SELECT COUNT(*) FROM mezunlar')->fetchColumn();

layout_header('Raporlar', ['alt' => 'Başvuru ve mezuniyet sürecinin genel görünümü.', 'eylem' => '<button type="button" class="dugme hayalet no-print" onclick="window.print()">' . ikon('yazdir', 16) . ' Yazdır</button>']);
?>
<div class="kpi-izgara">
  <?= kpi_karti('Toplam başvuru', $toplam, 'Tüm dönemler', 'mavi', 'dosya') ?>
  <?= kpi_karti('Devam eden', $toplam - (int)($dagilim['tamamlandi'] ?? 0) - (int)($dagilim['danisman_red'] ?? 0) - (int)($dagilim['komisyon_red'] ?? 0), 'Süreci süren başvuru', 'turuncu', 'surec') ?>
  <?= kpi_karti('Tamamlanan', (int)($dagilim['tamamlandi'] ?? 0), 'Belge teslim edildi', 'yesil', 'tik') ?>
  <?= kpi_karti('Mezun kaydı', $mezun, 'Mezunlar tablosu', 'yesil', 'kisiler') ?>
</div>
<div class="iki-kolon">
  <section class="kart"><div class="kart-baslik"><h2>Başvuru durumu dağılımı</h2></div>
    <?php foreach (DURUM_KISA as $k => $ad): $n = (int)($dagilim[$k] ?? 0); $y = $toplam ? round(100 * $n / $toplam) : 0; ?>
      <div class="rapor-cubuk"><span><?= e($ad) ?></span><div class="ilerleme"><i style="width:<?= $y ?>%"></i></div><span><?= $n ?></span></div>
    <?php endforeach; if (!$toplam) echo '<p class="muted">Henüz başvuru yok.</p>'; ?></section>
  <section class="kart"><div class="kart-baslik"><h2>Mezuniyet oturumları</h2></div>
    <div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Oturum</th><th>Durum</th><th>Mezun</th></tr></thead><tbody>
      <?php foreach ($oturum as $o) echo '<tr><td>' . e(tarih_tr($o['planlanan_tarih'])) . '</td><td>' . rozet($o['durum'] === 'acik' ? 'Açık' : ($o['erken_olusturuldu'] ? 'Erken kapandı' : 'Kapandı'), $o['durum'] === 'acik' ? 'mavi' : 'gri') . '</td><td>' . (int)$o['mezun'] . '</td></tr>'; ?>
    </tbody></table></div></section>
</div>
<section class="kart"><div class="kart-baslik"><h2>Başvuru dönemlerine göre</h2></div>
  <div class="tablo-kap"><table class="tablo"><thead><tr><th>Dönem</th><th>Durum</th><th>Başvuru</th><th>Mezuniyete uygun</th><th>Tamamlanan</th></tr></thead><tbody>
    <?php foreach ($donem as $d) echo '<tr><td>' . e(donem_etiketi($d)) . '</td><td>' . rozet($d['durum'] === 'acik' ? 'Açık' : 'Kapalı', $d['durum'] === 'acik' ? 'yesil' : 'gri') . '</td><td>' . (int)$d['basvuru'] . '</td><td>' . (int)$d['uygun'] . '</td><td>' . (int)$d['tamam'] . '</td></tr>'; ?>
  </tbody></table></div></section>
<?php layout_footer();
