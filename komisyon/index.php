<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['komisyon']);
$pdo = db();

const KOMISYON_KAPSAM = "b.durum NOT IN ('danisman_bekliyor','iade','danisman_red')";   // komisyona yalnızca danışman onayından geçenler görünür
function f_temizle(string $f): string { return preg_replace('/[^A-Za-z0-9_=&%.\-]/', '', $f); }

/* ---------------------------------------------------------------- Oy kullanma */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = f_temizle((string)($_POST['filtre'] ?? ''));
    $secili = $_POST['secili'] ?? [];
    if (($_POST['islem'] ?? '') === 'toplu_onay' && is_array($secili) && $secili) {
        toplu_sonuc_kaydet(komisyon_toplu_onay($pdo, $u['id'], $secili));   // her öğrenci için ayrı oy + ayrı kayıt; 3/5 kuralı oy sırasında işler
    } else {
        flash('Toplu onay için en az bir başvuru seçmelisiniz.', 'err');
    }
    redirect('/komisyon/index.php' . ($f !== '' ? '?' . $f : ''));
}

/* ---------------------------------------------------------------- Veriler */
$liste = $pdo->query("SELECT b.id, b.durum, b.basvuru_tarihi, b.mezuniyete_uygun, o.ogrenci_no, o.ad_soyad, o.danisman_id, dk.ad_soyad AS danisman_ad,
    a.gano, a.tamamlanan_akts, a.gereken_akts
    FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id JOIN kullanicilar dk ON dk.id = o.danisman_id
    LEFT JOIN akademik_kayitlari a ON a.ogrenci_id = o.id
    WHERE " . KOMISYON_KAPSAM . " ORDER BY (b.durum = 'komisyon_bekliyor') DESC, b.basvuru_tarihi DESC")->fetchAll();
$oylar = komisyon_oylari($pdo, array_column($liste, 'id'));
$eksik = $pdo->query("SELECT basvuru_id, GROUP_CONCAT(mesaj SEPARATOR ' | ') FROM basvuru_uygunluk WHERE durum <> 'tamam' GROUP BY basvuru_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$uyeler = komisyon_uyeleri($pdo);

$ozet = ['toplam' => count($liste), 'inceleme' => 0, 'oylama' => 0, 'onay' => 0, 'red' => 0, 'bekleyen' => 0];
foreach ($liste as &$x) {
    $o = $oylar[(int)$x['id']] ?? [];
    [$x['on'], $x['re']] = oy_sayilari($o);
    $x['benim'] = $o[$u['id']]['karar'] ?? null;
    if ($x['durum'] === 'komisyon_red') $x['grup'] = 'red';
    elseif ($x['durum'] === 'komisyon_bekliyor') $x['grup'] = 'devam';
    else $x['grup'] = 'onay';
    $x['bekleyen'] = $x['durum'] === 'komisyon_bekliyor' && !$x['benim'];
    if ($x['grup'] === 'devam') { if ($o) $ozet['oylama']++; else $ozet['inceleme']++; }
    if ($x['grup'] === 'onay') $ozet['onay']++;
    if ($x['grup'] === 'red') $ozet['red']++;
    if ($x['bekleyen']) $ozet['bekleyen']++;
}
unset($x);

$f = (string)($_GET['f'] ?? 'tum');
if (!in_array($f, ['tum', 'bekleyen', 'devam', 'onay', 'red'], true)) $f = 'tum';
$gorunen = array_values(array_filter($liste, fn($x) => match ($f) { 'bekleyen' => $x['bekleyen'], 'devam' => $x['grup'] === 'devam', 'onay' => $x['grup'] === 'onay', 'red' => $x['grup'] === 'red', default => true }));
$sayilar = ['tum' => count($liste), 'bekleyen' => $ozet['bekleyen'], 'devam' => $ozet['inceleme'] + $ozet['oylama'], 'onay' => $ozet['onay'], 'red' => $ozet['red']];
$danismanlar = array_unique(array_column($liste, 'danisman_ad'));
sort($danismanlar);

// Kurul şeridi: bekleyen (komisyon incelemesindeki) başvurular içinde her üyenin kullandığı oy sayısı
$bekleyenToplam = (int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum = 'komisyon_bekliyor'")->fetchColumn();
$uyeOy = $pdo->query("SELECT k.uye_id, COUNT(*) FROM komisyon_kararlari k JOIN basvurular b ON b.id = k.basvuru_id WHERE b.durum = 'komisyon_bekliyor' GROUP BY k.uye_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$sorgu = (string)($_SERVER['QUERY_STRING'] ?? '');

layout_header('Komisyon Paneli', ['alt' => 'Beş komisyon üyesi aynı başvuru listesi üzerinden çalışır; her üye yalnızca kendi hesabıyla ve kendi adına oy kullanır. Birden fazla öğrenciyi onaylamak için kutuları işaretleyip toplu onay verebilirsiniz.']);
?>
<div class="kpi-izgara">
  <?= kpi_karti('Toplam başvuru', $ozet['toplam'], 'Komisyona ulaşan', 'mavi', 'dosya') ?>
  <?= kpi_karti('İnceleme bekleyen', $ozet['inceleme'], 'Henüz hiç oy yok', 'gri', 'gecmis') ?>
  <?= kpi_karti('Oylama devam eden', $ozet['oylama'], 'En az bir oy var, karar yok', 'mavi', 'oy') ?>
  <?= kpi_karti('Komisyon onayladı', $ozet['onay'], ONAY_ESIGI . ' veya daha fazla onay', 'yesil', 'tik') ?>
  <?= kpi_karti('Reddedilen', $ozet['red'], 'Karar kesinleşti', 'kirmizi', 'kapat') ?>
  <?= kpi_karti('Karar bekleyen (sizin oyunuz)', $ozet['bekleyen'], 'Oy kullanmanız gereken', 'turuncu', 'oy') ?>
</div>

<section class="kart">
  <div class="kart-baslik"><h2><?= ikon('kisiler') ?> Komisyon üyeleri</h2><span class="muted">Karar kuralı: <?= KOMISYON_UYE_SAYISI ?> üyeden en az <b><?= ONAY_ESIGI ?></b> onay = kabul · <b><?= KOMISYON_UYE_SAYISI - ONAY_ESIGI + 1 ?></b> ret = ret</span></div>
  <div class="kurul">
    <?php foreach ($uyeler as $m): $ben = (int)$m['id'] === (int)$u['id']; $kullanilan = (int)($uyeOy[$m['id']] ?? 0); $kalan = max(0, $bekleyenToplam - $kullanilan); ?>
      <div class="koltuk<?= $ben ? ' ben' : '' ?>">
        <div class="koltuk-ust"><span class="kod"><?= e($m['kullanici_no']) ?></span><?= $ben ? rozet('Aktif · siz', 'mavi') : ($kalan === 0 ? rozet('Oy kullandı', 'yesil') : rozet('Bekliyor', 'turuncu')) ?></div>
        <b><?= e($m['ad_soyad']) ?></b>
        <small><?= $bekleyenToplam === 0 ? 'İncelemede başvuru yok' : $kullanilan . ' / ' . $bekleyenToplam . ' başvuruda oy kullandı' ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="kart" id="liste">
  <div class="kart-baslik"><h2><?= ikon('dosya') ?> Başvuru listesi</h2></div>
  <div class="cipler" style="margin-bottom:12px">
    <?php foreach (['tum' => 'Tüm başvurular', 'bekleyen' => 'Oylama bekleyenler', 'devam' => 'İnceleme devam edenler', 'onay' => 'Komisyonca onaylananlar', 'red' => 'Reddedilenler'] as $k => $ad): ?>
      <a class="cip<?= $f === $k ? ' aktif' : '' ?>" href="<?= e(BASE_URL) ?>/komisyon/index.php?f=<?= e($k) ?>#liste"><?= e($ad) ?><b><?= (int)$sayilar[$k] ?></b></a>
    <?php endforeach; ?>
  </div>
  <div class="arac">
    <div class="arama"><?= ikon('ara', 16) ?><input type="search" data-ara="komisyon" placeholder="Öğrenci numarası veya ad soyad ara" aria-label="Ara"></div>
    <select data-filtre="komisyon" data-alan="danisman" aria-label="Danışmana göre filtrele" style="width:auto"><option value="">Tüm danışmanlar</option>
      <?php foreach ($danismanlar as $d) echo '<option value="' . e($d) . '">' . e($d) . '</option>'; ?></select>
  </div>

  <form method="post" data-toplu-form>
  <?= csrf_field() ?><input type="hidden" name="filtre" value="<?= e(f_temizle($sorgu)) ?>">
  <div class="toplu-bar"><label style="margin:0;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" data-hepsi="sec"> Uygun olanların tümünü seç</label>
    <span data-secili-sayac>0 kayıt seçili</span>
    <button class="dugme yesil" name="islem" value="toplu_onay" data-toplu data-onay="Seçilen {n} başvuru için ONAY oyunuz kaydedilecek ve değiştirilemez. Oy verdiğiniz veya eksik koşulu olan başvurular otomatik atlanır; 3/5 kuralı her öğrencide ayrıca işler. Devam edilsin mi?">Seçilenleri toplu onayla</button></div>
  <div class="tablo-kap"><table class="tablo nw" data-tablo="komisyon">
    <thead><tr><th></th><th data-sirala>Öğrenci no</th><th data-sirala>Ad soyad</th><th data-sirala>Akademik danışman</th><th data-sirala>GANO</th><th data-sirala>AKTS</th><th data-sirala>Başvuru tarihi</th><th data-sirala>Akademik uygunluk</th><th>Eksik koşullar</th><th>Komisyon oy durumu</th><th data-sirala>Başvuru durumu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($gorunen as $x): $url = BASE_URL . '/komisyon/inceleme.php?id=' . (int)$x['id']; $secilebilir = $x['bekleyen'] && $x['mezuniyete_uygun']; ?>
      <tr data-href="<?= e($url) ?>" data-danisman="<?= e($x['danisman_ad']) ?>" style="cursor:pointer">
        <td><?php if ($secilebilir): ?><input type="checkbox" class="sec" name="secili[]" value="<?= (int)$x['id'] ?>" aria-label="Seç"><?php endif; ?></td>
        <td><?= e($x['ogrenci_no']) ?></td>
        <td><b><?= e($x['ad_soyad']) ?></b></td>
        <td><?= e($x['danisman_ad']) ?></td>
        <td><?= $x['gano'] !== null ? e(number_format((float)$x['gano'], 2)) : '–' ?></td>
        <td><?= $x['tamamlanan_akts'] !== null ? (int)$x['tamamlanan_akts'] . ' / ' . ($x['gereken_akts'] !== null ? (int)$x['gereken_akts'] : '?') : '–' ?></td>
        <td data-v="<?= e($x['basvuru_tarihi']) ?>"><?= e(tarih_tr($x['basvuru_tarihi'], true)) ?></td>
        <td data-v="<?= $x['mezuniyete_uygun'] ? 'Uygun' : 'Eksik' ?>"><?= rozet($x['mezuniyete_uygun'] ? 'Uygun' : 'Eksik', $x['mezuniyete_uygun'] ? 'yesil' : 'kirmizi') ?></td>
        <td><?php $ek = $eksik[$x['id']] ?? ''; echo $ek !== '' ? rozet('Eksik var', 'kirmizi', $ek) : rozet('Yok', 'gri'); ?></td>
        <td><?= oy_noktalari_html($uyeler, $oylar[(int)$x['id']] ?? []) ?><small><?= (int)$x['on'] ?> onay · <?= (int)$x['re'] ?> ret</small>
            <?php if ($x['benim']): ?><br><small>Sizin oyunuz: <b><?= $x['benim'] === 'onay' ? 'Onay' : 'Ret' ?></b></small><?php endif; ?></td>
        <td><?= durum_rozeti($x['durum']) ?></td>
        <td><a class="dugme <?= $x['bekleyen'] ? '' : 'hayalet' ?> kucuk" href="<?= e($url) ?>"><?= $x['bekleyen'] ? 'İncele ve oyla' : 'İncele' ?></a></td>
      </tr>
    <?php endforeach; ?>
      <tr class="bos-satir"<?= $gorunen ? ' style="display:none"' : '' ?>><td colspan="12">Bu filtreye uyan başvuru yok.</td></tr>
    </tbody>
  </table></div>
  </form>
  <div class="tablo-alt"><span data-sayac="komisyon"></span><span>Noktalar: yeşil onay · kırmızı ret · gri bekliyor (imleci üzerine getirince üye adı görünür).</span></div>
</section>
<?php layout_footer();
