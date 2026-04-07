<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = intval($_POST['id'] ?? 0);
    $durum = in_array($_POST['durum'] ?? '', ['yeni','inceleniyor','cozuldu','kapatildi'])
             ? $_POST['durum'] : null;

    if ($id && $durum) {
        $db = getDB();

        // Eski durumu al
        $eski = $db->prepare("SELECT durum, konu, tur FROM geri_bildirim WHERE id = ?");
        $eski->execute([$id]);
        $bildirim = $eski->fetch();

        // Güncelle
        $db->prepare("UPDATE geri_bildirim SET durum = ? WHERE id = ?")
           ->execute([$durum, $id]);

        // Logla
        ldapIslemLog(
            'Durum guncellendi',
            '#' . $id . ' | ' . ($bildirim['tur'] ?? '') . ' | ' .
            ($bildirim['konu'] ?? '') . ' | ' .
            ($bildirim['durum'] ?? '?') . ' -> ' . $durum
        );
    }
}

$redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/admin/list.php';
header('Location: ' . $redirect);
exit;
