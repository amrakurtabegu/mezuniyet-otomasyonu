# Mezuniyet Komisyonu Otomasyonu (sürüm 3 – yeni arayüz ve iş akışı)

PHP 8.1+ (PDO) · MySQL/MariaDB (XAMPP) · HTML/CSS/JS — çerçevesiz. **Tüm öğrenci/akademik veriler kurgusaldır (DEMO).**

## 1. Yedek alma (güncellemeden ÖNCE)
- Proje klasörünü kopyalayın (ör. `mezuniyet-otomasyonu-yedek`).
- Veritabanı: `http://localhost/phpmyadmin` → `mezuniyet_otomasyonu` → **Dışa Aktar** → Hızlı / SQL → İndir.
  Komutla: `C:\xampp\mysql\bin\mysqldump.exe -u root mezuniyet_otomasyonu > yedek.sql`
- Geri yükleme: phpMyAdmin → **İçe Aktar** → `yedek.sql`.

## 2. Kurulum (XAMPP, Windows)
1. Projeyi `C:\xampp\htdocs\mezuniyet-otomasyonu` içine koyun (BASE_URL otomatik bulunur; klasör adı farklı olsa da çalışır).
2. XAMPP Control Panel'den **Apache** ve **MySQL**'i başlatın.
3. Veritabanını kurun/yükseltin — iki yol:
   - **Kolay:** `kurulum.bat` dosyasına çift tıklayın (göç + demo verisi).
   - **Elle:** `C:\xampp\php\php.exe database\migrate.php`, sonra `C:\xampp\php\php.exe database\migrate_v3.php` (**sürüm 3 için zorunlu**), sonra `C:\xampp\php\php.exe database\seed.php`.
   - (phpMyAdmin ile sıfır kurulum: `database/schema.sql` içe aktarın, sonra `seed.php`.)
   `migrate.php` mevcut verileri silmez, tekrar çalıştırılabilir. `seed.php` da tekrar çalıştırılabilir (yinelenen kayıt oluşturmaz, bozuk şifre özetlerini onarır).
4. MySQL root şifreniz varsa `config/db.local.php` oluşturup `<?php define('DB_PASS','sifre');` yazın.
5. Tarayıcı: `http://localhost/mezuniyet-otomasyonu/`
6. Sıfırlama: `php database\seed.php --reset` (tüm başvuru/karar/liste/mezun kayıtlarını siler, onay ister).

## 3. Demo hesaplar (şifre hepsi için `123456`, yalnızca yerel geliştirme)
| Rol | Kullanıcı No | Not |
|---|---|---|
| Öğrenci | 2021001 | tüm koşullar tamam |
| | 2021002 | yalnızca GANO 2.45 (<2.50) |
| | 2021003 | GANO + AKTS (15 eksik) + 2. yaz stajı + İME + zorunlu ders eksik |
| | 2021004, 2021005 | tüm koşullar tamam |
| | 2021006 | yalnızca İME onay bekliyor |
| | 2021007 | AKTS 8 eksik + zorunlu ders eksik |
| | 2021008 | akademik kaydı hiç aktarılmamış (doğrulanamaz) |
| | 2021009 | tüm koşullar tamam |
| | 2021099 | hesap var, öğrenci profili YOK (hata senaryosu) |
| Danışman | D001 (2021001–005), D002 (2021006–009) | |
| Komisyon | K1, K2, K3, K4, K5 | |
| Bölüm Başkanı | B001 | |
| Sekreter | S001 | |

## 4. Yeni iş akışı (sürüm 3)
```
Öğrenci başvurusu (eksik koşullar olsa da)  →  Danışman (onay/ret; eksikli onay gerekçeli)
  →  Komisyon (5 üye, bireysel veya TOPLU oy, 3/5 kuralı)
  →  Sekreterlik İLİŞİK KESME (bireysel veya toplu)  →  [tamamlanan öğrenci listeden çıkar, otomatik belge hazırlamaya geçer]
  →  Sekreterlik BELGE HAZIRLAMA (otomatik belge; bireysel/toplu)  →  [doğrudan Bilgi İşlem onayına gider, sekreterliğe geri dönüş yok]
  →  Bilgi İşlem BELGE ONAYI (bireysel/toplu; düzeltme isteyebilir)
  →  Başvuru dönemini KAPAT  →  Mezuniyet listesi OLUŞTUR (sistem öğrencileri seçer)  →  İNCELE  →  Listeyi KAPAT
  →  E-İMZA (simülasyon) SON ONAYI (bireysel/toplu)  →  PDF belge öğrencinin hesabına otomatik eklenir; öğrenci ve Bilgi İşlem indirir
```
Durumlar (`basvurular.durum`): `danisman_bekliyor`, `iade`, `danisman_red`, `komisyon_bekliyor`, `komisyon_onay` (ilişik kesme bekliyor), `komisyon_red`, `iliski_kesme_tamam` (belge hazırlama bekliyor), `belge_hazirlaniyor` (Bilgi İşlem belge onayı bekliyor), `belge_onayli` (liste bekliyor), `listede` (liste açık), `eimza_surecinde` (liste kapandı), `tamamlandi`. `son_onayli` yalnızca eski kayıtlar için durur.

## 4a. Demo senaryosu
1. `2021001` → bilgileri kaydet → **Mezuniyet İncelemesini Başlat**. `2021002` (eksik koşullu) de başvurabilir; eksikler kırmızı uyarıyla görünür. `2021004`, `2021005` de başvurur.
2. `D001` → 2021001'i inceleme sayfasında **Onayla**; 2021004/2021005'i listeden seçip **Toplu Onay**. (Eksik koşullu 2021002 yalnızca **gerekçeyle** onaylanabilir.)
3. `K1` → listeden 2021004 ve 2021005'i işaretleyip **Seçilenleri toplu onayla** (sonuç kartı: başarılı/başarısız/işlem yapılmadı); `K2`, `K3` ile 3 onaya tamamlayın. Tek tek inceleme sayfasında **Önceki/Sonraki öğrenci** ile gezin.
4. `S001` → **İlişik Kesme İşlemleri** → öğrenciyi incele → "Tümünü Tamamlandı yap" → **Kaydet ve sonrakine geç** (öğrenci listeden çıkar); veya birkaç öğrenciyi seçip **toplu tamamla**.
5. `S001` → **Belge Hazırlama** → seçilenler için **Belgeleri hazırla ve Bilgi İşlem'e gönder**.
6. `B001` → **Belge Onayları** → toplu onay (veya incele/onayla; hatalıysa **düzeltme iste**).
7. `B001` → **Başvuru Dönemleri** → dönemi **kapat**. **Mezuniyet Listeleri** → dönemi seç → adayları gör → oturum seç → **Listeyi oluştur** → incele → **Listeyi kapat**.
8. `B001` → **E-İmza Süreci** → **Toplu e-imza onayı**. Belge hemen tamamlanır.
9. `2021001` → **Mezuniyet Belgem** → **Belgeyi görüntüle / PDF indir**. `B001` → **Belgeler** → aynı belgeyi görüntüle/indir.

## 4b. Sürüm 3 teknik notlar
- **Göç:** `database/migrate_v3.php` yalnızca ekler (yeni durum `belge_onayli`, liste/belge/toplu işlem sütunları, FK'lar), tekrar çalıştırılabilir, eski akıştaki başvuruları yeni akışa taşır ve işlem geçmişine yazar. **Çalıştırmadan önce yedek alın.**
- **Başvuru politikası:** göç `BASVURU_POLITIKASI` değerini `izin_ver` yapar (eksik koşullu öğrenci başvurabilir). Danışman eksikli başvuruyu yalnızca **gerekçe yazarak** onaylayabilir; eksikler hiçbir zaman "tamamlandı" sayılmaz ve komisyonda görünür. Komisyon **toplu onayı** eksik koşullu başvuruları otomatik atlar.
- **Toplu işlemler** (`toplu_calistir`): her kayıt ayrı işlenir, biri başarısız olursa diğerleri sürer; `toplu_islemler` tablosuna ve `islem_loglari`'na yazılır. Komisyon oylarında `komisyon_kararlari.toplu_islem_id` ile izlenir.
- **Belge/PDF:** `includes/belge.php` harici kütüphane olmadan gerçek PDF üretir (Türkçe karakterli). E-imza onayında dosya `storage/belgeler/` altına kaydedilir (web'den erişime kapalıdır). **E-imza simülasyondur, hukuki geçerliliği yoktur.**
- Öğrenci inceleme ekranları artık tam sayfadır (`*/inceleme.php`); modal kaldırıldı.
- Yetki: ilişik kesme + belge hazırlama = sekreter; belge onayı, dönem, liste, e-imza = bölüm başkanı (Bilgi İşlem adımını yürütür; yeni rol eklenmedi). Sekreterin mezun iletişim bilgisi görmesi `SEKRETER_ILETISIM_GORUR` ile yönetilir.

## 5. Kurallar ve ayarlar
- **Dönemler:** 1–15 ve 16–ay sonu; artık yıl/ay uzunluğu otomatik. Dönem satırı yoksa otomatik oluşur (açık). Başkan açıp kapatabilir. Mezuniyet oturumları (liste tarihleri) bunlardan bağımsızdır.
- **Uygunluk:** GANO ≥ 2.50, AKTS, 2 yaz stajı, uzun dönem staj/İME, zorunlu dersler, ek koşullar. Veri yoksa koşul "doğrulanamadı" sayılır, sağlanmış sayılmaz.
- **Başvuru politikası** (`bolum_kurallari.BASVURU_POLITIKASI`): `engelle` (varsayılan: eksik koşullu öğrenci başvuramaz) veya `izin_ver` (başvuru alınır, danışman onaylayamaz). Hangisinin geçerli olduğu hocadan teyit edilmelidir.
- **Komisyon:** `config/db.php` → `KOMISYON_UYE_SAYISI=5`, `ONAY_ESIGI=3`. 3 onay → kabul; 3 ret → ret.
- **Toplu onay** yalnızca sunucu tarafında yeniden doğrulanan, danışmana atanmış ve uygun başvuruları işler; her başvuru ayrı işlemde (transaction) kaydedilir.

## 6. Yetkiler (varsayım — hocayla teyit edin)
Sekreter: ilişik kesme kaydı, belge hazırlama, listeleri görme (no/ad/GANO). Bölüm Başkanı: dönem/oturum yönetimi, liste oluşturma, son onay, e-imza (simülasyon), teslim kaydı, mezunlar, işlem kayıtları.

## 7. Dosya yapısı
`config/` ayarlar · `includes/` auth, helpers, **uygunluk** (kural motoru), **is_akisi** (durum geçişleri), donemler, senkron, mock_servis · `database/` schema.sql, migrate.php, seed.php · `ogrenci/ danisman/ komisyon/ yonetim/` ekranlar · `tests/calistir.php` uçtan uca test · `docs/` test planı ve Bilgi İşlem talebi.

## 8. Tamamlananlar / eksikler
Tamam: giriş + rol ayrımı, öğrenci paneli (uygunluk raporu), başvuru, danışman bireysel/toplu karar, komisyon 3/5, ilişik kesme, son onay, mezuniyet listesi, belge/e-imza **simülasyonu**, geçmiş ve işlem kayıtları.
Eksik / dış bağımlı: gerçek öğrenci servisi (AKTS, staj, ders), üniversite kimlik doğrulaması (LDAP/SSO), resmî toplam AKTS ve ders kuralları, ilişik kesme birimlerinin resmî listesi, gerçek e-imza servisi (Bilgi İşlem), belge şablonunun resmî biçimi.
Bilinen sınırlar: kod bu geliştirme ortamında ÇALIŞTIRILAMADI (bkz. `docs/TEST_PLANI.md`); öğrenci başına dönem başına tek başvuru kuralı gereği reddedilen öğrenci aynı dönemde yeniden başvuramaz; giriş denemesi sınırlaması (brute-force koruması) yok.
