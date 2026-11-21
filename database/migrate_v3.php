<?php
/**
 * GÜVENLİ VERİTABANI GÖÇÜ — sürüm 2 → 3 (yeni iş akışı)
 * Kullanım:  php database/migrate_v3.php      (önce database/migrate.php çalıştırılmış olmalıdır)
 * - Tablo/sütun SİLMEZ; yalnızca ekler. Tekrar çalıştırılabilir (idempotent). ÇALIŞTIRMADAN ÖNCE YEDEK ALIN.
 * - Eski akıştaki başvuruları yeni akışa taşır (4. adım) ve her taşıma işlem geçmişine yazılır.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/belge.php';

function m3_kolon_var(PDO $pdo, string $tablo, string $kolon): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $s->execute([$tablo, $kolon]);
    return (int)$s->fetchColumn() > 0;
}
function m3_kisit_var(PDO $pdo, string $tablo, string $ad): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
    $s->execute([$tablo, $ad]);
    return (int)$s->fetchColumn() > 0;
}

/** Göçü uygular; yapılan değişikliklerin listesini döndürür. Hata olursa istisna fırlatır. */
function migrasyon_v3(PDO $pdo): array {
    $log = [];
    // 1) Yeni durum: belge_onayli (Bilgi İşlem ilk belge onayı tamam, mezuniyet listesi bekleniyor)
    $tip = (string)$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'basvurular' AND COLUMN_NAME = 'durum'")->fetchColumn();
    if (strpos($tip, 'belge_onayli') === false) {
        $pdo->exec("ALTER TABLE basvurular MODIFY durum ENUM('danisman_bekliyor','iade','danisman_red','komisyon_bekliyor','komisyon_onay','komisyon_red',
            'iliski_kesme_tamam','son_onayli','listede','belge_hazirlaniyor','belge_onayli','eimza_surecinde','tamamlandi') NOT NULL DEFAULT 'danisman_bekliyor'");
        $log[] = "basvurular.durum: 'belge_onayli' eklendi.";
    }

    // 2) Yeni sütunlar
    $eklemeler = [
        ['mezuniyet_listeleri', 'durum', "ALTER TABLE mezuniyet_listeleri ADD COLUMN durum ENUM('olusturuldu','kapali') NOT NULL DEFAULT 'kapali'"],
        ['mezuniyet_listeleri', 'donem_id', 'ALTER TABLE mezuniyet_listeleri ADD COLUMN donem_id INT NULL'],
        ['mezuniyet_listeleri', 'kapatma_tarihi', 'ALTER TABLE mezuniyet_listeleri ADD COLUMN kapatma_tarihi DATETIME NULL'],
        ['mezuniyet_listeleri', 'kapatan_id', 'ALTER TABLE mezuniyet_listeleri ADD COLUMN kapatan_id INT NULL'],
        ['mezuniyet_belgeleri', 'ilk_onay_tarihi', 'ALTER TABLE mezuniyet_belgeleri ADD COLUMN ilk_onay_tarihi DATETIME NULL'],
        ['mezuniyet_belgeleri', 'ilk_onay_id', 'ALTER TABLE mezuniyet_belgeleri ADD COLUMN ilk_onay_id INT NULL'],
        ['mezuniyet_belgeleri', 'dogrulama_kodu', 'ALTER TABLE mezuniyet_belgeleri ADD COLUMN dogrulama_kodu VARCHAR(20) NULL'],
        ['mezuniyet_belgeleri', 'dosya_yolu', 'ALTER TABLE mezuniyet_belgeleri ADD COLUMN dosya_yolu VARCHAR(255) NULL'],
        ['toplu_islemler', 'tur', "ALTER TABLE toplu_islemler ADD COLUMN tur VARCHAR(30) NOT NULL DEFAULT 'danisman_onay'"],
        ['toplu_islemler', 'basarisiz_sayisi', 'ALTER TABLE toplu_islemler ADD COLUMN basarisiz_sayisi INT NOT NULL DEFAULT 0'],
        ['komisyon_kararlari', 'toplu_islem_id', 'ALTER TABLE komisyon_kararlari ADD COLUMN toplu_islem_id INT NULL'],
    ];
    foreach ($eklemeler as [$t, $k, $sql]) {
        if (!m3_kolon_var($pdo, $t, $k)) { $pdo->exec($sql); $log[] = "Sütun eklendi: $t.$k"; }
    }
    $kisitlar = [
        ['mezuniyet_listeleri', 'fk_liste_donem', 'ALTER TABLE mezuniyet_listeleri ADD CONSTRAINT fk_liste_donem FOREIGN KEY (donem_id) REFERENCES basvuru_donemleri(id)'],
        ['komisyon_kararlari', 'fk_kk_toplu', 'ALTER TABLE komisyon_kararlari ADD CONSTRAINT fk_kk_toplu FOREIGN KEY (toplu_islem_id) REFERENCES toplu_islemler(id)'],
    ];
    foreach ($kisitlar as [$t, $ad, $sql]) {
        if (!m3_kisit_var($pdo, $t, $ad)) { $pdo->exec($sql); $log[] = "FK eklendi: $ad"; }
    }

    // 3) Eksik koşullu öğrenci de başvurabilsin (danışman yine de koşulları sağlamayanı ONAYLAYAMAZ)
    $n = $pdo->exec("UPDATE bolum_kurallari SET deger = 'izin_ver' WHERE kod = 'BASVURU_POLITIKASI' AND (deger IS NULL OR deger <> 'izin_ver')");
    if ($n) $log[] = 'BASVURU_POLITIKASI = izin_ver olarak güncellendi.';

    // 4) Eski akıştaki başvuruları yeni akışa taşı (belge kayıtları eksikse oluşturulur; her taşıma işlem geçmişine yazılır)
    $gecmis = $pdo->prepare("INSERT INTO basvuru_gecmisi (basvuru_id, eski_durum, yeni_durum, aciklama) VALUES (?,?,?,'Sürüm 3 iş akışına geçiş')");
    $belgeEkle = $pdo->prepare('INSERT IGNORE INTO mezuniyet_belgeleri (basvuru_id, belge_no, dogrulama_kodu, ilk_onay_tarihi) VALUES (?,?,?,NOW())');
    $belgeOnay = $pdo->prepare('UPDATE mezuniyet_belgeleri SET ilk_onay_tarihi = COALESCE(ilk_onay_tarihi, NOW()) WHERE basvuru_id = ?');
    $durumYaz = $pdo->prepare('UPDATE basvurular SET durum = ? WHERE id = ?');
    $tasi = function (array $eski, string $yeni) use ($pdo, $gecmis, $belgeEkle, $belgeOnay, $durumYaz, &$log) {
        $ph = implode(',', array_fill(0, count($eski), '?'));
        $s = $pdo->prepare("SELECT b.id, b.durum, o.ogrenci_no FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.durum IN ($ph)");
        $s->execute($eski);
        $say = 0;
        foreach ($s->fetchAll() as $r) {
            if ($r['durum'] === 'listede') {   // yalnızca KAPALI listedeki öğrenciler e-imza aşamasına geçer
                $k = $pdo->prepare("SELECT l.durum FROM mezunlar m JOIN mezuniyet_listeleri l ON l.id = m.liste_id WHERE m.ogrenci_no = ?");
                $k->execute([$r['ogrenci_no']]);
                if ($k->fetchColumn() !== 'kapali') continue;
            }
            $no = belge_no_uret((int)$r['id']);
            $belgeEkle->execute([$r['id'], $no, belge_dogrulama_kodu($no, $r['ogrenci_no'])]);
            $belgeOnay->execute([$r['id']]);
            $gecmis->execute([$r['id'], $r['durum'], $yeni]);
            $durumYaz->execute([$yeni, $r['id']]);
            $say++;
        }
        if ($say) $log[] = implode('/', $eski) . " → $yeni: $say başvuru taşındı.";
    };
    $tasi(['son_onayli'], 'belge_onayli');                         // eski "başkan son onayı" → Bilgi İşlem belge onayı tamam
    $tasi(['listede', 'belge_hazirlaniyor'], 'eimza_surecinde');   // eski akışta listeye alınmış → e-imza onayı bekliyor

    // 5) PDF klasörü (web'den erişime kapalı)
    $k = belge_klasoru();
    if (!is_file(dirname($k) . '/.htaccess')) { @file_put_contents(dirname($k) . '/.htaccess', "Require all denied\nDeny from all\n"); $log[] = 'storage/belgeler klasörü hazırlandı.'; }
    return $log;
}

// Komut satırından doğrudan çalıştırılırsa:
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    try {
        $log = migrasyon_v3(db());
    } catch (Throwable $e) {
        fwrite(STDERR, 'GÖÇ HATASI: ' . $e->getMessage() . "\nGöç yarım kalmış olabilir; yedekten dönüp hatayı giderin.\n");
        exit(1);
    }
    echo $log ? implode("\n", $log) : 'Yapılacak değişiklik yok (veritabanı zaten güncel).', "\n";
}
