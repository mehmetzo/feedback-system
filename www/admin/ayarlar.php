<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ayarlar.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();

if (($_SESSION['admin_kullanici'] ?? '') !== 'admin') {
    header('Location: /admin/dashboard.php');
    exit;
}

ldapIslemLog('Ayarlar sayfasi goruntulendi');

$tab   = $_GET['tab'] ?? 'hastane';
$mesaj = '';
$hata  = '';

// Logo sil
if (isset($_GET['logo_sil']) && $_GET['logo_sil'] === '1') {
    $logo_path = __DIR__ . '/../assets/logo.png';
    if (file_exists($logo_path)) unlink($logo_path);
    header('Location: /admin/ayarlar.php?tab=hastane&silindi=1');
    exit;
}

$logo_silindi = isset($_GET['silindi']) && $_GET['silindi'] === '1';
$logo_var     = file_exists(__DIR__ . '/../assets/logo.png');

// SMS test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sms_test'])) {
    $test_tel = preg_replace('/[^0-9]/', '', $_POST['test_telefon'] ?? '');
    if (strlen($test_tel) === 10) {
        $sonuc = smsSms($test_tel, 'Bu bir SMS test mesajidir.');
        if ($sonuc['basarili']) $mesaj = 'SMS gonderildi. ' . ($sonuc['mesaj'] ?? '');
        else $hata = $sonuc['mesaj'];
    } else {
        $hata = 'Gecerli bir telefon numarasi girin (10 haneli, basinda 0 olmadan).';
    }
    $tab = 'sms';
}

// Sifre degistir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['sms_test']) && ($_POST['grup'] ?? '') === 'sifre') {
    $mevcut = $_POST['mevcut_sifre'] ?? '';
    $yeni   = $_POST['yeni_sifre']   ?? '';
    $yeni2  = $_POST['yeni_sifre2']  ?? '';
    $db     = getDB();
    $stmt   = $db->prepare("SELECT sifre_hash FROM admin_kullanici WHERE kullanici_adi = 'admin'");
    $stmt->execute();
    $admin  = $stmt->fetch();
    if (!$admin || !password_verify($mevcut, $admin['sifre_hash'])) {
        $hata = 'Mevcut sifre hatali.';
    } elseif (strlen($yeni) < 4) {
        $hata = 'Yeni sifre en az 4 karakter olmalidir.';
    } elseif ($yeni !== $yeni2) {
        $hata = 'Yeni sifreler eslesmiyor.';
    } else {
        $hash = password_hash($yeni, PASSWORD_BCRYPT);
        $db->prepare("UPDATE admin_kullanici SET sifre_hash = ? WHERE kullanici_adi = 'admin'")->execute([$hash]);
        ldapIslemLog('Admin sifresi degistirildi');
        $mesaj = 'Sifre basariyla guncellendi.';
    }
    $tab = 'sifre';
}

// Diger ayarlari kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['sms_test']) && ($_POST['grup'] ?? '') !== 'sifre') {
    $grup   = $_POST['grup'] ?? '';
    $izinli = ['hastane', 'ldap', 'recaptcha', 'sms'];

    if (in_array($grup, $izinli)) {
        foreach ($_POST as $k => $v) {
            if (in_array($k, ['grup', 'csrf_token'])) continue;
            if (in_array($k, ['bind_pass', 'auth_pass', 'secret_key']) && $v === '') continue;
            if (in_array($k, ['bind_pass', 'auth_pass', 'secret_key', 'http_params', 'headers'])) {
                ayarKaydet($grup, $k, trim($v));
            } else {
                ayarKaydet($grup, $k, temizle($v));
            }
        }
        if ($grup === 'ldap'      && !isset($_POST['aktif']))            ayarKaydet('ldap',      'aktif',            '0');
        if ($grup === 'recaptcha' && !isset($_POST['aktif']))            ayarKaydet('recaptcha', 'aktif',            '0');
        if ($grup === 'sms'       && !isset($_POST['aktif']))            ayarKaydet('sms',       'aktif',            '0');
        if ($grup === 'sms'       && !isset($_POST['admin_not_gonder'])) ayarKaydet('sms',       'admin_not_gonder', '0');

        // Logo yukle
        if ($grup === 'hastane' && isset($_FILES['logo']) &&
            $_FILES['logo']['error'] === UPLOAD_ERR_OK &&
            $_FILES['logo']['size'] > 0) {
            $izinli_mime = ['image/png', 'image/jpeg', 'image/svg+xml'];
            $mime = mime_content_type($_FILES['logo']['tmp_name']);
            if (in_array($mime, $izinli_mime) && $_FILES['logo']['size'] <= 2 * 1024 * 1024) {
                $hedef = __DIR__ . '/../assets/logo.png';
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $hedef)) {
                    $logo_var = true;
                    $mesaj = 'Logo basariyla guncellendi.';
                } else {
                    $hata = 'Logo yuklenemedi. Klasor yazma izni kontrol edin.';
                }
            } else {
                $hata = 'Gecersiz logo. PNG, JPG veya SVG, max 2MB olmali.';
            }
        }

        if (empty($mesaj) && empty($hata)) {
            $mesaj = 'Ayarlar basariyla kaydedildi.';
        }
        ldapIslemLog('Ayarlar kaydedildi', $grup);
    }
    $tab = $grup;
}

$hastane = ayarGrupGetir('hastane');
$ldap    = ayarGrupGetir('ldap');
$recap   = ayarGrupGetir('recaptcha');
$sms     = ayarGrupGetir('sms');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ayarlar — Admin Paneli</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-sayfa-baslik">Ayarlar</h1>
      <p class="admin-sayfa-alt">Sistem yapilandirmasi</p>
    </div>
  </div>

  <?php if ($mesaj): ?>
  <div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px">
    ✅ <?= htmlspecialchars($mesaj) ?>
  </div>
  <?php endif; ?>
  <?php if ($hata): ?>
  <div style="background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px">
    ❌ <?= htmlspecialchars($hata) ?>
  </div>
  <?php endif; ?>

  <div class="ayar-tabs">
    <a href="?tab=hastane"   class="ayar-tab <?= $tab==='hastane'   ? 'aktif' : '' ?>">🏥 Hastane</a>
    <a href="?tab=ldap"      class="ayar-tab <?= $tab==='ldap'      ? 'aktif' : '' ?>">🔐 LDAP</a>
    <a href="?tab=recaptcha" class="ayar-tab <?= $tab==='recaptcha' ? 'aktif' : '' ?>">🤖 reCAPTCHA</a>
    <a href="?tab=sms"       class="ayar-tab <?= $tab==='sms'       ? 'aktif' : '' ?>">📱 SMS Server</a>
    <a href="?tab=sifre"     class="ayar-tab <?= $tab==='sifre'     ? 'aktif' : '' ?>">🔑 Şifre Değiştir</a>
  </div>

  <div class="ayar-kart">

    <?php if ($tab === 'hastane'): ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="grup" value="hastane">
      <div class="ayar-baslik">🏥 Hastane Bilgileri</div>
      <p class="ayar-aciklama">Bu bilgiler kullanıcı arayüzünde görüntülenir.</p>
      <div class="ayar-form-grup">
        <label>Kurum Adı</label>
        <input type="text" name="kurum" value="<?= htmlspecialchars($hastane['kurum'] ?? '') ?>" placeholder="Örn: XXX Bakanlığı">
      </div>
      <div class="ayar-form-grup">
        <label>Hastane Adı</label>
        <input type="text" name="adi" value="<?= htmlspecialchars($hastane['adi'] ?? '') ?>" placeholder="Örn: XXX Hastanesi">
      </div>
      <div class="ayar-form-grup">
        <label>Footer Metni</label>
        <input type="text" name="footer" value="<?= htmlspecialchars($hastane['footer'] ?? '') ?>" placeholder="Örn: Bilgi İşlem Daire Başkanlığı">
      </div>

      <!-- Logo -->
      <div class="ayar-form-grup">
        <label>Kurum Logosu</label>
        <?php if ($logo_silindi): ?>
        <div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:10px">✅ Logo kaldırıldı.</div>
        <?php endif; ?>
        <?php if ($logo_var): ?>
        <div style="margin-bottom:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <img src="/assets/logo.png?v=<?= time() ?>" alt="Mevcut Logo"
               style="height:50px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;padding:6px;background:#f7fafc">
          <a href="/admin/ayarlar.php?tab=hastane&logo_sil=1" class="admin-btn btn-danger"
             onclick="return confirm('Logoyu kaldırmak istediğinizden emin misiniz?')"
             style="font-size:12px;padding:6px 12px">🗑 Logoyu Kaldır</a>
        </div>
        <?php else: ?>
        <div style="font-size:12px;color:#718096;margin-bottom:8px">Henüz logo yüklenmemiş.</div>
        <?php endif; ?>
        <div onclick="document.getElementById('logoInput').click()"
             style="border:2px dashed #e2e8f0;border-radius:8px;padding:14px 16px;cursor:pointer;
                    display:flex;align-items:center;gap:10px;transition:border-color .2s"
             onmouseover="this.style.borderColor='#1a56a0'" onmouseout="this.style.borderColor='#e2e8f0'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a0aec0" stroke-width="1.5">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/>
          </svg>
          <span id="logoYazi" style="font-size:13px;color:#718096">PNG, JPG veya SVG seçin (max 2MB)</span>
        </div>
        <input type="file" id="logoInput" name="logo" accept="image/png,image/jpeg,image/svg+xml"
               style="display:none" onchange="logoOnizle(this)">
        <img id="logoOnizleme" src="" alt=""
             style="display:none;height:60px;margin-top:10px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;padding:6px">
      </div>
      <button type="submit" class="admin-btn btn-primary">💾 Kaydet</button>
    </form>

    <?php elseif ($tab === 'ldap'): ?>
    <form method="POST">
      <input type="hidden" name="grup" value="ldap">
      <div class="ayar-baslik">🔐 LDAP / Active Directory</div>
      <p class="ayar-aciklama">Admin paneli girişi için LDAP entegrasyonu.</p>
      <div class="ayar-form-grup">
        <label>LDAP Aktif</label>
        <label class="toggle-label">
          <input type="checkbox" name="aktif" value="1" <?= ($ldap['aktif'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
          LDAP ile kimlik doğrulama kullan
        </label>
      </div>
      <div class="ayar-form-grup">
        <label>LDAP Host (IP Adresi)</label>
        <input type="text" name="host" value="<?= htmlspecialchars($ldap['host'] ?? '') ?>" placeholder="Sunucu IP adresi">
      </div>
      <div class="ayar-form-grup">
        <label>Port</label>
        <input type="number" name="port" value="<?= htmlspecialchars($ldap['port'] ?? '389') ?>" placeholder="389">
      </div>
      <div class="ayar-form-grup">
        <label>Base DN</label>
        <input type="text" name="base_dn" value="<?= htmlspecialchars($ldap['base_dn'] ?? '') ?>" placeholder="dc=domain,dc=local">
      </div>
      <div class="ayar-form-grup">
        <label>Domain</label>
        <input type="text" name="domain" value="<?= htmlspecialchars($ldap['domain'] ?? '') ?>" placeholder="domain.local">
      </div>
      <div class="ayar-form-grup">
        <label>Servis Hesabı (Bind User)</label>
        <input type="text" name="bind_user" value="<?= htmlspecialchars($ldap['bind_user'] ?? '') ?>" placeholder="servis_hesabi">
      </div>
      <div class="ayar-form-grup">
        <label>Servis Hesabı Şifresi</label>
        <input type="password" name="bind_pass" placeholder="Değiştirmek için girin">
        <?php if (!empty($ldap['bind_pass'])): ?>
        <span style="font-size:12px;color:#38a169;margin-top:4px;display:block">✅ Şifre kayıtlı</span>
        <?php endif; ?>
      </div>
      <div class="ayar-form-grup">
        <label>Grup Adı</label>
        <input type="text" name="group" value="<?= htmlspecialchars($ldap['group'] ?? '') ?>" placeholder="grup_adi">
      </div>
      <button type="submit" class="admin-btn btn-primary">💾 Kaydet</button>
    </form>

    <?php elseif ($tab === 'recaptcha'): ?>
    <form method="POST">
      <input type="hidden" name="grup" value="recaptcha">
      <div class="ayar-baslik">🤖 Google reCAPTCHA v3</div>
      <p class="ayar-aciklama">
        Bot koruması için Google reCAPTCHA v3.
        <a href="https://www.google.com/recaptcha/admin" target="_blank" style="color:#1a56a0;text-decoration:none">Anahtar almak için tıklayın →</a>
      </p>
      <div class="ayar-form-grup">
        <label>reCAPTCHA Aktif</label>
        <label class="toggle-label">
          <input type="checkbox" name="aktif" value="1" <?= ($recap['aktif'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
          reCAPTCHA korumasını etkinleştir
        </label>
      </div>
      <div class="ayar-form-grup">
        <label>Site Key</label>
        <input type="text" name="site_key" value="<?= htmlspecialchars($recap['site_key'] ?? '') ?>" placeholder="6Lc...">
      </div>
      <div class="ayar-form-grup">
        <label>Secret Key</label>
        <input type="password" name="secret_key" placeholder="Değiştirmek için girin">
        <?php if (!empty($recap['secret_key'])): ?>
        <span style="font-size:12px;color:#38a169;margin-top:4px;display:block">✅ Secret key kayıtlı</span>
        <?php endif; ?>
      </div>
      <div class="ayar-form-grup">
        <label>Minimum Skor (0.0 - 1.0)</label>
        <input type="number" name="min_skor" step="0.1" min="0" max="1"
               value="<?= htmlspecialchars($recap['min_skor'] ?? '0.5') ?>" placeholder="0.5">
        <span style="font-size:12px;color:#718096;margin-top:4px;display:block">0.5 önerilir.</span>
      </div>
      <button type="submit" class="admin-btn btn-primary">💾 Kaydet</button>
    </form>

    <?php elseif ($tab === 'sms'): ?>
    <form method="POST" id="smsForm">
      <input type="hidden" name="grup" value="sms">
      <div class="ayar-baslik">📱 SMS Server</div>
      <p class="ayar-aciklama">
        Admin notu eklendiğinde telefon numarası olan kullanıcılara SMS gönderir.
        Parametrelerde <code style="background:#f7fafc;padding:2px 6px;border-radius:4px;font-size:12px">{telefon}</code>
        veya <code style="background:#f7fafc;padding:2px 6px;border-radius:4px;font-size:12px">$recipient</code>
        ve <code style="background:#f7fafc;padding:2px 6px;border-radius:4px;font-size:12px">{mesaj}</code>
        veya <code style="background:#f7fafc;padding:2px 6px;border-radius:4px;font-size:12px">$message</code> kullanabilirsiniz.
      </p>
      <div class="ayar-form-grup">
        <label>SMS Aktif</label>
        <label class="toggle-label">
          <input type="checkbox" name="aktif" value="1" <?= ($sms['aktif'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
          SMS servisini etkinleştir
        </label>
      </div>
      <div class="ayar-form-grup">
        <label>Admin Notu SMS Gönder</label>
        <label class="toggle-label">
          <input type="checkbox" name="admin_not_gonder" value="1" <?= ($sms['admin_not_gonder'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
          Admin notu eklenince telefonu olan kullanıcılara SMS at
        </label>
      </div>
      <div class="ayar-form-grup">
        <label>İstek Yöntemi</label>
        <div style="display:flex;gap:20px;margin-top:8px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:400">
            <input type="radio" name="http_method" value="GET" <?= ($sms['http_method'] ?? 'GET') === 'GET' ? 'checked' : '' ?>> GET
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:400">
            <input type="radio" name="http_method" value="POST" <?= ($sms['http_method'] ?? 'GET') === 'POST' ? 'checked' : '' ?>> POST
          </label>
        </div>
      </div>
      <div class="ayar-form-grup">
        <label>HTTP URL</label>
        <input type="text" name="http_url" value="<?= htmlspecialchars($sms['http_url'] ?? '') ?>" placeholder="https://sms.example.com/api/send">
      </div>
      <div class="ayar-form-grup">
        <label>HTTP Parameters</label>
        <textarea name="http_params" rows="3"
                  style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:13px;outline:none;resize:vertical"
                  placeholder="to={telefon}&message={mesaj}&from=HASTANE"><?= htmlspecialchars($sms['http_params'] ?? '') ?></textarea>
      </div>
      <div class="ayar-form-grup">
        <label>Request Headers <span style="font-weight:400;color:#718096">(her satıra bir header)</span></label>
        <textarea name="headers" rows="3"
                  style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:13px;outline:none;resize:vertical"
                  placeholder="Authorization: Bearer TOKEN"><?= htmlspecialchars($sms['headers'] ?? '') ?></textarea>
      </div>
      <div class="ayar-form-grup">
        <label>Kimlik Doğrulama</label>
        <div style="display:flex;gap:20px;margin-top:8px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:400">
            <input type="radio" name="auth_type" value="none" <?= ($sms['auth_type'] ?? 'none') === 'none' ? 'checked' : '' ?> onchange="authDegisti()"> No Authentication
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:400">
            <input type="radio" name="auth_type" value="basic" <?= ($sms['auth_type'] ?? 'none') === 'basic' ? 'checked' : '' ?> onchange="authDegisti()"> Basic Authentication
          </label>
        </div>
      </div>
      <div id="basicAuth" style="display:<?= ($sms['auth_type'] ?? 'none') === 'basic' ? 'block' : 'none' ?>;background:#f7fafc;border-radius:8px;padding:14px;margin-bottom:12px">
        <div class="ayar-form-grup" style="margin-bottom:10px">
          <label>Kullanıcı Adı</label>
          <input type="text" name="auth_user" value="<?= htmlspecialchars($sms['auth_user'] ?? '') ?>" placeholder="kullanici_adi">
        </div>
        <div class="ayar-form-grup" style="margin-bottom:0">
          <label>Şifre</label>
          <input type="password" name="auth_pass" placeholder="Değiştirmek için girin">
          <?php if (!empty($sms['auth_pass'])): ?>
          <span style="font-size:12px;color:#38a169;margin-top:4px;display:block">✅ Şifre kayıtlı</span>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" class="admin-btn btn-primary">💾 Kaydet</button>
        <button type="button" class="admin-btn btn-outline" onclick="document.getElementById('smsTestAlan').style.display='block'">📤 SMS Test</button>
      </div>
    </form>

    <div id="smsTestAlan" style="display:none;margin-top:20px;padding:16px;background:#f7fafc;border-radius:10px;border:1px solid #e2e8f0">
      <div style="font-weight:600;margin-bottom:12px;font-size:14px">📤 SMS Test Gönder</div>
      <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="grup" value="sms">
        <div style="flex:1;min-width:180px">
          <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
            Telefon <span style="font-weight:400;color:#718096">(10 haneli, başında 0 olmadan)</span>
          </label>
          <input type="text" name="test_telefon" placeholder="5XX XXX XX XX" maxlength="10"
                 inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                 style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:Inter,sans-serif;outline:none">
        </div>
        <button type="submit" name="sms_test" value="1" class="admin-btn btn-primary">Gönder</button>
      </form>
    </div>

    <?php elseif ($tab === 'sifre'): ?>
    <form method="POST">
      <input type="hidden" name="grup" value="sifre">
      <div class="ayar-baslik">🔑 Şifre Değiştir</div>
      <p class="ayar-aciklama">Admin hesabının giriş şifresini değiştirin.</p>
      <div class="ayar-form-grup">
        <label>Mevcut Şifre</label>
        <input type="password" name="mevcut_sifre" required placeholder="Mevcut şifreniz">
      </div>
      <div class="ayar-form-grup">
        <label>Yeni Şifre</label>
        <input type="password" name="yeni_sifre" required placeholder="En az 4 karakter">
      </div>
      <div class="ayar-form-grup">
        <label>Yeni Şifre Tekrar</label>
        <input type="password" name="yeni_sifre2" required placeholder="Yeni şifreyi tekrar girin">
      </div>
      <button type="submit" class="admin-btn btn-primary">🔑 Şifreyi Güncelle</button>
    </form>

    <?php endif; ?>
  </div>
</main>

<script>
function authDegisti() {
  var basic = document.querySelector('input[name="auth_type"][value="basic"]');
  document.getElementById('basicAuth').style.display = basic && basic.checked ? 'block' : 'none';
}
function logoOnizle(input) {
  if (!input.files || !input.files[0]) return;
  var dosya = input.files[0];
  var onizleme = document.getElementById('logoOnizleme');
  var yazi = document.getElementById('logoYazi');
  yazi.textContent = dosya.name;
  var reader = new FileReader();
  reader.onload = function(e) { onizleme.src = e.target.result; onizleme.style.display = 'block'; };
  reader.readAsDataURL(dosya);
}
</script>
</body>
</html>
