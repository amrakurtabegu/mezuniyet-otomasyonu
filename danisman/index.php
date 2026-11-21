<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['danisman']);
$pdo = db();

/** Danışmana atanmış başvuruları (isteğe bağlı filtrelerle) ve her biri için ANLIK uygunluk raporunu getirir. */
function danisman_satirlari(PDO $pdo, int $did, ?int $donemId = null, ?string $durum = null, ?array $idler = null): array {
    $sql = 'SELECT b.*, o.ogrenci_no, o.ad_soyad, o.bolum, o.program, a.gano AS a_gano, a.tamamlanan_akts, a.gereken_akts,
                   d.yil, d.ay, d.donem, d.baslangic, d.bitis
            FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id LEFT JOIN akademik_kayitlari a ON a.ogrenci_id = o.id
            LEFT JOIN basvuru_donemleri d ON d.id = b.donem_id
            WHERE o.danisman_id = ?';
    $p = [$did];
    if ($donemId) { $sql .= ' AND b.donem_id = ?'; $p[] = $donemId; }
    if ($durum) { $sql .= ' AND b.durum = ?'; $p[] = $durum; }
    if ($idler !== null) {
        if (!$idler) return [];
        $sql .= ' AND b.id IN (' . implode(',', array_fill(0, count($idler), '?')) . ')';
        foreach ($idler as $i) $p[] = $i;
    }
    $sql .= ' ORDER BY (b.durum = \'danisman_bekliyor\') DESC, d.baslangic DESC, b.basvuru_tarihi DESC';
    $s = $pdo->prepare($sql);
    $s->execute($p);
    $satirlar = $s->fetchAll();
    foreach ($satirlar as &$x) $x['rapor'] = uygunluk_raporu($pdo, (int)$x['ogrenci_id']);
    return $satirlar;
}

function filtre_temizle(string $f): string { return preg_replace('/[^A-Za-z0-9_=&%.\-]/', '', $f); }

/* ---------------------------------------------------------------- POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = (string)($_POST['islem'] ?? '');
    $f = filtre_temizle((string)($_POST['filtre'] ?? ''));
    $geri = '/danisman/index.php' . ($f !== '' ? '?' . $f : '');
    $secili = $_POST['secili'] ?? [];
    $secili = is_array($secili) ? array_values(array_unique(array_filter(array_map('intval', $secili), fn($x) => $x > 0))) : [];

    if (str_starts_with($islem, 'karar:')) {                       // bireysel karar: karar:<başvuru id>:<onay|iade|red>
        [, $bid, $karar] = array_pad(explode(':', $islem), 3, '');
        $g = $_POST['gerekce'] ?? [];
        $gerekce = is_array($g) ? trim((string)($g[(int)$bid] ?? '')) : '';
        [$ok, $m] = danisman_karar_uygula($pdo, $u['id'], (int)$bid, $karar, $gerekce);
        flash($m, $ok ? 'ok' : 'err');
        redirect($geri);
    }
    if ($islem === 'toplu_onizle') {                               // 1. adım: onay ekranı
        if (!$secili) { flash('Toplu onay için en az bir başvuru seçmelisiniz.', 'err'); redirect($geri); }
        $_SESSION['toplu_onizleme'] = $secili;
        redirect('/danisman/index.php?toplu=onizle');
    }
    if ($islem === 'toplu_uygula') {                               // 2. adım: sunucu her başvuruyu yeniden doğrulayarak işler
        $sonuc = toplu_onay_uygula($pdo, $u['id'], $secili);
        unset($_SESSION['toplu_onizleme']);
        $_SESSION['toplu_sonuc'] = $sonuc;
        redirect('/danisman/index.php?toplu=sonuc');
    }
    flash('Geçersiz işlem.', 'err');
    redirect($geri);
}

/* ---------------------------------------------------------------- Toplu onay: sonuç raporu */
if (($_GET['toplu'] ?? '') === 'sonuc') {
    $sonuc = $_SESSION['toplu_sonuc'] ?? null;
    unset($_SESSION['toplu_sonuc']);
    layout_header('Toplu Onay Sonucu', ['kirinti' => [['Ana Sayfa', BASE_URL . '/danisman/index.php'], ['Toplu Onay Sonucu']]]);
    echo '<section class="kart">';
    if (!$sonuc) echo '<p class="muted">Gösterilecek sonuç yok.</p>';
    else {
        echo '<div class="bildirim ok">İşlem #' . (int)$sonuc['id'] . ': <b>' . (int)$sonuc['onaylanan'] . '</b> başvuru onaylandı, <b>' . (int)$sonuc['atlanan'] . '</b> başvuru atlandı veya işlenemedi.</div>';
        echo '<div class="tablo-kap"><table class="tablo"><thead><tr><th>Öğrenci</th><th>Sonuç</th><th>Ayrıntı</th></tr></thead><tbody>';
        foreach ($sonuc['sonuclar'] as $r) echo '<tr><td>' . e($r['ogrenci_no']) . ' – ' . e($r['ad_soyad']) . '</td><td>' . rozet($r['ok'] ? 'Onaylandı' : 'Atlandı', $r['ok'] ? 'yesil' : 'kirmizi') . '</td><td>' . e($r['mesaj']) . '</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '<div class="satir"><a class="dugme" href="' . e(BASE_URL) . '/danisman/index.php">Danışman paneline dön</a></div></section>';
    layout_footer();
    exit;
}

/* ---------------------------------------------------------------- Toplu onay: onay ekranı */
if (($_GET['toplu'] ?? '') === 'onizle') {
    $idler = $_SESSION['toplu_onizleme'] ?? [];
    $satirlar = danisman_satirlari($pdo, $u['id'], null, null, $idler);
    $islenecek = array_filter($satirlar, fn($x) => $x['durum'] === 'danisman_bekliyor' && $x['rapor']['uygun']);
    layout_header('Toplu Onay – Onay Ekranı', ['kirinti' => [['Ana Sayfa', BASE_URL . '/danisman/index.php'], ['Toplu Onay']]]);
    echo '<section class="kart"><div class="bildirim ' . ($islenecek ? 'ok' : 'err') . '"><b>' . count($islenecek) . '</b> başvuru onaylanacak, <b>' . (count($satirlar) - count($islenecek)) . '</b> başvuru uygun olmadığı için atlanacak. Seçilen toplam: ' . count($idler) . '.</div>';
    echo '<div class="tablo-kap"><table class="tablo"><thead><tr><th>Öğrenci</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
    foreach ($satirlar as $x) {
        $uygun = $x['durum'] === 'danisman_bekliyor' && $x['rapor']['uygun'];
        echo '<tr><td>' . e($x['ogrenci_no']) . ' – ' . e($x['ad_soyad']) . '</td><td>' . durum_rozeti($x['durum']) . '</td><td>'
           . rozet($uygun ? 'Onaylanacak' : 'Atlanacak (' . ($x['durum'] !== 'danisman_bekliyor' ? 'danışman onayı beklemiyor' : 'mezuniyet koşulları eksik') . ')', $uygun ? 'yesil' : 'kirmizi') . '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<form method="post" class="satir">' . csrf_field() . '<input type="hidden" name="islem" value="toplu_uygula">';
    foreach ($idler as $i) echo '<input type="hidden" name="secili[]" value="' . (int)$i . '">';
    echo '<button class="dugme yesil" ' . ($islenecek ? '' : 'disabled') . ' onclick="return confirm(\'Seçilen uygun başvurular onaylansın mı?\')">Onayla ve uygula</button> '
       . '<a class="dugme hayalet" href="' . e(BASE_URL) . '/danisman/index.php">Vazgeç</a></form></section>';
    layout_footer();
    exit;
}

/* ---------------------------------------------------------------- Dashboard */
$fDonem = (int)($_GET['donem'] ?? 0) ?: null;
$fDurum = (string)($_GET['durum'] ?? '');
$fDurum = isset(DURUM_ETIKETLERI[$fDurum]) ? $fDurum : null;
$fUygun = (string)($_GET['uygunluk'] ?? '');
$satirlar = danisman_satirlari($pdo, $u['id'], $fDonem, $fDurum);
if ($fUygun === 'uygun') $satirlar = array_filter($satirlar, fn($x) => $x['rapor']['uygun']);
if ($fUygun === 'uygun_degil') $satirlar = array_filter($satirlar, fn($x) => !$x['rapor']['uygun']);
$satirlar = array_values($satirlar);

$say = $pdo->prepare('SELECT b.durum, COUNT(*) FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE o.danisman_id = ? GROUP BY b.durum');
$say->execute([$u['id']]);
$sayilar = $say->fetchAll(PDO::FETCH_KEY_PAIR);
$toplam = array_sum($sayilar);
$bekleyen = (int)($sayilar['danisman_bekliyor'] ?? 0);
$iade = (int)($sayilar['iade'] ?? 0);
$red = (int)($sayilar['danisman_red'] ?? 0);
$onayli = $toplam - $bekleyen - $iade - $red;

$donemSecenekleri = $pdo->prepare('SELECT DISTINCT d.* FROM basvuru_donemleri d JOIN basvurular b ON b.donem_id = d.id JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE o.danisman_id = ? ORDER BY d.baslangic DESC');
$donemSecenekleri->execute([$u['id']]);
$donemSecenekleri = $donemSecenekleri->fetchAll();
$sorgu = (string)($_SERVER['QUERY_STRING'] ?? '');

layout_header('Danışman Paneli', ['alt' => 'Danışmanı olduğunuz öğrencilerin mezuniyet başvuruları. Satıra tıklayarak tam sayfa incelemeyi açın; karar inceleme sayfasında verilir.']);
?>
<div class="kpi-izgara">
  <?= kpi_karti('Toplam öğrenci başvurusu', $toplam, 'Tüm dönemler', 'mavi', 'dosya') ?>
  <?= kpi_karti('Onay bekleyenler', $bekleyen, 'Kararınızı bekliyor', 'turuncu', 'oy') ?>
  <?= kpi_karti('İade edilenler', $iade, 'Öğrencide düzeltme bekliyor', 'turuncu', 'gecmis') ?>
  <?= kpi_karti('Reddedilenler', $red, 'Danışman tarafından', 'kirmizi', 'kapat') ?>
  <?= kpi_karti('Onaylananlar', $onayli, 'Komisyona gönderilen', 'yesil', 'tik') ?>
</div>

<section class="kart" id="liste">
  <form method="get" class="arac">
    <div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="danisman" placeholder="Öğrenci no veya ad soyad ara" aria-label="Ara"></div>
    <select name="donem" aria-label="Başvuru dönemi" style="width:auto"><option value="">Tüm dönemler</option>
      <?php foreach ($donemSecenekleri as $d) echo '<option value="' . (int)$d['id'] . '"' . ($fDonem === (int)$d['id'] ? ' selected' : '') . '>' . e(donem_etiketi($d)) . '</option>'; ?></select>
    <select name="uygunluk" aria-label="Uygunluk" style="width:auto"><option value="">Tüm uygunluk durumları</option>
      <option value="uygun"<?= $fUygun === 'uygun' ? ' selected' : '' ?>>Mezuniyete uygun</option><option value="uygun_degil"<?= $fUygun === 'uygun_degil' ? ' selected' : '' ?>>Eksik koşulu olan</option></select>
    <select name="durum" aria-label="Durum" style="width:auto"><option value="">Tüm durumlar</option>
      <?php foreach (DURUM_KISA as $k => $v) echo '<option value="' . e($k) . '"' . ($fDurum === $k ? ' selected' : '') . '>' . e($v) . '</option>'; ?></select>
    <button class="dugme" type="submit">Filtrele</button><a class="dugme hayalet" href="<?= e(BASE_URL) ?>/danisman/index.php">Temizle</a>
  </form>

  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="filtre" value="<?= e(filtre_temizle($sorgu)) ?>">
    <div class="arac" style="justify-content:space-between">
      <label style="margin:0;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" data-hepsi="sec"> Görünen uygun başvuruların tümünü seç</label>
      <button class="dugme yesil" name="islem" value="toplu_onizle" type="submit">Seçilen uygun başvuruları toplu onayla</button>
    </div>
    <p class="muted" style="margin-top:-6px">Yalnızca onayınızı bekleyen ve mezuniyet koşulları sağlanan başvurular seçilebilir; sunucu her başvuruyu yeniden doğrular.</p>

    <div class="tablo-kap"><table class="tablo nw" data-tablo="danisman">
      <thead><tr><th></th><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th data-sirala>Dönem</th><th data-sirala>Başvuru tarihi</th><th data-sirala>GANO</th><th data-sirala>AKTS</th><th data-sirala>Uygunluk</th><th data-sirala>Durum</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($satirlar as $x):
          $secilebilir = $x['durum'] === 'danisman_bekliyor' && $x['rapor']['uygun'];
          $url = BASE_URL . '/danisman/inceleme.php?id=' . (int)$x['id']; ?>
        <tr data-href="<?= e($url) ?>" style="cursor:pointer">
          <td><?php if ($secilebilir): ?><input type="checkbox" class="sec" name="secili[]" value="<?= (int)$x['id'] ?>" aria-label="Seç"><?php endif; ?></td>
          <td><?= e($x['ogrenci_no']) ?></td>
          <td><b><?= e($x['ad_soyad']) ?></b></td>
          <td data-v="<?= e($x['baslangic'] ?? '') ?>"><?= $x['donem_id'] ? e(donem_etiketi($x)) : '-' ?></td>
          <td data-v="<?= e($x['basvuru_tarihi']) ?>"><?= e(tarih_tr($x['basvuru_tarihi'], true)) ?></td>
          <td><?= $x['a_gano'] !== null ? e(number_format((float)$x['a_gano'], 2)) : '–' ?></td>
          <td><?= $x['tamamlanan_akts'] !== null ? (int)$x['tamamlanan_akts'] . ' / ' . ($x['gereken_akts'] !== null ? (int)$x['gereken_akts'] : '?') : '–' ?></td>
          <td data-v="<?= $x['rapor']['uygun'] ? 'Uygun' : 'Eksik' ?>"><?= rozet($x['rapor']['uygun'] ? 'Uygun' : 'Eksik koşul var', $x['rapor']['uygun'] ? 'yesil' : 'kirmizi', $x['rapor']['uygun'] ? '' : implode(' ', $x['rapor']['eksikler'])) ?></td>
          <td><?= durum_rozeti($x['durum']) ?></td>
          <td><a class="dugme hayalet kucuk" href="<?= e($url) ?>">İncele</a></td>
        </tr>
      <?php endforeach; ?>
        <tr class="bos-satir"<?= $satirlar ? ' style="display:none"' : '' ?>><td colspan="10">Bu filtrelere uyan mezuniyet başvurusu yok.</td></tr>
      </tbody>
    </table></div>
    <div class="tablo-alt"><span data-sayac="danisman"></span><span>Sütun başlığına tıklayarak sıralayabilirsiniz.</span></div>
  </form>
</section>
<?php layout_footer();
