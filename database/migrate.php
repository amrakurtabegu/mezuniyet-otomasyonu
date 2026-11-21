<?php
/**
 * GÜVENLİ VERİTABANI GÖÇÜ (migration) — sürüm 1 → sürüm 2
 * Kullanım:  php database/migrate.php
 * - Veritabanı yoksa oluşturur; eksik tabloları/sütunları ekler; MEVCUT KAYITLARI SİLMEZ.
 * - Tekrar tekrar çalıştırılabilir (idempotent).
 * - ÇALIŞTIRMADAN ÖNCE YEDEK ALIN (README → "1. Yedek alma").
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/donemler.php';

function mig_kolon_var(PDO $pdo, string $tablo, string $kolon): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$tablo, $kolon]);
    return (int)$s->fetchColumn() > 0;
}
function mig_kisit_var(PDO $pdo, string $tablo, string $ad): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
    $s->execute([$tablo, $ad]);
    return (int)$s->fetchColumn() > 0;
}
function mig_tablo_var(PDO $pdo, string $tablo): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $s->execute([$tablo]);
    return (int)$s->fetchColumn() > 0;
}

/** schema.sql içeriğini ifadelere böler (tam satır "--" yorumları atılır). */
function mig_ifadeler(string $sql): array {
    $satirlar = [];
    foreach (preg_split('/\R/', $sql) as $l) if (!preg_match('/^\s*--/', $l)) $satirlar[] = $l;
    $ifadeler = [];
    foreach (explode(";\n", implode("\n", $satirlar) . "\n") as $i) { $i = trim($i); if ($i !== '') $ifadeler[] = $i; }
    return $ifadeler;
}

/** Göçü uygular; yapılan adımların listesini döndürür. $pdo, hedef veritabanına bağlı olmalıdır. */
function migrasyon_calistir(PDO $pdo): array {
    $log = [];
    // 1) Eksik tabloları oluştur (mevcut tablolara dokunmaz)
    foreach (mig_ifadeler(file_get_contents(__DIR__ . '/schema.sql')) as $ifade) {
        if (preg_match('/^(CREATE DATABASE|USE)\b/i', $ifade)) continue;
        $pdo->exec($ifade);
    }
    $log[] = 'Eksik tablolar oluşturuldu / kontrol edildi.';

    // 2) Eski tablolara sütun ekleme
    $eklemeler = [
        ['kullanicilar', 'aktif', 'ALTER TABLE kullanicilar ADD COLUMN aktif TINYINT(1) NOT NULL DEFAULT 1'],
        ['kullanicilar', 'demo_hesap', 'ALTER TABLE kullanicilar ADD COLUMN demo_hesap TINYINT(1) NOT NULL DEFAULT 0'],
        ['ogrenciler', 'program', 'ALTER TABLE ogrenciler ADD COLUMN program VARCHAR(100) NULL'],
        ['basvurular', 'donem_id', 'ALTER TABLE basvurular ADD COLUMN donem_id INT NULL'],
        ['basvurular', 'son_onay_kullanici_id', 'ALTER TABLE basvurular ADD COLUMN son_onay_kullanici_id INT NULL'],
        ['basvurular', 'son_onay_tarihi', 'ALTER TABLE basvurular ADD COLUMN son_onay_tarihi DATETIME NULL'],
        ['danisman_kararlari', 'toplu_islem_id', 'ALTER TABLE danisman_kararlari ADD COLUMN toplu_islem_id INT NULL'],
    ];
    foreach ($eklemeler as [$tablo, $kolon, $sql]) {
        if (!mig_kolon_var($pdo, $tablo, $kolon)) { $pdo->exec($sql); $log[] = "Sütun eklendi: $tablo.$kolon"; }
    }

    // 3) Durum listesini genişlet, oturum_id'yi isteğe bağlı yap (başvuru artık oturumdan bağımsız alınır)
    $pdo->exec("ALTER TABLE basvurular MODIFY durum ENUM('danisman_bekliyor','iade','danisman_red','komisyon_bekliyor','komisyon_onay','komisyon_red',
        'iliski_kesme_tamam','son_onayli','listede','belge_hazirlaniyor','eimza_surecinde','tamamlandi') NOT NULL DEFAULT 'danisman_bekliyor'");
    $pdo->exec('ALTER TABLE basvurular MODIFY oturum_id INT NULL');
    $log[] = 'basvurular.durum genişletildi, oturum_id isteğe bağlı yapıldı.';

    // 4) Yabancı anahtarlar
    if (!mig_kisit_var($pdo, 'basvurular', 'fk_basvuru_donem')) {
        $pdo->exec('ALTER TABLE basvurular ADD CONSTRAINT fk_basvuru_donem FOREIGN KEY (donem_id) REFERENCES basvuru_donemleri(id)');
        $log[] = 'FK eklendi: basvurular.donem_id';
    }
    if (!mig_kisit_var($pdo, 'danisman_kararlari', 'fk_dk_toplu')) {
        $pdo->exec('ALTER TABLE danisman_kararlari ADD CONSTRAINT fk_dk_toplu FOREIGN KEY (toplu_islem_id) REFERENCES toplu_islemler(id)');
        $log[] = 'FK eklendi: danisman_kararlari.toplu_islem_id';
    }

    // 5) Eski başvuruları dönemlere bağla (başvuru tarihine göre)
    $eski = $pdo->query('SELECT id, basvuru_tarihi FROM basvurular WHERE donem_id IS NULL')->fetchAll();
    $up = $pdo->prepare('UPDATE basvurular SET donem_id = ? WHERE id = ?');
    foreach ($eski as $r) {
        $up->execute([donem_getir($pdo, new DateTime($r['basvuru_tarihi']))['id'], $r['id']]);
    }
    if ($eski) $log[] = count($eski) . ' eski başvuru dönemlere bağlandı.';

    // 6) Öğrenci başına dönem başına tek başvuru kuralı (yinelenen eski kayıt varsa uyarı verir, göçü durdurmaz)
    $s = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'basvurular' AND INDEX_NAME = 'uq_basvuru_ogr_donem'");
    if ((int)$s->fetchColumn() === 0) {
        try { $pdo->exec('ALTER TABLE basvurular ADD UNIQUE KEY uq_basvuru_ogr_donem (ogrenci_id, donem_id)'); $log[] = 'UNIQUE eklendi: (ogrenci_id, donem_id)'; }
        catch (PDOException $ex) { $log[] = 'UYARI: (ogrenci_id, donem_id) UNIQUE kuralı eklenemedi, aynı dönemde yinelenen başvuru kaydı var. Elle düzeltin.'; }
    }
    return $log;
}

// Doğrudan komut satırından çalıştırıldığında:
if (PHP_SAPI === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    try {
        $kok = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $kok->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci');
    } catch (PDOException $ex) {
        fwrite(STDERR, 'MySQL sunucusuna bağlanılamadı (XAMPP\'ta MySQL çalışıyor mu?): ' . $ex->getMessage() . PHP_EOL);
        exit(1);
    }
    try {
        foreach (migrasyon_calistir(db()) as $satir) echo ' - ' . $satir . PHP_EOL;
        echo 'Göç tamamlandı. Şimdi demo verisi için: php database/seed.php' . PHP_EOL;
    } catch (Throwable $ex) {
        fwrite(STDERR, 'GÖÇ HATASI: ' . $ex->getMessage() . PHP_EOL . 'Veritabanınızı yedekten geri yükleyip hatayı bildirin.' . PHP_EOL);
        exit(1);
    }
}
