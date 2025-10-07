<?php
$page_title = "Pengaturan Akun";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_credentials'])) {
    $client_id = trim($_POST['google_client_id']);
    $client_secret = trim($_POST['google_client_secret']);

    if (empty($client_id) || empty($client_secret)) {
        $_SESSION['error_message'] = "Client ID dan Client Secret tidak boleh kosong.";
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
            $_SESSION['error_message'] = "Gagal menyimpan kredensial: " . $e->getMessage();
        }
    }
    redirect('/admin/settings.php');
}

// Fetch current credentials to display
$stmt = $pdo->prepare("SELECT google_client_id FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch();
$current_client_id = !empty($user['google_client_id']) ? decrypt_data($user['google_client_id']) : null;


include __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Kredensial Google API</h6>
            </div>
            <div class="card-body">
                <p>Agar aplikasi ini dapat terhubung dengan akun Google Anda, Anda memerlukan "kunci" khusus dari Google. Kunci ini terdiri dari **Client ID** dan **Client Secret**. Ikuti petunjuk di samping untuk mendapatkannya, lalu masukkan di bawah ini.</p>

                <?php if ($current_client_id): ?>
                    <div class="alert alert-info">
                        <strong>Client ID saat ini:</strong>
                        <code><?php echo htmlspecialchars(substr($current_client_id, 0, 10)); ?>...<?php echo htmlspecialchars(substr($current_client_id, -4)); ?></code>
                        <br>
                        <small>Masukkan nilai baru di bawah ini untuk memperbaruinya.</small>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Anda belum mengatur kredensial Google API. Anda tidak akan bisa menambah profil OAuth atau mengupload video sampai ini diatur.
                    </div>
                <?php endif; ?>

                <form action="settings.php" method="POST">
                    <div class="mb-3">
                        <label for="google_client_id" class="form-label">Google Client ID</label>
                        <input type="password" class="form-control" id="google_client_id" name="google_client_id" placeholder="Masukkan Client ID Anda" required>
                    </div>
                    <div class="mb-3">
                        <label for="google_client_secret" class="form-label">Google Client Secret</label>
                        <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" placeholder="Masukkan Client Secret Anda" required>
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
                 <p>Anda bisa mendapatkan Client ID dan Secret dari Google Cloud Console.</p>
                 <ol class="small">
                     <li>Buka <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>.</li>
                     <li>Pilih proyek Anda dan buka menu **APIs & Services > Credentials**.</li>
                     <li>Buat **OAuth client ID** baru dengan tipe **Web application**.</li>
                     <li>Pastikan Anda menambahkan URI pengalihan yang benar: <br><code><?php echo APP_URL; ?>/oauth_callback.php</code></li>
                     <li>Salin Client ID dan Secret yang ditampilkan.</li>
                 </ol>
             </div>
         </div>
    </div>
</div>


<?php
include __DIR__ . '/../includes/footer.php';
?>