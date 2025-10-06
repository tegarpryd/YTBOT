<?php
// File untuk Fungsi-fungsi Pembantu

require_once __DIR__ . '/../config/config.php';

/**
 * Mengenkripsi string (misalnya, refresh token).
 * @param string $data Data yang akan dienkripsi.
 * @return string String terenkripsi dalam format base64.
 */
function encrypt_data($data) {
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Mendekripsi string yang dienkripsi oleh encrypt_data.
 * @param string $data String terenkripsi dalam format base64.
 * @return string|false Data asli atau false jika gagal.
 */
function decrypt_data($data) {
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    if (!$iv) return false;
    return openssl_decrypt($encrypted_data, ENCRYPTION_CIPHER, ENCRYPTION_KEY, 0, $iv);
}

/**
 * Mengalihkan pengguna ke halaman lain.
 * @param string $url URL tujuan.
 */
function redirect($url) {
    header("Location: " . APP_URL . $url);
    exit();
}

/**
 * Memeriksa apakah pengguna sudah login.
 * @return bool True jika login, false jika tidak.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Memeriksa apakah pengguna yang login adalah admin.
 * @return bool True jika admin, false jika tidak.
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Memastikan hanya pengguna yang login yang bisa mengakses halaman.
 */
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['error_message'] = "Anda harus login untuk mengakses halaman ini.";
        redirect('/login.php');
    }
}

/**
 * Memastikan hanya admin yang bisa mengakses halaman.
 */
function require_admin() {
    if (!is_admin()) {
        $_SESSION['error_message'] = "Akses ditolak. Halaman ini hanya untuk admin.";
        redirect('/admin/dashboard.php');
    }
}

/**
 * Mengambil kredensial Google untuk pengguna yang sedang login.
 * @param PDO $pdo
 * @return array|false Kredensial atau false jika tidak ditemukan/tidak login.
 */
function get_current_user_google_credentials($pdo) {
    if (!is_logged_in()) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT google_client_id, google_client_secret FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $credentials = $stmt->fetch();

    if (!$credentials || empty($credentials['google_client_id']) || empty($credentials['google_client_secret'])) {
        return false;
    }

    return [
        'client_id' => decrypt_data($credentials['google_client_id']),
        'client_secret' => decrypt_data($credentials['google_client_secret'])
    ];
}


/**
 * Membuat dan mengkonfigurasi Google API Client berdasarkan kredensial pengguna.
 * @param string $clientId
 * @param string $clientSecret
 * @return Google_Client Objek Google Client yang telah dikonfigurasi.
 * @throws Exception jika kredensial tidak valid.
 */
function get_google_client($clientId, $clientSecret) {
    if (empty($clientId) || empty($clientSecret)) {
        throw new Exception("Google Client ID atau Client Secret belum diatur untuk akun Anda. Silakan atur di halaman Pengaturan.");
    }

    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setRedirectUri(APP_URL . '/oauth_callback.php'); // Menggunakan konstanta dari DB
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setScopes([
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ]);
    return $client;
}

/**
 * Mencatat aktivitas pengguna ke database.
 * @param PDO $pdo Objek koneksi PDO.
 * @param int|null $user_id ID pengguna yang melakukan aksi.
 * @param string $action Deskripsi aksi.
 * @param string|null $target_type Tipe target (e.g., 'user', 'video').
 * @param int|null $target_id ID target.
 * @param string|null $details Detail tambahan.
 */
function log_activity($pdo, $user_id, $action, $target_type = null, $target_id = null, $details = null) {
    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:user_id, :action, :target_type, :target_id, :details, :ip_address)"
    );
    $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':target_type' => $target_type,
        ':target_id' => $target_id,
        ':details' => $details,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
    ]);
}

/**
 * Menampilkan pesan flash (error/sukses) dan menghapusnya dari session.
 * @param string $key Kunci session untuk pesan.
 * @param string $type Tipe bootstrap alert (e.g., 'danger', 'success').
 */
function display_flash_message($key, $type = 'danger') {
    if (isset($_SESSION[$key])) {
        echo '<div class="alert alert-' . htmlspecialchars($type) . '">' . htmlspecialchars($_SESSION[$key]) . '</div>';
        unset($_SESSION[$key]);
    }
}
?>