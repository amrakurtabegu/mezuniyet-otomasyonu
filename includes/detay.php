<?php
/**
 * ORTAK AYRINTI PANELİ: danışman, komisyon, sekreter ve bölüm başkanı ekranlarında aynı bileşen kullanılır.
 * Yalnızca okuma/görüntüleme yapar; kararlar ilgili sayfanın formlarıyla (is_akisi.php üzerinden) kaydedilir.
 */
const STAJ_TON = ['onaylandi'=>['Onaylandı', 'yesil'], 'tamamlandi_onay_bekliyor'=>['Tamamlandı, onay bekliyor', 'turuncu'],
                  'devam_ediyor'=>['Devam ediyor', 'mavi'], 'tamamlanmadi'=>['Tamamlanmadı', 'kirmizi']];

function komisyon_uyeleri(PDO $pdo): array {
    return $pdo->query("SELECT id, kullanici_no, ad_soyad FROM kullanicilar WHERE rol = 'komisyon' AND aktif = 1 ORDER BY kullanici_no")->fetchAll();
}

/** Dönüş: [basvuru_id => [uye_id => ['karar', 'aciklama', 'tarih']]] */
function komisyon_oylari(PDO $pdo, array $bidler): array {
    $bidler = array_values(array_map('intval', $bidler));
    if (!$bidler) return [];
    $s = $pdo->prepare('SELECT basvuru_id, uye_id, karar, aciklama, karar_tarihi FROM komisyon_kararlari WHERE basvuru_id IN (' . implode(',', array_fill(0, count($bidler), '?')) . ')');
    $s->execute($bidler);
    $c = [];
    foreach ($s->fetchAll() as $r) $c[(int)$r['basvuru_id']][(int)$r['uye_id']] = ['karar'=>$r['karar'], 'aciklama'=>$r['aciklama'], 'tarih'=>$r['karar_tarihi']];
    return $c;
}

/** Tabloda beş üyenin oy durumunu küçük noktalarla gösterir (yeşil onay, kırmızı ret, gri bekliyor). */
function oy_noktalari_html(array $uyeler, array $oylar): string {
    $h = '<span class="oy-noktalar">';
    foreach ($uyeler as $m) {
        $o = $oylar[(int)$m['id']]['karar'] ?? null;
        $h .= '<i class="nokta ' . ($o === 'onay' ? 'onay' : ($o === 'red' ? 'red' : 'bos')) . '" title="' . e($m['kullanici_no'] . ' – ' . $m['ad_soyad'] . ': ' . ($o === 'onay' ? 'Onay' : ($o === 'red' ? 'Ret' : 'Bekliyor'))) . '"></i>';
    }
    return $h . '</span>';
}

function oy_sayilari(array $oylar): array {
    $o = 0; $r = 0;
    foreach ($oylar as $x) { if ($x['karar'] === 'onay') $o++; else $r++; }
    return [$o, $r];
}

function akademik_ozet(PDO $pdo, int $ogrenciId): array {
    $a = $pdo->prepare('SELECT * FROM akademik_kayitlari WHERE ogrenci_id = ?'); $a->execute([$ogrenciId]);
    $st = $pdo->prepare('SELECT tur, durum FROM staj_kayitlari WHERE ogrenci_id = ?'); $st->execute([$ogrenciId]);
    $de = $pdo->prepare('SELECT * FROM ders_kayitlari WHERE ogrenci_id = ? ORDER BY zorunlu DESC, ders_kodu'); $de->execute([$ogrenciId]);
    $ek = $pdo->prepare('SELECT * FROM ek_kosullar WHERE ogrenci_id = ? ORDER BY ad'); $ek->execute([$ogrenciId]);
    return ['ak'=>$a->fetch() ?: null, 'staj'=>array_column($st->fetchAll(), 'durum', 'tur'), 'dersler'=>$de->fetchAll(), 'ek'=>$ek->fetchAll()];
}

/** GANO + AKTS ilerleme çubuğu (öğrenci panelindeki ve ayrıntı panelindeki ortak özet). */
function akts_cubugu_html(?array $ak): string {
    if (!$ak || $ak['tamamlanan_akts'] === null || $ak['gereken_akts'] === null) return '<span class="muted">AKTS bilgisi yok</span>';
    $y = (int)min(100, round(100 * (int)$ak['tamamlanan_akts'] / max(1, (int)$ak['gereken_akts'])));
    return '<div class="ilerleme" role="progressbar" aria-valuenow="' . $y . '" aria-valuemin="0" aria-valuemax="100"><i style="width:' . $y . '%"></i></div><small>' . (int)$ak['tamamlanan_akts'] . ' / ' . (int)$ak['gereken_akts'] . ' AKTS</small>';
}

function akademik_tablolari_html(array $oz): string {
    $h = '<div class="uc-kolon"><div><h4>Staj durumları</h4><div class="tablo-kap"><table class="tablo kucuk"><tbody>';
    foreach (STAJ_ETIKETLERI as $tur => $ad) {
        $d = $oz['staj'][$tur] ?? null;
        [$t, $ton] = $d !== null ? (STAJ_TON[$d] ?? [$d, 'gri']) : ['Kayıt yok', 'gri'];
        $h .= '<tr><td>' . e($ad) . '</td><td>' . rozet($t, $ton) . '</td></tr>';
    }
    $h .= '</tbody></table></div></div><div><h4>Ek mezuniyet koşulları</h4><div class="tablo-kap"><table class="tablo kucuk"><tbody>';
    $et = ['tamam'=>['Sağlandı', 'yesil'], 'eksik'=>['Eksik', 'kirmizi'], 'bekliyor'=>['İnceleme bekliyor', 'turuncu']];
    foreach ($oz['ek'] as $x) { [$t, $ton] = $et[$x['durum']] ?? [$x['durum'], 'gri']; $h .= '<tr><td>' . e($x['ad']) . '</td><td>' . rozet($t, $ton) . '</td></tr>'; }
    if (!$oz['ek']) $h .= '<tr><td colspan="2" class="muted">Tanımlı ek koşul yok (bölümden teyit edilmeli).</td></tr>';
    $h .= '</tbody></table></div></div></div><h4>Zorunlu ve diğer dersler</h4><div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Kod</th><th>Ders</th><th>AKTS</th><th>Tür</th><th>Durum</th></tr></thead><tbody>';
    $dt = ['gecti'=>['Geçti', 'yesil'], 'kaldi'=>['Kaldı', 'kirmizi'], 'alinmadi'=>['Alınmadı', 'turuncu']];
    foreach ($oz['dersler'] as $x) { [$t, $ton] = $dt[$x['durum']] ?? [$x['durum'], 'gri']; $h .= '<tr><td>' . e($x['ders_kodu']) . '</td><td>' . e($x['ders_adi']) . '</td><td>' . e($x['akts'] ?? '-') . '</td><td>' . ($x['zorunlu'] ? 'Zorunlu' : 'Seçmeli') . '</td><td>' . rozet($t, $ton) . '</td></tr>'; }
    if (!$oz['dersler']) $h .= '<tr><td colspan="5" class="muted">Ders kaydı sistemde yok; doğrulanamadı.</td></tr>';
    return $h . '</tbody></table></div>';
}

/**
 * Bir başvurunun ayrıntı paneli. $mod: 'tam' (danışman, komisyon, başkan) veya 'sekreter' (asgari bilgi: akademik rapor ve iletişim gösterilmez).
 * Başvuru bulunamazsa boş dize döner; yetki denetimini çağıran sayfa yapar.
 */
function ogrenci_detay_html(PDO $pdo, int $bid, string $mod = 'tam'): string {
    $s = $pdo->prepare('SELECT b.*, o.ogrenci_no, o.ad_soyad, o.bolum, o.program, o.calisma_durumu, o.eposta, o.telefon, k.ad_soyad AS danisman_ad,
        d.yil, d.ay, d.donem, d.baslangic, d.bitis FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id JOIN kullanicilar k ON k.id = o.danisman_id
        LEFT JOIN basvuru_donemleri d ON d.id = b.donem_id WHERE b.id = ?');
    $s->execute([$bid]);
    $x = $s->fetch();
    if (!$x) return '';
    $ogrId = (int)$x["ogrenci_id"];
    $oz = akademik_ozet($pdo, $ogrId);
    $rapor = uygunluk_raporu($pdo, $ogrId);
    $gano = $oz['ak'] && $oz['ak']['gano'] !== null ? number_format((float)$oz['ak']['gano'], 2) : '-';
    $bolum = fn(string $baslik, string $icerik, bool $acik = true) => '<details class="det"' . ($acik ? ' open' : '') . '><summary>' . e($baslik) . '</summary><div class="det-ic">' . $icerik . '</div></details>';

    $h = '<div class="detay-baslik"><span class="avatar buyuk">' . e(bas_harfler($x['ad_soyad'])) . '</span><div><h2>' . e($x['ad_soyad']) . '</h2><p class="muted">' . e($x['ogrenci_no']) . ' · ' . e($x['bolum']) . ($x['program'] ? ' / ' . e($x['program']) : '') . '</p></div><div class="detay-durum">' . durum_rozeti($x['durum']) . '</div></div>';
    $h .= '<dl class="ozet-izgara"><div><dt>Akademik danışman</dt><dd>' . e($x['danisman_ad']) . '</dd></div><div><dt>Başvuru tarihi</dt><dd>' . e(tarih_tr($x['basvuru_tarihi'], true)) . '</dd></div>'
        . '<div><dt>Başvuru dönemi</dt><dd>' . ($x['donem_id'] ? e(donem_etiketi($x)) : '-') . '</dd></div><div><dt>GANO</dt><dd>' . e($gano) . '</dd></div>'
        . '<div><dt>AKTS</dt><dd>' . akts_cubugu_html($oz['ak']) . '</dd></div><div><dt>Akademik uygunluk</dt><dd>' . rozet($rapor['uygun'] ? 'Uygun' : 'Eksik / doğrulanamadı', $rapor['uygun'] ? 'yesil' : 'kirmizi') . '</dd></div></dl>';

    if (!$rapor['uygun']) {
        $h .= '<div class="uyari-kutu"><b>Eksik veya doğrulanamayan mezuniyet koşulları var:</b><ul style="margin:6px 0 0 18px">';
        foreach ($rapor['eksikler'] as $m) $h .= '<li>' . e($m) . '</li>';
        $h .= '</ul></div>';
    }
    $h .= $bolum('Akademik uygunluk raporu', uygunluk_html($rapor));
    $h .= $bolum('Staj, ders ve ek koşullar', akademik_tablolari_html($oz), false);
    if ($mod === 'tam') {
        $h .= $bolum('İletişim ve çalışma bilgileri', '<dl class="ozet-izgara"><div><dt>Çalışma durumu</dt><dd>' . e($x['calisma_durumu'] ?: '-') . '</dd></div><div><dt>E-posta</dt><dd>' . e($x['eposta'] ?: '-') . '</dd></div><div><dt>Telefon</dt><dd>' . e($x['telefon'] ?: '-') . '</dd></div></dl>', false);
    }

    $dk = $pdo->prepare('SELECT d.*, k.ad_soyad FROM danisman_kararlari d JOIN kullanicilar k ON k.id = d.danisman_id WHERE d.basvuru_id = ? ORDER BY d.id');
    $dk->execute([$bid]);
    $kl = '';
    $kt = ['onay'=>['Onayladı', 'yesil'], 'iade'=>['İade etti', 'turuncu'], 'red'=>['Reddetti', 'kirmizi']];
    foreach ($dk->fetchAll() as $d) { [$t, $ton] = $kt[$d['karar']] ?? [$d['karar'], 'gri']; $kl .= '<li>' . rozet($t, $ton) . ' <b>' . e($d['ad_soyad']) . '</b> · ' . e(tarih_tr($d['karar_tarihi'], true)) . ($d['gerekce'] ? '<br><span class="muted">Gerekçe: ' . e($d['gerekce']) . '</span>' : '') . '</li>'; }
    $h .= $bolum('Akademik danışman kararı', $kl !== '' ? '<ul class="karar-liste">' . $kl . '</ul>' : '<p class="muted">Danışman henüz karar vermedi.</p>');

    if (!in_array($x['durum'], ['danisman_bekliyor', 'iade', 'danisman_red'], true)) {
        $uyeler = komisyon_uyeleri($pdo);
        $oy = komisyon_oylari($pdo, [$bid])[$bid] ?? [];
        [$on, $re] = oy_sayilari($oy);
        $t = '<p>' . rozet($on . ' onay', 'yesil') . ' ' . rozet($re . ' ret', $re ? 'kirmizi' : 'gri') . ' <span class="muted">Karar için ' . KOMISYON_UYE_SAYISI . ' üyeden en az ' . ONAY_ESIGI . ' onay gerekir.</span></p>';
        $t .= '<div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Üye</th><th>Oy</th><th>Tarih</th><th>Açıklama</th></tr></thead><tbody>';
        foreach ($uyeler as $m) {
            $o = $oy[(int)$m['id']] ?? null;
            $t .= '<tr><td>' . e($m['kullanici_no']) . ' – ' . e($m['ad_soyad']) . '</td><td>' . ($o ? rozet($o['karar'] === 'onay' ? 'Onay' : 'Ret', $o['karar'] === 'onay' ? 'yesil' : 'kirmizi') : rozet('Bekliyor', 'gri')) . '</td><td>' . ($o ? e(tarih_tr($o['tarih'], true)) : '-') . '</td><td>' . e($o['aciklama'] ?? '') . '</td></tr>';
        }
        $h .= $bolum('Komisyon oylaması', $t . '</tbody></table></div>');
    }

    $ik = $pdo->prepare('SELECT * FROM iliski_kesme_kontrolleri WHERE basvuru_id = ? ORDER BY birim');
    $ik->execute([$bid]);
    $ik = $ik->fetchAll();
    if ($ik) {
        $it = ['bekliyor'=>['Bekliyor', 'turuncu'], 'tamam'=>['Tamamlandı', 'yesil'], 'engel'=>['Engel var', 'kirmizi']];
        $t = '<div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Birim</th><th>Durum</th><th>Açıklama</th><th>Son kontrol</th></tr></thead><tbody>';
        foreach ($ik as $r) { [$tt, $ton] = $it[$r['durum']]; $t .= '<tr><td>' . e(ILISKI_BIRIMLERI[$r['birim']] ?? $r['birim']) . '</td><td>' . rozet($tt, $ton) . '</td><td>' . e($r['aciklama'] ?? '') . '</td><td>' . e(tarih_tr($r['kontrol_tarihi'], true)) . '</td></tr>'; }
        $h .= $bolum('İlişik kesme kontrolü', $t . '</tbody></table></div>');
    }

    $b = $pdo->prepare('SELECT * FROM mezuniyet_belgeleri WHERE basvuru_id = ?');
    $b->execute([$bid]);
    if ($belge = $b->fetch()) {
        [$bt, $bton] = belge_durum_etiketi($x['durum']);
        $rol = current_user()['rol'] ?? '';
        $h .= $bolum('Mezuniyet belgesi', '<p>' . rozet($bt, $bton) . ' Belge no: <b>' . e($belge['belge_no']) . '</b></p>'
            . (in_array($rol, ['bolum_baskani', 'sekreter'], true) ? '<p><a class="dugme hayalet kucuk" target="_blank" href="' . e(BASE_URL) . '/pdf/mezuniyet_belgesi.php?basvuru_id=' . (int)$bid . '">Belgeyi görüntüle</a></p>' : '')
            . '<p class="muted">E-imza adımı yalnızca simülasyondur; hukuken geçerli değildir.' . ($belge['eimza_tarihi'] ? ' E-imza tarihi: ' . e(tarih_tr($belge['eimza_tarihi'], true)) . '.' : '') . '</p>', true);
    }

    $g = $pdo->prepare('SELECT g.*, k.ad_soyad FROM basvuru_gecmisi g LEFT JOIN kullanicilar k ON k.id = g.kullanici_id WHERE g.basvuru_id = ? ORDER BY g.id');
    $g->execute([$bid]);
    $t = '<div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Tarih</th><th>Durum</th><th>Kim</th><th>Açıklama</th></tr></thead><tbody>';
    foreach ($g->fetchAll() as $r) $t .= '<tr><td>' . e(tarih_tr($r['tarih'], true)) . '</td><td>' . durum_rozeti($r['yeni_durum']) . '</td><td>' . e($r['ad_soyad'] ?? 'Öğrenci / Sistem') . '</td><td>' . e($r['aciklama']) . '</td></tr>';
    return $h . $bolum('Başvuru geçmişi', $t . '</tbody></table></div>', false);
}

/** Ayrıntı paneline gelen istekte (?parca=detay) JSON olmayan, yalnızca HTML parçası döndürülür. */
function parca_cikti(string $html): never {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $html !== '' ? $html : '<p class="muted">Başvuru bulunamadı veya bu kaydı görüntüleme yetkiniz yok.</p>';
    exit;
}

/** Sekreter / Bilgi İşlem kuyruklarının başvuru numaraları (öğrenci numarasına göre) - önceki/sonraki gezinti için. */
function kuyruk_idleri(PDO $pdo, string $kuyruk): array {
    $kosul = match ($kuyruk) {
        'iliski' => "b.durum = 'komisyon_onay' AND NOT EXISTS (SELECT 1 FROM iliski_kesme_kontrolleri i WHERE i.basvuru_id = b.id AND i.durum = 'engel')",
        'engel' => "b.durum = 'komisyon_onay' AND EXISTS (SELECT 1 FROM iliski_kesme_kontrolleri i WHERE i.basvuru_id = b.id AND i.durum = 'engel')",
        'belge' => "b.durum = 'iliski_kesme_tamam'",
        'belge_onay' => "b.durum = 'belge_hazirlaniyor'",
        'eimza' => "b.durum = 'eimza_surecinde'",
        default => '0',
    };
    return array_map('intval', $pdo->query("SELECT b.id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE $kosul ORDER BY o.ogrenci_no")->fetchAll(PDO::FETCH_COLUMN));
}

/** İlişik kesme birimlerinin kısa özeti: her birim için renkli rozet. $durumlar: [birim => bekliyor|tamam|engel] */
function iliski_ozet_html(array $durumlar): string {
    $ton = ['bekliyor' => 'turuncu', 'tamam' => 'yesil', 'engel' => 'kirmizi'];
    $et = ['bekliyor' => 'bekliyor', 'tamam' => 'tamam', 'engel' => 'engel var'];
    $h = '';
    foreach (ILISKI_BIRIMLERI as $k => $ad) { $d = $durumlar[$k] ?? 'bekliyor'; $h .= rozet($ad . ': ' . $et[$d], $ton[$d]) . ' '; }
    return $h;
}
function iliski_durumlari(PDO $pdo, array $bidler): array {
    $bidler = array_values(array_map('intval', $bidler));
    if (!$bidler) return [];
    $s = $pdo->prepare('SELECT basvuru_id, birim, durum FROM iliski_kesme_kontrolleri WHERE basvuru_id IN (' . implode(',', array_fill(0, count($bidler), '?')) . ')');
    $s->execute($bidler);
    $c = [];
    foreach ($s->fetchAll() as $r) $c[(int)$r['basvuru_id']][$r['birim']] = $r['durum'];
    return $c;
}
