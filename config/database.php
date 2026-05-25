<?php
// config/database.php

$localConfigPath = __DIR__ . '/local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) $localConfig = [];

$host = getenv('DB_HOST') ?: ($localConfig['db_host'] ?? 'localhost');
$db   = getenv('DB_NAME') ?: ($localConfig['db_name'] ?? '');
$user = getenv('DB_USER') ?: ($localConfig['db_user'] ?? '');
$pass = getenv('DB_PASS') ?: ($localConfig['db_pass'] ?? '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Jangan tampilkan error detail ke publik di production
    die("Koneksi Database Gagal. Cek config.");
    // throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Mulai session di sini agar tidak perlu dipanggil berulang kali
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

// Set Timezone Indonesia
date_default_timezone_set('Asia/Jakarta');
?>
