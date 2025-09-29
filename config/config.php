<?php
// File Konfigurasi Utama

// Pengaturan Database
// Ganti dengan detail koneksi database Anda.
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'youtube_uploader_db');

// Pengaturan Google API
// Dapatkan kredensial ini dari Google Cloud Console.
// Pastikan untuk menambahkan URI pengalihan yang benar: http://yourdomain.com/oauth_callback.php
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/youtube_uploader/oauth_callback.php'); // Sesuaikan dengan URL Anda

// Pengaturan Aplikasi
define('APP_URL', 'http://localhost/youtube_uploader'); // URL root aplikasi Anda
define('APP_NAME', 'YouTube Multi-Channel Uploader');
define('SESSION_LIFETIME', 3600); // Durasi sesi dalam detik (1 jam)

// Kunci Enkripsi
// Ganti ini dengan string acak yang sangat kuat!
// Anda bisa membuatnya dengan: openssl rand -base64 32
define('ENCRYPTION_KEY', 'your-super-secret-and-strong-encryption-key');
define('ENCRYPTION_CIPHER', 'AES-256-CBC');

// Direktori Upload
// Pastikan direktori ini ada dan dapat ditulis oleh server web.
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// Mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';
?>