<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani', 'sekreter']);
$pdo = db();
$baskan = $u['rol'] === 'bolum_baskani';
$iletisim = $baskan || SEKRETER_ILETISIM_GORUR;   // sekreterin iletişim bilgisini görmesi config/db.php içindeki tek ayarla yönetilir
$id = (int)($_GET['id'] ?? 0);

/* ---------------------------------------------------------------- İşlemler (yalnızca başkan / Bilgi İşlem) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$baskan) { http_response_code(403); exit('Yetkisiz işlem.'); }
    $islem = (string)($_POST['islem'] ?? '');
    try {
        if ($islem === 'olustur') {           // Aşama B: dönem kapalıysa ve belge onaylı öğrenci varsa liste oluşturulur (öğrencileri sistem seçer)
            $listeId = liste_olustur($pdo, (int)($_POST['donem_id'] ?? 0), (int)($_POST['oturum_id'] ?? 0), $u['id']);
            flash("Mezuniyet listesi oluşturuldu (#$listeId). Şimdi listeyi inceleyin; hazırsa kapatın.");
            redirect('/yonetim/listeler.php?id=' . $listeId);
        } elseif ($islem === 'kapat') {       // Aşama C: kapatılan liste değiştirilemez; öğrenciler e-imza aşamasına geçer
            [$ok, $m] = liste_kapat($pdo, (int)($_POST['liste_id'] ?? 0), $u['id']);
            flash($m, $ok ? 'ok' : 'err');
            redirect('/yonetim/listeler.php?id=' . (int)($_POST['liste_id'] ?? 0));
        } elseif ($islem === 'iptal') {
            [$ok, $m] = liste_iptal($pdo, (int)($_POST['liste_id'] ?? 0), $u['id']);
            flash($m, $ok ? 'ok' : 'err');
            redirect('/yonetim/listeler.php');
        } elseif ($islem === 'oturum_uret') {
            $ay = max(1, min(24, (int)($_POST['ay'] ?? 6)));
            $ins = $pdo->prepare('INSERT IGNORE INTO oturumlar (planlanan_tarih) VALUES (?)');
            for ($m = 0; $m < $ay; $m++) {
                $d = new DateTime('first day of this month'); $d->modify("+$m month");
                foreach ([$d->format('Y-m-15'), $d->format('Y-m-t')] as $t) if ($t >= date('Y-m-d')) $ins->execute([$t]);
            }
            log_islem(null, "Oturum tarihleri üretildi ($ay ay)", $u['id']);
            flash('Oturum tarihleri oluşturuldu.');
        } elseif ($islem === 'oturum_ekle') {
            $t = (string)($_POST['tarih'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) throw new RuntimeException('Geçersiz tarih.');
            $pdo->prepare('INSERT INTO oturumlar (planlanan_tarih) VALUES (?)')->execute([$t]);
            flash('Oturum eklendi.');
        } else { flash('Geçersiz işlem.', 'err'); }
    } catch (PDOException $ex) { flash('İşlem yapılamadı (tarih zaten tanımlı olabilir).', 'err'); }
    catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); redirect('/yonetim/listeler.php' . (!empty($_POST['donem_id']) ? '?donem=' . (int)$_POST['donem_id'] : '')); }
    redirect('/yonetim/listeler.php');
}

/* ---------------------------------------------------------------- Tek liste */
if ($id) {
    $s = $pdo->prepare('SELECT l.*, o.planlanan_tarih FROM mezuniyet_listeleri l JOIN oturumlar o ON o.id = l.oturum_id WHERE l.id = ?');
    $s->execute([$id]);
    $l = $s->fetch();
    layout_header('Mezuniyet Listesi', ['kirinti' => [['Ana Sayfa', BASE_URL . rol_anasayfa($u['rol'])], ['Mezuniyet Listeleri', BASE_URL . '/yonetim/listeler.php'], ['Liste #' . $id]]]);
    if (!$l) { echo '<div class="kart"><p>Liste bulunamadı.</p><a class="dugme hayalet" href="' . e(BASE_URL) . '/yonetim/listeler.php">Tüm listeler</a></div>'; layout_footer(); exit; }
    $m = $pdo->prepare('SELECT m.*, (SELECT b.durum FROM basvurular b JOIN ogrenciler og ON og.id = b.ogrenci_id WHERE og.ogrenci_no = m.ogrenci_no ORDER BY b.id DESC LIMIT 1) AS bdurum FROM mezunlar m WHERE m.liste_id = ? ORDER BY m.ogrenci_no');
    $m->execute([$id]);
    $mezunlar = $m->fetchAll();
    $acik = $l['durum'] === 'olusturuldu';
    ?>
    <section class="kart">
      <div class="kart-baslik"><h2>Liste #<?= (int)$id ?> · Oturum <?= e(tarih_tr($l['planlanan_tarih'])) ?></h2>
        <div class="satir" style="margin:0"><a class="dugme hayalet" target="_blank" href="<?= e(BASE_URL) ?>/pdf/dekanlik_evraki.php?liste_id=<?= (int)$id ?>"><?= ikon('belge', 16) ?> Dekanlık evrakı</a>
          <button type="button" class="dugme hayalet" onclick="window.print()"><?= ikon('yazdir', 16) ?> Yazdır</button><a class="dugme hayalet" href="<?= e(BASE_URL) ?>/yonetim/listeler.php">Tüm listeler</a></div></div>
      <p><?= $acik ? rozet('Oluşturuldu · incelenebilir, henüz kapatılmadı', 'mavi') : rozet('Kapatıldı · değiştirilemez', 'gri') ?> <?= $l['erken_olusturuldu'] ? rozet('Erken oluşturuldu', 'turuncu') : '' ?>
        <span class="muted">Oluşturulma: <?= e(tarih_tr($l['olusturma_tarihi'], true)) ?><?= $l['kapatma_tarihi'] ? ' · Kapatma: ' . e(tarih_tr($l['kapatma_tarihi'], true)) : '' ?> · <?= count($mezunlar) ?> öğrenci</span></p>
      <?php if ($baskan && $acik): ?>
        <div class="uyari-kutu">Listeyi inceleyin. <b>Kapattığınızda</b> liste değiştirilemez ve öğrenciler e-imza aşamasına geçer. Hatalıysa kapatmadan önce iptal edebilirsiniz (öğrenciler liste bekleyenlere döner).</div>
        <div class="satir" style="margin-top:0">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="kapat"><input type="hidden" name="liste_id" value="<?= (int)$id ?>"><button class="dugme yesil" onclick="return confirm('Liste kapatılsın mı? Kapatıldıktan sonra değiştirilemez; öğrenciler e-imza aşamasına geçer.')">Listeyi kapat</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="iptal"><input type="hidden" name="liste_id" value="<?= (int)$id ?>"><button class="dugme kirmizi" onclick="return confirm('Liste iptal edilsin mi? Öğrenciler yeniden liste bekleyenlere döner.')">Listeyi iptal et</button></form></div>
      <?php endif; ?>
      <div class="arac" style="margin-top:14px"><div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="liste" placeholder="Listede ara" aria-label="Listede ara"></div></div>
      <div class="tablo-kap"><table class="tablo nw" data-tablo="liste">
        <thead><tr><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th data-sirala>GANO</th><th data-sirala>Bölüm</th><?= $iletisim ? '<th data-sirala>Çalışma durumu</th><th>E-posta</th><th>Telefon</th>' : '' ?><th>Belge / e-imza</th></tr></thead>
        <tbody>
        <?php foreach ($mezunlar as $r): ?>
          <tr><td><?= e($r['ogrenci_no']) ?></td><td><b><?= e($r['ad_soyad']) ?></b></td><td><?= e(number_format((float)$r['gano'], 2)) ?></td><td><?= e($r['bolum']) ?></td>
            <?php if ($iletisim) echo '<td>' . e($r['calisma_durumu']) . '</td><td>' . e($r['eposta']) . '</td><td>' . e($r['telefon']) . '</td>'; ?>
            <td><?= $r['bdurum'] ? durum_rozeti($r['bdurum']) : '–' ?></td></tr>
        <?php endforeach; ?>
          <tr class="bos-satir"<?= $mezunlar ? ' style="display:none"' : '' ?>><td colspan="8">Bu listede öğrenci yok.</td></tr>
        </tbody></table></div>
      <div class="tablo-alt"><span data-sayac="liste"></span></div>
    </section>
    <?php layout_footer(); exit;
}

/* ---------------------------------------------------------------- Liste arşivi + yeni liste oluşturma */
$where = []; $p = [];
$fDonem = (string)($_GET['ay'] ?? ''); $fOturum = (int)($_GET['oturum'] ?? 0); $fOgr = trim((string)($_GET['q'] ?? '')); $fDurum = (string)($_GET['durum'] ?? '');
if (preg_match('/^\d{4}-\d{2}$/', $fDonem)) { $where[] = "DATE_FORMAT(o.planlanan_tarih, '%Y-%m') = ?"; $p[] = $fDonem; } else $fDonem = '';
if ($fOturum) { $where[] = 'l.oturum_id = ?'; $p[] = $fOturum; }
if ($fOgr !== '') { $where[] = 'EXISTS (SELECT 1 FROM mezunlar m WHERE m.liste_id = l.id AND (m.ogrenci_no LIKE ? OR m.ad_soyad LIKE ?))'; $p[] = "%$fOgr%"; $p[] = "%$fOgr%"; }
if (in_array($fDurum, ['olusturuldu', 'kapali'], true)) { $where[] = 'l.durum = ?'; $p[] = $fDurum; } else $fDurum = '';
$s = $pdo->prepare("SELECT l.*, o.planlanan_tarih, (SELECT COUNT(*) FROM mezunlar m WHERE m.liste_id = l.id) AS sayi,
    (SELECT COUNT(*) FROM mezunlar m JOIN ogrenciler og ON og.ogrenci_no = m.ogrenci_no JOIN basvurular b ON b.ogrenci_id = og.id AND b.durum = 'tamamlandi' WHERE m.liste_id = l.id) AS belge_tamam
    FROM mezuniyet_listeleri l JOIN oturumlar o ON o.id = l.oturum_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.id DESC');
$s->execute($p);
$liste = $s->fetchAll();
$aylar = $pdo->query("SELECT DISTINCT DATE_FORMAT(o.planlanan_tarih, '%Y-%m') AS ym FROM mezuniyet_listeleri l JOIN oturumlar o ON o.id = l.oturum_id ORDER BY ym DESC")->fetchAll(PDO::FETCH_COLUMN);
$oturumlarArsiv = $pdo->query('SELECT o.id, o.planlanan_tarih FROM oturumlar o JOIN mezuniyet_listeleri l ON l.oturum_id = o.id ORDER BY o.planlanan_tarih DESC')->fetchAll();
$ayAdi = fn(string $ym) => (AY_ADLARI[(int)substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 0, 4);

if ($baskan) {
    $kapaliDonemler = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM basvurular b WHERE b.donem_id = d.id AND b.durum = 'belge_onayli') AS aday FROM basvuru_donemleri d WHERE d.durum = 'kapali' ORDER BY d.baslangic DESC LIMIT 12")->fetchAll();
    $acikOturumlar = $pdo->query("SELECT * FROM oturumlar WHERE durum = 'acik' AND planlanan_tarih >= CURDATE() ORDER BY planlanan_tarih LIMIT 12")->fetchAll();
    $secDonem = (int)($_GET['donem'] ?? 0);
    $adaylar = $secDonem ? liste_adaylari($pdo, $secDonem) : [];
    $tumOturumlar = $pdo->query('SELECT * FROM oturumlar ORDER BY planlanan_tarih DESC LIMIT 16')->fetchAll();
}
layout_header('Mezuniyet Listeleri', ['alt' => 'Liste oluşturma ve liste kapatma ayrı aşamalardır: önce dönem kapatılır, sonra liste oluşturulur ve incelenir, en son kapatılır. Kapatılan listeler arşivde kalır.']);
if ($baskan): ?>
<section class="kart">
  <div class="kart-baslik"><h2><?= ikon('liste') ?> Yeni liste oluştur</h2><span class="muted">Aşama B · yalnızca KAPALI dönem ve belgesi onaylı öğrenciler</span></div>
  <?php if (!$kapaliDonemler): ?><p class="muted">Kapalı başvuru dönemi yok. Önce <a href="<?= e(BASE_URL) ?>/yonetim/donemler.php">Başvuru Dönemleri</a> ekranından ilgili dönemi kapatın.</p>
  <?php else: ?>
  <form method="get" class="arac"><select name="donem" aria-label="Başvuru dönemi" style="width:auto"><option value="">Kapalı dönem seçin…</option>
    <?php foreach ($kapaliDonemler as $d) echo '<option value="' . (int)$d['id'] . '"' . ($secDonem === (int)$d['id'] ? ' selected' : '') . '>' . e(donem_etiketi($d)) . ' · ' . (int)$d['aday'] . ' aday</option>'; ?></select>
    <button class="dugme">Adayları göster</button></form>
  <?php if ($secDonem): ?>
    <p>Sistem uygun öğrencileri seçti: <b><?= count($adaylar) ?></b> öğrenci (belgesi onaylı, bu dönemde başvurmuş).</p>
    <div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Öğrenci no</th><th>Ad soyad</th><th>Bölüm</th><th>GANO</th></tr></thead><tbody>
      <?php foreach ($adaylar as $r) echo '<tr><td>' . e($r['ogrenci_no']) . '</td><td>' . e($r['ad_soyad']) . '</td><td>' . e($r['bolum']) . '</td><td>' . e(number_format((float)$r['gano'], 2)) . '</td></tr>'; if (!$adaylar) echo '<tr><td colspan="4" class="muted">Bu dönemde listeye alınacak öğrenci yok.</td></tr>'; ?>
    </tbody></table></div>
    <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="olustur"><input type="hidden" name="donem_id" value="<?= $secDonem ?>">
      <div><label for="oturum">Mezuniyet oturumu</label><select id="oturum" name="oturum_id" style="width:auto" required>
        <?php foreach ($acikOturumlar as $o) echo '<option value="' . (int)$o['id'] . '">' . e(tarih_tr($o['planlanan_tarih'])) . ($o['planlanan_tarih'] > date('Y-m-d') ? ' (erken liste)' : '') . '</option>'; ?></select></div>
      <button class="dugme yesil" <?= ($adaylar && $acikOturumlar) ? '' : 'disabled' ?> onclick="return confirm('Liste oluşturulsun mu? Sonraki adımda inceleyip kapatabilirsiniz.')">Listeyi oluştur</button></form>
    <?php if (!$acikOturumlar): ?><p class="muted">Açık mezuniyet oturumu yok; aşağıdaki “Mezuniyet oturumları” bölümünden ekleyin.</p><?php endif; ?>
  <?php endif; endif; ?>
</section>
<?php endif; ?>

<section class="kart">
  <div class="kart-baslik"><h2><?= ikon('gecmis') ?> Liste arşivi</h2></div>
  <form method="get" class="arac">
    <select name="ay" aria-label="Dönem (ay)" style="width:auto"><option value="">Tüm dönemler</option><?php foreach ($aylar as $d) echo '<option value="' . e($d) . '"' . ($fDonem === $d ? ' selected' : '') . '>' . e($ayAdi($d)) . '</option>'; ?></select>
    <select name="oturum" aria-label="Mezuniyet oturumu" style="width:auto"><option value="">Tüm oturumlar</option><?php foreach ($oturumlarArsiv as $o) echo '<option value="' . (int)$o['id'] . '"' . ($fOturum === (int)$o['id'] ? ' selected' : '') . '>' . e(tarih_tr($o['planlanan_tarih'])) . '</option>'; ?></select>
    <select name="durum" aria-label="Liste durumu" style="width:auto"><option value="">Tüm liste durumları</option><option value="olusturuldu"<?= $fDurum === 'olusturuldu' ? ' selected' : '' ?>>Oluşturuldu (açık)</option><option value="kapali"<?= $fDurum === 'kapali' ? ' selected' : '' ?>>Kapatıldı</option></select>
    <div class="arama"><?= ikon('ara', 16) ?><input type="search" name="q" value="<?= e($fOgr) ?>" placeholder="Öğrenci no veya ad soyad" aria-label="Öğrenci ara"></div>
    <button class="dugme" type="submit">Filtrele</button><a class="dugme hayalet" href="<?= e(BASE_URL) ?>/yonetim/listeler.php">Temizle</a>
  </form>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="listeler">
    <thead><tr><th data-sirala>Liste no</th><th data-sirala>Oturum tarihi</th><th data-sirala>Oluşturulma</th><th>Liste durumu</th><th data-sirala>Öğrenci</th><th>Belge ilerlemesi</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $r): ?>
      <tr><td data-v="<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></td><td data-v="<?= e($r['planlanan_tarih']) ?>"><?= e(tarih_tr($r['planlanan_tarih'])) ?></td><td data-v="<?= e($r['olusturma_tarihi']) ?>"><?= e(tarih_tr($r['olusturma_tarihi'], true)) ?></td>
        <td><?= $r['durum'] === 'olusturuldu' ? rozet('Oluşturuldu · açık', 'mavi') : rozet('Kapatıldı', 'gri') ?> <?= $r['erken_olusturuldu'] ? rozet('Erken', 'turuncu') : '' ?></td><td><?= (int)$r['sayi'] ?></td>
        <td><?= (int)$r['belge_tamam'] ?> / <?= (int)$r['sayi'] ?> belge tamamlandı</td>
        <td class="sag"><a class="dugme hayalet kucuk" href="?id=<?= (int)$r['id'] ?>">Görüntüle</a></td></tr>
    <?php endforeach; ?>
      <tr class="bos-satir"<?= $liste ? ' style="display:none"' : '' ?>><td colspan="7">Filtrelere uyan liste yok.</td></tr>
    </tbody></table></div>
  <div class="tablo-alt"><span data-sayac="listeler"></span></div>
</section>

<?php if ($baskan): ?>
<section class="kart"><details><summary><b>Mezuniyet oturumları</b> <span class="muted">(planlı tarihler: her ayın 15'i ve son günü)</span></summary>
  <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="oturum_uret"><div style="width:90px"><label>Kaç ay?</label><input type="number" name="ay" value="6" min="1" max="24"></div><button class="dugme hayalet">Otomatik üret</button></form>
  <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="oturum_ekle"><div><label>Elle oturum tarihi</label><input type="date" name="tarih" required></div><button class="dugme hayalet">Ekle</button></form>
  <div class="tablo-kap" style="margin-top:14px"><table class="tablo kucuk"><thead><tr><th>Planlanan tarih</th><th>Durum</th></tr></thead><tbody>
    <?php foreach ($tumOturumlar as $o) echo '<tr><td>' . e(tarih_tr($o['planlanan_tarih'])) . '</td><td>' . rozet($o['durum'] === 'acik' ? 'Açık' : 'Kapalı', $o['durum'] === 'acik' ? 'mavi' : 'gri') . '</td></tr>'; ?>
  </tbody></table></div>
  <p class="muted">Erken liste oluşturmak diğer planlı oturum tarihlerini değiştirmez.</p></details></section>
<?php endif; ?>
<?php layout_footer();
