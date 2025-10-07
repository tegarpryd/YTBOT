<?php
// Halaman Pengaturan Akun (Versi 2 - Diperbaiki)
$page_title = "Pengaturan Akun";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_credentials'])) {
    $client_id = trim($_POST['google_client_id'] ?? '');
    $client_secret = trim($_POST['google_client_secret'] ?? '');

    // Validasi yang lebih ketat
    if (empty($client_id) || empty($client_secret)) {
        $_SESSION['error_message'] = "Client ID dan Client Secret tidak boleh kosong.";
    } elseif (!str_starts_with($client_id, '.apps.googleusercontent.com') && !str_starts_with($client_id, 'http')) {
        // Ini adalah validasi sederhana, ID asli biasanya diakhiri dengan .apps.googleusercontent.com
        // Namun kita longgarkan sedikit untuk format lain jika ada.
        // $_SESSION['error_message'] = "Format Google Client ID tampaknya tidak valid.";
    } else {
        try {
            // Enkripsi kredensial sebelum disimpan
            $encrypted_client_id = encrypt_data($client_id);
            $encrypted_client_secret = encrypt_data($client_secret);

            $stmt = $pdo->prepare(
                "UPDATE users SET google_client_id = :client_id, google_client_secret = :client_secret WHERE id = :user_id"
            );
            $stmt->execute([
                ':client_id' => $encrypted_client_id,
                ':client_secret' => $encrypted_client_secret,
                ':user_id' => $user_id
            ]);

            log_activity($pdo, $user_id, 'Memperbarui kredensial Google API');
            $_SESSION['success_message'] = "Kredensial Google API berhasil disimpan.";

        } catch (Exception $e) {
            error_log("Gagal menyimpan kredensial untuk user_id {$user_id}: " . $e->getMessage());
            $_SESSION['error_message'] = "Terjadi kesalahan teknis saat menyimpan kredensial.";
        }
    }
    redirect('/admin/settings.php');
}

// Ambil kredensial saat ini untuk ditampilkan (dengan dekripsi yang aman)
$current_client_id = null;
$credentials = get_current_user_google_credentials($pdo);
if ($credentials && isset($credentials['client_id'])) {
    $current_client_id = $credentials['client_id'];
}

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fa-solid fa-key me-2"></i>Kredensial Google API Anda</h6>
            </div>
            <div class="card-body">
                <p class="text-muted">Agar aplikasi ini dapat terhubung dengan akun Google Anda, Anda memerlukan "kunci" khusus dari Google. Kunci ini terdiri dari **Client ID** dan **Client Secret**. Ikuti petunjuk di samping untuk mendapatkannya, lalu masukkan di bawah ini.</p>

                <?php if ($current_client_id): ?>
                    <div class="alert alert-info">
                        <strong>Client ID saat ini:</strong>
                        <code><?php echo htmlspecialchars(substr($current_client_id, 0, 10)); ?>...<?php echo htmlspecialchars(substr($current_client_id, -4)); ?></code>
                        <br>
                        <small>Kredensial Anda sudah tersimpan. Masukkan nilai baru di bawah ini untuk memperbaruinya.</small>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Anda belum mengatur kredensial Google API.</strong> Anda tidak akan bisa menambah profil Google atau mengupload video sampai ini diatur.
                    </div>
                <?php endif; ?>

                <form action="settings.php" method="POST">
                    <div class="mb-3">
                        <label for="google_client_id" class="form-label">Google Client ID</label>
                        <input type="password" class="form-control" id="google_client_id" name="google_client_id" placeholder="Masukkan Client ID Anda di sini" required>
                    </div>
                    <div class="mb-3">
                        <label for="google_client_secret" class="form-label">Google Client Secret</label>
                        <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" placeholder="Masukkan Client Secret Anda di sini" required>
                    </div>
                    <div class="text-end">
                        <button type="submit" name="save_credentials" class="btn btn-primary">
                            <i class="fa-solid fa-save me-2"></i> Simpan Kredensial
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
         <div class="card shadow-sm">
             <div class="card-body">
                 <h5 class="card-title"><i class="fa-solid fa-circle-info me-2"></i> Di mana mendapatkan ini?</h5>
                 <ol class="small ps-3">
                     <li class="mb-2">Buka <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
                     <li class="mb-2">Pilih proyek Anda dan buka menu **APIs & Services > Credentials**.</li>
                     <li class="mb-2">Klik **+ CREATE CREDENTIALS** dan pilih **OAuth client ID**.</li>
                     <li class="mb-2">Pastikan Anda menambahkan **Authorized redirect URI** yang benar: <br><code class="user-select-all bg-light p-1 rounded d-block mt-1"><?php echo APP_URL; ?>/oauth_callback.php</code></li>
                     <li>Salin Client ID dan Secret yang ditampilkan.</li>
                 </ol>
             </div>
         </div>
    </div>
</div>


<?php
include __DIR__ . '/../includes/footer.php';
?>