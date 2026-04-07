<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ayarlar.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin/list.php'); exit; }

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM geri_bildirim WHERE id = ?");
$stmt->execute([$id]);
$b = $stmt->fetch();
if (!$b) { header('Location: /admin/list.php'); exit; }

ldapIslemLog('Bildirim detay goruntulendi', '#' . $id . ' | ' . $b['tur'] . ' | ' . $b['konu']);

// Admin notu kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_notu'])) {
    $not     = temizle($_POST['admin_notu'] ?? '');
    $eski_not = $b['admin_notu'] ?? '';

    $db->prepare("UPDATE geri_bildirim SET admin_notu = ? WHERE id = ?")
       ->execute([$not, $id]);
    ldapIslemLog('Admin notu guncellendi', '#' . $id . ' | ' . mb_substr($not, 0, 50));
    $b['admin_notu'] = $not;

    // SMS sadece not degistiyse ve bos degilse gonder
    $sms_aktif      = ayarGetir('sms', 'aktif',            '0') === '1';
    $sms_not_gonder = ayarGetir('sms', 'admin_not_gonder', '0') === '1';
    $sms_mesaj      = '';

    if ($sms_aktif && $sms_not_gonder && !empty($not) &&
        trim($not) !== trim($eski_not) && !empty($b['telefon'])) {
        $hastane_adi = ayarGetir('hastane', 'adi', 'Hastane');
        $mesaj = $hastane_adi . ' - Bildiriminiz hakkinda not: ' . $not;
        $sonuc = smsSms($b['telefon'], $mesaj);
        $sms_mesaj = $sonuc['basarili']
            ? '<div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px">✅ SMS gonderildi: ' . htmlspecialchars($b['telefon']) . '</div>'
            : '<div style="background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px">❌ SMS gonderilemedi: ' . htmlspecialchars($sonuc['mesaj']) . '</div>';
    } elseif ($sms_aktif && $sms_not_gonder && !empty($not) &&
              trim($not) === trim($eski_not)) {
        $sms_mesaj = '<div style="background:#fffbeb;border:1px solid #f6e05e;color:#b7791f;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px">ℹ️ Not degismedi, SMS gonderilmedi.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bildirim Detay #<?= $b['id'] ?> — Admin Paneli</title>
<link rel="stylesheet" href="/assets/admin.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="admin-main">

  <div class="admin-topbar">
    <div>
      <h1 class="admin-sayfa-baslik">Bildirim Detay #<?= $b['id'] ?></h1>
      <p class="admin-sayfa-alt"><?= date('d.m.Y H:i', strtotime($b['olusturma_tarihi'])) ?></p>
    </div>
    <a href="/admin/list.php" class="admin-btn btn-outline">← Geri</a>
  </div>

  <div class="detay-grid">

    <!-- Bildirim Bilgileri -->
    <div class="detay-kart">
      <div class="detay-baslik">Bildirim Bilgileri</div>
      <table class="detay-tablo">
        <tr>
          <td>Tür</td>
          <td>
            <span class="tur-badge tur-<?= $b['tur'] ?>">
              <?= $b['tur']==='sikayet' ? '🔴 Şikayet' : '🟢 Öneri' ?>
            </span>
          </td>
        </tr>
        <tr><td>Konu</td><td><?= htmlspecialchars($b['konu']) ?></td></tr>
        <tr><td>Ad Soyad</td><td><?= htmlspecialchars($b['ad_soyad'] ?? 'Anonim') ?></td></tr>
        <tr><td>Telefon</td><td><?= htmlspecialchars($b['telefon'] ?? '—') ?></td></tr>
        <tr>
          <td>Durum</td>
          <td>
            <span class="durum-badge durum-<?= $b['durum'] ?>">
              <?= ucfirst($b['durum']) ?>
            </span>
          </td>
        </tr>
        <tr>
          <td>Tarih</td>
          <td><?= date('d.m.Y H:i', strtotime($b['olusturma_tarihi'])) ?></td>
        </tr>
      </table>
    </div>

    <!-- Açıklama -->
    <div class="detay-kart">
      <div class="detay-baslik">Açıklama</div>
      <div class="detay-aciklama">
        <?= nl2br(htmlspecialchars($b['aciklama'])) ?>
      </div>

      <!-- Görsel varsa göster -->
      <?php if (!empty($b['gorsel_yol'])): ?>
      <div style="margin-top:16px">
        <div style="font-size:13px;font-weight:600;color:#718096;margin-bottom:8px">
          Eklenen Görsel
        </div>
        <a href="<?= htmlspecialchars($b['gorsel_yol']) ?>" target="_blank">
          <img src="<?= htmlspecialchars($b['gorsel_yol']) ?>"
               alt="Eklenen gorsel"
               style="max-width:100%;border-radius:8px;
                      border:1px solid #e2e8f0;cursor:zoom-in">
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Durum Güncelle -->
    <div class="detay-kart">
      <div class="detay-baslik">Durum Güncelle</div>
      <form method="POST" action="/admin/durum_guncelle.php">
        <input type="hidden" name="id" value="<?= $b['id'] ?>">
        <input type="hidden" name="redirect" value="/admin/detail.php?id=<?= $b['id'] ?>">
        <select name="durum" class="filtre-select"
                style="width:100%;margin-bottom:12px">
          <option value="yeni"        <?= $b['durum']==='yeni'?'selected':'' ?>>Yeni</option>
          <option value="inceleniyor" <?= $b['durum']==='inceleniyor'?'selected':'' ?>>İnceleniyor</option>
          <option value="cozuldu"     <?= $b['durum']==='cozuldu'?'selected':'' ?>>Çözüldü</option>
          <option value="kapatildi"   <?= $b['durum']==='kapatildi'?'selected':'' ?>>Kapatıldı</option>
        </select>
        <button type="submit" class="admin-btn btn-primary" style="width:100%">
          Güncelle
        </button>
      </form>
    </div>

    <!-- Admin Notu -->
    <div class="detay-kart">
      <div class="detay-baslik">Admin Notu</div>
      <?php if (!empty($sms_mesaj)) echo $sms_mesaj; ?>
      <form method="POST">
        <textarea name="admin_notu" rows="4"
          placeholder="Bu bildirim hakkında not ekleyin..."
          style="width:100%;padding:10px;border:1.5px solid #e2e8f0;
                 border-radius:8px;font-family:Inter,sans-serif;font-size:14px;
                 resize:vertical;outline:none;margin-bottom:10px;box-sizing:border-box"
          onfocus="this.style.borderColor='#1a56a0'"
          onblur="this.style.borderColor='#e2e8f0'"
        ><?= htmlspecialchars($b['admin_notu'] ?? '') ?></textarea>
        <button type="submit" class="admin-btn btn-primary" style="width:100%">
          Notu Kaydet
        </button>
      </form>
    </div>

  </div>
</main>
</body>
</html>
