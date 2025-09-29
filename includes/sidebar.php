<?php
// Mendapatkan path skrip saat ini untuk menyorot menu aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="bg-dark border-end" id="sidebar-wrapper">
    <div class="sidebar-heading bg-dark text-white">
        <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="text-white text-decoration-none">
            <i class="fa-brands fa-youtube me-2"></i> <?php echo APP_NAME; ?>
        </a>
    </div>
    <div class="list-group list-group-flush">
        <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="list-group-item list-group-item-action list-group-item-dark p-3 <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge-high me-2"></i> Dashboard
        </a>
        <a href="<?php echo APP_URL; ?>/admin/upload.php" class="list-group-item list-group-item-action list-group-item-dark p-3 <?php echo ($current_page == 'upload.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cloud-arrow-up me-2"></i> Upload Video
        </a>
        <a href="<?php echo APP_URL; ?>/admin/oauth_profiles.php" class="list-group-item list-group-item-action list-group-item-dark p-3 <?php echo ($current_page == 'oauth_profiles.php') ? 'active' : ''; ?>">
            <i class="fa-brands fa-google me-2"></i> Profil OAuth
        </a>
        <a href="<?php echo APP_URL; ?>/admin/channels.php" class="list-group-item list-group-item-action list-group-item-dark p-3 <?php echo ($current_page == 'channels.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-tv me-2"></i> Daftar Channel
        </a>

        <?php if (is_admin()): // Tampilkan menu ini hanya jika pengguna adalah admin ?>
        <hr class="text-secondary">
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>ADMIN AREA</span>
        </h6>
        <a href="<?php echo APP_URL; ?>/admin/users.php" class="list-group-item list-group-item-action list-group-item-dark p-3 <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users-gear me-2"></i> Manajemen User
        </a>
        <a href="<?php echo APP_URL; ?>/admin/logs.php" class="list-group-item list-group-item-action list-group-item-dark p-3 <?php echo ($current_page == 'logs.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-clipboard-list me-2"></i> Activity Logs
        </a>
        <?php endif; ?>
    </div>
</div>