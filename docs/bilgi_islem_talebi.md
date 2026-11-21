# Bilgi İşlem Daire Başkanlığına Talep Yazısı

**Konu:** Mezuniyet Komisyonu Otomasyonu için Web Servis ve Kimlik Doğrulama Talebi

Sayın Bilgi İşlem Daire Başkanlığı,

Bölümümüzde öğrenciler tarafından geliştirilmekte olan **Mezuniyet Komisyonu Otomasyonu**'nun üniversitemizin mevcut sistemleriyle uyumlu çalışabilmesi için aşağıdaki hususların netleştirilmesini ve ilgili teknik desteğin sağlanmasını talep ederiz.

## 1. İhtiyaç Duyulan Öğrenci Bilgileri
Aşağıdaki bilgilerin bir web servis (REST/SOAP) aracılığıyla alınması gerekmektedir:

| Alan | Açıklama |
|---|---|
| Öğrenci numarası | Benzersiz öğrenci kimliği |
| Ad ve soyad | |
| GANO | Genel akademik not ortalaması |
| Bölüm bilgisi | Öğrencinin kayıtlı olduğu bölüm |
| Danışman bilgisi | Öğrencinin danışmanının kullanıcı numarası/adı |

> İhtiyaç halinde: öğrenci danışmanlık otomasyonundaki "mezuniyete uygun mu?" (evet/hayır) bilgisinin de servis üzerinden sağlanması.

## 2. Netleştirilmesi İstenen Hususlar
1. Yukarıdaki bilgileri sağlayan **mevcut bir web servis bulunmakta mıdır?**
2. Böyle bir servis yoksa, ilgili bilgileri sağlayacak bir servisin **hazırlanması mümkün müdür?** Tahmini süre nedir?
3. Servisin **adresi (URL)**, **istek ve yanıt formatı** (JSON/XML), **kimlik doğrulama yöntemi** (API anahtarı, OAuth, Basic vb.) ve **erişim yetkileri** nelerdir?
4. Öğrenci numarası ve şifre ile giriş işleminin **mevcut üniversite kimlik doğrulama sistemi** (LDAP/SSO vb.) üzerinden yapılması mümkün müdür?
5. **Teknik dokümantasyon**, **test ortamı** ve **örnek servis yanıtları** paylaşılabilir mi?

## 3. Ek Talepler (Öğrenci Ekibimiz Tarafından)
- Servis erişimi için **IP kısıtı** varsa geliştirme sunucusunun tanımlanması.
- İstek sayısı sınırı (**rate limit**) bilgisi.
- Kişisel verilerin korunması (KVKK) kapsamında servis üzerinden alınabilecek verilerin sınırları.
- Servis kesintilerinde iletişim kurulacak **teknik irtibat kişisi**.

Gereğini arz ederiz.

**Bölüm Mezuniyet Komisyonu Otomasyonu Proje Ekibi**
Tarih: ___ / ___ / ______

## 4. Ek Veri Talepleri (Sürüm 2)
Uygunluk denetimi için servis üzerinden ayrıca şunlar gerekmektedir: tamamlanan ve gereken toplam AKTS; zorunlu ders listesi ve her dersin durumu (geçti/kaldı/alınmadı); birinci ve ikinci yaz stajı ile uzun dönem staj/İME onay durumları; bölümün tanımladığı ek mezuniyet koşulları; ilişik kesme birimlerinin listesi ve borç sorgu servisi; belge imzalama için yetkili e-imza servisi, kimlik bilgileri ve hukuki süreç tarifi.
