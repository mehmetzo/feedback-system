# 🏥 Hastane Dijital Geri Bildirim Sistemi

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=for-the-badge&logo=apache&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![KVKK](https://img.shields.io/badge/KVKK-Uyumlu-green?style=for-the-badge)

**Geleneksel öneri kutularının yerini alan, QR kod tabanlı modern hasta geri bildirim yönetim sistemi.**

</div>

---

## 📋 İçindekiler

- [Özellikler](#-özellikler)
- [Gereksinimler](#-gereksinimler)
- [Kurulum](#-kurulum)
- [Güvenlik](#-güvenlik)
- [Proje Yapısı](#-proje-yapısı)

---

## ✨ Özellikler

### 👤 Kullanıcı Tarafı
- 📱 **QR Kod ile Erişim** — Uygulama indirmeye gerek yok, kamera ile QR okutunca form açılır
- 🔴 **Şikayet Bildirimi** — Konu seçimi, açıklama ve isteğe bağlı görsel yükleme
- 🟢 **Öneri Bildirimi** — Aynı kategorilerle hızlı öneri iletimi
- 📎 **Görsel Ekleme** — JPG, PNG, WEBP formatında kanıt fotoğrafı yükleme (max 5MB)
- ✅ **Teşekkür Ekranı** — Animasyonlu onay ve otomatik yönlendirme

### 🖥️ Admin Paneli
- 📊 **Dashboard** — Gerçek zamanlı istatistikler ve Chart.js grafikleri
- 📋 **Bildirim Yönetimi** — Filtreleme, arama, durum güncelleme
- 🔄 **Durum Takibi** — Yeni → İnceleniyor → Çözüldü → Kapatıldı
- 💬 **Admin Notu** — Bildirimlere not ekleme ve SMS ile hasta bilgilendirme
- 📤 **Dışa Aktarma** — CSV ve PDF formatında rapor çıktısı
- 📜 **Erişim Logları** — Tüm kullanıcı işlemlerinin kayıt altına alınması

### ⚙️ Sistem Ayarları *(Admin Panelinden)*
- 🏥 **Hastane Bilgileri** — Kurum adı, logo, footer metni
- 🔐 **LDAP / Active Directory** — Kurumsal hesaplarla giriş
- 🤖 **Google reCAPTCHA v3** — Bot koruması
- 📱 **SMS Sunucusu** — HTTP/GET/POST, Basic Auth desteği, test özelliği

---

## 📦 Gereksinimler

- [Docker](https://docs.docker.com/get-docker/) 20.10+
- [Docker Compose](https://docs.docker.com/compose/install/) v2+
- 512MB RAM (minimum)

---

## 🚀 Kurulum

### 1. Repoyu klonlayın

```bash
git clone https://github.com/mehmetzo/feedback-system.git
cd feedback-system
```

### 2. Klasör izinlerini ayarlayın

```bash
chmod 777 www/uploads
chmod 777 www/assets
```

### 3. Admin şifresi oluşturun

```bash
#container'ı başlatın
docker compose up -d --build

```

## 🌐 Erişim Adresleri

| Sayfa | URL |
|-------|-----|
| 👤 Kullanıcı Formu | `http://SUNUCU_IP:9990/` |
| 🔧 Admin Paneli | `http://SUNUCU_IP:9990/admin/` |
| 🔲 Veritabanı (dış) | `SUNUCU_IP:3308` |


QR kodu hastane bölümlerine asın. Hastalar telefon kamerasıyla okutarak forma ulaşır.

```
### Hasta Akışı
QR Okut → Şikayet/Öneri Seç → Formu Doldur → Gönder → Teşekkür
```

## ⚙️ Ayarlar

Tüm sistem ayarları **Admin Paneli → Ayarlar** üzerinden yapılır, herhangi bir dosya düzenlemeye gerek yok.

```
### 🔐 LDAP Ayarları
- Host     : Active Directory sunucu IP
- Port     : 389
- Base DN  : dc=domain,dc=local
- Domain   : domain.local
- Bind User: servis_hesabi
- Grup     : yetkili_grup
```

### 🤖 reCAPTCHA Ayarları
[Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin) üzerinden v3 anahtarı alın.


```
### 📱 SMS Ayarları
- Yöntem  : GET veya POST
- URL     : https://sms-provider.com/api/send
- Params  : to={telefon}&message={mesaj}&...
- Auth    : None veya Basic Authentication
```



