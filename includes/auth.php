<?php
// Oturum: çerez ayarları güvenli (HttpOnly, SameSite) ve sıkı mod açık. CLI (seed/test) çalıştırmalarında oturum başlatılmaz.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'httponly'=>true, 'samesite'=>'Lax',
                               'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mock_servis.php';
require_once __DIR__ . '/senkron.php';
require_once __DIR__ . '/donemler.php';
require_once __DIR__ . '/uygunluk.php';
require_once __DIR__ . '/is_akisi.php';

/**
 * Giriş dener. Başarılıysa null, değilse Türkçe hata mesajı döndürür.
 * Şifre doğrulaması her zaman password_verify ile yapılır.
 */
function giris_yap(string $no, string $sifre): ?string {
    if ($no === '') return 'Kullanıcı numarasını giriniz.';
    $s = db()->prepare('SELECT * FROM kullanicilar WHERE kullanici_no = ?');
    $s->execute([$no]);
    $k = $s->fetch();
    if (!$k) return 'Bu numaraya ait bir kullanıcı hesabı bulunamadı.';
    if (array_key_exists('aktif', $k) && !(int)$k['aktif']) return 'Bu hesap devre dışı bırakılmış.';
    if (empty($k['sifre_hash']) || password_get_info($k['sifre_hash'])['algo'] === null) {
        error_log('[mezuniyet-otomasyonu] Geçersiz şifre özeti: ' . $no);
        return 'Hesabın şifre kaydı geçersiz. Yerel kurulumda "php database/seed.php" komutunu yeniden çalıştırarak demo hesapları onarın.';
    }
    if (!password_verify($sifre, $k['sifre_hash'])) return 'Şifre hatalı.';
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['user'] = ['id'=>(int)$k['id'], 'no'=>$k['kullanici_no'], 'ad'=>$k['ad_soyad'], 'rol'=>$k['rol']];
    return null;
}

function rol_anasayfa(string $rol): string {
    return match ($rol) {
        'ogrenci' => '/ogrenci/index.php',
        'danisman' => '/danisman/index.php',
        'komisyon' => '/komisyon/index.php',
        'sekreter' => '/sekreter/index.php',
        default => '/yonetim/index.php',
    };
}

/** Sayfayı yalnızca belirtilen rollere açar; POST isteklerinde CSRF kontrolü yapar. */
function require_role(array $roller): array {
    $u = current_user();
    if (!$u) redirect('/login.php');
    if (!in_array($u['rol'], $roller, true)) {
        http_response_code(403);
        exit('<!DOCTYPE html><meta charset="utf-8">Bu sayfaya erişim yetkiniz yok. <a href="' . e(BASE_URL . rol_anasayfa($u['rol'])) . '">Kendi paneline dön</a>');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
    return $u;
}
