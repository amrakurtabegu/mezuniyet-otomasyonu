<?php
/**
 * MEZUNİYET UYGUNLUK SERVİSİ
 * Her koşul ayrı değerlendirilir; sonuç yalnızca veritabanı kayıtlarından üretilir (öğrenciye özel koda yer yok).
 * Koşul durumları: 'tamam' (sağlandı), 'eksik' (sağlanmadı), 'belirsiz' (veri yok → doğrulanamadı).
 * Kural: 'belirsiz' bir koşul ASLA sağlanmış sayılmaz; öğrenci ancak tüm koşullar 'tamam' ise uygundur.
 */
const STAJ_ETIKETLERI = ['yaz1'=>'Birinci yaz stajı', 'yaz2'=>'İkinci yaz stajı', 'ime'=>'Uzun dönem staj / İME'];
const STAJ_DURUM_ETIKETLERI = ['tamamlanmadi'=>'tamamlanmadı', 'devam_ediyor'=>'devam ediyor',
                               'tamamlandi_onay_bekliyor'=>'tamamlandı, onay bekliyor', 'onaylandi'=>'onaylandı'];
const DERS_DURUM_ETIKETLERI = ['gecti'=>'geçti', 'kaldi'=>'kaldı', 'alinmadi'=>'alınmadı'];

function kural_degeri(PDO $pdo, string $kod): ?string {
    $s = $pdo->prepare('SELECT deger FROM bolum_kurallari WHERE kod = ?');
    $s->execute([$kod]);
    $v = $s->fetchColumn();
    return ($v === false || $v === null || $v === '') ? null : (string)$v;
}

/** 'engelle' (varsayılan) veya 'izin_ver' */
function uygunluk_politikasi(PDO $pdo): string {
    return kural_degeri($pdo, 'BASVURU_POLITIKASI') === 'izin_ver' ? 'izin_ver' : 'engelle';
}

function _kontrol(string $kod, string $ad, string $durum, string $mesaj): array {
    return ['kod'=>$kod, 'ad'=>$ad, 'durum'=>$durum, 'mesaj'=>$mesaj];
}

function uygunluk_raporu(PDO $pdo, int $ogrenciId): array {
    $k = [];
    $s = $pdo->prepare('SELECT * FROM akademik_kayitlari WHERE ogrenci_id = ?');
    $s->execute([$ogrenciId]);
    $ak = $s->fetch() ?: null;

    // 1) GANO
    $min = (float)(kural_degeri($pdo, 'GANO_MIN') ?? '2.50');
    $minTxt = number_format($min, 2, '.', '');
    if ($ak === null || $ak['gano'] === null) {
        $k[] = _kontrol('gano', 'GANO', 'belirsiz', 'GANO bilgisi sistemde yok; koşul doğrulanamadı.');
    } elseif ((float)$ak['gano'] < $min) {
        $k[] = _kontrol('gano', 'GANO', 'eksik', 'GANO: ' . number_format((float)$ak['gano'], 2) . ' — en az ' . $minTxt . ' gerekir (' . number_format($min - (float)$ak['gano'], 2) . ' eksik).');
    } else {
        $k[] = _kontrol('gano', 'GANO', 'tamam', 'GANO: ' . number_format((float)$ak['gano'], 2) . ' — koşul sağlandı (en az ' . $minTxt . ').');
    }

    // 2) AKTS (gereken değer öğrencinin kaydından, yoksa bölüm kuralından; ikisi de yoksa doğrulanamaz)
    $gereken = $ak['gereken_akts'] ?? null;
    if ($gereken === null) { $kv = kural_degeri($pdo, 'TOPLAM_AKTS'); $gereken = $kv !== null ? (int)$kv : null; }
    $tam = $ak['tamamlanan_akts'] ?? null;
    if ($tam === null) {
        $k[] = _kontrol('akts', 'AKTS', 'belirsiz', 'Tamamlanan AKTS bilgisi sistemde yok; koşul doğrulanamadı.');
    } elseif ($gereken === null) {
        $k[] = _kontrol('akts', 'AKTS', 'belirsiz', 'Tamamlanan AKTS: ' . (int)$tam . ' — gereken toplam AKTS tanımlı değil (bölümden teyit edilmeli).');
    } elseif ((int)$tam < (int)$gereken) {
        $k[] = _kontrol('akts', 'AKTS', 'eksik', 'Gereken AKTS: ' . (int)$gereken . '; tamamlanan: ' . (int)$tam . ' — ' . ((int)$gereken - (int)$tam) . ' AKTS eksik.');
    } else {
        $k[] = _kontrol('akts', 'AKTS', 'tamam', 'Gereken AKTS: ' . (int)$gereken . '; tamamlanan: ' . (int)$tam . ' — koşul sağlandı.');
    }

    // 3-5) Stajlar
    $st = $pdo->prepare('SELECT tur, durum FROM staj_kayitlari WHERE ogrenci_id = ?');
    $st->execute([$ogrenciId]);
    $stajlar = array_column($st->fetchAll(), 'durum', 'tur');
    foreach (STAJ_ETIKETLERI as $tur => $etiket) {
        $d = $stajlar[$tur] ?? null;
        if ($d === null) $k[] = _kontrol('staj_' . $tur, $etiket, 'belirsiz', $etiket . ': kayıt bulunamadı; doğrulanamadı.');
        elseif ($d === 'onaylandi') $k[] = _kontrol('staj_' . $tur, $etiket, 'tamam', $etiket . ': onaylandı.');
        else $k[] = _kontrol('staj_' . $tur, $etiket, 'eksik', $etiket . ': ' . (STAJ_DURUM_ETIKETLERI[$d] ?? $d) . ' — onaylanmış değil.');
    }

    // 6) Zorunlu dersler
    $d = $pdo->prepare('SELECT ders_kodu, durum FROM ders_kayitlari WHERE ogrenci_id = ? AND zorunlu = 1 ORDER BY ders_kodu');
    $d->execute([$ogrenciId]);
    $dersler = $d->fetchAll();
    if (!$dersler) {
        $k[] = _kontrol('dersler', 'Zorunlu dersler', 'belirsiz', 'Zorunlu ders kaydı sistemde yok; doğrulanamadı.');
    } else {
        $eksik = [];
        foreach ($dersler as $x) if ($x['durum'] !== 'gecti') $eksik[] = $x['ders_kodu'] . ' (' . (DERS_DURUM_ETIKETLERI[$x['durum']] ?? $x['durum']) . ')';
        $k[] = $eksik
            ? _kontrol('dersler', 'Zorunlu dersler', 'eksik', 'Tamamlanmayan zorunlu dersler: ' . implode(', ', $eksik) . '.')
            : _kontrol('dersler', 'Zorunlu dersler', 'tamam', 'Kayıtlı ' . count($dersler) . ' zorunlu dersin tümü başarıyla tamamlandı.');
    }

    // 7) Bölümün tanımladığı ek zorunlu koşullar
    $e = $pdo->prepare('SELECT ad, durum FROM ek_kosullar WHERE ogrenci_id = ?');
    $e->execute([$ogrenciId]);
    $ekler = $e->fetchAll();
    $ekEksik = []; $ekBekleyen = [];
    foreach ($ekler as $x) { if ($x['durum'] === 'eksik') $ekEksik[] = $x['ad']; elseif ($x['durum'] === 'bekliyor') $ekBekleyen[] = $x['ad']; }
    if ($ekEksik) $k[] = _kontrol('ek_kosullar', 'Ek koşullar', 'eksik', 'Eksik ek koşullar: ' . implode(', ', $ekEksik) . '.');
    elseif ($ekBekleyen) $k[] = _kontrol('ek_kosullar', 'Ek koşullar', 'belirsiz', 'İnceleme bekleyen ek koşullar: ' . implode(', ', $ekBekleyen) . '.');
    else $k[] = _kontrol('ek_kosullar', 'Ek koşullar', 'tamam', $ekler ? 'Tanımlı tüm ek koşullar sağlandı.' : 'Bölümce tanımlanmış ek zorunlu koşul yok (bölümden teyit edilmeli).');

    $eksikler = [];
    foreach ($k as $c) if ($c['durum'] !== 'tamam') $eksikler[] = $c['mesaj'];
    return ['uygun'=>!$eksikler, 'kontroller'=>$k, 'eksikler'=>$eksikler, 'akademik_var'=>$ak !== null,
            'kaynak'=>$ak['kaynak'] ?? null, 'guncelleme'=>$ak['guncelleme_tarihi'] ?? null];
}

/** Raporu tablo olarak çizer (öğrenci, danışman ve komisyon ekranlarında ortak kullanılır). */
function uygunluk_html(array $r): string {
    $etiket = ['tamam'=>'Sağlandı', 'eksik'=>'Eksik', 'belirsiz'=>'Doğrulanamadı'];
    $ton = ['tamam'=>'yesil', 'eksik'=>'kirmizi', 'belirsiz'=>'turuncu'];
    $h = '<p class="uygunluk-ozet"><b>Akademik uygunluk:</b> ' . rozet($r['uygun'] ? 'Tüm koşullar sağlanıyor' : 'Eksik veya doğrulanamayan koşul var', $r['uygun'] ? 'yesil' : 'kirmizi') . '</p>';
    $h .= '<div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Koşul</th><th>Durum</th><th>Ayrıntı</th></tr></thead><tbody>';
    foreach ($r['kontroller'] as $c) {
        $h .= '<tr><td>' . e($c['ad']) . '</td><td>' . rozet($etiket[$c['durum']] ?? $c['durum'], $ton[$c['durum']] ?? 'gri') . '</td><td>' . e($c['mesaj']) . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    if ($r['kaynak'] === 'mock') {
        $h .= '<p class="muted">Veri kaynağı: DEMO (simülasyon) — doğrulanmış üniversite verisi değildir. Son güncelleme: ' . e(tarih_tr($r['guncelleme'], true)) . '</p>';
    } elseif (!$r['akademik_var']) {
        $h .= '<p class="muted">Bu öğrenci için akademik kayıt henüz sisteme aktarılmamış.</p>';
    }
    return $h;
}
