<?php
// Dekanlığa gönderilecek mezuniyet evrakı (tarayıcıdan "Yazdır → PDF olarak kaydet" ile PDF alınır)
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani', 'sekreter']);
$pdo = db();
$id = (int)($_GET['liste_id'] ?? 0);
$s = $pdo->prepare('SELECT l.*, o.planlanan_tarih FROM mezuniyet_listeleri l JOIN oturumlar o ON o.id=l.oturum_id WHERE l.id=?');
$s->execute([$id]); $l = $s->fetch();
if (!$l) { http_response_code(404); exit('Liste bulunamadı.'); }
$m = $pdo->prepare('SELECT * FROM mezunlar WHERE liste_id=? ORDER BY ogrenci_no'); $m->execute([$id]); $mezunlar = $m->fetchAll();
$uyeler = $pdo->query("SELECT ad_soyad FROM kullanicilar WHERE rol='komisyon' ORDER BY kullanici_no")->fetchAll(PDO::FETCH_COLUMN);
$baskan = $pdo->query("SELECT ad_soyad FROM kullanicilar WHERE rol='bolum_baskani' LIMIT 1")->fetchColumn();
$bolumler = array_unique(array_column($mezunlar, 'bolum'));
log_islem(null, "Dekanlık evrakı oluşturuldu (liste #$id)");
?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><title>Dekanlık Evrakı – Liste #<?= $id ?></title>
<style>
 body{font-family:"Times New Roman",serif;margin:40px;color:#000;line-height:1.5}
 h3,h4{text-align:center;margin:4px}.sag{text-align:right}
 table{width:100%;border-collapse:collapse;margin:16px 0}th,td{border:1px solid #000;padding:5px 8px;font-size:13px}
 .imza{display:flex;justify-content:space-between;flex-wrap:wrap;margin-top:40px;font-size:13px}.imza div{width:30%;text-align:center;margin-bottom:30px}
 @media print{.no-print{display:none}body{margin:15mm}}
</style></head><body>
<p class="no-print"><button onclick="window.print()">Yazdır / PDF Olarak Kaydet</button></p>
<h3>T.C.</h3><h3>ÜNİVERSİTE ADI</h3><h4>MÜHENDİSLİK FAKÜLTESİ DEKANLIĞINA</h4>
<p class="sag">Tarih: <?= tarih_tr($l['olusturma_tarihi']) ?><br>Sayı: MZN-<?= date('Y') ?>/<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?></p>
<p><b>Konu:</b> <?= tarih_tr($l['planlanan_tarih']) ?> Tarihli Mezuniyet Oturumu Hk.</p>
<p>&nbsp;&nbsp;&nbsp;&nbsp;<?= e(implode(', ', $bolumler) ?: 'Bölümümüz') ?> bölümü mezuniyet komisyonu tarafından yapılan inceleme sonucunda,
 aşağıda adı geçen <?= count($mezunlar) ?> öğrencinin mezuniyet koşullarını sağladığı ve en az <?= ONAY_ESIGI ?> komisyon üyesinin onayı ile mezuniyetlerinin uygun görüldüğü tespit edilmiştir.
 Gereğini bilgilerinize arz ederim.</p>
<table>
 <tr><th>S.No</th><th>Öğrenci No</th><th>Adı Soyadı</th><th>Bölüm</th><th>GANO</th></tr>
 <?php foreach ($mezunlar as $i => $r): ?>
  <tr><td><?= $i + 1 ?></td><td><?= e($r['ogrenci_no']) ?></td><td><?= e($r['ad_soyad']) ?></td><td><?= e($r['bolum']) ?></td><td><?= e(number_format((float)$r['gano'], 2)) ?></td></tr>
 <?php endforeach; if (!$mezunlar) echo '<tr><td colspan="5">Listede öğrenci yok.</td></tr>'; ?>
</table>
<p class="sag"><b><?= e($baskan) ?></b><br>Bölüm Başkanı</p>
<h4 style="text-align:left;margin-top:30px">Mezuniyet Komisyonu Üyeleri</h4>
<div class="imza"><?php foreach ($uyeler as $ad) echo '<div>' . e($ad) . '<br>Komisyon Üyesi<br><br>İmza: ______________</div>'; ?></div>
</body></html>
