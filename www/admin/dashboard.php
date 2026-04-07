<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();
ldapIslemLog('Dashboard goruntulendi');

$db = getDB();

$stats = [];
$stats['toplam']   = $db->query("SELECT COUNT(*) FROM geri_bildirim")->fetchColumn();
$stats['sikayet']  = $db->query("SELECT COUNT(*) FROM geri_bildirim WHERE tur='sikayet'")->fetchColumn();
$stats['oneri']    = $db->query("SELECT COUNT(*) FROM geri_bildirim WHERE tur='oneri'")->fetchColumn();
$stats['yeni']     = $db->query("SELECT COUNT(*) FROM geri_bildirim WHERE durum='yeni'")->fetchColumn();
$stats['bugun']    = $db->query("SELECT COUNT(*) FROM geri_bildirim WHERE DATE(olusturma_tarihi)=CURDATE()")->fetchColumn();
$stats['bu_hafta'] = $db->query("SELECT COUNT(*) FROM geri_bildirim WHERE olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$konular = $db->query("
    SELECT konu, COUNT(*) as sayi
    FROM geri_bildirim
    WHERE tur='sikayet'
    GROUP BY konu
    ORDER BY sayi DESC
    LIMIT 5
")->fetchAll();

$son_bildirimler = $db->query("
    SELECT * FROM geri_bildirim
    ORDER BY olusturma_tarihi DESC
    LIMIT 10
")->fetchAll();

$gunluk = $db->query("
    SELECT DATE(olusturma_tarihi) as gun, tur, COUNT(*) as sayi
    FROM geri_bildirim
    WHERE olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(olusturma_tarihi), tur
    ORDER BY gun ASC
")->fetchAll();

$is_admin = ($_SESSION['admin_kullanici'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Admin Paneli</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="admin-page">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-sayfa-baslik">Dashboard</h1>
      <p class="admin-sayfa-alt">Geri Bildirim Sistemi Genel Görünümü</p>
    </div>
    <div class="topbar-aksiyonlar">
      <a href="/admin/list.php" class="admin-btn btn-primary">Tüm Bildirimler</a>
      <?php if ($is_admin): ?>
      <a href="/admin/export.php?format=csv" class="admin-btn btn-outline">⬇ CSV</a>
      <a href="/admin/export.php?format=pdf" class="admin-btn btn-outline" target="_blank">📄 PDF</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- İstatistik kartları -->
  <div class="stat-grid">
    <div class="stat-kart">
      <div class="stat-ikon">📊</div>
      <div class="stat-sayi"><?= $stats['toplam'] ?></div>
      <div class="stat-etiket">Toplam Bildirim</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">🔴</div>
      <div class="stat-sayi"><?= $stats['sikayet'] ?></div>
      <div class="stat-etiket">Şikayet</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">🟢</div>
      <div class="stat-sayi"><?= $stats['oneri'] ?></div>
      <div class="stat-etiket">Öneri</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">🔔</div>
      <div class="stat-sayi"><?= $stats['yeni'] ?></div>
      <div class="stat-etiket">Yeni (Bekleyen)</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">📅</div>
      <div class="stat-sayi"><?= $stats['bugun'] ?></div>
      <div class="stat-etiket">Bugün</div>
    </div>
    <div class="stat-kart">
      <div class="stat-ikon">📈</div>
      <div class="stat-sayi"><?= $stats['bu_hafta'] ?></div>
      <div class="stat-etiket">Bu Hafta</div>
    </div>
  </div>

  <!-- Grafikler -->
  <div class="grafik-grid">
    <div class="grafik-kart">
      <h3>Son 7 Gün — Bildirim Trendi</h3>
      <canvas id="trendGrafik" height="120"></canvas>
    </div>
    <div class="grafik-kart">
      <h3>Şikayet Konuları Dağılımı</h3>
      <canvas id="konuGrafik" height="60" style="max-height:140px"></canvas>
    </div>
  </div>

  <!-- Son bildirimler -->
  <div class="tablo-kart">
    <div class="tablo-baslik">
      <h3>Son Bildirimler</h3>
      <a href="/admin/list.php" class="tumu-link">Tümünü Gör →</a>
    </div>
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
          <?php foreach ($son_bildirimler as $b): ?>
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
              <span class="durum-badge durum-<?= $b['durum'] ?>">
                <?= ucfirst($b['durum']) ?>
              </span>
            </td>
            <td style="white-space:nowrap">
              <?= date('d.m.Y H:i', strtotime($b['olusturma_tarihi'])) ?>
            </td>
            <td>
              <a href="/admin/detail.php?id=<?= $b['id'] ?>" class="detay-btn">Detay</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
var gunluk = <?= json_encode($gunluk) ?>;
var gunler = [...new Set(gunluk.map(function(r){ return r.gun; }))];
var sikayetData = gunler.map(function(g){
  var r = gunluk.find(function(x){ return x.gun===g && x.tur==='sikayet'; });
  return r ? parseInt(r.sayi) : 0;
});
var oneriData = gunler.map(function(g){
  var r = gunluk.find(function(x){ return x.gun===g && x.tur==='oneri'; });
  return r ? parseInt(r.sayi) : 0;
});

new Chart(document.getElementById('trendGrafik'), {
  type: 'line',
  data: {
    labels: gunler.map(function(g){
      return new Date(g).toLocaleDateString('tr-TR', {day:'2-digit', month:'short'});
    }),
    datasets: [
      {
        label: 'Şikayet',
        data: sikayetData,
        borderColor: '#e53e3e',
        backgroundColor: 'rgba(229,62,62,0.1)',
        tension: 0.3,
        fill: true
      },
      {
        label: 'Öneri',
        data: oneriData,
        borderColor: '#38a169',
        backgroundColor: 'rgba(56,161,105,0.1)',
        tension: 0.3,
        fill: true
      }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});

var konular = <?= json_encode($konular) ?>;
if (konular.length > 0) {
  new Chart(document.getElementById('konuGrafik'), {
    type: 'doughnut',
    data: {
      labels: konular.map(function(k){ return k.konu; }),
      datasets: [{
        data: konular.map(function(k){ return parseInt(k.sayi); }),
        backgroundColor: ['#e53e3e','#dd6b20','#d69e2e','#38a169','#3182ce']
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } }
    }
  });
}
</script>
</body>
</html>
