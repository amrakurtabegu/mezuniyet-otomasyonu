-- Mezuniyet Komisyonu Otomasyonu - veritabanı şeması (sürüm 2)
-- Tüm tablolar IF NOT EXISTS ile oluşturulur: bu dosya mevcut verileri SİLMEZ, tekrar içe aktarılabilir.
-- Eski (sürüm 1) veritabanını yükseltmek için: php database/migrate.php
CREATE DATABASE IF NOT EXISTS mezuniyet_otomasyonu CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE mezuniyet_otomasyonu;

CREATE TABLE IF NOT EXISTS kullanicilar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_no VARCHAR(20) NOT NULL,
  sifre_hash VARCHAR(255) NOT NULL,
  ad_soyad VARCHAR(100) NOT NULL,
  rol ENUM('ogrenci','danisman','komisyon','bolum_baskani','sekreter') NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  demo_hesap TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_kullanici_no (kullanici_no)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ogrenciler (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT NOT NULL,
  ogrenci_no VARCHAR(20) NOT NULL,
  ad_soyad VARCHAR(100) NOT NULL,
  gano DECIMAL(3,2) NULL,
  bolum VARCHAR(100) NULL,
  program VARCHAR(100) NULL,
  danisman_id INT NOT NULL,
  calisma_durumu VARCHAR(255) NULL,
  eposta VARCHAR(100) NULL,
  telefon VARCHAR(20) NULL,
  UNIQUE KEY uq_ogr_kullanici (kullanici_id),
  UNIQUE KEY uq_ogr_no (ogrenci_no),
  KEY idx_ogr_danisman (danisman_id),
  CONSTRAINT fk_ogr_kullanici FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
  CONSTRAINT fk_ogr_danisman FOREIGN KEY (danisman_id) REFERENCES kullanicilar(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS akademik_kayitlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_id INT NOT NULL,
  gano DECIMAL(3,2) NULL,
  tamamlanan_akts SMALLINT NULL,
  gereken_akts SMALLINT NULL,
  kaynak ENUM('mock','dogrulanmis') NOT NULL DEFAULT 'mock',
  guncelleme_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akademik_ogrenci (ogrenci_id),
  CONSTRAINT fk_akademik_ogrenci FOREIGN KEY (ogrenci_id) REFERENCES ogrenciler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staj_kayitlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_id INT NOT NULL,
  tur ENUM('yaz1','yaz2','ime') NOT NULL,
  durum ENUM('tamamlanmadi','devam_ediyor','tamamlandi_onay_bekliyor','onaylandi') NOT NULL DEFAULT 'tamamlanmadi',
  onay_tarihi DATE NULL,
  kaynak ENUM('mock','dogrulanmis') NOT NULL DEFAULT 'mock',
  UNIQUE KEY uq_staj (ogrenci_id, tur),
  CONSTRAINT fk_staj_ogrenci FOREIGN KEY (ogrenci_id) REFERENCES ogrenciler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ders_kayitlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_id INT NOT NULL,
  ders_kodu VARCHAR(20) NOT NULL,
  ders_adi VARCHAR(120) NOT NULL,
  akts TINYINT NULL,
  zorunlu TINYINT(1) NOT NULL DEFAULT 1,
  durum ENUM('gecti','kaldi','alinmadi') NOT NULL DEFAULT 'alinmadi',
  kaynak ENUM('mock','dogrulanmis') NOT NULL DEFAULT 'mock',
  UNIQUE KEY uq_ders (ogrenci_id, ders_kodu),
  CONSTRAINT fk_ders_ogrenci FOREIGN KEY (ogrenci_id) REFERENCES ogrenciler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ek_kosullar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_id INT NOT NULL,
  kosul_kodu VARCHAR(40) NOT NULL,
  ad VARCHAR(150) NOT NULL,
  durum ENUM('tamam','eksik','bekliyor') NOT NULL DEFAULT 'bekliyor',
  kaynak ENUM('mock','dogrulanmis') NOT NULL DEFAULT 'mock',
  UNIQUE KEY uq_ek_kosul (ogrenci_id, kosul_kodu),
  CONSTRAINT fk_ek_ogrenci FOREIGN KEY (ogrenci_id) REFERENCES ogrenciler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bolum_kurallari (
  kod VARCHAR(40) PRIMARY KEY,
  deger VARCHAR(100) NULL,
  aciklama VARCHAR(255) NULL,
  dogrulandi TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS basvuru_donemleri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  yil SMALLINT NOT NULL,
  ay TINYINT NOT NULL,
  donem TINYINT NOT NULL,
  baslangic DATE NOT NULL,
  bitis DATE NOT NULL,
  durum ENUM('acik','kapali') NOT NULL DEFAULT 'acik',
  UNIQUE KEY uq_donem (yil, ay, donem)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS oturumlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  planlanan_tarih DATE NOT NULL,
  durum ENUM('acik','kapali') NOT NULL DEFAULT 'acik',
  gercek_kapanis_tarihi DATE NULL,
  UNIQUE KEY uq_oturum_tarih (planlanan_tarih)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS basvurular (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_id INT NOT NULL,
  oturum_id INT NULL,
  donem_id INT NULL,
  basvuru_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  durum ENUM('danisman_bekliyor','iade','danisman_red','komisyon_bekliyor','komisyon_onay','komisyon_red',
             'iliski_kesme_tamam','son_onayli','listede','belge_hazirlaniyor','eimza_surecinde','tamamlandi')
        NOT NULL DEFAULT 'danisman_bekliyor',
  mezuniyete_uygun TINYINT(1) NOT NULL DEFAULT 0,
  son_onay_kullanici_id INT NULL,
  son_onay_tarihi DATETIME NULL,
  UNIQUE KEY uq_basvuru_ogr_donem (ogrenci_id, donem_id),
  KEY idx_basvuru_durum (durum),
  CONSTRAINT fk_basvuru_ogrenci FOREIGN KEY (ogrenci_id) REFERENCES ogrenciler(id),
  CONSTRAINT fk_basvuru_oturum FOREIGN KEY (oturum_id) REFERENCES oturumlar(id),
  CONSTRAINT fk_basvuru_donem FOREIGN KEY (donem_id) REFERENCES basvuru_donemleri(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS basvuru_uygunluk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  kosul_kodu VARCHAR(40) NOT NULL,
  durum ENUM('tamam','eksik','belirsiz') NOT NULL,
  mesaj VARCHAR(500) NULL,
  kontrol_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_uygunluk (basvuru_id, kosul_kodu),
  CONSTRAINT fk_uygunluk_basvuru FOREIGN KEY (basvuru_id) REFERENCES basvurular(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS basvuru_gecmisi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  eski_durum VARCHAR(30) NULL,
  yeni_durum VARCHAR(30) NOT NULL,
  kullanici_id INT NULL,
  aciklama VARCHAR(500) NULL,
  tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gecmis_basvuru (basvuru_id),
  CONSTRAINT fk_gecmis_basvuru FOREIGN KEY (basvuru_id) REFERENCES basvurular(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS toplu_islemler (
  id INT AUTO_INCREMENT PRIMARY KEY,
  danisman_id INT NOT NULL,
  tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  secilen_sayisi INT NOT NULL DEFAULT 0,
  onaylanan_sayisi INT NOT NULL DEFAULT 0,
  atlanan_sayisi INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_toplu_danisman FOREIGN KEY (danisman_id) REFERENCES kullanicilar(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS danisman_kararlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  danisman_id INT NOT NULL,
  karar ENUM('onay','iade','red') NOT NULL,
  gerekce TEXT NULL,
  karar_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  toplu_islem_id INT NULL,
  KEY idx_dk_basvuru (basvuru_id),
  CONSTRAINT fk_dk_basvuru FOREIGN KEY (basvuru_id) REFERENCES basvurular(id),
  CONSTRAINT fk_dk_danisman FOREIGN KEY (danisman_id) REFERENCES kullanicilar(id),
  CONSTRAINT fk_dk_toplu FOREIGN KEY (toplu_islem_id) REFERENCES toplu_islemler(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS komisyon_kararlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  uye_id INT NOT NULL,
  karar ENUM('onay','red') NOT NULL,
  aciklama TEXT NULL,
  karar_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oy (basvuru_id, uye_id),
  CONSTRAINT fk_kk_basvuru FOREIGN KEY (basvuru_id) REFERENCES basvurular(id),
  CONSTRAINT fk_kk_uye FOREIGN KEY (uye_id) REFERENCES kullanicilar(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS iliski_kesme_kontrolleri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  birim ENUM('kutuphane','diger_birimler') NOT NULL,
  durum ENUM('bekliyor','tamam','engel') NOT NULL DEFAULT 'bekliyor',
  aciklama VARCHAR(255) NULL,
  kontrol_eden_id INT NULL,
  kontrol_tarihi DATETIME NULL,
  UNIQUE KEY uq_iliski (basvuru_id, birim),
  CONSTRAINT fk_iliski_basvuru FOREIGN KEY (basvuru_id) REFERENCES basvurular(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mezuniyet_listeleri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  oturum_id INT NOT NULL,
  olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erken_olusturuldu TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_liste_oturum (oturum_id),
  CONSTRAINT fk_liste_oturum FOREIGN KEY (oturum_id) REFERENCES oturumlar(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mezunlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_no VARCHAR(20) NOT NULL,
  ad_soyad VARCHAR(100) NOT NULL,
  bolum VARCHAR(100) NULL,
  gano DECIMAL(3,2) NULL,
  calisma_durumu VARCHAR(255) NULL,
  eposta VARCHAR(100) NULL,
  telefon VARCHAR(20) NULL,
  liste_id INT NOT NULL,
  oturum_id INT NOT NULL,
  mezuniyet_tarihi DATE NOT NULL,
  UNIQUE KEY uq_mezun_no (ogrenci_no),
  CONSTRAINT fk_mezun_liste FOREIGN KEY (liste_id) REFERENCES mezuniyet_listeleri(id),
  CONSTRAINT fk_mezun_oturum FOREIGN KEY (oturum_id) REFERENCES oturumlar(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mezuniyet_belgeleri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  belge_no VARCHAR(40) NOT NULL,
  hazirlayan_id INT NULL,
  hazirlama_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bilgi_islem_gonderim_tarihi DATETIME NULL,
  eimza_durumu ENUM('beklemede','simule_edildi','imzalandi') NOT NULL DEFAULT 'beklemede',
  eimza_tarihi DATETIME NULL,
  eimza_yetkili_id INT NULL,
  teslim_tarihi DATE NULL,
  teslim_yontemi ENUM('elektronik','basili') NULL,
  teslim_kaydeden_id INT NULL,
  UNIQUE KEY uq_belge_basvuru (basvuru_id),
  CONSTRAINT fk_belge_basvuru FOREIGN KEY (basvuru_id) REFERENCES basvurular(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS islem_loglari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NULL,
  kullanici_id INT NULL,
  islem VARCHAR(255) NOT NULL,
  tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_basvuru (basvuru_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO bolum_kurallari (kod, deger, aciklama, dogrulandi) VALUES
  ('GANO_MIN', '2.50', 'Mezuniyet için en az GANO (proje isterlerinde belirtilen kural)', 1),
  ('TOPLAM_AKTS', NULL, 'Mezuniyet için gereken toplam AKTS: BÖLÜMDEN TEYİT EDİLMELİ. Boşsa öğrencinin akademik kaydındaki gereken_akts alanı kullanılır', 0),
  ('BASVURU_POLITIKASI', 'engelle', 'engelle: zorunlu koşulu eksik öğrenci başvuramaz (varsayılan). izin_ver: başvuru alınır ancak danışman onaylayamaz', 1);
