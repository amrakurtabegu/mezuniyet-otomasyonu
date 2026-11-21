<?php
/**
 * DEMO VERİSİ YÜKLEME (yalnızca yerel geliştirme/ders sunumu içindir)
 * Kullanım:
 *   php database/seed.php            → demo hesapları, öğrenci/akademik kayıtları, dönemleri ve oturumları oluşturur/onarır
 *   php database/seed.php --reset    → ayrıca TÜM başvuru/karar/liste/mezun kayıtlarını siler (onay ister)
 * Tekrar tekrar çalıştırmak güvenlidir: yinelenen kullanıcı/öğrenci/akademik kayıt oluşmaz.
 * UYARI: Demo hesapların şifresi her çalıştırmada "123456" olarak yeniden yazılır (bozuk hash'leri onarır).
 * Tüm veriler KURGUSALDIR (includes/mock_servis.php); gerçek öğrenci/akademik veri değildir.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock_servis.php';
require_once __DIR__ . '/../includes/senkron.php';
require_once __DIR__ . '/../includes/donemler.php';

const DEMO_SIFRE = '123456';

function demo_hesaplar(): array {
    $h = [['D001', 'Prof. Dr. Ayşe Danışman', 'danisman'], ['D002', 'Doç. Dr. Kemal Yıldırım', 'danisman'],['D003', 'Dr. Öğr. Üyesi Tuba Karaca', 'danisman']];
    foreach ([1=>'Dr. Öğr. Üyesi Can Aydın', 2=>'Doç. Dr. Selin Korkmaz', 3=>'Prof. Dr. Murat Şahin', 4=>'Dr. Öğr. Üyesi Zeynep Arslan', 5=>'Doç. Dr. Burak Öztürk'] as $i => $ad)
        $h[] = ["K$i", $ad, 'komisyon'];
    $h[] = ['B001', 'Prof. Dr. Hasan Başkan', 'bolum_baskani'];
    $h[] = ['S001', 'Fatma Sekreter', 'sekreter'];
    foreach (mock_ogrenci_listesi() as $no => $o) $h[] = [(string)$no, $o['ad'] . ' ' . $o['soyad'], 'ogrenci'];
    $h[] = ['2021099', 'Profilsiz Test Hesabı', 'ogrenci'];   // kullanıcı hesabı var, öğrenci profili yok → hata senaryosu testi
    return $h;
}

function demo_veri_yukle(PDO $pdo, bool $reset = false): array {
    $rapor = [];
    if ($reset) {
        foreach (['mezuniyet_belgeleri', 'mezunlar', 'mezuniyet_listeleri', 'iliski_kesme_kontrolleri', 'komisyon_kararlari', 'danisman_kararlari',
                  'toplu_islemler', 'basvuru_gecmisi', 'basvuru_uygunluk', 'basvurular', 'islem_loglari'] as $t) $pdo->exec("DELETE FROM $t");
        $pdo->exec("UPDATE oturumlar SET durum = 'acik', gercek_kapanis_tarihi = NULL");
        $rapor[] = 'Başvuru, karar, liste ve mezun kayıtları sıfırlandı; oturumlar yeniden açıldı.';
    }
    $pdo->exec("INSERT IGNORE INTO bolum_kurallari (kod, deger, aciklama, dogrulandi) VALUES
        ('GANO_MIN', '2.50', 'Mezuniyet için en az GANO (proje isterlerinde belirtilen kural)', 1),
        ('TOPLAM_AKTS', NULL, 'Mezuniyet için gereken toplam AKTS: BÖLÜMDEN TEYİT EDİLMELİ', 0),
        ('BASVURU_POLITIKASI', 'engelle', 'engelle: zorunlu koşulu eksik öğrenci başvuramaz (varsayılan). izin_ver: başvuru alınır ancak danışman onaylayamaz', 1)");

    $hash = password_hash(DEMO_SIFRE, PASSWORD_DEFAULT);
    $k = $pdo->prepare('INSERT INTO kullanicilar (kullanici_no, sifre_hash, ad_soyad, rol, aktif, demo_hesap) VALUES (?,?,?,?,1,1)
        ON DUPLICATE KEY UPDATE sifre_hash=VALUES(sifre_hash), ad_soyad=VALUES(ad_soyad), rol=VALUES(rol), aktif=1, demo_hesap=1');
    $n = 0;
    foreach (demo_hesaplar() as [$no, $ad, $rol]) { $k->execute([$no, $hash, $ad, $rol]); $n++; }
    $rapor[] = "$n demo hesap oluşturuldu/onarıldı (şifre: " . DEMO_SIFRE . ').';

    // Öğrenci profilleri ve akademik kayıtlar (mock servis → yerel tablolar)
    $id = $pdo->prepare('SELECT id FROM kullanicilar WHERE kullanici_no = ?');
    $say = 0;
    foreach (array_keys(mock_ogrenci_listesi()) as $no) {
        $id->execute([(string)$no]);
        [$ok, $kod] = ogrenci_senkronize($pdo, (int)$id->fetchColumn(), (string)$no);
        if (!$ok) throw new RuntimeException("Öğrenci $no eşitlenemedi: $kod");
        $say++;
    }
    $rapor[] = "$say öğrenci profili ve akademik kaydı eşitlendi (kaynak: mock).";

    // Başvuru dönemleri: bu ay ve sonraki 2 ay (1–15 ve 16–ay sonu); içinde bulunulan dönem AÇIK
    for ($m = 0; $m < 3; $m++) {
        $ay = new DateTime('first day of this month');
        $ay->modify("+$m month");
        donem_getir($pdo, (clone $ay)->setDate((int)$ay->format('Y'), (int)$ay->format('n'), 1));
        donem_getir($pdo, (clone $ay)->setDate((int)$ay->format('Y'), (int)$ay->format('n'), 16));
    }
    $su = donem_getir($pdo);
    $pdo->prepare("UPDATE basvuru_donemleri SET durum = 'acik' WHERE id = ?")->execute([$su['id']]);
    $rapor[] = 'Başvuru dönemleri hazır. Şu anki dönem açık: ' . donem_etiketi($su);

    // Mezuniyet oturumları (planlanan tarihler: her ayın 15'i ve son günü)
    $o = $pdo->prepare('INSERT IGNORE INTO oturumlar (planlanan_tarih) VALUES (?)');
    for ($m = 0; $m < 6; $m++) {
        $ay = new DateTime('first day of this month');
        $ay->modify("+$m month");
        foreach ([$ay->format('Y-m-15'), $ay->format('Y-m-t')] as $t) if ($t >= date('Y-m-d')) $o->execute([$t]);
    }
    $rapor[] = 'Mezuniyet oturum tarihleri hazır.';
    return $rapor;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    $reset = in_array('--reset', $argv, true);
    if ($reset && !in_array('--evet', $argv, true)) {
        echo "DİKKAT: Tüm başvuru, karar, mezuniyet listesi ve mezun kayıtları SİLİNECEK (kullanıcılar kalır).\nDevam etmek için EVET yazın: ";
        if (trim((string)fgets(STDIN)) !== 'EVET') { echo "İptal edildi.\n"; exit(0); }
    }
    try {
        foreach (demo_veri_yukle(db(), $reset) as $satir) echo ' - ' . $satir . PHP_EOL;
    } catch (Throwable $ex) {
        fwrite(STDERR, 'SEED HATASI: ' . $ex->getMessage() . PHP_EOL . '(Önce "php database/migrate.php" çalıştırıldı mı?)' . PHP_EOL);
        exit(1);
    }
    echo "\nHesaplar (şifre hepsi için " . DEMO_SIFRE . "):\n";
    echo "  Öğrenci: 2021001..2021037 ve 2021100..2021189 | Profilsiz test hesabı: 2021099\n  Danışman: D001, D002, D003 (öğrenciler üçüne dağıtılmıştır)\n";
    echo "  Komisyon: K1..K5 | Bölüm Başkanı: B001 | Sekreter: S001\n";
}
