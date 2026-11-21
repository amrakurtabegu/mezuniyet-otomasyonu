<?php
/**
 * Uçtan uca iş akışı testi (komut satırı). Ayrı bir TEST veritabanı kullanır; gerçek verinize dokunmaz.
 * Çalıştırma:  C:\xampp\php\php.exe tests\calistir.php
 * Test veritabanı (mezuniyet_otomasyonu_test) her çalıştırmada silinip yeniden kurulur.
 */
define('DB_NAME', 'mezuniyet_otomasyonu_test');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/migrate.php';
require_once __DIR__ . '/../database/migrate_v3.php';
require_once __DIR__ . '/../database/seed.php';

$gecen = 0; $kalan = 0;
function ok(bool $kosul, string $ad): void {
    global $gecen, $kalan;
    if ($kosul) { $gecen++; echo "  [GEÇTİ] $ad\n"; } else { $kalan++; echo "  [KALDI] $ad\n"; }
}
function kimlik(PDO $p, string $no): int { $s = $p->prepare('SELECT id FROM kullanicilar WHERE kullanici_no=?'); $s->execute([$no]); return (int)$s->fetchColumn(); }
function ogr(PDO $p, string $no): array { $s = $p->prepare('SELECT * FROM ogrenciler WHERE ogrenci_no=?'); $s->execute([$no]); return $s->fetch(); }
function bas(PDO $p, string $no): array { $s = $p->prepare('SELECT b.* FROM basvurular b JOIN ogrenciler o ON o.id=b.ogrenci_id WHERE o.ogrenci_no=? ORDER BY b.id DESC LIMIT 1'); $s->execute([$no]); return $s->fetch() ?: []; }
function iletisim(PDO $p, string $no): void { $p->prepare("UPDATE ogrenciler SET eposta='t@example.com', telefon='05551234567', calisma_durumu='Çalışmıyor' WHERE ogrenci_no=?")->execute([$no]); }

// --- Kurulum: test veritabanını sıfırdan oluştur
$kok = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$kok->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
$kok->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci');
$pdo = db();
migrasyon_calistir($pdo); migrasyon_v3($pdo);
demo_veri_yukle($pdo);
$sonra = demo_veri_yukle($pdo);   // ikinci çalıştırma: yinelenen kayıt oluşmamalı
echo "\n== Kurulum / tekrarlanabilirlik\n";
ok((int)$pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_no='2021001'")->fetchColumn() === 1, 'Seed iki kez çalışınca kullanıcı yinelenmedi');
ok((int)$pdo->query('SELECT COUNT(*) FROM ders_kayitlari')->fetchColumn() === 24, 'Ders kayıtları yinelenmedi');

echo "\n== Dönem hesabı\n";
ok(donem_hesapla(new DateTime('2028-02-20'))['bitis'] === '2028-02-29', 'Artık yıl: Şubat 2028 ikinci dönem 29 Şubat’ta biter');
ok(donem_hesapla(new DateTime('2027-02-20'))['bitis'] === '2027-02-28', 'Şubat 2027 ikinci dönem 28 Şubat’ta biter');
ok(donem_hesapla(new DateTime('2026-10-15'))['donem'] === 1 && donem_hesapla(new DateTime('2026-10-16'))['donem'] === 2, '15 → 1. dönem, 16 → 2. dönem');

echo "\n== Giriş\n";
$_SESSION = [];
ok(giris_yap('2021001', '123456') === null && ($_SESSION['user']['rol'] ?? '') === 'ogrenci', 'Geçerli öğrenci girişi');
$_SESSION = [];
ok(giris_yap('2021001', 'yanlis') === 'Şifre hatalı.', 'Yanlış şifre reddedildi');
ok(str_contains((string)giris_yap('9999999', 'x'), 'bulunamadı'), 'Bilinmeyen numara anlaşılır mesaj verdi');
$pdo->prepare("UPDATE kullanicilar SET sifre_hash='bozuk' WHERE kullanici_no='2021005'")->execute();
ok(str_contains((string)giris_yap('2021005', '123456'), 'geçersiz'), 'Bozuk hash ayrı mesajla bildirildi (şifre kontrolü atlanmadı)');
demo_veri_yukle($pdo);
ok(giris_yap('2021005', '123456') === null, 'Seed bozuk hash’i onardı');
[$o, $kod] = ogrenci_senkronize($pdo, kimlik($pdo, '2021099'), '2021099');
ok(!$o && $kod === 'profil_yok', 'Profili olmayan hesap "profil_yok" olarak ayrıştırıldı');

echo "\n== Uygunluk raporu\n";
$r2 = uygunluk_raporu($pdo, (int)ogr($pdo, '2021002')['id']);
ok(!$r2['uygun'] && $r2['kontroller'][0]['kod'] === 'gano' && $r2['kontroller'][0]['durum'] === 'eksik', 'GANO 2.45 → GANO eksik');
$r3 = uygunluk_raporu($pdo, (int)ogr($pdo, '2021003')['id']);
$m3 = implode(' ', $r3['eksikler']);
ok(str_contains($m3, '15 AKTS eksik'), 'AKTS açığı doğru hesaplandı (15)');
ok(str_contains($m3, 'İkinci yaz stajı') && str_contains($m3, 'Uzun dönem staj'), 'Eksik stajların her biri listelendi');
$r8 = uygunluk_raporu($pdo, (int)ogr($pdo, '2021008')['id']);
ok(!$r8['uygun'] && !$r8['akademik_var'] && $r8['kontroller'][0]['durum'] === 'belirsiz', 'Bilinmeyen veri "sağlandı" sayılmadı');
ok(uygunluk_raporu($pdo, (int)ogr($pdo, '2021001')['id'])['uygun'], 'Tüm koşulları sağlayan öğrenci uygun');

echo "\n== Başvuru\n";
ok(kural_degeri($pdo, 'BASVURU_POLITIKASI') === 'izin_ver', 'Göç sonrası varsayılan politika: eksik koşullu öğrenci başvurabilir');
$pdo->exec("UPDATE bolum_kurallari SET deger='engelle' WHERE kod='BASVURU_POLITIKASI'");
$og8 = ogr($pdo, '2021008'); iletisim($pdo, '2021008'); $og8 = ogr($pdo, '2021008');
ok(basvuru_on_kosullari($pdo, $og8)['kod'] === 'akademik_yok', 'Akademik kaydı olmayan öğrenci için ayrı neden kodu');
$og2 = ogr($pdo, '2021002'); iletisim($pdo, '2021002'); $og2 = ogr($pdo, '2021002');
ok(basvuru_on_kosullari($pdo, $og2)['kod'] === 'eksik_kosul', 'Politika "engelle" iken eksik koşullu öğrenci engellendi');
foreach (['2021001','2021004','2021005'] as $no) iletisim($pdo, $no);
$og1 = ogr($pdo, '2021001');
$donem = donem_getir($pdo);
$pdo->prepare("UPDATE basvuru_donemleri SET durum='kapali' WHERE id=?")->execute([$donem['id']]);
ok(basvuru_on_kosullari($pdo, $og1)['kod'] === 'donem_kapali', 'Kapalı dönemde başvuru alınmadı');
$pdo->prepare("UPDATE basvuru_donemleri SET durum='acik' WHERE id=?")->execute([$donem['id']]);
[$b1] = basvuru_olustur($pdo, $og1);
ok($b1 && bas($pdo, '2021001')['durum'] === 'danisman_bekliyor', 'Uygun öğrenci başvurusu kaydedildi');
ok(!basvuru_olustur($pdo, $og1)[0], 'Yinelenen aktif başvuru engellendi');
foreach (['2021004', '2021005'] as $no) basvuru_olustur($pdo, ogr($pdo, $no));
$D1 = kimlik($pdo, 'D001'); $D2 = kimlik($pdo, 'D002');

echo "\n== Danışman\n";
ok(!danisman_karar_uygula($pdo, $D2, (int)bas($pdo, '2021001')['id'], 'onay', '')[0], 'Başka danışman atanmamış başvuruyu onaylayamaz');
ok(!danisman_karar_uygula($pdo, $D1, (int)bas($pdo, '2021001')['id'], 'red', '')[0], 'Gerekçesiz ret reddedildi');
ok(danisman_karar_uygula($pdo, $D1, (int)bas($pdo, '2021001')['id'], 'onay', '')[0] && bas($pdo, '2021001')['durum'] === 'komisyon_bekliyor', 'Bireysel onay → komisyon_bekliyor');
$t = toplu_onay_uygula($pdo, $D1, [(int)bas($pdo, '2021004')['id'], (int)bas($pdo, '2021005')['id']]);
ok($t['onaylanan'] === 2, 'Toplu onay iki uygun başvuruyu onayladı');
// Politikayı gevşet: uygunsuz öğrenci başvurabilsin, ama onay engellensin
$pdo->exec("UPDATE bolum_kurallari SET deger='izin_ver' WHERE kod='BASVURU_POLITIKASI'");
[$bk] = basvuru_olustur($pdo, ogr($pdo, '2021002'));
ok($bk && bas($pdo, '2021002')['mezuniyete_uygun'] == 0, 'izin_ver politikasında eksikli başvuru alındı (uygun=0)');
$tb = toplu_onay_uygula($pdo, $D1, [(int)bas($pdo, '2021002')['id']]);
ok($tb['onaylanan'] === 0 && $tb['atlanan'] === 1 && bas($pdo, '2021002')['durum'] === 'danisman_bekliyor', 'Uygunsuz başvuru toplu onayla atlatılamadı');
ok(!danisman_karar_uygula($pdo, $D1, (int)bas($pdo, '2021002')['id'], 'onay', '')[0], 'Eksik koşullu başvuru GEREKÇESİZ onaylanamadı');
ok(danisman_karar_uygula($pdo, $D1, (int)bas($pdo, '2021002')['id'], 'iade', 'GANO yetersiz')[0] && bas($pdo, '2021002')['durum'] === 'iade', 'İade gerekçeyle kaydedildi (ayrı durum)');
$pdo->exec("UPDATE bolum_kurallari SET deger='engelle' WHERE kod='BASVURU_POLITIKASI'");

echo "\n== Komisyon\n";
$bid = (int)bas($pdo, '2021001')['id'];
$K = array_map(fn($i) => kimlik($pdo, "K$i"), [1,2,3,4,5]);
ok((int)$pdo->query("SELECT COUNT(*) FROM basvurular WHERE durum='komisyon_bekliyor'")->fetchColumn() === 3, 'Komisyon, danışman onaylı başvuruları görüyor');
ok(komisyon_oy_ver($pdo, $K[0], $bid, 'onay')[0], 'K1 onay verdi');
ok(!komisyon_oy_ver($pdo, $K[0], $bid, 'onay')[0], 'K1 ikinci kez oy kullanamadı');
komisyon_oy_ver($pdo, $K[1], $bid, 'onay');
ok(bas($pdo, '2021001')['durum'] === 'komisyon_bekliyor', '2 onayda karar henüz kesinleşmedi');
$son = komisyon_oy_ver($pdo, $K[2], $bid, 'onay');
ok($son[2] === 'komisyon_onay' && bas($pdo, '2021001')['durum'] === 'komisyon_onay', '3. onayda başvuru komisyon_onay oldu');
ok(!komisyon_oy_ver($pdo, $K[3], $bid, 'red')[0] && bas($pdo, '2021001')['durum'] === 'komisyon_onay', 'Kesinleşmiş karar ek oyla değişmedi');
ok(!komisyon_oy_ver($pdo, $D1, (int)bas($pdo, '2021004')['id'], 'onay')[0], 'Komisyon üyesi olmayan oy kullanamadı');
$b4 = (int)bas($pdo, '2021004')['id'];
komisyon_oy_ver($pdo, $K[0], $b4, 'red'); komisyon_oy_ver($pdo, $K[1], $b4, 'red'); $r = komisyon_oy_ver($pdo, $K[2], $b4, 'red');
ok($r[2] === 'komisyon_red', '3 retle başvuru reddedildi');

$b5 = (int)bas($pdo, '2021005')['id'];
$tk = komisyon_toplu_onay($pdo, $K[0], [$b5, $bid]);
ok($tk['basarili'] === 1 && $tk['atlandi'] === 1, 'Komisyon toplu onayı: oy verilebilen işlendi, kesinleşmiş başvuru atlandı');
ok(komisyon_toplu_onay($pdo, $K[0], [$b5])['atlandi'] === 1, 'Toplu onayda daha önce oy verilen başvuru yeniden işlenmedi');
komisyon_oy_ver($pdo, $K[1], $b5, 'onay'); komisyon_oy_ver($pdo, $K[2], $b5, 'onay');
ok(bas($pdo, '2021005')['durum'] === 'komisyon_onay', 'Toplu oy + bireysel oylarla 3/5 kuralı işledi');

echo "\n== İlişik kesme → belge → Bilgi İşlem onayı → dönem/liste → e-imza\n";
$S = kimlik($pdo, 'S001'); $B = kimlik($pdo, 'B001');
ok(!iliski_kesme_kaydet($pdo, $bid, ['kutuphane' => ['durum' => 'engel', 'aciklama' => '']], $S)[0], 'Açıklamasız "engel var" kaydı reddedildi');
$r = iliski_kesme_kaydet($pdo, $bid, ['kutuphane' => ['durum' => 'tamam', 'aciklama' => '']], $S);
ok($r[0] && !$r[2] && bas($pdo, '2021001')['durum'] === 'komisyon_onay', 'Tek birim tamam → öğrenci ilişik kesme listesinde kaldı');
$r = iliski_kesme_kaydet($pdo, $bid, ['diger_birimler' => ['durum' => 'tamam', 'aciklama' => '']], $S);
ok($r[0] && $r[2] && bas($pdo, '2021001')['durum'] === 'iliski_kesme_tamam', 'Tüm birimler tamam → otomatik belge hazırlama aşaması');
ok(!iliski_kesme_kaydet($pdo, $bid, ['kutuphane' => ['durum' => 'engel', 'aciklama' => 'x']], $S)[0] && bas($pdo, '2021001')['durum'] === 'iliski_kesme_tamam', 'Tamamlanan öğrenci ilişik kesme listesine geri düşmedi');
$tp = iliski_kesme_toplu_tamamla($pdo, $S, [$b5, $bid]);
ok($tp['basarili'] === 1 && $tp['atlandi'] === 1 && bas($pdo, '2021005')['durum'] === 'iliski_kesme_tamam', 'Toplu ilişik kesme: uygun öğrenci tamamlandı, tamamlanmış olan atlandı');
ok(belge_hazirla($pdo, $bid, $S)[0] && bas($pdo, '2021001')['durum'] === 'belge_hazirlaniyor', 'Belge hazırlandı ve doğrudan Bilgi İşlem onayına gitti');
ok(!belge_hazirla($pdo, $bid, $S)[0], 'Aynı öğrenci için belge hazırlama tekrarlanamadı');
ok(belge_toplu_hazirla($pdo, $S, [$b5])['basarili'] === 1, 'Toplu belge hazırlama çalıştı');
ok(!belge_duzeltme_iste($pdo, $b5, $B, '')[0], 'Açıklamasız düzeltme talebi reddedildi');
ok(belge_duzeltme_iste($pdo, $b5, $B, 'Soyad yazımı hatalı')[0] && bas($pdo, '2021005')['durum'] === 'iliski_kesme_tamam', 'Düzeltme talebi belge hazırlama listesine geri gönderdi');
ok(belge_hazirla($pdo, $b5, $S)[0], 'Düzeltilen belge yeniden hazırlandı');
ok(belge_ilk_onay($pdo, $bid, $B)[0] && bas($pdo, '2021001')['durum'] === 'belge_onayli', 'Bilgi İşlem bireysel belge onayı');
ok(!belge_ilk_onay($pdo, $bid, $B)[0], 'Onaylanan belge tekrar onay listesine düşmedi');
$bt = belge_toplu_onayla($pdo, $B, [$b5, $bid]);
ok($bt['basarili'] === 1 && $bt['atlandi'] === 1, 'Bilgi İşlem toplu belge onayı: bekleyen onaylandı, onaylı olan atlandı');

$pdo->exec("INSERT IGNORE INTO oturumlar (planlanan_tarih) VALUES ('2099-12-31')");
$oturum = (int)$pdo->query("SELECT id FROM oturumlar WHERE planlanan_tarih='2099-12-31'")->fetchColumn();
$acikHata = false;
try { liste_olustur($pdo, (int)$donem['id'], $oturum, $B); } catch (RuntimeException $ex) { $acikHata = true; }
ok($acikHata, 'Başvuru dönemi açıkken liste oluşturulamadı');
$pdo->prepare("UPDATE basvuru_donemleri SET durum='kapali' WHERE id=?")->execute([$donem['id']]);
ok(count(liste_adaylari($pdo, (int)$donem['id'])) === 2, 'Sistem listeye alınacak 2 belge onaylı öğrenciyi gösterdi');
$liste = liste_olustur($pdo, (int)$donem['id'], $oturum, $B);
ok($liste > 0 && (int)$pdo->query("SELECT COUNT(*) FROM mezunlar WHERE liste_id=$liste")->fetchColumn() === 2 && bas($pdo, '2021001')['durum'] === 'listede', 'Dönem kapatıldıktan sonra liste ve mezun kayıtları oluştu');
ok(!eimza_onayla($pdo, $bid, $B)[0], 'Liste kapatılmadan e-imza onayı verilemedi');
ok(liste_kapat($pdo, $liste, $B)[0] && bas($pdo, '2021001')['durum'] === 'eimza_surecinde', 'Liste kapatıldı → öğrenciler e-imza aşamasında');
ok(!liste_iptal($pdo, $liste, $B)[0] && !liste_kapat($pdo, $liste, $B)[0], 'Kapatılan liste iptal edilemedi / yeniden kapatılamadı');
ok(eimza_onayla($pdo, $bid, $B)[0] && bas($pdo, '2021001')['durum'] === 'tamamlandi', 'Bireysel e-imza (simülasyon) onayı → tamamlandi');
$et = eimza_toplu_onayla($pdo, $B, [$b5, $bid]);
ok($et['basarili'] === 1 && $et['atlandi'] === 1 && bas($pdo, '2021005')['durum'] === 'tamamlandi', 'Toplu e-imza onayı çalıştı');
$v = belge_verisi($pdo, $bid);
ok($v && belge_imzali($v) && $v['ogrenci_no'] === '2021001', 'Son onay sonrası belge imzalı ve doğru öğrenciye ait');
$pdfIcerik = belge_pdf_icerik($v);
ok(strpos($pdfIcerik, '%PDF-1.4') === 0 && strpos($pdfIcerik, '%%EOF') !== false, 'PDF belgesi üretildi');
$yol = belge_pdf_dosyasi($pdo, $bid);
ok($yol && is_file($yol) && strpos((string)file_get_contents($yol), '%PDF') === 0, 'İmzalı PDF dosyası saklandı');
if ($yol) @unlink($yol);
ok((int)$pdo->query("SELECT COUNT(*) FROM toplu_islemler WHERE tur IN ('komisyon_onay','iliski_kesme','belge_hazirla','belge_onay','eimza_onay')")->fetchColumn() >= 6, 'Her toplu işlem ayrı kayıt olarak saklandı');
ok((int)$pdo->query("SELECT COUNT(*) FROM komisyon_kararlari WHERE toplu_islem_id IS NOT NULL")->fetchColumn() >= 1, 'Toplu onaydaki oy üye adına ve toplu işlem numarasıyla kaydedildi');

echo "\n== Denetim kaydı\n";
ok((int)$pdo->query("SELECT COUNT(*) FROM islem_loglari WHERE islem LIKE 'Danışman kararı%'")->fetchColumn() >= 3, 'Danışman kararları işlem kaydında');
ok((int)$pdo->query("SELECT COUNT(*) FROM islem_loglari WHERE islem LIKE 'Komisyon üyesi oyu%'")->fetchColumn() >= 4, 'Komisyon oyları işlem kaydında');
ok((int)$pdo->query('SELECT COUNT(*) FROM basvuru_gecmisi')->fetchColumn() > 10, 'Durum geçmişi tutuluyor');

echo "\n== Göç (eski şema → yeni şema, veri korunur)\n";
$pdo->exec('DROP DATABASE `' . DB_NAME . '`');
$pdo->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci');
$eski = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (mig_ifadeler(file_get_contents(__DIR__ . '/eski_schema.sql')) as $i) if (!preg_match('/^(CREATE DATABASE|USE)\b/i', $i)) $eski->exec($i);
$h = password_hash('123456', PASSWORD_DEFAULT);
$eski->prepare("INSERT INTO kullanicilar (kullanici_no,sifre_hash,ad_soyad,rol) VALUES ('D001',?,'Eski Danışman','danisman'),('2021001',?,'Eski Öğrenci','ogrenci')")->execute([$h, $h]);
$eski->exec("INSERT INTO ogrenciler (kullanici_id,ogrenci_no,ad_soyad,gano,bolum,danisman_id) VALUES (2,'2021001','Eski Öğrenci',3.1,'BM',1)");
$eski->exec("INSERT INTO oturumlar (planlanan_tarih) VALUES ('2026-10-31')");
$eski->exec("INSERT INTO basvurular (ogrenci_id,oturum_id,basvuru_tarihi,durum,mezuniyete_uygun) VALUES (1,1,'2026-10-20 10:00:00','komisyon_bekliyor',1)");
migrasyon_calistir($eski); migrasyon_v3($eski);
$eski->exec('USE `' . DB_NAME . '`');
ok((int)$eski->query('SELECT COUNT(*) FROM basvurular')->fetchColumn() === 1 && $eski->query('SELECT durum FROM basvurular')->fetchColumn() === 'komisyon_bekliyor', 'Eski başvuru kaydı ve durumu korundu');
ok($eski->query('SELECT donem_id FROM basvurular')->fetchColumn() !== null, 'Eski başvuru bir başvuru dönemine bağlandı');
ok(giris_yap_kontrol($eski), 'Eski kullanıcı hash’i hâlâ geçerli');
function giris_yap_kontrol(PDO $p): bool { return password_verify('123456', (string)$p->query("SELECT sifre_hash FROM kullanicilar WHERE kullanici_no='2021001'")->fetchColumn()); }
migrasyon_calistir($eski); migrasyon_v3($eski);
ok((int)$eski->query('SELECT COUNT(*) FROM basvurular')->fetchColumn() === 1, 'Göç ikinci kez çalışınca veri bozulmadı');

echo "\nSonuç: $gecen geçti, $kalan kaldı.\n";
exit($kalan ? 1 : 0);
