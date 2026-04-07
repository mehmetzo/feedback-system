<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();
ldapIslemLog('Bildirim listesi goruntulendi');

$db = getDB();

$tur       = $_GET['tur']       ?? '';
$durum     = $_GET['durum']     ?? '';
$ara       = temizle($_GET['ara'] ?? '');
$tarih_bas = $_GET['tarih_bas'] ?? '';
$tarih_bit = $_GET['tarih_bit'] ?? '';
$sayfa     = max(1, intval($_GET['sayfa'] ?? 1));
$limit     = 20;
$offset    = ($sayfa - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($tur)       { $where[] = 'tur = ?';                     $params[] = $tur; }
if ($durum)     { $where[] = 'durum = ?';                   $params[] = $durum; }
if ($ara)       { $where[] = '(konu LIKE ? OR aciklama LIKE ? OR ad_soyad LIKE ?)';
                  array_push($params, "%$ara%", "%$ara%", "%$ara%"); }
if ($tarih_bas) { $where[] = 'DATE(olusturma_tarihi) >= ?'; $params[] = $tarih_bas; }
if ($tarih_bit) { $where[] = 'DATE(olusturma_tarihi) <= ?'; $params[] = $tarih_bit; }

$sql_where = implode(' AND ', $where);

$toplam_stmt = $db->prepare("SELECT COUNT(*) FROM geri_bildirim WHERE $sql_where");
$toplam_stmt->execute($params);
$toplam_kayit = $toplam_stmt->fetchColumn();
$toplam_sayfa = ceil($toplam_kayit / $limit);

$stmt = $db->prepare("
    SELECT * FROM geri_bildirim
    WHERE $sql_where
    ORDER BY olusturma_tarihi DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$bildirimler = $stmt->fetchAll();

$filtre_query = http_build_query(array_filter([
    'tur'       => $tur,
    'durum'     => $durum,
    'ara'       => $ara,
    'tarih_bas' => $tarih_bas,
    'tarih_bit' => $tarih_bit,
]));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bildirimler — Admin Paneli</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-sayfa-baslik">Bildirimler</h1>
      <p class="admin-sayfa-alt">Toplam <?= $toplam_kayit ?> kayıt</p>
    </div>
    <?php if (($_SESSION['admin_kullanici'] ?? '') === 'admin'): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="/admin/export.php?format=csv&<?= $filtre_query ?>"
         class="admin-btn btn-outline">⬇ CSV</a>
      <a href="/admin/export.php?format=pdf&<?= $filtre_query ?>"
         class="admin-btn btn-outline" target="_blank">📄 PDF</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Filtreler -->
  <form method="GET" class="filtre-form">
    <input type="text" name="ara" placeholder="Ara..."
           value="<?= htmlspecialchars($ara) ?>" class="filtre-input">
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
    <input type="date" name="tarih_bas"
           value="<?= htmlspecialchars($tarih_bas) ?>"
           class="filtre-input" style="max-width:150px" title="Başlangıç Tarihi">
    <input type="date" name="tarih_bit"
           value="<?= htmlspecialchars($tarih_bit) ?>"
           class="filtre-input" style="max-width:150px" title="Bitiş Tarihi">
    <button type="submit" class="admin-btn btn-primary">Filtrele</button>
    <a href="/admin/list.php" class="admin-btn btn-outline">Temizle</a>
  </form>

  <!-- Tablo -->
  <div class="tablo-kart">
    <div class="tablo-wrap">
      <table class="admin-tablo">
        <thead>
          <tr>
            <th>#</th>
            <th>Tür</th>
            <th>Konu</th>
            <th>Ad Soyad</th>
            <th>Durum</th>
            <th>Tarih</th>
            <th>İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bildirimler)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:2rem;color:#888">
              Kayıt bulunamadı.
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($bildirimler as $b): ?>
          <tr class="<?= $b['durum']==='yeni' ? 'satir-yeni' : '' ?>">
            <td><?= $b['id'] ?></td>
            <td>
              <span class="tur-badge tur-<?= $b['tur'] ?>">
                <?= $b['tur']==='sikayet' ? '🔴 Şikayet' : '🟢 Öneri' ?>
              </span>
            </td>
            <td><?= htmlspecialchars($b['konu']) ?></td>
            <td><?= htmlspecialchars($b['ad_soyad'] ?? 'Anonim') ?></td>
            <td>
              <form method="POST" action="/admin/durum_guncelle.php" style="display:inline">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <select name="durum" onchange="this.form.submit()"
                        class="durum-select durum-<?= $b['durum'] ?>">
                  <option value="yeni"        <?= $b['durum']==='yeni'?'selected':'' ?>>Yeni</option>
                  <option value="inceleniyor" <?= $b['durum']==='inceleniyor'?'selected':'' ?>>İnceleniyor</option>
                  <option value="cozuldu"     <?= $b['durum']==='cozuldu'?'selected':'' ?>>Çözüldü</option>
                  <option value="kapatildi"   <?= $b['durum']==='kapatildi'?'selected':'' ?>>Kapatıldı</option>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap">
              <?= date('d.m.Y H:i', strtotime($b['olusturma_tarihi'])) ?>
            </td>
            <td>
              <a href="/admin/detail.php?id=<?= $b['id'] ?>" class="detay-btn">Detay</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sayfalama -->
  <?php if ($toplam_sayfa > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $i])) ?>"
       class="page-btn <?= $i===$sayfa ? 'page-aktif' : '' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</main>
</body>
</html>
