
<?php
require_once __DIR__ . '/config/db.php';
adminKontrol();

// Composer ile endroid/qr-code veya basit alternatif
// composer.json'a ekleyin: "endroid/qr-code": "^4.0"
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

require __DIR__ . '/vendor/autoload.php';

$db = getDB();
$bolgeler = $db->query("SELECT * FROM bolge WHERE aktif=1 ORDER BY ad")->fetchAll();

$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>




QR Kod Üretici




<?php include __DIR__ . '/admin/partials/sidebar.php'; ?>

  QR Kod Üretici
  Bölge bazlı QR kodlarını oluşturun ve yazdırın.

  
    
    <?php
      $url = $base_url . '/index.php?bolge=' . urlencode($bolge['kod']);
      $qr  = QrCode::create($url)->setSize(200)->setMargin(10);
      $png = (new PngWriter())->write($qr);
      $b64 = base64_encode($png->getString());
    ?>
    
      
      <?= htmlspecialchars($bolge['ad']) ?>
      <?= htmlspecialchars($url) ?>
      
        ⬇ İndir
