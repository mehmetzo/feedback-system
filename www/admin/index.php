<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ayarlar.php';
require_once __DIR__ . '/../config/ldap.php';
sessionBaslat();

if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Oturum süresi mesajı
$oturum_mesaj = '';
if (isset($_GET['oturum']) && $_GET['oturum'] === 'suredi') {
    $oturum_mesaj = 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.';
}

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici = temizle($_POST['kullanici'] ?? '');
    $sifre     = $_POST['sifre'] ?? '';

    if ($kullanici && $sifre) {
        // Önce admin kullanıcısı mı kontrol et
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM admin_kullanici WHERE kullanici_adi = ? AND aktif = 1");
        $stmt->execute([$kullanici]);
        $admin = $stmt->fetch();

        if ($admin && $admin['sifre_hash'] !== 'LDAP' && password_verify($sifre, $admin['sifre_hash'])) {
            // Yerel admin girişi
            $_SESSION['admin_id']          = $admin['id'];
            $_SESSION['admin_ad']          = $admin['ad_soyad'];
            $_SESSION['admin_kullanici']   = $admin['kullanici_adi'];
            $_SESSION['admin_giris_zaman'] = time();
            $db->prepare("UPDATE admin_kullanici SET son_giris = NOW() WHERE id = ?")
               ->execute([$admin['id']]);
            header('Location: /admin/dashboard.php');
            exit;
        }

        // LDAP aktif mi kontrol et
        $ldap_aktif = ayarGetir('ldap', 'aktif', '0') === '1';
        if ($ldap_aktif) {
            $sonuc = ldapKullaniciDogrula($kullanici, $sifre);
            if ($sonuc['basarili']) {
                $_SESSION['admin_id']          = $kullanici;
                $_SESSION['admin_ad']          = $sonuc['ad_soyad'];
                $_SESSION['admin_kullanici']   = $kullanici;
                $_SESSION['admin_email']       = $sonuc['email'];
                $_SESSION['admin_giris_zaman'] = time();

                // DB'ye kaydet
                $mevcut = $db->prepare("SELECT id FROM admin_kullanici WHERE kullanici_adi = ?");
                $mevcut->execute([$kullanici]);
                if ($mevcut->fetch()) {
                    $db->prepare("UPDATE admin_kullanici SET son_giris = NOW() WHERE kullanici_adi = ?")
                       ->execute([$kullanici]);
                } else {
                    $db->prepare("INSERT INTO admin_kullanici (kullanici_adi, sifre_hash, ad_soyad, email, son_giris) VALUES (?, 'LDAP', ?, ?, NOW())")
                       ->execute([$kullanici, $sonuc['ad_soyad'], $sonuc['email']]);
                }
                header('Location: /admin/dashboard.php');
                exit;
            }
            $hata = $sonuc['hata'];
        } else {
            $hata = 'Kullanıcı adı veya şifre hatalı.';
        }
    } else {
        $hata = 'Kullanıcı adı ve şifre gereklidir.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Girişi — Geri Bildirim Sistemi</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-login-page">
<div class="login-wrapper">
  <div class="login-kart">
    <div class="login-logo">
      <img src="/assets/logo.png" alt="Logo" onerror="this.style.display='none'">
      <h2>Yönetim Paneli</h2>
      <p>Geri Bildirim Sistemi</p>
    </div>

    <?php if ($oturum_mesaj): ?>
    <div class="alert" style="background:#fffbeb;border:1px solid #f6e05e;color:#b7791f;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px">
      <?= htmlspecialchars($oturum_mesaj) ?>
    </div>
    <?php endif; ?>

    <?php if ($hata): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($hata) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-grup">
        <label>Kullanıcı Adı</label>
        <input type="text" name="kullanici" required autofocus
               placeholder="Kullanıcı adınız"
               value="<?= htmlspecialchars($_POST['kullanici'] ?? '') ?>">
      </div>
      <div class="form-grup">
        <label>Şifre</label>
        <input type="password" name="sifre" required placeholder="••••••••">
      </div>
      <button type="submit" class="login-btn">Giriş Yap</button>
    </form>
  </div>
</div>
</body>
</html>
