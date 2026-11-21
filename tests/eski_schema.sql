CREATE DATABASE IF NOT EXISTS mezuniyet_otomasyonu CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE mezuniyet_otomasyonu;

CREATE TABLE kullanicilar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_no VARCHAR(20) UNIQUE NOT NULL,
  sifre_hash VARCHAR(255) NOT NULL,
  ad_soyad VARCHAR(100) NOT NULL,
  rol ENUM('ogrenci','danisman','komisyon','bolum_baskani','sekreter') NOT NULL
);

CREATE TABLE ogrenciler (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_id INT UNIQUE NOT NULL,
  ogrenci_no VARCHAR(20) UNIQUE NOT NULL,
  ad_soyad VARCHAR(100) NOT NULL,
  gano DECIMAL(3,2),
  bolum VARCHAR(100),
  danisman_id INT NOT NULL,
  calisma_durumu VARCHAR(255) NULL,
  eposta VARCHAR(100) NULL,
  telefon VARCHAR(20) NULL,
  FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
  FOREIGN KEY (danisman_id) REFERENCES kullanicilar(id)
);

CREATE TABLE oturumlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  planlanan_tarih DATE NOT NULL UNIQUE,
  durum ENUM('acik','kapali') NOT NULL DEFAULT 'acik',
  gercek_kapanis_tarihi DATE NULL
);

CREATE TABLE basvurular (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_id INT NOT NULL,
  oturum_id INT NOT NULL,
  basvuru_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
  durum ENUM('danisman_bekliyor','iade','danisman_red','komisyon_bekliyor',
             'komisyon_onay','komisyon_red','listede') NOT NULL DEFAULT 'danisman_bekliyor',
  mezuniyete_uygun TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (ogrenci_id) REFERENCES ogrenciler(id),
  FOREIGN KEY (oturum_id) REFERENCES oturumlar(id)
);

CREATE TABLE danisman_kararlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  danisman_id INT NOT NULL,
  karar ENUM('onay','iade','red') NOT NULL,
  gerekce TEXT NULL,
  karar_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (basvuru_id) REFERENCES basvurular(id),
  FOREIGN KEY (danisman_id) REFERENCES kullanicilar(id)
);

CREATE TABLE komisyon_kararlari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NOT NULL,
  uye_id INT NOT NULL,
  karar ENUM('onay','red') NOT NULL,
  aciklama TEXT NULL,
  karar_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (basvuru_id, uye_id),
  FOREIGN KEY (basvuru_id) REFERENCES basvurular(id),
  FOREIGN KEY (uye_id) REFERENCES kullanicilar(id)
);

CREATE TABLE mezuniyet_listeleri (
  id INT AUTO_INCREMENT PRIMARY KEY,
  oturum_id INT NOT NULL UNIQUE,
  olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
  erken_olusturuldu TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (oturum_id) REFERENCES oturumlar(id)
);

CREATE TABLE mezunlar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ogrenci_no VARCHAR(20) UNIQUE NOT NULL,  -- aynı öğrenci iki kez mezun olamaz
  ad_soyad VARCHAR(100) NOT NULL,
  bolum VARCHAR(100),
  gano DECIMAL(3,2),
  calisma_durumu VARCHAR(255),
  eposta VARCHAR(100),
  telefon VARCHAR(20),
  liste_id INT NOT NULL,
  oturum_id INT NOT NULL,
  mezuniyet_tarihi DATE NOT NULL,
  FOREIGN KEY (liste_id) REFERENCES mezuniyet_listeleri(id),
  FOREIGN KEY (oturum_id) REFERENCES oturumlar(id)
);

CREATE TABLE islem_loglari (
  id INT AUTO_INCREMENT PRIMARY KEY,
  basvuru_id INT NULL,
  kullanici_id INT NULL,
  islem VARCHAR(255) NOT NULL,
  tarih DATETIME DEFAULT CURRENT_TIMESTAMP
);
