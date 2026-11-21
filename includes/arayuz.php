<?php
/**
 * ORTAK ARAYÜZ BİLEŞENLERİ (iş mantığı içermez)
 * Kenar çubuğu, üst çubuk, rozetler, KPI kartları ve süreç zaman çizelgesi burada tanımlıdır;
 * böylece beş rolün tüm ekranları aynı tasarım dilini kullanır.
 * Renk anlamları: yeşil = onaylandı/tamamlandı · turuncu = bekliyor/işlemde · kırmızı = reddedildi/engel
 *                 mavi = inceleniyor · gri = henüz başlamadı
 */
const DURUM_KISA = [
    'danisman_bekliyor' => 'Danışman Onayı Bekliyor', 'iade' => 'İade Edildi', 'danisman_red' => 'Danışman Reddetti',
    'komisyon_bekliyor' => 'Komisyon İncelemesinde', 'komisyon_onay' => 'Komisyon Onayladı', 'komisyon_red' => 'Komisyon Reddetti',
    'iliski_kesme_tamam' => 'Belge Hazırlama Bekliyor', 'son_onayli' => 'Son Onaylandı (eski akış)', 'belge_hazirlaniyor' => 'Bilgi İşlem Onayı Bekliyor',
    'belge_onayli' => 'Belge Onaylandı', 'listede' => 'Mezuniyet Listesinde', 'eimza_surecinde' => 'E-İmza Bekliyor', 'tamamlandi' => 'Tamamlandı',
];
const ZC_ADIMLARI = ['Başvuru Oluşturuldu', 'Danışman Onayı', 'Komisyon İncelemesi', 'İlişik Kesme', 'Belge Hazırlama',
                     'Bilgi İşlem Belge Onayı', 'Mezuniyet Listesi', 'E-İmza Onayı', 'Belge Teslimi'];

function ikon(string $ad, int $boyut = 18): string {
    static $y = [
        'pano' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'dosya' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'kisiler' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.4 3.3-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .7 3.2 2.4 3.5 5.2"/>',
        'surec' => '<circle cx="5" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="18" r="2"/><path d="M6.6 7.4l3.8 3.2M13.6 13.4l3.8 3.2"/>',
        'liste' => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
        'belge' => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h5"/>',
        'rapor' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'gecmis' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'ayar' => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'zil' => '<path d="M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 21h4"/>',
        'kullanici' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 4-6 8-6s7.2 2 8 6"/>',
        'cikis' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 8l-4 4 4 4M6 12h10"/>',
        'ara' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'kapat' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'tik' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'takvim' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'oy' => '<path d="M5 13l4 4L19 7"/><rect x="3" y="3" width="18" height="18" rx="3"/>',
        'imza' => '<path d="M4 20c3-1 4-6 6-6s1 4 3 4 2-3 4-3M14 4l6 6-8 8H6v-6z"/>',
        'yazdir' => '<path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7"/>',
    ];
    return '<svg class="ik" width="' . $boyut . '" height="' . $boyut . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($y[$ad] ?? $y['dosya']) . '</svg>';
}

/** Ton: yesil | turuncu | kirmizi | mavi | gri */
function rozet(string $metin, string $ton = 'gri', string $ipucu = ''): string {
    return '<span class="rozet ' . e($ton) . '"' . ($ipucu !== '' ? ' title="' . e($ipucu) . '"' : '') . '>' . e($metin) . '</span>';
}
function durum_tonu(string $d): string {
    return match ($d) {
        'danisman_bekliyor', 'komisyon_onay', 'iliski_kesme_tamam', 'belge_hazirlaniyor', 'eimza_surecinde' => 'turuncu',
        'iade', 'danisman_red', 'komisyon_red' => 'kirmizi',
        'komisyon_bekliyor', 'belge_onayli', 'listede', 'son_onayli' => 'mavi',
        'tamamlandi' => 'yesil',
        default => 'gri',
    };
}
function durum_rozeti(string $d): string { return rozet(DURUM_KISA[$d] ?? $d, durum_tonu($d), durum_etiketi($d)); }

/** Belgenin süreçteki yeri (e-imza adımı SİMÜLASYONDUR). Dönüş: [etiket, ton] */
function belge_durum_etiketi(string $durum, ?array $belge = null): array {
    return match ($durum) {
        'iliski_kesme_tamam' => ['Belge Hazırlama Bekliyor', 'turuncu'],
        'belge_hazirlaniyor' => ['Bilgi İşlem Onayı Bekliyor', 'turuncu'],
        'belge_onayli' => ['Belge Onaylandı', 'mavi'],
        'listede' => ['Listede · Liste Kapatılacak', 'mavi'],
        'eimza_surecinde' => ['E-İmza Onayı Bekliyor', 'turuncu'],
        'tamamlandi' => ['E-İmzalı Belge Hazır', 'yesil'],
        default => ['Henüz Başlamadı', 'gri'],
    };
}

function kpi_karti(string $baslik, $deger, string $not = '', string $ton = 'mavi', string $ikonAdi = 'dosya'): string {
    return '<div class="kpi ' . e($ton) . '"><span class="kpi-ik">' . ikon($ikonAdi, 20) . '</span><div><div class="kpi-sayi">' . e((string)$deger)
         . '</div><div class="kpi-ad">' . e($baslik) . '</div>' . ($not !== '' ? '<div class="kpi-not">' . e($not) . '</div>' : '') . '</div></div>';
}

function bas_harfler(string $ad): string {
    $p = preg_split('/\s+/u', trim($ad)) ?: [];
    $h = mb_substr((string)($p[0] ?? ''), 0, 1);
    if (count($p) > 1) $h .= mb_substr((string)end($p), 0, 1);
    return mb_strtoupper($h);
}

/** 9 adımlı süreç zaman çizelgesi durumları: tamam | devam | bekliyor | red | iade */
function zaman_cizelgesi_hesapla(string $durum, ?array $belge = null): array {
    $n = count(ZC_ADIMLARI);
    $s = array_fill(0, $n, 'bekliyor');
    $s[0] = 'tamam';
    $akis = ['danisman_bekliyor' => 1, 'iade' => 1, 'danisman_red' => 1, 'komisyon_bekliyor' => 2, 'komisyon_red' => 2, 'komisyon_onay' => 3,
             'iliski_kesme_tamam' => 4, 'belge_hazirlaniyor' => 5, 'belge_onayli' => 6, 'listede' => 6, 'son_onayli' => 6, 'eimza_surecinde' => 7, 'tamamlandi' => 9];
    $i = $akis[$durum] ?? 1;
    for ($k = 1; $k < $i && $k < $n; $k++) $s[$k] = 'tamam';
    if ($i < $n) $s[$i] = match ($durum) { 'iade' => 'iade', 'danisman_red', 'komisyon_red' => 'red', default => 'devam' };
    return $s;
}
function zaman_cizelgesi_html(string $durum, ?array $belge = null): string {
    $etiket = ['tamam'=>'Tamamlandı', 'devam'=>'Devam Ediyor', 'bekliyor'=>'Bekliyor', 'red'=>'Reddedildi', 'iade'=>'İade Edildi'];
    $h = '<ol class="zc">';
    foreach (zaman_cizelgesi_hesapla($durum, $belge) as $i => $d) {
        $sinif = $d === 'iade' ? 'red' : $d;
        $isaret = $d === 'tamam' ? ikon('tik', 14) : ($d === 'red' || $d === 'iade' ? ikon('kapat', 14) : (string)($i + 1));
        $h .= '<li class="' . e($sinif) . '"><span class="zc-nokta">' . $isaret . '</span><span class="zc-ad">' . e(ZC_ADIMLARI[$i]) . '</span><span class="zc-durum">' . e($etiket[$d]) . '</span></li>';
    }
    return $h . '</ol>';
}

/** Toplu işlem sonucu: başarılı / başarısız / işlem yapılmayan kayıtlar ayrı ayrı. Oturumda saklanır, bir kez gösterilir. */
function toplu_sonuc_html(array $s): string {
    $ad = ['komisyon_onay' => 'Komisyon toplu onayı', 'iliski_kesme' => 'Toplu ilişik kesme tamamlama', 'belge_hazirla' => 'Toplu belge hazırlama',
           'belge_onay' => 'Toplu belge onayı', 'eimza_onay' => 'Toplu e-imza onayı'];
    $h = '<section class="kart sonuc-kart"><div class="kart-baslik"><h2>' . e($ad[$s['tur']] ?? 'Toplu işlem') . ' · sonuç <small>(işlem #' . (int)$s['id'] . ')</small></h2>'
       . '<span>' . rozet($s['basarili'] . ' başarılı', 'yesil') . ' ' . rozet($s['basarisiz'] . ' başarısız', $s['basarisiz'] ? 'kirmizi' : 'gri') . ' ' . rozet($s['atlandi'] . ' işlem yapılmadı', $s['atlandi'] ? 'turuncu' : 'gri') . '</span></div>';
    $tonlar = ['basarili' => ['Başarılı', 'yesil'], 'basarisiz' => ['Başarısız', 'kirmizi'], 'atlandi' => ['İşlem yapılmadı', 'turuncu']];
    $h .= '<div class="tablo-kap"><table class="tablo kucuk"><thead><tr><th>Öğrenci</th><th>Sonuç</th><th>Açıklama</th></tr></thead><tbody>';
    foreach ($s['sonuclar'] as $r) $h .= '<tr><td>' . e($r['ogrenci_no']) . ' - ' . e($r['ad_soyad']) . '</td><td>' . rozet($tonlar[$r['durum']][0], $tonlar[$r['durum']][1]) . '</td><td>' . e($r['mesaj']) . '</td></tr>';
    return $h . '</tbody></table></div></section>';
}
function toplu_sonuc_kaydet(array $s): void {
    $_SESSION['toplu_sonuc'] = $s;
    $_SESSION['flash'][] = [$s['basarisiz'] ? 'err' : 'ok', "Toplu işlem tamamlandı: {$s['basarili']} başarılı, {$s['basarisiz']} başarısız, {$s['atlandi']} işlem yapılmadı."];
}

/** İnceleme sayfalarında sıradaki öğrenciye geçiş: önceki / sonraki / listeye dön. $ek: sayfa adresine eklenecek sorgu (ör. "&kuyruk=belge"). */
function kuyruk_sonraki(array $kuyruk, int $id): ?int {
    $i = array_search($id, $kuyruk, true);
    return ($i !== false && $i < count($kuyruk) - 1) ? $kuyruk[$i + 1] : null;
}
function gezinti_html(array $kuyruk, int $id, string $sayfa, string $liste, string $ek = ''): string {
    $i = array_search($id, $kuyruk, true);
    $onceki = ($i !== false && $i > 0) ? $kuyruk[$i - 1] : null;
    $sonraki = kuyruk_sonraki($kuyruk, $id);
    $d = fn(?int $x, string $m) => $x ? '<a class="dugme hayalet" href="' . e($sayfa . '?id=' . $x . $ek) . '">' . $m . '</a>' : '<span class="dugme hayalet pasif">' . $m . '</span>';
    return '<div class="gezinti no-print">' . $d($onceki, '← Önceki öğrenci') . '<span class="gezinti-sira">' . ($i === false ? '-' : $i + 1) . ' / ' . count($kuyruk) . '</span>'
         . $d($sonraki, 'Sonraki öğrenci →') . '<a class="dugme hayalet" href="' . e($liste) . '">Listeye dön</a></div>';
}

function menu_ogeleri(string $rol): array {
    $b = BASE_URL;
    return match ($rol) {
        'ogrenci' => [['Dashboard', "$b/ogrenci/index.php", 'pano'], ['Başvurum', "$b/ogrenci/index.php#basvuru", 'dosya'], ['Süreç Takibi', "$b/ogrenci/index.php#surec", 'surec'],
                      ['Akademik Durum', "$b/ogrenci/index.php#akademik", 'rapor'], ['Mezuniyet Belgem', "$b/ogrenci/index.php#belge", 'belge'], ['İletişim Bilgileri', "$b/ogrenci/index.php#iletisim", 'kullanici']],
        'danisman' => [['Dashboard', "$b/danisman/index.php", 'pano'], ['Başvurular', "$b/danisman/index.php#liste", 'dosya'],
                       ['Onay Bekleyenler', "$b/danisman/index.php?durum=danisman_bekliyor", 'oy'], ['İade Edilenler', "$b/danisman/index.php?durum=iade", 'gecmis']],
        'komisyon' => [['Dashboard', "$b/komisyon/index.php", 'pano'], ['Başvuru Listesi', "$b/komisyon/index.php#liste", 'dosya'],
                       ['Oylama Bekleyenler', "$b/komisyon/index.php?f=bekleyen", 'oy'], ['Karara Bağlananlar', "$b/komisyon/index.php?f=onay", 'tik']],
        'sekreter' => [['Dashboard', "$b/sekreter/index.php", 'pano'], ['Komisyon Onayı Tamamlananlar', "$b/sekreter/komisyon.php", 'oy'],
                       ['İlişik Kesme İşlemleri', "$b/sekreter/iliski.php", 'surec'], ['Belge Hazırlama', "$b/sekreter/belge.php", 'belge'],
                       ['Mezuniyet Listeleri', "$b/yonetim/listeler.php", 'liste'], ['Raporlar', "$b/yonetim/raporlar.php", 'rapor']],
        default => [['Dashboard', "$b/yonetim/index.php", 'pano'], ['Belge Onayları', "$b/yonetim/belge_onaylari.php", 'oy'], ['Başvuru Dönemleri', "$b/yonetim/donemler.php", 'takvim'],
                    ['Mezuniyet Listeleri', "$b/yonetim/listeler.php", 'liste'], ['E-İmza Süreci', "$b/yonetim/eimza.php", 'imza'], ['Mezun Öğrenciler', "$b/yonetim/mezunlar.php", 'kisiler'],
                    ['Belgeler', "$b/yonetim/belgeler.php", 'belge'], ['Raporlar', "$b/yonetim/raporlar.php", 'rapor'], ['İşlem Geçmişi', "$b/yonetim/loglar.php", 'gecmis'], ['Ayarlar', "$b/yonetim/ayarlar.php", 'ayar']],
    };
}
function menu_aktif(string $url): bool {
    if (str_contains($url, '#')) return false;
    $yol = parse_url($url, PHP_URL_PATH);
    if ($yol !== parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)) return false;
    parse_str((string)parse_url($url, PHP_URL_QUERY), $beklenen);
    foreach ($beklenen as $k => $v) if (($_GET[$k] ?? null) !== $v) return false;
    if (!$beklenen) foreach (['f', 'durum'] as $k) if (!empty($_GET[$k])) return false;
    return true;
}

/** Üst çubuktaki bildirim listesi; her rol için kendi bekleyen işlerini canlı hesaplar. Dönüş: [['mesaj','url','sayi'], ...] */
function bildirimler(PDO $pdo, array $u): array {
    $say = function (string $sql, array $p = []) use ($pdo): int { $s = $pdo->prepare($sql); $s->execute($p); return (int)$s->fetchColumn(); };
    $b = []; $k = BASE_URL;
    if ($u['rol'] === 'ogrenci') {
        $s = $pdo->prepare('SELECT b.durum FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE o.kullanici_id = ? ORDER BY b.id DESC LIMIT 1');
        $s->execute([$u['id']]);
        $d = (string)$s->fetchColumn();
        if ($d !== '') $b[] = ['mesaj' => 'Başvuru durumunuz: ' . durum_etiketi($d), 'url' => "$k/ogrenci/index.php#surec", 'sayi' => in_array($d, ['iade', 'danisman_red', 'komisyon_red', 'tamamlandi'], true) ? 1 : 0];
    } elseif ($u['rol'] === 'danisman') {
        $b[] = ['mesaj' => 'Onayınızı bekleyen başvuru', 'url' => "$k/danisman/index.php?durum=danisman_bekliyor",
                'sayi' => $say("SELECT COUNT(*) FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE o.danisman_id = ? AND b.durum = 'danisman_bekliyor'", [$u['id']])];
    } elseif ($u['rol'] === 'komisyon') {
        $b[] = ['mesaj' => 'Oyunuzu bekleyen başvuru', 'url' => "$k/komisyon/index.php?f=bekleyen",
                'sayi' => $say("SELECT COUNT(*) FROM basvurular b WHERE b.durum = 'komisyon_bekliyor' AND NOT EXISTS (SELECT 1 FROM komisyon_kararlari c WHERE c.basvuru_id = b.id AND c.uye_id = ?)", [$u['id']])];
    } elseif ($u['rol'] === 'sekreter') {
        $b[] = ['mesaj' => 'İlişik kesme bekleyen öğrenci', 'url' => "$k/sekreter/iliski.php", 'sayi' => $say("SELECT COUNT(*) FROM basvurular WHERE durum = 'komisyon_onay'")];
        $b[] = ['mesaj' => 'Belge hazırlama bekleyen öğrenci', 'url' => "$k/sekreter/belge.php", 'sayi' => $say("SELECT COUNT(*) FROM basvurular WHERE durum = 'iliski_kesme_tamam'")];
    } else {
        $b[] = ['mesaj' => 'Belge onayınızı bekleyen öğrenci', 'url' => "$k/yonetim/belge_onaylari.php", 'sayi' => $say("SELECT COUNT(*) FROM basvurular WHERE durum = 'belge_hazirlaniyor'")];
        $b[] = ['mesaj' => 'Mezuniyet listesi bekleyen öğrenci', 'url' => "$k/yonetim/listeler.php", 'sayi' => $say("SELECT COUNT(*) FROM basvurular WHERE durum = 'belge_onayli'")];
        $b[] = ['mesaj' => 'E-imza onayı bekleyen öğrenci', 'url' => "$k/yonetim/eimza.php", 'sayi' => $say("SELECT COUNT(*) FROM basvurular WHERE durum = 'eimza_surecinde'")];
    }
    return $b;
}

/**
 * Sayfa kabuğu: sabit kenar çubuğu + üst çubuk + içerik alanı.
 * Seçenekler: 'alt' (başlık altı açıklama), 'eylem' (başlık sağındaki HTML), 'kirinti' ([[etiket, url?], ...])
 */
function layout_header(string $baslik, array $o = []): void {
    $u = current_user();
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($baslik) . ' – Mezuniyet Komisyonu Otomasyonu</title>';
    echo '<link rel="stylesheet" href="' . e(BASE_URL) . '/assets/style.css"></head><body><div class="kabuk">';
    if ($u) {
        $bil = bildirimler(db(), $u);
        $okunmamis = array_sum(array_map(fn($x) => $x['sayi'] > 0 ? 1 : 0, $bil));
        echo '<aside class="kenar" id="kenar"><a class="marka" href="' . e(BASE_URL . rol_anasayfa($u['rol'])) . '"><span class="marka-isaret">M</span><span>Mezuniyet<br>Komisyonu</span></a><nav class="menu" aria-label="Ana menü">';
        foreach (menu_ogeleri($u['rol']) as [$ad, $url, $ik]) echo '<a href="' . e($url) . '"' . (menu_aktif($url) ? ' class="aktif" aria-current="page"' : '') . '>' . ikon($ik) . '<span>' . e($ad) . '</span></a>';
        echo '</nav><div class="kenar-alt"><span class="avatar">' . e(bas_harfler($u['ad'])) . '</span><div><b>' . e($u['ad']) . '</b><br><small>' . e(ROL_ETIKETLERI[$u['rol']] ?? $u['rol']) . '</small></div></div></aside>';
        echo '<div class="ana"><header class="ust"><button type="button" class="ikon-dugme menu-ac" id="menuAc" aria-label="Menüyü aç">' . ikon('menu', 20) . '</button>';
        $kirinti = $o['kirinti'] ?? [['Ana Sayfa', BASE_URL . rol_anasayfa($u['rol'])], [$baslik]];
        echo '<nav class="kirinti" aria-label="Sayfa yolu">';
        foreach ($kirinti as $i => $kr) echo ($i ? '<span class="ayirac">/</span>' : '') . (isset($kr[1]) ? '<a href="' . e($kr[1]) . '">' . e($kr[0]) . '</a>' : '<span aria-current="page">' . e($kr[0]) . '</span>');
        echo '</nav><div class="ust-sag">';
        echo '<details class="acilir"><summary class="ikon-dugme zil" aria-label="Bildirimler">' . ikon('zil', 20) . ($okunmamis ? '<i class="sayac">' . $okunmamis . '</i>' : '') . '</summary><div class="acilir-ic bildirim-liste"><b>Bildirimler</b>';
        foreach ($bil as $x) echo '<a href="' . e($x['url']) . '">' . ($x['sayi'] > 0 ? '<span class="rozet turuncu">' . (int)$x['sayi'] . '</span>' : '<span class="rozet gri">–</span>') . '<span>' . e($x['mesaj']) . '</span></a>';
        if (!$bil) echo '<p class="muted">Yeni bildirim yok.</p>';
        echo '</div></details>';
        echo '<details class="acilir"><summary class="profil-dugme"><span class="avatar">' . e(bas_harfler($u['ad'])) . '</span><span class="profil-ad"><b>' . e($u['ad']) . '</b><small>' . e(ROL_ETIKETLERI[$u['rol']] ?? $u['rol']) . '</small></span></summary>'
           . '<div class="acilir-ic profil"><p><b>' . e($u['ad']) . '</b><br><span class="muted">Kullanıcı no: ' . e($u['no']) . '<br>Rol: ' . e(ROL_ETIKETLERI[$u['rol']] ?? $u['rol']) . '</span></p>'
           . '<a class="cikis" href="' . e(BASE_URL) . '/logout.php">' . ikon('cikis') . '<span>Güvenli Çıkış</span></a></div></details>';
        echo '</div></header><main class="icerik" id="icerik">';
    } else {
        echo '<div class="ana"><main class="icerik">';
    }
    echo '<div class="sayfa-baslik"><div><h1>' . e($baslik) . '</h1>' . (!empty($o['alt']) ? '<p class="alt">' . e($o['alt']) . '</p>' : '') . '</div>' . ($o['eylem'] ?? '') . '</div>';
    foreach ($_SESSION['flash'] ?? [] as [$tur, $m]) echo '<div class="bildirim ' . e($tur) . '" role="status">' . e($m) . '</div>';
    unset($_SESSION['flash']);
    if (!empty($_SESSION['toplu_sonuc'])) { echo toplu_sonuc_html($_SESSION['toplu_sonuc']); unset($_SESSION['toplu_sonuc']); }
}
function layout_footer(): void {
    echo '</main></div></div>';
    echo '<script src="' . e(BASE_URL) . '/assets/app.js"></script></body></html>';
}
