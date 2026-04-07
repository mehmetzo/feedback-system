<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();

if (($_SESSION['admin_kullanici'] ?? '') !== 'admin') {
    header('Location: /admin/dashboard.php');
    exit;
}

ldapIslemLog('Disa aktar sayfasi goruntulendi');

$tur       = $_GET['tur']       ?? '';
$durum     = $_GET['durum']     ?? '';
$tarih_bas = $_GET['tarih_bas'] ?? '';
$tarih_bit = $_GET['tarih_bit'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dışa Aktar — Admin Paneli</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-sayfa-baslik">Dışa Aktar</h1>
      <p class="admin-sayfa-alt">Geri bildirimleri dışa aktarın</p>
    </div>
    <a href="/admin/list.php" class="admin-btn btn-outline">← Geri</a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:600px">

    <!-- CSV -->
    <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);text-align:center">
      <div style="font-size:40px;margin-bottom:12px">📊</div>
      <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">CSV Formatı</h3>
      <p style="font-size:13px;color:#718096;margin-bottom:20px;line-height:1.5">
        Excel ve diğer programlarda açılabilir. Tüm veriler dahil.
      </p>
      <a href="/admin/export.php?format=csv&tur=<?= urlencode($tur) ?>&durum=<?= urlencode($durum) ?>&tarih_bas=<?= urlencode($tarih_bas) ?>&tarih_bit=<?= urlencode($tarih_bit) ?>"
         class="admin-btn btn-primary" style="width:100%;justify-content:center">
        ⬇ CSV İndir
      </a>
    </div>

    <!-- PDF -->
    <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06);text-align:center">
      <div style="font-size:40px;margin-bottom:12px">📄</div>
      <h3 style="font-size:16px;font-weight:700;margin-bottom:6px">PDF Formatı</h3>
      <p style="font-size:13px;color:#718096;margin-bottom:20px;line-height:1.5">
        Yazdırılabilir rapor formatı. Tarayıcıdan PDF olarak kaydedin.
      </p>
      <a href="/admin/export.php?format=pdf&tur=<?= urlencode($tur) ?>&durum=<?= urlencode($durum) ?>&tarih_bas=<?= urlencode($tarih_bas) ?>&tarih_bit=<?= urlencode($tarih_bit) ?>"
         class="admin-btn btn-outline" style="width:100%;justify-content:center" target="_blank">
        📄 PDF Görüntüle
      </a>
    </div>

  </div>

  <!-- Filtreler -->
  <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-top:16px;max-width:600px">
    <h3 style="font-size:14px;font-weight:600;margin-bottom:14px">Filtrele</h3>
    <form method="GET" class="filtre-form">
      <select name="tur" class="filtre-select">
        <option value="">Tüm Türler</option>
        <option value="sikayet" <?= $tur==='sikayet'?'selected':'' ?>>Şikayet</option>
        <option value="oneri"   <?= $tur==='oneri'?'selected':'' ?>>Öneri</option>
      </select>
      <select name="durum" class="filtre-select">
        <option value="">Tüm Durumlar</option>
        <option value="yeni"        <?= $durum==='yeni'?'selected':'' ?>>Yeni</option>
        <option value="inceleniyor" <?= $durum==='inceleniyor'?'selected':'' ?>>İnceleniyor</option>
        <option value="cozuldu"     <?= $durum==='cozuldu'?'selected':'' ?>>Çözüldü</option>
        <option value="kapatildi"   <?= $durum==='kapatildi'?'selected':'' ?>>Kapatıldı</option>
      </select>
      <input type="date" name="tarih_bas" value="<?= htmlspecialchars($tarih_bas) ?>"
             class="filtre-input" style="max-width:150px" title="Başlangıç">
      <input type="date" name="tarih_bit" value="<?= htmlspecialchars($tarih_bit) ?>"
             class="filtre-input" style="max-width:150px" title="Bitiş">
      <button type="submit" class="admin-btn btn-primary">Filtrele</button>
    </form>
  </div>

</main>
</body>
</html>
