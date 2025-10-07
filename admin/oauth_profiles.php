<?php
$page_title = "Profil OAuth";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile'])) {
    $profile_id_to_delete = $_POST['profile_id'] ?? 0;

    // Verifikasi bahwa profil milik pengguna yang sedang login
    $stmt = $pdo->prepare("SELECT id, email FROM oauth_profiles WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $profile_id_to_delete, ':user_id' => $_SESSION['user_id']]);
    $profile = $stmt->fetch();

    if ($profile) {
        // Hapus profil. Channel terkait akan terhapus secara otomatis karena ON DELETE CASCADE.
        $deleteStmt = $pdo->prepare("DELETE FROM oauth_profiles WHERE id = :id");
        $deleteStmt->execute([':id' => $profile_id_to_delete]);

        log_activity($pdo, $_SESSION['user_id'], 'Profil OAuth dihapus', 'oauth_profile', $profile_id_to_delete, json_encode(['email' => $profile['email']]));
        $_SESSION['success_message'] = "Profil OAuth '{$profile['email']}' berhasil dihapus.";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus profil. Profil tidak ditemukan atau Anda tidak memiliki izin.";
    }
    redirect('/admin/oauth_profiles.php');
}

// Fetch all OAuth profiles for the logged-in user
$stmt = $pdo->prepare("SELECT * FROM oauth_profiles WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$profiles = $stmt->fetchAll();

// Get Google Auth URL for the "Add New" button
$google_login_url = '#'; // Default value
$credentials_error = null;
try {
    $credentials = get_current_user_google_credentials($pdo);
    if ($credentials) {
        $client = get_google_client($credentials['client_id'], $credentials['client_secret']);
        $google_login_url = $client->createAuthUrl();
    } else {
        // This will be caught and displayed as a message
        throw new Exception("Kredensial Google (Client ID/Secret) Anda belum diatur.");
    }
} catch (Exception $e) {
    $credentials_error = $e->getMessage();
}


include __DIR__ . '/../includes/header.php';
?>

<?php if ($credentials_error): ?>
<div class="alert alert-warning">
    <strong>Aksi Dibutuhkan:</strong> <?php echo htmlspecialchars($credentials_error); ?>
    <a href="settings.php" class="alert-link">Buka Halaman Pengaturan untuk menyiapkannya.</a>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
    <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="btn btn-primary <?php if ($credentials_error) echo 'disabled'; ?>">
        <i class="fa-brands fa-google me-2"></i> Tambah Profil OAuth Baru
    </a>
</div>

<p class="text-muted mb-4">Ini adalah daftar semua Akun Google yang telah Anda hubungkan. Setiap akun dapat memiliki banyak channel YouTube. Anda dapat menghubungkan lebih banyak akun atau menghapus yang tidak lagi diperlukan.</p>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Nama Profil (Email)</th>
                        <th>Ditambahkan Pada</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($profiles)): ?>
                        <tr>
                            <td colspan="4" class="text-center p-5">
                                <div class="display-4 text-muted mb-3"><i class="fa-brands fa-google"></i></div>
                                <h4>Belum Ada Profil Google yang Terhubung</h4>
                                <p class="text-muted">Hubungkan akun Google Anda untuk mulai menyinkronkan channel YouTube.</p>
                                <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="btn btn-primary mt-2 <?php if ($credentials_error) echo 'disabled'; ?>">
                                    <i class="fa-solid fa-plus me-2"></i>Hubungkan Akun Google Pertama Anda
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($profiles as $profile): ?>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-user-check text-success me-2"></i>
                                    <strong><?php echo htmlspecialchars($profile['profile_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($profile['email']); ?></small>
                                </td>
                                <td><?php echo date('d M Y, H:i', strtotime($profile['created_at'])); ?></td>
                                <td>
                                    <?php if ($profile['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="oauth_profiles.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus profil ini? Semua channel terkait juga akan dihapus.');" class="d-inline">
                                        <input type="hidden" name="profile_id" value="<?php echo $profile['id']; ?>">
                                        <button type="submit" name="delete_profile" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Hapus profil ini secara permanen dari sistem.">
                                            <i class="fa-solid fa-trash-can"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>