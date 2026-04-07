<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/ayarlar.php';
sessionBaslat();
csrfKontrol();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$tur      = in_array($_POST['tur'] ?? '', ['sikayet', 'oneri']) ? $_POST['tur'] : null;
$ad_soyad = temizle($_POST['ad_soyad'] ?? '');
$telefon  = preg_replace('/[^0-9]/', '', $_POST['telefon'] ?? '');
$konu     = temizle($_POST['konu'] ?? '');
$aciklama = temizle($_POST['aciklama'] ?? '');

// reCAPTCHA dogrulama
$recaptcha_aktif     = ayarGetir('recaptcha', 'aktif', '0') === '1';
$recaptcha_secret    = ayarGetir('recaptcha', 'secret_key', '');
$recaptcha_min_skor  = (float)(ayarGetir('recaptcha', 'min_skor', '0.5'));

if ($recaptcha_aktif && !empty($recaptcha_secret)) {
    $recaptcha_token = $_POST['recaptcha_token'] ?? '';
    if (empty($recaptcha_token)) {
        $_SESSION['form_hata'] = ['Guvenlik dogrulamasi basarisiz. Lutfen tekrar deneyin.'];
        header('Location: /form.php?tur=' . urlencode($tur ?? 'sikayet'));
        exit;
    }
    $verify = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => $recaptcha_secret,
                'response' => $recaptcha_token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ])
        ]])
    );
    if ($verify) {
        $recaptcha_result = json_decode($verify, true);
        if (!$recaptcha_result['success'] || ($recaptcha_result['score'] ?? 0) < $recaptcha_min_skor) {
            $_SESSION['form_hata'] = ['Bot aktivitesi tespit edildi. Lutfen tekrar deneyin.'];
            header('Location: /form.php?tur=' . urlencode($tur ?? 'sikayet'));
            exit;
        }
    }
}

// Validasyon
$hatalar = [];
if (!$tur)                        $hatalar[] = 'Gecersiz bildirim turu.';
if (empty($konu))                 $hatalar[] = 'Konu secimi zorunludur.';
if (mb_strlen($aciklama) < 10)   $hatalar[] = 'Aciklama cok kisa (en az 10 karakter).';
if (mb_strlen($aciklama) > 2000) $hatalar[] = 'Aciklama cok uzun (en fazla 2000 karakter).';
if (!empty($telefon) && strlen($telefon) !== 10) {
    $hatalar[] = 'Telefon numarasi 10 haneli olmalidir.';
}

if (!empty($hatalar)) {
    $_SESSION['form_hata'] = $hatalar;
    header('Location: /form.php?tur=' . urlencode($tur ?? 'sikayet'));
    exit;
}

$db = getDB();

// Rate limiting — ayni IP'den 5 dakikada max 3 istek
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$ip = trim(explode(',', $ip)[0]);
$rate_stmt = $db->prepare("
    SELECT COUNT(*) FROM geri_bildirim
    WHERE ip_adresi = ?
    AND olusturma_tarihi > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$rate_stmt->execute([$ip]);
if ($rate_stmt->fetchColumn() >= 3) {
    $_SESSION['form_hata'] = ['Cok fazla istek gonderildi. Lutfen bekleyiniz.'];
    header('Location: /form.php?tur=' . urlencode($tur));
    exit;
}

// Gorsel yukle
$gorsel_yol = null;
if (!empty($_FILES['gorsel']['name']) &&
    $_FILES['gorsel']['error'] === UPLOAD_ERR_OK &&
    $_FILES['gorsel']['size'] > 0) {

    $dosya      = $_FILES['gorsel'];
    $izinli     = ['image/jpeg', 'image/png', 'image/webp'];
    $uzanti_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime       = mime_content_type($dosya['tmp_name']);

    if (!in_array($mime, $izinli)) {
        $hatalar[] = 'Gecersiz dosya turu. Sadece JPG, PNG veya WEBP yukleyebilirsiniz.';
    } elseif ($dosya['size'] > 5 * 1024 * 1024) {
        $hatalar[] = 'Dosya 5MB\'dan buyuk olamaz.';
    } else {
        $uzanti    = $uzanti_map[$mime];
        $dosya_adi = uniqid('img_', true) . '.' . $uzanti;
        $hedef     = __DIR__ . '/uploads/' . $dosya_adi;
        if (move_uploaded_file($dosya['tmp_name'], $hedef)) {
            $gorsel_yol = '/uploads/' . $dosya_adi;
        }
    }

    if (!empty($hatalar)) {
        $_SESSION['form_hata'] = $hatalar;
        header('Location: /form.php?tur=' . urlencode($tur));
        exit;
    }
}

// Kaydet
$ins = $db->prepare("
    INSERT INTO geri_bildirim
        (tur, ad_soyad, telefon, konu, aciklama, gorsel_yol, ip_adresi, tarayici)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
");
$ins->execute([
    $tur,
    $ad_soyad ?: null,
    $telefon  ?: null,
    $konu,
    $aciklama,
    $gorsel_yol,
    $ip,
    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
]);

// Tesekkur sayfasina yonlendir
unset($_SESSION['csrf_token']);
$_SESSION['bildirim_basarili'] = true;
$_SESSION['bildirim_tur']      = $tur;
header('Location: /tesekkur.php');
exit;
