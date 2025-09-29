<?php
$page_title = "Dashboard";
require_once __DIR__ . '/../includes/db.php'; // Ini sudah menyertakan config, functions
require_once __DIR__ . '/../includes/functions.php';

require_login(); // Pastikan pengguna sudah login

// Mengambil statistik dari database
try {
    // Jumlah Pengguna
    $stmt_users = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt_users->fetchColumn();

    // Jumlah Profil OAuth
    $stmt_oauth = $pdo->prepare("SELECT COUNT(*) FROM oauth_profiles WHERE user_id = :user_id");
    $stmt_oauth->execute([':user_id' => $_SESSION['user_id']]);
    $total_oauth_profiles = $stmt_oauth->fetchColumn();

    // Jumlah Channel
    $stmt_channels = $pdo->prepare("SELECT COUNT(c.id) FROM channels c JOIN oauth_profiles o ON c.oauth_profile_id = o.id WHERE o.user_id = :user_id");
    $stmt_channels->execute([':user_id' => $_SESSION['user_id']]);
    $total_channels = $stmt_channels->fetchColumn();

    // Jumlah Upload Sukses
    $stmt_uploads = $pdo->prepare("SELECT COUNT(*) FROM uploads WHERE user_id = :user_id AND upload_status = 'success'");
    $stmt_uploads->execute([':user_id' => $_SESSION['user_id']]);
    $total_uploads = $stmt_uploads->fetchColumn();

    // Log aktivitas terbaru
    $stmt_logs = $pdo->prepare("SELECT a.*, u.username FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 10");
    $stmt_logs->execute();
    $recent_logs = $stmt_logs->fetchAll();

} catch (PDOException $e) {
    // Tangani error database dengan baik
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "Gagal memuat data dashboard.";
    // Inisialisasi variabel agar tidak error di view
    $total_users = $total_oauth_profiles = $total_channels = $total_uploads = 0;
    $recent_logs = [];
}


include __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- Kartu Statistik -->
<div class="row">
    <?php if (is_admin()): ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Pengguna</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fa-solid fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Profil OAuth Anda</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_oauth_profiles; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fa-brands fa-google fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Channel Anda</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_channels; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fa-brands fa-youtube fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Upload Sukses Anda</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_uploads; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fa-solid fa-cloud-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Aktivitas Terbaru -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Aktivitas Terbaru (Global)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Belum ada aktivitas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'Sistem'); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($log['details']); ?></small></td>
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