<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ayarlar.php';
require_once __DIR__ . '/../config/ldap.php';
adminKontrol();

$db     = getDB();
$format = $_GET['format'] ?? 'csv';
$tur    = $_GET['tur']    ?? '';
$durum  = $_GET['durum']  ?? '';
$tarih_bas = $_GET['tarih_bas'] ?? '';
$tarih_bit = $_GET['tarih_bit'] ?? '';

$where  = ['1=1'];
$params = [];
if ($tur)       { $where[] = 'tur = ?';                     $params[] = $tur; }
if ($durum)     { $where[] = 'durum = ?';                   $params[] = $durum; }
if ($tarih_bas) { $where[] = 'DATE(olusturma_tarihi) >= ?'; $params[] = $tarih_bas; }
if ($tarih_bit) { $where[] = 'DATE(olusturma_tarihi) <= ?'; $params[] = $tarih_bit; }

$stmt = $db->prepare("
    SELECT id, tur, konu, aciklama, ad_soyad, telefon, durum, olusturma_tarihi
    FROM geri_bildirim
    WHERE " . implode(' AND ', $where) . "
    ORDER BY olusturma_tarihi DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

ldapIslemLog('Disa aktarildi', strtoupper($format) . ' - ' . count($rows) . ' kayit');

// CSV
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="geri_bildirimler_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['ID','Tür','Konu','Açıklama','Ad Soyad','Telefon','Durum','Tarih'], ';');
    foreach ($rows as $r) {
        fputcsv($fp, [
            $r['id'], $r['tur'], $r['konu'], $r['aciklama'],
            $r['ad_soyad'] ?? '', $r['telefon'] ?? '',
            $r['durum'], $r['olusturma_tarihi']
        ], ';');
    }
    fclose($fp);
    exit;
}

// PDF — saf PHP ile HTML→PDF
if ($format === 'pdf') {
    $hastane_adi = ayarGetir('hastane', 'adi', 'Hastane');
    $tarih_str   = date('d.m.Y H:i');

    $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
    <style>
      body { font-family: Arial, sans-serif; font-size: 11px; color: #1a202c; margin: 20px; }
      h2 { font-size: 16px; color: #1a56a0; margin-bottom: 4px; }
      .meta { font-size: 11px; color: #718096; margin-bottom: 16px; }
      table { width: 100%; border-collapse: collapse; }
      th { background: #1a56a0; color: #fff; padding: 7px 8px; text-align: left; font-size: 11px; }
      td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; vertical-align: top; }
      tr:nth-child(even) td { background: #f7fafc; }
      .badge-sikayet { background:#fff5f5;color:#c53030;padding:2px 6px;border-radius:4px;font-size:10px; }
      .badge-oneri   { background:#f0fff4;color:#276749;padding:2px 6px;border-radius:4px;font-size:10px; }
      .badge-yeni        { background:#ebf8ff;color:#2b6cb0;padding:2px 6px;border-radius:4px;font-size:10px; }
      .badge-inceleniyor { background:#fffbeb;color:#b7791f;padding:2px 6px;border-radius:4px;font-size:10px; }
      .badge-cozuldu     { background:#f0fff4;color:#276749;padding:2px 6px;border-radius:4px;font-size:10px; }
      .badge-kapatildi   { background:#f7fafc;color:#718096;padding:2px 6px;border-radius:4px;font-size:10px; }
      .footer { margin-top: 16px; font-size: 10px; color: #a0aec0; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style></head><body>';

    $html .= '<h2>' . htmlspecialchars($hastane_adi) . ' — Geri Bildirim Raporu</h2>';
    $html .= '<div class="meta">Oluşturulma: ' . $tarih_str . ' &nbsp;|&nbsp; Toplam: ' . count($rows) . ' kayıt';
    if ($tur)       $html .= ' &nbsp;|&nbsp; Tür: ' . htmlspecialchars($tur);
    if ($durum)     $html .= ' &nbsp;|&nbsp; Durum: ' . htmlspecialchars($durum);
    if ($tarih_bas) $html .= ' &nbsp;|&nbsp; Başlangıç: ' . htmlspecialchars($tarih_bas);
    if ($tarih_bit) $html .= ' &nbsp;|&nbsp; Bitiş: ' . htmlspecialchars($tarih_bit);
    $html .= '</div>';

    $html .= '<table><thead><tr>
        <th>#</th><th>Tür</th><th>Konu</th><th>Açıklama</th>
        <th>Ad Soyad</th><th>Telefon</th><th>Durum</th><th>Tarih</th>
    </tr></thead><tbody>';

    foreach ($rows as $r) {
        $aciklama = mb_strlen($r['aciklama']) > 80
            ? mb_substr($r['aciklama'], 0, 80) . '...'
            : $r['aciklama'];
        $html .= '<tr>
            <td>' . $r['id'] . '</td>
            <td><span class="badge-' . $r['tur'] . '">' . ($r['tur'] === 'sikayet' ? 'Şikayet' : 'Öneri') . '</span></td>
            <td>' . htmlspecialchars($r['konu']) . '</td>
            <td>' . htmlspecialchars($aciklama) . '</td>
            <td>' . htmlspecialchars($r['ad_soyad'] ?? 'Anonim') . '</td>
            <td>' . htmlspecialchars($r['telefon'] ?? '—') . '</td>
            <td><span class="badge-' . $r['durum'] . '">' . ucfirst($r['durum']) . '</span></td>
            <td>' . date('d.m.Y H:i', strtotime($r['olusturma_tarihi'])) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<div class="footer">' . htmlspecialchars($hastane_adi) . ' Geri Bildirim Sistemi — ' . $tarih_str . '</div>';
    $html .= '</body></html>';

    // Tarayıcıya HTML gönder, print dialog aç
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    echo '<script>window.onload=function(){ window.print(); }</script>';
    exit;
}

// Format bilinmiyorsa listeye dön
header('Location: /admin/list.php');
exit;
