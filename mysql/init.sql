SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS hastane_feedback
  CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;

USE hastane_feedback;

CREATE TABLE geri_bildirim (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  tur               ENUM('sikayet','oneri') NOT NULL,
  ad_soyad          VARCHAR(100),
  telefon           VARCHAR(20),
  konu              VARCHAR(100) NOT NULL,
  aciklama          TEXT NOT NULL,
  gorsel_yol        VARCHAR(255) NULL,
  durum             ENUM('yeni','inceleniyor','cozuldu','kapatildi') DEFAULT 'yeni',
  ip_adresi         VARCHAR(45),
  tarayici          TEXT,
  admin_notu        TEXT,
  olusturma_tarihi  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE konu (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  tur   ENUM('sikayet','oneri') NOT NULL,
  ad    VARCHAR(100) NOT NULL,
  sira  INT DEFAULT 0,
  aktif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE admin_kullanici (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_adi    VARCHAR(50) NOT NULL UNIQUE,
  sifre_hash       VARCHAR(255) NOT NULL,
  ad_soyad         VARCHAR(100),
  email            VARCHAR(100),
  son_giris        TIMESTAMP NULL,
  aktif            TINYINT(1) DEFAULT 1,
  olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE ldap_log (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  kullanici_adi    VARCHAR(100) NOT NULL,
  islem            VARCHAR(255) NOT NULL,
  seviye           ENUM('info','warning','error') DEFAULT 'info',
  ip_adresi        VARCHAR(45),
  tarayici         TEXT,
  olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE ayarlar (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  grup              VARCHAR(50) NOT NULL,
  anahtar           VARCHAR(100) NOT NULL,
  deger             TEXT,
  guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY grup_anahtar (grup, anahtar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO konu (tur, ad, sira) VALUES
('sikayet', 'Personel Tutumu',               1),
('sikayet', 'Temizlik ve Hijyen',            2),
('sikayet', 'Bekleme Suresi',                3),
('sikayet', 'Hizmet Kalitesi',               4),
('sikayet', 'Yonlendirme ve Bilgilendirme',  5),
('sikayet', 'Fiziksel Kosullar',             6),
('sikayet', 'Gurultu ve Rahatsizlik',        7),
('sikayet', 'Fatura ve Odeme',               8),
('sikayet', 'Randevu Sistemi',               9),
('sikayet', 'Otopark ve Ulasim',             10),
('sikayet', 'Diger',                         99);

INSERT INTO konu (tur, ad, sira) VALUES
('oneri', 'Personel Tutumu',               1),
('oneri', 'Temizlik ve Hijyen',            2),
('oneri', 'Bekleme Suresi',                3),
('oneri', 'Hizmet Kalitesi',               4),
('oneri', 'Yonlendirme ve Bilgilendirme',  5),
('oneri', 'Fiziksel Kosullar',             6),
('oneri', 'Gurultu ve Rahatsizlik',        7),
('oneri', 'Fatura ve Odeme',               8),
('oneri', 'Randevu Sistemi',               9),
('oneri', 'Otopark ve Ulasim',             10),
('oneri', 'Diger',                         99);

INSERT INTO ayarlar (grup, anahtar, deger) VALUES
('hastane', 'adi',             ''),
('hastane', 'kurum',           ''),
('hastane', 'footer',          ''),
('ldap',    'aktif',           '0'),
('ldap',    'host',            ''),
('ldap',    'port',            '389'),
('ldap',    'base_dn',         ''),
('ldap',    'bind_user',       ''),
('ldap',    'bind_pass',       ''),
('ldap',    'group',           ''),
('ldap',    'domain',          ''),
('recaptcha', 'aktif',         '0'),
('recaptcha', 'site_key',      ''),
('recaptcha', 'secret_key',    ''),
('recaptcha', 'min_skor',      '0.5'),
('sms', 'aktif',               '0'),
('sms', 'http_method',         'GET'),
('sms', 'http_url',            ''),
('sms', 'http_params',         ''),
('sms', 'headers',             ''),
('sms', 'auth_type',           'none'),
('sms', 'auth_user',           ''),
('sms', 'auth_pass',           ''),
('sms', 'admin_not_gonder',    '0');

-- Admin kullanicisi (varsayilan sifre: admin — ilk giriste degistirin)


INSERT INTO admin_kullanici (kullanici_adi, sifre_hash, ad_soyad, email)
VALUES ('admin', '$2y$10$IN4kmnmA3AJaZFTI8h.Tr.N.ydYbwe8LsZ5UB0B0ZCS.qcxYoKE7a', 'Sistem Yoneticisi', '');
