<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/ayarlar.php';
sessionBaslat();

$tur = in_array($_GET['tur'] ?? '', ['sikayet', 'oneri']) ? $_GET['tur'] : 'sikayet';

$db = getDB();
$konular_stmt = $db->prepare("SELECT * FROM konu WHERE tur = ? AND aktif = 1 ORDER BY sira ASC");
$konular_stmt->execute([$tur]);
$konular = $konular_stmt->fetchAll();

$baslik    = $tur === 'sikayet' ? 'Şikayet Bildirimi' : 'Öneri Bildirimi';
$alt_baslik = $tur === 'sikayet' ? 'Yaşadığınız sorunu bizimle paylaşın' : 'Önerinizi bizimle paylaşın';
$renk_cls  = $tur === 'sikayet' ? 'sikayet' : 'oneri';
$csrf      = csrfToken();

// Ayarlardan bilgileri çek
$hastane_adi   = ayarGetir('hastane', 'adi',   'Hastane');
$recaptcha_aktif    = ayarGetir('recaptcha', 'aktif', '0') === '1';
$recaptcha_site_key = ayarGetir('recaptcha', 'site_key', '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#1a56a0">
<title><?= htmlspecialchars($baslik) ?> — <?= htmlspecialchars($hastane_adi) ?></title>
<link rel="stylesheet" href="/assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if ($recaptcha_aktif && $recaptcha_site_key): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptcha_site_key) ?>"></script>
<?php endif; ?>
</head>
<body class="form-page">
<div class="page-wrapper">

  <!-- Header -->
  <header class="site-header">
    <div class="header-inner">
      <a href="/" class="geri-btn" title="Geri">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <polyline points="15,18 9,12 15,6"/>
        </svg>
      </a>
      <img src="/assets/logo.png" alt="Logo" class="site-logo-sm"
           onerror="this.style.display='none'">
      <span class="header-hastane-sm"><?= htmlspecialchars($hastane_adi) ?></span>
    </div>
  </header>

  <!-- Form baslik bandi -->
  <div class="form-banner <?= $renk_cls ?>-banner">
    <div class="banner-ikon-banner">
      <?php if ($tur === 'sikayet'): ?>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        <line x1="12" y1="9"  x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <?php else: ?>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8"  x2="12" y2="16"/>
        <line x1="8"  y1="12" x2="16" y2="12"/>
      </svg>
      <?php endif; ?>
    </div>
    <div>
      <div class="banner-baslik"><?= htmlspecialchars($baslik) ?></div>
      <div class="banner-bolge"><?= htmlspecialchars($alt_baslik) ?></div>
    </div>
  </div>

  <!-- Form -->
  <main class="form-main">

    <?php if (!empty($_SESSION['form_hata'])): ?>
    <div class="alert alert-danger">
      <?php foreach ($_SESSION['form_hata'] as $h): ?>
        <div><?= htmlspecialchars($h) ?></div>
      <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['form_hata']); ?>
    <?php endif; ?>

    <form id="bildirimForm" action="/submit.php" method="POST"
          enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="tur" value="<?= $tur ?>">
      <input type="hidden" name="recaptcha_token" id="recaptchaToken">

      <!-- Ad Soyad -->
      <div class="form-grup">
        <label class="form-label" for="ad_soyad">
          Ad Soyad
          <span class="opsiyonel-badge">İsteğe Bağlı</span>
        </label>
        <div class="input-wrapper">
          <svg class="input-ikon" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <input type="text" id="ad_soyad" name="ad_soyad" class="form-input"
                 placeholder="Adınız ve soyadınız" maxlength="100" autocomplete="name">
        </div>
      </div>

      <!-- Telefon -->
      <div class="form-grup">
        <label class="form-label" for="telefon">
          Telefon
          <span class="opsiyonel-badge">İsteğe Bağlı</span>
        </label>
        <p style="font-size:11px;color:var(--text);font-weight:500;margin-bottom:5px;line-height:1.4">
          Bildiriminizle ilgili tarafınıza geri dönüş yapılabilmesi için telefon numaranızı girebilirsiniz.
        </p>
        <div class="input-wrapper">
          <svg class="input-ikon" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 16.92v3a2 2 0 01-2.18 2A19.79 19.79 0 013.08 4.18 2 2 0 015 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L9.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
          </svg>
          <input type="tel" id="telefon" name="telefon" class="form-input"
                 placeholder="5XX XXX XX XX" maxlength="10"
                 inputmode="numeric" pattern="[0-9]*"
                 oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        </div>
      </div>

      <!-- Gorsel Yukle -->
      <div class="form-grup">
        <label class="form-label" for="gorsel">
          Görsel
          <span class="opsiyonel-badge">İsteğe Bağlı</span>
        </label>
        <div class="dosya-yukle-alan" id="dosyaAlan"
             onclick="document.getElementById('gorsel').click()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="1.5">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21,15 16,10 5,21"/>
          </svg>
          <span class="dosya-yukle-yazi">Görsel seçmek için tıklayın</span>
          <span class="dosya-yukle-alt">JPG, PNG, WEBP — Max 5MB</span>
          <img id="onizleme" src="" alt=""
               style="display:none;max-width:100%;max-height:150px;
                      border-radius:8px;margin-top:8px">
        </div>
        <input type="file" id="gorsel" name="gorsel"
               accept="image/jpeg,image/png,image/webp"
               style="display:none" onchange="gorselOnizle(this)">
        <div class="hata-mesaj" id="gorsel-hata"></div>
      </div>

      <!-- Konu -->
      <div class="form-grup">
        <label class="form-label" for="konu">
          Konu
          <span class="zorunlu-badge">Zorunlu</span>
        </label>
        <div class="input-wrapper select-wrapper">
          <svg class="input-ikon" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
            <rect x="9" y="3" width="6" height="4" rx="2"/>
          </svg>
          <select id="konu" name="konu" class="form-input form-select" required>
            <option value="">-- Konu Seçiniz --</option>
            <?php foreach ($konular as $k): ?>
            <option value="<?= htmlspecialchars($k['ad']) ?>">
              <?= htmlspecialchars($k['ad']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <svg class="select-ok" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6,9 12,15 18,9"/>
          </svg>
        </div>
        <div class="hata-mesaj" id="konu-hata"></div>
      </div>

      <!-- Aciklama -->
      <div class="form-grup">
        <label class="form-label" for="aciklama">
          Açıklama
          <span class="zorunlu-badge">Zorunlu</span>
        </label>
        <div class="textarea-wrapper">
          <textarea id="aciklama" name="aciklama" class="form-textarea" rows="4"
            placeholder="<?= $tur === 'sikayet'
              ? 'Yaşadığınız sorunu detaylı olarak açıklayınız...'
              : 'Önerinizi detaylı olarak açıklayınız...' ?>"
            required maxlength="2000"></textarea>
          <div class="karakter-sayac">
            <span id="karSayac">0</span>/2000
          </div>
        </div>
        <div class="hata-mesaj" id="aciklama-hata"></div>
      </div>

      <!-- Gonder -->
      <button type="submit" class="gonder-btn <?= $renk_cls ?>-btn" id="gonderBtn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22,2 15,22 11,13 2,9"/>
        </svg>
        <span>Gönder</span>
      </button>
    </form>

  </main>
</div>

<script>
var RECAPTCHA_SITE_KEY = '<?= $recaptcha_aktif ? htmlspecialchars($recaptcha_site_key) : '' ?>';

function gorselOnizle(input) {
  var hata     = document.getElementById('gorsel-hata');
  var onizleme = document.getElementById('onizleme');
  var yazi     = document.querySelector('.dosya-yukle-yazi');
  hata.textContent = '';
  if (!input.files || !input.files[0]) return;
  var dosya = input.files[0];
  if (dosya.size > 5 * 1024 * 1024) {
    hata.textContent = 'Dosya boyutu 5MB\'dan büyük olamaz.';
    input.value = '';
    return;
  }
  var izinli = ['image/jpeg','image/png','image/webp'];
  if (izinli.indexOf(dosya.type) === -1) {
    hata.textContent = 'Sadece JPG, PNG veya WEBP yükleyebilirsiniz.';
    input.value = '';
    return;
  }
  var reader = new FileReader();
  reader.onload = function(e) {
    onizleme.src = e.target.result;
    onizleme.style.display = 'block';
    yazi.textContent = dosya.name;
  };
  reader.readAsDataURL(dosya);
}

var aclm  = document.getElementById('aciklama');
var sayac = document.getElementById('karSayac');
aclm.addEventListener('input', function() {
  sayac.textContent = aclm.value.length;
});

document.getElementById('bildirimForm').addEventListener('submit', function(e) {
  var gecerli = true;
  document.querySelectorAll('.hata-mesaj').forEach(function(el) { el.textContent = ''; });

  if (!document.getElementById('konu').value) {
    document.getElementById('konu-hata').textContent = 'Lütfen bir konu seçiniz.';
    gecerli = false;
  }
  if (aclm.value.trim().length < 10) {
    document.getElementById('aciklama-hata').textContent = 'Açıklama en az 10 karakter olmalıdır.';
    gecerli = false;
  }
  if (!gecerli) { e.preventDefault(); return; }

  var btn = document.getElementById('gonderBtn');
  btn.disabled = true;
  btn.innerHTML = '<svg class="spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0"/></svg><span>Gönderiliyor...</span>';

  if (RECAPTCHA_SITE_KEY) {
    e.preventDefault();
    grecaptcha.ready(function() {
      grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' }).then(function(token) {
        document.getElementById('recaptchaToken').value = token;
        document.getElementById('bildirimForm').submit();
      });
    });
  }
});
</script>
</body>
</html>
