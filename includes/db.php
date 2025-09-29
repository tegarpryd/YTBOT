<?php
// File untuk Koneksi Database

require_once __DIR__ . '/../config/config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Pada lingkungan produksi, jangan tampilkan error detail.
    // Cukup catat ke log dan tampilkan pesan generik.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Tidak dapat terhubung ke database. Silakan periksa konfigurasi.");
}
?>