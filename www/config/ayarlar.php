<?php
function ayarGetir(string $grup, string $anahtar, string $varsayilan = ''): string {
    static $cache = [];
    $key = $grup . '.' . $anahtar;
    if (!isset($cache[$key])) {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT deger FROM ayarlar WHERE grup = ? AND anahtar = ?");
            $stmt->execute([$grup, $anahtar]);
            $row  = $stmt->fetch();
            $cache[$key] = ($row && $row['deger'] !== null) ? trim((string)$row['deger']) : $varsayilan;
        } catch (Exception $e) {
            $cache[$key] = $varsayilan;
        }
    }
    return $cache[$key];
}

function ayarKaydet(string $grup, string $anahtar, string $deger): void {
    $db = getDB();
    $db->prepare("
        INSERT INTO ayarlar (grup, anahtar, deger)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE deger = VALUES(deger)
    ")->execute([$grup, $anahtar, $deger]);
}

function ayarGrupGetir(string $grup): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT anahtar, deger FROM ayarlar WHERE grup = ?");
        $stmt->execute([$grup]);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['anahtar']] = trim((string)$row['deger']);
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

function smsSms(string $telefon, string $mesaj): array {
    $url        = ayarGetir('sms', 'http_url');
    $params_raw = ayarGetir('sms', 'http_params');
    $headers    = ayarGetir('sms', 'headers');
    $method     = strtoupper(ayarGetir('sms', 'http_method', 'GET'));
    $auth_type  = ayarGetir('sms', 'auth_type', 'none');
    $auth_user  = ayarGetir('sms', 'auth_user');
    $auth_pass  = ayarGetir('sms', 'auth_pass');

    if (empty($url)) {
        return ['basarili' => false, 'mesaj' => 'SMS sunucusu yapilandirilmamis.'];
    }

    // Değişkenleri yerleştir
    $params_str = str_replace(
        ['{telefon}', '{mesaj}', '$recipient', '$message'],
        [$telefon,    $mesaj,    $telefon,     $mesaj],
        $params_raw
    );

    // Boşlukları + ile değiştir
    $params_str = str_replace(' ', '+', $params_str);

    // Header'ları parse et
    $header_arr = [];
    if (!empty($headers)) {
        foreach (explode("\n", trim($headers)) as $h) {
            $h = trim($h);
            if ($h) $header_arr[] = $h;
        }
    }

    // URL oluştur
    if ($method === 'POST') {
        $full_url = $url;
    } else {
        // URL sonunda ? varsa direkt ekle, yoksa ? ekle
        $full_url = rtrim($url, '?') . '?' . $params_str;
    }

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL,            $full_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT,        15);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST,       true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params_str);
        $header_arr[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    if (!empty($header_arr)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_arr);
    }

    if ($auth_type === 'basic' && !empty($auth_user)) {
        curl_setopt($curl, CURLOPT_USERPWD, $auth_user . ':' . $auth_pass);
    }

    $response   = curl_exec($curl);
    $http_code  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($curl_error) {
        return ['basarili' => false, 'mesaj' => 'Baglanti hatasi: ' . $curl_error];
    }

    if ($http_code >= 200 && $http_code < 300) {
        // XML yanıtında ErrorCode kontrolü
        if (!empty($response) && preg_match('/<ErrorCode>(\d+)<\/ErrorCode>/', $response, $m)) {
            if ($m[1] === '0') {
                $packet_id = '';
                if (preg_match('/<PacketId>(\d+)<\/PacketId>/', $response, $pm)) {
                    $packet_id = $pm[1];
                }
                return [
                    'basarili' => true,
                    'mesaj'    => 'SMS gonderildi.' . ($packet_id ? ' (ID: ' . $packet_id . ')' : ''),
                    'yanit'    => $response
                ];
            }
            return ['basarili' => false, 'mesaj' => 'SMS hata kodu: ' . $m[1], 'yanit' => $response];
        }
        return ['basarili' => true, 'mesaj' => 'SMS gonderildi. (HTTP ' . $http_code . ')', 'yanit' => $response];
    }

    return ['basarili' => false, 'mesaj' => 'HTTP ' . $http_code . ': ' . substr($response, 0, 200)];
}
