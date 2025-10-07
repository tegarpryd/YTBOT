<?php
// Backend User CRUD Handler (Versi 2 - Diperbaiki)

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
            // Validasi Input Server-Side yang Kuat
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (empty($username) || empty($email) || empty($role)) {
                throw new Exception("Semua field wajib diisi: Username, Email, dan Role.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Format email tidak valid.");
            }
            if ($action === 'add' && empty($password)) {
                throw new Exception("Password wajib diisi untuk pengguna baru.");
            }
            if (!empty($password) && strlen($password) < 8) {
                throw new Exception("Password harus memiliki minimal 8 karakter.");
            }
            if (!in_array($role, ['admin', 'user'])) {
                throw new Exception("Role yang dipilih tidak valid.");
            }

            if ($action === 'add') {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                $new_user_id = $pdo->lastInsertId();

                log_activity($pdo, $_SESSION['user_id'], 'User baru ditambahkan', 'user', $new_user_id, json_encode(['username' => $username]));
                $_SESSION['success_message'] = "Pengguna '{$username}' berhasil ditambahkan.";

            } else { // 'edit'
                if (empty($user_id)) throw new Exception("User ID tidak valid untuk diedit.");

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $hashed_password, $role, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $user_id]);
                }

                log_activity($pdo, $_SESSION['user_id'], 'User berhasil diedit', 'user', $user_id, json_encode(['username' => $username]));
                $_SESSION['success_message'] = "Pengguna '{$username}' berhasil diperbarui.";
            }
            break;

        case 'suspend':
            if (empty($user_id)) throw new Exception("User ID tidak valid untuk ditangguhkan.");
            if ($user_id == $_SESSION['user_id']) throw new Exception("Anda tidak dapat menangguhkan akun Anda sendiri.");

            $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$user_id]);

            log_activity($pdo, $_SESSION['user_id'], 'User ditangguhkan', 'user', $user_id);
            $_SESSION['success_message'] = "Pengguna berhasil ditangguhkan.";
            break;

        case 'activate':
            if (empty($user_id)) throw new Exception("User ID tidak valid untuk diaktifkan.");

            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user_id]);

            log_activity($pdo, $_SESSION['user_id'], 'User diaktifkan', 'user', $user_id);
            $_SESSION['success_message'] = "Pengguna berhasil diaktifkan kembali.";
            break;

        default:
            throw new Exception("Aksi tidak dikenal atau tidak valid.");
    }

} catch (PDOException $e) {
    // Menangkap error spesifik dari database (misal: duplikat)
    error_log("Database Error di handle_user_crud.php: " . $e->getMessage());
    if ($e->getCode() == 23000) { // Kode untuk duplicate entry
        $_SESSION['error_message'] = "Gagal: Username atau Email sudah ada yang menggunakan. Silakan gunakan yang lain.";
    } else {
        $_SESSION['error_message'] = "Terjadi kesalahan pada database. Silakan coba lagi.";
    }
} catch (Exception $e) {
    // Menangkap semua error logika lainnya
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
}

redirect('/admin/users.php');
?>