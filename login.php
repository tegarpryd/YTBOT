<?php
require_once __DIR__ . '/includes/db.php'; // Ini sudah menyertakan config dan functions
require_once __DIR__ . '/includes/functions.php';

// Jika sudah login, arahkan ke dashboard
if (is_logged_in()) {
    redirect('/admin/dashboard.php');
}

$error_message = '';

// Proses login form standar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password tidak boleh kosong.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role, status FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'active') {
                // Regenerasi session ID untuk keamanan
                session_regenerate_id(true);

                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                // Set session timeout
                $_SESSION['last_activity'] = time();


                log_activity($pdo, $user['id'], 'User login berhasil');
                redirect('/admin/dashboard.php');
            } else {
                $error_message = 'Akun Anda telah ditangguhkan.';
            }
        } else {
            $error_message = 'Username atau password salah.';
            log_activity($pdo, null, 'User login gagal', 'user', null, json_encode(['username' => $username]));
        }
    }
}

// Persiapan untuk Login dengan Google (tombol di UI)
$google_client = get_google_client();
$google_login_url = $google_client->createAuthUrl();

$page_title = "Login";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
        }
    </style>
</head>
<body>
    <div class="card login-card shadow-sm">
        <div class="card-body p-5">
            <h3 class="card-title text-center mb-4"><?php echo APP_NAME; ?></h3>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
             <?php display_flash_message('success_message', 'success'); ?>
             <?php display_flash_message('error_message', 'danger'); ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="login" class="btn btn-primary">Login</button>
                </div>
            </form>

            <hr class="my-4">

            <p class="text-center text-muted">Atau tambahkan profil OAuth baru:</p>

            <div class="d-grid">
                <a href="<?php echo htmlspecialchars($google_login_url); ?>" class="btn btn-danger">
                    <i class="bi bi-google"></i> Tambah & Autentikasi dengan Google
                </a>
            </div>
            <p class="small text-center mt-3 text-muted">
                Tombol ini digunakan untuk menambahkan Akun Google baru ke dalam sistem. Anda harus login terlebih dahulu untuk mengelolanya.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>