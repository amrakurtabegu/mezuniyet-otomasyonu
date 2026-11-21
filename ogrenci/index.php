<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['ogrenci']);
$pdo = db();

/* 1) Öğrenci profili: önce yerel kayıt; yoksa üniversite servisinden (şimdilik mock) eşitlenir.
      Her başarısızlık nedeni ayrı ve açık bir mesajla gösterilir. */
$s = $pdo->prepare('SELECT * FROM ogrenciler WHERE kullanici_id = ?');
$s->execute([$u['id']]);
$og = $s->fetch() ?: null;
$profilHata = null;
if (!$og) {
    [$ok, $kod] = ogrenci_senkronize($pdo, $u['id'], $u['no']);
    if ($ok) { $s->execute([$u['id']]); $og = $s->fetch() ?: null; } else $profilHata = $kod;
}
if (!$og) {
    $mesajlar = [
        'profil_yok'  => 'Giriş başarılı; ancak bu numara (' . $u['no'] . ') için öğrenci profili bulunamadı. Üniversite öğrenci kayıt servisinde kaydınız görünmüyor. Lütfen bölüm sekreterliği ile iletişime geçin.',
        'danisman_yok' => 'Giriş başarılı; ancak danışman atamanız sistemde tanımlı değil. Lütfen bölüm sekreterliğinden danışman ataması isteyin.',
        'teknik_hata' => 'Öğrenci bilgileriniz şu anda alınamadı (teknik bir sorun var). Lütfen daha sonra tekrar deneyin.',
    ];
    layout_header('Öğrenci Paneli');
    echo '<div class="bildirim err">' . e($mesajlar[$profilHata] ?? $mesajlar['teknik_hata']) . '</div>';
    layout_footer();
    exit;
}

/* 2) İşlemler (resmî akademik veriler burada DEĞİŞTİRİLEMEZ; yalnızca iletişim/çalışma bilgisi ve başvuru) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = (string)($_POST['islem'] ?? '');
    if ($islem === 'bilgi_kaydet') {
        $calisma = trim((string)($_POST['calisma_durumu'] ?? ''));
        $eposta = trim((string)($_POST['eposta'] ?? ''));
        $tel = telefon_normalize((string)($_POST['telefon'] ?? ''));
        if ($calisma === '') flash('Çalışma durumunu giriniz.', 'err');
        elseif (!eposta_gecerli($eposta)) flash('Geçerli bir e-posta adresi giriniz.', 'err');
        elseif ($tel === null) flash('Geçerli bir cep telefonu giriniz (örn. 0555 123 45 67).', 'err');
        else {
            $pdo->prepare('UPDATE ogrenciler SET calisma_durumu=?, eposta=?, telefon=? WHERE id=?')->execute([mb_substr($calisma, 0, 255), $eposta, $tel, $og['id']]);
            log_islem(null, 'Öğrenci iletişim/çalışma bilgilerini güncelledi: ' . $og['ogrenci_no']);
            flash('Bilgileriniz kaydedildi.');
        }
    } elseif ($islem === 'basvuru_baslat') {
        [$ok, $m] = basvuru_olustur($pdo, $og);
        flash($m, $ok ? 'ok' : 'err');
    } elseif ($islem === 'tekrar_gonder') {
        [$ok, $m] = basvuru_tekrar_gonder($pdo, $og, (int)($_POST['basvuru_id'] ?? 0));
        flash($m, $ok ? 'ok' : 'err');
    } elseif ($islem === 'yenile') {
        [$ok, $kod] = ogrenci_senkronize($pdo, $u['id'], $u['no']);
        flash($ok ? 'Akademik bilgileriniz üniversite servisinden yenilendi.' : 'Akademik bilgiler yenilenemedi (' . $kod . ').', $ok ? 'ok' : 'err');
    }
    redirect('/ogrenci/index.php');
}

/* 3) Görüntüleme verileri */
$on = basvuru_on_kosullari($pdo, $og);
$rapor = $on['rapor'];
$b = $pdo->prepare('SELECT b.*, d.yil, d.ay, d.donem, d.baslangic, d.bitis FROM basvurular b LEFT JOIN basvuru_donemleri d ON d.id = b.donem_id WHERE b.ogrenci_id = ? ORDER BY b.id DESC');
$b->execute([$og['id']]);
$basvurular = $b->fetchAll();
$oz = akademik_ozet($pdo, (int)$og['id']);
$ak = $oz['ak'];
$guncel = $basvurular[0] ?? null;
$belge = null;
if ($guncel) { $bq = $pdo->prepare('SELECT * FROM mezuniyet_belgeleri WHERE basvuru_id = ?'); $bq->execute([$guncel['id']]); $belge = $bq->fetch() ?: null; }
$stajOnay = count(array_filter($oz['staj'], fn($d) => $d === 'onaylandi'));
$zorunlu = array_filter($oz['dersler'], fn($x) => (int)$x['zorunlu'] === 1);
$dersGecen = count(array_filter($zorunlu, fn($x) => $x['durum'] === 'gecti'));

layout_header('Mezuniyet Süreci', ['alt' => 'Hoş geldiniz, ' . $og['ad_soyad'] . '. Başvurunuzun hangi aşamada olduğunu bu ekrandan izleyebilirsiniz.']);
?>
<div class="ogr-ozet">
  <div class="kart" style="margin:0"><small>GANO</small><div class="buyuk-sayi"><?= $ak && $ak['gano'] !== null ? e(number_format((float)$ak['gano'], 2)) : '–' ?></div><small>Mezuniyet için en az <?= e(number_format((float)(kural_degeri($pdo, 'GANO_MIN') ?? '2.50'), 2)) ?></small></div>
  <div class="kart" style="margin:0"><small>Tamamlanan AKTS</small><div class="buyuk-sayi"><?= $ak && $ak['tamamlanan_akts'] !== null ? (int)$ak['tamamlanan_akts'] : '–' ?><small> / <?= $ak && $ak['gereken_akts'] !== null ? (int)$ak['gereken_akts'] : '?' ?></small></div><?= akts_cubugu_html($ak) ?></div>
  <div class="kart" style="margin:0"><small>Staj durumu</small><div class="buyuk-sayi"><?= $stajOnay ?><small> / 3 onaylı</small></div><small>İki yaz stajı ve uzun dönem staj / İME</small></div>
  <div class="kart" style="margin:0"><small>Zorunlu dersler</small><div class="buyuk-sayi"><?= $dersGecen ?><small> / <?= count($zorunlu) ?> geçildi</small></div><small>Eksik ders varsa başvuru yapılamaz</small></div>
</div>

<section class="kart" id="surec">
  <div class="kart-baslik"><h2><?= ikon('surec') ?> Süreç takibi</h2><?= $guncel ? durum_rozeti($guncel['durum']) : '' ?></div>
  <?php if ($guncel): $ton = durum_tonu($guncel['durum']); ?>
    <div class="durum-serit <?= e($ton === 'kirmizi' ? 'kirmizi' : ($ton === 'yesil' ? 'yesil' : '')) ?>">
      <div><b><?= e(durum_etiketi($guncel['durum'])) ?></b><br><span class="muted">Başvuru tarihi: <?= e(tarih_tr($guncel['basvuru_tarihi'], true)) ?><?= $guncel['donem_id'] ? ' · ' . e(donem_etiketi($guncel)) : '' ?></span></div>
      <?php if ($belge): [$bt, $bton] = belge_durum_etiketi($guncel['durum'], $belge); ?><div style="margin-left:auto"><?= rozet($bt, $bton) ?><br><small>Belge no: <?= e($belge['belge_no']) ?></small></div><?php endif; ?>
    </div>
    <?= zaman_cizelgesi_html($guncel['durum'], $belge) ?>
    <?php
      $dk = $pdo->prepare('SELECT * FROM danisman_kararlari WHERE basvuru_id = ? ORDER BY id DESC LIMIT 1'); $dk->execute([$guncel['id']]); $dk = $dk->fetch();
      if ($dk && in_array($dk['karar'], ['iade', 'red'], true) && in_array($guncel['durum'], ['iade', 'danisman_red'], true)) echo '<div class="bildirim err" style="margin-top:14px"><b>' . ($dk['karar'] === 'iade' ? 'Düzeltme isteği' : 'Ret gerekçesi') . ':</b> ' . e($dk['gerekce']) . '</div>';
      if ($guncel['durum'] === 'komisyon_red') {
          $rr = $pdo->prepare("SELECT aciklama FROM komisyon_kararlari WHERE basvuru_id = ? AND karar = 'red' AND aciklama IS NOT NULL"); $rr->execute([$guncel['id']]);
          foreach ($rr->fetchAll(PDO::FETCH_COLUMN) as $a) echo '<div class="bildirim err" style="margin-top:14px"><b>Komisyon açıklaması:</b> ' . e($a) . '</div>';
      }
      if ($guncel['durum'] === 'iade'): ?>
      <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="tekrar_gonder"><input type="hidden" name="basvuru_id" value="<?= (int)$guncel['id'] ?>">
        <button class="dugme turuncu">Düzeltmeyi tamamladım, yeniden gönder</button></form>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">Henüz bir mezuniyet başvurunuz yok. Aşağıdaki “Mezuniyet başvurusu” bölümünden başlatabilirsiniz.</p>
    <?= zaman_cizelgesi_html('danisman_bekliyor') ?>
  <?php endif; ?>
</section>

<section class="kart" id="belge">
  <div class="kart-baslik"><h2><?= ikon('belge') ?> Mezuniyet belgem</h2><?= $guncel ? rozet(...belge_durum_etiketi($guncel['durum'])) : rozet('Henüz başvuru yok', 'gri') ?></div>
  <?php if ($guncel && $guncel['durum'] === 'tamamlandi' && $belge): ?>
    <div class="durum-serit yesil"><div><b>E-imzalı mezuniyet belgeniz hazır.</b><br><span class="muted">Belge no: <?= e($belge['belge_no']) ?> · E-imza (simülasyon) tarihi: <?= e(tarih_tr($belge['eimza_tarihi'], true)) ?></span></div>
      <div style="margin-left:auto" class="satir"><a class="dugme hayalet" href="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= (int)$guncel['id'] ?>">Belgeyi görüntüle</a>
        <a class="dugme yesil" href="<?= e(BASE_URL) ?>/pdf/mezuniyet_belgesi.php?basvuru_id=<?= (int)$guncel['id'] ?>&dosya=pdf&indir=1">PDF indir</a></div></div>
    <p class="muted">E-imza adımı yalnızca simülasyondur; belgenin hukuki geçerliliği yoktur.</p>
  <?php else: ?><p class="muted">Süreç tamamlandığında (liste kapatılıp e-imza onayı verildiğinde) belgeniz otomatik olarak buraya eklenir; ayrıca bir işlem yapmanız gerekmez.</p><?php endif; ?>
</section>

<div class="iki-kolon">
  <div>
    <section class="kart" id="basvuru">
      <div class="kart-baslik"><h2><?= ikon('dosya') ?> Mezuniyet başvurusu</h2>
        <?= rozet($on['donem']['durum'] === 'acik' ? 'Başvurular açık' : 'Başvurular kapalı', $on['donem']['durum'] === 'acik' ? 'yesil' : 'kirmizi') ?></div>
      <p><b>Başvuru dönemi:</b> <?= e(donem_etiketi($on['donem'])) ?></p>
      <div class="bildirim <?= $on['izin'] ? 'ok' : 'err' ?>" style="margin-bottom:0"><?= e($on['mesaj']) ?></div>
      <?php if ($on['izin'] && !$rapor['uygun']): ?><div class="uyari-kutu" style="margin-top:12px"><b>Eksik koşullarınız var.</b> Yine de başvurabilirsiniz; eksikler sistemde tamamlanmış sayılmaz, danışmanınız ve komisyon tarafından görülür. Danışmanınız gerekçeyle onaylamazsa başvurunuz reddedilebilir.</div><?php endif; ?>
      <form method="post" class="satir"><?= csrf_field() ?><input type="hidden" name="islem" value="basvuru_baslat">
        <button class="dugme yesil" type="submit" <?= $on['izin'] ? '' : 'disabled' ?>>Mezuniyet incelemesini başlat</button></form>
    </section>

    <section class="kart" id="akademik">
      <div class="kart-baslik"><h2><?= ikon('rapor') ?> Akademik durum ve uygunluk raporu</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="islem" value="yenile"><button class="dugme hayalet kucuk" type="submit">Akademik bilgilerimi yenile</button></form></div>
      <?= uygunluk_html($rapor) ?>
      <details class="det" style="margin-top:14px"><summary>Staj, ders ve ek koşul ayrıntıları</summary><div class="det-ic"><?= akademik_tablolari_html($oz) ?></div></details>
    </section>
  </div>

  <div>
    <section class="kart" id="kimlik">
      <div class="kart-baslik"><h2><?= ikon('kullanici') ?> Kişisel bilgiler</h2></div>
      <dl class="ozet-izgara" style="grid-template-columns:1fr;margin:0;border:0;padding:0">
        <div><dt>Öğrenci no</dt><dd><?= e($og['ogrenci_no']) ?></dd></div>
        <div><dt>Ad soyad</dt><dd><?= e($og['ad_soyad']) ?></dd></div>
        <div><dt>Bölüm / program</dt><dd><?= e($og['bolum']) ?><?= $og['program'] ? ' / ' . e($og['program']) : '' ?></dd></div>
      </dl>
      <p class="muted">Bu bilgiler üniversite sisteminden alınır ve değiştirilemez.</p>
    </section>

    <section class="kart" id="iletisim">
      <div class="kart-baslik"><h2><?= ikon('kullanici') ?> İletişim ve çalışma bilgileri</h2></div>
      <p class="muted">Mezuniyet sonrası iletişim ve mezun izleme çalışmalarında kullanılacaktır.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="islem" value="bilgi_kaydet">
        <label for="calisma">Mevcut çalışma durumu (çalışıyor / çalışmıyor, nerede?)</label>
        <input id="calisma" name="calisma_durumu" value="<?= e($og['calisma_durumu']) ?>" required maxlength="255">
        <label for="eposta">Güncel kişisel e-posta</label>
        <input id="eposta" type="email" name="eposta" value="<?= e($og['eposta']) ?>" required>
        <label for="telefon">Cep telefonu</label>
        <input id="telefon" name="telefon" value="<?= e($og['telefon']) ?>" placeholder="0555 123 45 67" required>
        <div class="satir"><button class="dugme" type="submit">Bilgileri kaydet</button></div>
      </form>
    </section>
  </div>
</div>

<?php if (count($basvurular) > 0): ?>
<section class="kart" id="gecmis">
  <div class="kart-baslik"><h2><?= ikon('gecmis') ?> Başvuru geçmişim</h2></div>
  <?php foreach ($basvurular as $x):
      $gc = $pdo->prepare('SELECT g.*, k.ad_soyad FROM basvuru_gecmisi g LEFT JOIN kullanicilar k ON k.id = g.kullanici_id WHERE g.basvuru_id = ? ORDER BY g.id'); $gc->execute([$x['id']]); $gc = $gc->fetchAll();
      $eks = $pdo->prepare("SELECT mesaj FROM basvuru_uygunluk WHERE basvuru_id = ? AND durum <> 'tamam' ORDER BY id"); $eks->execute([$x['id']]); $eks = $eks->fetchAll(PDO::FETCH_COLUMN); ?>
    <details class="det"<?= $x['id'] === $guncel['id'] ? ' open' : '' ?>><summary><?= e(tarih_tr($x['basvuru_tarihi'], true)) ?> · <?= $x['donem_id'] ? e(donem_etiketi($x)) : '-' ?> · <?= e(DURUM_KISA[$x['durum']] ?? $x['durum']) ?></summary>
      <div class="det-ic">
        <?php if ($eks): ?><p><b>Başvuru anındaki eksikler:</b></p><ul><?php foreach ($eks as $m) echo '<li>' . e($m) . '</li>'; ?></ul><?php endif; ?>
        <div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Tarih</th><th>Durum</th><th>Açıklama</th></tr></thead><tbody>
          <?php foreach ($gc as $g) echo '<tr><td>' . e(tarih_tr($g['tarih'], true)) . '</td><td>' . durum_rozeti($g['yeni_durum']) . '</td><td>' . e($g['aciklama']) . '</td></tr>'; ?>
        </tbody></table></div>
      </div></details>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php layout_footer();
