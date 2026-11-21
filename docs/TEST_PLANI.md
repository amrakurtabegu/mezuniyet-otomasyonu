# Test Planı

## Otomatik (komut satırı)
`C:\xampp\php\php.exe tests\calistir.php` — ayrı `mezuniyet_otomasyonu_test` veritabanını kurar, iş akışını uçtan uca dener ve GEÇTİ/KALDI yazar. Gerçek veritabanınıza dokunmaz.
Kapsadığı senaryolar: geçerli/geçersiz giriş, bilinmeyen numara, bozuk şifre özeti, profilsiz hesap, GANO/AKTS/staj eksikleri, doğrulanamayan veri, kapalı dönem, başvuru, yinelenen başvuru, danışman kapsamı, bireysel onay/iade/ret, toplu onay, uygunsuz başvurunun atlatılamaması, komisyon (tek oy, 3 onay, 3 ret, kesin karar), ilişik kesme, son onay, liste/mezun, belge/e-imza simülasyonu, denetim kaydı, eski şemadan göç (veri korunumu), artık yıl dönemleri.

## Elle (tarayıcı) — otomatik kapsamayan
| # | Senaryo | Beklenen |
|---|---|---|
| 1 | 2021001 / 123456 ile giriş | Öğrenci paneli |
| 2 | Yanlış şifre | "Şifre hatalı." |
| 3 | 9999999 ile giriş | "...kullanıcı hesabı bulunamadı." |
| 4 | 2021099 ile giriş | Profil bulunamadı mesajı (başka hata değil) |
| 5 | Öğrenci olarak /danisman/index.php adresini aç | "erişim yetkiniz yok" (403) |
| 6 | Danışman olarak /yonetim/index.php | 403 |
| 7 | D001: tümünü seç → toplu onayla → onay ekranı → uygula | Sonuç raporu: onaylanan/atlanan |
| 8 | Sekreter ile "Son Onay" butonu | Görünmez; POST ile denenirse 403 |
| 9 | Başvuru dönemini kapat | Öğrenci panelinde kapalı mesajı, düğme pasif |
| 10 | Form gönderiminde CSRF alanını sil (geliştirici araçları) | 419 hata |
| 11 | Veritabanı yedeğinden sonra migrate.php | Kayıtlar yerinde |

## Sürüm 3 iş akışı kontrol listesi (elle, tarayıcıda)
1. **Yedek alın**, `migrate_v3.php` çalıştırın. `php tests/calistir.php` tüm testleri geçmeli (iş akışı testleri yeni akışa göre güncellendi).
2. Eksik koşullu `2021002` ile başvuru → başvuru alınmalı, eksikler kırmızı uyarıyla görünmeli. `D001` onaylamaya çalışırken gerekçe boşsa hata vermeli.
3. Komisyon: `K1` ile liste üzerinden **toplu onay** → sonuç kartında başarılı/başarısız/işlem yapılmadı ayrı görünmeli; eksikli ve oy verilmiş kayıtlar atlanmalı; `K2`,`K3` ile 3. onayda karar kesinleşmeli, sonra oy düğmeleri kapanmalı.
4. Sekreter: ilişik kesme inceleme sayfasında **Önceki / Sonraki / Listeye dön**; "Engel var" + boş açıklama → hata; hepsi tamam + **Kaydet ve sonrakine geç** → öğrenci listeden çıkmalı, **tekrar düşmemeli**. Toplu tamamlama sonrası öğrenciler Belge Hazırlama listesinde olmalı.
5. Belge hazırlama (bireysel/toplu) → öğrenci sekreter listelerinden kaybolup Bilgi İşlem **Belge Onayları**'nda görünmeli. Düzeltme iste → notla sekreterliğe dönmeli.
6. Bilgi İşlem toplu belge onayı → öğrenciler Mezuniyet Listeleri'nde aday olmalı; **dönem açıkken** liste oluşturma kapalı olmalı.
7. Dönemi kapat → liste oluştur → liste ekranında hâlâ erişilebilir; **kapat** sonrası kapalı liste değişmemeli, iptal edilememeli; öğrenciler **E-İmza Süreci**'nde olmalı.
8. Toplu e-imza → belge hemen tamamlanmalı; öğrenci panelinde **Mezuniyet Belgem** görünmeli, PDF indirilebilmeli (Türkçe karakterler doğru); Bilgi İşlem **Belgeler** ekranından da indirilebilmeli. Başka öğrencinin belgesi adresle açılmamalı (403).
9. İşlem Geçmişi: durum geçişleri ve toplu işlem kayıtları (kim, ne zaman) görünmeli.
10. Mobil (< 980 px): yan menü ☰, tablolar yatay kaydırma, zaman çizelgesi dikey.
