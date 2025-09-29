<?php
$page_title = "Logs Aktivitas";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin();

// Fetch upload logs with details
$stmt_uploads = $pdo->query(
    "SELECT u.id, u.title, u.upload_status, u.error_message, u.uploaded_at, us.username, c.title as channel_name
     FROM uploads u
     JOIN users us ON u.user_id = us.id
     JOIN channels c ON u.channel_id = c.id
     ORDER BY u.created_at DESC
     LIMIT 50"
);
$upload_logs = $stmt_uploads->fetchAll();

// Fetch general activity logs
$stmt_activity = $pdo->query(
    "SELECT a.id, a.action, a.details, a.ip_address, a.created_at, us.username
     FROM activity_logs a
     LEFT JOIN users us ON a.user_id = us.id
     ORDER BY a.created_at DESC
     LIMIT 100"
);
$activity_logs = $stmt_activity->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>
<p>Halaman ini menampilkan riwayat upload video dan aktivitas penting lainnya yang terjadi di dalam sistem.</p>

<!-- Upload Logs -->
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">Log Upload Video</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Judul Video</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upload_logs)): ?>
                        <tr><td colspan="6" class="text-center">Tidak ada riwayat upload.</td></tr>
                    <?php else: ?>
                        <?php foreach ($upload_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['uploaded_at'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['title']); ?></td>
                                <td><?php echo htmlspecialchars($log['channel_name']); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'secondary';
                                    if ($log['upload_status'] == 'success') $status_class = 'success';
                                    if ($log['upload_status'] == 'failed') $status_class = 'danger';
                                    if ($log['upload_status'] == 'uploading') $status_class = 'info';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo htmlspecialchars($log['upload_status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($log['upload_status'] == 'failed'): ?>
                                        <small class="text-danger"><?php echo htmlspecialchars($log['error_message']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- General Activity Logs -->
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">Log Aktivitas Sistem</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>IP Address</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activity_logs)): ?>
                        <tr><td colspan="5" class="text-center">Tidak ada aktivitas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($activity_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'Sistem'); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
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