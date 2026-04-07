<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();
ldapIslemLog('Log sayfası görüntülendi');

$db = getDB();

$kullanici = temizle($_GET['kullanici'] ?? '');
$seviye    = $_GET['seviye'] ?? '';
$tarih     = $_GET['tarih']  ?? '';
$sayfa     = max(1, intval($_GET['sayfa'] ?? 1));
$limit     = 50;
$offset    = ($sayfa - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($kullanici) { $where[] = 'kullanici_adi LIKE ?'; $params[] = "%$kullanici%"; }
if ($seviye)    { $where[] = 'seviye = ?';            $params[] = $seviye; }
if ($tarih)     { $where[] = 'DATE(olusturma_tarihi) = ?'; $params[] = $tarih; }

$sql_where = implode(' AND ', $where);

$toplam = $db->prepare("SELECT COUNT(*) FROM ldap_log WHERE $sql_where");
$toplam->execute($params);
$toplam_kayit = $toplam->fetchColumn();
$toplam_sayfa = ceil($toplam_kayit / $limit);

$stmt = $db->prepare("
    SELECT * FROM ldap_log
    WHERE $sql_where
    ORDER BY olusturma_tarihi DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$loglar = $stmt->fetchAll();

// Özet istatistikler
$istatistik = $db->query("
    SELECT
        COUNT(*) as toplam,
        SUM(seviye='info') as basarili,
        SUM(seviye='warning') as uyari,
        SUM(seviye='error') as hata,
        COUNT(DISTINCT kullanici_adi) as benzersiz_kullanici
    FROM ldap_log
    WHERE olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Erişim Logları — Admin Paneli</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-sayfa-baslik">Erişim Logları</h1>
      <p class="admin-sayfa-alt">LDAP kullanıcı işlem kayıtları</p>
    </div>
  </div>

  <!-- Son 7 gün istatistik -->
  <div class="stat-grid" style="margin-bottom:20px">
    <div class="stat-kart">
      <div class="stat-ikon">📋</div>
      <div class="stat-sayi"><?= $istatistik['toplam'] ?></div>
      <div class="stat-etiket">Son 7 Gün İşlem</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">✅</div>
      <div class="stat-sayi"><?= $istatistik['basarili'] ?></div>
      <div class="stat-etiket">Başarılı Giriş</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">⚠️</div>
      <div class="stat-sayi"><?= $istatistik['uyari'] ?></div>
      <div class="stat-etiket">Başarısız Deneme</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">👤</div>
      <div class="stat-sayi"><?= $istatistik['benzersiz_kullanici'] ?></div>
      <div class="stat-etiket">Benzersiz Kullanıcı</div>
    </div>
  </div>

  <!-- Filtreler -->
  <form method="GET" class="filtre-form">
    <input type="text" name="kullanici" placeholder="Kullanıcı ara..."
           value="<?= htmlspecialchars($kullanici) ?>" class="filtre-input">
    <select name="seviye" class="filtre-select">
      <option value="">Tüm Seviyeler</option>
      <option value="info"    <?= $seviye==='info'?'selected':'' ?>>Başarılı</option>
      <option value="warning" <?= $seviye==='warning'?'selected':'' ?>>Uyarı</option>
      <option value="error"   <?= $seviye==='error'?'selected':'' ?>>Hata</option>
    </select>
    <input type="date" name="tarih" value="<?= htmlspecialchars($tarih) ?>"
           class="filtre-input" style="max-width:150px">
    <button type="submit" class="admin-btn btn-primary">Filtrele</button>
    <a href="/admin/logs.php" class="admin-btn btn-outline">Temizle</a>
  </form>

  <!-- Log tablosu -->
  <div class="tablo-kart">
    <div class="tablo-baslik">
      <h3>İşlem Kayıtları</h3>
      <span style="font-size:13px;color:#718096">Toplam <?= $toplam_kayit ?> kayıt</span>
    </div>
    <div class="tablo-wrap">
      <table class="admin-tablo">
        <thead>
          <tr>
            <th>Tarih/Saat</th>
            <th>Kullanıcı</th>
            <th>İşlem</th>
            <th>Seviye</th>
            <th>IP Adresi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($loglar)): ?>
          <tr><td colspan="5" style="text-align:center;padding:2rem;color:#888">Kayıt bulunamadı.</td></tr>
          <?php else: ?>
          <?php foreach ($loglar as $log): ?>
          <tr>
            <td style="white-space:nowrap;font-size:13px">
              <?= date('d.m.Y H:i:s', strtotime($log['olusturma_tarihi'])) ?>
            </td>
            <td><strong><?= htmlspecialchars($log['kullanici_adi']) ?></strong></td>
            <td><?= htmlspecialchars($log['islem']) ?></td>
            <td>
              <?php
              $seviye_cls = [
                'info'    => 'durum-cozuldu',
                'warning' => 'durum-inceleniyor',
                'error'   => 'tur-sikayet',
              ];
              $seviye_ikon = [
                'info'    => '✅',
                'warning' => '⚠️',
                'error'   => '❌',
              ];
              $cls = $seviye_cls[$log['seviye']] ?? 'durum-yeni';
              ?>
              <span class="durum-badge <?= $cls ?>">
                <?= $seviye_ikon[$log['seviye']] ?? '' ?>
                <?= ucfirst($log['seviye']) ?>
              </span>
            </td>
            <td style="font-size:13px;color:#718096">
              <?= htmlspecialchars($log['ip_adresi'] ?? '—') ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($toplam_sayfa > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['sayfa' => $i])) ?>"
       class="page-btn <?= $i===$sayfa ? 'page-aktif' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</main>
</body>
</html>
