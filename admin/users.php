<?php
$page_title = "Manajemen User";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin(); // Hanya admin yang boleh mengakses halaman ini

// Fetch all users from the database
$stmt = $pdo->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-action="add">
        <i class="bi bi-person-plus-fill me-2"></i> Tambah User Baru
    </button>
</div>

<p>Halaman ini memungkinkan Anda untuk mengelola pengguna yang dapat mengakses platform ini. Anda dapat menambah, mengedit, atau menangguhkan akun pengguna.</p>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Tanggal Terdaftar</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-warning edit-user-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#userModal"
                                        data-action="edit"
                                        data-id="<?php echo $user['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-role="<?php echo $user['role']; ?>">
                                    <i class="bi bi-pencil-fill"></i> Edit
                                </button>
                                <form action="handle_user_crud.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status pengguna ini?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <?php if ($user['status'] === 'active'): ?>
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="btn btn-sm btn-secondary">
                                            <i class="bi bi-pause-circle-fill"></i> Suspend
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-play-circle-fill"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm" action="handle_user_crud.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction">
                    <input type="hidden" name="user_id" id="userId">

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div id="passwordHelp" class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// We will add JS for this modal later in assets/js/script.js
include __DIR__ . '/../includes/footer.php';
?>