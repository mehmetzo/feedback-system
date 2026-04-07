<?php
$current  = basename($_SERVER['PHP_SELF']);
$tur_get  = $_GET['tur'] ?? '';
$is_admin = ($_SESSION['admin_kullanici'] ?? '') === 'admin';
?>

<!-- Mobil topbar -->
<div class="mobil-topbar">
  <button class="hamburger" onclick="sidebarAc()" aria-label="Menü">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="3" y1="6"  x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>
  <span class="mobil-topbar-baslik">Geri Bildirim Yönetimi</span>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="sidebarKapat()"></div>

<aside class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-logo">
    <img src="/assets/logo.png" alt="Logo" onerror="this.style.display='none'">
    <h3>Geri Bildirim Yönetimi</h3>
  </div>

  <nav class="sidebar-nav">
    <a href="/admin/dashboard.php"
       class="<?= $current==='dashboard.php' ? 'aktif' : '' ?>">
      📊 Dashboard
    </a>
    <a href="/admin/list.php"
       class="<?= ($current==='list.php' && $tur_get==='') ? 'aktif' : '' ?>">
      📋 Tüm Bildirimler
    </a>
    <a href="/admin/list.php?tur=sikayet"
       class="<?= ($current==='list.php' && $tur_get==='sikayet') ? 'aktif' : '' ?>">
      🔴 Şikayetler
    </a>
    <a href="/admin/list.php?tur=oneri"
       class="<?= ($current==='list.php' && $tur_get==='oneri') ? 'aktif' : '' ?>">
      🟢 Öneriler
    </a>

    <?php if ($is_admin): ?>
    <div class="nav-ayrac">Yönetim</div>
    <a href="/admin/export_page.php"
       class="<?= $current==='export_page.php' ? 'aktif' : '' ?>">
      ⬇ Dışa Aktar
    </a>
    <a href="/admin/logs.php"
       class="<?= $current==='logs.php' ? 'aktif' : '' ?>">
      📜 Erişim Logları
    </a>
    <div class="nav-ayrac">Ayarlar</div>
    <a href="/admin/ayarlar.php?tab=hastane"
       class="<?= ($current==='ayarlar.php' && ($_GET['tab']??'')==='hastane') ? 'aktif' : '' ?>">
      🏥 Hastane
    </a>
    <a href="/admin/ayarlar.php?tab=ldap"
       class="<?= ($current==='ayarlar.php' && ($_GET['tab']??'')==='ldap') ? 'aktif' : '' ?>">
      🔐 LDAP
    </a>
    <a href="/admin/ayarlar.php?tab=recaptcha"
       class="<?= ($current==='ayarlar.php' && ($_GET['tab']??'')==='recaptcha') ? 'aktif' : '' ?>">
      🤖 reCAPTCHA
    </a>
    <a href="/admin/ayarlar.php?tab=sms"
       class="<?= ($current==='ayarlar.php' && ($_GET['tab']??'')==='sms') ? 'aktif' : '' ?>">
      📱 SMS Server
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      👤 <?= htmlspecialchars($_SESSION['admin_ad'] ?? $_SESSION['admin_kullanici'] ?? 'Admin') ?>
    </div>
    <a href="/admin/logout.php" class="logout-btn">Çıkış Yap</a>
  </div>
</aside>

<script>
function sidebarAc() {
  document.getElementById('adminSidebar').classList.add('acik');
  document.getElementById('sidebarOverlay').classList.add('aktif');
  document.body.style.overflow = 'hidden';
}
function sidebarKapat() {
  document.getElementById('adminSidebar').classList.remove('acik');
  document.getElementById('sidebarOverlay').classList.remove('aktif');
  document.body.style.overflow = '';
}
</script>
