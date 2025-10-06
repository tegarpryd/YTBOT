<?php
// File Konfigurasi Inti (Versi 2)

// Langkah 1: Tentukan Kredensial Database (Satu-satunya konfigurasi dalam file)
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'youtube_uploader_db');

// Langkah 2: Buat Koneksi Database Awal
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Jika koneksi gagal, tidak ada yang bisa dilakukan.
    die("Koneksi ke database gagal. Periksa kredensial di config/config.php. Error: " . $e->getMessage());
}

// Langkah 3: Muat semua pengaturan dari tabel 'settings'
try {
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Langkah 4: Definisikan semua pengaturan sebagai konstanta
    foreach ($settings as $name => $value) {
        // Gunakan strtoupper untuk konvensi nama konstanta (e.g., app_name -> APP_NAME)
        if (!defined(strtoupper($name))) {
            define(strtoupper($name), $value);
        }
    }
} catch (\PDOException $e) {
    // Jika tabel settings tidak ada atau error
    die("Gagal memuat pengaturan dari database. Pastikan database telah diimpor dengan benar. Error: " . $e->getMessage());
}

// Validasi bahwa konstanta penting telah dimuat
if (!defined('APP_URL') || !defined('ENCRYPTION_KEY')) {
    die("Konfigurasi penting (APP_URL, ENCRYPTION_KEY) tidak ditemukan di database.");
}

// Direktori Upload (ini lebih baik tetap berbasis path server)
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// Mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Hapus variabel global $settings setelah selesai
unset($settings);
?>