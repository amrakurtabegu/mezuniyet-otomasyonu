<?php
// Veritabanı ve uygulama ayarları (XAMPP varsayılanları).
// Ayarları değiştirmek için bu dosyayı düzenlemek yerine config/db.local.php oluşturabilirsiniz, örn:
//   <?php define('DB_PASS', 'sifreniz');
if (is_file(__DIR__ . '/db.local.php')) require __DIR__ . '/db.local.php';

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'mezuniyet_otomasyonu');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Komisyon: toplam üye sayısı ve kabul için gereken en az onay sayısı (5 üyeden 3'ü)
defined('KOMISYON_UYE_SAYISI') || define('KOMISYON_UYE_SAYISI', 5);
defined('ONAY_ESIGI') || define('ONAY_ESIGI', 3);

// Sekreter mezuniyet listelerinde çalışma durumu, e-posta ve telefonu görebilsin mi? (KVKK açısından hocayla teyit edin; false = yalnızca no, ad soyad, GANO, bölüm)
defined('SEKRETER_ILETISIM_GORUR') || define('SEKRETER_ILETISIM_GORUR', true);

date_default_timezone_set('Europe/Istanbul');

// BASE_URL otomatik bulunur (htdocs içindeki klasör adı ne olursa olsun). Gerekirse db.local.php içinde define('BASE_URL', '/klasor');
if (!defined('BASE_URL')) {
    $baseUrl = '/mezuniyet-otomasyonu';
    $docRoot = ($_SERVER['DOCUMENT_ROOT'] ?? '') !== '' ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $kok = realpath(dirname(__DIR__));
    if ($docRoot !== false && $kok !== false) {
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $kok = str_replace('\\', '/', $kok);
        if (stripos($kok, $docRoot) === 0) $baseUrl = rtrim(substr($kok, strlen($docRoot)), '/');
    }
    define('BASE_URL', $baseUrl);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $ex) {
            // Kimlik bilgileri/yığın izi kullanıcıya gösterilmez; teknik ayrıntı yalnızca sunucu günlüğüne yazılır.
            error_log('[mezuniyet-otomasyonu] Veritabanı bağlantı hatası: ' . $ex->getMessage());
            if (PHP_SAPI === 'cli') { fwrite(STDERR, 'Veritabanına bağlanılamadı: ' . $ex->getMessage() . PHP_EOL); exit(1); }
            http_response_code(500);
            exit('Veritabanına bağlanılamadı. XAMPP\'ta MySQL\'in çalıştığını ve veritabanının içe aktarıldığını (database/schema.sql) kontrol edin. Teknik ayrıntı sunucu günlüğündedir.');
        }
    }
    return $pdo;
}
