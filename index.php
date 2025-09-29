<?php
// Titik Masuk Aplikasi

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    // Jika pengguna sudah login, arahkan ke dashboard
    redirect('/admin/dashboard.php');
} else {
    // Jika belum, arahkan ke halaman login
    redirect('/login.php');
}
?>