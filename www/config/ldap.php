<?php
function ldapAyar(string $anahtar, string $varsayilan = ''): string {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT deger FROM ayarlar WHERE grup = 'ldap' AND anahtar = ?");
        $stmt->execute([$anahtar]);
        $row = $stmt->fetch();
        return $row ? (string)$row['deger'] : $varsayilan;
    } catch (Exception $e) {
        return $varsayilan;
    }
}

function ldapBaglan() {
    $host = str_replace('ldap://', '', ldapAyar('host'));
    $port = (int)(ldapAyar('port', '389'));

    if (empty($host)) return false;

    $conn = ldap_connect($host, $port);
    if (!$conn) return false;

    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);
    return $conn;
}

function ldapKullaniciDogrula(string $kullanici_adi, string $sifre): array {
    if (empty($kullanici_adi) || empty($sifre)) {
        return ['basarili' => false, 'hata' => 'Kullanici adi ve sifre gereklidir.'];
    }

    $domain   = ldapAyar('domain');
    $base_dn  = ldapAyar('base_dn');
    $bind_user = ldapAyar('bind_user');
    $bind_pass = ldapAyar('bind_pass');
    $grup_adi  = ldapAyar('group');

    if (empty($domain) || empty($base_dn) || empty($bind_user)) {
        return ['basarili' => false, 'hata' => 'LDAP ayarları eksik. Lütfen yönetici ile iletişime geçin.'];
    }

    $conn = ldapBaglan();
    if (!$conn) {
        return ['basarili' => false, 'hata' => 'LDAP sunucusuna baglanılamadı.'];
    }

    // Servis hesabı ile bağlan (UPN formatı)
    $bind = @ldap_bind($conn, $bind_user . '@' . $domain, $bind_pass);
    if (!$bind) {
        $hata = ldap_error($conn);
        ldapLog('SYSTEM', 'Servis hesabi baglantisi basarisiz: ' . $hata, 'error');
        return ['basarili' => false, 'hata' => 'LDAP servis baglantisi basarisiz: ' . $hata];
    }

    // Kullanıcıyı ara
    $filtre = '(sAMAccountName=' . ldap_escape($kullanici_adi, '', LDAP_ESCAPE_FILTER) . ')';
    $sonuc  = @ldap_search($conn, $base_dn, $filtre, [
        'dn', 'cn', 'mail', 'displayName', 'memberOf', 'sAMAccountName'
    ]);

    if (!$sonuc || ldap_count_entries($conn, $sonuc) === 0) {
        ldapLog($kullanici_adi, 'Kullanici bulunamadi', 'warning');
        return ['basarili' => false, 'hata' => 'Kullanici adi veya sifre hatali.'];
    }

    $entry   = ldap_first_entry($conn, $sonuc);
    $user_dn = ldap_get_dn($conn, $entry);
    $attrs   = ldap_get_attributes($conn, $entry);

    // Kullanıcı şifresi ile bağlan (UPN formatı)
    $user_bind = @ldap_bind($conn, $kullanici_adi . '@' . $domain, $sifre);
    if (!$user_bind) {
        ldapLog($kullanici_adi, 'Sifre hatali: ' . ldap_error($conn), 'warning');
        return ['basarili' => false, 'hata' => 'Kullanici adi veya sifre hatali.'];
    }

    // Grup üyeliği kontrol
    if (!empty($grup_adi) && !ldapGrupKontrol($conn, $attrs, $user_dn, $grup_adi, $base_dn)) {
        ldapLog($kullanici_adi, 'Grup yetkisi yok: ' . $grup_adi, 'warning');
        ldap_unbind($conn);
        return ['basarili' => false, 'hata' => 'Bu sisteme erisim yetkiniz bulunmamaktadir.'];
    }

    $display_name = $attrs['displayName'][0] ?? $attrs['cn'][0] ?? $kullanici_adi;
    $email        = $attrs['mail'][0] ?? '';

    ldapLog($kullanici_adi, 'Basarili giris', 'info');
    ldap_unbind($conn);

    return [
        'basarili'      => true,
        'kullanici_adi' => $kullanici_adi,
        'ad_soyad'      => $display_name,
        'email'         => $email,
        'dn'            => $user_dn,
    ];
}

function ldapGrupKontrol($conn, array $attrs, string $user_dn, string $grup_adi, string $base_dn): bool {
    // memberOf ile kontrol
    if (!empty($attrs['memberOf']['count'])) {
        $count = $attrs['memberOf']['count'];
        for ($i = 0; $i < $count; $i++) {
            if (stripos($attrs['memberOf'][$i], 'cn=' . $grup_adi . ',') !== false) {
                return true;
            }
        }
    }

    // Grup araması ile kontrol
    $filtre = '(&(objectClass=group)(cn=' . ldap_escape($grup_adi, '', LDAP_ESCAPE_FILTER) . ')(member=' . ldap_escape($user_dn, '', LDAP_ESCAPE_FILTER) . '))';
    $sonuc  = @ldap_search($conn, $base_dn, $filtre, ['cn']);
    if ($sonuc && ldap_count_entries($conn, $sonuc) > 0) {
        return true;
    }

    return false;
}

function ldapLog(string $kullanici, string $mesaj, string $seviye = 'info'): void {
    try {
        $db = getDB();
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = trim(explode(',', $ip)[0]);
        $db->prepare("
            INSERT INTO ldap_log (kullanici_adi, islem, seviye, ip_adresi, tarayici)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $kullanici,
            $mesaj,
            $seviye,
            $ip,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300)
        ]);
    } catch (Exception $e) {
        error_log('LDAP Log hatasi: ' . $e->getMessage());
    }
}

function ldapIslemLog(string $islem, string $detay = ''): void {
    sessionBaslat();
    $kullanici = $_SESSION['admin_kullanici'] ?? 'bilinmiyor';
    ldapLog($kullanici, $islem . ($detay ? ': ' . $detay : ''), 'info');
}
