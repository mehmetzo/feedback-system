<?php
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'hastane_feedback');
define('DB_USER', getenv('DB_USER') ?: 'hastane_user');
define('DB_PASS', getenv('DB_PASS') ?: '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci"
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Veritabani baglantisi kurulamadi.');
        }
    }
    return $pdo;
}

function sessionBaslat(): void {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

function adminKontrol(): void {
    sessionBaslat();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/index.php');
        exit;
    }
    // Session suresi 8 saat
    if (!empty($_SESSION['admin_giris_zaman']) &&
        (time() - $_SESSION['admin_giris_zaman']) > 28800) {
        session_destroy();
        header('Location: /admin/index.php?oturum=suredi');
        exit;
    }
}

function temizle(string $veri): string {
    return htmlspecialchars(strip_tags(trim($veri)), ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string {
    sessionBaslat();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfKontrol(): void {
    sessionBaslat();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf_token']) ||
            $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Guvenlik dogrulamasi basarisiz.');
        }
    }
}
