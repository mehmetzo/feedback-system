<?php
require_once __DIR__ . '/config/db.php';
sessionBaslat();
if (empty($_SESSION['bildirim_basarili'])) {
    header('Location: /');
    exit;
}
$tur = $_SESSION['bildirim_tur'] ?? 'sikayet';
unset($_SESSION['bildirim_basarili'], $_SESSION['bildirim_tur']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="8;url=/">
<title>Teşekkür Ederiz</title>
<link rel="stylesheet" href="/assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="tesekkur-page">
<div class="page-wrapper tesekkur-wrapper">
  <div class="tesekkur-kart">

    <div class="check-container">
      <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
      </svg>
    </div>

    <h1 class="tesekkur-baslik">Teşekkür Ederiz</h1>

    <p class="tesekkur-mesaj">
      <?php echo $tur === 'sikayet'
        ? 'Şikayetiniz başarıyla alınmıştır.'
        : 'Öneriniz başarıyla alınmıştır.'; ?>
    </p>

    <p class="tesekkur-alt">
      Hizmet kalitemizi artırmak için değerlendirilecektir.
    </p>

    <div class="tesekkur-saglik">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2">
        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
      </svg>
      Sağlıklı günler dileriz.
    </div>

    <div class="yonlendirme-bar">
      <div class="yonlendirme-ilerleme"></div>
    </div>
    <p class="yonlendirme-yazi">Ana sayfaya yönlendiriliyorsunuz...</p>

    <a href="/" class="ana-sayfa-btn">Ana Sayfaya Dön</a>

  </div>
</div>
</body>
</html>
