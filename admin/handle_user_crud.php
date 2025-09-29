<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_admin(); // Keamanan ganda, pastikan hanya admin

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/users.php');
}

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? null;

try {
    switch ($action) {
        case 'add':
        case 'edit':
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];

            if (empty($username) || empty($email) || empty($role)) {
                throw new Exception("Username, email, dan role tidak boleh kosong.");
            }
            if ($action === 'add' && empty($password)) {
                throw new Exception("Password wajib diisi untuk pengguna baru.");
            }
            if (!in_array($role, ['admin', 'user'])) {
                throw new Exception("Role tidak valid.");
            }

            if ($action === 'add') {
                // Tambah user baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
                $stmt->execute([':username' => $username, ':email' => $email, ':password' => $hashed_password, ':role' => $role]);
                $new_user_id = $pdo->lastInsertId();

                log_activity($pdo, $_SESSION['user_id'], 'User baru ditambahkan', 'user', $new_user_id, json_encode(['username' => $username]));
                $_SESSION['success_message'] = "Pengguna '{$username}' berhasil ditambahkan.";

            } else {
                // Edit user
                if (empty($user_id)) throw new Exception("User ID tidak ditemukan untuk diedit.");

                if (!empty($password)) {
                    // Jika password diisi, update password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, password = :password, role = :role WHERE id = :id");
                    $stmt->execute([':username' => $username, ':email' => $email, ':password' => $hashed_password, ':role' => $role, ':id' => $user_id]);
                } else {
                    // Jika password kosong, jangan update password
                    $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id");
                    $stmt->execute([':username' => $username, ':email' => $email, ':role' => $role, ':id' => $user_id]);
                }

                log_activity($pdo, $_SESSION['user_id'], 'User berhasil diedit', 'user', $user_id, json_encode(['username' => $username]));
                $_SESSION['success_message'] = "Pengguna '{$username}' berhasil diperbarui.";
            }
            break;

        case 'suspend':
            if (empty($user_id)) throw new Exception("User ID tidak ditemukan untuk suspend.");
            if ($user_id == $_SESSION['user_id']) throw new Exception("Anda tidak dapat menangguhkan akun Anda sendiri.");

            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = :id");
            $stmt->execute([':id' => $user_id]);

            log_activity($pdo, $_SESSION['user_id'], 'User ditangguhkan', 'user', $user_id);
            $_SESSION['success_message'] = "Pengguna berhasil ditangguhkan.";
            break;

        case 'activate':
            if (empty($user_id)) throw new Exception("User ID tidak ditemukan untuk aktivasi.");

            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id");
            $stmt->execute([':id' => $user_id]);

            log_activity($pdo, $_SESSION['user_id'], 'User diaktifkan', 'user', $user_id);
            $_SESSION['success_message'] = "Pengguna berhasil diaktifkan kembali.";
            break;

        default:
            throw new Exception("Aksi tidak dikenal.");
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Kode untuk duplicate entry
        $_SESSION['error_message'] = "Gagal: Username atau Email sudah ada yang menggunakan.";
    } else {
        $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
}

redirect('/admin/users.php');
?>