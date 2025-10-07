<?php
// Halaman Upload Video (Versi 2 - Diperbaiki)
$page_title = "Upload Video Baru";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Ambil semua channel yang siap digunakan, dikelompokkan berdasarkan profil Google
$stmt = $pdo->prepare(
    "SELECT c.id, c.title as channel_title, o.email as oauth_email
     FROM channels c
     JOIN oauth_profiles o ON c.oauth_profile_id = o.id
     WHERE o.user_id = :user_id
     ORDER BY o.email, c.title"
);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$channels_result = $stmt->fetchAll();

$grouped_channels = [];
foreach ($channels_result as $channel) {
    $grouped_channels[$channel['oauth_email']][] = $channel;
}

// Cek apakah pengguna siap untuk mengupload (punya API key dan channel)
$can_upload = false;
if (!empty($channels_result)) {
    $credentials = get_current_user_google_credentials($pdo);
    if ($credentials) {
        $can_upload = true;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<?php if (!$can_upload): ?>
    <div class="card shadow">
        <div class="card-body text-center p-5">
            <div class="display-4 text-muted mb-3"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h4>Anda Belum Siap Mengupload</h4>
            <p class="text-muted">Untuk mengupload video, Anda harus menyelesaikan langkah-langkah berikut terlebih dahulu:</p>
            <ul class="list-unstyled">
                <li>1. <a href="settings.php">Atur Kunci API Google Anda</a>.</li>
                <li>2. <a href="oauth_profiles.php">Hubungkan setidaknya satu Akun Google</a> yang memiliki channel.</li>
            </ul>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fa-solid fa-upload me-2"></i>Formulir Upload</h6>
        </div>
        <div class="card-body">
            <form id="uploadForm" action="handle_upload.php" method="POST" enctype="multipart/form-data">

                <!-- Pemilihan Channel -->
                <div class="mb-4">
                    <label class="form-label fw-bold">1. Pilih Channel Tujuan</label>
                    <p class="small text-muted">Pilih satu atau lebih channel untuk mengunggah video ini.</p>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllChannels">Pilih Semua</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllChannels">Hapus Pilihan</button>
                    </div>
                    <div id="channelList" class="channel-list-container border rounded p-3" style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($grouped_channels as $email => $channels): ?>
                            <div class="mb-3">
                                <strong class="d-block border-bottom pb-1 mb-2"><i class="fa-brands fa-google text-muted me-2"></i><?php echo htmlspecialchars($email); ?></strong>
                                <?php foreach ($channels as $channel): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="channel_ids[]" value="<?php echo $channel['id']; ?>" id="channel_<?php echo $channel['id']; ?>">
                                        <label class="form-check-label" for="channel_<?php echo $channel['id']; ?>">
                                            <?php echo htmlspecialchars($channel['channel_title']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Detail Video -->
                <div class="mb-4">
                    <label class="form-label fw-bold">2. Isi Detail Video</label>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="title" name="title" placeholder="Judul Video" required>
                    </div>
                    <div class="mb-3">
                        <textarea class="form-control" id="description" name="description" rows="5" placeholder="Deskripsi Video"></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="text" class="form-control" id="tags" name="tags" placeholder="Tags, pisahkan dengan koma (contoh: gaming, tutorial, vlog)">
                    </div>
                    <div class="mb-3">
                        <label for="privacy_status" class="form-label">Status Privasi</label>
                        <select class="form-select" id="privacy_status" name="privacy_status" required>
                            <option value="private">Private</option>
                            <option value="unlisted">Unlisted</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>

                <!-- Pilih File -->
                <div class="mb-4">
                    <label for="video_file" class="form-label fw-bold">3. Pilih File Video</label>
                    <input class="form-control" type="file" id="video_file" name="video_file" accept="video/mp4,video/quicktime,video/x-msvideo" required>
                    <div class="form-text">Video dengan durasi di bawah 60 detik akan dianggap sebagai YouTube Short.</div>
                </div>

                <!-- Tombol Submit & Progress Bar -->
                <div class="mt-4">
                     <button type="submit" id="submitButton" class="btn btn-primary w-100">
                        <i class="fa-solid fa-cloud-arrow-up me-2"></i>Mulai Upload
                    </button>
                </div>
                <div class="progress mt-3 d-none" id="progressBarContainer" style="height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <div id="uploadStatus" class="mt-3"></div>

            </form>
        </div>
    </div>
<?php endif; ?>

<?php
include __DIR__ . '/../includes/footer.php';
?>