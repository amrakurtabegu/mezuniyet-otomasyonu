<?php
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $yol): never { header('Location: ' . BASE_URL . $yol); exit; }
function flash(string $mesaj, string $tur = 'ok'): void { $_SESSION['flash'][] = [$tur, $mesaj]; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('<!DOCTYPE html><meta charset="utf-8">Oturum süresi dolmuş veya geçersiz istek (CSRF). <a href="' . e(BASE_URL) . '/login.php">Giriş sayfasına dönün</a> ve tekrar deneyin.');
    }
}

function tarih_tr(?string $t, bool $saat = false): string {
    if (!$t) return '-';
    return date($saat ? 'd.m.Y H:i' : 'd.m.Y', strtotime($t));
}

const DURUM_ETIKETLERI = [
    'danisman_bekliyor'  => 'Danışman Onayı Bekliyor',
    'iade'               => 'Danışman Tarafından İade Edildi (Düzeltme Bekleniyor)',
    'danisman_red'       => 'Danışman Tarafından Reddedildi',
    'komisyon_bekliyor'  => 'Komisyon İncelemesinde',
    'komisyon_onay'      => 'Komisyon Onayladı – İlişik Kesme Bekliyor',
    'komisyon_red'       => 'Komisyon Tarafından Reddedildi',
    'iliski_kesme_tamam' => 'İlişik Kesme Tamam – Belge Hazırlama Bekliyor',
    'son_onayli'         => '(Eski akış) Son Onaylandı',
    'belge_hazirlaniyor' => 'Belge Hazırlandı – Bilgi İşlem Belge Onayı Bekliyor',
    'belge_onayli'       => 'Belge Onaylandı – Mezuniyet Listesi Bekliyor',
    'listede'            => 'Mezuniyet Listesinde – Liste Kapatılması Bekleniyor',
    'eimza_surecinde'    => 'Liste Kapandı – E-İmza Onayı Bekliyor',
    'tamamlandi'         => 'Tamamlandı (E-İmzalı Belge Öğrenci Hesabında)',
];
function durum_etiketi(string $d): string { return DURUM_ETIKETLERI[$d] ?? $d; }
function durum_sinifi(string $d): string {
    if (in_array($d, ['danisman_red', 'komisyon_red'], true)) return 'eksik';
    if ($d === 'iade') return 'belirsiz';
    if ($d === 'tamamlandi') return 'tamam';
    return '';
}

const ROL_ETIKETLERI = ['ogrenci'=>'Öğrenci', 'danisman'=>'Danışman', 'komisyon'=>'Komisyon Üyesi', 'bolum_baskani'=>'Bölüm Başkanı', 'sekreter'=>'Sekreter'];

/** Başvurunun o an beklediği aşamanın sırası (0–7); 8 = tüm aşamalar bitti. */
const ADIM_ADLARI = ['Danışman Onayı', 'Komisyon İncelemesi', 'İlişik Kesme Kontrolü', 'Son Onay', 'Mezuniyet Listesi', 'Belge Hazırlama', 'E-İmza Süreci', 'Teslim'];
const ADIM_INDEKSI = ['danisman_bekliyor'=>0, 'iade'=>0, 'komisyon_bekliyor'=>1, 'komisyon_onay'=>2, 'iliski_kesme_tamam'=>3,
                      'son_onayli'=>4, 'listede'=>5, 'belge_hazirlaniyor'=>5, 'eimza_surecinde'=>6, 'tamamlandi'=>8];
function surec_adimlari_html(string $durum): string {
    $red = ['danisman_red' => 0, 'komisyon_red' => 1];   // reddedilen başvuru hangi adımda durdu
    $idx = ADIM_INDEKSI[$durum] ?? -1;
    $h = '<ol class="adimlar">';
    foreach (ADIM_ADLARI as $i => $ad) {
        if (isset($red[$durum])) $sinif = $i < $red[$durum] ? 'bitti' : ($i === $red[$durum] ? 'red' : '');
        else $sinif = $i < $idx ? 'bitti' : ($i === $idx ? 'simdi' : '');
        $h .= '<li class="' . $sinif . '">' . e($ad) . '</li>';
    }
    return $h . '</ol>';
}

function eposta_gecerli(string $e): bool { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
/** Türkiye cep telefonu: 5XXXXXXXXX, 05XXXXXXXXX veya +905XXXXXXXXX */
function telefon_normalize(string $t): ?string {
    $t = preg_replace('/[\s\-\(\)]/', '', $t);
    if (!preg_match('/^(\+90|0)?(5\d{9})$/', $t, $m)) return null;
    return '0' . $m[2];
}

function log_islem(?int $basvuruId, string $islem, ?int $kullaniciId = null): void {
    $u = current_user();
    db()->prepare('INSERT INTO islem_loglari (basvuru_id, kullanici_id, islem) VALUES (?,?,?)')
        ->execute([$basvuruId, $kullaniciId ?? ($u['id'] ?? null), mb_substr($islem, 0, 255)]);
}

// Ortak arayüz bileşenleri (kenar çubuğu, üst çubuk, rozetler, zaman çizelgesi) ve ortak ayrıntı paneli
require_once __DIR__ . '/arayuz.php';
require_once __DIR__ . '/detay.php';
require_once __DIR__ . '/belge.php';
