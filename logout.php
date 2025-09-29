<?php
require_once __DIR__ . '/includes/functions.php'; // Ini sudah memanggil session_start()

// Pastikan pengguna memang login sebelum mencoba logout
if (is_logged_in()) {
    require_once __DIR__ . '/includes/db.php';
    log_activity($pdo, $_SESSION['user_id'], 'User logout');
}

// Hapus semua variabel sesi
$_SESSION = [];

// Hancurkan sesi
session_destroy();

// Mulai sesi baru untuk menyimpan pesan flash
session_start();
$_SESSION['success_message'] = "Anda telah berhasil logout.";

// Alihkan ke halaman login
redirect('/login.php');
?>