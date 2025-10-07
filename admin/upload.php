<?php
$page_title = "Upload Video";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Fetch channels grouped by OAuth profile for the dropdown
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

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h2 mb-4"><?php echo htmlspecialchars($page_title); ?></h1>

<?php if (empty($grouped_channels)): ?>
    <div class="alert alert-warning">
        <strong>Anda belum memiliki channel.</strong> Silakan <a href="oauth_profiles.php" class="alert-link">tambahkan profil Google</a> terlebih dahulu untuk menyinkronkan channel Anda.
    </div>
<?php else: ?>
    <div class="card shadow">
        <div class="card-header">
            Formulir Upload Video
        </div>
        <div class="card-body">
            <form id="uploadForm" action="handle_upload.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Pilih Channel Tujuan (Bisa lebih dari satu)</label>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllChannels">Pilih Semua</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllChannels">Hapus Pilihan</button>
                    </div>
                    <div id="channelList" class="channel-list-container border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($grouped_channels as $email => $channels): ?>
                            <div class="mb-3">
                                <strong class="d-block border-bottom pb-1 mb-2"><i class="fa-brands fa-google me-2"></i><?php echo htmlspecialchars($email); ?></strong>
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
                    <div class="form-text">Pilih setidaknya satu channel.</div>
                </div>

                <div class="mb-3">
                    <label for="title" class="form-label">Judul Video</label>
                    <input type="text" class="form-control" id="title" name="title" required maxlength="100">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="description" name="description" rows="5" maxlength="5000"></textarea>
                </div>

                <div class="mb-3">
                    <label for="tags" class="form-label">Tags (pisahkan dengan koma)</label>
                    <input type="text" class="form-control" id="tags" name="tags" placeholder="misal: gaming, tutorial, vlog">
                </div>

                <div class="mb-3">
                    <label for="privacy_status" class="form-label">Status Privasi</label>
                    <select class="form-select" id="privacy_status" name="privacy_status" required>
                        <option value="private">Private</option>
                        <option value="unlisted">Unlisted</option>
                        <option value="public">Public</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="video_file" class="form-label">Pilih File Video</label>
                    <input class="form-control" type="file" id="video_file" name="video_file" accept="video/mp4,video/quicktime,video/x-msvideo" required>
                    <div class="form-text">
                        Video dengan durasi di bawah 60 detik akan otomatis dianggap sebagai YouTube Short.
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress mt-4 d-none" id="progressBarContainer">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <div id="uploadStatus" class="mt-2"></div>


                <div class="mt-4 text-end">
                    <button type="submit" id="submitButton" class="btn btn-primary">
                        <i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Video
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
// We will add JS for progress bar later in assets/js/script.js
include __DIR__ . '/../includes/footer.php';
?>